<?php

/**
 * Общая файловая очередь AI Engine.
 *
 * Все операции чтения/изменения синхронизируются отдельным lock-файлом.
 * Запись JSON выполняется во временный файл с последующим rename().
 */
class ApprovalQueue
{
    private static function storageDirectory(): string
    {
        $configured = getenv('DAODES_STORAGE_PATH');

        return rtrim(
            $configured ?: dirname(__DIR__, 2) . '/storage',
            DIRECTORY_SEPARATOR
        );
    }

    private static function queueFile(): string
    {
        return self::storageDirectory() . '/ai_patch_queue.json';
    }

    private static function lockFile(): string
    {
        return self::queueFile() . '.lock';
    }

    public static function add(array $patch): array
    {
        return self::withLock(LOCK_EX, function () use ($patch) {
            $queue = self::readQueue();

            $item = [
                'id' => 'patch_' . bin2hex(random_bytes(16)),
                'status' => 'pending',
                'created_at' => date(DATE_ATOM),
                'updated_at' => date(DATE_ATOM),
                'patch' => $patch,
            ];

            $queue[] = $item;
            self::writeQueue($queue);

            return $item;
        });
    }

    public static function load(): array
    {
        return self::withLock(LOCK_SH, function () {
            return self::readQueue();
        });
    }

    public static function find(string $id): ?array
    {
        foreach (self::load() as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * $expectedStatus обеспечивает compare-and-set:
     * только один запрос сможет перевести pending -> applying.
     */
    public static function updateStatus(
        string $id,
        string $status,
        ?string $expectedStatus = null
    ): bool {
        $allowed = ['pending', 'applying', 'applied', 'rejected', 'failed'];

        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Недопустимый статус патча.');
        }

        return self::withLock(LOCK_EX, function () use ($id, $status, $expectedStatus) {
            $queue = self::readQueue();
            $found = false;

            foreach ($queue as &$item) {
                if (($item['id'] ?? null) !== $id) {
                    continue;
                }

                if (
                    $expectedStatus !== null &&
                    ($item['status'] ?? null) !== $expectedStatus
                ) {
                    unset($item);
                    return false;
                }

                $item['status'] = $status;
                $item['updated_at'] = date(DATE_ATOM);
                $found = true;
                break;
            }

            unset($item);

            if ($found) {
                self::writeQueue($queue);
            }

            return $found;
        });
    }

    private static function withLock(int $mode, callable $callback)
    {
        $directory = self::storageDirectory();

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Не удалось создать каталог storage для AI Engine.');
        }

        $handle = fopen(self::lockFile(), 'c');

        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть lock-файл очереди AI Engine.');
        }

        try {
            if (!flock($handle, $mode)) {
                throw new RuntimeException('Не удалось заблокировать очередь AI Engine.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function readQueue(): array
    {
        $file = self::queueFile();

        if (!is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException('Не удалось прочитать очередь AI Engine.');
        }

        if (trim($contents) === '') {
            return [];
        }

        $queue = json_decode($contents, true);

        if (!is_array($queue) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Файл очереди AI Engine содержит повреждённый JSON. Файл не перезаписан.'
            );
        }

        return array_values(array_filter($queue, 'is_array'));
    }

    private static function writeQueue(array $queue): void
    {
        $file = self::queueFile();
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

        $json = json_encode(
            array_values($queue),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException('Не удалось сериализовать очередь AI Engine.');
        }

        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Не удалось записать временный файл очереди.');
        }

        if (!rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('Не удалось атомарно обновить очередь AI Engine.');
        }

        @chmod($file, 0660);
    }
}
