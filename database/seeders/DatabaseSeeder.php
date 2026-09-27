<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * إعداد النماذج وحده. لا جهات ولا مستخدمين ولا محاضرات: البيانات تُنشأ
     * على النسخة المنشورة نفسها، لا من المستودع.
     */
    public function run(): void
    {
        $this->call(ModelConfigSeeder::class);
    }
}
