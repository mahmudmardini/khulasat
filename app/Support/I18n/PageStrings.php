<?php

declare(strict_types=1);

namespace App\Support\I18n;

use App\Enums\HadithBook;
use App\Enums\Locale;

/**
 * The published page's own words — T-69.
 *
 * **إطارُ الصفحة كان عربياً في كلّ اللغات**: «مواضع الآيات والأحاديث»،
 * و«المحاضرة/ألقاها/المكان»، وزرّا «مشاهدة المحاضرة كاملة» و«صفحة الجامع»،
 * والتنويه، ورابط البلاغ. فيخرج المتنُ إنجليزياً في إطارٍ لا يقرؤه صاحبُه.
 *
 * ★ **والإطارُ ليس زينة**: قارئٌ لا يعرف العربية لا يعرف أنّ الزرّ يفتح
 * المحاضرة، ولا أنّ السطر تنويهٌ لا متن. **وقرارُ T-38 «يُترجَم ما حولها»
 * يشمله**، ولم يُنفَّذ إلّا في العنوان والعنوان الفرعي.
 *
 * ★★ **ولا نداءَ نموذجٍ لها.** هذه ثوابتُ واجهةٍ تُكتب مرّةً وتُراجَع، لا
 * محتوًى يُنتَج: ونموذجٌ يترجم «رواه البخاري» في كلّ ملخّصٍ **يصرف مالاً
 * ويُخرج صياغةً مختلفة في كلّ صفحة** — والتخريجُ اصطلاحٌ يثبت.
 *
 * وهي هنا لا في `lang/`: تلك للوحة، وهي عربيةٌ وحدها (CLAUDE.md §1)،
 * وهذه للصفحة المنشورة وهي بأربع لغات.
 */
