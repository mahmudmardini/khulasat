<?php

declare(strict_types=1);

/*
 * Denetim kapısı — SCREENS.md ekran 5, şartname §7-5. T-133'te uyarlandı.
 *
 * Bunlar ürünün en hassas metinleridir: kurumun adıyla neyin yayımlanacağı
 * burada karara bağlanır. İfade **ne bulduğumuzu** söyler, ne hükmettiğimizi
 * değil — dürüst bir aletle kaynaklara söz uyduran bir alet arasındaki fark.
 */

return [

    'title' => 'Delilleri gözden geçir',

    'match' => [
        'exact' => 'Kaynakla birebir',
        'partial' => 'Kaynak lafzına yakın',
        'none' => 'Ona bir kaynak bulamadık',
    ],

    /*
     * «Kaynak bulamadık», «mevzû» değil. Tahriç edemememiz onun uydurma
     * olduğuna hüküm değildir — ürün sahibinin kararı, 7 Eylül 2026, T-06b.
     */
    'none_hint' => 'Elimizdeki kaynaklarda bulamadık. Bu, onun hakkında bir hüküm değildir; yalnızca tahriç edemediğimizi gösterir — bu yüzden yayımlanmaz.',

    'grade' => [
        'sahih' => 'Sahih',
        'hasan' => 'Hasen',
        'daif' => 'Zayıf',
        'mawdu' => 'Mevzû',
        'unknown' => 'Nakledilmiş bir hüküm yok',
    ],

    'labels' => [
        'quoted' => 'Derste geçtiği hâliyle lafız',
        'narrator' => 'Râvi',
        'source' => 'Kaynak lafzı',
        'takhrij' => 'Tahriç',
        'ruling' => 'Hüküm',
        'ayah' => 'Ayet',
        'surah' => 'Sûre',
        'diff_legend' => 'Her iki taraftaki vurgulu kısım, diğerinden farklı olan yerdir',
    ],

    'decision' => [
        'publish_with_source' => 'Kaynak lafzıyla yayımlanır',
        'drop' => 'Özetten çıkarılır',
        'pending' => 'Kararınızı bekliyor',
    ],

    'gate' => [
        'blocked' => 'Her delil karara bağlanmadan yayımlanamaz.',
        'remaining' => ':count tanesi hâlâ karara bağlanmadı',
    ],

    'counter' => ':total delilden :current.',
    'context' => 'Bağlam',
    'context_missing' => 'Geçtiği paragrafı deşifre metninde bulamadık.',
    'no_source' => 'Karşılaştırılacak bir kaynak lafzı yok.',

    /*
     * Üç eylem — SCREENS.md §5. **Üç, fazlası değil**; sıraları tercih
     * sırasıdır: önce kaynak lafzı, çünkü asıl olan odur.
     */
    'actions' => [
        'source' => 'Kaynak lafzını sabitle',
        'as_quoted' => 'Geçtiği gibi bırak',
        'remove' => 'Delili çıkar',
        'resume' => 'Hazırlamaya devam et',
    ],

    /*
     * «Geçtiği gibi bırak» **uyarır, engellemez** — karar sistemin değil
     * insanındır. İfade tam olarak ne olacağını söyler, «emin misiniz?»
     * diye sormaz.
     */
    'confirm' => [
        'as_quoted_title' => 'Ders lafzını sabitleme',
        'as_quoted' => 'Konuşmacının söylediği lafız yayımlanacak, kaynak lafzı değil — ve bunu kurumunuzun adıyla imzalayan sizsiniz.',
        'remove_title' => 'Delili çıkarma',
        'remove' => 'Bu delil özetten çıkarılır ve içinde görünmez; kaydı sizde saklı kalır.',
    ],

    'decided' => [
        'approved' => 'Kaynak lafzıyla sabitlendi',
        'corrected' => 'Derste geçtiği gibi sabitlendi',
        'removed' => 'Özetten çıkarıldı',
        'auto_passed' => 'Kendiliğinden geçti',
    ],

    'all_settled' => 'Delillerin hepsi karara bağlandı. Devam edin, işlem kaldığı yerden sürsün.',
    'empty' => 'Bu derste delil yok',
    'empty_body' => 'Delilsiz bir ders gayet sahih bir derstir — konuşmacı ne ayet ne hadis nakletmiş, dolayısıyla gözden geçirilecek bir şey yok.',
    'keyboard' => 'Eylemler için 1, 2 ve 3; deliller arasında geçmek için ok tuşları.',
];
