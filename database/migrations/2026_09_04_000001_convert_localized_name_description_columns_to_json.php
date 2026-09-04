<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, nullable: bool, down_type: string}>
     */
    private array $columns = [
        ['table' => 'addons', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'categories', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'categories', 'column' => 'description', 'nullable' => true, 'down_type' => 'text'],
        ['table' => 'discounts', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'expense_lists', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'expense_lists', 'column' => 'description', 'nullable' => true, 'down_type' => 'text'],
        ['table' => 'financial_accounts', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'halls', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'kitchens', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'materials', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'options', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'payment_methods', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'payment_methods', 'column' => 'description', 'nullable' => true, 'down_type' => 'text'],
        ['table' => 'products', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'products', 'column' => 'description', 'nullable' => true, 'down_type' => 'text'],
        ['table' => 'product_recipes', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'taxes', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
        ['table' => 'variations', 'column' => 'name', 'nullable' => false, 'down_type' => 'string'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $column) {
            $this->convertTextToJson($column['table'], $column['column'], $column['nullable']);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->columns) as $column) {
            $this->convertJsonToText($column['table'], $column['column'], $column['nullable'], $column['down_type']);
        }
    }

    private function convertTextToJson(string $table, string $column, bool $nullable): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->convertTextToJsonForMysql($table, $column, $nullable),
            'pgsql' => $this->convertTextToJsonForPostgres($table, $column, $nullable),
            default => $this->storeJsonAsText($table, $column),
        };
    }

    private function convertJsonToText(string $table, string $column, bool $nullable, string $type): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->convertJsonToTextForMysql($table, $column, $nullable, $type),
            'pgsql' => $this->convertJsonToTextForPostgres($table, $column, $nullable, $type),
            default => $this->restoreTextFromStoredJson($table, $column),
        };
    }

    private function convertTextToJsonForMysql(string $table, string $column, bool $nullable): void
    {
        $temporaryColumn = "{$column}_localized_tmp";

        DB::statement("ALTER TABLE `{$table}` ADD `{$temporaryColumn}` JSON NULL AFTER `{$column}`");
        DB::statement("UPDATE `{$table}` SET `{$temporaryColumn}` = CASE WHEN `{$column}` IS NULL THEN NULL ELSE JSON_OBJECT('en', `{$column}`, 'ar', `{$column}`) END");
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        DB::statement("ALTER TABLE `{$table}` CHANGE `{$temporaryColumn}` `{$column}` JSON ".($nullable ? 'NULL' : 'NOT NULL'));
    }

    private function convertJsonToTextForMysql(string $table, string $column, bool $nullable, string $type): void
    {
        $temporaryColumn = "{$column}_text_tmp";
        $columnType = $type === 'string' ? 'VARCHAR(255)' : 'TEXT';

        DB::statement("ALTER TABLE `{$table}` ADD `{$temporaryColumn}` {$columnType} NULL AFTER `{$column}`");
        DB::statement("UPDATE `{$table}` SET `{$temporaryColumn}` = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`{$column}`, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(`{$column}`, '$.ar')))");
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        DB::statement("ALTER TABLE `{$table}` CHANGE `{$temporaryColumn}` `{$column}` {$columnType} ".($nullable ? 'NULL' : 'NOT NULL'));
    }

    private function convertTextToJsonForPostgres(string $table, string $column, bool $nullable): void
    {
        $temporaryColumn = "{$column}_localized_tmp";

        DB::statement("ALTER TABLE {$table} ADD COLUMN {$temporaryColumn} JSON");
        DB::statement("UPDATE {$table} SET {$temporaryColumn} = CASE WHEN {$column} IS NULL THEN NULL ELSE json_build_object('en', {$column}, 'ar', {$column}) END");
        DB::statement("ALTER TABLE {$table} DROP COLUMN {$column}");
        DB::statement("ALTER TABLE {$table} RENAME COLUMN {$temporaryColumn} TO {$column}");

        if (! $nullable) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
        }
    }

    private function convertJsonToTextForPostgres(string $table, string $column, bool $nullable, string $type): void
    {
        $temporaryColumn = "{$column}_text_tmp";
        $columnType = $type === 'string' ? 'VARCHAR(255)' : 'TEXT';

        DB::statement("ALTER TABLE {$table} ADD COLUMN {$temporaryColumn} {$columnType}");
        DB::statement("UPDATE {$table} SET {$temporaryColumn} = COALESCE({$column}->>'en', {$column}->>'ar')");
        DB::statement("ALTER TABLE {$table} DROP COLUMN {$column}");
        DB::statement("ALTER TABLE {$table} RENAME COLUMN {$temporaryColumn} TO {$column}");

        if (! $nullable) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
        }
    }

    private function storeJsonAsText(string $table, string $column): void
    {
        $wrappedRows = DB::table($table)
            ->whereNotNull($column)
            ->pluck($column, 'id')
            ->map(fn ($value) => json_encode(['en' => $value, 'ar' => $value], JSON_UNESCAPED_UNICODE));

        foreach ($wrappedRows as $id => $value) {
            DB::table($table)->where('id', $id)->update([$column => $value]);
        }
    }

    private function restoreTextFromStoredJson(string $table, string $column): void
    {
        $rows = DB::table($table)
            ->whereNotNull($column)
            ->pluck($column, 'id');

        foreach ($rows as $id => $value) {
            $localized = json_decode($value, true);

            if (! is_array($localized)) {
                continue;
            }

            DB::table($table)->where('id', $id)->update([
                $column => $localized['en'] ?? $localized['ar'] ?? null,
            ]);
        }
    }
};
