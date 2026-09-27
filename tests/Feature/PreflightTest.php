<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Stage;
use App\Models\Hadith;
use App\Models\ModelConfig;
use App\Models\QuranAyah;
use App\Models\QuranTranslation;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Support\Facades\Http;

// T-35. **ولا شبكة هنا**: الفحص نفسه يجب ألّا ينادي أحداً.

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake();
});

/**
 * كلّ ما يحتاجه الفحص ليمرّ، ما خلا ما يُخرّبه الاختبار عمداً.
 *
 * والمصحف يُكتفى منه بعتبة الفحص: بذرُ ستّة آلاف آية في كلّ اختبار
 * يقيس سرعة البذر لا صحّة الفحص.
 */
function readyDeployment(): void
{
    (new ModelConfigSeeder)->run();

    $ayat = [];

    for ($surah = 1; $surah <= 60; $surah++) {
        for ($ayah = 1; $ayah <= 100; $ayah++) {
            $ayat[] = [
                'surah' => $surah,
                'ayah' => $ayah,
                'surah_name_ar' => 'سورة '.$surah,
                'text_uthmani' => 'نصّ',
                'text_imlaei' => 'نصّ',
                'text_normalized' => 'نص',
            ];
        }
    }

    QuranAyah::query()->insert($ayat);

    // ترجماتٌ كاملةٌ لكلّ لغة — T-161: الناقصةُ حاجب.
    foreach (Locale::translatable() as $locale) {
        $rows = [];

        for ($i = 0; $i < 6_236; $i++) {
            $rows[] = [
                'surah' => intdiv($i, 100) + 1,
                'ayah' => $i % 100 + 1,
                'locale' => $locale->value,
                'translation_id' => (int) $locale->quranTranslationId(),
                'text' => 'Meaning',
            ];
        }

        foreach (array_chunk($rows, 1_000) as $chunk) {
            QuranTranslation::query()->insert($chunk);
        }
    }

    Hadith::query()->create([
        'book' => 'malik',
        'hadith_number' => '1',
        'text' => 'إنما الأعمال بالنيات',
        'text_plain' => 'إنما الأعمال بالنيات',
        'text_normalized' => 'انما الاعمال بالنيات',
        'grade' => 'sahih',
        'takhrij' => 'رواه مالك',
    ]);
}

it('يمرّ على إعدادٍ كامل، ولا ينادي أحداً', function (): void {
    readyDeployment();

    $this->artisan('khulasah:preflight')->assertSuccessful();

    // **وهذا أهمّ توكيدٍ في الملفّ**: فحصُ الجاهزية لو نادى نموذجاً واحداً
    // «ليتأكّد» لصار هو نفسه إنفاقاً، ولاحتاج إذناً — CLAUDE.md §0.
    Http::assertNothingSent();
});

it('يحجب حين تكون مرحلةٌ تُنادى بلا صفّ', function (): void {
    readyDeployment();

    // المرحلة تُنادى في T-09ب، وسقوطُها صامت: تُفتح الحقول يدوياً ولا
    // يعرف أحدٌ أنّ الميزة ميّتة. فالفحص هو ما يُظهرها.
    ModelConfig::query()->where('stage', Stage::LectureDetails->value)->delete();

    $this->artisan('khulasah:preflight')->assertFailed();
});

// سعرُ الصفر يُنفق ولا يُسجَّل، فلا يوقف الطابورَ سقفٌ ولا يُنبّه تنبيه.
it('يحجب على سعرٍ صفريّ لأنّ سقف الإنفاق لن يحرس', function (): void {
    readyDeployment();

    ModelConfig::query()->where('stage', Stage::Writing->value)
        ->update(['output_price_per_m' => 0.0]);

    $this->artisan('khulasah:preflight')->assertFailed();
});

// §6-أ: «البديل من مزوّد مختلف لا من العائلة نفسها».
it('يحجب على بديلٍ من المزوّد نفسه', function (): void {
    readyDeployment();

    // البديل نفسُ مزوّد الأصل — أيّاً كان المزوّد الافتراضي حالياً، لا
    // `anthropic` بعينها، فلا يُكسَر هذا الاختبار كلّما تبدّل الإعداد.
    $writing = ModelConfig::query()->where('stage', Stage::Writing->value)->firstOrFail();

    ModelConfig::query()->where('stage', Stage::Writing->value)
        ->update(['fallback_provider' => $writing->provider, 'fallback_model_id' => $writing->model_id]);

    $this->artisan('khulasah:preflight')->assertFailed();
});

it('يحجب على مزوّدٍ لا محوّل له', function (): void {
    readyDeployment();

    // `mistral` مثالٌ متعمَّد على مزوّدٍ بلا محوّل — بخلاف `google` الذي
    // صار له محوّلٌ في T-41 ولم يعد يصلح مثالاً لهذا الاختبار.
    ModelConfig::query()->where('stage', Stage::Cleaning->value)
        ->update(['provider' => 'mistral', 'model_id' => 'mistral-large']);

    $this->artisan('khulasah:preflight')->assertFailed();
});

// T-161: جدولٌ غير مبذور يُخرج آياتٍ بلا ترجمةٍ في صفحاتٍ منشورة.
it('يحجب حين تنقص ترجمةُ لغة', function (): void {
    readyDeployment();
    QuranTranslation::query()->where('locale', 'ru')->limit(10)->delete();

    $this->artisan('khulasah:preflight')->assertFailed();
});

it('يحجب حين لا يُبذَر المصحف', function (): void {
    readyDeployment();
    QuranAyah::query()->delete();

    $this->artisan('khulasah:preflight')->assertFailed();
});

// **المفتاح الفارغ تنبيهٌ في الوهمي وحاجبٌ في الحقيقي**: مزوّدٌ بلا مفتاح
// لا يُنادى ولا يصلح بديلاً.
it('يحجب على مفتاحٍ فارغ في الوضع الحقيقي وحده', function (): void {
    readyDeployment();
    config()->set('khulasah.model.anthropic.api_key', '');
    config()->set('khulasah.model.openai.api_key', '');

    $this->artisan('khulasah:preflight')->assertSuccessful();

    config()->set('khulasah.model.gateway', 'real');

    $this->artisan('khulasah:preflight')->assertFailed();
});

it('لا يطبع قيمة مفتاح قطّ', function (): void {
    readyDeployment();
    config()->set('khulasah.model.anthropic.api_key', 'sk-ant-سرٌّ-لا-يُطبع');

    $this->artisan('khulasah:preflight')->doesntExpectOutputToContain('sk-ant-سرٌّ-لا-يُطبع');
});
