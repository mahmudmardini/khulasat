<?php

declare(strict_types=1);

namespace App\Support\Quran;

use App\Enums\Locale;

/**
 * Surah names as a non-Arabic reader knows them — T-80.
 *
 * ★★ **منقولةٌ من المصدر الذي نبذر منه المصحفَ وترجماتِه**
 * (`api.quran.com/api/v4/chapters?language=…`، ١١ أيلول ٢٠٢٦) — لا من نموذجٍ
 * ولا من ذاكرة. فاسمُ السورة في تخريجٍ منشورٍ باسم جهةٍ شرعية نصٌّ يُنسب،
 * واسمٌ مخمَّنٌ فيه خطأٌ لا تكشفه مطابقة.
 *
 * - `en` ← `name_simple`: الاسمُ العربيّ بحروفٍ لاتينية، كما يعرفه قارئ
 *   الترجمات الإنجليزية (Al-Baqarah).
 * - `tr` ← `translated_name` بالتركية: وهو الاسمُ نفسُه برسمٍ تركيّ (Bakara)،
 *   كما في مآل ديانت.
 * - `ru` ← `translated_name` بالروسية: **وهو معنى الاسم لا نطقُه** (Корова)،
 *   إذ لا يحمل المصدرُ رسماً كيريلياً للاسم العربي.
 *
 * والعربيةُ ليست هنا: اسمُها في `quran_ayat.surah_name_ar` المبذور.
 */
