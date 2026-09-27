<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();

            $table->string('name_ar');
            $table->string('name_ar_full')->nullable();
            $table->string('name_latin')->nullable();
            $table->string('slug')->unique();
            $table->string('custom_domain')->nullable()->unique();

            $table->string('plan')->default('trial');
            $table->string('status')->default('active');

            // مجال الجهة — المواصفة §4-أ. الشرعي هو المجال الوحيد المنفَّذ
            // في المرحلة الأولى، والعمود موجود حتى لا يكون فتح مجال ثانٍ ترحيلاً.
            $table->string('domain')->default('islamic');

            $table->jsonb('brand_kit')->default('{}');
            $table->text('disclaimer_text')->nullable();

            // الحصص — المواصفة §11. تُقرأ منها الحدود قبل وضع المهمّة في الطابور.
            $table->unsignedInteger('monthly_quota')->default(3);
            $table->unsignedInteger('daily_cap')->default(3);
            $table->unsignedInteger('max_lecture_minutes')->default(90);
            $table->unsignedInteger('transcription_minutes_quota')->default(0);
            $table->unsignedInteger('regenerations_per_summary')->default(2);

            $table->timestamps();

            $table->index('status');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
