<?php

namespace VendorOrg\DbAutofake\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VendorOrg\DbAutofake\Support\SchemaInspector;
use VendorOrg\DbAutofake\Support\ValueGenerator;

class TableFakeCommand extends Command
{
    protected $signature = 'table:fake
        {table : Target table name}
        {--rows=50 : Number of rows to insert}
        {--truncate : Truncate the table before inserting}
        {--locale= : Faker locale if available (e.g. en_US, ar_SA)}
        {--nullable= : Probability (0..1) to leave nullable columns as NULL}
        {--chunk= : Insert batch size}
        {--seed= : Random seed to make data deterministic}';

    protected $description = 'Fill an existing table with fake data based on its columns. Faker/DBAL are optional.';

    public function handle()
    {
        $table     = $this->argument('table');
        $rows      = (int) ($this->option('rows') ?: 50);
        $truncate  = (bool) $this->option('truncate');

        if (!Schema::hasTable($table)) {
            $this->error("Table {$table} does not exist.");
            return self::FAILURE;
        }

        $cfg      = config('db_autofake');
        $locale   = $this->option('locale')   ?: ($cfg['locale'] ?? 'en_US');
        $nullable = $this->option('nullable') !== null ? (float)$this->option('nullable') : ($cfg['nullable_probability'] ?? 0.1);
        $chunk    = $this->option('chunk')    !== null ? (int)$this->option('chunk')       : ($cfg['chunk'] ?? 500);
        $seed     = $this->option('seed')     !== null ? (int)$this->option('seed')        : null;

        if ($truncate) {
            DB::table($table)->truncate();
            $this->info("Truncated {$table}.");
        }

        $columns  = SchemaInspector::getColumns($table);
        $colNames = array_map(fn($c) => $c->name, $columns);
        $ignore   = array_map('strtolower', $cfg['ignore_columns'] ?? ['id']);

        $gen = new ValueGenerator($locale, $seed);

        $inserted = 0;
        $batch = [];
        for ($i = 0; $i < $rows; $i++) {
            $row = [];
            foreach ($columns as $c) {
                $name  = $c->name;
                $lname = strtolower($name);
                if (in_array($lname, $ignore, true)) continue;

                // Timestamps/known columns from config
                if (isset($cfg['timestamps'][$lname])) {
                    $mode = $cfg['timestamps'][$lname];
                    if ($mode === 'now')   { $row[$name] = now(); }
                    elseif ($mode === 'null') { $row[$name] = null; }
                    // 'skip' means do nothing
                    continue;
                }

                // SAFETY: columns ending with _at are treated as datetime
                if (preg_match('/_at$/', $lname)) {
                    $row[$name] = now();
                    continue;
                }

                // Nullable probability
                if ($c->nullable && $this->chance($nullable)) {
                    $row[$name] = null;
                    continue;
                }

                // Generate value
                $val = $gen->byColumn($name, $c->type, $c->length);
                if ($val === '__SKIP__') continue;

                // Respect column length
                if (is_string($val) && $c->length && $c->length > 0) {
                    $val = mb_substr($val, 0, $c->length);
                }

                $row[$name] = $val;
            }

            // Only keep valid keys
            $row = Arr::only($row, $colNames);
            if (!empty($row)) $batch[] = $row;

            if (count($batch) >= $chunk) {
                DB::table($table)->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table($table)->insert($batch);
            $inserted += count($batch);
        }

        $this->info("Inserted {$inserted} row(s) into {$table}.");
        return self::SUCCESS;
    }

    protected function chance(float $p): bool
    {
        return $p > 0 && mt_rand() / mt_getrandmax() < $p;
    }
}