final class PageStrings
{
    /** كلماتُ الإطار — مفتاحٌ لكلّ لغة. */
    private const CHROME = [
        'sources_title' => [
            'ar' => 'مواضع الآيات والأحاديث',
            'en' => 'Sources of the verses and hadiths',
            'tr' => 'Âyet ve hadis kaynakları',
            'ru' => 'Источники аятов и хадисов',
        ],
        /*
         * عنوانُ شريط اللغات — T-134. **بلسان الصفحة** لا بلسان الهدف:
         * من يقرأ الصفحة العربية يرى «بلغةٍ أخرى»، وأسماءُ اللغات بعده
         * بألسنتها. فالعنوانُ يُفهَم، والاسمُ يُعرَف.
         */
        'other_languages' => [
            'ar' => 'بلغةٍ أخرى',
            'en' => 'In another language',
            'tr' => 'Başka bir dilde',
            'ru' => 'На другом языке',
        ],
        'majlis_lecture' => ['ar' => 'المحاضرة', 'en' => 'Lecture', 'tr' => 'Ders', 'ru' => 'Лекция'],
        'majlis_speaker' => ['ar' => 'ألقاها', 'en' => 'Delivered by', 'tr' => 'Veren', 'ru' => 'Читал'],
        'majlis_date' => ['ar' => 'التاريخ', 'en' => 'Date', 'tr' => 'Tarih', 'ru' => 'Дата'],
        'majlis_venue' => ['ar' => 'المكان', 'en' => 'Venue', 'tr' => 'Yer', 'ru' => 'Место'],
        'watch_full' => [
            'ar' => 'مشاهدة المحاضرة كاملة',
            'en' => 'Watch the full lecture',
            'tr' => 'Dersin tamamını izle',
            'ru' => 'Смотреть лекцию полностью',
        ],
        'venue_page' => [
            'ar' => 'صفحة الجامع',
            'en' => 'The mosque’s page',
            'tr' => 'Cami sayfası',
            'ru' => 'Страница мечети',
        ],
        'complaint' => [
            'ar' => 'للتنبيه على خطأ في هذا الملخّص',
            'en' => 'Report an error in this summary',
            'tr' => 'Bu özetteki bir hatayı bildirin',
            'ru' => 'Сообщить об ошибке в этом резюме',
        ],
        /*
         * **التنويه لا يُحذف ولا يُختصر**: الملخّص ليس نصّاً حرفياً للمحاضرة،
         * وقولُ ذلك صراحةً شرطُ أمانةٍ لا زينة. **فيُقال بلسان قارئه.**
         */
        'disclaimer' => [
            'ar' => 'هذا الملخّص مأخوذٌ من المحاضرة، وليس نصًّا حرفيًّا لها. والرجوع إلى التسجيل أتمُّ وأولى.',
            'en' => 'This summary is drawn from the lecture and is not a verbatim transcript of it. Returning to the recording is fuller and better.',
            'tr' => 'Bu özet dersten alınmıştır ve dersin birebir metni değildir. Kaydın kendisine dönmek daha tam ve daha doğrudur.',
            'ru' => 'Это резюме составлено по лекции и не является её дословной записью. Обращение к записи полнее и предпочтительнее.',
        ],
        /** صيغةُ التخريج: «رواه فلان». */
        'narrated_by' => ['ar' => 'رواه', 'en' => 'Reported by', 'tr' => 'Rivayet eden:', 'ru' => 'Передал'],
        /** «رقم» في «رواه البخاري، رقم ٩٢٣». */
        'number' => ['ar' => 'رقم', 'en' => 'no.', 'tr' => 'no.', 'ru' => '№'],
        /*
         * الفاصلةُ بين الراوي والرقم — **وهي حرفٌ من اللغة لا رمزٌ محايد.**
         * كُتبت «،» ثابتةً فخرج «Reported by al-Bukhari، no. 6306» في
         * الصفحة الإنجليزية: فاصلةٌ عربية في سطرٍ لاتينيّ.
         */
        'comma' => ['ar' => '، ', 'en' => ', ', 'tr' => ', ', 'ru' => ', '],
        /*
         * نسبةُ ترجمات الآيات — **سطرٌ واحد في ذيل الصفحة** (T-89)، لا وسمٌ
         * فوق كلّ آية. و**«ترجمةُ معانٍ» لا «ترجمة»**: لفظُ القرآن لا يُنقل
         * إلى لسانٍ آخر، وما يُنقل معناه بفهم مترجمه. و`:name` اسمُه.
         */
        'ayah_translation_credit' => [
            'ar' => 'ترجمة معاني الآيات: :name',
            'en' => 'Translations of the meanings of the verses: :name',
            'tr' => 'Ayetlerin meali: :name',
            'ru' => 'Перевод смыслов аятов: :name',
        ],
        // والمتنُ يعرض بعضَ الآية: فالترجمةُ تُقال للآية كلّها، لا لما عُرض منها.
        'ayah_translation_whole' => [
            'ar' => 'الآية كاملة',
            'en' => 'Whole verse',
            'tr' => 'Ayetin tamamı',
            'ru' => 'Весь аят',
        ],
        /*
         * ★ **وسومُ الكتل وأزرارُ الصفحة** — T-87، بلاغُ مالك المنتج. كانت
         * عربيةً ثابتةً في `BodyBlocks` والقوالب، فخرجت «يُظنّ» و«مشاركة
         * الملخّص» في الصفحة الإنجليزية — علّةُ T-69 في مواضعَ لم تُفتَّش.
         */
        'gloss_linguistic' => ['ar' => 'لغةً', 'en' => 'Linguistically', 'tr' => 'Sözlükte', 'ru' => 'В языке'],
        'gloss_technical' => ['ar' => 'اصطلاحاً', 'en' => 'Technically', 'tr' => 'Terim olarak', 'ru' => 'В терминологии'],
        'mfix_claim' => ['ar' => 'يُظنّ', 'en' => 'Misconception', 'tr' => 'Yaygın kanı', 'ru' => 'Заблуждение'],
        'mfix_fix' => ['ar' => 'والصواب', 'en' => 'Correction', 'tr' => 'Doğrusu', 'ru' => 'Как верно'],
        /*
         * ★ **ولا لقبَ يُضاف بلا طلب** — T-102، قرار مالك المنتج. كانت
         * «محاضرةٌ للشيخ فلان»، فمن لم يكن شيخاً لُقّب به في صفحةٍ تُنشر
         * باسمه. واللقبُ يُكتب في اسم الملقي إن أراده صاحبُه.
         */
        'lecture_by' => ['ar' => 'ألقاها', 'en' => 'Delivered by', 'tr' => 'Dersi veren:', 'ru' => 'Читал'],
        'lecture_at' => ['ar' => 'في', 'en' => 'at', 'tr' => 'Yer:', 'ru' => 'Место:'],
        'lesson_kicker' => ['ar' => 'درسٌ ملخَّص', 'en' => 'Lesson summary', 'tr' => 'Ders özeti', 'ru' => 'Конспект урока'],
        'lesson_ayah' => ['ar' => 'آية الدرس', 'en' => 'The lesson’s verse', 'tr' => 'Dersin ayeti', 'ru' => 'Аят урока'],
        'share_summary' => ['ar' => 'مشاركة الملخّص', 'en' => 'Share the summary', 'tr' => 'Özeti paylaş', 'ru' => 'Поделиться резюме'],
        // زرُّ الاختبار — T-195. ويُرسم في العربية وحدها، والبقيةُ لتمام الجدول.
        'take_quiz' => ['ar' => 'اختبر فهمك', 'en' => 'Test your understanding', 'tr' => 'Anladığını sına', 'ru' => 'Проверьте себя'],
        'save_pdf' => ['ar' => 'حفظ بصيغة PDF', 'en' => 'Save as PDF', 'tr' => 'PDF olarak kaydet', 'ru' => 'Сохранить в PDF'],
        // نصُّ المشاركة — `:sheikh` و`:venue` اسمان يُملآن ولا يُترجمان.
        'share_text' => [
            'ar' => 'ملخّصٌ لمحاضرة :sheikh',
            'en' => 'A summary of a lecture by :sheikh',
            'tr' => ':sheikh dersinin özeti',
            'ru' => 'Резюме лекции: :sheikh',
        ],
        'share_text_venue' => ['ar' => ' في :venue', 'en' => ' at :venue', 'tr' => ' — :venue', 'ru' => ' — :venue'],
        'toast_copied' => [
            'ar' => 'نُسخ الرابط، يمكنك لصقه ومشاركته',
            'en' => 'Link copied — paste it anywhere to share',
            'tr' => 'Bağlantı kopyalandı; yapıştırıp paylaşabilirsiniz',
            'ru' => 'Ссылка скопирована — вставьте её, чтобы поделиться',
        ],
        'toast_copy_failed' => [
            'ar' => 'تعذّر النسخ، انسخ الرابط من شريط العنوان',
            'en' => 'Couldn’t copy — copy the link from the address bar',
            'tr' => 'Kopyalanamadı; bağlantıyı adres çubuğundan kopyalayın',
            'ru' => 'Не удалось скопировать — скопируйте ссылку из адресной строки',
        ],
        'toast_copy_manual' => [
            'ar' => 'انسخ الرابط من شريط العنوان أعلى المتصفّح',
            'en' => 'Copy the link from the address bar at the top of your browser',
            'tr' => 'Bağlantıyı tarayıcının üstündeki adres çubuğundan kopyalayın',
            'ru' => 'Скопируйте ссылку из адресной строки браузера',
        ],
        'print_tip_title' => [
            'ar' => 'لحفظ الملفّ بصيغة PDF بأفضل جودة',
            'en' => 'To save the PDF at its best quality',
            'tr' => 'PDF’i en iyi kalitede kaydetmek için',
            'ru' => 'Чтобы сохранить PDF в лучшем качестве',
        ],
        'print_tip_intro' => [
            'ar' => 'سيفتح متصفّحك نافذة الطباعة. اضبط فيها ثلاثة أمور ليخرج الملفّ بألوانه وتنسيقه كما تراه الآن:',
            'en' => 'Your browser will open its print dialog. Set three things so the file keeps its colours and layout exactly as you see them now:',
            'tr' => 'Tarayıcınız yazdırma penceresini açacak. Dosyanın renkleri ve düzeni şimdi gördüğünüz gibi çıksın diye üç ayarı yapın:',
            'ru' => 'Браузер откроет окно печати. Настройте три параметра, чтобы файл сохранил цвета и оформление, как сейчас:',
        ],
        'print_tip_destination' => ['ar' => 'الوجهة', 'en' => 'Destination', 'tr' => 'Hedef', 'ru' => 'Назначение'],
        'print_tip_background' => ['ar' => 'فعّل خيار', 'en' => 'Turn on', 'tr' => 'Şunu açın:', 'ru' => 'Включите'],
        'print_tip_background_label' => [
            'ar' => 'الرسومات الخلفية',
            'en' => 'Background graphics',
            'tr' => 'Arka plan grafikleri',
            'ru' => 'Фоновая графика',
        ],
        // اسمُ الخيار في المتصفّح الإنجليزيّ — يُذكر لمن متصفّحُه بغير لغة الصفحة.
        'print_tip_background_hint' => [
            'ar' => ' (Background graphics)',
            'en' => '',
            'tr' => ' (Background graphics)',
            'ru' => ' (Background graphics)',
        ],
        'print_tip_paper' => ['ar' => 'حجم الورق', 'en' => 'Paper size', 'tr' => 'Kağıt boyutu', 'ru' => 'Размер бумаги'],
        'print_tip_margins' => ['ar' => 'والهوامش', 'en' => 'margins', 'tr' => 'kenar boşlukları', 'ru' => 'поля'],
        'print_tip_default' => ['ar' => 'افتراضية', 'en' => 'Default', 'tr' => 'Varsayılan', 'ru' => 'По умолчанию'],
        'print_tip_go' => [
            'ar' => 'فتح نافذة الطباعة',
            'en' => 'Open the print dialog',
            'tr' => 'Yazdırma penceresini aç',
            'ru' => 'Открыть окно печати',
        ],
        'print_tip_back' => ['ar' => 'رجوع', 'en' => 'Back', 'tr' => 'Geri', 'ru' => 'Назад'],
        /*
         * سطرُ الاعتماد في آخر البصمة — الهوية البصرية الثانية §٠٥، T-98.
         * `:brand` موضعُ اسم المنصّة، ويختلف موضعُه باختلاف اللسان.
         * و«باستخدام» بهمزة وصل: مصدرُ «استخدم» الخماسيّ.
         */
        'made_with' => [
            'ar' => 'أُعدّت باستخدام :brand',
            'en' => 'Prepared with :brand',
            'tr' => ':brand ile hazırlandı',
            'ru' => 'Подготовлено с помощью :brand',
        ],
        // اسمُ المنصّة **بضمّته** كما يُرسم في الهوية — طلبُ مالك المنتج (T-99)،
        // وإن كانت الهوية §٠٣ تحذفها تحت ٣٢ بكسل.
        'platform_name' => ['ar' => 'خُلاصات', 'en' => 'Khulasat', 'tr' => 'Khulasat', 'ru' => 'Khulasat'],
        // ذيلُ بطاقة المشاركة — T-144. وعدُ الصفحة في سطر، بجانب الشعار.
        'share_card_note' => [
            'ar' => 'كلُّ شاهدٍ إلى مصدره',
            'en' => 'Every citation traced to its source',
            'tr' => 'Her delil kaynağına bağlı',
            'ru' => 'Каждый довод — к источнику',
        ],
    ];

