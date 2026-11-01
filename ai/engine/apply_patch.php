
<?php

require_once __DIR__ . '/approval_queue.php';

class PatchApplier
{
    private static function projectRoot(): string
    {
        $config = require __DIR__ . '/config.php';

        $root = realpath($config['project_root'] ?? '');

        if ($root === false || !is_dir($root)) {
            throw new RuntimeException(
                'Invalid project root.'
            );
        }

        return $root;
    }

    /**
     * Старый метод сохраняем.
     * Он только отправляет патч в очередь.
     */
    public static function apply(string $patchFile): void
    {
        if (!is_file($patchFile) || !is_readable($patchFile)) {
            throw new RuntimeException(
                'Patch file does not exist or is not readable.'
            );
        }

        $patch = file_get_contents($patchFile);

        if ($patch === false || trim($patch) === '') {
            throw new RuntimeException(
                'Empty patch file.'
            );
        }

        $patchData = [
            'diff' => $patch,
            'project_root' => self::projectRoot(),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $item = ApprovalQueue::add($patchData);

        echo "Patch sent to approval queue.\n";
        echo "Patch ID: " . $item['id'] . "\n";
    }

    /**
     * Запуск применения по ID одобренного патча.
     */
    public static function applyApprovedById(string $id): void
    {
        $item = ApprovalQueue::claimForApply($id);

        try {

            $patch = $item['patch'] ?? [];

            if (!is_array($patch)) {
                throw new RuntimeException(
                    'Invalid patch data.'
                );
            }

            $patch['_queue_id'] = $id;

            self::applyApproved($patch);

            ApprovalQueue::transition(
                $id,
                'applying',
                'applied'
            );

            echo "Approved patch applied successfully.\n";

        } catch (Throwable $e) {

            try {

                $current = ApprovalQueue::find($id);

                if (
                    $current !== null &&
                    ($current['status'] ?? null) === 'applying'
                ) {
                    ApprovalQueue::transition(
                        $id,
                        'applying',
                        'failed'
                    );
                }

            } catch (Throwable $queueError) {

                error_log(
                    'AI queue status error: ' .
                    $queueError->getMessage()
                );
            }

            throw $e;
        }
    }

    /**
     * Фактическое применение.
     * Разрешено только для патча со статусом applying,
     * полученного через applyApprovedById().
     */
    public static function applyApproved(array $patch): void
    {
        $id = $patch['_queue_id'] ?? null;

        if (!is_string($id) || $id === '') {
            throw new RuntimeException(
                'Missing approved patch ID.'
            );
        }

        $queueItem = ApprovalQueue::find($id);

        if (
            $queueItem === null ||
            ($queueItem['status'] ?? null) !== 'applying'
        ) {
            throw new RuntimeException(
                'Patch is not authorized for application.'
            );
        }

        $diff = $patch['diff'] ?? null;

        if (!is_string($diff) || trim($diff) === '') {
            throw new RuntimeException(
                'Empty patch diff.'
            );
        }

        $checksum = hash('sha256', $diff);

        if (!hash_equals(
            (string) ($queueItem['sha256'] ?? ''),
            $checksum
        )) {
            throw new RuntimeException(
                'Patch integrity check failed.'
            );
        }

        self::ProjectRootCheck($patch);

        self::applyDiff($diff);
    }

    /**
     * Проверка проекта, к которому относится патч.
     */
    private static function ProjectRootCheck(array $patch): void
    {
        $root = self::projectRoot();

        $patchRoot = $patch['project_root'] ?? null;

        if (!is_string($patchRoot) || realpath($patchRoot) !== $root) {
            throw new RuntimeException(
                'Patch project root does not match configured project root.'
            );
        }
    }

    /**
     * Безопасное применение unified diff.
     */
    private static function applyDiff(string $diff): void
    {
        $root = self::projectRoot();

        $storage = $root . '/storage';

        if (!is_dir($storage) || !is_writable($storage)) {
            throw new RuntimeException(
                'Storage directory is not writable.'
            );
        }

        $lockFile = $storage . '/ai_apply.lock';

        $lock = fopen($lockFile, 'c');

        if ($lock === false) {
            throw new RuntimeException(
                'Unable to open AI apply lock.'
            );
        }

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);

            throw new RuntimeException(
                'Unable to acquire AI apply lock.'
            );
        }

