<?php

namespace VendorOrg\DbAutofake\Support;

use Illuminate\Support\Str;

class ValueGenerator
{
    protected $faker;
    protected $useFaker = false;

    public function __construct(?string $locale = null, ?int $seed = null)
    {
        if (class_exists(\Faker\Factory::class)) {
            $this->faker = \Faker\Factory::create($locale ?: 'ar_SA');
            if ($seed !== null) {
                $this->faker->seed($seed);
                mt_srand($seed);
            }
            $this->useFaker = true;
        }
    }

    public function byColumn(string $name, string $type, ?int $length = null)
    {
        $n = strtolower($name);

        // حقول شائعة تُملأ في الأمر حسب الكونفيج
        if (in_array($n, ['created_at','updated_at','deleted_at'], true)) {
            return '__SKIP__';
        }

        // حسب اسم العمود (إن وُجد Faker)
        if ($this->useFaker) {
            if (preg_match('/email/', $n)) return $this->faker->unique()->safeEmail();
            if (preg_match('/(user_?name|login)/', $n)) return $this->faker->userName();
            if (preg_match('/(full_?name|name)/', $n)) return $this->faker->name();
            if (preg_match('/first_?name/', $n)) return $this->faker->firstName();
            if (preg_match('/last_?name/', $n)) return $this->faker->lastName();
            if (preg_match('/(phone|mobile|tel)/', $n)) return $this->faker->phoneNumber();
            if (preg_match('/(address|addr)/', $n)) return $this->faker->address();
            if (preg_match('/city/', $n)) return $this->faker->city();
            if (preg_match('/country/', $n)) return $this->faker->country();
            if (preg_match('/(zip|postal)/', $n)) return $this->faker->postcode();
            if (preg_match('/(company)/', $n)) return $this->faker->company();
            if (preg_match('/(title|subject|heading)/', $n)) return $this->faker->sentence(3);
            if (preg_match('/slug/', $n)) return Str::slug($this->faker->sentence(3));
            if (preg_match('/(desc|description|summary)/', $n)) return $this->faker->paragraph();
            if (preg_match('/(content|body|notes|remarks)/', $n)) return $this->faker->paragraphs(2, true);
            if (preg_match('/(url|link)/', $n)) return $this->faker->url();
            if (preg_match('/(image|avatar|photo|thumb)/', $n)) return $this->faker->imageUrl(640, 480, 'cats', true);
            if (preg_match('/ip/', $n)) return $this->faker->ipv4();
            if (preg_match('/mac/', $n)) return $this->faker->macAddress();
            if (preg_match('/(password|pass)/', $n)) return bcrypt('password');
            if (preg_match('/remember_token/', $n)) return Str::random(10);
        }

        // وإلا: حسب النوع
        return $this->byType($type, $length);
    }

    public function byType(string $type, ?int $length = null)
    {
        if ($this->useFaker) {
            return match ($type) {
                'integer','bigint','smallint'           => $this->faker->numberBetween(0, 1_000_000),
                'decimal','float'                        => $this->faker->randomFloat(2, 0, 999999),
                'boolean'                                => $this->faker->boolean(),
                'date'                                   => $this->faker->date(),
                'datetime','datetimetz','timestamp'      => $this->faker->dateTime(),
                'time'                                   => $this->faker->time(),
                'json'                                   => json_encode(['k'=>$this->faker->word(),'v'=>$this->faker->sentence()], JSON_UNESCAPED_UNICODE),
                'text'                                   => $this->faker->paragraph(),
                default                                  => $this->limit($this->faker->sentence(3), $length),
            };
        }

        // بديل بدون Faker
        switch ($type) {
            case 'integer':
            case 'bigint':
            case 'smallint':
                return random_int(0, 1_000_000);
            case 'decimal':
            case 'float':
                return (float) number_format(mt_rand(0, 999999) + mt_rand()/mt_getrandmax(), 2, '.', '');
            case 'boolean':
                return (bool) random_int(0,1);
            case 'date':
                return date('Y-m-d');
            case 'datetime':
            case 'datetimetz':
            case 'timestamp':
                return date('Y-m-d H:i:s');
            case 'time':
                return date('H:i:s');
            case 'json':
                return json_encode(['k'=>'val','n'=>random_int(1,9999)], JSON_UNESCAPED_UNICODE);
            case 'text':
                return $this->limit(bin2hex(random_bytes(64)), $length);
            default:
                return $this->limit(bin2hex(random_bytes(32)), $length);
        }
    }

    protected function limit(string $s, ?int $len)
    {
        if ($len && $len > 0) return mb_substr($s, 0, $len);
        return $s;
    }
}