    /** الدرجة — **وتُعرض مع الضعيف وجوباً** (§7-5)، فتُقال بلسانٍ يُفهم. */
    private const GRADES = [
        'sahih' => ['ar' => 'صحيح', 'en' => 'authentic', 'tr' => 'sahih', 'ru' => 'достоверный'],
        'hasan' => ['ar' => 'حسن', 'en' => 'good', 'tr' => 'hasen', 'ru' => 'хороший'],
        'daif' => ['ar' => 'ضعيف', 'en' => 'weak', 'tr' => 'zayıf', 'ru' => 'слабый'],
        'mawdu' => ['ar' => 'موضوع', 'en' => 'fabricated', 'tr' => 'mevzû', 'ru' => 'вымышленный'],
    ];

    /** أسماءُ الرواة كما تُنطق في كلّ لسان — والقائمةُ سبعةٌ مغلقة. */
    private const NARRATORS = [
        'bukhari' => ['ar' => 'البخاري', 'en' => 'al-Bukhari', 'tr' => 'Buhârî', 'ru' => 'аль-Бухари'],
        'muslim' => ['ar' => 'مسلم', 'en' => 'Muslim', 'tr' => 'Müslim', 'ru' => 'Муслим'],
        'abudawud' => ['ar' => 'أبو داود', 'en' => 'Abu Dawud', 'tr' => 'Ebû Dâvûd', 'ru' => 'Абу Дауд'],
        'tirmidhi' => ['ar' => 'الترمذي', 'en' => 'al-Tirmidhi', 'tr' => 'Tirmizî', 'ru' => 'ат-Тирмизи'],
        'nasai' => ['ar' => 'النسائي', 'en' => 'al-Nasa’i', 'tr' => 'Nesâî', 'ru' => 'ан-Насаи'],
        'ibnmajah' => ['ar' => 'ابن ماجه', 'en' => 'Ibn Majah', 'tr' => 'İbn Mâce', 'ru' => 'Ибн Маджа'],
        'malik' => ['ar' => 'مالك', 'en' => 'Malik', 'tr' => 'Mâlik', 'ru' => 'Малик'],
        'ahmad' => ['ar' => 'أحمد', 'en' => 'Ahmad', 'tr' => 'Ahmed b. Hanbel', 'ru' => 'Ахмад'],
        'darimi' => ['ar' => 'الدارمي', 'en' => 'al-Darimi', 'tr' => 'Dârimî', 'ru' => 'ад-Дарими'],
    ];

