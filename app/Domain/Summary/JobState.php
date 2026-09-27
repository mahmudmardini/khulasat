<?php

declare(strict_types=1);

namespace App\Domain\Summary;

use App\Actions\Summary\TransitionJob;
use App\Models\SummaryJob;

/**
 * The generation job state machine — المواصفة §5.
 *
 * الانتقالات المسموحة **مذكورة صراحةً** في {@see self::allowedTransitions()}،
 * وما عداها يرمي {@see InvalidTransition}. ولا يوجد انتقال «عامّ» ولا إعداد
 * يفتح انتقالاً مغلقاً: آلة الحالات التي تُفتح بإعداد ليست آلة حالات.
 *
 * ولا تُغيَّر حالة مهمّة إلا عبر {@see TransitionJob}،
 * ويمنع {@see SummaryJob} ما عداه.
 */
enum JobState: string
{
    case Queued = 'queued';
    case Transcribing = 'transcribing';
    case Cleaning = 'cleaning';
    case ExtractingStructure = 'extracting_structure';
    case ExtractingEvidence = 'extracting_evidence';
    case Verifying = 'verifying';
    case NeedsReview = 'needs_review';
    case Writing = 'writing';
    case Rendering = 'rendering';
    case Published = 'published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** ثلاث محاولات، بتراجع أُسّي — المواصفة §5. */
    public const MAX_AUTOMATIC_ATTEMPTS = 3;

    /** أساس التراجع الأُسّي بالثواني: ٣٠، ٦٠، ١٢٠. */
    private const RETRY_BASE_SECONDS = 30;

    /**
     * The states this one may move to. **هذه هي الخريطة، ولا خريطة غيرها.**
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Queued => [self::Transcribing, self::Failed, self::Cancelled],
            self::Transcribing => [self::Cleaning, self::Failed, self::Cancelled],
            self::Cleaning => [self::ExtractingStructure, self::Failed, self::Cancelled],
            self::ExtractingStructure => [self::ExtractingEvidence, self::Failed, self::Cancelled],
            self::ExtractingEvidence => [self::Verifying, self::Failed, self::Cancelled],

            // فرعان: needs_review إن وُجد شاهد match_status != exact، وإلا writing
            // مباشرةً — المواصفة §5. والفرز قرار طبقة التحقّق لا قرار الآلة.
            self::Verifying => [self::NeedsReview, self::Writing, self::Failed, self::Cancelled],

            // **لا failed هنا.** الانتظار البشري لا يفشل من نفسه، ولا ينتهي
            // بمهلة. وما يبقى للإنسان هو الحسم أو الإلغاء.
            self::NeedsReview => [self::Writing, self::Cancelled],

            self::Writing => [self::Rendering, self::Failed, self::Cancelled],
            self::Rendering => [self::Published, self::Failed, self::Cancelled],

            self::Published, self::Failed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), strict: true);
    }

    /**
     * @throws InvalidTransition عند انتقال غير مذكور في الخريطة.
     */
    public function transitionTo(self $to): self
    {
        if (! $this->canTransitionTo($to)) {
            throw InvalidTransition::between($this, $to);
        }

        return $to;
    }

    /** المواصفة §5: published و failed و cancelled. */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Whether a failed *call* in this state may be retried automatically.
     *
     * المواصفة §5: التفريغ والتنظيف والإخراج وحدها. وهذه إعادةٌ لاستدعاء
     * **سقط**، لا لاستدعاء نجح ورداءت نتيجته — انظر {@see self::isModelStage()}.
     */
    public function allowsAutomaticRetry(): bool
    {
        return match ($this) {
            self::Transcribing, self::Cleaning, self::Rendering => true,
            default => false,
        };
    }

    /**
     * Whether this state calls a language model.
     *
     * المواصفة §5: **مراحل النماذج لا تُعاد آلياً عند نجاح الاستدعاء ورداءة
     * النتيجة.** الإعادة قرار بشري، وتُحتسب من `regenerations_per_summary`.
     * ولذلك لا مسار آليّ يعيد الدخول إلى أيّ من هذه الحالات: الخريطة أعلاه
     * لا تحمل انتقالاً راجعاً واحداً.
     */
    public function isModelStage(): bool
    {
        return match ($this) {
            self::Cleaning, self::ExtractingStructure, self::ExtractingEvidence, self::Writing => true,
            default => false,
        };
    }

    /**
     * Whether the state may be abandoned by a timeout.
     *
     * المواصفة §5: **`needs_review` لا تنتهي بمهلة. تنتظر الإنسان.** ومهلةٌ
     * على المراجعة تعني نشر شاهدٍ لم يحسمه أحد، وهو بعينه ما بُني هذا المنتج
     * ليمنعه.
     */
    public function expiresByTimeout(): bool
    {
        return ! $this->isTerminal() && $this !== self::NeedsReview;
    }

    /**
     * المواصفة §5: التوقّف عند `needs_review` **يمنع النشر منعاً باتّاً**،
     * ولا يوجد إعداد يتجاوزه. والخريطة تنفّذ هذا بنفسها: لا طريق من
     * `needs_review` إلى `published` إلا عبر `writing`، ولا يُدخل `writing`
     * إلا وكلّ شاهدٍ محسوم.
     */
    public function blocksPublishing(): bool
    {
        return $this === self::NeedsReview;
    }

    /** تراجع أُسّي: ٣٠ ثانية، ثم ٦٠، ثم ١٢٠. */
    public static function retryDelaySeconds(int $attempt): int
    {
        return self::RETRY_BASE_SECONDS * (2 ** max(0, $attempt - 1));
    }
}
