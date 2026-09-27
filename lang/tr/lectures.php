<?php

declare(strict_types=1);

/*
 * Liste ve oluşturma ekranları — SCREENS.md ekran 2 ve 3, görev T-16.
 * T-133'te uyarlandı.
 *
 * «Her metin lang/ dizininden gelir. Hiçbir metin bileşenin içine yazılmaz,
 * (Kaydet) bile.» Emoji yok, «token» kelimesi yok, model adı yok, hata kodu yok.
 */

return [

    'index' => [
        'title' => 'Özetler',
        'subtitle' => 'Hazırladığınız her şey ve önce kararınızı bekleyenler.',
        'search' => 'Başlıklarda ve konuşmacılarda ara',

        'quick' => [
            'title' => 'Bir bağlantıyla başlayın',
            'hint' => 'Dersin YouTube bağlantısını yapıştırın; hiçbir kaynak harcamadan sizin için kontrol edelim.',
            'cta' => 'Devam',
        ],

        /*
         * Karar bekleyenlere çağrı. Sayı görünen sayfayı değil kurumun
         * tamamını kapsar — yoksa gerçekte yirmi varken «iki delil» der.
         */
        'awaiting' => [
            'title' => 'Gözden geçirmenizi bekleyen :count özet var',
            'body' => 'Delilleri karara bağlanmadan hiçbiri yayımlanmaz.',
            'cta' => 'Göster',
        ],

        'filters' => [
            'status' => 'Durum',
            'month' => 'Ay',
            'speaker' => 'Konuşmacı',
            'all' => 'Hepsi',
        ],

        'sort' => [
            'label' => 'Sıralama',
            'smart' => 'Önce sizi bekleyenler',
            'newest' => 'En yeni',
            'oldest' => 'En eski',
            'title' => 'Başlığa göre',
        ],
        'view' => [
            'label' => 'Görünüm',
            'grid' => 'Kartlar',
            'table' => 'Tablo',
        ],
        'columns' => [
            'title' => 'Başlık',
            'speaker' => 'Konuşmacı',
            'date' => 'Tarih',
            'status' => 'Durum',
            'outputs' => 'Çıktılar',
            'action' => 'İşlem',
        ],
        'open' => 'Aç',
        'review' => 'Şimdi gözden geçir',
        /*
         * «Filtreye uyan yok», «hiç özet yok» demek değildir: ilki filtreyi
         * kaldırarak, ikincisi özet oluşturarak çözülür. İkisi için tek mesaj,
         * yazılmadığı durumu yanıltır.
         */
        'no_matches' => [
            'title' => 'Filtreye uyan özet yok',
            'body' => 'Aramayı genişletin ya da filtreyi kaldırıp bütün özetleri görün.',
        ],
    ],

    'create' => [
        'title' => 'Yeni özet',
        'subtitle' => 'Bize kaynağı, ders adını ve konuşmacısını verin; gerisi bizde.',
        'submit' => 'Hazırlamaya başla',
        'submit_hint' => 'Bu ayın kotasından bir özet düşülür.',

        /*
         * İsteğe bağlı alanlar katlanır — T-27. Hiçbiri zorunlu olmayan sekiz
         * alan, zorunlu olan üçünün arasına serpiştirildiğinde istenenin
         * yalnızca üç olduğunu gizler.
         */
        'details' => [
            'show' => 'İsteğe bağlı meclis bilgileri',
            'hide' => 'İsteğe bağlı bilgileri gizle',
        ],

        /*
         * Görünüm — T-95. **Varsayılan olarak katlı** — ürün sahibinin kararı,
         * 9 Eylül 2026 — **ama uygulanacak olanı gizlemez**: katlıyken fiilî
         * değerler rozet olarak görünür. Diller buradan «Ne üretiyoruz»a taşındı:
         * dil bir görünüm değil içerik kararıdır.
         */
        'appearance' => [
            'legend' => 'Görünüm',
            'hint' => 'Yalnızca bu özet için sayfa şablonu ve renk paleti; ikisi de Ayarlar\'daki kurum varsayılanını değiştirmez.',
            'template' => 'Sayfa şablonu',
            'palette' => 'Renk paleti',
            'change' => 'Değiştir',
            'done' => 'Tamam',
            'default' => 'Kurumunuzun varsayılanı',
            'custom' => 'Bu özete özel',
            'reset' => 'Kurum varsayılanına dön',
            'preview' => 'Görünümü önizle',
            'preview_title' => 'Görünüm önizlemesi',
            'preview_hint' => 'Gerçek şablonla, örnek içerik üzerinde, bu özetin renkleriyle.',
            'close' => 'Önizlemeyi kapat',
        ],

        /*
         * ★★ **Diller metni, her dilin kendi özetini alacağını söyler** —
         * ürün sahibinin isteği, 9 Eylül 2026. İki dil seçen iki sayfa
         * beklemeli, iki dilli tek sayfa değil.
         */
        'produce' => [
            'legend' => 'Ne üretiyoruz',
            'page' => 'Özet — her zaman üretilir',
            'page_hint' => 'Paylaşılabilir bağlantısı olan yayımlanmış bir özet; her dilin kendi özeti olur.',
            'languages' => 'Yayın dilleri',
            'languages_hint' => 'Seçtiğiniz her dil için ayrı bir yayımlanmış özet oluşur. Deliller her hâlükârda Arapça asıl üzerinden doğrulanır.',
            'languages_min' => 'En az bir dil seçin.',
            'quran' => ':language — ayetler onaylı :name mealiyle',
        ],

        'attribution' => [
            'hint' => 'Dersin sayfa başlığında ve meclis künyesinde nasıl nispet edileceği.',
            'about' => [
                'institution' => 'Konuşmacının yanında kurumun adı ve yeri.',
                'speaker_only' => 'Yalnızca konuşmacı, kurum adı olmadan.',
                'publisher_only' => 'Kuruma nispet yok — dışarıdan aktarılan bir ders için.',
            ],
            'preview' => 'Sayfada böyle görünür',
            'no_venue' => 'Kurumun adı ne sayfa başlığında ne de meclis künyesinde görünür.',
            'speaker_placeholder' => 'Konuşmacının adı',
        ],

        'groups' => [
            'speaker' => 'Konuşmacı',
            'when' => 'Zaman',
        ],
        'weekday_hint' => 'Miladi tarihten doldurulur, gerekirse değiştirilir.',

        'summary' => [
            'title' => 'Talebinizin özeti',
            'source' => 'Kaynak',
            'lesson' => 'Ders',
            'outputs' => 'Çıktılar',
            'languages' => 'Diller',
            'appearance' => 'Görünüm',
            'missing' => 'Henüz belirlenmedi',
            'text_source' => 'Yapıştırılmış deşifre metni',
            'upload_source' => 'Ses veya video dosyası',
            'page' => 'Özet',
            'carousel' => 'Instagram slaytları',
            'left' => 'Bu ay :limit hakkınızdan :left kaldı.',
            'todo' => 'Başlamadan önce',
            'todo_source' => 'Ders kaynağı',
            'todo_title' => 'Ders başlığı',
            'todo_speaker' => 'Konuşmacının adı',
        ],

        'source' => [
            'legend' => 'Ders kaynağı',
            'tabs' => [
                'url' => 'YouTube bağlantısı',
                'upload' => 'Ses veya video dosyası',
                'text' => 'Metin deşifresi',
            ],
            'url_label' => 'Dersin YouTube bağlantısı',
            'url_hint' => 'Bağlantı, hiçbir kaynak harcanmadan kontrol edilir; başlığı ve süreyi önce görürsünüz.',
            'upload_hint' => '500 MB\'a kadar. Ses veya video.',
            'soon' => 'Yakında',
            'text_label' => 'Deşifre metnini yapıştırın',
            'text_hint' => 'Ya da srt veya vtt altyazı dosyası veya düz metin yükleyin.',
            'text_placeholder' => 'Dersin tam metnini buraya yapıştırın…',

            /*
             * Tekrar — T-65. **Engellemez, durur ve sorar**: aynı videodan
             * başka bir dilde yeniden üretmek meşru bir ihtiyaçtır; sessiz
             * tekrar değildir.
             */
            'duplicate' => 'Bu bağlantı daha önce «:title» içinde girilmişti. '
                .'Ondan ikinci bir özet istiyorsanız — başka bir dilde ya da başka bir şablonla — bunu onaylayıp tekrar gönderin.',
            'duplicate_confirm' => 'Tekrar olduğunu biliyorum ve ondan ikinci bir özet istiyorum.',
        ],

        'preflight' => [
            'run' => 'Bağlantıyı kontrol et',
            'running' => 'Bağlantı kontrol ediliyor…',
            'title' => 'Bağlantıda bulduklarımız',
            'video_title' => 'Başlık',
            'duration' => 'Süre',
            'duration_minutes' => ':count dakika',
            'captions' => 'Arapça altyazı',
            'captions_found' => 'Var — metin buradan okunacak, ses deşifresi yapılmayacak',
            'captions_missing' => 'Yok — ses deşifre edilecek, bu daha yavaştır',
            'too_long' => 'Bu dersin süresi :minutes dakika, aboneliğinizin sınırı ise :limit dakika. Daha kısa bir ders seçin ya da abonelik sayfasından sınırınızı yükseltin.',
            'unavailable' => 'Bu bağlantının bilgilerini okuyamadık. YouTube\'da bir ders bağlantısı olduğundan ve oynatma listesi olmadığından emin olun ya da ses dosyasını yükleyin.',
            'session_expired' => 'Sayfa uzun süre açık kaldığı için oturumunuz sona erdi. Sayfayı yenileyip tekrar deneyin.',
        ],

        'outputs' => [
            'legend' => 'İstenen çıktılar',
            'page' => 'Sayfa',
            'page_always' => 'Her zaman üretilir',
            'carousel' => 'Instagram karuseli',
            'images' => 'Görsel paketi',
            'locked' => 'Kurum paketi ve üzerinde.',
            /*
             * **Görsel paketi herkes için kapalı**, görüntüleyicisi kurulana
             * kadar (T-20). İşaretlenip hiçbir şey üretmeyen bir kutu,
             * tutulmayan bir sözdür — «henüz açılmadı» diyenden kötüdür.
             */
            'soon' => 'Yakında.',
        ],

        'meeting' => [
            'legend' => 'Meclis bilgileri',
            'title' => 'Ders başlığı',
            'subtitle' => 'Alt başlık',
            'speaker' => 'Konuşmacının adı',
            'gregorian' => 'Miladi tarih',
            'hijri' => 'Hicri tarih',
            'hijri_hint' => 'Göründüğü gibi yazılır, örneğin «12 Receb 1447».',
            'weekday' => 'Gün',
            'time_note' => 'Anlatım saati',
            'venue_mode' => 'Nispet biçimi',
            'venue' => [
                'institution' => 'Kurumun adıyla',
                'speaker_only' => 'Yalnızca konuşmacının adıyla',
                'publisher_only' => 'Nispetsiz',
            ],
        ],
    ],
];