    private function __construct() {}

    public static function of(string $key, Locale $locale): string
    {
        return self::CHROME[$key][$locale->value] ?? self::CHROME[$key]['ar'] ?? '';
    }

    /**
     * كلماتُ الإطار كلُّها — تُمرَّر إلى القالب دفعةً واحدة.
     *
     * @return array<string, string>
     */
    public static function all(Locale $locale): array
    {
        $out = [];

        foreach (array_keys(self::CHROME) as $key) {
            $out[$key] = self::of($key, $locale);
        }

        return $out;
    }

    /** الدرجةُ بلسان الصفحة، أو `null` لدرجةٍ لا تُعرف. */
    public static function grade(?string $grade, Locale $locale): ?string
    {
        if ($grade === null || ! isset(self::GRADES[$grade])) {
            return null;
        }

        return self::GRADES[$grade][$locale->value] ?? self::GRADES[$grade]['ar'];
    }

    /**
     * اسمُ الراوي بلسان الصفحة — ويُستدلّ على كتابه من عنوانه العربيّ.
     *
     * **والعنوانُ في `source_meta` قائمةٌ مغلقة** يكتبها {@see HadithBook},
     * فالاستدلالُ منه حتميٌّ لا تخمين. وما لم يُعرف يُردّ `null` فيسقط
     * القارئُ إلى التخريج المحفوظ كما هو.
     */
    public static function narrator(?string $bookTitle, Locale $locale): ?string
    {
        if ($bookTitle === null || trim($bookTitle) === '') {
            return null;
        }

        foreach (HadithBook::cases() as $book) {
            if ($book->title() !== $bookTitle) {
                continue;
            }

            return self::NARRATORS[$book->value][$locale->value] ?? $book->narratedBy();
        }

        return null;
    }
}
