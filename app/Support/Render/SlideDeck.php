<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\SlideKind;
use App\Support\Arabic;
use App\Support\Quran\AyahText;

/**
 * شرائح الكاروسيل مجتمعةً — المواصفة §8-أ، والمهمّة T-19.
 *
 * **وهي أصلٌ يُحفظ لا نتيجةٌ تُحسب كلّ مرّة**: تُقيَّد في `outputs.meta`،
 * فإعادة رسم الكاروسيل تقرؤها ولا تستدعي نموذجاً ثانيةً. وذلك نصّ §8-أ:
 * «إعادة العرض لا تُعيد تشغيل الخطّ ولا تُحتسب من حصّة إعادة التوليد».
 */
final readonly class SlideDeck
{
    /** حدّا العدد — المرحلة ٧: «اصنع من 6 إلى 10 شرائح». */
    public const MIN = 6;

    public const MAX = 10;

    /** سقف النصّ الحرّ في الشريحة — المرحلة ٧ والمواصفة §8-أ. */
    public const MAX_WORDS = 40;

    /** @param  list<Slide>  $slides */
    public function __construct(public array $slides) {}

    public function count(): int
    {
        return count($this->slides);
    }

    /**
     * يبني الشرائح من مخرَج المرحلة ٧ كما وصل.
     *
     * وصنفٌ لا يُعرف يسقط ولا يُخمَّن: `kind` عقدٌ مغلق في التعليمات، وقيمةٌ
     * خارجه تعني أنّ النموذج خرج عن الشكل — فرسمُها بصنفٍ افتراضيّ يُخفي
     * الخروج ويُخرج شريحةً بتنسيقٍ ليس لها.
     *
     * @param  array<string, mixed>  $decoded
     */
    public static function fromModel(array $decoded): self
    {
        $slides = [];
        $position = 0;

        /** @var list<array<string, mixed>> $rows */
        $rows = is_array($decoded['slides'] ?? null) ? $decoded['slides'] : [];

        foreach ($rows as $row) {
            $kind = SlideKind::tryFrom((string) ($row['kind'] ?? ''));

            if ($kind === null) {
                continue;
            }

            $source = trim((string) ($row['source_line'] ?? ''));

            $slides[] = new Slide(
                index: ++$position,
                kind: $kind,
                heading: trim((string) ($row['heading'] ?? '')),
                body: trim((string) ($row['body'] ?? '')),
                sourceLine: $source === '' ? null : $source,
            );
        }

        return new self($slides);
    }

    /** يُعيد بناء الشرائح من `outputs.meta` — بلا نداء نموذج. */
    public static function fromArray(mixed $rows): self
    {
        $slides = [];
        $position = 0;

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $kind = SlideKind::tryFrom((string) ($row['kind'] ?? ''));

            if ($kind === null) {
                continue;
            }

            $source = $row['source_line'] ?? null;

            $slides[] = new Slide(
                index: ++$position,
                kind: $kind,
                heading: (string) ($row['heading'] ?? ''),
                body: (string) ($row['body'] ?? ''),
                sourceLine: is_string($source) && $source !== '' ? $source : null,
                anchored: (bool) ($row['anchored'] ?? false),
            );
        }

        return new self($slides);
    }

    /**
     * ★ **يثبّت كلّ لفظ مصدريّ على الشاهد المتحقَّق منه.**
     *
     * وهذا هو الحارس الذي يجعل القيدَ بنيةً لا رجاءً. فالتعليمات تقول للنموذج
     * «تُنقل بلفظ مصدرها حرفاً بحرف، ولا تختصرها»، **والتعليمات وحدها لا
     * تكفي**: نموذجٌ ضاقت عليه الشريحة يختصر الحديث ويضع نقاطاً، فيخرج على
     * إنستغرام حديثٌ مبتور منسوبٌ إلى البخاري. ولذلك لا يُصدَّق ما كتبه:
     * يُطابَق على `ContentObject` **مطابقةً نصّية حتمية بلا نموذج**
     * (CLAUDE.md §2 القاعدة الثالثة)، ثمّ **يُستبدل به لفظُ المصدر كاملاً**.
     *
     * فالاختصار يُردّ، والتحريف يُردّ، ويُفرد الطويلُ بشريحته لأنّ متنها
     * صار لفظَ المصدر وحده.
     *
     * **وما لم يُطابق شيئاً يسقط.** وهي سُنّة {@see PageRenderer::heroAyah()}
     * نفسها: «شاهدٌ لم يبلغ التحقّق لا يُعرض بلفظ النموذج — فالغياب أسلم».
     */
    public function anchoredTo(ContentObject $content): self
    {
        $kept = [];
        $position = 0;

        foreach ($this->slides as $slide) {
            if (! $slide->kind->carriesSourceWording()) {
                $kept[] = $slide->withIndex(++$position);

                continue;
            }

            $match = $this->match($slide, $content->evidence);

            if ($match === null) {
                continue;
            }

            $kept[] = $slide
                ->withBody($match->text, $match->citation() ?: $slide->sourceLine, anchored: true)
                ->withIndex(++$position);
        }

        return new self($kept);
    }

    /** الشرائح التي تجاوز نصّها الحرّ سقف الأربعين كلمة. @return list<Slide> */
    public function overlong(): array
    {
        return array_values(array_filter(
            $this->slides,
            static fn (Slide $slide): bool => $slide->freeWordCount() > self::MAX_WORDS,
        ));
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (Slide $slide): array => $slide->toArray(), $this->slides);
    }

    /**
     * النصّ الذي يُنسخ يدوياً إلى إنستغرام — «نسخ الكلّ» في SCREENS.md §6.
     *
     * ورقمُ الآية فيه بلا «۝» — {@see AyahText::forPost()}، T-172.
     */
    public function toPlainText(): string
    {
        $blocks = array_map(static function (Slide $slide): string {
            return implode("\n", array_filter([
                $slide->label().'. '.$slide->heading,
                AyahText::forPost($slide->body),
                $slide->sourceLine,
            ], static fn (?string $line): bool => $line !== null && trim($line) !== ''));
        }, $this->slides);

        return implode("\n\n", $blocks);
    }

    /**
     * الشاهد المتحقَّق الذي تنقل عنه هذه الشريحة، إن وُجد.
     *
     * @param  list<RenderedEvidence>  $evidence
     */
    private function match(Slide $slide, array $evidence): ?RenderedEvidence
    {
        $needle = Arabic::normalize($slide->body);

        if ($needle === '') {
            return null;
        }

        // الآية تُطابَق على الآيات أوّلاً، فلا تُثبَّت آيةٌ على لفظ حديث
        // يشترك معها في ألفاظٍ عامّة.
        $ordered = $slide->kind === SlideKind::Ayah
            ? [...array_filter($evidence, static fn (RenderedEvidence $e): bool => $e->kind === 'ayah'), ...$evidence]
            : $evidence;

        foreach ($ordered as $item) {
            $hay = Arabic::normalize($item->text);

            if ($hay === '') {
                continue;
            }

            /*
             * **والاحتواء في الاتجاهين معاً.** فالمبتور جزءٌ من المصدر
             * (`str_contains($hay, $needle)`)، والمزيد عليه — كلمةُ تصديرٍ
             * مثل «قال ﷺ» — يحتوي المصدرَ. وكلاهما يُثبَّت على اللفظ الكامل.
             */
            if (str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $item;
            }

            if (self::overlap($needle, $hay) >= 0.8) {
                return $item;
            }
        }

        return null;
    }

    /**
     * نسبة الكلمات المشتركة إلى كلمات الأقصر منهما.
     *
     * **ومقياسُها الأقصر لا الأطول**: شريحةٌ نقلت نصف الحديث تشترك مع
     * المصدر في كلّ كلماتها، والقسمة على كلمات المصدر تُعطي ٠٫٥ فتُفلتها.
     * وهي بعينها الحالة التي بُني هذا الحارس لها.
     */
    private static function overlap(string $left, string $right): float
    {
        $a = array_unique(explode(' ', $left));
        $b = array_unique(explode(' ', $right));

        $smallest = min(count($a), count($b));

        if ($smallest === 0) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / $smallest;
    }
}
