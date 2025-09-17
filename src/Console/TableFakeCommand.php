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
        {table : اسم الجدول}
        {--rows=50 : عدد الصفوف}
        {--truncate : تفريغ الجدول قبل الإدراج}
        {--locale= : لغة Faker إن وُجد}
        {--nullable= : نسبة ترك الحقول Nullable (0..1)}
        {--chunk= : حجم الدفعة}
        {--seed= : تثبيت العشوائية}';

    protected $description = 'يملأ أي جدول قائم ببيانات وهمية اعتمادًا على أعمدته. الاعتمادات Faker/DBAL اختيارية.';

    public function handle()
    {
        $table     = $this->argument('table');
        $rows      = (int) ($this->option('rows') ?: 50);
        $truncate  = (bool) $this->option('truncate');

        if (!Schema::hasTable($table)) {
            $this->error("الجدول {$table} غير موجود.");
            return self::FAILURE;
        }

        $cfg      = config('db_autofake');
        $locale   = $this->option('locale')   ?: ($cfg['locale'] ?? 'ar_SA');
        $nullable = $this->option('nullable') !== null ? (float)$this->option('nullable') : ($cfg['nullable_probability'] ?? 0.1);
        $chunk    = $this->option('chunk')    !== null ? (int)$this->option('chunk')       : ($cfg['chunk'] ?? 500);
        $seed     = $this->option('seed')     !== null ? (int)$this->option('seed')        : null;

        if ($truncate) {
            DB::table($table)->truncate();
            $this->info("تم تفريغ {$table}.");
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

                // timestamps من الكونفيج
                if (isset($cfg['timestamps'][$lname])) {
                    $mode = $cfg['timestamps'][$lname];
                    if ($mode === 'now')   { $row[$name] = now(); }
                    elseif ($mode === 'null') { $row[$name] = null; }
                    continue;
                }

                // nullable نسبة
                if ($c->nullable && $this->chance($nullable)) {
                    $row[$name] = null;
                    continue;
                }

                // توليد
                $val = $gen->byColumn($name, $c->type, $c->length);
                if ($val === '__SKIP__') continue;

                if (is_string($val) && $c->length && $c->length > 0) {
                    $val = mb_substr($val, 0, $c->length);
                }

                $row[$name] = $val;
            }

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

        $this->info("تم إدراج {$inserted} صف(وف) في {$table}.");
        return self::SUCCESS;
    }

    protected function chance(float $p): bool
    {
        return $p > 0 && mt_rand() / mt_getrandmax() < $p;
    }
}
