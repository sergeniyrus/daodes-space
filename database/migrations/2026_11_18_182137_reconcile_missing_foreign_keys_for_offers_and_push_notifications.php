
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // offers.user_id -> users.id
        $this->addForeignKeyIfMissing(
            'offers',
            'user_id',
            'users',
            'fk_offers_user_id'
        );

        // offers.category_id -> category_offers.id
        $this->addForeignKeyIfMissing(
            'offers',
            'category_id',
            'category_offers',
            'fk_offers_category_id'
        );

        // push_notifications.customer_id -> customers.id
        $this->addForeignKeyIfMissing(
            'push_notifications',
            'customer_id',
            'customers',
            'fk_push_notifications_customer_id'
        );
    }

    private function addForeignKeyIfMissing(
        string $tableName,
        string $columnName,
        string $referencedTable,
        string $constraintName
    ): void {
        // Проверяем существование обеих таблиц.
        if (
            !Schema::hasTable($tableName) ||
            !Schema::hasTable($referencedTable)
        ) {
            throw new \RuntimeException(
                "Не найдена таблица {$tableName} или {$referencedTable}"
            );
        }

        // Проверяем существование колонок.
        if (
            !Schema::hasColumn($tableName, $columnName) ||
            !Schema::hasColumn($referencedTable, 'id')
        ) {
            throw new \RuntimeException(
                "Не найдена колонка {$tableName}.{$columnName} или {$referencedTable}.id"
            );
        }

        // Проверяем существующий внешний ключ.
        $existingForeignKey = DB::selectOne(
            "
            SELECT
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
            ",
            [$tableName, $columnName]
        );

        if ($existingForeignKey) {
            if (
                $existingForeignKey->REFERENCED_TABLE_NAME === $referencedTable &&
                $existingForeignKey->REFERENCED_COLUMN_NAME === 'id'
            ) {
                return;
            }

            throw new \RuntimeException(
                "Колонка {$tableName}.{$columnName} уже связана с другой таблицей."
            );
        }

        // Проверяем наличие подходящего индекса.
        $existingIndex = DB::selectOne(
            "
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND SEQ_IN_INDEX = 1
            LIMIT 1
            ",
            [$tableName, $columnName]
        );

        // Создаём индекс, только если его нет.
        if (!$existingIndex) {
            Schema::table($tableName, function (Blueprint $table) use (
                $columnName,
                $tableName
            ) {
                $table->index(
                    $columnName,
                    $tableName . '_' . $columnName . '_index'
                );
            });
        }

        // Добавляем внешний ключ с каскадным удалением.
        Schema::table($tableName, function (Blueprint $table) use (
            $columnName,
            $referencedTable,
            $constraintName
        ) {
            $table->foreign($columnName, $constraintName)
                ->references('id')
                ->on($referencedTable)
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Не удаляем внешние ключи и индексы автоматически,
        // чтобы случайный rollback не нарушил целостность данных.
    }
};
