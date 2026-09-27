<?php

declare(strict_types=1);

namespace App\Actions\Summary;

use App\Domain\Summary\JobState;
use App\Http\Controllers\Admin\AdminJobController;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Ui\JobProgress;
use Illuminate\Support\Facades\DB;

/**
 * يخلق مهمّةً خَلَفاً تستأنف من آخر مرحلةٍ ناجحة — لا من الصفر — T-93.
 *
 * **كانت هذه موجودةً في لوحة المشرف وحدها** ({@see AdminJobController::retry()}),
 * فمن استعمل زرّ «أعدْ المحاولة» من شاشته أعاد التفريغ والبنية والشواهد
 * كلَّها — وهو عين الإهدار الذي صُمّمت آلة الحالات لتفاديه: كلّ فعلٍ ينقل
 * الحالة بنفسه ويحفظ نتاجه، **وإحياء الصفر يرمي ما حُفظ**.
 *
 * فالمنطق يُستخرج إلى موضعٍ واحد يستعمله المساران معاً — المشرف والمستخدم
 * — لا منطقان قد يتباعدان صامتَين.
 *
 * ★ **والحالة `failed` تبقى نهائية.** هذا لا يُحيي المهمّة المتوقّفة —
 * ذاك ممنوعٌ (§5، `InvalidTransition::outsideStateMachine`) — بل يخلق
 * أختها التي تبدأ من حيث وقفت، فيبقى سجلّ الانتقالات صادقاً: ما وقع قد
 * وقع، والمحاولة الثانية محاولةٌ ثانية بهويّتها.
 */
final class ResumeFailedJob
{
    /**
     * @return list<string>
     *
     * الحقول المنسوخة من {@see EvidenceItem} — حرفاً، فالخَلَف يحمل قرار
     * التحقّق والمراجعة كما استقرّ لا كما يُعاد تخمينه.
     */
    private const EVIDENCE_FIELDS = [
        'tenant_id', 'domain', 'kind', 'raw_text', 'normalized_text',
        'matched_text', 'source_ref', 'source_meta', 'match_status',
        'review_status', 'resolved_by', 'resolved_at',
    ];

    public function handle(SummaryJob $job): SummaryJob
    {
        $resumeAt = JobProgress::lastRunningState($job) ?? JobState::Queued;

        return DB::transaction(function () use ($job, $resumeAt): SummaryJob {
            $fresh = SummaryJob::query()->create([
                'lecture_id' => $job->lecture_id,
                'tenant_id' => $job->tenant_id,
                // الإنشاء ليس انتقالاً، فالخَلَف يبدأ حيث وقف سلفُه —
                // كما تبدأ كلُّ مهمّةٍ في `queued` بلا انتقالٍ يسبقه.
                'state' => $resumeAt->value,
                // محاولةٌ ثانيةٌ بالفعل: لا تُمنح ثلاثَ محاولاتٍ آليةً كاملة
                // من جديد، فتدور مهمّةٌ فاشلة إلى ما لا نهاية عبر أخواتها.
                'attempt' => 1,
                'transcript_source' => $job->transcript_source,
                // النصّ الملصوق يُنقل، فلا يُطلب من المستخدم لصقُه ثانيةً —
                // وهذا صحيحٌ في كلّ نقطة استئناف: خامٌ، أو منظَّفٌ، سيّان.
                'transcript_text' => $job->transcript_text,
                'transcript_word_count' => $job->transcript_word_count,
                'structure_json' => $job->structure_json,
                'evidence_json' => $job->evidence_json,
                /*
                 * ★ **`body_json` يُنسخ مع `body_html`** — T-93. كان
                 * `body_html` وحده يُنسخ، وهذا يكفي لصفحة المصدر
                 * ({@see \App\Support\Render\ContentObject::fromJob()}
                 * تقرأ `body_html` للغة المصدر)، **ولا يكفي لترجمتها**:
                 * `TranslateSummary::handle()` يقرأ `body_json`، فمهمّةٌ
                 * استؤنفت عند `rendering` بلغاتٍ إضافية كانت تُترجم من
                 * متنٍ فارغ صامتةً — لا خطأ يُرى، وترجمةٌ لا معنى فيها.
                 */
                'body_json' => $job->body_json,
                'body_html' => $job->body_html,
            ]);

            // والشواهد تُنسخ معها: من استُؤنف عند «الكتابة» أو ما بعدها
            // يحتاج ما حُقّق قبله، وإلّا كُتب متنٌ بلا شواهده أو رُسمت
            // صفحةٌ بلا مواضع تخريجها.
            foreach ($job->evidenceItems()->get() as $item) {
                EvidenceItem::query()->create([
                    ...$item->only(self::EVIDENCE_FIELDS),
                    'summary_job_id' => $fresh->id,
                ]);
            }

            return $fresh;
        });
    }
}
