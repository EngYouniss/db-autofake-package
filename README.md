# vendororg/db-autofake

أمر Artisan لملء أي جدول قائم ببيانات وهمية بدون factories/seeders.

## التثبيت
composer require vendororg/db-autofake
# (اختياري)
composer require fakerphp/faker
composer require doctrine/dbal

## نشر الإعداد
php artisan vendor:publish --provider="VendorOrg\\DbAutofake\\DbAutofakeServiceProvider" --tag=db-autofake-config

## الاستخدام
php artisan table:fake users --rows=100 --truncate
php artisan table:fake products --rows=250 --nullable=0.3 --locale=en_US --seed=123
