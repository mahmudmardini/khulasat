<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Actions\Brand\GenerateCarouselDesigns;
use App\Http\Controllers\Settings\BrandController;
use App\Models\Tenant;
use App\Services\Render\CarouselRenderer;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * قوالبُ كاروسيل الجهة في `brand_kit` — T-173.
 *
 * - `carousel_designs`: **المعتمدة**، وأوّلُها افتراضيُّ الجهة. وهي وحدها
 *   ما يُبنى به الكاروسيل ({@see CarouselDesign::forTenant()}).
 * - `carousel_design_candidates`: **المرشّحة** من آخر توليد، لا تُستعمل حتى
 *   تُعتمد.
 * - `carousel_design_generation`: حالُ التوليد.
 *
 * وتشترك فيه «هوية الجهة» ولوحةُ المشرف، فلا تفترق القاعدة بين شاشتين.
 */
final class TenantCarouselDesigns
{
    /** حدٌّ يُبقي الاختيار عند البناء قائمةً لا مخزناً. */
    public const MAX_APPROVED = 6;

    private const APPROVED = 'carousel_designs';

    /** @return list<CarouselDesign> */
    public static function approved(Tenant $tenant): array
    {
        return array_values(array_filter(array_map(
            CarouselDesign::tryFrom(...),
            (array) (self::kit($tenant)[self::APPROVED] ?? []),
        )));
    }

    /** @return list<array{design: CarouselDesign, rationale: string}> */
    public static function candidates(Tenant $tenant): array
    {
        $rows = (array) (self::kit($tenant)[GenerateCarouselDesigns::CANDIDATES] ?? []);
        $kept = [];

        foreach ($rows as $row) {
            $design = CarouselDesign::tryFrom($row['design'] ?? null);

            if ($design !== null) {
                $kept[] = ['design' => $design, 'rationale' => (string) ($row['rationale'] ?? '')];
            }
        }

        return $kept;
    }

    /** @return array{state: string|null, error: string|null, at: string|null} */
    public static function status(Tenant $tenant): array
    {
        $status = (array) (self::kit($tenant)[GenerateCarouselDesigns::STATUS] ?? []);

        return [
            'state' => $status['state'] ?? null,
            'error' => $status['error'] ?? null,
            'at' => $status['at'] ?? null,
        ];
    }

    /** قالبٌ معتمدٌ أو مرشَّحٌ بمعرّفه، أو الافتراضيّ. */
    public static function find(Tenant $tenant, string $id): ?CarouselDesign
    {
        if ($id === CarouselDesign::DEFAULT_ID) {
            return CarouselDesign::default();
        }

        foreach ([...self::approved($tenant), ...array_column(self::candidates($tenant), 'design')] as $design) {
            if ($design->id === $id) {
                return $design;
            }
        }

        return null;
    }

    /** يعتمد مرشَّحاً: يُنقل إلى المعتمدة، ويخرج من المرشّحة. */
    public static function approve(Tenant $tenant, string $id): bool
    {
        $approved = self::approved($tenant);

        if (count($approved) >= self::MAX_APPROVED) {
            return false;
        }

        $kit = self::kit($tenant);
        $remaining = [];
        $found = null;

        foreach (self::candidates($tenant) as $candidate) {
            if ($candidate['design']->id === $id) {
                $found = $candidate['design'];

                continue;
            }

            $remaining[] = ['design' => $candidate['design']->toArray(), 'rationale' => $candidate['rationale']];
        }

        if ($found === null) {
            return false;
        }

        $kit[self::APPROVED] = array_map(
            static fn (CarouselDesign $design): array => $design->toArray(),
            [...$approved, $found],
        );
        $kit[GenerateCarouselDesigns::CANDIDATES] = $remaining;

        self::save($tenant, $kit);

        return true;
    }

    /** يحذف قالباً معتمداً أو مرشَّحاً. */
    public static function remove(Tenant $tenant, string $id): void
    {
        $kit = self::kit($tenant);

        $kit[self::APPROVED] = array_values(array_map(
            static fn (CarouselDesign $design): array => $design->toArray(),
            array_filter(self::approved($tenant), static fn (CarouselDesign $design): bool => $design->id !== $id),
        ));

        $kit[GenerateCarouselDesigns::CANDIDATES] = array_values(array_map(
            static fn (array $candidate): array => ['design' => $candidate['design']->toArray(), 'rationale' => $candidate['rationale']],
            array_filter(self::candidates($tenant), static fn (array $candidate): bool => $candidate['design']->id !== $id),
        ));

        self::save($tenant, $kit);
    }

    /** يجعل قالباً معتمداً أوّلَها، فهو افتراضيُّ الجهة. */
    public static function makeDefault(Tenant $tenant, string $id): void
    {
        $approved = self::approved($tenant);

        usort($approved, static fn (CarouselDesign $a, CarouselDesign $b): int => ($b->id === $id) <=> ($a->id === $id));

        $kit = self::kit($tenant);
        $kit[self::APPROVED] = array_map(static fn (CarouselDesign $design): array => $design->toArray(), $approved);

        self::save($tenant, $kit);
    }

    /**
     * ما تعرضه الشاشتان.
     *
     * @return array<string, mixed>
     */
    public static function forScreen(Tenant $tenant): array
    {
        $approved = self::approved($tenant);

        return [
            'approved' => array_map(static fn (CarouselDesign $design, int $index): array => [
                'id' => $design->id,
                'name' => $design->name,
                'is_default' => $index === 0,
            ], $approved, array_keys($approved)),
            'candidates' => array_map(static fn (array $candidate): array => [
                'id' => $candidate['design']->id,
                'name' => $candidate['design']->name,
                'rationale' => $candidate['rationale'],
            ], self::candidates($tenant)),
            'status' => self::status($tenant),
            'max_approved' => self::MAX_APPROVED,
        ];
    }

    /**
     * القالبُ على كاروسيل العيّنة، بهوية الجهة — للمعاينة في إطار.
     *
     * **ولا يكتب شيئاً**، ولا شاهدة عدّ: العيّنةُ ليست ملخّصاً.
     */
    public static function previewHtml(Tenant $tenant, CarouselDesign $design): string
    {
        return (new CarouselRenderer(app(ViewFactory::class), SampleCarousel::deck(), null, $design))
            ->preview(BrandController::sampleContent(), BrandKit::forTenant($tenant));
    }

    /** @return array<string, mixed> */
    private static function kit(Tenant $tenant): array
    {
        return (array) ($tenant->brand_kit ?? []);
    }

    /** @param  array<string, mixed>  $kit */
    private static function save(Tenant $tenant, array $kit): void
    {
        $tenant->forceFill(['brand_kit' => $kit])->save();
    }
}
