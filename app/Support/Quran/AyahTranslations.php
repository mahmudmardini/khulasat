<?php

declare(strict_types=1);

namespace App\Support\Quran;

use App\Enums\Locale;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\QuranAyah;
use App\Models\QuranTranslation;
use App\Models\SummaryJob;
use App\Support\Arabic;
use Illuminate\Support\Facades\Log;

/**
 * Approved ayah translations placed under their ayat — T-80.
 *
 * ★★ **ولا يترجم نموذجٌ آيةً** (T-38): الترجمةُ تُقرأ من `quran_translations`
 * المبذور من ترجماتٍ معتمدةٍ منشورة، فهي حتميّةٌ كمطابقتها.
 *
 * **والربطُ حتميٌّ كذلك**: كتلةُ الآية تُطابَق بلفظها المطبَّع على شاهدٍ
 * مثبَّتٍ له سورةٌ ورقم — وحارسُ T-73 جعل لفظَ الكتلة لفظَ المثبَّت حرفاً.
 * وما لم يُربط يخرج عربياً وحده: **ترجمةٌ مخمَّنةٌ تحت آيةٍ نسبةُ معنًى إلى
 * كتاب الله في غير موضعه.**
 */
final class AyahTranslations
{
    private function __construct() {}

    /**
     * يضع في كلّ كتلة آيةٍ مربوطة ترجمتَها وتخريجَها بلسان الصفحة.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|null
     */
    public static function apply(?array $body, SummaryJob $job, Locale $locale): ?array
    {
        if ($body === null || $locale->isSource()) {
            return $body;
        }

        $refs = self::references($job);

        if ($refs === []) {
            return $body;
        }

        /*
         * ★★ **وسقوطُها لا يكون صامتاً — T-141.**
         *
         * فمخارجُها الصامتة ثلاثة: جدولٌ غير مبذور، ولفظٌ لا يُطابق موضعاً،
         * ونصفُ ترجمة. وصمتُها هو سببُ أنّ ملخّصين على الخادم خرجا بآياتٍ
         * عاريةٍ من ترجمتها **ولم يُعلَم إلّا ببلاغِ مالك المنتج**.
         *
         * **والجدولُ الفارغ يُقيَّد وحده أوّلاً**: هو العلّةُ الجامعة — كلُّ
         * آيةٍ في الملخّص تسقط معه، فسطرٌ واحدٌ يقولها خيرٌ من سطرٍ لكلّ آية.
         */
        if (QuranTranslation::query()->where('locale', $locale->value)->doesntExist()) {
            Log::warning('ayah_translations.locale_not_seeded', [
                'summary_job_id' => $job->id,
                'locale' => $locale->value,
                'ayat' => count($refs),
                'fix' => 'php artisan khulasah:seed-quran-translations --locale='.$locale->value,
            ]);

            return $body;
        }

        self::walk($body, $refs, $locale);

        return $body;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array{surah: int, ayah: int, end: ?int}|null>  $refs
     */
    private static function walk(array &$node, array $refs, Locale $locale): void
    {
        if (($node['type'] ?? null) === 'evidence' && ($node['kind'] ?? null) === 'ayah') {
            self::annotate($node, $refs, $locale);

            return;
        }

        foreach ($node as &$child) {
            if (is_array($child)) {
                self::walk($child, $refs, $locale);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, array{surah: int, ayah: int, end: ?int}|null>  $refs
     */
    private static function annotate(array &$block, array $refs, Locale $locale): void
    {
        $key = self::key((string) ($block['text'] ?? ''));
        $ref = $refs[$key] ?? null;

        if ($ref === null) {
            /*
             * لفظُ الكتلة لا يُطابق موضعاً مثبَّتاً — أو يُطابق ملتبساً
             * (`null` في {@see references}). **وهذا حدٌّ مقصود** (حارسُ
             * T-73)، فيُقيَّد إشعاراً لا تحذيراً: الآيةُ تخرج عربيةً
             * سليمةً، والمفقودُ ترجمةٌ لا نسبة.
             */
            Log::info('ayah_translations.unmatched_block', [
                'locale' => $locale->value,
                'ambiguous' => array_key_exists($key, $refs),
            ]);

            return;
        }

        $last = $ref['end'] ?? $ref['ayah'];

        $citation = AyahCitation::for($ref['surah'], $ref['ayah'], $ref['end'], $locale);

        if ($citation !== null) {
            $block['source'] = $citation;
        }

        $rows = QuranTranslation::query()
            ->where('locale', $locale->value)
            ->where('surah', $ref['surah'])
            ->whereBetween('ayah', [$ref['ayah'], $last])
            ->orderBy('ayah')
            ->pluck('text')
            ->map(static fn (string $text): string => trim($text))
            ->filter(static fn (string $text): bool => $text !== '');

        // ★ **لا نصفَ ترجمة**: آيتان موصولتان ترجمةُ إحداهما وحدها تُقرأ
        // ترجمةً للاثنتين. فإمّا الترجمةُ كاملةً أو العربيُّ وحده.
        if ($rows->count() !== $last - $ref['ayah'] + 1) {
            // **وهذا نقصٌ في الجدول لا حدٌّ مقصود**، فيُقيَّد تحذيراً: بذرٌ
            // ناقصٌ يُخرج آيةً بلا ترجمةٍ في صفحةٍ منشورة (T-141، وصدرُ
            // {@see \App\Console\Commands\SeedQuranTranslations}).
            Log::warning('ayah_translations.incomplete_range', [
                'locale' => $locale->value,
                'surah' => $ref['surah'],
                'from' => $ref['ayah'],
                'to' => $last,
                'found' => $rows->count(),
            ]);

            return;
        }

        $block['translation'] = $rows->implode(' ');
        $block['partial'] = ! self::coversWhole($key, $ref['surah'], $ref['ayah'], $last);
    }

    /**
     * أيعرض المتنُ الآيةَ كلَّها أم بعضَها؟ — بمقارنة لفظه بالمصحف المبذور.
     *
     * **ولا يُقرأ `is_fragment`**: يصف ما قاله المتكلّم لا ما يُعرض، فتخرج
     * الآيةُ تامّةً في المتن وهو `true`.
     */
    private static function coversWhole(string $key, int $surah, int $first, int $last): bool
    {
        $whole = QuranAyah::query()
            ->where('surah', $surah)
            ->whereBetween('ayah', [$first, $last])
            ->orderBy('ayah')
            ->pluck('text_uthmani')
            ->implode(' ');

        return $whole !== '' && self::key($whole) === $key;
    }

    /**
     * مواضعُ الآيات المثبَّتة في هذه المهمّة، بمفتاح لفظها المطبَّع.
     *
     * **والملتبسُ `null`**: لفظٌ واحدٌ لآيتين مختلفتين (والمكرَّر في المصحف
     * كثير) لا يُخمَّن أيُّهما المقصود — نظيرُ حارس T-73.
     *
     * @return array<string, array{surah: int, ayah: int, end: ?int}|null>
     */
    private static function references(SummaryJob $job): array
    {
        $refs = [];

        $items = $job->evidenceItems()
            ->where('kind', 'ayah')
            ->whereNot('review_status', ReviewStatus::Removed->value)
            ->get();

        foreach ($items as $item) {
            /** @var EvidenceItem $item */
            $surah = $item->meta('surah_number');
            $ayah = $item->meta('ayah_number');
            $end = $item->meta('ayah_number_end');

            if (! is_numeric($surah) || ! is_numeric($ayah)) {
                continue;
            }

            $ref = ['surah' => (int) $surah, 'ayah' => (int) $ayah, 'end' => is_numeric($end) ? (int) $end : null];
            $key = self::key($item->matched_text ?? $item->raw_text);

            $refs[$key] = array_key_exists($key, $refs) && $refs[$key] !== $ref ? null : $ref;
        }

        return $refs;
    }

    /**
     * مفتاحُ المطابقة — **والقوسان وعلامةُ الآية ورقمُها ليست من اللفظ**:
     * زينةُ عرضٍ يضعها {@see AyahText} (T-56) وينزعها، ثمّ التطبيعُ الواحد.
     */
    private static function key(string $text): string
    {
        return Arabic::normalize(AyahText::plain($text));
    }
}
