<?php

declare(strict_types=1);

use App\Actions\Stages\CondenseForCarousel;
use App\Contracts\ModelGateway;
use App\Domain\Summary\JobState;
use App\Enums\OutputType;
use App\Enums\SlideKind;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Model\ModelResponse;
use App\Support\Render\Slide;
use App\Support\Render\SlideDeck;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Support\Facades\DB;

/*
 * «لم تُقبل الشرائح» — T-217.
 *
 * حارسُ المرحلة ٧ (العدد وسقف الأربعين) فشلُ مخطّط، فيُعاد مرّةً واحدة كما
 * تعيد البوّابة على مخطّط JSON (§6). وعلاماتُ الترقيم ليست كلمات.
 *
 * والبوّابةُ هنا تردّ ما يُعدّ لها بالترتيب: لا ردودَ كاروسيل مسجَّلة في هذا
 * المستودع، والمقيسُ عددُ النداءات وما يُقبل منها، لا النموذج.
 */

/** بوّابةٌ تردّ ما يُعدّ لها بالترتيب، وتعدّ ما نُودي. */
final class ScriptedCarouselGateway implements ModelGateway
{
    /** @var list<array<string, mixed>|ModelCallFailed> */
    public array $replies = [];

    public int $calls = 0;

    public function call(Stage $stage, array $messages, ?array $schema = null, ?SummaryJob $job = null): ModelResponse
    {
        $this->calls++;

        $reply = array_shift($this->replies) ?? throw new RuntimeException('لا ردّ مُعَدّ.');

        if ($reply instanceof ModelCallFailed) {
            throw $reply;
        }

        return new ModelResponse($stage, 'fake', 'fake', (string) json_encode($reply, JSON_UNESCAPED_UNICODE), decoded: $reply);
    }
}

/** شرائحُ بنصٍّ حرٍّ محايد، و`$words` كلمةً في متن كلٍّ منها. */
function neutralSlides(int $count, int $words = 10): array
{
    $kinds = ['cover', ...array_fill(0, max(0, $count - 2), 'concept'), 'closing'];

    return ['slides' => array_map(
        static fn (string $kind, int $i): array => [
            'index' => $i + 1,
            'kind' => $kind,
            'heading' => 'عنوان',
            'body' => implode(' ', array_fill(0, $words, 'كلمة')),
            'source_line' => null,
        ],
        $kinds,
        array_keys($kinds),
    )];
}

/** ستُّ شرائح، الخامسةُ منها بخمسٍ وأربعين كلمة. */
function overlongSlides(): array
{
    $deck = neutralSlides(6);
    $deck['slides'][4]['body'] = implode(' ', array_fill(0, 45, 'كلمة'));

    return $deck;
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['plan' => 'business']);
    $lecture = Lecture::factory()->create(['tenant_id' => $this->tenant->id, 'title_ar' => 'عنوان الدرس']);

    $this->job = SummaryJob::factory()->for_($lecture)->inState(JobState::Published)->create([
        'structure_json' => ['title_ar' => 'عنوان الدرس', 'core_concept' => 'نصّ المفهوم.'],
        'body_html' => '<p class="lead">متن.</p>',
    ]);

    app()->instance(ModelGateway::class, $this->gateway = new ScriptedCarouselGateway);
});

it('يعيد التكثيف مرّةً إن خالف المخرَجُ قيدَي المرحلة، ويقبل الثانية', function (Closure $first): void {
    $this->gateway->replies = [$first(), neutralSlides(6)];

    $deck = app(CondenseForCarousel::class)->handle($this->job);

    expect($deck->count())->toBe(6)
        ->and($this->gateway->calls)->toBe(2);
})->with([
    'شريحةٌ فوق الأربعين' => [fn (): array => overlongSlides()],
    'خمسُ شرائح' => [fn (): array => neutralSlides(5)],
]);

it('يقف برسالة الحارس إن خالفت الثانيةُ أيضاً، ولا يُنادي ثالثة', function (): void {
    $this->gateway->replies = [overlongSlides(), overlongSlides(), neutralSlides(6)];

    try {
        app(CondenseForCarousel::class)->handle($this->job);
        $this->fail('كان يجب أن يقف بعد الإعادة.');
    } catch (ModelCallFailed $failed) {
        expect($failed->errorCode)->toBe('schema_validation_failed')
            ->and($failed->getMessage())->toBe('الشرائح (٥) تجاوز نصّها الحرّ 40 كلمة.')
            ->and($this->gateway->calls)->toBe(2);
    }
});

it('لا يعيد على إخفاق البوّابة نفسها، فتلك أعادت مرّتها', function (): void {
    $this->gateway->replies = [ModelCallFailed::schemaValidation('الخرج ليس JSON صالحاً.', Stage::Carousel), neutralSlides(6)];

    expect(fn () => app(CondenseForCarousel::class)->handle($this->job))->toThrow(ModelCallFailed::class);
    expect($this->gateway->calls)->toBe(1);
});

it('يبني الشرائح من الضغطة الأولى ولو تجاوزت شريحةٌ في النداء الأوّل', function (): void {
    $user = User::factory()->owner()->for_($this->tenant)->create();
    $this->gateway->replies = [overlongSlides(), neutralSlides(6)];

    $this->actingAs($user)->post("/panel/jobs/{$this->job->id}/carousel/build", ['recondense' => true])
        ->assertSessionHasNoErrors();

    $carousel = $this->job->outputs()->where('type', OutputType::Carousel->value)->first();

    expect($carousel?->meta['slides'] ?? [])->toHaveCount(6);
});

it('لا يعدّ علامات الترقيم كلمات، ويعدّ الأرقام', function (): void {
    // عنوانٌ بكلمة، ومتنٌ بتسعٍ وثلاثين ومعها علاماتٌ بين مسافتين: أربعون.
    $body = implode(' ', array_fill(0, 39, 'كلمة')).' — : … «» - .';
    $slide = new Slide(1, SlideKind::Concept, 'عنوان', $body);

    expect($slide->freeWordCount())->toBe(40)
        ->and((new SlideDeck([$slide]))->overlong())->toBe([])
        ->and((new Slide(2, SlideKind::Concept, 'عنوان', 'الآية ٩٧ والحديث 3'))->freeWordCount())->toBe(5);
});

it('يرفع سقف توكنز الكاروسيل من قيمة البذر، ولا يمسّ قيمةً غيّرها المشرف', function (int $before, int $after): void {
    DB::table('model_config')->where('stage', 'carousel')->delete();
    DB::table('model_config')->insert([...ModelConfigSeeder::row('carousel'), 'max_tokens' => $before, 'updated_at' => now()]);

    (require database_path('migrations/2026_10_06_100000_raise_carousel_max_tokens.php'))->up();

    expect((int) DB::table('model_config')->where('stage', 'carousel')->value('max_tokens'))->toBe($after)
        ->and(ModelConfigSeeder::row('carousel')['max_tokens'])->toBe(8_000);
})->with([
    'قيمةُ البذر' => [4_000, 8_000],
    'قيمةُ المشرف' => [6_000, 6_000],
]);
