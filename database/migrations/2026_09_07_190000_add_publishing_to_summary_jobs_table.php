<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مسار الملخّص الدائم وحالة نشره — المواصفة §9.
 *
 * **و`slug` فريدٌ داخل الجهة لا في النظام كلّه:** جامعان يلقيان درساً
 * بالعنوان نفسه أمرٌ متوقّع، ولا معنى لأن يزاحم أحدهما الآخر على اسمٍ
 * تحت نطاقه هو.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('state');

            $table->timestamp('published_at')->nullable()->after('finished_at');

            /*
             * **الحذف يُبقي أثراً.** المواصفة §9: «إزالة من التخزين + صفحة
             * 410 محفوظة، **لا 404**». و410 تقول «كان هنا وأُزيل»، وهي
             * الصحيحة لرابطٍ شاركه الناس؛ و404 تقول «لم يكن»، وهي كذب.
             */
            $table->timestamp('unpublished_at')->nullable()->after('published_at');

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropColumn(['slug', 'published_at', 'unpublished_at']);
        });
    }
};
