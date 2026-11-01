<?php

class ApprovalQueue
{
    /**
     * Путь к файлу очереди.
     */
    private static function queueFile(): string
    {
        $projectRoot = dirname(__DIR__, 2);

        return $projectRoot . '/storage/ai_patch_queue.json';
    }

    /**
     * Путь к файлу блокировки очереди.
     */
    private static function lockFile(): string
    {
        return self::queueFile() . '.lock';
    }

    /**
 * Создание или открытие файла с групповыми правами.
 *
 * Новые файлы создаются с учётом umask 0002.
 * Существующие файлы не подвергаются chmod,
 * поскольку могут принадлежать другому пользователю.
 */
private static function createWritableFile(
    string $file,
    string $mode
) {
    $previousUmask = umask(0002);

    try {
        $handle = fopen($file, $mode);
    } finally {
        umask($previousUmask);
    }

    if ($handle === false) {
        throw new RuntimeException(
            'Unable to open AI queue file: ' . $file
        );
    }

    if (!is_writable($file)) {
        fclose($handle);

        throw new RuntimeException(
            'AI queue file is not writable: ' .
            $file .
            '. Check ownership, group and permissions.'
        );
    }

    return $handle;
}

    /**
     * Выполнение операции с блокировкой очереди.
     */
    private static function withLock(
        bool $exclusive,
        callable $callback
    ): mixed {
        $directory = dirname(self::queueFile());

        if (!is_dir($directory)) {
            $previousUmask = umask(0002);

            try {
                $created = mkdir($directory, 0775, true);
            } finally {
                umask($previousUmask);
            }

            if (!$created && !is_dir($directory)) {
                throw new RuntimeException(
                    'Unable to create AI queue directory.'
                );
            }
        }

        $lock = self::createWritableFile(
            self::lockFile(),
            'c'
        );

        $lockType = $exclusive ? LOCK_EX : LOCK_SH;

        if (!flock($lock, $lockType)) {
            fclose($lock);

            throw new RuntimeException(
                'Unable to lock AI queue.'
            );
        }

        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Чтение очереди без повторного получения блокировки.
     */
    private static function loadUnlocked(): array
    {
        $file = self::queueFile();

        if (!file_exists($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException(
                'Unable to read AI queue.'
            );
        }

        try {
            $queue = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'AI queue contains invalid JSON: ' .
                $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_array($queue)) {
            throw new RuntimeException(
                'AI queue must contain a JSON array.'
            );
        }

        return $queue;
    }

    /**
     * Атомарное сохранение очереди.
     */
    private static function saveUnlocked(array $queue): void
    {
        $file = self::queueFile();

        $json = json_encode(
            $queue,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );

        $temporaryFile = $file .
            '.tmp.' .
            bin2hex(random_bytes(8));

        $previousUmask = umask(0002);

        try {
            $written = file_put_contents(
                $temporaryFile,
                $json,
                LOCK_EX
            );
        } finally {
            umask($previousUmask);
        }

        if ($written === false) {
            throw new RuntimeException(
                'Unable to write temporary AI queue.'
            );
        }

        if (!chmod($temporaryFile, 0664)) {
            @unlink($temporaryFile);

            throw new RuntimeException(
                'Unable to set permissions on temporary AI queue.'
            );
        }

        if (!rename($temporaryFile, $file)) {
            @unlink($temporaryFile);

            throw new RuntimeException(
                'Unable to replace AI queue file.'
            );
        }
    }

    /**
     * Разрешённые переходы статусов.
     */
    private static function allowedTransitions(): array
    {
        return [
            'pending' => [
                'approved',
                'rejected',
            ],

            'approved' => [
                'applying',
            ],

            'applying' => [
                'applied',
                'failed',
            ],

            'rejected' => [],

            'applied' => [],

            'failed' => [],
        ];
    }

    /**
     * Проверка допустимости перехода.
     */
    private static function validateTransition(
        string $expectedStatus,
        string $newStatus
    ): void {
        $transitions = self::allowedTransitions();

        if (!isset($transitions[$expectedStatus])) {
            throw new InvalidArgumentException(
                'Invalid current AI patch status: ' .
                $expectedStatus
            );
        }

        if (!in_array(
            $newStatus,
            $transitions[$expectedStatus],
            true
        )) {
            throw new InvalidArgumentException(
                'Transition from ' .
                $expectedStatus .
                ' to ' .
                $newStatus .
                ' is not allowed.'
            );
        }
    }

    /**
     * Добавление патча в очередь.
     */
    public static function add(array $patch): array
    {
        $diff = $patch['diff'] ?? null;

        if (!is_string($diff) || trim($diff) === '') {
            throw new InvalidArgumentException(
                'Cannot add an empty patch to the queue.'
            );
        }

        return self::withLock(true, function () use ($patch, $diff) {

            $queue = self::loadUnlocked();

            $now = date('Y-m-d H:i:s');

            $item = [
                'id' => bin2hex(random_bytes(16)),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
                'sha256' => hash('sha256', $diff),
                'patch' => $patch,
            ];

            $queue[] = $item;

            self::saveUnlocked($queue);

            return $item;
        });
    }

    /**
     * Получение всей очереди.
     */
    public static function load(): array
    {
        return self::withLock(false, function () {
            return self::loadUnlocked();
        });
    }

    /**
     * Получение патча по ID.
     */
    public static function find(string $id): ?array
    {
        return self::withLock(false, function () use ($id) {

            $queue = self::loadUnlocked();

            foreach ($queue as $item) {
                if (($item['id'] ?? null) === $id) {
                    return $item;
                }
            }

            return null;
        });
    }

    /**
     * Строгий переход статуса.
     */
    public static function transition(
        string $id,
        string $expectedStatus,
        string $newStatus
    ): array {
        self::validateTransition(
            $expectedStatus,
            $newStatus
        );

        return self::withLock(true, function () use (
            $id,
            $expectedStatus,
            $newStatus
        ) {

            $queue = self::loadUnlocked();

            foreach ($queue as &$item) {

                if (($item['id'] ?? null) !== $id) {
                    continue;
                }

                $currentStatus = $item['status'] ?? null;

                if ($currentStatus !== $expectedStatus) {
                    throw new RuntimeException(
                        'Invalid status transition: expected ' .
                        $expectedStatus .
                        ', current status is ' .
                        ($currentStatus ?? 'unknown')
                    );
                }

                $item['status'] = $newStatus;
                $item['updated_at'] = date('Y-m-d H:i:s');

                $updatedItem = $item;

                unset($item);

                self::saveUnlocked($queue);

                return $updatedItem;
            }

            unset($item);

            throw new RuntimeException(
                'AI patch not found: ' . $id
            );
        });
    }

    /**
     * Совместимость со старым API.
     *
     * Теперь метод не обходит проверку переходов.
     */
    public static function updateStatus(
        string $id,
        string $status
    ): void {
        $item = self::find($id);

        if ($item === null) {
            throw new RuntimeException(
                'AI patch not found: ' . $id
            );
        }

        $currentStatus = $item['status'] ?? null;

        if (!is_string($currentStatus)) {
            throw new RuntimeException(
                'AI patch has an invalid current status.'
            );
        }

        self::transition(
            $id,
            $currentStatus,
            $status
        );
    }

    /**
     * Резервирование одобренного патча для применения.
     */
    public static function claimForApply(string $id): array
    {
        return self::transition(
            $id,
            'approved',
            'applying'
        );
    }
}