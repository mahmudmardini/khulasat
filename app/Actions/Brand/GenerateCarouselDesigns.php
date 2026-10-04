<?php

declare(strict_types=1);

namespace App\Actions\Brand;

use App\Actions\Stages\StageSchemas;
use App\Contracts\ModelGateway;
use App\Contracts\OverflowProbe;
use App\Contracts\ShareCardCapturer;
use App\Enums\Stage;
use App\Http\Controllers\Settings\BrandController;
use App\Models\Tenant;
use App\Services\Model\ModelCallRecorder;
use App\Services\Quota\SpendCap;
use App\Services\Render\CarouselRenderer;
use App\Support\Model\ImageAttachment;
use App\Support\Model\StagePrompt;
use App\Support\Render\BrandKit;
use App\Support\Render\CarouselDesign;
use App\Support\Render\Palette;
use App\Support\Render\SampleCarousel;
use App\Support\Verification\DomainPolicy;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * قوالبُ كاروسيل الجهة — T-173، المرحلة الثانية.
 *
 * نداءٌ واحد يقرأ هوية الجهة (اسمها ولوحتها وشعارها) ويُخرج ثلاثة قوالب من
 * كتالوجٍ مغلق. **وما يخرج مرشَّحٌ لا معتمَد**: يُحفظ في
 * `brand_kit.carousel_design_candidates`، والجهةُ تعتمد منه ما تشاء.
 *
 * ★ **والنموذجُ يختار ولا يكتب.** كلُّ قالبٍ يمرّ بـ{@see CarouselDesign::from()}
 * الصارمة، وقيمةٌ واحدةٌ خارج الكتالوج تُسقطه كلَّه. ومعرّفُه يضعه الخادم.
 * **ثمّ يُقاس فيضُه** على كاروسيل الإجهاد ({@see SampleCarousel::stress()}) بهوية
 * الجهة نفسها، وما فاض نصُّه عن شريحةٍ لا يُعرض: يُقصّ في الصورة صامتاً.
 *
 * والتعليماتُ تعليماتُ الجهة إن كتبها المشرف (`tenants.carousel_design_prompt`)،
 * وإلّا الافتراضية في `resources/prompts/shared/carousel_design.txt`.
 */
final class GenerateCarouselDesigns
{
    public const CANDIDATES = 'carousel_design_candidates';

