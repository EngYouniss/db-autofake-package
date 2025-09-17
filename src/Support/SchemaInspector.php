<?php

namespace VendorOrg\DbAutofake\Support;

use Illuminate\Support\Facades\DB;

class SchemaInspector
{
    public static function getColumns(string $table): array
    {
        // استخدام DBAL إن وُجد (أوسع توافقًا)
        if (interface_exists(\Doctrine\DBAL\Driver::class)) {
            $sm = DB::connection()->getDoctrineSchemaManager();
            $platform = $sm->getDatabasePlatform();
            if (method_exists($platform, 'registerDoctrineTypeMapping')) {
                $platform->registerDoctrineTypeMapping('enum', 'string');
                $platform->registerDoctrineTypeMapping('json', 'json');
            }
            $cols = $sm->listTableColumns($table);
            $out = [];
            foreach ($cols as $name => $col) {
                $out[] = (object)[
                    'name'    => $name,
                    'type'    => strtolower($col->getType()->getName()),
                    'nullable'=> !$col->getNotnull(),
                    'length'  => $col->getLength(),
                ];
            }
            return $out;
        }

        // بديل MySQL/MariaDB عبر INFORMATION_SCHEMA
        $rows = DB::select("
            SELECT COLUMN_NAME as name,
                   DATA_TYPE as type,
                   CASE WHEN IS_NULLABLE='YES' THEN 1 ELSE 0 END as nullable,
                   CHARACTER_MAXIMUM_LENGTH as length
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$table]);

        return array_map(function ($r) {
            $type = strtolower($r->type);
            if (in_array($type, ['varchar','char','tinytext','text','mediumtext','longtext'])) $type = 'string';
            if (in_array($type, ['double','decimal','numeric','real'])) $type = 'decimal';
            if ($type === 'tinyint') $type = 'integer';
            return (object)[
                'name'    => $r->name,
                'type'    => $type,
                'nullable'=> (bool)$r->nullable,
                'length'  => $r->length ? (int)$r->length : null,
            ];
        }, $rows);
    }
}
