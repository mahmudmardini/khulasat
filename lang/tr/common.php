<?php

declare(strict_types=1);

/*
 * Ortak arayüz metinleri — SCREENS.md §genel kurallar: «her metin lang/
 * dizininden gelir. Hiçbir metin bileşenin içine yazılmaz, (Kaydet) bile».
 *
 * Hiçbir yerde emoji yok ve ton sakin kalır — pazarlama da kutlama da yok.
 * Bu, SCREENS.md'nin başındaki ikinci ilkedir ve çeviriyle değişmez (T-133).
 */

return [

    'actions' => [
        'save' => 'Kaydet',
        'cancel' => 'İptal',
        'confirm' => 'Onayla',
        'delete' => 'Sil',
        'edit' => 'Düzenle',
        'close' => 'Kapat',
        'back' => 'Geri',
        'next' => 'İleri',
        'retry' => 'Tekrar dene',
        'upload' => 'Dosya yükle',
        'browse' => 'Cihazdan seç',
        'copy' => 'Kopyala',
        'copied' => 'Kopyalandı',
        'search' => 'Ara',
        'filter' => 'Filtrele',
        'clear_filters' => 'Filtreleri kaldır',
    ],

    'nav' => [
        'index' => 'Özetler',
        'create' => 'Yeni özet',
        'verify' => 'Doğrulama aracı',
        'brand' => 'Kurum kimliği',
        'billing' => 'Abonelik',
        'team' => 'Ekip',
        'expand' => 'Menüyü genişlet',
        'collapse' => 'Menüyü daralt',
        'open_menu' => 'Menüyü aç',
        'close_menu' => 'Menüyü kapat',
        'skip_to_content' => 'İçeriğe geç',

        'section_work' => 'Çalışma',
        'section_settings' => 'Ayarlar',
    ],

    'locale' => [
        'label' => 'Panel dili',
    ],

    /* Dile göre okuma dağılımı — T-140, iki panelde de okunur. */
    'views' => [
        'by_locale' => 'Dile göre okumalar',
        'unattributed' => 'Belirtilmemiş',
        'unattributed_hint' => 'Diller ayrılmadan önce kaydedilen ya da henüz yeniden oluşturulmamış bir dosyadan gelen okumalar.',
        'recent_of_total' => '30 günde :recent · başlangıçtan beri :total',
    ],

    'roles' => [
        'owner' => 'Kurum sahibi',
        'editor' => 'Editör',
        'viewer' => 'Görüntüleyici',
    ],

    'table' => [
        'empty' => 'Eşleşen sonuç yok',
        'loading' => 'Yükleniyor',
        'sort_asc' => 'Artan sırala',
        'sort_desc' => 'Azalan sırala',
        'of' => '/',
    ],

    'state' => [
        'loading' => 'Yükleniyor',
        'saving' => 'Kaydediliyor',
        'required' => 'Zorunlu alan',
        'optional' => 'İsteğe bağlı',
        'suggested' => 'Önerilen',
    ],

    /*
     * Yıkıcı bir diyalog **tam olarak neyin kaybolacağını** söyler — §genel
     * kurallar. «Emin misiniz?» bir onay değildir, çünkü neyin gittiğini
     * söylemez.
     */
    'confirm' => [
        'title' => 'Bu işlemi onayla',
        'irreversible' => 'Bu geri alınamaz.',
    ],

    'dropzone' => [
        'prompt' => 'Dosyayı buraya bırakın ya da cihazınızdan seçin',
        'drop_now' => 'Şimdi bırakın',
        'max_size' => 'En fazla :size',
        'wrong_type' => 'Bu dosya türü kabul edilmiyor. Kabul edilenler: :types',
        'too_large' => 'Dosya izin verilen sınırdan büyük (:size).',
        'remove' => 'Dosyayı kaldır',
    ],

    'complaint' => [
        'title' => 'Hata bildirimi',
        'intro' => 'Bizdeki bir özette bir delilin tahricinde veya hükmünde hata gördüyseniz ya da konuşmacı sizseniz ve sözün size nispetine itiraz ediyorsanız bize bildirin. Hesap gerekmez.',
        'kind' => 'İtiraz türü',
        'url' => 'Sayfa bağlantısı',
        'contact' => 'İletişim yolu',
        'contact_hint' => 'Bir e-posta ya da numara; itirazınızın ne olduğunu size bildirelim.',
        'detail' => 'Ayrıntı',
        'submit' => 'Gönder',
        'sla' => 'Bu türe :hours saat içinde yanıt veriyoruz.',
        'sent' => 'İtirazınız bize ulaştı, :hours saat içinde size döneceğiz.',
    ],

    /*
     * Ürünün kendisi — **altındaki `brand` değil**: o, yayımlayan kurumun
     * kimliğidir; bu ise hazırlayan aracın kimliği.
     */
    'product' => [
        'name' => 'Khulasat',
        'rights' => '© :year',
        'slogan' => 'Kaynağına dayandırılmış özetler',
    ],

    'brand' => [
        'title' => 'Kurum kimliği',
        'subtitle' => 'Burada ayarladığınız şey, kurumunuzun yayımladığı her sayfada görünür.',
        'saved' => 'Kurum kimliği kaydedildi.',
        'preview' => 'Önizleme',
        'section_identity' => 'Ad',
        'section_palette' => 'Renk paleti',
        'section_logo' => 'Amblem',
        'section_links' => 'Bağlantılar ve bilgilendirme',
        'name_ar' => 'Arapça ad',
        'name_ar_full' => 'Tam ad',
        'name_ar_full_hint' => 'Özetin altındaki meclis künyesinde görünür.',
        'name_latin' => 'Latin harfleriyle ad',
        'logo_max' => '500 KB',
        'youtube_url' => 'YouTube kanalı bağlantısı',
        'social_url' => 'Sosyal medya sayfası bağlantısı',
        'disclaimer' => 'Bilgilendirme metni',
        'disclaimer_hint' => 'Her özetin altında görünür. Varsayılan metnin kullanılması için boş bırakın.',

        'preview_live' => 'Kaydetmeden önce her değişikliği izler',
        'preview_updating' => 'Önizleme güncelleniyor…',
        'preview_failed' => 'Önizleme güncellenemedi.',
        'unsaved' => 'Kaydedilmemiş değişiklikler',
        'logo_current' => 'Mevcut amblem',
        'logo_chosen' => 'Seçilen amblem — henüz kaydedilmedi. «Kaydet»ten sonra yeni özetlerde görünür.',

        'logo_optional' => 'İsteğe bağlı',
        'logo_remove' => 'Amblemi kaldır',
        'logo_removing' => 'Amblem «Kaydet»te kaldırılır; bundan sonra sayfalarda ve slaytlarda görünmez.',
        'logo_keep' => 'Kalsın',

        'logo_transparent' => 'Amblem arka planını saydam yap',
        'logo_transparent_hint' => 'Arkasında levha olmadan. Zaten açık renkli ve koyu başlıkta korunmaya ihtiyacı olmayan amblem için seçin.',

        'scope_note' => 'Bunlar yeni özetlerin varsayılanlarıdır. Yayımlanmış olanlar, önizlemelerinde «Yayımlananı güncelle»ye basana kadar değişmez.',
        'nav_label' => 'Kimlik bölümleri',
        'section_appearance' => 'Görünüm',
        'appearance_hint' => 'Sayfa şablonu ve renk paleti. İkisi de belirli bir özet için oluşturma ekranından değiştirilebilir.',
        'section_locales' => 'Varsayılan yayın dilleri',
        'reset' => 'Geri al',
        'leave_confirm' => 'Kurum kimliğinde kaydedilmemiş değişiklikler var ve sayfadan ayrılırsanız kaybolur. Yine de ayrılıyor musunuz?',
    ],

    'palette' => [
        'legend' => 'Kurum renk paleti',
        'selected' => 'Seçili palet',
    ],

    // قوالبُ كاروسيل الجهة — T-173.
    'carousel_designs' => [
        'title' => 'Karusel şablonları',
        'hint' => 'Kimliğinize uygun slayt görseli şablonları: sistem adınızı, paletinizi ve logonuzu okuyup üç şablon önerir, size uyanları onaylarsınız. İlk onayladığınız, sonraki her karuselin varsayılanı olur.',
        'cost_hint' => 'Şablon üretmek modeli çağırır ama özet kotanızdan düşülmez. Onaylamak ve onlarla görsel oluşturmak ücretsizdir.',
        'generate' => 'Kimliğime şablon üret',
        'regenerate' => 'Yeni şablonlar üret',
        'generating' => 'Şablonlar üretiliyor… bu bir dakika sürebilir; sayfa kendiliğinden güncellenir.',
        'failed' => 'Şablonlar üretilemedi. Biraz sonra yeniden deneyin.',
        'capped' => 'Platform bugünkü harcama sınırına ulaştı; sınır kalkana dek üretim yapılmaz.',
        'none_valid' => 'Önerilen şablonların hiçbiri geçerli değildi. Yeniden deneyin.',
        'cannot_approve' => 'En fazla :max şablon onaylanabilir. Yenisini onaylamadan önce birini kaldırın.',
        'candidates_title' => 'Önerilen — henüz onaylanmadı',
        'approved_title' => 'Onaylı',
        'default_badge' => 'Varsayılan',
        'approve' => 'Onayla',
        'discard' => 'Yoksay',
        'make_default' => 'Varsayılan yap',
        'remove' => 'Kaldır',
        'empty' => 'Henüz şablon yok. Bir şablon onaylayana dek slaytlar özgün şablonla çizilir.',
        'unnamed' => 'Şablon',
        'preview_title' => '“:name” şablonunun önizlemesi',
        'prompt_title' => 'Bu kurum için şablon üretim talimatları',
        'prompt_hint' => 'Yalnızca bu kurum için varsayılan talimatların yerini alır. Varsayılana dönmek için boş bırakın.',
        'prompt_default' => 'Varsayılan talimatlar',
        'prompt_copy_default' => 'Varsayılandan başla',
        'prompt_saved' => 'Talimatlar kaydedildi.',
        'prompt_custom' => 'Özel talimatlar',
        'prompt_using_default' => 'Varsayılan kullanılıyor',
        'choose' => 'Şablon',
        'default_original' => 'Özgün şablon',
    ],
];
