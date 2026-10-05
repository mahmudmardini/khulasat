<?php

declare(strict_types=1);

namespace App\Support\Guide;

use App\Actions\Team\InviteMember;
use App\Enums\ComplaintKind;
use App\Enums\Locale;
use App\Enums\TranscriptErrorCode;
use App\Support\Arabic;
use App\Support\Quiz\QuizGuard;
use App\Support\Render\TenantCarouselDesigns;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * دليلُ الاستخدام — T-215. ثلاثةُ أدلّة بحسب الدور، باللغات الأربع.
 *
 * **والنصُّ في ملفّات Markdown لا في الكود**: `resources/guide/<لغة>/<فصل>.md`،
 * يكتبه ويراجعه من لا يقرأ PHP. وقائمةُ فصول كلّ دور في `chapters.php`،
 * فالفصلُ المشترك (الفهرس، والمراجعة، والنشر…) يُكتب مرّةً ويقرؤه دوران.
 *
 * ★ **ومعرّفاتُ العناوين صريحةٌ وواحدةٌ في اللغات الأربع** (`{#review}`):
 * فالرابطُ المنسوخ من العربية يفتح القسمَ نفسه بالإنجليزية، وتبديلُ اللغة
 * لا يُضيع موضعَ القارئ. ويحرس ذلك `GuideTest`.
 *
 * **والأرقامُ من الإعداد لا من النصّ** (`{{verify_per_hour}}`): كصفحة
 * الخصوصية (T-207)، فلا يَعِد الدليلُ بحدٍّ غيّره الكود.
 */
final class GuideBook
{
    /** بترتيب العرض في صفحة الاختيار: من يُعطى الدليلَ أكثرَ أوّلاً. */
    public const ROLES = ['owner', 'editor', 'admin'];

    /** صنوفُ التنبيه في النصّ: `> [!TIP]` كصيغة GitHub. */
    private const CALLOUTS = ['note', 'tip', 'warning', 'limit'];

    public function __construct(private readonly ?string $root = null) {}

    /**
     * فصولُ الدليل مرسومةً، وفهرسُها.
     *
     * @return list<array{id: string, number: int, title: string, html: string, topics: list<array{id: string, title: string}>}>
     */
    public function chapters(Locale $locale, string $role): array
    {
        $keys = $this->manifest()[$role] ?? [];

        return Cache::rememberForever(
            'guide:'.$locale->value.':'.$role.':'.$this->signature($locale, $keys),
            fn (): array => $this->unlinkMissing(array_values(array_map(
                fn (string $key, int $index): array => $this->chapter($locale, $role, $key, $index + 1),
                $keys,
                array_keys($keys),
            ))),
        );
    }

    /**
     * الفصلُ المشترك يُحيل إلى فصلٍ لا يقرؤه كلُّ دور — «هوية الجهة» في دليل
     * المحرّر مثلاً. فالإحالةُ إلى قسمٍ ليس في هذا الدليل تبقى نصّاً بلا
     * رابط، ولا تصير رابطاً لا يفتح شيئاً.
     *
     * @param  list<array{id: string, number: int, title: string, html: string, topics: list<array{id: string, title: string}>}>  $chapters
     * @return list<array{id: string, number: int, title: string, html: string, topics: list<array{id: string, title: string}>}>
     */
    private function unlinkMissing(array $chapters): array
    {
        $anchors = [];

        foreach ($chapters as $chapter) {
            $anchors[$chapter['id']] = true;

            foreach ($chapter['topics'] as $topic) {
                $anchors[$topic['id']] = true;
            }
        }

        return array_map(static function (array $chapter) use ($anchors): array {
            $chapter['html'] = (string) preg_replace_callback(
                '~<a href="#([^"]+)">(.*?)</a>~s',
                static fn (array $m): string => isset($anchors[$m[1]]) ? $m[0] : $m[2],
                $chapter['html'],
            );

            return $chapter;
        }, $chapters);
    }

    /**
     * ملفّ الفصل بلغته، وإلّا فبالعربية — اللغةُ الأصل لا تغيب.
     *
     * والسقوطُ حمايةٌ لا سياسة: الاختبار يشترط الفصول كلَّها باللغات كلِّها.
     */
    public function path(Locale $locale, string $chapter): string
    {
        $path = $this->root()."/{$locale->value}/{$chapter}.md";

        return is_file($path) ? $path : $this->root().'/'.Locale::source()->value."/{$chapter}.md";
    }

