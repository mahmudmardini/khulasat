<?php

declare(strict_types=1);

return [
    'title' => 'Kullanım kılavuzu',
    'kicker' => 'Khulasat kılavuzu',
    'intro' => 'Khulasat’taki her şeyin sade bir anlatımı: ekranlar, düğmeler, sistemin yapabildikleri ve yapamadıkları. Rolünüze uygun kılavuzu seçin.',
    'choose' => 'Kılavuzunuzu seçin',
    'open' => 'Kılavuzu aç',
    'not_sure' => 'Rolünüzden emin değil misiniz? Panelinizi açıp ekranın üstündeki adınıza tıklayın; rolünüz adınızın altında görünür. Kurum panelini hiç kullanmadıysanız Kurum sahibi kılavuzuyla başlayın: üçünün en kapsamlısıdır.',

    'roles' => [
        'owner' => [
            'title' => 'Kurum sahibi kılavuzu',
            'who' => 'Khulasat’ta bir cami veya merkezden sorumlu kişi için.',
            'covers' => 'Özet oluşturma, gözden geçirme ve yayımlama; slaytlar, anlama testleri, kurum kimliği, abonelik ve ekip.',
        ],
        'editor' => [
            'title' => 'Editör kılavuzu',
            'who' => 'Özetleri hazırlayan ve gözden geçiren ekip üyesi için.',
            'covers' => 'Özet oluşturma, ayet ve hadisleri gözden geçirme, yayımlama, slaytlar, anlama testleri ve yetkinizin kapsamadığı işler.',
        ],
        'admin' => [
            'title' => 'Platform yöneticisi kılavuzu',
            'who' => 'Khulasat platformunun tamamını yöneten kişi için.',
            'covers' => 'Kurumlar ve sınırları, işler, itirazlar, gelen talepler, yapay zekâ modelleri, maliyetler ve harcama tavanı.',
        ],
    ],

    'switch_role' => 'Kılavuz',
    'language' => 'Kılavuz dili',
    'contents' => 'İçindekiler',
    'open_contents' => 'İçindekileri aç',
    'close_contents' => 'İçindekileri kapat',
    'chapter' => 'Bölüm :n',
    'to_panel' => 'Panele giriş yap',
    'all_guides' => 'Tüm kılavuzlar',
    'print' => 'Kılavuzu yazdır',
    'back_to_top' => 'Başa dön',

    'search' => [
        'label' => 'Kılavuzda ara',
        'placeholder' => 'Kılavuzda ara…',
        'shortcut' => 'Aramak için / tuşuna basın',
        'empty' => '“:q” için sonuç yok. Başka ya da daha kısa bir kelime deneyin.',
        'count' => ':count sonuç',
        'clear' => 'Aramayı temizle',
    ],

    'copy_link' => 'Bu bölümün bağlantısını kopyala',
    'copied' => 'Bölüm bağlantısı kopyalandı. Paylaşmak için istediğiniz yere yapıştırın.',
    'copy_failed' => 'Kopyalanamadı. Bağlantıyı adres çubuğundan kopyalayın.',
    'share' => 'Paylaş',

    'callouts' => [
        'note' => 'Not',
        'tip' => 'İpucu',
        'warning' => 'Dikkat',
        'limit' => 'Sistemin yapmadıkları',
    ],
];
