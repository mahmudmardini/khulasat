<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\OutputType;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedOutput;

/**
 * عارضٌ يرسم من `ContentObject` — المواصفة §8-أ.
 *
 * **كل عارض مستقلّ تماماً**، وإضافةُ عارضٍ جديد لا تمسّ الخطّ ولا العارضات
 * الأخرى. والسبب أنّ المحتوى المتحقَّق منه غالٍ ويُنتَج مرّة، والمخرجات
 * رخيصة وتتعدّد. **ومن ربط المخرَج بعمودٍ واحد أعاد الهيكلة عند الثاني.**
 *
 * وقاعدتان لا تُتجاوزان (§8-أ):
 *   ١. **العارض يقرأ `ContentObject` ولا يعدّله.**
 *   ٢. إعادة العرض لا تُعيد تشغيل الخطّ ولا تُحتسب من حصّة إعادة التوليد.
 */
interface Renderer
{
    public function type(): OutputType;

    /** نسخة العارض، تُحفظ في `outputs.renderer_version` لتتبّع التغيّرات. */
    public function version(): string;

    public function render(ContentObject $content, BrandKit $brand): RenderedOutput;
}
