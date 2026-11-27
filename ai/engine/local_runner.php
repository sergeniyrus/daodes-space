<?php

/**
 * DAODES Local AI Runner
 *
 * Запускается только из CLI от владельца проекта.
 * Обрабатывает исключительно патчи со статусом approved.
 *
 * Режимы:
 * php local_runner.php --once
 * php local_runner.php --watch
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI access only.\n");
}

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/approval_queue.php';
require_once __DIR__ . '/apply_patch.php';

class LocalAIRunner
{
    private string $projectRoot;

    private int $pollInterval = 2;

    private bool $hasErrors = false;

    public function __construct(array $config)
    {
        $this->projectRoot = rtrim(
            $config['project_root'],
            DIRECTORY_SEPARATOR
        );

        if (!is_dir($this->projectRoot)) {
            throw new RuntimeException(
                'Project root does not exist.'
            );
        }

        $this->assertExecutionUser();
    }

    /**
     * Проверяем, что Runner запущен от владельца проекта.
     */
    private function assertExecutionUser(): void
    {
        if (!function_exists('posix_geteuid')) {
            throw new RuntimeException(
                'POSIX extension is required to verify the Runner user.'
            );
        }

        $currentUid = posix_geteuid();
        $projectUid = fileowner($this->projectRoot);

        if ($projectUid === false || $currentUid !== $projectUid) {
            $currentUser = posix_getpwuid($currentUid);
            $projectUser = $projectUid !== false
                ? posix_getpwuid($projectUid)
                : false;

            throw new RuntimeException(
                'Runner must run as the project owner. ' .
                'Current user: ' .
                ($currentUser['name'] ?? 'unknown') .
                '; project owner: ' .
                ($projectUser['name'] ?? 'unknown') .
                '.'
            );
        }
    }

    /**
     * Запуск одного прохода.
     */
    public function runOnce(): int
    {
        echo "\n";
        echo "====================================\n";
        echo "DAODES LOCAL AI RUNNER\n";
        echo "====================================\n";

        echo 'Project: ' . $this->projectRoot . "\n";
        echo 'Time: ' . date('Y-m-d H:i:s') . "\n";

        try {
            $items = $this->loadQueueItems();
        } catch (Throwable $e) {
            $this->writeError(
                'Unable to load approval queue: ' . $e->getMessage()
            );

            return 1;
        }

        $approved = [];

        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? (
                is_string($key) ? $key : null
            );

            if (
                ($item['status'] ?? null) !== 'approved' ||
                !is_string($id) ||
                !preg_match('/^[a-f0-9]{32}$/', $id)
            ) {
                continue;
            }

            $approved[] = $id;
        }

        if (empty($approved)) {
            echo "No approved patches.\n";
            return 0;
        }

        echo 'Approved patches found: ' . count($approved) . "\n";

        foreach ($approved as $id) {
            $this->processPatch($id);
        }

        return $this->hasErrors ? 1 : 0;
    }

    /**
     * Получение элементов очереди.
     */
    private function loadQueueItems(): array
    {
        $queue = ApprovalQueue::load();

        if (!is_array($queue)) {
            throw new RuntimeException(
                'ApprovalQueue::load() returned invalid data.'
            );
        }

        // Поддержка распространённых вариантов структуры очереди.
        if (isset($queue['items']) && is_array($queue['items'])) {
            return $queue['items'];
        }

        if (isset($queue['patches']) && is_array($queue['patches'])) {
            return $queue['patches'];
        }

        return $queue;
    }

    /**
     * Применение одного одобренного патча.
     */
    private function processPatch(string $id): void
    {
        echo "\n";
        echo "------------------------------------\n";
        echo "Patch: {$id}\n";

        try {
            /*
             * Внутри applyApprovedById выполняется атомарный переход:
             *
             * approved -> applying -> applied
             *
             * Если другой Runner уже захватил патч,
             * повторное применение не должно произойти.
             */
            PatchApplier::applyApprovedById($id);

            echo "Result: applied\n";

        } catch (Throwable $e) {
            $this->handlePatchError($id, $e);
        }
    }

    /**
     * Обработка ошибки применения.
     */
    private function handlePatchError(
        string $id,
        Throwable $e
    ): void {
        try {
            $item = ApprovalQueue::find($id);
            $status = $item['status'] ?? 'unknown';

            /*
             * Если статус уже изменился другим Runner,
             * это может быть нормальная гонка за патч.
             */
            if (
                $status === 'applying' ||
                $status === 'applied'
            ) {
                echo "Patch is already being processed or completed.\n";
                echo "Current status: {$status}\n";
                return;
            }

            $this->writeError(
                "Patch {$id}: {$e->getMessage()}"
            );

            echo "Current status: {$status}\n";

        } catch (Throwable $queueError) {
            $this->writeError(
                "Patch {$id}: {$e->getMessage()}"
            );

            $this->writeError(
                'Unable to inspect queue status: ' .
                $queueError->getMessage()
            );
        }
    }

    /**
     * Постоянный режим.
     */
    public function watch(): int
    {
        echo "Runner started in WATCH mode.\n";
        echo "Polling interval: {$this->pollInterval} seconds.\n";
        echo "Press Ctrl+C to stop.\n";

        while (true) {
            $this->hasErrors = false;

            $this->runOnce();

            sleep($this->pollInterval);
        }

        return 0;
    }

    /**
     * Вывод ошибки.
     */
    private function writeError(string $message): void
    {
        $this->hasErrors = true;

        fwrite(
            STDERR,
            '[AI RUNNER ERROR] ' . $message . "\n"
        );
    }
}

try {
    $mode = $argv[1] ?? null;

    if (!in_array($mode, ['--once', '--watch'], true)) {
        fwrite(
            STDERR,
            "Usage:\n" .
            "  php local_runner.php --once\n" .
            "  php local_runner.php --watch\n"
        );

        exit(1);
    }

    $runner = new LocalAIRunner($config);

    if ($mode === '--once') {
        exit($runner->runOnce());
    }

    exit($runner->watch());

} catch (Throwable $e) {
    fwrite(
        STDERR,
        "\nAI RUNNER ERROR: " . $e->getMessage() . "\n"
    );

    exit(1);
}