    /**
     * لقطةُ الشاشة بلغتها، وإلّا فبالعربية: لوحةُ المشرف وأداةُ «تحقّق»
     * عربيّتان في كلّ لغة، فلقطتُهما واحدة.
     */
    public function shot(Locale $locale, string $name): ?string
    {
        foreach ([$locale->value, Locale::source()->value] as $candidate) {
            $relative = "/images/guide/{$candidate}/{$name}.webp";

            if (is_file(public_path($relative))) {
                return $relative;
            }
        }

        return null;
    }

    /** @return array<string, list<string>> */
    public function manifest(): array
    {
        /** @var array<string, list<string>> */
        return require $this->root().'/chapters.php';
    }

    /**
     * الأرقامُ التي يذكرها الدليل، من مصادرها في الكود والإعداد.
     *
     * @return array<string, int>
     */
    public function figures(): array
    {
        return [
            'verify_per_hour' => (int) config('khulasah.verify.per_hour', 5),
            'verify_max_chars' => (int) config('khulasah.verify.max_chars', 4000),
            'verify_min_chars' => (int) config('khulasah.verify.min_chars', 20),
            'verify_retention_days' => (int) config('khulasah.verify.retention_days', 7),
            'upload_max_mb' => intdiv((int) config('khulasah.transcript.upload.max_bytes', 500 * 1024 * 1024), 1024 * 1024),
            'transcript_min_words' => TranscriptErrorCode::MINIMUM_WORDS,
            'upload_retention_days' => (int) config('khulasah.transcript.upload.retention_days', 3),
            'invitation_days' => InviteMember::VALID_DAYS,
            'takedown_hours' => ComplaintKind::Takedown->slaHours(),
            'evidence_complaint_days' => intdiv(ComplaintKind::Evidence->slaHours(), 24),
            'quiz_min_questions' => QuizGuard::MIN_QUESTIONS,
            'quiz_max_questions' => QuizGuard::MAX_QUESTIONS,
            'carousel_max_designs' => TenantCarouselDesigns::MAX_APPROVED,
            'logo_max_kb' => 500,
        ];
    }

