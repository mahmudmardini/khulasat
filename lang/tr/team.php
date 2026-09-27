<?php

declare(strict_types=1);

/*
 * Ekip — SCREENS.md §10, görev T-33. T-133'te uyarlandı.
 *
 * **Ton sakin kalır — pazarlama da kutlama da yok** — ve ifade eylemi değil
 * etkisini söyler: «erişimi kaldırılır», «üyeyi sil»den daha açıktır.
 */

return [

    'title' => 'Ekip',
    'subtitle' => ':tenant paneline kim girebilir ve hangi yetkiyle.',

    'members' => 'Üyeler',
    'you' => 'Siz',

    'roles' => [
        'owner' => 'Kurum sahibi',
        'editor' => 'Editör',
        'viewer' => 'Görüntüleyici',
    ],

    'role_hints' => [
        'owner' => 'Kurum içindeki her şey: özetler, yayımlama, kimlik, abonelik ve ekip.',
        'editor' => 'Özet oluşturur, delillerini gözden geçirir ve yayımlar. Kimliğe, aboneliğe ve ekibe dokunmaz.',
        'viewer' => 'Görür; oluşturmaz, yayımlamaz.',
    ],

    'change_role' => 'Yetki',
    'role_changed' => 'Yetki değiştirildi.',

    'remove' => 'Erişimi kaldır',
    'member_removed' => 'Üyenin erişimi kaldırıldı.',
    'remove_confirm' => ':name bundan sonra kurum panelinize erişemez ve bir daha giriş yapamaz. Oluşturduğu özetler olduğu gibi kalır; onlar kuruma aittir, ona değil.',

    'invite' => 'Üye davet et',
    'invite_email' => 'Davet edilenin e-postası',
    'invite_role' => 'Yetki',
    'invite_submit' => 'Davet bağlantısı oluştur',

    'pending' => 'Henüz kabul edilmemiş davetler',
    'pending_empty' => 'Bekleyen davet yok.',
    'expires' => ':date tarihinde sona erer',
    'expired' => 'Süresi doldu',
    'invite_revoke' => 'Daveti iptal et',
    'invite_revoked' => 'Davet iptal edildi.',
    'invite_revoke_confirm' => ':email adresine gönderilen davet bağlantısı geçersiz olur ve bundan sonra hiçbir şey açmaz. Dilediğiniz zaman tekrar davet edebilirsiniz.',

    /*
     * ★ **Bağlantı elle kopyalanıp gönderilir.**
     *
     * E-posta her zaman ulaşmaz — gereksiz posta filtresi, yanlış yazılmış
     * adres, hiç ayarlanmamış bir gönderici. **Bu kurumlar e-postadan çok
     * anlık mesajlaşma kullanır.** Ve bir kez gösterildiği açıkça söylenir
     * ki kimse kopyalamadan ekranı kapatmasın.
     */
    'link_ready' => 'Davet bağlantısı hazır',
    'link_once' => 'Şimdi kopyalayıp sahibine gönderin. Bir daha gösterilmez — bizde bağlantının kendisi değil özeti saklanır. Kopyalamadan kapattıysanız daveti yeniden oluşturun.',
    'link_copy' => 'Bağlantıyı kopyala',
    'link_copied' => 'Kopyalandı',
    'link_next' => 'Davet edilen bağlantıyı açar, adını ve parolasını yazar ve seçtiğiniz yetkiyle panelinize girer. Bağlantı bir hafta çalışır, sonra sona erer.',

    'invite_cancel' => 'İptal',
    'awaiting' => 'Kabul bekleniyor',
    'role_change' => 'Yetkiyi değiştir',
    'role_change_confirm' => ':name kullanıcısının yetkisi şu andan itibaren «:from» yetkisinden «:to» yetkisine geçer. :hint',

    'roles_table' => 'Her rolün yapabildikleri',
    'ability' => 'Yetki',
    'abilities' => [
        'view' => 'Özetleri görüntüler',
        'create' => 'Özet oluşturur',
        'review' => 'Delilleri gözden geçirir',
        'publish' => 'Özetleri yayımlar',
        'brand' => 'Kurum kimliğini yönetir',
        'billing' => 'Aboneliği yönetir',
        'team' => 'Ekibi yönetir',
    ],
    'yes' => 'Evet',
    'no' => 'Hayır',

    'accept' => [
        'title' => 'Ekibe katılma',
        'subtitle' => ':tenant paneline :role yetkisiyle davet edildiniz.',
        'name' => 'Adınız',
        'password' => 'Parola',
        'confirm' => 'Tekrar yazın',
        'rules' => 'En az sekiz karakter olmalı ve daha önce sızmış yaygın bir parola olmamalı.',
        'submit' => 'Panele gir',

        'invalid' => 'Bu bağlantının süresi dolmuş veya kullanılmış',
        'invalid_body' => 'Davet bağlantıları bir hafta çalışır, sonra sona erer. Sizi davet edenden yeni bir bağlantı isteyin.',
    ],

    'welcome' => 'Hoş geldiniz. Burası kurumunuzun paneli.',

    'errors' => [
        'already_registered' => 'Bu e-postanın bizde zaten bir hesabı var. Bir kuruma bağlı olan başka bir kuruma davet edilemez.',
        'invalid_invitation' => 'Bu bağlantının süresi dolmuş veya kullanılmış. Sizi davet edenden yeni bir bağlantı isteyin.',
        /*
         * **Kurum asla sahipsiz bırakılmaz.** Son sahibi kaldırmak — ya da
         * yetkisini düşürmek — kurumu kendine kapatır: ne ekip, ne kimlik,
         * ne abonelik, ne de bizden başka bir giriş yolu kalır.
         */
        'last_owner' => 'Bu, kurumun son sahibidir. Önce başka bir üyeyi «Kurum sahibi» yapın, sonra tekrar deneyin.',
    ],
];
