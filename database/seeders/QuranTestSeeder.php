<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\QuranAyah;
use App\Support\Arabic;
use Illuminate\Database\Seeder;

/**
 * A minimal Qur'an slice for tests.
 *
 * الاختبارات لا تبذر 6236 آية ولا تمسّ الشبكة: تُقرأ آيات العيّنة من ملفّ
 * محلّي فتكون حتمية وسريعة. والبذر الكامل يُختبر يدوياً بالأمر نفسه.
 */
class QuranTestSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/quran/rasm-pairs.json')),
            true,
        );

        $names = [2 => 'البقرة', 3 => 'آل عمران', 16 => 'النحل', 18 => 'الكهف', 35 => 'فاطر', 49 => 'الحجرات', 68 => 'القلم', 70 => 'المعارج'];

        foreach ($data['verses'] as $key => $texts) {
            [$surah, $ayah] = array_map(intval(...), explode(':', (string) $key));

            QuranAyah::query()->updateOrCreate(
                ['surah' => $surah, 'ayah' => $ayah],
                [
                    'surah_name_ar' => $names[$surah] ?? '',
                    'text_uthmani' => $texts['uthmani'],
                    'text_imlaei' => $texts['imlaei'],
                    'text_normalized' => Arabic::normalize($texts['imlaei']),
                ],
            );
        }
    }
}
