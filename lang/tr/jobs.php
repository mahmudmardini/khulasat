<?php

declare(strict_types=1);

/*
 * Üretim işinin durumları ve metinleri — SCREENS.md §durumlar ve renkleri,
 * şartname §5. T-133'te uyarlandı.
 *
 * **«Token» yok, model adı yok, hata kodu yok** — SCREENS.md'nin başındaki
 * yönetici ilke. Bu yüzden aşamalar koddaki adlarıyla değil, içerik
 * yöneticisinin anladığı adlarla anılır.
 */

return [

    'status' => [
        'queued' => 'Hazırlanıyor',
        'transcribing' => 'Hazırlanıyor',
        'cleaning' => 'Hazırlanıyor',
        'structuring' => 'Hazırlanıyor',
        'extracting' => 'Hazırlanıyor',
        'verifying' => 'Hazırlanıyor',
        'writing' => 'Hazırlanıyor',
        'rendering' => 'Hazırlanıyor',
        'needs_review' => 'Gözden geçirmenizi bekliyor',
        'published' => 'Yayımlandı',
        'failed' => 'Durdu',
        'unpublished' => 'Yayımlanmadı',
    ],

    /*
     * «Her uzun işlemin belirli bir durumu vardır; belirsiz bir dönen çark ve
     * uydurma bir yüzde yoktur» — §genel kurallar.
     */
    'steps_label' => 'Özet hazırlama aşamaları',

    'steps' => [
        'transcribing' => 'Ders metninin çıkarılması',
        'cleaning' => 'Metnin temizlenmesi',
        'structuring' => 'Yapının çıkarılması',
        'extracting' => 'Delillerin çıkarılması',
        'verifying' => 'Delillerin doğrulanması',
        // Gözden geçirme, model çağrısı olmasa da görünümde bir aşamadır;
        // düşürülmesi, beklemenin bizde değil kullanıcıda olduğunu gizlerdi.
        'review' => 'Delilleri gözden geçirmeniz',
        'writing' => 'Metnin yazılması',
        'rendering' => 'Sayfanın üretilmesi',
    ],

    'step_state' => [
        'done' => 'Tamamlandı',
        'active' => 'Şimdi çalışıyor',
        // **Sizi bekliyor** — SCREENS §4. «Çalışıyor» ile aynı değildir: o
        // kendiliğinden ilerler, bu ise kullanıcı bir şey yapana dek durur.
        'awaiting' => 'Sizi bekliyor',
        'pending' => 'Başlamadı',
        'failed' => 'Durdu',
    ],

    'outputs' => [
        'page' => 'Sayfa',
        'carousel' => 'Karusel',
        'images' => 'Görsel paketi',
        'not_produced' => 'Üretilmedi',
    ],

    'follow' => [
        'title' => 'Hazırlığı izleme',
        'live' => 'Bu sayfa kendiliğinden güncellenir.',
        'review_cta' => 'Denetim kapısını aç',
        'view_cta' => 'Özeti aç',
        'change_source' => 'Kaynağı değiştir',
        'failed_title' => 'Hazırlık durdu',
        'failed_fallback' => 'Hazırlık tamamlanmadan durdu. Tekrar deneyin; sürerse ders kaynağını değiştirin.',
        'cancelled' => 'Bu özetin hazırlanması iptal edildi.',
        'needs_review_note' => 'Hazırlık, kararınızı bekleyen delillerde durdu. Siz karara bağlamadan hiçbir şey yayımlanmaz.',
        'published_note' => 'Hazırlık tamamlandı ve özet yayımlandı.',
        'pending_count' => 'Karara bağlanmamış :count delil kaldı.',

        'pending_one' => 'Karara bağlanmamış bir delil kaldı.',
        'pending_two' => 'Karara bağlanmamış iki delil kaldı.',
        'pending_few' => 'Karara bağlanmamış :count delil kaldı.',
        'pending_many' => 'Karara bağlanmamış :count delil kaldı.',
        'pending_other' => 'Karara bağlanmamış :count delil kaldı.',

        'running_note' => 'Hazırlık sürüyor.',
        'running_body' => 'Bu sayfadan ayrılabilirsiniz; iş kendiliğinden tamamlanır.',

        'cancel' => 'Hazırlığı iptal et',
        'cancel_hint' => 'Hazırlık şimdi durur ve yeniden başlamaz. Bu özet için kotanızdan harcanan geri verilmez.',
        'cancel_failed' => 'İptal ulaşmadan hazırlık bitti, iptal edilecek bir şey yok.',

        /*
         * Kaldırma bildirimi — T-28.
         *
         * **Ve «arıza» denmez.** Sayfa bir kararla kaldırıldı; kurum bunu ve
         * sebebini bilmeyi hak eder — çalışan bir bağlantının çalışmaz olup
         * arızanın bizde olduğunu sanmasını değil.
         */
        'takedown' => 'Bu sayfa yayından kaldırıldı',
        'takedown_body' => 'Bağlantısı artık hata değil «bu özet kaldırıldı» sayfasını döndürüyor. Metin ve deliller sizde olduğu gibi duruyor.',
        'takedown_reason' => 'Sebep',
        'takedown_at' => 'Kaldırma tarihi',

        'started' => 'Başladı',
        'finished' => 'Bitti',
        'elapsed' => 'Sürdü',

        'minutes_one' => 'Bir dakika',
        'minutes_two' => 'İki dakika',
        'minutes_few' => ':count dakika',
        'minutes_many' => ':count dakika',
        'minutes_other' => ':count dakika',
    ],

    /*
     * Canlı izleme ekranı — T-83.
     *
     * ★ **Hiçbir yerinde yüzde yoktur** — SCREENS.md §4. Üç büyük evre,
     * gerçek sekiz aşamayı toplar ve başka bir şey uydurmaz.
     */
    'live' => [
        'phases' => [
            'listen' => 'Dinleme',
            'understand' => 'Anlama ve doğrulama',
            'write' => 'Yazma ve üretme',
        ],
        'elapsed' => ':time geçti',

        'wizard' => [
            'position' => ':total adımdan :current · :name',
            'queued' => 'Sırada; birazdan başlar.',
            'awaiting' => 'Hazırlık, delilleri gözden geçirmeniz için durdu.',
            'all_done' => 'Sekiz aşamanın hepsi tamamlandı.',
            'stopped_at' => 'Hazırlık «:name» aşamasında durdu.',
            'running_since' => ':time süredir',
            'just_started' => 'Az önce başladı',
            'took' => ':time sürdü',
            'instant' => 'Bir saniyeden az',
            // Her delilin kaynağıyla kendiliğinden eşleştiği, size ihtiyaç
            // duyulmayan bir gözden geçirme — süresi olmaz.
            'skipped' => 'Kararınıza gerek kalmadı',
            'decided' => 'Kararınızla kesinleşti',
            'seconds_one' => 'Bir saniye',
            'seconds_two' => 'İki saniye',
            'seconds_few' => ':count saniye',
            'seconds_many' => ':count saniye',
            'seconds_other' => ':count saniye',

            'about' => [
                'transcribing' => 'Ders metnini kaynağından çıkarıyoruz.',
                'cleaning' => 'Metni temizliyor, deşifre hatalarını düzeltiyoruz.',
                'structuring' => 'Dersin bölümlerini ve ana fikrini kuruyoruz.',
                'extracting' => 'Konuşmacının naklettiği ayet ve hadisleri ayıklıyoruz.',
                'verifying' => 'Her delili onaylı kaynağıyla eşleştiriyoruz; tahmin değil metin eşleştirmesi.',
                'review' => 'Kaynağıyla eşleşmeyen, karara bağlamanız için sizde durur.',
                'writing' => 'Özetin metnini okunur bir üslupla yazıyoruz.',
                'rendering' => 'Sayfayı kimliğinizle ve yayın dillerinizle üretiyoruz.',
            ],

            'tally' => [
                'running' => 'Şu ana kadar',
                'done' => 'Hazırlık dökümü',
                'time' => 'Süre',
                'words' => 'Ders metni',
                'evidence' => 'Deliller',
                'locales' => 'Çıktı dilleri',
                'not_started' => 'Henüz başlamadı',
                'words_pending' => 'Metin çıkarıldıktan sonra bilinir',
                'evidence_pending' => 'Deliller çıkarıldıktan sonra bilinir',
                'span' => ':from – :to',
                'span_without_review' => ':from – :to, inceleme süreniz hariç',
                'now' => 'şimdi',
            ],
        ],

        'words_one' => 'Bir kelime',
        'words_two' => 'İki kelime',
        'words_few' => ':count kelime',
        'words_many' => ':count kelime',
        'words_other' => ':count kelime',

        'nuggets_title' => 'Dersten izlenimler',
        'nuggets_hint' => 'Metin yazılmadan önce, çıkarıldığı hâliyle dersin yapısından.',
        'nuggets_waiting' => 'Dersin yapısı çıkarıldıktan sonra izlenimler burada görünür.',
        'nugget_kinds' => [
            'concept' => 'Ana fikir',
            'diagnosis' => 'Teşhis',
            'axis' => 'Bölüm',
        ],
        'nugget_position' => ':total içinden :current',
        'previous' => 'Önceki izlenim',
        'next' => 'Sonraki izlenim',
        'pause' => 'Geçişi durdur',
        'resume' => 'Geçişi sürdür',

        'evidence_none' => 'Bu dersten delil çıkarılmadı',
        'evidence_one' => 'Bir delil bulundu',
        'evidence_two' => 'İki delil bulundu',
        'evidence_few' => ':count delil bulundu',
        'evidence_many' => ':count delil bulundu',
        'evidence_other' => ':count delil bulundu',
        'locales' => 'Çıktı dilleri: :list',

        /*
         * Bitiş haberi. **Ve istenmeden olmaz**: düğme, tarayıcının ses için
         * şart koştuğu dokunuş ve bildirim izni istemenin görünür sebebidir.
         */
        'notify' => [
            'enable' => 'Bitince bana haber ver',
            'enabled' => 'Bitince size haber vereceğiz',
            'blocked' => 'Tarayıcı bildirimleri engelli; sizi bir sesle ve sekme başlığındaki bir işaretle uyaracağız.',
            'published' => '«:title» hazır',
            'published_body' => 'Özet önizlemeye hazır.',
            'marker_published' => 'Bitti',
            'review' => '«:title» gözden geçirmenizi bekliyor',
            'review_body' => 'Hazırlık, kararınızı bekleyen delillerde durdu.',
            'marker_review' => 'Sizi bekliyor',
            'failed' => '«:title» hazırlığı durdu',
            'failed_body' => 'Sebebi ve ne yapılacağını görmek için sayfayı açın.',
            'marker_failed' => 'Durdu',
        ],
    ],

    /*
     * Karusel — SCREENS.md §6, görev T-19.
     *
     * Açıkça görünmesi gereken fark, maliyet farkıdır: çizim ücretsizdir,
     * yalnızca yeni metin için harcama yapılır.
     */
    'carousel' => [
        'title' => 'Instagram slaytları',
        'subtitle' => 'Aynı özetten altı ilâ on slayt, kurumunuzun kimliğiyle.',

        'build' => 'Slaytları oluştur',
        'build_hint' => 'Dersin yapısı slaytlara yoğunlaştırılır; ayet ve hadisler kaynak lafızlarıyla eksiksiz aktarılır.',

        'rebuild' => 'Yeniden çiz',
        'rebuild_hint' => 'Aynı metinlerden güncel kimlikle yeniden çizilir; ücretsizdir ve kotanızdan düşülmez.',

        'recondense' => 'Yeni metin iste',
        'recondense_hint' => 'Metin baştan yoğunlaştırılır ve slaytlar başka bir ifadeyle çıkar. Harcama yapılan tek şey budur.',
        'recondense_confirm' => 'Mevcut slayt metinleri yeni bir ifadeyle değiştirilir. Devam edelim mi?',

        'preview' => 'Önizleme',
        'texts' => 'Slayt metinleri',
        'copy' => 'Slayt metnini kopyala',
        'copy_all' => 'Hepsini kopyala',
        'copied' => 'Kopyalandı',
        'open' => 'Sekmede aç',
        'public_url' => 'Yayımlanan bağlantı',
        'count' => ':count slayt',

        'anchored' => 'Kaynak lafzıyla',
        'anchored_hint' => 'Bu slaydın metni, doğrulanmış delilden eksiksiz aktarılmıştır ve kısaltılmaz.',

        'empty' => 'Slaytlar henüz oluşturulmadı',
        'empty_body' => 'Slaytlar hazır özetten oluşturulur ve ondan hiçbir şeyi yeniden çalıştırmaz.',

        /*
         * Alt paket, özelliği kapalı ve bir yükseltme satırıyla görür —
         * SCREENS.md §3-b. «Sahip olmadığını görmek yükseltme sebebidir;
         * gizlemek ise varlığını öğrenmeyi engeller.»
         */
        'locked' => 'Slaytlar Kurum paketi ve üzerindedir',
        'locked_body' => 'İçerik hazır ve slaytlar ondan ek maliyet olmadan çizilir. Paketinizi yükseltmek için bize ulaşın, size açılsın.',
        'locked_cta' => 'Aboneliğinizi görün',

        'blocked' => 'Slaytlar, deliller karara bağlandıktan sonra oluşturulur',
        'blocked_body' => 'İçinde karara bağlanmamış delil varken hiçbir çıktı çizilmez.',

        'rejected' => 'Slaytlar kabul edilmedi: :reason Başka bir ifade isteniyor.',
        'failed' => 'Slaytlar şu anda oluşturulamadı. Birazdan tekrar deneyin.',
    ],

    /*
     * Önizleme ve yayımlama — SCREENS.md §6, görev T-30.
     *
     * ★ **Açıkça görünmesi gereken fark, maliyet farkıdır**: yeni bir çıktı
     * eklemek kotadan düşülmez, çünkü var olan içerikten çizimdir. Yalnızca
     * yeniden üretim sayılır ve çalışmadan önce kalanı gösterir.
     */
    'preview' => [
        'title' => 'Özet önizlemesi',
        'subtitle' => 'Burada gördüğünüz, harfi harfine yayımlanacak olandır.',

        'tabs' => [
            'page' => 'Sayfa',
            'carousel' => 'Slaytlar',
            'images' => 'Görsel paketi',
        ],

        'device' => [
            'legend' => 'Önizleme ölçüsü',
            'desktop' => 'Bilgisayar',
            'tablet' => 'Tablet',
            'mobile' => 'Telefon',
        ],

        'locale' => [
            'legend' => 'Önizleme dili',
            'untranslated' => 'Henüz çevrilmedi',
            'untranslated_note' => 'Bu dil henüz çevrilmedi; çevirisi tamamlanana kadar sayfa Arapça gösterilir.',
        ],

        'quick' => [
            'legend' => 'Hızlı işlemler',
            'copy_link' => 'Bağlantıyı kopyala',
            'copied' => 'Bağlantı kopyalandı',
            'share' => 'Paylaş',
            'pdf' => 'PDF olarak dışa aktar',
            'pdf_hint' => 'Yazdırma penceresi sayfayı yayımlandığı hâliyle açar: «PDF olarak kaydet»i seçin.',
            'html' => 'HTML indir',
            'open' => 'Sekmede aç',
            'needs_publish' => 'Önce yayımlayın ki bu dilin kopyalanıp paylaşılacak bir bağlantısı olsun.',
        ],

        'publish' => 'Yayımla',
        'republish' => 'Yayımlananı güncelle',
        'publish_hint' => 'Dosya herkese açık bağlantısına yüklenir. Kotanızdan düşülmez.',
        'live' => 'Şu anda yayımda',
        'not_live' => 'Henüz yayımlanmadı',
        'open_public' => 'Herkese açık bağlantıyı aç',
        'manage' => 'Yayımlamayı yönet',

        'download' => 'İndir',
        'download_page' => 'Sayfayı indir',
        'download_carousel' => 'Slayt metinlerini indir',
        'download_hint' => 'Kendi başına yeten bir dosya: cihazınızdan ağ olmadan açılır, isterseniz kendi sitenize yükleyebilirsiniz.',

        'add_output' => 'Çıktı ekle',
        'add_carousel' => 'Slaytları oluştur',
        'free_hint' => 'Aynı içerikten çizim: maliyetsizdir ve kotanızdan düşülmez.',

        'regenerate' => 'Yeniden üret',
        'regenerate_hint' => 'Özet kaynağından yeniden oluşturulur ve kotanızdan düşülen tek şey budur.',
        'regenerate_left' => 'Bu özet için :count yeniden üretim hakkınız kaldı.',
        'regenerate_none' => 'Bu özet için izin verilen yeniden üretim haklarını tükettiniz.',
        'regenerate_confirm' => 'Özet baştan oluşturulur ve kotanızdan bir yeniden üretim düşülür. Yenisi tamamlanana kadar mevcut özet olduğu gibi kalır.',

        'blocked' => 'Önizleme, deliller karara bağlandıktan sonra görünür',
        'blocked_body' => 'İçinde karara bağlanmamış delil varken hiçbir çıktı çizilmez.',

        'not_ready' => 'Metin henüz tamamlanmadı',
        'not_ready_body' => 'Önizleme yazılmış metinden çizilir, o da henüz yazılmadı.',

        'more_title' => 'İndir, yayınla, yeniden üret',
        'open_carousel_page' => 'Slaytlar sayfası',
        'images_soon' => 'Görsel paketi henüz oluşturulmadı',
        'images_soon_body' => 'Oluşturulduğunda aynı slaytlardan, maliyetsiz ve yeniden üretimsiz çizilecek.',

        'carousel_empty' => 'Bu özet için slayt oluşturulmadı',
        'slides' => 'Instagram slaytları',
        'slide_texts' => 'Slayt metinleri',
    ],

    'published' => [
        'title' => 'Yayımlanan özet',
        'link' => 'Herkese açık bağlantı',
        'link_missing' => 'Henüz bağlantı yok — ilk yayımda oluşur.',
        'copy_link' => 'Bağlantıyı kopyala',
        'outputs' => 'Çıktılar',
        'output_missing' => 'Üretilmedi',
        'rendered_at' => 'Son çizim',
        'published_at' => 'Yayım tarihi',
        'unpublished_at' => 'Kaldırma tarihi',

        /*
         * Açılma sayacı — SCREENS.md §7, T-31'de bağlandı.
         *
         * **Ad, tam olarak neyin sayıldığını söyler.** Sayılan, ziyaretçiler
         * değil sayfa açılmalarıdır: ne çerez ne parmak izi var, dolayısıyla
         * bir okuyucuyu diğerinden ayırmanın yolu yok — **ve istemiyoruz da**.
         */
        'visits' => 'Ziyaretler',
        'visits_none' => 'Henüz açılmadı',
        'visits_recent' => 'Son otuz günde :count',
        'visits_hint' => 'Bunlar okuyucu sayısı değil sayfa açılmalarıdır: çerez yok, tarayıcı parmak izi yok ve açan hakkında hiçbir şey saklanmaz. Kendi önizlemeleriniz sayılmaz.',
        'visits_by_output' => 'bunun :count kadarı slaytlar için',

        'publish' => 'Yayımla',
        'republish' => 'Yeniden yayımla',
        'refresh' => 'Yayımlananı güncelle',
        'refresh_hint' => 'Sayfa güncel kimlik ve içerikle yeniden çizilir ve aynı dosyanın üzerine yazılır. Bağlantı değişmez.',
        'published_now' => 'Özet yayımlandı.',
        'publish_failed' => 'Şu anda yayımlanamadı. Tekrar deneyin; sürerse bize yazın.',
        'not_publishable' => 'Bu özet bu hâliyle yayımlanamaz: ya metni tamamlanmamıştır ya da içinde karara bağlanmamış bir delil vardır.',

        'unpublish' => 'Yayından kaldır',
        'unpublish_hint' => 'Sayfa ağdan kaldırılır; metin ve deliller sizde kalır, tek tuşla yeniden yayımlanır.',
        'unpublish_confirm' => 'Sayfa şimdi ağdan kaldırılır ve bağlantısı, açan herkese «bu özet kaldırıldı» sayfasını döndürür. Metin ve deliller sizde kalır, dilediğiniz zaman ücretsiz yeniden yayımlarsınız.',
        'unpublished_now' => 'Özet yayından kaldırıldı.',
        'unpublished_note' => 'Bu özet şu anda yayımda değil',
        'unpublished_body' => 'Bağlantısı, açan herkese hata sayfası değil «bu özet kaldırıldı» sayfasını döndürür. Metin ve deliller sizde olduğu gibi; dilediğiniz zaman ücretsiz yeniden yayımlayın.',

        'delete' => 'Kalıcı olarak sil',
        'delete_hint' => 'Metin, deliller ve çıktılar sizden silinir. Geri alınamaz.',
        'delete_confirm' => 'Bu özetin metni, delilleri ve çıktıları geri dönüşü olmayacak şekilde silinir. Bağlantısı, açan herkese hata sayfası değil «bu özet kaldırıldı» sayfasını döndürmeye devam eder — çünkü bağlantıyı paylaşan kişi, onun var olduğunu ve kaldırıldığını bilmeyi hak eder. Oluşturmak için harcanan kota geri verilmez.',
        'deleted_now' => 'Özet kalıcı olarak silindi.',

        'not_published_yet' => 'Bu özet henüz yayımlanmadı',
        'not_published_body' => 'Önce önizleyin, beğendiğinizde buradan yayımlayın.',
        'preview_cta' => 'Önizlemeyi aç',
    ],

    'empty' => [
        'title' => 'Henüz özet oluşturmadınız',
        'body' => 'Bize dersin bağlantısını verin; delilleri kaynağına bağlanmış ve değerlendirilmiş, yayımlanmaya ve paylaşılmaya hazır, dizilmiş bir sayfa geri verelim.',
    ],
    /*
     * Mevcut özete "Dil ekle" — T-166. Kayıtlı içerikten tek bir çeviri
     * çağrısı: yeni özet değil, kotadan da düşülmez.
     */
    'add_locale' => [
        'legend' => 'Dil ekle',
        'hint' => 'Oluşturma aşamaları yeniden çalıştırılmadan bu özetin kendisinden çevrilir ve kotanızdan düşülmez.',
        'published_hint' => 'Özet yayımlandı; yeni dil, çevirisi bitince tek başına yayımlanır.',
        'translating' => ':locale çevriliyor…',
        'translating_short' => 'Çevriliyor',
        'translating_note' => 'Bu dil şu anda çevriliyor ve bir iki dakika içinde burada görünecek. Bu ekrandan ayrılabilirsiniz.',
        'failed' => ':locale çevrilemedi',
        'retry' => 'Yeniden dene',
        'queued' => ':locale çevirisi başladı; bir iki dakika içinde görünecek.',
        'refused' => [
            'not_ready' => 'Dil, ancak özet tamamlanıp tüm delilleri netleştikten sonra eklenebilir.',
            'translating' => 'Bu dil şu anda çevriliyor.',
            'unavailable' => 'Bu dil özette zaten var ya da eklenemez.',
        ],
    ],

    // حزمةُ صور الكاروسيل — T-173.
    'images' => [
        'create' => 'Görselleri oluştur',
        'recreate' => 'Görselleri yeniden oluştur',
        'download' => 'Paketi indir',
        'empty' => 'Slayt görselleri henüz oluşturulmadı',
        'empty_body' => 'Her slayt Instagram boyutunda (1080×1350) bir görsel olarak yakalanır ve gönderi metniyle paketlenir. Ücretsizdir ve kotanızdan düşülmez.',
        'rendering' => 'Görseller oluşturuluyor…',
        'rendering_body' => 'Slaytlar gruplar halinde yakalanır; birkaç saniye sürer. Yenileri gelene dek önceki görseller kalır ve sayfa kendiliğinden güncellenir.',
        'free_hint' => 'Görselleri herhangi bir şablonla oluşturmak ve yeniden oluşturmak ücretsizdir ve kotanızdan düşülmez.',
        'ready_hint' => 'Sırasıyla numaralanmış 1080×1350 görseller ve caption.txt içinde gönderi metni.',
        'progress' => ':total görselden :done yakalandı',
        'stalled' => 'Görsel oluşturma tamamlanmadan durdu. Yeniden deneyin.',
        'failed_title' => 'Görseller oluşturulamadı',
        'needs_carousel' => 'Önce slaytları oluşturun',
        'needs_carousel_body' => 'Görseller slaytlardan yakalanır, bu yüzden önce slaytlar gelir.',
        'disabled' => 'Bu sunucuda görsel yakalama etkin değil.',
        'no_carousel' => 'Önce slaytları oluşturun; görseller onlardan yakalanır.',
        'pending' => 'Çözülmemiş :count kanıt varken görseller oluşturulmaz.',
        'overflow' => ':slides. slaytın metni bu şablonda sınırlarını aşıyor ve görselde kesilir. Başka bir şablon deneyin ya da slaytları yeniden oluşturun.',
        'capture_failed' => ':slide. slayt yakalanamadı. Biraz sonra yeniden deneyin.',
        'failed' => 'Görseller oluşturulamadı. Biraz sonra yeniden deneyin.',
        'slide_alt' => 'Slayt :slide',
    ],
];
