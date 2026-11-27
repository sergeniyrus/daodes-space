<?php

use Symfony\Component\Process\Process;

require_once __DIR__ . '/approval_queue.php';

class PatchApplier
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * CLI-вход run.php только проверяет и ставит патч в очередь.
     * Он никогда не применяет изменения напрямую.
     */
    public static function apply(string $patchFile): array
    {
        if (!is_file($patchFile) || !is_readable($patchFile)) {
            throw new RuntimeException('Файл патча не найден или недоступен для чтения.');
        }

        $contents = file_get_contents($patchFile);

        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException('Файл патча пуст.');
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded) || !isset($decoded['diff']) || !is_string($decoded['diff'])) {
            throw new RuntimeException(
                'Ожидается JSON с полями diff и base_commit. Старый формат не поддерживается.'
            );
        }

        if (
            !isset($decoded['base_commit']) ||
            !is_string($decoded['base_commit']) ||
            !preg_match('/^[a-f0-9]{40,64}$/i', $decoded['base_commit'])
        ) {
            throw new RuntimeException('В патче отсутствует корректный base_commit.');
        }

        self::validateDiff($decoded['diff']);

        $root = self::projectRoot();
        self::assertGitRepository($root);
        self::assertCleanWorkingTree($root);

        $head = trim(self::runGit($root, ['rev-parse', 'HEAD'])->getOutput());
        if (!hash_equals($decoded['base_commit'], $head)) {
            throw new RuntimeException(
                'Патч создан на другой версии проекта. Сначала необходимо сгенерировать новый diff.'
            );
        }

        // Некорректный или неприменимый diff не должен попадать даже в pending.
        self::runGitWithInput(
            $root,
            ['apply', '--check', '--whitespace=error', '-'],
            $decoded['diff']
        );

        $item = ApprovalQueue::add([
            'diff' => $decoded['diff'],
            'base_commit' => $decoded['base_commit'],
            'created_at' => $decoded['created_at'] ?? date(DATE_ATOM),
            'generator' => 'DAODES AI Engine',
        ]);

        return $item;
    }

    /**
     * Применение вызывается только из защищённого Laravel-контроллера
     * после атомарного перехода pending -> applying.
     */
    public static function applyApproved(array $patch): void
    {
        if (!isset($patch['diff'], $patch['base_commit'])) {
            throw new RuntimeException('Патч не содержит diff или base_commit.');
        }

        $diff = $patch['diff'];

        if (!is_string($diff) || trim($diff) === '') {
            throw new RuntimeException('Пустой diff.');
        }

        if (!is_string($patch['base_commit']) || !preg_match('/^[a-f0-9]{40,64}$/i', $patch['base_commit'])) {
            throw new RuntimeException('Некорректный base_commit.');
        }

        self::validateDiff($diff);

        $root = self::projectRoot();
        $lockPath = $root . '/storage/ai_patch_apply.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new RuntimeException('Не удалось открыть блокировку применения патча.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Не удалось заблокировать применение патча.');
            }

            self::assertGitRepository($root);
            self::assertCleanWorkingTree($root);

            $head = trim(self::runGit($root, ['rev-parse', 'HEAD'])->getOutput());

            if (!hash_equals($patch['base_commit'], $head)) {
                throw new RuntimeException(
                    'Патч создан на другой версии проекта. Сначала необходимо сгенерировать новый diff.'
                );
            }

            self::runGitWithInput($root, ['apply', '--check', '--whitespace=error', '-'], $diff);

            // git apply без --reject либо применяет весь diff, либо сообщает об ошибке.
            self::runGitWithInput($root, ['apply', '--whitespace=error', '-'], $diff);

            try {
                self::lintChangedPhpFiles($root, $diff);
            } catch (Throwable $exception) {
                // Откатываем только этот diff, не перемещая HEAD и не затрагивая другие коммиты.
                self::runGitWithInput(
                    $root,
                    ['apply', '--reverse', '--check', '-'],
                    $diff
                );

                self::runGitWithInput(
                    $root,
                    ['apply', '--reverse', '-'],
                    $diff
                );

                throw new RuntimeException(
                    'PHP lint не пройден. Изменения этого патча отменены: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function validateDiff(string $diff): void
    {
        $diff = trim($diff);

        if ($diff === '' || str_contains($diff, '```') || preg_match('/^diff--git\b/m', $diff)) {
            throw new RuntimeException('Пустой diff, Markdown-обрамление или некорректный заголовок diff.');
        }

        if (!str_starts_with($diff, 'diff --git ')) {
            throw new RuntimeException('Diff должен начинаться с заголовка diff --git.');
        }

        if (
            preg_match('/^(?:new file mode|deleted file mode|old mode|new mode|rename from|rename to|copy from|copy to|GIT binary patch)\b/m', $diff) ||
            preg_match('/^index [0-9a-f]+\.\.\.[0-9a-f]+ 120000$/m', $diff)
        ) {
            throw new RuntimeException('Создание, удаление, переименование, смена режима, бинарные файлы и символические ссылки запрещены.');
        }

        $blocks = preg_split('/(?=^diff --git )/m', $diff, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($blocks) || $blocks === []) {
            throw new RuntimeException('В diff отсутствуют блоки файлов.');
        }

        $seenPaths = [];

        foreach ($blocks as $block) {
            $lines = preg_split('/\r?\n/', trim($block));

            if (!$lines || !preg_match('/^diff --git a\/([^\s]+) b\/([^\s]+)$/', $lines[0], $header)) {
                throw new RuntimeException('В diff обнаружен некорректный заголовок файла.');
            }

            $oldPath = $header[1];
            $newPath = $header[2];

            if ($oldPath !== $newPath) {
                throw new RuntimeException('Переименование файлов запрещено.');
            }

            $path = $newPath;
            self::validateRelativePath($path);

            if (isset($seenPaths[$path])) {
                throw new RuntimeException('В diff повторяется один и тот же файл: ' . $path);
            }
            $seenPaths[$path] = true;

            $oldHeaders = preg_grep('/^--- /', $lines) ?: [];
            $newHeaders = preg_grep('/^\+\+\+ /', $lines) ?: [];
            $hunks = preg_grep('/^@@ /', $lines) ?: [];

            if (count($oldHeaders) !== 1 || count($newHeaders) !== 1 || count($hunks) < 1) {
                throw new RuntimeException('Diff-блок должен содержать ровно один набор ---/+++ и хотя бы один hunk.');
            }

            if (
                trim(reset($oldHeaders)) !== '--- a/' . $path ||
                trim(reset($newHeaders)) !== '+++ b/' . $path
            ) {
                throw new RuntimeException('Пути diff --git и заголовков ---/+++ не совпадают: ' . $path);
            }

            self::validateAllowedPath($path);
        }
    }

    private static function validateRelativePath(string $path): void
    {
        if (
            $path === '' ||
            str_contains($path, '"') ||
            str_contains($path, '\\') ||
            str_starts_with($path, '/') ||
            preg_match('/^[A-Za-z]:\//', $path) ||
            preg_match('/[\x00-\x20\x7F]/', $path)
        ) {
            throw new RuntimeException('В diff обнаружен недопустимый путь файла.');
        }

        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new RuntimeException('В diff обнаружен небезопасный путь файла.');
            }
        }
    }

    private static function validateAllowedPath(string $path): void
    {
        $basename = basename($path);

        if ($basename === '.env' || str_starts_with($basename, '.env.')) {
            throw new RuntimeException('Изменение env-файлов запрещено.');
        }

        $blockedFiles = [
            'composer.lock',
            'package-lock.json',
            'npm-shrinkwrap.json',
            'yarn.lock',
            'pnpm-lock.yaml',
            'bun.lock',
            'bun.lockb',
        ];

        if (in_array($basename, $blockedFiles, true)) {
            throw new RuntimeException('Изменение lock-файлов запрещено этим механизмом.');
        }

        $blockedPrefixes = [
            '.git/',
            '.vite/',
            '.cache/',
            '.next/',
            'vendor/',
            'node_modules/',
            'storage/',
            'bootstrap/cache/',
            'public/build/',
            'public/storage/',
            'dist/',
            'coverage/',
            'tmp/',
            'temp/',
        ];

        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                throw new RuntimeException('Изменение защищённого или генерируемого каталога запрещено: ' . $path);
            }
        }
    }

    private static function assertGitRepository(string $root): void
    {
        $result = self::runGit($root, ['rev-parse', '--is-inside-work-tree']);

        if (trim($result->getOutput()) !== 'true') {
            throw new RuntimeException('Каталог проекта не является Git-репозиторием.');
        }
    }

    private static function assertCleanWorkingTree(string $root): void
    {
        $result = self::runGit($root, [
            'status',
            '--porcelain',
            '--untracked-files=all',
        ]);

        if (trim($result->getOutput()) !== '') {
            throw new RuntimeException(
                'Рабочая копия Git содержит незакоммиченные или неотслеживаемые файлы. ' .
                'Сначала сохраните или уберите эти изменения.'
            );
        }
    }

    private static function lintChangedPhpFiles(string $root, string $diff): void
    {
        preg_match_all('/^diff --git a\/([^\s]+) b\/([^\s]+)$/m', $diff, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $relative = $match[2];

            if (!str_ends_with(strtolower($relative), '.php')) {
                continue;
            }

            $absolute = $root . '/' . $relative;

            if (!is_file($absolute)) {
                continue;
            }

            $real = realpath($absolute);
            $realRoot = realpath($root);

            if (
                $real === false ||
                $realRoot === false ||
                !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)
            ) {
                throw new RuntimeException('PHP-файл патча находится за пределами проекта.');
            }

            $process = new Process([PHP_BINARY, '-l', $real], $root);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new RuntimeException(
                    'Ошибка синтаксиса PHP в ' . $relative . ': ' .
                    trim($process->getErrorOutput() . "\n" . $process->getOutput())
                );
            }
        }
    }

    private static function runGit(string $root, array $arguments): Process
    {
        $process = new Process(array_merge(['git'], $arguments), $root);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'Git завершился с ошибкой: ' .
                trim($process->getErrorOutput() . "\n" . $process->getOutput())
            );
        }

        return $process;
    }

    private static function runGitWithInput(
        string $root,
        array $arguments,
        string $input
    ): Process {
        $process = new Process(array_merge(['git'], $arguments), $root);
        $process->setInput($input);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'Не удалось выполнить git apply: ' .
                trim($process->getErrorOutput() . "\n" . $process->getOutput())
            );
        }

        return $process;
    }
}
