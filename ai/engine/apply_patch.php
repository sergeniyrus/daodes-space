<?php

require_once __DIR__ . '/approval_queue.php';

class PatchApplier
{
    /**
     * Получение канонического корня проекта.
     */
    private static function projectRoot(): string
    {
        $config = require __DIR__ . '/config.php';
        $root = realpath($config['project_root'] ?? '');

        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Invalid project root.');
        }

        return $root;
    }

    /**
     * Сохраняет старый API: отправляет diff в очередь на одобрение.
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
            throw new RuntimeException('Empty patch file.');
        }

        $patchData = [
            'diff' => $patch,
            'project_root' => self::projectRoot(),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $item = ApprovalQueue::add($patchData);

        echo "Patch sent to approval queue.\n";
        echo 'Patch ID: ' . $item['id'] . "\n";
    }

    /**
     * Запуск применения по ID одобренного патча.
     */
    public static function applyApprovedById(string $id): void
    {
        // Одновременно зарезервировать патч может только один процесс.
        $item = ApprovalQueue::claimForApply($id);

        try {
            $patch = $item['patch'] ?? [];

            if (!is_array($patch)) {
                throw new RuntimeException('Invalid patch data.');
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
     * Фактическое применение патча.
     *
     * Разрешено только для записи очереди со статусом applying.
     */
    public static function applyApproved(array $patch): void
    {
        $id = $patch['_queue_id'] ?? null;

        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Missing approved patch ID.');
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
            throw new RuntimeException('Empty patch diff.');
        }

        $checksum = hash('sha256', $diff);

        if (!hash_equals(
            (string) ($queueItem['sha256'] ?? ''),
            $checksum
        )) {
            throw new RuntimeException('Patch integrity check failed.');
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

        if (
            !is_string($patchRoot) ||
            realpath($patchRoot) !== $root
        ) {
            throw new RuntimeException(
                'Patch project root does not match configured project root.'
            );
        }
    }

    /**
     * Создание или открытие файла блокировки применения.
     *
     * Существующие файлы не подвергаются chmod.
     *
     * @return resource
     */
    private static function openApplyLock(string $lockFile)
    {
        $previousUmask = umask(0002);

        try {
            $lock = fopen($lockFile, 'c');
        } finally {
            umask($previousUmask);
        }

        if ($lock === false) {
            throw new RuntimeException(
                'Unable to open AI apply lock.'
            );
        }

        if (!is_writable($lockFile)) {
            fclose($lock);

            throw new RuntimeException(
                'AI apply lock is not writable by the current user. ' .
                'Check ownership, group and permissions.'
            );
        }

        return $lock;
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
        $lock = self::openApplyLock($lockFile);

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);

            throw new RuntimeException(
                'Unable to acquire AI apply lock.'
            );
        }

        $temporaryFile = null;

        try {
            // Проверяем, что это корень Git-репозитория.
            $gitRoot = self::runGit(
                $root,
                ['rev-parse', '--show-toplevel']
            );

            if ($gitRoot['code'] !== 0) {
                throw new RuntimeException(
                    "Unable to determine Git repository root:\n" .
                    $gitRoot['output']
                );
            }

            if (realpath(trim($gitRoot['output'])) !== $root) {
                throw new RuntimeException(
                    'Git repository root does not match project root.'
                );
            }

            // Не затрагиваем существующие изменения пользователя.
            $status = self::runGit(
                $root,
                [
                    'status',
                    '--porcelain',
                    '--untracked-files=all',
                ]
            );

            if ($status['code'] !== 0) {
                throw new RuntimeException(
                    "Unable to check Git working tree:\n" .
                    $status['output']
                );
            }

            if (trim($status['output']) !== '') {
                throw new RuntimeException(
                    "Git working tree is not clean. Commit or safely save " .
                    "existing changes before applying AI patches.\n" .
                    $status['output']
                );
            }

            // Фиксируем исходный HEAD.
            $headResult = self::runGit(
                $root,
                ['rev-parse', 'HEAD']
            );

            if ($headResult['code'] !== 0) {
                throw new RuntimeException(
                    "Unable to determine original Git HEAD:\n" .
                    $headResult['output']
                );
            }

            $originalHead = trim($headResult['output']);

            if ($originalHead === '') {
                throw new RuntimeException(
                    'Unable to determine original Git HEAD.'
                );
            }

            // Создаём временный файл патча.
            $createdTemporaryFile = tempnam($storage, 'ai_patch_');

            if ($createdTemporaryFile === false) {
                throw new RuntimeException(
                    'Unable to create temporary patch file.'
                );
            }

            $temporaryFile = $createdTemporaryFile;

            $writtenBytes = file_put_contents(
                $temporaryFile,
                $diff,
                LOCK_EX
            );

            if ($writtenBytes !== strlen($diff)) {
                throw new RuntimeException(
                    'Unable to write temporary patch.'
                );
            }

            // Проверяем безопасность каждого пути до git apply --check.
            self::validatePatchPaths(
                $diff,
                $temporaryFile,
                $root
            );

            // Проверяем возможность применения без изменения файлов.
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

            if ($currentHead['code'] !== 0) {
                throw new RuntimeException(
                    "Unable to recheck Git HEAD:\n" .
                    $currentHead['output']
                );
            }

            if (trim($currentHead['output']) !== $originalHead) {
                throw new RuntimeException(
                    'Git HEAD changed during patch validation.'
                );
            }

            // Повторно проверяем рабочую директорию.
            $currentStatus = self::runGit(
                $root,
                [
                    'status',
                    '--porcelain',
                    '--untracked-files=all',
                ]
            );

            if ($currentStatus['code'] !== 0) {
                throw new RuntimeException(
                    "Unable to recheck Git working tree:\n" .
                    $currentStatus['output']
                );
            }

            if (trim($currentStatus['output']) !== '') {
                throw new RuntimeException(
                    "Git working tree changed during patch validation.\n" .
                    $currentStatus['output']
                );
            }

            // Повторно проверяем пути непосредственно перед применением.
            self::validatePatchPaths(
                $diff,
                $temporaryFile,
                $root
            );

            // Фактическое применение.
            $apply = self::runGit(
                $root,
                [
                    'apply',
                    $temporaryFile,
                ]
            );

            if ($apply['code'] !== 0) {
                // Не выполняем git reset --hard:
                // он может уничтожить чужие изменения.
                throw new RuntimeException(
                    "Git patch application failed:\n" .
                    $apply['output']
                );
            }
        } finally {
            if (
                $temporaryFile !== null &&
                file_exists($temporaryFile)
            ) {
                unlink($temporaryFile);
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Проверяет все пути, которые Git извлекает из diff.
     *
     * Git самостоятельно разбирает формат заголовков, включая
     * экранированные имена файлов и пробелы в путях.
     */
    private static function validatePatchPaths(
        string $diff,
        string $patchFile,
        string $root
    ): void {
        // Переименование и копирование пока не поддерживаются.
        if (preg_match(
            '/^(?:rename from|rename to|copy from|copy to) /m',
            $diff
        )) {
            throw new RuntimeException(
                'Patch renames and copies are not allowed.'
            );
        }

        // Удаление файлов запрещено.
        if (
            preg_match('/^deleted file mode /m', $diff) ||
            preg_match('/^\+\+\+ \/dev\/null(?:\s|$)/m', $diff)
        ) {
            throw new RuntimeException(
                'Deleting files through AI patches is not allowed.'
            );
        }

        // Бинарные патчи не поддерживаются.
        if (
            str_contains($diff, 'GIT binary patch') ||
            preg_match('/^Binary files .* differ$/m', $diff)
        ) {
            throw new RuntimeException(
                'Binary patches are not allowed.'
            );
        }

        // Изменение режима существующего файла запрещено.
        // Новые файлы разрешены только с обычным режимом 100644.
        if (
            preg_match('/^(?:old mode|new mode) /m', $diff) ||
            preg_match('/^new file mode (?!100644$)/m', $diff)
        ) {
            throw new RuntimeException(
                'Changing file permissions or creating special files is not allowed.'
            );
        }

        // Получаем NUL-разделённый список затрагиваемых файлов.
        $result = self::runGitRaw(
            $root,
            [
                'apply',
                '--numstat',
                '-z',
                $patchFile,
            ]
        );

        if ($result['code'] !== 0) {
            throw new RuntimeException(
                "Unable to inspect patch paths:\n" .
                $result['output']
            );
        }

        if (
            $result['output'] === '' ||
            !str_ends_with($result['output'], "\0")
        ) {
            throw new RuntimeException(
                'Git returned an invalid patch path list.'
            );
        }

        $records = explode("\0", $result['output']);
        array_pop($records);

        if ($records === []) {
            throw new RuntimeException(
                'Patch does not contain any file changes.'
            );
        }

        foreach ($records as $record) {
            // Формат: additions<TAB>deletions<TAB>path<NUL>.
            // Ограничение в 3 части сохраняет табуляции в имени пути,
            // после чего они будут отклонены проверкой управляющих символов.
            $columns = explode("\t", $record, 3);

            if (
                count($columns) !== 3 ||
                $columns[2] === ''
            ) {
                throw new RuntimeException(
                    'Unable to parse a path in the AI patch.'
                );
            }

            self::validatePatchPath(
                $columns[2],
                $root
            );
        }
    }

    /**
     * Проверяет один путь на traversal, секреты,
     * защищённые компоненты и символьные ссылки.
     */
    private static function validatePatchPath(
        string $path,
        string $root
    ): void {
        if (
            $path === '' ||
            str_contains($path, '\\') ||
            preg_match('/[\x00-\x1F\x7F]/', $path) ||
            str_starts_with($path, '/') ||
            preg_match('/^[A-Za-z]:/', $path)
        ) {
            throw new RuntimeException(
                'Unsafe or invalid patch path: ' . $path
            );
        }

        $segments = explode('/', $path);

        // Защита от path traversal и неоднозначных путей.
        foreach ($segments as $segment) {
            if (
                $segment === '' ||
                $segment === '.' ||
                $segment === '..'
            ) {
                throw new RuntimeException(
                    'Path traversal detected in AI patch: ' . $path
                );
            }
        }

        $lowerPath = strtolower($path);
        $basename = strtolower(basename($path));

        // Защищённые каталоги и конфигурационные секреты.
        $protectedDirectories = [
            '.git',
            '.ssh',
            '.aws',
            '.gnupg',
            'storage',
            'vendor',
            'node_modules',
            'certs',
            'certificates',
            'private-keys',
            'private_keys',
            'secrets',
            'credentials',
        ];

        foreach ($segments as $segment) {
            $lowerSegment = strtolower($segment);

            if (
                in_array($lowerSegment, $protectedDirectories, true) ||
                str_starts_with($lowerSegment, '.env') ||
                $lowerSegment === '.gitmodules'
            ) {
                throw new RuntimeException(
                    'Patch is not allowed to modify protected path: ' .
                    $path
                );
            }
        }

        // Laravel cache и Git submodule configuration.
        if (
            $lowerPath === 'bootstrap/cache' ||
            str_starts_with($lowerPath, 'bootstrap/cache/')
        ) {
            throw new RuntimeException(
                'Patch is not allowed to modify protected path: ' .
                $path
            );
        }

        // Сертификаты, закрытые ключи и хранилища ключей.
        if (preg_match(
            '/\.(?:pem|key|p12|pfx|crt|cer|der|jks|keystore|ppk)$/i',
            $basename
        )) {
            throw new RuntimeException(
                'Patch is not allowed to modify certificates or private keys: ' .
                $path
            );
        }

        // Файлы с распространёнными учётными данными.
        if (in_array(
            $basename,
            ['.netrc', '.npmrc', '.pypirc', '.dockercfg'],
            true
        )) {
            throw new RuntimeException(
                'Patch is not allowed to modify credential files: ' .
                $path
            );
        }

        // Известные имена приватных ключей без расширения.
        if (preg_match(
            '/^(?:id_rsa|id_ed25519|privkey|private_key)(?:\..*)?$/i',
            $basename
        )) {
            throw new RuntimeException(
                'Patch is not allowed to modify private keys: ' .
                $path
            );
        }

        // Критические компоненты AI Engine разрешено менять только вручную.
        $protectedFiles = [
            'ai/engine/approval_queue.php',
            'ai/engine/apply_patch.php',
            'ai/engine/local_runner.php',
            'ai/engine/run_apply.php',
            'ai/engine/config.php',
            'ai/engine/run.php',
            'ai/engine/watch_patch.sh',
        ];

        if (in_array($lowerPath, $protectedFiles, true)) {
            throw new RuntimeException(
                'AI patches cannot modify the protected AI execution component: ' .
                $path
            );
        }

        // Проверяем каждый компонент пути на символьные ссылки.
        // Это также защищает от выхода за пределы корня проекта.
        $current = $root;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;

            if (is_link($current)) {
                throw new RuntimeException(
                    'Patch cannot traverse or replace a symbolic link: ' .
                    $path
                );
            }

            if (
                $index < $lastIndex &&
                file_exists($current) &&
                !is_dir($current)
            ) {
                throw new RuntimeException(
                    'A parent component of the patch path is not a directory: ' .
                    $path
                );
            }

            if (file_exists($current)) {
                $resolved = realpath($current);

                if (
                    $resolved === false ||
                    (
                        $resolved !== $root &&
                        !str_starts_with(
                            $resolved,
                            $root . DIRECTORY_SEPARATOR
                        )
                    )
                ) {
                    throw new RuntimeException(
                        'Patch path resolves outside the project root: ' .
                        $path
                    );
                }
            }
        }
    }

    /**
     * Выполнение Git-команды с сохранением бинарного/NUL-вывода.
     *
     * Аргументы передаются массивом без shell-интерпретации.
     *
     * @return array{code:int, output:string}
     */
    private static function runGitRaw(
        string $root,
        array $arguments
    ): array {
        $command = array_merge(
            [
                'git',
                '-C',
                $root,
            ],
            array_map(
                static fn ($argument): string => (string) $argument,
                $arguments
            )
        );

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $root
        );

        if (!is_resource($process)) {
            throw new RuntimeException(
                'Unable to start Git path validation.'
            );
        }

        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $code = proc_close($process);

        if ($output === false) {
            throw new RuntimeException(
                'Unable to read Git path validation output.'
            );
        }

        return [
            'code' => $code,
            'output' => $output,
        ];
    }

    /**
     * Выполнение Git-команды с экранированием аргументов.
     *
     * @return array{code:int, output:string}
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

        if (
            $result['code'] !== 0 ||
            trim($result['output']) === ''
        ) {
            throw new RuntimeException(
                'Unable to establish Git backup point.'
            );
        }
    }

    /**
     * Сохранено для совместимости.
     *
     * Безусловный reset HEAD~1 не выполняется.
     */
    private static function rollback(): void
    {
        throw new RuntimeException(
            'Rollback requires the original verified Git HEAD.'
        );
    }
}
