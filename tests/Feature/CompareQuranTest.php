<?php

declare(strict_types=1);

use App\Models\QuranAyah;
use App\Support\Arabic;
use Illuminate\Support\Facades\Http;

/*
 * T-212 — مقارنةُ نصّ المصحف بنصّ مجمع الملك فهد من quranenc، **بلا شبكة**.
 * وما يُقارَن ما بعد التطبيع: ترميزُ الضبط يختلف بين المصدرين، والحروفُ لا.
 */
function ayahRow(int $sura, int $ayah, string $text): void
{
    QuranAyah::query()->create([
        'surah' => $sura,
        'ayah' => $ayah,
        'surah_name_ar' => 'الفاتحة',
        'text_uthmani' => $text,
        'text_imlaei' => $text,
        'text_normalized' => Arabic::normalize($text),
    ]);
}

beforeEach(function (): void {
    QuranAyah::query()->delete();
    Http::preventStrayRequests();
});

it('reports every ayah the same after normalization and exits cleanly', function (): void {
    ayahRow(1, 1, 'بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ');

    Http::fake(['quranenc.com/*' => Http::response(['result' => [
        ['sura' => '1', 'aya' => '1', 'arabic_text' => 'بِسۡمِ ٱللَّهِ ٱلرَّحۡمَٰنِ ٱلرَّحِيمِ'],
    ]])]);

    $this->artisan('khulasah:compare-quran', ['--sura' => [1]])
        ->expectsOutputToContain('متطابقة: 1 · مختلفة: 0')
        ->assertSuccessful();
});

it('prints both wordings of an ayah that differs, and fails', function (): void {
    ayahRow(1, 2, 'ٱلْحَمْدُ لِلَّهِ رَبِّ ٱلْعَـٰلَمِينَ');

    Http::fake(['quranenc.com/*' => Http::response(['result' => [
        ['sura' => '1', 'aya' => '2', 'arabic_text' => 'ٱلۡحَمۡدُ لِلَّهِ رَبِّ ٱلنَّاسِ'],
    ]])]);

    $this->artisan('khulasah:compare-quran', ['--sura' => [1]])
        ->expectsOutputToContain('مختلفة: 1')
        ->expectsOutputToContain('— 1:2')
        ->assertFailed();
});

it('writes nothing to the mushaf table', function (): void {
    ayahRow(1, 1, 'بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ');

    Http::fake(['quranenc.com/*' => Http::response(['result' => [
        ['sura' => '1', 'aya' => '1', 'arabic_text' => 'نصٌّ آخر'],
    ]])]);

    $this->artisan('khulasah:compare-quran', ['--sura' => [1]]);

    expect(QuranAyah::query()->where('surah', 1)->where('ayah', 1)->value('text_uthmani'))
        ->toBe('بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ');
});
