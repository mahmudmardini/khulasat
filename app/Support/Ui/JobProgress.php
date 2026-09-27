<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Actions\Publish\UnpublishSummary;
use App\Actions\Summary\ResumeFailedJob;
use App\Domain\Summary\JobState;
use App\Models\SummaryJob;

/**
 * Projects a job's state onto the screens' vocabulary — SCREENS.md §الحالات و§4.
 *
 * **آلة الحالات لغة النظام، والشاشة لغة مدير المحتوى**، وبينهما ترجمة:
 * ثنتا عشرة حالةً تُقرأ خمساً في الفهرس («قيد الإعداد» تجمع ثمانياً)، وثمانيَ
 * مراحلَ في المتابعة. ووضعُ الترجمة هنا يمنع أن تُكتب في كلّ شاشة على وجه.
 *
 * **ولا نسبة مئوية.** «النسبة المخترعة تكذب، والمستخدم يكتشف كذبها» — §4.
 * والمراحل غير متساوية زمناً، فتقسيمُها بالتساوي كذبٌ يُقاس.
 */
final class JobProgress
{
    /**
     * المراحل بترتيب عرضها — SCREENS.md §4.
     *
     * والمراجعة فيها **وإن لم تكن نداءَ نموذج**: هي الموضع الذي يقف عنده
     * الخطّ منتظراً إنساناً، وإسقاطُها من العرض يُخفي عن المستخدم أنّ
     * الوقوف عنده هو لا عندنا.
     *
     * @var list<array{key: string, state: JobState}>
     */
    private const STEPS = [
        ['key' => 'transcribing', 'state' => JobState::Transcribing],
        ['key' => 'cleaning', 'state' => JobState::Cleaning],
        ['key' => 'structuring', 'state' => JobState::ExtractingStructure],
        ['key' => 'extracting', 'state' => JobState::ExtractingEvidence],
        ['key' => 'verifying', 'state' => JobState::Verifying],
        ['key' => 'review', 'state' => JobState::NeedsReview],
        ['key' => 'writing', 'state' => JobState::Writing],
        ['key' => 'rendering', 'state' => JobState::Rendering],
    ];

    private function __construct() {}

    /**
     * الحالة كما تُعرض شارةً في الفهرس.
     *
     * ثماني حالاتٍ تُقرأ «قيد الإعداد» واحدةً: التمييز بينها يعني شيئاً لنا
     * ولا يعني شيئاً لمن ينتظر ملخّصه. و`cancelled` تُعرض «غير منشور» لا
     * «متوقّف»: الإلغاء قرارٌ لا عطل.
     */
    public static function badge(JobState $state): string
    {
        return match ($state) {
            JobState::ExtractingStructure => 'structuring',
            JobState::ExtractingEvidence => 'extracting',
            JobState::Cancelled => 'unpublished',
            default => $state->value,
        };
    }

    /**
     * الشارة كما تُعرض لمهمّةٍ بعينها — T-28.
     *
     * ★ **والمُزالة تُعرض «غير منشور» وإن بقيت حالتُها `published`.**
     *
     * فـ{@see UnpublishSummary} لا ينقل آلة الحالات —
     * وهذا صحيح، فالإزالة ليست مرحلةً في الخطّ — لكنّه يضع `unpublished_at`.
     * وشارةٌ تقرأ الحالةَ وحدها **تقول لصاحب الجهة «منشور» وصفحتُه تردّ
     * 410**، وهو أسوأ ما يُقال له.
     */
    public static function badgeFor(SummaryJob $job): string
    {
        return $job->unpublished_at !== null ? 'unpublished' : self::badge($job->state);
    }

