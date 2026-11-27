<?php

$projectRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/context_builder.php';
require_once __DIR__ . '/rule_engine.php';
require_once __DIR__ . '/analyzer.php';
require_once __DIR__ . '/patch_generator.php';
require_once __DIR__ . '/apply_patch.php';

$storagePath = $config['storage_path'];
$logPath = $storagePath . '/logs/ai-engine.log';

if (!is_dir($storagePath) && !mkdir($storagePath, 0775, true) && !is_dir($storagePath)) {
    fwrite(STDERR, "AI Engine: не удалось создать storage.\n");
    exit(1);
}

if (!is_dir(dirname($logPath)) && !mkdir(dirname($logPath), 0775, true) && !is_dir(dirname($logPath))) {
    fwrite(STDERR, "AI Engine: не удалось создать каталог логов.\n");
    exit(1);
}

// Очередь и run.php должны использовать один и тот же storage-каталог.
putenv('DAODES_STORAGE_PATH=' . $storagePath);

function aiLog(string $message, string $level = 'INFO'): void
{
    global $logPath;

    $line = '[' . date(DATE_ATOM) . '] [' . $level . '] ' . $message . PHP_EOL;
    $maxBytes = 5 * 1024 * 1024;

    if (is_file($logPath) && filesize($logPath) >= $maxBytes) {
        @rename($logPath, $logPath . '.' . date('Ymd-His') . '.old');
    }

    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

$task = trim(implode(' ', array_slice($argv, 1)));
if ($task === '') {
    $task = 'Проведи консервативный анализ переданных исходников DAODES. Создавай патч только при наличии конкретной, подтверждённой исходным кодом ошибки. Если безопасное изменение не обосновано, не создавай патч.';
}
$task = substr($task, 0, (int) ($config['max_task_chars'] ?? 700));

$runLockPath = $storagePath . '/ai_engine_run.lock';
$runLock = fopen($runLockPath, 'c');

if ($runLock === false || !flock($runLock, LOCK_EX | LOCK_NB)) {
    if (is_resource($runLock)) {
        fclose($runLock);
    }

    fwrite(STDERR, "AI Engine уже выполняется. Второй запуск запрещён.\n");
    exit(1);
}

$runGit = static function (array $arguments) use ($config): Symfony\Component\Process\Process {
    $process = new Symfony\Component\Process\Process(
        array_merge(['git'], $arguments),
        $config['project_root']
    );
    $process->setTimeout(30);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new RuntimeException(
            'Git завершился с ошибкой: ' .
            trim($process->getErrorOutput() . "\n" . $process->getOutput())
        );
    }

    return $process;
};

$assertCleanTree = static function () use ($runGit): void {
    $status = trim($runGit([
        'status',
        '--porcelain',
        '--untracked-files=all',
    ])->getOutput());

    if ($status !== '') {
        $preview = implode("\n", array_slice(explode("\n", $status), 0, 20));
        throw new RuntimeException(
            "Рабочая копия Git не чистая. Генерация отменена, чтобы diff соответствовал base_commit.\n" .
            "Сначала проверьте и сохраните нужные изменения в Git.\n" . $preview
        );
    }
};

try {
    aiLog('Запуск AI Engine.');
    aiLog('Модель анализа: ' . $config['models']['architecture']);
    aiLog('Модель генерации: ' . $config['models']['code']);
    aiLog('Ограничения: num_ctx=' . $config['ollama_options']['num_ctx'] .
        ', num_batch=' . $config['ollama_options']['num_batch'] .
        ', max_context_bytes=' . $config['max_context_bytes']);

    // Контекст читается из рабочей директории, поэтому она должна соответствовать HEAD.
    $assertCleanTree();

    $head = trim($runGit(['rev-parse', 'HEAD'])->getOutput());
    if (!preg_match('/^[a-f0-9]{40,64}$/i', $head)) {
        throw new RuntimeException('Не удалось определить корректный Git commit.');
    }
    $baseCommit = $head;

    aiLog('Git working tree чистый. Base commit: ' . $baseCommit);
    aiLog('Проверка доступности моделей Ollama.');

    OllamaClient::ensureModelAvailable($config['models']['architecture'], $config);
    OllamaClient::ensureModelAvailable($config['models']['code'], $config);

    aiLog('Обе модели доступны.');
    aiLog('Сбор контекста проекта.');

    $context = ContextBuilder::build($config['project_root'], $config, $task);
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($contextJson === false) {
        throw new RuntimeException('Не удалось сериализовать контекст проекта.');
    }

    $contextBytes = strlen($contextJson);
    aiLog('Контекст собран: файлов=' . count($context) . ', JSON bytes=' . $contextBytes);
    aiLog('Файлы контекста: ' . implode(', ', array_column($context, 'path')));

    aiLog('Загрузка правил.');
    $rules = RuleEngine::loadRules($config['rules_path']);
    $rules = substr($rules, 0, (int) ($config['max_rules_bytes'] ?? 2500));
    aiLog('Правила загружены: bytes=' . strlen($rules));

    aiLog('Этап 1/2: анализ архитектуры.');
    $analysis = Analyzer::analyze($context, $rules, $task, $config);

    if (!is_array($analysis) || trim((string) ($analysis['response'] ?? '')) === '') {
        throw new RuntimeException('Архитектурная модель не вернула текст анализа.');
    }

    aiLog('Анализ завершён.');
    aiLog('Этап 2/2: генерация unified diff с исходниками.');

    $diff = PatchGenerator::generate($analysis, $config, $context, $task, $rules);
    aiLog('Diff получен: bytes=' . strlen($diff));

    // Проверяем, что за время работы моделей никто не изменил рабочую копию.
    $assertCleanTree();
    $headAfterGeneration = trim($runGit(['rev-parse', 'HEAD'])->getOutput());

    if (!hash_equals($baseCommit, $headAfterGeneration)) {
        throw new RuntimeException('Git HEAD изменился во время генерации. Патч не будет поставлен в очередь.');
    }

    $patchData = [
        'diff' => $diff,
        'base_commit' => $baseCommit,
        'created_at' => date(DATE_ATOM),
        'models' => $config['models'],
        'context_files' => array_column($context, 'path'),
        'task' => $task,
    ];

    $patchFile = $storagePath . '/ai_last_patch.json';
    $json = json_encode(
        $patchData,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException('Не удалось сериализовать подготовленный патч.');
    }

    $temporaryPatchFile = $patchFile . '.' . bin2hex(random_bytes(6)) . '.tmp';

    if (file_put_contents($temporaryPatchFile, $json, LOCK_EX) === false) {
        throw new RuntimeException('Не удалось записать временный файл патча.');
    }

    @chmod($temporaryPatchFile, 0660);

    if (!rename($temporaryPatchFile, $patchFile)) {
        @unlink($temporaryPatchFile);
        throw new RuntimeException('Не удалось атомарно сохранить ai_last_patch.json.');
    }

    aiLog('Промежуточный патч сохранён: ' . $patchFile);

    $queued = PatchApplier::apply($patchFile);
    aiLog('Патч добавлен в очередь: ' . $queued['id']);
    aiLog('Откройте /admin/ai/approval для проверки и одобрения.');
} catch (Throwable $exception) {
    aiLog(get_class($exception) . ': ' . $exception->getMessage(), 'ERROR');
    fwrite(STDERR, 'AI Engine error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    flock($runLock, LOCK_UN);
    fclose($runLock);
}
