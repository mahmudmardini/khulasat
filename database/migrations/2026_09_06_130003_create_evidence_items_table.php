<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الشواهد المستخرجة وأحكام التحقّق عليها — المواصفة §4 و§4-أ.
     *
     * الأعمدة الصريحة هنا **ما يشترك فيه كلّ مجال**. وما يخصّ المجال الشرعي
     * (رقم السورة والآية، والراوي، والكتاب، والدرجة، والتخريج) يسكن
     * `source_meta`. والجدولُ الذي يحمل `surah_number` عموداً صريحاً يقفل
     * المنتج على مجاله، وفكُّه بعد امتلائه ترحيلُ بيانات حيّة.
     */
    public function up(): void
    {
        Schema::create('evidence_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('summary_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // يُنسخ من tenants.domain عند الإنشاء، فيبقى الحكم مقروءاً بعد
            // تغيير مجال الجهة. ومنه يُحلّ المحقّق بمفتاح (domain, kind).
            $table->string('domain')->default('islamic');

            // **نصّ بقائمة سماح لكل مجال، لا enum في القاعدة** — المواصفة §4-أ.
            // إضافة نوع شاهد في مجال جديد لا يجوز أن تكون ترحيلاً.
            $table->string('kind');

            $table->text('raw_text');
            $table->text('normalized_text');
            $table->text('matched_text')->nullable();
            $table->string('source_ref')->nullable();
            $table->jsonb('source_meta')->default('{}');

            // exact | partial | none — App\Enums\MatchStatus.
            $table->string('match_status');

            // auto_passed | pending | approved | corrected | removed — App\Enums\ReviewStatus.
            $table->string('review_status')->default('pending');

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->index('summary_job_id');
            $table->index(['tenant_id', 'review_status']);
            $table->index(['domain', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_items');
    }
};