    /**
     * المراحل الثماني وحالُ كلٍّ منها — لـ`StepTracker`.
     *
     * **و`needs_review` «بانتظارك» لا «جارية»**: الجارية تعمل من نفسها،
     * وهذه لا تتحرّك حتى يفعل المستخدم شيئاً. والفرق بينهما هو الفرق بين
     * «انتظرْ» و«افعلْ».
     *
     * ★ **ومعها زمنُها** — T-92، و§4 نصّاً: «تمّت (✓ وزمنها)». من سجلّ
     * الانتقالات لا تقديراً: ما تمّ أو توقّف فمدّتُه بين دخوله وخروجه،
     * والجاريةُ ببدئها وحده (والعدُّ إلى الآن في المتصفّح بساعة من يقرأ)،
     * وما لم يبدأ لا زمنَ له.
     *
     * @return list<array{key: string, state: string, started_at: string|null, seconds: int|null, skipped: bool}>
     */
    public static function steps(SummaryJob $job): array
    {
        // **المتوقّفة تُعرَض عند موضع توقّفها لا في آخر الصفّ.** والحالة
        // `failed` لا تقول أين وقعت، فتُقرأ من آخر انتقال: `from_state`
        // هو المرحلة التي كانت جارية حين سقطت.
        $current = $job->state;
        $stalled = $current->isTerminal() && $current !== JobState::Published
            ? self::lastRunningState($job)
            : null;

        $index = self::indexOf($stalled ?? $current);
        [$entered, $left] = self::timeline($job);

        return array_values(array_map(
            static function (array $step, int $position) use ($current, $index, $entered, $left): array {
                $state = self::stateOf($position, $index, $current);
                $value = $step['state']->value;

                $in = $state === 'pending' ? null : ($entered[$value] ?? null);
                $out = in_array($state, ['done', 'failed'], true) ? ($left[$value] ?? null) : null;

                return [
                    'key' => $step['key'],
                    'state' => $state,
                    'started_at' => $in?->toIso8601String(),
                    // فرقُ الطابعين بالثواني — لا `diffIn*` فإشارتُها تبدّلت بين إصدارات Carbon.
                    'seconds' => $in !== null && $out !== null ? max(0, $out->getTimestamp() - $in->getTimestamp()) : null,
                    /*
                     * **مراجعةٌ تجاوزها الخطّ ولم يدخلها قطّ** — كلُّ شاهدٍ طابق
                     * مصدره آلياً فلم يُحتج إلى المستخدم. تُعلَّم بذلك، ولا يُنسب
                     * إليها زمنٌ لم يُصرف.
                     */
                    'skipped' => $step['state'] === JobState::NeedsReview && $state === 'done' && $in === null,
                ];
            },
            self::STEPS,
            array_keys(self::STEPS),
        ));
    }

    /**
     * دخولُ كلّ حالةٍ وخروجُها من سجلّ الانتقالات — T-92.
     *
     * **أوّلُ دخولٍ وآخرُ خروج**: فإعادةُ المحاولة الآلية داخل الحالة لا تكتب
     * انتقالاً، وإن كُتب فالمدّةُ كلُّها ما قضاه الخطّ فيها.
     *
     * @return array{0: array<string, \Carbon\CarbonInterface>, 1: array<string, \Carbon\CarbonInterface>}
     */
    private static function timeline(SummaryJob $job): array
    {
        $entered = [];
        $left = [];

        foreach ($job->transitions()->get(['from_state', 'to_state', 'occurred_at']) as $transition) {
            if ($transition->to_state !== null && $transition->occurred_at !== null) {
                $entered[$transition->to_state->value] ??= $transition->occurred_at;
            }

            if ($transition->from_state !== null && $transition->occurred_at !== null) {
                $left[$transition->from_state->value] = $transition->occurred_at;
            }
        }

        return [$entered, $left];
    }

    /** أين وقف الخطّ؟ — وموضعُ الحالة النهائية آخرُ الصفّ. */
    private static function indexOf(JobState $state): int
    {
        foreach (self::STEPS as $position => $step) {
            if ($step['state'] === $state) {
                return $position;
            }
        }

        // `queued` قبل الأولى، والنهائية بعد الأخيرة.
        return $state === JobState::Queued ? -1 : count(self::STEPS);
    }

    /**
     * آخر مرحلةٍ كانت تعمل قبل التوقّف — من سجلّ الانتقالات لا من التخمين.
     *
     * **عامّةٌ لأنّ {@see ResumeFailedJob} تحتاجها
     * أيضاً** — T-93. نقطةُ الاستئناف بعد إخفاقٍ هي نفسها نقطةُ التوقّف
     * المعروضة هنا، فلا مكان لحسابها مرّتين بمنطقين قد يتباعدان.
     */
    public static function lastRunningState(SummaryJob $job): ?JobState
    {
        $transition = $job->transitions()
            ->where('to_state', $job->state->value)
            ->latest('occurred_at')
            ->first();

        return $transition?->from_state;
    }

    private static function stateOf(int $position, int $index, JobState $current): string
    {
        if ($position < $index) {
            return 'done';
        }

        if ($position > $index) {
            // المُلغاة والمخفقة لا تُظهر ما بعدها «لم يبدأ» فحسب — وهو صحيح،
            // فما بعد التوقّف لم يبدأ فعلاً.
            return 'pending';
        }

        return match (true) {
            $current === JobState::Failed, $current === JobState::Cancelled => 'failed',
            $current === JobState::NeedsReview => 'awaiting',
            default => 'active',
        };
    }
}