    /**
     * @return array{id: string, number: int, title: string, html: string, topics: list<array{id: string, title: string}>}
     */
    private function chapter(Locale $locale, string $role, string $key, int $number): array
    {
        $markdown = $this->figuresIn($locale, $this->forRole((string) file_get_contents($this->path($locale, $key)), $role));

        $html = Str::markdown($markdown, [
            // النصُّ نصُّنا في المستودع، ولا HTML خامٌ فيه يُحتاج إليه.
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new AttributesExtension, new TableExtension]);

        $html = $this->callouts($locale, $html);
        $html = $this->screenshots($locale, $html);
        $html = preg_replace('~<table>(.*?)</table>~s', '<div class="guide-table"><table>$1</table></div>', $html) ?? $html;

        // عنوانُ الفصل `#` في الملفّ، وفي الصفحة `h2`: الصفحةُ لها `h1` واحد.
        $html = (string) preg_replace_callback(
            '~<(/?)h([1-5])\b~',
            static fn (array $m): string => '<'.$m[1].'h'.((int) $m[2] + 1),
            $html,
        );

        $title = '';
        $id = $key;
        $topics = [];

        $html = (string) preg_replace_callback(
            '~<h([23])((?:\s+[a-z-]+="[^"]*")*)>(.*?)</h\1>~s',
            function (array $m) use ($locale, &$title, &$id, &$topics): string {
                preg_match('~\bid="([^"]+)"~', $m[2], $found);
                $text = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5));
                $anchor = $found[1] ?? Str::slug($text);

                if ($m[1] === '2') {
                    $title = $text;
                    $id = $anchor;
                } else {
                    $topics[] = ['id' => $anchor, 'title' => $text];
                }

                return $this->heading((int) $m[1], $anchor, $m[3], $locale);
            },
            $html,
        );

        return ['id' => $id, 'number' => $number, 'title' => $title, 'html' => $html, 'topics' => $topics];
    }

    /**
     * العنوانُ ومعه زرُّ نسخ رابطه — يُرسم هنا لا في React: المتنُ HTML
     * جاهز، وزرٌّ واحد بسمةٍ يلتقطه مستمعٌ واحد في الصفحة.
     */
    private function heading(int $level, string $id, string $inner, Locale $locale): string
    {
        $label = e(trans('guide.copy_link', [], $locale->value));
        $icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>';

        return sprintf(
            '<h%1$d id="%2$s" class="guide-heading"><span class="guide-heading__text">%3$s</span><button type="button" class="guide-copy" data-anchor="%2$s" aria-label="%4$s" title="%4$s">%5$s</button></h%1$d>',
            $level,
            e($id),
            $inner,
            $label,
            $icon,
        );
    }

    /**
     * ما يخصّ دوراً دون آخر داخل فصلٍ مشترك:
     *
     *     ::: owner
     *     نصٌّ لمالك الجهة وحده.
     *     :::
     */
    private function forRole(string $markdown, string $role): string
    {
        return (string) preg_replace_callback(
            '~^:::[ \t]+([a-z ]+?)[ \t]*\R(.*?)^:::[ \t]*$\R?~ms',
            static fn (array $m): string => in_array($role, preg_split('~\s+~', trim($m[1])) ?: [], true) ? $m[2] : '',
            $markdown,
        );
    }

    private function figuresIn(Locale $locale, string $markdown): string
    {
        return (string) preg_replace_callback(
            '~\{\{\s*([a-z_]+)\s*\}\}~',
            function (array $m) use ($locale): string {
                $value = $this->figures()[$m[1]] ?? null;

                if ($value === null) {
                    return $m[0];
                }

                return $locale->isSource() ? Arabic::toArabicIndicDigits($value) : (string) $value;
            },
            $markdown,
        );
    }

    /**
     * `> [!TIP]` ← بطاقةُ تنبيهٍ بعنوانها في لغة الدليل.
     */
    private function callouts(Locale $locale, string $html): string
    {
        return (string) preg_replace_callback(
            '~<blockquote>\s*<p>\[!([A-Z]+)\]\s*(.*?)</blockquote>~s',
            static function (array $m) use ($locale): string {
                $kind = strtolower($m[1]);

                if (! in_array($kind, self::CALLOUTS, true)) {
                    return $m[0];
                }

                $label = e(trans("guide.callouts.{$kind}", [], $locale->value));

                return '<aside class="guide-callout" data-kind="'.$kind.'"><p class="guide-callout__label">'.$label.'</p><p>'.$m[2].'</aside>';
            },
            $html,
        );
    }

    /**
     * `![شرح](shot:create-source)` ← صورةٌ بإطارها وشرحِها تحتها.
     */
    private function screenshots(Locale $locale, string $html): string
    {
        return (string) preg_replace_callback(
            '~<p><img src="shot:([a-z0-9-]+)" alt="([^"]*)"(?: title="[^"]*")? /></p>~',
            function (array $m) use ($locale): string {
                $src = $this->shot($locale, $m[1]);

                if ($src === null) {
                    return '';
                }

                $size = @getimagesize(public_path($src)) ?: [0, 0];

                return sprintf(
                    '<figure class="guide-shot"><a href="%1$s" target="_blank" rel="noopener" class="guide-shot__frame"><img src="%1$s" alt="%2$s" width="%3$d" height="%4$d" loading="lazy" decoding="async"></a><figcaption>%2$s</figcaption></figure>',
                    e($src).'?v='.substr(md5((string) filemtime(public_path($src))), 0, 8),
                    $m[2],
                    $size[0],
                    $size[1],
                );
            },
            $html,
        );
    }

    /** @param list<string> $keys */
    private function signature(Locale $locale, array $keys): string
    {
        $parts = [json_encode($this->figures()), (string) @filemtime($this->root().'/chapters.php')];

        foreach ($keys as $key) {
            $path = $this->path($locale, $key);
            $parts[] = $path.':'.@filemtime($path).':'.@filesize($path);
        }

        foreach ([$locale->value, Locale::source()->value] as $folder) {
            $parts[] = (string) @filemtime(public_path("images/guide/{$folder}"));
        }

        $parts[] = (string) @filemtime(lang_path("{$locale->value}/guide.php"));

        return md5(implode('|', $parts));
    }

    private function root(): string
    {
        return $this->root ?? resource_path('guide');
    }
}
