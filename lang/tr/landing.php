<?php

declare(strict_types=1);

/*
 * Tanıtım sayfası metni — Türkçe (T-131).
 *
 * `lang/ar/landing.php` esas alınarak uyarlanmıştır, satır satır
 * çevrilmemiştir. Arapça metin bilinçli olarak edebî bir nesirdir;
 * birebir aktarıldığında Türkçede yapay ve fazla ağdalı durur.
 *
 * Arapça asıldır: ürünün ne yaptığı konusunda ikisi çelişirse Arapça
 * geçerlidir ve hatalı olan bu dosyadır.
 *
 * ★ Ayet ve hadis metni asla yerine çeviri konularak verilmez — lafız
 * Arapça kalır, altına etiketlenmiş bir meal düşülür; Arapça olmayan bir
 * özet de fiilen böyle yayımlanır. {@see App\Enums\Locale::meaningLabel}
 */

return [

    'meta' => [
        'title' => 'Khulasat — sesli dersten kaynaklı, ilmî bir özete',
        'description' => 'Saatlerce süren ders ve konferansları kimliğinizi taşıyan, kaynaklı bir sayfaya dönüştürün. Her ayet ve hadis güvenilir kaynağıyla harfi harfine eşleştirilir ve tahrici verilir; doğrulanmayan hiçbir delil yayımlanmaz.',
        'og_title' => 'Khulasat — sesli dersten kaynaklı, ilmî bir özete',
        'og_description' => 'Ders bir kez anlatılır, özeti yıllarca okunur. Anlatılan ilmi kendi kurumsal kimliğinizle tutan tek bir sayfa; içindeki her ayet ve hadis kaynağına dayandırılmış. Kaynağı gösterilmeyen hiçbir nakil yayımlanmaz.',
        'og_image_alt' => 'Khulasat — sesli dersten kaynaklı, ilmî bir özete; her delil kaynağına bağlı',
        'site_name' => 'Khulasat',
        'og_locale' => 'tr_TR',
        'org_description' => 'Dersin ilmini, her ayet ve hadisi kaynağına dayandırılmış tek bir sayfada koruyan platform.',
        'app_description' => 'Ders bağlantısını alır ve tek bir sayfa verir: dakikalar içinde okunan düzenli bir özet ve muteber metinlerle harfi harfine karşılaştırıldıktan sonra kaynağına bağlanmış ayet ve hadisler.',
    ],

    'nav' => [
        'home_aria' => 'Khulasat — ana sayfa',
        'sections_aria' => 'Sayfa bölümleri',
        'menu_aria' => 'Menü',
        'anatomy' => 'Ne alırsınız',
        'how' => 'Nasıl çalışır',
        'verify' => 'Doğruluk',
        'tool' => 'Doğrulama aracı',
        'audience' => 'Kimler için',
        'faq' => 'Sorular',
        'login' => 'Giriş',
        'contact' => 'Bize ulaşın',
        'lang_aria' => 'Sayfa dili',
        'skip' => 'İçeriğe geç',
    ],

    'hero' => [
        'h1' => 'Sesli dersten, dakikalar içinde okunan kaynaklı ilmî bir özete',
        'eyebrow' => 'Kaynağına dayandırılmış özetler',
        'lede' => 'Saatlerce süren ders ve konferansları kimliğinizi taşıyan, kaynaklı bir sayfaya dönüştürün. Her ayet ve hadisi güvenilir kaynağıyla harfi harfine eşleştirir, tahricini eksiksiz veririz; böylece ilminizi güvenle ve emanete sadık kalarak paylaşırsınız.',
        'cta_primary' => 'İlk özetinizi deneyin',
        'cta_ghost' => 'Örnek özeti inceleyin',
        'cta_real' => 'Yayımlanmış bir özeti inceleyin',
        'assure_time' => 'Bir saatlik ders dakikalar içinde hazır',
        'assure_youtube' => 'YouTube bağlantılarıyla çalışır',
        'assure_noinstall' => 'Kurulum gerektirmez',
        'assure_langs' => 'Dört dilde yayımlanır',
        'access' => 'Şimdilik özel davetle. Hesabınızı etkinleştirmek için bize ulaşın ya da platformu ilk dersinizle deneyin.',
        'caption' => 'Örnek sayfa — tarif edilen alanların yerini sizin içeriğiniz alır.',
        'caption_real' => 'Fiilen yayımlanmış bir özet — tamamını açın →',
    ],

    'demo' => [
        'title' => 'Ders başlığı',
        'subtitle' => 'Alt başlık — dersin konusunu anlatan tek cümle',
        'ayah' => 'Anahtar ayet buraya gelir; işitildiği gibi değil, Kur\'an\'daki lafzıyla',
        'ayah_src' => 'Sûre adı · ayet numarası',
        'speaker_label' => 'Konuşmacı',
        'speaker' => 'Konuşmacının adı',
        'place_label' => 'Yer',
        'place' => 'Dersin verildiği yer',
        'axis_title' => 'Bölüm başlığı',
        'lead' => 'Giriş paragrafı buraya gelir — ana fikri üç dört satırda toparlar; mecliste söylenenden birebir alınmış değil, okunmak için yazılmış bir nesirdir.',
        'sacred' => 'Kur\'ânî delil buraya gelir; mushafla karşılaştırıldıktan sonra onun lafzıyla sabitlenir',
        'sources_head' => 'Geçen ayet ve hadisler',
        'ayah_ref' => 'Sûre adı · ayet numarası',
        'ayah_text' => 'Ayet, kaynaktaki lafzıyla',
        'hadith_ref' => 'Kaynak · kitap · hadis numarası',
        'hadith_text' => 'Hadis, kaynaktaki lafzıyla',
        'attest' => 'Bu özet dersten çıkarılmıştır, dersin birebir metni değildir ve konuşmacı tarafından gözden geçirilmemiştir. Asıl olan kaydın kendisidir.',
        'attest_link' => 'Bu özetteki bir hatayı bildirin',
        'mk_by' => 'Hazırlanışı:',
        'mk_name' => 'Khulasat',
    ],

    'problem' => [
        'eyebrow' => 'Bir hafta sonra dersten geriye ne kalır',
        'h2' => 'Onlarca saatlik ilim, oynatma listelerinde unutulup gidiyor',
        'lede' => 'Kayıt, anlatıldığı saatte işini görür; sonra kimsenin dönmediği uzun bir ses kaydı olarak kalır. Bedelini konuşmacı da okuyucu da öder.',
        'head_aspect' => 'Yön',
        'head_now' => 'Geleneksel deşifre',
        'head_effect' => 'Khulasat ile',
        'axes' => [
            ['t' => 'Boşa giden zaman', 'f' => '60 ila 90 dakikalık, baştan sona dinlenmesi zor kayıtlar', 'e' => 'Dakikalar içinde okunan, dersin bütün fikirlerini toplayan odaklı bir özet'],
            ['t' => 'Aramanın zorluğu', 'f' => 'Bilgi ses kayıtlarında hapsolmuş; içinde arama yapmak ya da alıntı almak zor', 'e' => 'Doğrudan arayabileceğiniz, alıntı yapabileceğiniz ve saklayabileceğiniz düzenli bir dijital metin'],
            ['t' => 'Deşifre ve kaynak gösterme zahmeti', 'f' => 'Ders başına 3-5 saat süren, kaynaksız elle deşifre', 'e' => 'Otomatik işleme ve delillerin doğru tahrici; size yalnızca eşleşmeyenleri incelemek kalır'],
        ],
        'verdict' => 'Khulasat yalnızca bir özetleme aracı değil, ilmî bir emanettir. Genel yapay zekâ araçları sözü kısaltır ama emanette gevşek davranır: ayetin lafzını değiştirir, hadisi tahricsiz nakleder. Khulasat ise dersi odaklı başlıklar hâlinde yeniden düzenler ve her delili aslıyla harfi harfine eşleştirir.',
    ],

    'anatomy' => [
        'eyebrow' => 'Elinize ne geçiyor',
        'h2' => 'Tek bir sayfa: meşgul olan okur, araştırmacı dayanak alır',
        'lede_html' => 'Kesintisiz bir metin duvarı değil: fikri öne çıkaran bölümler, anlamayı pekiştiren karşılaştırmalar, kaynağına bağlanmış deliller ve kendi kimliğinizi taşıyan bir meclis kartı. Aşağıda gördüğünüz, yapısı, renkleri ve yazı tipleriyle sayfanın kendisidir — içindekiler yalnızca <b>tarif edilmiş alanlardır</b> ve yayımlarken yerlerini sizin içeriğiniz alır.',
        'lede_real' => 'Kesintisiz bir metin duvarı değil: fikri öne çıkaran bölümler, kaynağına bağlanmış deliller ve kurumun kimliğini taşıyan bir meclis kartı. Aşağıdakiler, fiilen yayımlanmış bir özetten yayımlandığı lafızla alınmış kesitlerdir.',
        'showcase_title' => 'Bu özet fiilen yayımlanmıştır',
        'showcase_body' => 'Şu anda platformda duran gerçek bir sayfa; gerçek içerik ve gerçek bir konuşmacıyla. Okuyucuların gördüğü hâliyle görmek için tamamını açın.',
        'showcase_cta' => 'Yayımlanmış özeti aç',
        'rows' => [
            ['t' => 'Önce fikir', 'b' => 'Dersin ana fikri sayfanın başında; Kur\'ânî delili ise göze her şeyden önce çarpan altın çerçeveli ayrı bir yerde.'],
            ['t' => 'Geçen ayet ve hadisler', 'b' => 'Sayfanın sonunda bir kaynak listesi: her delil kaynağındaki lafzıyla, ayet numarası veya hadis kaynağıyla — okuyan dilerse kendisi denetler.'],
            ['t' => 'Emanet ve ilmî kaynak gösterme', 'b' => '«Gayriresmî özet, konuşmacı tarafından gözden geçirilmemiştir», asıl kaydın bağlantısı ve kaldırma talebi için açık bir yol — istisnasız her sayfada.'],
        ],
        'note' => 'Yukarıda gördüğünüz örnek sayfadır, gerçek bir kurumun sayfası değil: konuşmacı adı yok, yer adı yok, gerçek ders metni yok. Burada tarif edilen her alan, yayında sizin içeriğinizle dolar.',
        'note_real' => 'Yukarıdaki kesitler fiilen yayımlanmış bir özetten, yayımlandığı lafızla alınmıştır; yalnızca kaynağıyla eşleşen deliller gösterilir.',
        'extra' => 'Aynı sayfadan — yeniden çalışmaya gerek kalmadan — baskı ve arşiv için bir dosya, kurumsal paketlerde de paylaşılacak bir Instagram karuseli. İlim bir kez kaynağına bağlanır, sonra insanlara bulundukları yerde ulaşır.',
    ],

    'how' => [
        'eyebrow' => 'Nasıl çalışır',
        'h2' => 'Bağlantıdan yayımlanmış sayfaya üç aşama',
        'lede' => 'Bağlantıyı göndermekten yayımlanmış sayfaya kadar — kurulacak bir program ve sizden beklenen teknik bir iş yok.',
        'steps' => [
            ['t' => 'Dersi gönderin', 'b' => 'YouTube bağlantısı, ses ya da video dosyası veya yazılı metni; konuşmacısı ve tarihiyle birlikte.'],
            ['t' => 'Doğrulamayı gözden geçirin', 'b' => 'Metin dolgu, tekrar ve konu dışı sözlerden arındırılır, fikirler anlam bozulmadan başlıklar hâlinde düzenlenir ve her delil kaynağıyla eşleştirilir. Önünüze yalnızca eşleşmeyenler gelir.'],
            ['t' => 'Yayımlayın ve ilmi ulaştırın', 'b' => 'Özet şablonunuza dizilir ve kimliğinizi taşıyan kalıcı bir bağlantıda, paylaşıma hazır olarak yayımlanır.'],
        ],
        'gate_flag' => 'İncelemeniz yalnızca şüpheli bir yer varsa gerekir',
    ],

    'verify' => [
        'eyebrow' => 'Metne sadakat',
        'h2' => 'Metni kaynağıyla eşleştiririz, kendimizden bir şey katmayız',
        'lede' => 'Bir delilin sahih olup olmadığını ya da ne anlama geldiğini yapay zekâya sormayız. Metnini, kendi elimizde tuttuğumuz güvenilir kaynakların bir nüshasıyla kelime kelime eşleştiririz. Hüküm kaynağındır, zanna ya da makinenin tahminine değil: delil eşleşirse tahriciyle yayımlanır, farklıysa siz karar verene kadar bekletilir.',
        'mission' => 'Khulasat, sorumlu İslami yapay zekâda öncü bir model olarak tasarlandı: algoritmaların hızını ilmî emanetle birleştirir; metinlerde halüsinasyon da dinî lafızlarda tahrif de yoktur.',
        'tracks' => [
            ['tag' => 'Tam eşleşme', 't' => 'Hemen onaylanır', 'b' => 'Delil güvenilir kaynaklarla harfi harfine eşleşir; kaynağın lafzıyla sabitlenir, ayet numarası ya da hadis tahrici otomatik olarak eklenir.'],
            ['tag' => 'Kısmi eşleşme', 't' => 'İncelemeniz gerekir', 'b' => 'Delil mana ile ya da yakın bir lafızla geçmiştir; derste söylenen, kaynağın lafzıyla yan yana önünüze gelir ve kaynağın lafzını tek tıkla onaylarsınız.'],
            ['tag' => 'Eşleşmedi', 't' => 'Yayın otomatik olarak durur', 'b' => 'Delil güvenilir kaynaklarda bulunamamış ya da çok zayıf veya uydurma olarak değerlendirilmiştir; açık kararınız olmadan yayımlanmaz. Platformda bu adımı atlayan hiçbir seçenek yoktur.'],
        ],
        'gate' => [
            'title' => 'Denetim kapısı',
            'pill' => 'Kararınızı bekliyor',
            'counter' => '14 delilden 1\'i — kısmi eşleşme',
            'from_key' => 'Derste geçtiği hâliyle lafız',
            'from_meaning' => 'Ameller ancak niyetlere göredir ve herkese niyet ettiği şey vardır.',
            'from_src' => 'Kaynağı: dersin kaydı',
            'to_key' => 'Kaynakta geçtiği hâliyle lafız',
            'to_meaning' => 'Ameller ancak niyetlere göredir ve şüphesiz herkese niyet ettiği şey vardır.',
            'to_src' => 'Sahîh-i Buhârî · Bed\'ü\'l-vahy · 1. hadis',
            'btn_keep' => 'Kaynak lafzını sabitle',
            'btn_drop' => 'Delili çıkar',
            'foot' => 'Siz seçene kadar hiçbir şey yayımlanmaz',
            'caption' => 'Bu, denetim kapısının kendi panelinizde göründüğü hâlinin bir benzetimidir. Karar her zaman size aittir — şüphelendiğimizi gizlemek yerine önünüze koyarız.',
        ],
        'note_title' => 'Durduğumuz bir çizgi',
        'note_body' => 'Metni kaynağına dayandırırız; hadisin sıhhati hakkında hüküm vermeyiz. Bir aletle bir müftü arasındaki fark, platformun hiçbir sayfada ve hiçbir ekranda aşmadığı bir çizgidir.',
        'tool' => [
            'title' => 'Kendi metninizde deneyin',
            'body' => 'Herhangi bir makaleyi, hutbeyi ya da elden ele dolaşan bir mesajı yapıştırın. İçindeki her ayet ve hadisi çıkarır, her birini kaynağıyla eşleştirir ve her hükmün gerekçesini belirtiriz. Hesap gerekmez.',
            'cta' => 'Doğrulama aracını açın',
            'arabic_only' => 'Araç Arapçadır ve Arapça metinler üzerinde çalışır.',
        ],
    ],

    'audience' => [
        'eyebrow' => 'Kimler için',
        'h2' => 'Dört ayrı ihtiyaç ve hepsine yeten tek bir sayfa',
        'cards' => [
            [
                't' => 'Üniversite öğrencileri ve araştırmacılar',
                'pain' => 'İki saatlik dersi dakikalar içinde gözden geçirin, doğruluktan ödün vermeden.',
                'wins' => [
                    'Dersin tamamını yeniden dinlemek yerine odaklı başlıklar ve karşılaştırmalar',
                    'Doğrudan arayabileceğiniz ve alıntı yapabileceğiniz bir metin',
                    'Her alıntı ve delil, doğrulanmış kaynağına bağlı',
                ],
            ],
            [
                't' => 'İçerik üreticileri ve hatipler',
                'pain' => 'Dersinizi okunur bir arşive ve hazır paylaşımlara dönüştürün.',
                'wins' => [
                    'Özetten doğrudan okuma ve baskı dosyası',
                    'Kurumsal paketlerde hazır Instagram kartları',
                    'Sözünüzü daha geniş bir kitleye ulaştıran tek bir bağlantı',
                ],
            ],
            [
                't' => 'Camiler ve İslam merkezleri',
                'pain' => 'Caminin derslerini düzenli sayfalarda arşivleyin.',
                'wins' => [
                    'Haftalık dersin özeti, sohbet gruplarında tek bir bağlantıyla',
                    'Merkezin adını ve logosunu taşıyan bir sayfa',
                    'Yıldan yıla büyüyen bir davet arşivi',
                ],
            ],
            [
                't' => 'Akademiler ve ilim enstitüleri',
                'pain' => 'Akademinizin öğrencileri için yazılı ve kaynaklı bir başvuru kaynağı.',
                'wins' => [
                    'Her sesli ders için yazılı materyal',
                    'Sınav öncesi dizinlemesi ve tekrarı kolay',
                    'Aramada dizinlenen ve sizi tanıtan sayfalar',
                ],
            ],
        ],
    ],

    'brand' => [
        'eyebrow' => 'Şablon ve kimlik',
        'h2' => 'Sizin adınıza ve malzemenize yakışan şablonla yayımlanır',
        'lede' => 'Hazır bir şablon ve renk paleti seçersiniz; adınızı taşıyan her sayfa aynı profesyonel düzeyde görünür.',
        'templates_title' => 'Her biri kendi yapısında altı şablon',
        'templates_sub' => 'Düzen ve karakter değişir; içerik her birinde eksiksizdir — sunum değişir, hiçbir şey düşmez.',
        'templates' => ['Klasik', 'Modern', 'Editoryal', 'Ders', 'Özet', 'Araştırma'],
        'palettes_title' => 'Altı ayarlanmış palet',
        'palettes_sub' => 'Kâğıt ve ekran için birlikte ayarlanmıştır; seçtiğiniz her şablonda ikinci bir ayara gerek kalmadan çalışır.',
        'palettes' => ['Zümrüt', 'Çivit', 'Toprak kahve', 'Füme', 'Vişne', 'Turkuaz'],
        'logo_title' => 'Önce sizin amblemiz; bizimki paletinize uyum sağlar',
        'logo_sub' => 'Kurumunuzun adı ve logosu her sayfanın başında, bağlantılarınız sonunda yer alır. Khulasat logosu sayfanızın rengini alır; sizinkiyle yarışmaz.',
        'langs_title' => 'Dört dünya dilinde yayın desteği',
        'langs_sub' => 'Özet Arapça yayımlanır; yanında bir veya daha fazla dilde de yayımlanabilir — her birinin kendi kalıcı bağlantısıyla.',
    ],

    'faq' => [
        'eyebrow' => 'Sorular',
        'h2' => 'Her şeyden önce sorulan dokuz soru',
        'items' => [
            ['q' => 'Hadisin sıhhati hakkında hüküm veriyor musunuz?', 'a' => 'Hayır, yalnızca eşleştirir ve tahric ederiz. Platform hadisin lafzını güvenilir kaynağıyla eşleştirir, yerini ve numarasını verir, derecesini de kaynağın belirttiği şekliyle aktarır; kendiliğinden hüküm vermez. Hadis hakkında hüküm vermek ilim ehlinin işidir; emanet, metni olduğu gibi aktarmak ve araştırmacının kendisinin dönüp bakabilmesini sağlamaktır.'],
            ['q' => 'Derste geçen bir delil kaynağıyla eşleşmezse ne olur?', 'a' => 'Yayın o noktada durur. Bir delil kaynağından farklı bir lafızla geçmişse platform sizi uyarır, derste söyleneni kaynağın lafzıyla yan yana gösterir; siz de kaynağın lafzını onaylar ya da delili kaldırırsınız. Platformda bu adımı atlayan hiçbir seçenek yoktur.'],
            ['q' => 'Bir özet ne kadar sürer?', 'a' => 'Yalnızca birkaç dakika. Bir saatlik ders dakikalar içinde incelemeye hazır olur; daha uzun dersler daha fazla sürer. Ardından inceleme sizin birkaç dakikanızı alır; bütün deliller kaynaklarıyla eşleşirse hiç gerekmez.'],
            ['q' => 'Dinî olmayan dersler için de uygun mu?', 'a' => 'Evet — ders, kurs, seminer ve panel için. Kaynağa bağlama, malzemede ayet veya hadis bulunduğunda devreye girer; şer\'î bir delil yoksa özet durmadan geçer. Altı şablon arasında yalnızca meclise değil, müfredata dayalı derse, özete ve araştırmaya uygun olanlar da vardır.'],
            ['q' => 'Nasıl hesap açtırırım?', 'a' => 'Bu aşamada hesaplar yalnızca davetledir, kendiliğinden kayıt yoktur. Sayfanın altındaki «Dersinizle deneyin» sekmesinden kendi derslerinizden biriyle deneyin ya da «Bize ulaşın» sekmesinden yazın; size dönelim.'],
            ['q' => 'Abonelik ne kadar?', 'a' => 'Fiyatlandırma her kurumla, büyüklüğüne ve ayda kaç özet yayımladığına göre ayrı ayrı belirlenir. Bu aşamada çevrim içi ödeme yoktur. Sayfanın altındaki formdan bize yazın, ne kadar yayın yaptığınızı bildirin; size özel bir rakamla dönelim.'],
            ['q' => 'Ayet ve hadis metinleri nereden alınıyor?', 'a' => 'Tamamının bir nüshasını kendi sistemimizde tuttuğumuz muteber kaynaklardan; böylece hiçbir dış servise sormaz ve hiçbir makinenin hafızasına dayanmayız. Her delilin kaynağı, sayfanın sonundaki «Geçen ayet ve hadisler» listesinde açıkça görünür, okuyucu kendisi denetleyebilir.'],
            ['q' => 'Ortaya çıkan sayfanın hakları kime ait?', 'a' => 'Bütün haklar sizindir. Sayfa adınız ve logonuzla, sahibi olduğunuz kalıcı bir bağlantıda yayımlanır; onu dilediğiniz zaman güncelleyebilir, yayından kaldırabilir ya da kalıcı olarak silebilirsiniz.'],
            ['q' => 'Bir sayfanın kaldırılması veya hatasının bildirilmesi nasıl istenir?', 'a' => 'Yayımlanan her sayfanın altında açık bir bağlantı vardır ve giriş yapmadan çalışır — çünkü itiraz eden çoğu zaman hesap sahibi değildir: kendisine söz nispet edilen bir hoca ya da yanlış bir kaynak gördüğünü düşünen bir okuyucu. Talep, tarafımızda takip edilen bir kayıt açar.'],
        ],
    ],

    'invite' => [
        'h2' => 'Bize yazın ya da tek bir dersle deneyin',
        'lede' => 'Sorunuzu ya da ihtiyacınızı bize yazın. Ya da bir ders bağlantısı gönderin; ondan eksiksiz bir özet hazırlayalım ve gerçek delillerini tek tek önünüze koyalım — hükmü sonra siz verin.',
        'alt_showcase' => 'Ya da önce fiilen yayımlanmış bir özete göz atın →',
        'alt_anatomy' => 'Ya da önce örnek sayfayı görün →',
        'tabs_aria' => 'Talep türü',
        'tab_contact' => 'Bize ulaşın',
        'tab_lecture' => 'Dersinizle deneyin',
        'contact_hint' => 'Bir soru, fiyatlandırma hakkında bir şey ya da bize söylemek istediğiniz başka bir konu.',
        'lecture_hint' => 'Khulasat\'ı kendi derslerinizden biriyle deneyin: bağlantısını gönderin; eksiksiz bir özet hazırlayıp yayından önce delillerini size gösterelim.',
        'name' => 'Ad',
        'role' => 'Sıfatınız',
        'role_placeholder' => 'Seçin…',
        'roles' => ['Öğrenci veya araştırmacı', 'İçerik üreticisi veya hatip', 'Cami veya İslam merkezi', 'Akademi veya ilim enstitüsü', 'Diğer'],
        'contact' => 'E-posta veya WhatsApp numarası',
        'link' => 'Ders bağlantısı',
        'optional' => '(isteğe bağlı)',
        'message' => 'Mesajınız',
        'message_placeholder' => 'Sorunuz ya da ihtiyacınız.',
        'lecture_message' => 'Notlar',
        'lecture_message_placeholder' => 'Ders ya da kurumunuz hakkında bilmemizi istediğiniz herhangi bir şey.',
        'submit_contact' => 'Mesajı gönder',
        'submit_lecture' => 'Deneme talep et',
        'tiny' => 'Her mesajı ekibimizden biri okuyup yanıtlıyor; pazarlama e-postası göndermiyoruz.',
        'sent_contact' => 'Mesajınız bize ulaştı. Ekibimizden biri okuyup yazdığınız iletişim adresinden size dönecek.',
        'sent_lecture' => 'Talebiniz ve ders bağlantısı bize ulaştı. Ekibimizden biri inceleyip yazdığınız iletişim adresinden size dönecek.',
        'errors' => [
            'name' => 'Ad gerekli.',
            'role' => 'Size neyin uygun olduğunu bilmemiz için sıfatınızı seçin.',
            'contact' => 'Size dönebilmemiz için bir e-posta veya WhatsApp numarası gerekiyor.',
            'link' => 'Bağlantı geçersiz. Adres çubuğundan tam olarak kopyalayın.',
            'message' => 'Mesaj alanın aldığından uzun. Kısaltın veya iletişim adresinizden gönderin.',
            'message_required' => 'Size nasıl yardımcı olabileceğimizi bilmemiz için mesajınızı yazın.',
            'lecture_link' => 'Denememizi istediğiniz dersin bağlantısını ekleyin.',
        ],
    ],

    'footer' => [
        'slogan' => 'Okunan bir özet, doğrulanan bir delil.',
        'page' => 'Sayfa',
        'links' => 'Bağlantılar',
        'login_tenants' => 'Kurum girişi',
        'complaint' => 'Kaldırma talebi veya hata bildirimi',
        'anatomy_link' => 'Örnek sayfa',
        'rights' => 'Khulasat · © 2026',
    ],

];
