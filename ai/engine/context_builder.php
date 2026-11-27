<?php

class ContextBuilder
{
    public static function build(string $path, array $config, string $task = ''): array
    {
        $root = realpath($path);

        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Каталог проекта не найден: ' . $path);
        }

        $ignorePaths = $config['ignore_paths'] ?? [];
        $allowedExtensions = $config['allowed_extensions'] ?? [
            'php', 'blade.php', 'js', 'ts', 'css', 'json', 'md', 'yml', 'yaml',
        ];

        $maxFiles = max(1, (int) ($config['max_context_files'] ?? 8));
        $maxFileBytes = max(1, (int) ($config['max_file_bytes'] ?? 1800));
        $maxTotalBytes = max(1, (int) ($config['max_context_bytes'] ?? 6000));
        $taskTerms = self::extractTaskTerms($task);

        $paths = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));

            if (self::isIgnored($relative, $ignorePaths)) {
                continue;
            }

            if (!self::hasAllowedExtension($relative, $allowedExtensions)) {
                continue;
            }

            if ($file->getSize() > $maxFileBytes || !is_readable($absolute)) {
                continue;
            }

            $paths[] = [
                'absolute' => $absolute,
                'relative' => $relative,
                'size' => $file->getSize(),
                'priority' => self::priority($relative, $taskTerms),
            ];
        }

        // Сначала наиболее релевантные задаче файлы, затем стабильная сортировка по пути.
        usort($paths, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return strcmp($a['relative'], $b['relative']);
        });

        $result = [];

        foreach ($paths as $item) {
            if (count($result) >= $maxFiles) {
                break;
            }

            $content = file_get_contents($item['absolute']);

            if (
                $content === false ||
                strpos($content, "\0") !== false ||
                preg_match('//u', $content) !== 1
            ) {
                continue;
            }

            // Учитываем фактический размер JSON, включая пути, кавычки и экранирование.
            $candidate = $result;
            $candidate[] = [
                'path' => $item['relative'],
                'content' => $content,
            ];

            $candidateJson = json_encode(
                $candidate,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if (
                $candidateJson === false ||
                strlen($content) > $maxFileBytes ||
                strlen($candidateJson) > $maxTotalBytes
            ) {
                continue;
            }

            $result = $candidate;
        }

        if ($result === []) {
            throw new RuntimeException(
                'Не удалось собрать контекст: нет доступных файлов, соответствующих ограничениям.'
            );
        }

        return $result;
    }

    private static function extractTaskTerms(string $task): array
    {
        $task = function_exists('mb_strtolower')
            ? mb_strtolower($task, 'UTF-8')
            : strtolower($task);

        $terms = preg_split('/[^\pL\pN_]+/u', $task, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = [];

        foreach ($terms as $term) {
            if (strlen($term) >= 3) {
                $result[$term] = true;
            }
        }

        return array_keys($result);
    }

    private static function priority(string $path, array $taskTerms): int
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($path, 'UTF-8')
            : strtolower($path);

        $score = 0;

        if (str_starts_with($normalized, 'app/http/controllers/')) {
            $score += 45;
        } elseif (str_starts_with($normalized, 'app/services/')) {
            $score += 42;
        } elseif (str_starts_with($normalized, 'app/models/')) {
            $score += 38;
        } elseif (str_starts_with($normalized, 'routes/')) {
            $score += 40;
        } elseif (str_starts_with($normalized, 'app/')) {
            $score += 25;
        } elseif (str_starts_with($normalized, 'resources/views/')) {
            $score += 18;
        } elseif (str_starts_with($normalized, 'tests/')) {
            $score += 15;
        } elseif (str_starts_with($normalized, 'config/')) {
            $score += 12;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $score += match ($extension) {
            'php' => 12,
            'js', 'ts' => 8,
            'css' => 5,
            'json', 'yml', 'yaml' => 3,
            'md' => 1,
            default => 0,
        };

        foreach ($taskTerms as $term) {
            if ($term !== '' && str_contains($normalized, $term)) {
                $score += 20;
            }
        }

        return $score;
    }

    private static function isIgnored(string $relative, array $ignorePaths): bool
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');

        foreach ($ignorePaths as $ignore) {
            $ignore = trim(str_replace('\\', '/', (string) $ignore), '/');

            if ($ignore === '') {
                continue;
            }

            if ($relative === $ignore || str_starts_with($relative, $ignore . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function hasAllowedExtension(string $path, array $extensions): bool
    {
        $lowerPath = strtolower($path);

        foreach ($extensions as $extension) {
            $extension = strtolower((string) $extension);

            if ($extension === 'blade.php' && str_ends_with($lowerPath, '.blade.php')) {
                return true;
            }

            if ($extension !== '' && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === $extension) {
                return true;
            }
        }

        return false;
    }
}
