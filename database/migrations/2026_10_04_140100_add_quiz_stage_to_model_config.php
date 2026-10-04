<?php

declare(strict_types=1);

use Database\Seeders\ModelConfigSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * صفُّ المرحلة ٨ — T-195.
     *
     * **بالترحيل لا بالبذر وحده**: البذرُ يكتب الصفوف كلَّها فوق ما غيّره
     * المشرف من شاشة النماذج، فلا يُعاد على نسخةٍ منشورة. وبلا صفٍّ تقف
     * المرحلة بـ`model_not_configured` عند أوّل اختبار. فيُضاف الصفّ إن غاب،
     * ولا يُمسّ إن وُجد.
     */
    public function up(): void
    {
        $row = ModelConfigSeeder::row('quiz');

        if ($row === null || DB::table('model_config')->where('stage', 'quiz')->exists()) {
            return;
        }

        DB::table('model_config')->insert($row + ['updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('model_config')->where('stage', 'quiz')->delete();
    }
};
