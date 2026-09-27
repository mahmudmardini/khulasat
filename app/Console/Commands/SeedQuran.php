<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\QuranContent;
use App\Models\QuranAyah;
use App\Support\Arabic;
use App\Support\Quran\QuranSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the local Qur'an — Hafs 'an 'Asim.
 *
 * النصّ يُجلب ويُحفظ محلّياً. المواصفة §10 من الدراسة: حجم المصحف كلّه
 * نحو ميغابايت، فلا مبرّر لجعل التحقّق نداءً شبكياً لحظياً يتعطّل بتعطّل
 * مزوّده أو يصطدم بحدود الاستعمال المجاني.
 *
 * ★ **ويُجلب من {@see QuranContent} بحساب مطوّر** — T-161. وشروطُ المصدر لا
 * تُجيز حفظه أكثر من أسبوع بلا مزامنة، فيُعاد أسبوعياً بـ`khulasah:sync-quran`
 * ويُقيَّد وقتُه في {@see QuranSync}.
 */
class SeedQuran extends Command
{
    protected $signature = 'khulasah:seed-quran
                            {--force : يعيد البذر ولو كان الجدول ممتلئاً}';

    protected $description = 'بذر المصحف برواية حفص عن عاصم — ويُعاد أسبوعياً بـ khulasah:sync-quran';

    private const EXPECTED_AYAT = 6236;

    public function handle(QuranContent $source): int
    {
        $existing = QuranAyah::count();

        if ($existing === self::EXPECTED_AYAT && ! $this->option('force')) {
            $this->info('المصحف مبذور بالفعل ('.self::EXPECTED_AYAT.' آية). استعمل --force لإعادة البذر.');

            return self::SUCCESS;
        }

        $this->info('جلب المصحف — رواية حفص عن عاصم…');

        $core = $source->core();
        $names = $core['chapters'];
        $uthmani = $core['verses'];
        $imlaei = $source->imlaei();

        if (count($uthmani) !== self::EXPECTED_AYAT || count($imlaei) !== self::EXPECTED_AYAT) {
            $this->error(sprintf(
                'عدد الآيات غير متوقّع: عثماني %d · إملائي %d · والمنتظر %d. لم يُكتب شيء.',
                count($uthmani), count($imlaei), self::EXPECTED_AYAT,
            ));

            return self::FAILURE;
        }

        // **والمفاتيح نفسُها في النصّين**: عثمانيٌّ بلا إملائيّه آيةٌ لا تُطابَق.
        if (array_diff_key($uthmani, $imlaei) !== []) {
            $this->error('آياتٌ في العثماني لا إملائيّ لها. لم يُكتب شيء.');

            return self::FAILURE;
        }

        $this->seed($uthmani, $imlaei, $names);
        QuranSync::record(QuranSync::CORE, count($uthmani));

        $count = QuranAyah::count();
        $this->newLine();
        $this->info("تمّ. {$count} آية في الجدول.");

        return $count === self::EXPECTED_AYAT ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, string>  $uthmani
     * @param  array<string, string>  $imlaei
     * @param  array<int, string>  $names
     */
    private function seed(array $uthmani, array $imlaei, array $names): void
    {
        $bar = $this->output->createProgressBar(self::EXPECTED_AYAT);
        $bar->start();

        // معاملة واحدة: إمّا مصحف كامل أو لا شيء. ونصفُ مصحفٍ في الجدول
        // يعني مطابقةً تُخفق صامتةً على ما لم يُبذَر.
        DB::transaction(function () use ($uthmani, $imlaei, $names, $bar): void {
            foreach (array_chunk($uthmani, 500, true) as $chunk) {
                $rows = [];

                foreach ($chunk as $key => $textUthmani) {
                    [$surah, $ayah] = array_map(intval(...), explode(':', $key));
                    $textImlaei = $imlaei[$key];

                    $rows[] = [
                        'surah' => $surah,
                        'ayah' => $ayah,
                        'surah_name_ar' => $names[$surah] ?? '',
                        'text_uthmani' => $textUthmani,
                        'text_imlaei' => $textImlaei,
                        // المطابقة على الإملائي — T-03ب.
                        'text_normalized' => Arabic::normalize($textImlaei),
                    ];
                }

                QuranAyah::query()->upsert(
                    $rows,
                    ['surah', 'ayah'],
                    ['surah_name_ar', 'text_uthmani', 'text_imlaei', 'text_normalized'],
                );

                $bar->advance(count($rows));
            }
        });

        $bar->finish();
    }
}
