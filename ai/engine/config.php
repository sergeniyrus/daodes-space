<?php

$projectRoot = dirname(__DIR__, 2);

/*
 * CLI-запуск не загружает Laravel bootstrap, поэтому читаем нужные значения
 * из .env самостоятельно. Уже заданные переменные окружения имеют приоритет.
 */
$envFile = $projectRoot . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

$storagePath = getenv('DAODES_STORAGE_PATH') ?: $projectRoot . '/storage';

return [
    'ollama_url' => getenv('OLLAMA_URL') ?: 'http://127.0.0.1:11434',

    'models' => [
        'code' => getenv('OLLAMA_CODE_MODEL') ?: 'qwen2.5-coder:1.5b',
        'architecture' => getenv('OLLAMA_ARCH_MODEL') ?: 'qwen3:4b',
    ],

    'ollama_connect_timeout' => 10,
    'ollama_timeout' => 600,
    'ollama_keep_alive' => 0,
    'ollama_options' => [
        'num_ctx' => 4096,
        'num_batch' => 128,
        'num_thread' => 6,
        'temperature' => 0.1,
    ],
    'ollama_num_predict' => [
        'architecture' => 900,
        'code' => 1800,
    ],

    'project_root' => $projectRoot,
    'storage_path' => rtrim($storagePath, DIRECTORY_SEPARATOR),
    'rules_path' => $projectRoot . '/ai/rules',

    // Ограничения контекста для машины с 6 ГБ VRAM.
    'max_context_files' => 8,
    'max_file_bytes' => 1800,
    'max_context_bytes' => 6000,
    'max_rules_bytes' => 2500,
    'max_generator_rules_bytes' => 1500,
    'max_analysis_bytes' => 3500,
    'max_analyzer_context_bytes' => 8000,
    'max_generator_context_bytes' => 10000,
    'max_diff_bytes' => 12000,
    'max_task_chars' => 700,

    'ignore_paths' => [
        '.git',
        '.vite',
        '.cache',
        '.next',
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        'public/build',
        'public/storage',
        'dist',
        'coverage',
        'tmp',
        'temp',
    ],

    'allowed_extensions' => [
        'php', 'blade.php', 'js', 'ts', 'css', 'json', 'md', 'yml', 'yaml',
    ],
];