    public const STATUS = 'carousel_design_generation';

    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly ModelCallRecorder $recorder,
        private readonly SpendCap $spendCap,
        private readonly ShareCardCapturer $capturer,
        private readonly OverflowProbe $probe,
        private readonly ViewFactory $views,
    ) {}

    /** التعليماتُ التي يُولَّد بها لهذه الجهة. */
    public static function prompt(Tenant $tenant): string
    {
        $own = trim((string) $tenant->carousel_design_prompt);

        return $own !== '' ? $own : StagePrompt::for(Stage::CarouselDesign, DomainPolicy::DEFAULT);
    }

    /** حالُ التوليد في هوية الجهة، لتراه الشاشتان: `generating` ثمّ `ready` أو `failed`. */
    public static function mark(Tenant $tenant, string $state, ?string $error = null): void
    {
        $kit = (array) ($tenant->brand_kit ?? []);
        $kit[self::STATUS] = ['state' => $state, 'error' => $error, 'at' => now()->toIso8601String()];

        $tenant->forceFill(['brand_kit' => $kit])->save();
    }

    /**
     * @return list<CarouselDesign> المرشَّحة، وقد حُفظت.
     *
     * @throws RuntimeException سببُه يُعرض كما هو.
     */
    public function handle(Tenant $tenant): array
    {
        // ★ **السقفُ قبل النداء** (§2، القاعدة الخامسة)، ولو جاء الطلبُ من
        // المشرف: السقفُ يوقف الإنفاق كلَّه، ولا يستثني باباً.
        if ($this->spendCap->isHalted() || $this->spendCap->breachedReason() !== null) {
            throw new RuntimeException(trans('common.carousel_designs.capped'));
        }

        $logo = $this->logo($tenant);

        $response = $this->gateway->call(
            Stage::CarouselDesign,
            $this->messages($tenant, $logo),
            StageSchemas::for(Stage::CarouselDesign),
        );

        $this->recorder->recordForTenant($response, $tenant);

        $candidates = $this->candidates((array) (($response->decoded ?? [])['designs'] ?? []), $tenant);

        if ($candidates === []) {
            throw new RuntimeException(trans('common.carousel_designs.none_valid'));
        }

        $kit = (array) ($tenant->fresh()?->brand_kit ?? $tenant->brand_kit ?? []);
        $kit[self::CANDIDATES] = array_map(
            static fn (array $candidate): array => [
                'design' => $candidate['design']->toArray(),
                'rationale' => $candidate['rationale'],
            ],
            $candidates,
        );
        $kit[self::STATUS] = ['state' => 'ready', 'error' => null, 'at' => now()->toIso8601String()];

        $tenant->forceFill(['brand_kit' => $kit])->save();

        return array_map(static fn (array $candidate): CarouselDesign => $candidate['design'], $candidates);
    }

    /**
     * ما يصحّ من الردّ، بمعرّفاتٍ يضعها الخادم وبلا تكرار.
     *
     * @param  array<mixed>  $rows
     * @return list<array{design: CarouselDesign, rationale: string}>
     */
    private function candidates(array $rows, Tenant $tenant): array
    {
        $kept = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $design = CarouselDesign::tryFrom([
                'id' => 'c-'.Str::lower(Str::random(8)),
                'name' => $row['name'] ?? null,
                ...array_intersect_key($row, CarouselDesign::CATALOG),
                'layouts' => $row['layouts'] ?? null,
            ]);

            if ($design === null) {
                continue;
            }

            // قالبان بالقيم نفسها قالبٌ واحد، ولو اختلف اسماهما.
            $signature = md5(serialize([$design->values, $design->layouts]));

            if (isset($seen[$signature]) || $this->overflows($design, $tenant)) {
                continue;
            }

            $seen[$signature] = true;
            $kept[] = ['design' => $design, 'rationale' => Str::limit(trim((string) ($row['rationale'] ?? '')), 300)];
        }

        return $kept;
    }

    /**
     * أيفيض نصُّ شريحةٍ بهذا القالب على كاروسيل الإجهاد؟
     *
     * **وتعذُّرُ القياس لا يُسقط قالباً** (لا متصفّح): قالبٌ يُعرض بلا قياس خيرٌ
     * من توليدٍ مدفوعٍ لا يُعرض منه شيء. وإنشاءُ الصور يقيس الشرائح الحقيقية
     * ثانيةً قبل التقاطها.
     */
    private function overflows(CarouselDesign $design, Tenant $tenant): bool
    {
        $html = (new CarouselRenderer($this->views, SampleCarousel::stress(), rtrim((string) config('app.url'), '/').'/s/lesson', $design))
            ->render(BrandController::sampleContent(), BrandKit::forTenant($tenant))
            ->contents;

        $over = $this->probe->overflowing($html);

        if ($over !== null && $over !== []) {
            Log::info('carousel_design_overflows', ['tenant_id' => $tenant->id, 'slides' => $over, 'design' => $design->toArray()]);

            return true;
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private function messages(Tenant $tenant, ?ImageAttachment $logo): array
    {
        $kit = (array) ($tenant->brand_kit ?? []);
        $palette = Palette::find($kit['palette'] ?? null);

        $brief = [
            'tenant' => [
                'name' => $tenant->name_ar,
                'name_full' => $tenant->name_ar_full,
                'name_latin' => $tenant->name_latin,
            ],
            'palette' => ['key' => $palette->key, 'name' => $palette->nameAr, 'colors' => $palette->vars],
            'logo' => $logo === null ? 'none' : 'attached',
            'catalog' => CarouselDesign::CATALOG,
            'layouts' => CarouselDesign::LAYOUTS,
        ];

        $user = [
            'role' => 'user',
            'content' => (string) json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ];

        if ($logo !== null) {
            $user['images'] = [['mime' => $logo->mime, 'base64' => $logo->base64]];
        }

        return [['role' => 'system', 'content' => self::prompt($tenant)], $user];
    }

    /**
     * الشعارُ صورةً يقرؤها النموذج.
     *
     * والـSVG لا يقرؤه نموذج، **ولا يُحوَّل بمكتبة صور**: ملفٌّ من خارجنا
     * تفسّره مكتبةٌ بلا عزل بابٌ معروف للثغرات. فيُرسم بالمتصفّح المعزول نفسه
     * الذي يلتقط الشرائح، ومن غيره يُولَّد بلا شعار.
     */
    private function logo(Tenant $tenant): ?ImageAttachment
    {
        $uri = (string) (((array) ($tenant->brand_kit ?? []))['logo_data_uri'] ?? '');

        if (preg_match('#^data:(image/[a-z+]+);base64,(.+)$#s', $uri, $match) !== 1) {
            return null;
        }

        try {
            if ($match[1] === 'image/png') {
                return ImageAttachment::fromContents((string) base64_decode($match[2], true));
            }

            $html = '<!DOCTYPE html><html><body style="margin:0;background:#fff;display:flex;align-items:center;'
                .'justify-content:center;width:640px;height:640px"><img src="'.e($uri).'" style="max-width:560px;'
                .'max-height:560px"></body></html>';

            $png = $this->capturer->capture($html, 640, 640);

            return $png === null || $png === '' ? null : ImageAttachment::fromContents($png);
        } catch (Throwable) {
            return null;
        }
    }
}
