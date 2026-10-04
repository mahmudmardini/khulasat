<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Actions\Publish\UnpublishSummary;
use App\Actions\Summary\ResumeFailedJob;
use App\Domain\Summary\JobState;
use App\Models\SummaryJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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

        $steps = array_values(array_map(
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

        return self::withQuiz($job, $steps);
    }

    /**
     * «بناء الاختبار» — T-195، ظاهرةً لمن طلبه.
     *
     * ★ **ليست حالةً في الآلة**: يُبنى في أوّل الإخراج قبل رسم الصفحة،
     * فتُقرأ حالُها من الاختبار نفسه. ما دام يُبنى فالإخراجُ لم يبدأ بعد في
     * العرض، وإلّا ظهرت مرحلتان جاريتان معاً.
     *
     * **وتعذُّرُه لا يُسقط الملخّص** — فيُعلَّم «توقّفت» عنده وحده، والصفحةُ
     * تمضي إلى النشر.
     *
     * @param  list<array{key: string, state: string, started_at: string|null, seconds: int|null, skipped: bool}>  $steps
     * @return list<array{key: string, state: string, started_at: string|null, seconds: int|null, skipped: bool}>
     */
    private static function withQuiz(SummaryJob $job, array $steps): array
    {
        if ($job->lecture?->want_quiz !== true) {
            return $steps;
        }

        $at = array_search('rendering', array_column($steps, 'key'), true);
        $rendering = $steps[$at];
        $quiz = $job->quiz()->first(['id', 'state', 'generated_at', 'updated_at']);
        $began = $rendering['started_at'];

        $step = ['key' => 'quiz', 'state' => 'pending', 'started_at' => null, 'seconds' => null, 'skipped' => false];

        if ($quiz !== null) {
            $end = $quiz->generated_at ?? $quiz->updated_at;
            $step['state'] = $quiz->isReady() ? 'done' : 'failed';
            $step['started_at'] = $began;
            $step['seconds'] = $began !== null && $end !== null
                ? max(0, $end->getTimestamp() - Carbon::parse($began)->getTimestamp())
                : null;

            // والإخراجُ يبدأ في العرض حيث انتهى الاختبار، فلا يُعدّ زمنُه مرّتين.
            if ($end !== null && $rendering['started_at'] !== null) {
                $steps[$at]['started_at'] = $end->toIso8601String();
            }
            if ($rendering['seconds'] !== null && $step['seconds'] !== null) {
                $steps[$at]['seconds'] = max(0, $rendering['seconds'] - $step['seconds']);
            }
        } elseif ($rendering['state'] === 'active') {
            $step['state'] = 'active';
            $step['started_at'] = $began;
            $steps[$at]['state'] = 'pending';
            $steps[$at]['started_at'] = null;
        }

        array_splice($steps, $at, 0, [$step]);

        return $steps;
    }

    /**
     * دخولُ كلّ حالةٍ وخروجُها من سجلّ الانتقالات — T-92.
     *
     * **أوّلُ دخولٍ وآخرُ خروج**: فإعادةُ المحاولة الآلية داخل الحالة لا تكتب
     * انتقالاً، وإن كُتب فالمدّةُ كلُّها ما قضاه الخطّ فيها.
     *
     * @return array{0: array<string, CarbonInterface>, 1: array<string, CarbonInterface>}
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

    /**
     * وقتُ المراجعة — T-171: ما قضته المهمّة بيد الجهة لا بيدنا.
     *
     * ★ **«استغرق» يُحسب بلا هذا الوقت.** ومراجعةٌ دامت ست عشرة دقيقة
     * من عشرين كانت تُقرأ بطئاً في المنتج، والوقتُ وقتُ الجهة نفسها.
     *
     * **مجموعُ كلّ فترات `needs_review`** لا الأولى وحدها: مهمّةٌ تعود
     * إلى المراجعة بعد إعادة محاولة تدخلها مرّتين. والفترةُ المفتوحة —
     * مهمّةٌ تنتظر القرار الآن — لا تدخل المجموع، ويُرجَع بدؤها ليقف
     * عنده العدّاد في المتصفّح.
     *
     * @return array{seconds: int, open_since: string|null}
     */
    public static function review(SummaryJob $job): array
    {
        $seconds = 0;
        $since = null;

        foreach ($job->transitions()->get(['from_state', 'to_state', 'occurred_at']) as $transition) {
            if ($transition->occurred_at === null) {
                continue;
            }

            if ($transition->from_state === JobState::NeedsReview && $since !== null) {
                $seconds += max(0, $transition->occurred_at->getTimestamp() - $since->getTimestamp());
                $since = null;
            }

            if ($transition->to_state === JobState::NeedsReview) {
                $since = $transition->occurred_at;
            }
        }

        return [
            'seconds' => $seconds,
            'open_since' => $job->state === JobState::NeedsReview ? $since?->toIso8601String() : null,
        ];
    }

    /**
     * زمنُ الإعداد لمهمّةٍ انتهت — من بدئها إلى انتهائها، بلا وقت المراجعة (T-171).
     * و`null` لما لم ينتهِ: الجاريةُ يعدّها المتصفّح بساعة من يقرأ.
     */
    public static function preparationSeconds(SummaryJob $job): ?int
    {
        if ($job->started_at === null || $job->finished_at === null) {
            return null;
        }

        $total = $job->finished_at->getTimestamp() - $job->started_at->getTimestamp();

        return max(0, $total - self::review($job)['seconds']);
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