        $temporaryFile = null;

        try {

            // Проверяем, что это Git-репозиторий.
            $gitRoot = self::runGit(
                $root,
                ['rev-parse', '--show-toplevel']
            );

            if (realpath(trim($gitRoot['output'])) !== $root) {
                throw new RuntimeException(
                    'Git repository root does not match project root.'
                );
            }

            // Не затрагиваем существующие изменения пользователя.
            $status = self::runGit(
                $root,
                ['status', '--porcelain', '--untracked-files=all']
            );

            if (trim($status['output']) !== '') {
                throw new RuntimeException(
                    "Git working tree is not clean. Commit or safely save existing changes before applying AI patches.\n" .
                    $status['output']
                );
            }

            // Фиксируем исходный HEAD.
            $headResult = self::runGit(
                $root,
                ['rev-parse', 'HEAD']
            );

            $originalHead = trim($headResult['output']);

            if ($originalHead === '') {
                throw new RuntimeException(
                    'Unable to determine original Git HEAD.'
                );
            }

            $temporaryFile = tempnam(
                $storage,
                'ai_patch_'
            );

            if ($temporaryFile === false) {
                throw new RuntimeException(
                    'Unable to create temporary patch file.'
                );
            }

            if (file_put_contents(
                $temporaryFile,
                $diff,
                LOCK_EX
            ) === false) {
                throw new RuntimeException(
                    'Unable to write temporary patch.'
                );
            }

            // Сначала проверяем возможность применения.
            $check = self::runGit(
                $root,
                [
                    'apply',
                    '--check',
                    $temporaryFile,
                ]
            );

            if ($check['code'] !== 0) {
                throw new RuntimeException(
                    "Git patch validation failed:\n" .
                    $check['output']
                );
            }

            // Перед применением убеждаемся, что HEAD не изменился.
            $currentHead = self::runGit(
                $root,
                ['rev-parse', 'HEAD']
            );

            if (trim($currentHead['output']) !== $originalHead) {
                throw new RuntimeException(
                    'Git HEAD changed during patch validation.'
                );
            }

            // Фактическое применение.
            $apply = self::runGit(
                $root,
                [
                    'apply',
                    $temporaryFile,
                ]
            );

            if ($apply['code'] !== 0) {

                // Восстанавливаем только при неизменном HEAD.
                $headAfterFailure = self::runGit(
                    $root,
                    ['rev-parse', 'HEAD']
                );

                if (trim($headAfterFailure['output']) === $originalHead) {

                    self::runGit(
                        $root,
                        [
                            'reset',
                            '--hard',
                            $originalHead,
                        ]
                    );

                }

                throw new RuntimeException(
                    "Git patch application failed:\n" .
                    $apply['output']
                );
            }

        } finally {

            if ($temporaryFile !== null && file_exists($temporaryFile)) {
                unlink($temporaryFile);
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Выполнение Git-команды без shell-интерполяции аргументов.
     */
    private static function runGit(
        string $root,
        array $arguments
    ): array {
        $command = 'git -C ' . escapeshellarg($root);

        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg((string) $argument);
        }

        $output = [];

        exec(
            $command . ' 2>&1',
            $output,
            $code
        );

        return [
            'code' => $code,
            'output' => implode("\n", $output),
        ];
    }

    /**
     * Сохранено для совместимости с прежней архитектурой.
     */
    private static function backup(): void
    {
        // Перед применением проверяется чистота Git.
        // Исходный HEAD используется как точка восстановления.
        $root = self::projectRoot();

        $result = self::runGit(
            $root,
            ['rev-parse', 'HEAD']
        );

        if ($result['code'] !== 0 || trim($result['output']) === '') {
            throw new RuntimeException(
                'Unable to establish Git backup point.'
            );
        }
    }

    /**
     * Сохранено для совместимости.
     * Не выполняем безусловный reset HEAD~1.
     */
    private static function rollback(): void
    {
        throw new RuntimeException(
            'Rollback requires the original verified Git HEAD.'
        );
    }
}
