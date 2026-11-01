
<?php

class ApprovalQueue
{
    private static function queueFile(): string
    {
        $projectRoot = dirname(__DIR__, 2);

        return $projectRoot . '/storage/ai_patch_queue.json';
    }

    private static function lockFile(): string
    {
        return self::queueFile() . '.lock';
    }

    private static function withLock(
        bool $exclusive,
        callable $callback
    ): mixed {
        $directory = dirname(self::queueFile());

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Unable to create AI queue directory.'
                );
            }
        }

        $lock = fopen(self::lockFile(), 'c');

        if ($lock === false) {
            throw new RuntimeException(
                'Unable to open AI queue lock.'
            );
        }

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

        $queue = json_decode($contents, true);

        if (!is_array($queue)) {
            throw new RuntimeException(
                'AI queue contains invalid JSON.'
            );
        }

        return $queue;
    }

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

        $temporaryFile = $file . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($temporaryFile, $json, LOCK_EX) === false) {
            throw new RuntimeException(
                'Unable to write temporary AI queue.'
            );
        }

        if (!rename($temporaryFile, $file)) {
            @unlink($temporaryFile);

            throw new RuntimeException(
                'Unable to replace AI queue file.'
            );
        }
    }

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

            $item = [
                'id' => bin2hex(random_bytes(16)),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'sha256' => hash('sha256', $diff),
                'patch' => $patch,
            ];

            $queue[] = $item;

            self::saveUnlocked($queue);

            return $item;
        });
    }

    public static function load(): array
    {
        return self::withLock(false, function () {
            return self::loadUnlocked();
        });
    }

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

    public static function transition(
        string $id,
        string $expectedStatus,
        string $newStatus
    ): array {
        $allowedStatuses = [
            'pending',
            'approved',
            'rejected',
            'applying',
            'applied',
            'failed',
        ];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new InvalidArgumentException(
                'Invalid AI patch status.'
            );
        }

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

                if (($item['status'] ?? null) !== $expectedStatus) {
                    throw new RuntimeException(
                        'Invalid status transition: expected ' .
                        $expectedStatus .
                        ', current status is ' .
                        ($item['status'] ?? 'unknown')
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

    public static function updateStatus(
        string $id,
        string $status
    ): void {
        self::withLock(true, function () use ($id, $status) {

            $queue = self::loadUnlocked();

            foreach ($queue as &$item) {

                if (($item['id'] ?? null) !== $id) {
                    continue;
                }

                $item['status'] = $status;
                $item['updated_at'] = date('Y-m-d H:i:s');

                unset($item);

                self::saveUnlocked($queue);

                return;
            }

            unset($item);

            throw new RuntimeException(
                'AI patch not found: ' . $id
            );
        });
    }

    public static function claimForApply(string $id): array
    {
        return self::transition(
            $id,
            'approved',
            'applying'
        );
    }
}
