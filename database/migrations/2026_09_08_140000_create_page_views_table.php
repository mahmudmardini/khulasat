<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `page_views` — عدّاد فتحات الصفحات المنشورة، SCREENS.md §7 (T-31).
 *
 * ★ **ومجموعٌ يوميّ لا صفٌّ لكلّ فتحة.**
 *
 * فصفٌّ لكلّ زيارة يعني جدولاً ينمو بلا حدّ لقيمةٍ كلُّ ما يُطلب منها عددٌ
 * في بطاقة. **ويعني أسوأ من ذلك:** طابعاً زمنياً دقيقاً لكلّ فتحة مع عنوان
 * الصفحة — وهو أثرٌ يقارب سلوك القارئ، ونحن نعدّ الصفحة لا الناس.
 *
 * والتجميع باليوم يعطي المجموع والاتّجاه معاً، ولا يحفظ عن القارئ شيئاً:
 * **لا عنوان شبكة، ولا كوكي، ولا بصمة متصفّح**.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            /*
             * **وتسقط مع الملخّص.** فالحذف النهائي (T-30) «يمحو المتن
             * والشواهد والمخرجات»، وعدّادُ صفحةٍ ممحوّة لا معنى له.
             */
            $table->foreignId('summary_job_id')->constrained()->cascadeOnDelete();

            // page | carousel — App\Enums\OutputType. نصٌّ بقائمة سماح لا
            // enum، كسائر الجداول: عارضٌ جديد لا يجوز أن يكون ترحيلاً.
            $table->string('output_type');

            $table->date('day');
            $table->unsignedInteger('views')->default(0);

            // مفتاح الزيادة الذرّية — `on conflict` يستند إليه.
            $table->unique(['summary_job_id', 'output_type', 'day']);
            $table->index(['tenant_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
