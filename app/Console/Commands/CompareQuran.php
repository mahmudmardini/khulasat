<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\QuranAyah;
use App\Support\Arabic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * مقارنةُ نصّ المصحف عندنا بنصّ مجمع الملك فهد — T-212، وثيقةُ المرجعية.
 *
 * يجلب `arabic_text` من quranenc (منصّة الجمعية، بلا مفتاح)، ويقارنه بجدول
 * `quran_ayat` آيةً آية **بالتطبيع نفسه الذي تُطابَق به الآيات**: فما يختلف
 * بعده يختلف في حروفه لا في ترميز علامات الضبط.
 *
 * **يقرأ ولا يكتب.** ويُشغَّل مرّةً على الخادم، وتُسجَّل نتيجته في SOURCES.md.
 */
class CompareQuran extends Command
{
    private const ENDPOINT = 'https://quranenc.com/api/v1/translation/sura/english_saheeh/%d';

    protected $signature = 'khulasah:compare-quran {--sura=* : سورٌ بعينها بدل المصحف كلّه}';

    protected $description = 'يقارن نصّ المصحف عندنا بنصّ مجمع الملك فهد من quranenc — للقراءة فقط (T-212)';

    public function handle(): int
    {
        $suras = array_map(intval(...), (array) $this->option('sura')) ?: range(1, 114);

        $same = 0;
        $differ = [];
        $missing = [];

        foreach ($suras as $sura) {
            try {
                $rows = Http::timeout(30)->retry(3, 1000)->get(sprintf(self::ENDPOINT, $sura))->throw()->json('result') ?? [];
            } catch (Throwable $e) {
                $this->error("تعذّر جلب السورة {$sura}: {$e->getMessage()}");

                return self::FAILURE;
            }

            $ours = QuranAyah::query()->where('surah', $sura)->pluck('text_uthmani', 'ayah');

            foreach ($rows as $row) {
                $ayah = (int) $row['aya'];
                $theirs = (string) $row['arabic_text'];

                if (! isset($ours[$ayah])) {
                    $missing[] = "{$sura}:{$ayah}";

                    continue;
                }

                if (Arabic::normalize($ours[$ayah]) === Arabic::normalize($theirs)) {
                    $same++;
                } else {
                    $differ[] = [$sura.':'.$ayah, $ours[$ayah], $theirs];
                }
            }
        }

        $this->info("متطابقة: {$same} · مختلفة: ".count($differ).' · غائبة عندنا: '.count($missing));

        foreach ($differ as [$key, $mine, $theirs]) {
            $this->line("— {$key}");
            $this->line("  عندنا: {$mine}");
            $this->line("  المجمع: {$theirs}");
        }

        if ($missing !== []) {
            $this->line('غائبة عندنا: '.implode('، ', $missing));
        }

        return $differ === [] && $missing === [] ? self::SUCCESS : self::FAILURE;
    }
}
