<?php

declare(strict_types=1);

namespace App\Support\Publish;

use App\Actions\Summary\AddOutputLocale;
use App\Enums\Locale;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;

/**
 * ما يُضاف من اللغات إلى ملخّصٍ قائم، وحالُ ما طُلب منها — T-166.
 *
 * **مصدرٌ واحد للشاشتين وللفعل معاً.** فالمعاينةُ وشاشةُ الملخّص تعرضان
 * الزرّ، و{@see AddOutputLocale} يحرس الطلب — ولو حسب كلٌّ منها الحالَ
 * بطريقته لعرضت شاشةٌ زرّاً يرفضه الخادم.
 */
final class LocaleAdditions
{
    /** لم تُختر، وتُضاف بنداء ترجمةٍ واحد. */
    public const AVAILABLE = 'available';

    /** طُلبت ولم تنتهِ ترجمتُها. */
    public const TRANSLATING = 'translating';

    /** مختارةٌ بلا متنٍ مترجَم، ولا طلبَ قائم — تُعاد. */
    public const FAILED = 'failed';

    /** مختارةٌ ولها متن — لا شيء يُفعل. */
    public const READY = 'ready';

    /**
     * ★ **وطلبٌ قديمٌ لم ينتهِ يُعدّ ساقطاً.** فعاملٌ مات في وسط الترجمة لا
     * ينادي `failed()`، ولا يبقى إلّا `requested_at` قائماً — فتنتظر الشاشةُ
     * أبداً ما لن يأتي. ونداءُ الترجمة دقيقةٌ أو اثنتان، فربعُ ساعةٍ سعةٌ.
     */
    private const STALE_AFTER_MINUTES = 15;

    /**
     * صفٌّ لكلّ لغةٍ فيها فعلٌ أو انتظار — وما اكتمل لا يُعرض.
     *
     * @return list<array{key: string, label: string, state: string}>
     */
    public static function for(SummaryJob $job): array
    {
        $rows = self::translations($job);
        $chosen = self::chosen($job);
        $out = [];

        foreach (Locale::cases() as $locale) {
            $state = self::resolve($locale, $chosen, $rows[$locale->value] ?? null);

            if ($state === null || $state === self::READY) {
                continue;
            }

            $out[] = ['key' => $locale->value, 'label' => $locale->label(), 'state' => $state];
        }

        return $out;
    }

    /** حالُ لغةٍ واحدة — و`null` لما لا يُضاف أصلاً. */
    public static function stateOf(SummaryJob $job, Locale $locale): ?string
    {
        $row = $job->translations()->where('locale', $locale->value)->first();

        return self::resolve($locale, self::chosen($job), $row);
    }

    /** أتُرجَم هذه اللغة الآن؟ — مبدّلُ المعاينة يقول «جارٍ» لا «لم تُترجَم». */
    public static function isTranslating(?SummaryTranslation $row): bool
    {
        return $row?->requested_at !== null
            && $row->requested_at->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    /**
     * @param  list<string>  $chosen
     */
    private static function resolve(Locale $locale, array $chosen, ?SummaryTranslation $row): ?string
    {
        $isChosen = in_array($locale->value, $chosen, true);

        /*
         * ★ **ولغةُ المصدر لا تُضاف إلى ملخّصٍ لم يُخترها.**
         *
         * فالعربيةُ أولى الحالات ترتيباً ({@see Locale::primaryOf()})،
         * فإضافتُها تجعلها اللغةَ الأولى، **فينتقل الجذرُ إليها ويُزاح ما
         * كان عليه إلى مقطعٍ** — وذلك رابطٌ شاركه الناس ينقلب لغةً أخرى.
         */
        if ($locale->isSource()) {
            return $isChosen ? self::READY : null;
        }

        if (self::isTranslating($row)) {
            return self::TRANSLATING;
        }

        if ($isChosen) {
            return $row?->isReady() === true ? self::READY : self::FAILED;
        }

        return self::AVAILABLE;
    }

    /** @return list<string> */
    private static function chosen(SummaryJob $job): array
    {
        $locales = $job->lecture?->outputLocales() ?? $job->tenant?->outputLocales() ?? [Locale::source()];

        return array_map(static fn (Locale $locale): string => $locale->value, $locales);
    }

    /** @return array<string, SummaryTranslation> */
    private static function translations(SummaryJob $job): array
    {
        $out = [];

        foreach ($job->translations()->get() as $row) {
            /** @var SummaryTranslation $row */
            $out[$row->locale->value] = $row;
        }

        return $out;
    }
}
