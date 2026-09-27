<?php

declare(strict_types=1);

/*
 * Giriş — SCREENS.md §1. `lang/ar/auth.php` esas alınarak uyarlanmıştır (T-133).
 *
 * ★ Aşağıdaki iki mesajın (`failed`, `reset.sent`) kasıtlı belirsizliği her
 * dilde korunur: bir adresin kayıtlı olup olmadığını söylemek, formu bir
 * keşif aracına çevirir.
 */

return [

    'tagline' => 'Bir ders anlatılır ve delilleri kaynağına bağlanmış, dizilmiş bir sayfa olarak geri döner; kurumunuzun adıyla yayımlanmaya hazır.',

    'title' => 'Giriş',
    'subtitle' => 'Kurumunuzun paneline girin.',

    'email' => 'E-posta adresi',
    'password' => 'Parola',
    'show_password' => 'Parolayı göster',
    'hide_password' => 'Parolayı gizle',
    'remember' => 'Oturumum açık kalsın',
    'submit' => 'Giriş yap',
    'logout' => 'Çıkış',

    'failed' => 'E-posta veya parola doğru değil.',
    'throttle' => 'Çok fazla denediniz. :seconds saniye sonra tekrar deneyin.',

    'no_signup' => 'Hesaplar kurumunuzun daveti ile açılır. Giriş yapamıyorsanız hesabınızı oluşturan kişiye başvurun.',

    'reset' => [
        'title' => 'Parola sıfırlama',
        'subtitle' => 'E-postanızı yazın, yeni parola belirlemeniz için size bir bağlantı gönderelim.',
        'submit' => 'Bağlantıyı gönder',
        'back_to_login' => 'Girişe dön',
        'link' => 'Parolanızı mı unuttunuz?',

        'sent' => 'Bu adres bizde kayıtlıysa sıfırlama bağlantısı az önce gönderildi. Ulaşmazsa gereksiz posta klasörünü kontrol edin.',

        'new_title' => 'Yeni parola',
        'new_subtitle' => 'Yeni parolanızı iki kez yazın.',
        'password' => 'Yeni parola',
        'confirm' => 'Tekrar yazın',
        'save' => 'Parolayı değiştir',
        'rules' => 'En az sekiz karakter olmalı ve daha önce sızmış yaygın bir parola olmamalı.',

        'done' => 'Parolanız değiştirildi. Şimdi onunla giriş yapın.',
        'invalid' => 'Bu bağlantının süresi dolmuş veya daha önce kullanılmış. Yeni bir tane isteyin.',

        'mail_subject' => 'Parola sıfırlama',
        'mail_greeting' => 'Merhaba :name,',
        'mail_greeting_plain' => 'Merhaba,',
        'mail_intro' => 'Hesabınızın parolasını sıfırlama talebi aldık. Yeni bir parola belirlemek için aşağıdaki düğmeye basın.',
        'mail_action' => 'Parolayı değiştir',
        'mail_expiry' => 'Bu bağlantı :minutes dakika geçerlidir, sonra süresi dolar.',
        'mail_ignore' => 'Bunu siz istemediyseniz yapmanız gereken bir şey yok: mevcut parolanız geçerli kalır ve bu bağlantı olmadan kimse onu değiştiremez.',
        'mail_fallback' => 'Düğme çalışmazsa bu bağlantıyı tarayıcınıza kopyalayın:',
    ],
];
