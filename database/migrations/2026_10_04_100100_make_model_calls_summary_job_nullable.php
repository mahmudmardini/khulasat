<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * نداءٌ لا يخصّ ملخّصاً — T-173.
 *
 * توليدُ قوالب الكاروسيل نداءٌ مدفوع للجهة نفسها لا لملخّصٍ منها، وكان
 * `summary_job_id` إلزامياً فلا يُقيَّد نداءٌ كهذا أصلاً: لا يظهر في الكلفة،
 * ولا يحسبه سقفُ الإنفاق. وسجلُّ الاستعمال (`usage_ledger`) يقبله من قبل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_calls', function (Blueprint $table): void {
            $table->foreignId('summary_job_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('model_calls', function (Blueprint $table): void {
            $table->foreignId('summary_job_id')->nullable(false)->change();
        });
    }
};