final class SurahNames
{
    /** @var array<int, array{en: string, tr: string, ru: string}> */
    private const NAMES = [
        1 => ['en' => 'Al-Fatihah', 'tr' => 'Fâtiha', 'ru' => 'Открывающая Коран'],
        2 => ['en' => 'Al-Baqarah', 'tr' => 'Bakara', 'ru' => 'Корова'],
        3 => ['en' => 'Ali \'Imran', 'tr' => 'Âl-i İmrân', 'ru' => 'Семейство Имрана'],
        4 => ['en' => 'An-Nisa', 'tr' => 'Nisâ', 'ru' => 'Женщины'],
        5 => ['en' => 'Al-Ma\'idah', 'tr' => 'Mâide', 'ru' => 'Трапеза'],
        6 => ['en' => 'Al-An\'am', 'tr' => 'En\'âm', 'ru' => 'Скот'],
        7 => ['en' => 'Al-A\'raf', 'tr' => 'A\'râf', 'ru' => 'Ограды'],
        8 => ['en' => 'Al-Anfal', 'tr' => 'Enfâl', 'ru' => 'Трофеи'],
        9 => ['en' => 'At-Tawbah', 'tr' => 'Tevbe', 'ru' => 'Покаяние'],
        10 => ['en' => 'Yunus', 'tr' => 'Yûnus', 'ru' => 'Йунус'],
        11 => ['en' => 'Hud', 'tr' => 'Hûd', 'ru' => 'Худ'],
        12 => ['en' => 'Yusuf', 'tr' => 'Yûsuf', 'ru' => 'Йусуф'],
        13 => ['en' => 'Ar-Ra\'d', 'tr' => 'Ra\'d', 'ru' => 'Гром'],
        14 => ['en' => 'Ibrahim', 'tr' => 'İbrâhîm', 'ru' => 'Ибрахим'],
        15 => ['en' => 'Al-Hijr', 'tr' => 'Hicr', 'ru' => 'Хиджр '],
        16 => ['en' => 'An-Nahl', 'tr' => 'Nahl', 'ru' => 'Пчелы'],
        17 => ['en' => 'Al-Isra', 'tr' => 'İsrâ', 'ru' => 'Ночной перенос'],
        18 => ['en' => 'Al-Kahf', 'tr' => 'Kehf', 'ru' => 'Пещера'],
        19 => ['en' => 'Maryam', 'tr' => 'Meryem', 'ru' => 'Марьям'],
        20 => ['en' => 'Taha', 'tr' => 'Tâhâ', 'ru' => 'Та Ха '],
        21 => ['en' => 'Al-Anbya', 'tr' => 'Enbiyâ', 'ru' => 'Пророки'],
        22 => ['en' => 'Al-Hajj', 'tr' => 'Hac', 'ru' => 'Паломничество'],
        23 => ['en' => 'Al-Mu\'minun', 'tr' => 'Mü\'minûn', 'ru' => 'Верующие'],
        24 => ['en' => 'An-Nur', 'tr' => 'Nûr', 'ru' => 'Свет'],
        25 => ['en' => 'Al-Furqan', 'tr' => 'Furkân', 'ru' => 'аль-Фуркан'],
        26 => ['en' => 'Ash-Shu\'ara', 'tr' => 'Şuarâ', 'ru' => 'Поэты'],
        27 => ['en' => 'An-Naml', 'tr' => 'Neml', 'ru' => 'Муравьи'],
        28 => ['en' => 'Al-Qasas', 'tr' => 'Kasas', 'ru' => 'Рассказ'],
        29 => ['en' => 'Al-\'Ankabut', 'tr' => 'Ankebût', 'ru' => 'Паук'],
        30 => ['en' => 'Ar-Rum', 'tr' => 'Rûm', 'ru' => 'Римляне'],
        31 => ['en' => 'Luqman', 'tr' => 'Lokmân', 'ru' => 'Лукман'],
        32 => ['en' => 'As-Sajdah', 'tr' => 'Secde', 'ru' => 'Земной поклон'],
        33 => ['en' => 'Al-Ahzab', 'tr' => 'Ahzâb', 'ru' => 'Союзники'],
        34 => ['en' => 'Saba', 'tr' => 'Sebe\'', 'ru' => 'Сава'],
        35 => ['en' => 'Fatir', 'tr' => 'Fâtır', 'ru' => 'Творец'],
        36 => ['en' => 'Ya-Sin', 'tr' => 'Yâsîn', 'ru' => 'Йа Син'],
        37 => ['en' => 'As-Saffat', 'tr' => 'Sâffât', 'ru' => 'Выстроившиеся в ряды'],
        38 => ['en' => 'Sad', 'tr' => 'Sâd', 'ru' => 'Сод'],
        39 => ['en' => 'Az-Zumar', 'tr' => 'Zümer', 'ru' => 'Толпы'],
        40 => ['en' => 'Ghafir', 'tr' => 'Mü\'min', 'ru' => 'Прощающий'],
        41 => ['en' => 'Fussilat', 'tr' => 'Fussilet', 'ru' => 'Разъяснены'],
        42 => ['en' => 'Ash-Shuraa', 'tr' => 'Şûrâ', 'ru' => 'Совет'],
        43 => ['en' => 'Az-Zukhruf', 'tr' => 'Zuhruf', 'ru' => 'Украшения'],
        44 => ['en' => 'Ad-Dukhan', 'tr' => 'Duhân', 'ru' => 'Дым'],
        45 => ['en' => 'Al-Jathiyah', 'tr' => 'Câsiye', 'ru' => 'Коленопреклоненные'],
        46 => ['en' => 'Al-Ahqaf', 'tr' => 'Ahkâf', 'ru' => 'Барханы'],
        47 => ['en' => 'Muhammad', 'tr' => 'Muhammed', 'ru' => 'Мухаммад'],
        48 => ['en' => 'Al-Fath', 'tr' => 'Fetih', 'ru' => 'Победа'],
        49 => ['en' => 'Al-Hujurat', 'tr' => 'Hucurât', 'ru' => 'Комнаты'],
        50 => ['en' => 'Qaf', 'tr' => 'Kâf', 'ru' => 'Каф'],
        51 => ['en' => 'Adh-Dhariyat', 'tr' => 'Zâriyât', 'ru' => 'Рассеивающие прах'],
        52 => ['en' => 'At-Tur', 'tr' => 'Tûr', 'ru' => 'Гора'],
        53 => ['en' => 'An-Najm', 'tr' => 'Necm', 'ru' => 'Звезда'],
        54 => ['en' => 'Al-Qamar', 'tr' => 'Kamer', 'ru' => 'Месяц'],
        55 => ['en' => 'Ar-Rahman', 'tr' => 'Rahmân', 'ru' => 'Милостивый'],
        56 => ['en' => 'Al-Waqi\'ah', 'tr' => 'Vâkıa', 'ru' => 'Событие'],
        57 => ['en' => 'Al-Hadid', 'tr' => 'Hadîd', 'ru' => 'Железо'],
        58 => ['en' => 'Al-Mujadila', 'tr' => 'Mücâdele', 'ru' => 'Препирающаяся'],
        59 => ['en' => 'Al-Hashr', 'tr' => 'Haşr', 'ru' => 'Сбор'],
        60 => ['en' => 'Al-Mumtahanah', 'tr' => 'Mümtehine', 'ru' => 'Испытуемая'],
        61 => ['en' => 'As-Saf', 'tr' => 'Saf', 'ru' => 'Ряды'],
        62 => ['en' => 'Al-Jumu\'ah', 'tr' => 'Cuma', 'ru' => 'Собрание'],
        63 => ['en' => 'Al-Munafiqun', 'tr' => 'Münâfikûn', 'ru' => 'Лицемеры'],
        64 => ['en' => 'At-Taghabun', 'tr' => 'Tegâbün', 'ru' => 'Взаимное обделение'],
        65 => ['en' => 'At-Talaq', 'tr' => 'Talâk', 'ru' => 'Развод'],
        66 => ['en' => 'At-Tahrim', 'tr' => 'Tahrîm', 'ru' => 'Запрещение'],
        67 => ['en' => 'Al-Mulk', 'tr' => 'Mülk', 'ru' => 'Власть'],
        68 => ['en' => 'Al-Qalam', 'tr' => 'Kalem', 'ru' => 'Письменная трость'],
        69 => ['en' => 'Al-Haqqah', 'tr' => 'Hâkka', 'ru' => 'Неминуемое'],
        70 => ['en' => 'Al-Ma\'arij', 'tr' => 'Meâric', 'ru' => 'Ступени'],
        71 => ['en' => 'Nuh', 'tr' => 'Nûh', 'ru' => 'Нух'],
        72 => ['en' => 'Al-Jinn', 'tr' => 'Cin', 'ru' => 'Джинны'],
        73 => ['en' => 'Al-Muzzammil', 'tr' => 'Müzzemmil', 'ru' => 'Закутавшийся'],
        74 => ['en' => 'Al-Muddaththir', 'tr' => 'Müddessir', 'ru' => 'Завернувшийся'],
        75 => ['en' => 'Al-Qiyamah', 'tr' => 'Kıyâmet', 'ru' => 'Воскресение'],
        76 => ['en' => 'Al-Insan', 'tr' => 'İnsân', 'ru' => 'Человек'],
        77 => ['en' => 'Al-Mursalat', 'tr' => 'Mürselât', 'ru' => 'Посылаемые'],
        78 => ['en' => 'An-Naba', 'tr' => 'Nebe', 'ru' => 'Весть'],
        79 => ['en' => 'An-Nazi\'at', 'tr' => 'Naziât', 'ru' => 'Исторгающие'],
        80 => ['en' => '\'Abasa', 'tr' => 'Abese', 'ru' => 'Нахмурился'],
        81 => ['en' => 'At-Takwir', 'tr' => 'Tekvîr', 'ru' => 'Скручивание'],
        82 => ['en' => 'Al-Infitar', 'tr' => 'İnfitâr', 'ru' => 'Раскалывание'],
        83 => ['en' => 'Al-Mutaffifin', 'tr' => 'Mutaffifîn', 'ru' => 'Обвешивающие'],
        84 => ['en' => 'Al-Inshiqaq', 'tr' => 'İnşikâk', 'ru' => 'Разверзнется'],
        85 => ['en' => 'Al-Buruj', 'tr' => 'Burûc', 'ru' => 'Созвездия Зодиака'],
        86 => ['en' => 'At-Tariq', 'tr' => 'Târık', 'ru' => 'Ночной путник'],
        87 => ['en' => 'Al-A\'la', 'tr' => 'A\'lâ', 'ru' => 'Всевышний'],
        88 => ['en' => 'Al-Ghashiyah', 'tr' => 'Gâşiye', 'ru' => 'Покрывающее'],
        89 => ['en' => 'Al-Fajr', 'tr' => 'Fecr', 'ru' => 'Заря'],
        90 => ['en' => 'Al-Balad', 'tr' => 'Beled', 'ru' => 'Город'],
        91 => ['en' => 'Ash-Shams', 'tr' => 'Şems', 'ru' => 'Солнце'],
        92 => ['en' => 'Al-Layl', 'tr' => 'Leyl', 'ru' => 'Ночь'],
        93 => ['en' => 'Ad-Duhaa', 'tr' => 'Duhâ', 'ru' => 'Утро'],
        94 => ['en' => 'Ash-Sharh', 'tr' => 'İnşirâh', 'ru' => 'Раскрытие'],
        95 => ['en' => 'At-Tin', 'tr' => 'Tîn', 'ru' => 'Смоковница'],
        96 => ['en' => 'Al-\'Alaq', 'tr' => 'Alak', 'ru' => 'Сгусток крови'],
        97 => ['en' => 'Al-Qadr', 'tr' => 'Kadir', 'ru' => 'Предопределение'],
        98 => ['en' => 'Al-Bayyinah', 'tr' => 'Beyyine', 'ru' => 'Ясное знамение'],
        99 => ['en' => 'Az-Zalzalah', 'tr' => 'Zilzâl', 'ru' => 'Сотрясение'],
        100 => ['en' => 'Al-\'Adiyat', 'tr' => 'Âdiyât', 'ru' => 'Скачущие'],
        101 => ['en' => 'Al-Qari\'ah', 'tr' => 'Kâria', 'ru' => 'Великое бедствие'],
        102 => ['en' => 'At-Takathur', 'tr' => 'Tekâsür', 'ru' => 'Страсть к приумножению'],
        103 => ['en' => 'Al-\'Asr', 'tr' => 'Asr', 'ru' => 'Предвечернее время'],
        104 => ['en' => 'Al-Humazah', 'tr' => 'Hümeze', 'ru' => 'Хулитель'],
        105 => ['en' => 'Al-Fil', 'tr' => 'Fîl', 'ru' => 'Слон'],
        106 => ['en' => 'Quraysh', 'tr' => 'Kureyş', 'ru' => 'Курейшиты'],
        107 => ['en' => 'Al-Ma\'un', 'tr' => 'Maûn', 'ru' => 'Мелочь'],
        108 => ['en' => 'Al-Kawthar', 'tr' => 'Kevser', 'ru' => 'Изобилие'],
        109 => ['en' => 'Al-Kafirun', 'tr' => 'Kâfirûn', 'ru' => 'Неверующие'],
        110 => ['en' => 'An-Nasr', 'tr' => 'Nasr', 'ru' => 'Помощь'],
        111 => ['en' => 'Al-Masad', 'tr' => 'Tebbet', 'ru' => 'Пальмовые волокна'],
        112 => ['en' => 'Al-Ikhlas', 'tr' => 'İhlâs', 'ru' => 'Очищение веры'],
        113 => ['en' => 'Al-Falaq', 'tr' => 'Felak', 'ru' => 'Рассвет'],
        114 => ['en' => 'An-Nas', 'tr' => 'Nâs', 'ru' => 'Люди'],
    ];

    private function __construct() {}

    /** اسمُ السورة بلسان الصفحة، أو `null` للعربية ولرقمٍ خارج المصحف. */
    public static function of(int $surah, Locale $locale): ?string
    {
        return self::NAMES[$surah][$locale->value] ?? null;
    }
}
