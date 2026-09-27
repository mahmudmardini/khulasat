<?php

declare(strict_types=1);

namespace App\Services\Brand;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * تنقية الشعار المرفوع — المواصفة §12، المخطر الثاني.
 *
 * **ملفّ SVG يحمل JavaScript ويُنفَّذ عند العرض.** وهو ليس احتمالاً نظرياً:
 * SVG وثيقةُ XML تُنفَّذ في سياق الصفحة التي تعرضها، فشعارٌ مرفوعٌ من جهةٍ
 * يسرق جلسة مديرِ محتوى جهةٍ أخرى إن عُرض في اللوحة بلا تنقية.
 *
 * وثلاثة حرّاس، لا واحد:
 *   ١. **النوع بالمحتوى لا بالامتداد.** `logo.png` قد يكون SVG، و`.svg`
 *      قد يكون HTML. والامتداد يكتبه الرافع.
 *   ٢. **التنقية قبل الحفظ لا قبل العرض.** الملفّ الملوّث في التخزين
 *      يخرج يوماً من طريقٍ لم يُنقَّ.
 *   ٣. **والخدمة من نطاقٍ منفصل** — §12. فحتى لو نفَذ شيء، نفَذ في أصلٍ
 *      لا يملك جلسة أحد.
 */
class LogoSanitizer
{
    private const MAX_BYTES = 512_000;

    private const ALLOWED = ['image/svg+xml', 'image/png'];

    /**
     * @return array{mime: string, contents: string}
     *
     * @throws RuntimeException برسالة عربية من `lang/ar/errors.php`.
     */
    public function sanitize(UploadedFile $file): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException(__('errors.brand.logo_too_large'));
        }

        $contents = (string) file_get_contents($file->getRealPath());

        $mime = $this->detect($contents);

        if (! in_array($mime, self::ALLOWED, true)) {
            throw new RuntimeException(__('errors.brand.logo_type'));
        }

        return [
            'mime' => $mime,
            'contents' => $mime === 'image/svg+xml' ? $this->scrubSvg($contents) : $contents,
        ];
    }

    /**
     * النوع من البايتات الأولى ومن بنية الوثيقة — **لا من الامتداد**.
     *
     * وترتيب الفحص مقصود: توقيع PNG قاطعٌ ويُفحص أوّلاً، وأمّا SVG فنصٌّ
     * يُستدلّ عليه، فلا يُسأل عنه إلّا بعد استبعاد الثنائي.
     */
    private function detect(string $contents): string
    {
        if (str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        $head = ltrim(substr($contents, 0, 1024));

        if (stripos($head, '<svg') !== false || stripos($head, '<?xml') === 0) {
            return stripos($contents, '<svg') !== false ? 'image/svg+xml' : 'application/xml';
        }

        return 'application/octet-stream';
    }

    /**
     * تنقية SVG.
     *
     * والمكتبة تُسقط `<script>` و`on*=` وسائر ما يُنفَّذ. **ويُضاف عليها
     * منعُ `<foreignObject>` صراحةً** — المواصفة §12 تذكره بعينه، وهو باب
     * إدخال HTML داخل SVG.
     */
    private function scrubSvg(string $svg): string
    {
        $sanitizer = new Sanitizer;

        // المكتبة تُبقي المستندات المرجعية الخارجية اختيارياً — وتُمنع هنا:
        // شعارٌ يطلب موردَ خارجيّ يُسرّب زيارة كلّ من فتح الصفحة.
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);

        $clean = $sanitizer->sanitize($svg);

        if ($clean === false || trim($clean) === '') {
            throw new RuntimeException(__('errors.brand.logo_unsafe'));
        }

        // حارسٌ ثانٍ بعد المكتبة. وهي كافيةٌ اليوم، وقد تتبدّل بترقية —
        // وهذه الأسماء بعينها تذكرها المواصفة §12 فلا تُترك لتنفيذٍ خارجي.
        foreach (['<script', '<foreignobject', 'javascript:', '<iframe', '<use'] as $forbidden) {
            if (stripos($clean, $forbidden) !== false) {
                throw new RuntimeException(__('errors.brand.logo_unsafe'));
            }
        }

        if (preg_match('/\son[a-z]+\s*=/i', $clean) === 1) {
            throw new RuntimeException(__('errors.brand.logo_unsafe'));
        }

        return $clean;
    }

    /**
     * الشعار كـ data URI للتضمين في الصفحة — §8: «ملفّ واحد قائم بذاته».
     *
     * @param  array{mime: string, contents: string}  $logo
     */
    public function toDataUri(array $logo): string
    {
        return 'data:'.$logo['mime'].';base64,'.base64_encode($logo['contents']);
    }
}
