<?php

declare(strict_types=1);

/*
 * Çıktı dilleri — T-38, {@see App\Enums\Locale}, T-133'te uyarlandı.
 *
 * ★ Bunlar bir ÖZETİN yayımlandığı dillerdir — panelin dili değil; onu
 * artık her kullanıcı kendisi seçer (T-133).
 */

return [

    'label' => 'Çıktı dilleri',

    'hint' => 'Seçtiğiniz her dil için ayrı bir yayımlanmış özet oluşur ve bu, kurumunuzun varsayılanıdır — '
        .'her özet için oluşturma ekranından değiştirilebilir. Deliller her hâlükârda Arapça asıl '
        .'üzerinden doğrulanır.',

    'source_note' => 'Kaynak dil — doğrulama onun üzerinden yapılır.',

    'min' => 'En az bir dil seçin.',

    'saved' => 'Çıktı dilleri kaydedildi.',

    /*
     * ★ Meal etiketi — T-38'de bir kabul ölçütü: «yalnızca çeviriyi okuyan
     * onu hadis sanır».
     */
    'meaning_only' => 'Anlamı',

    'quran_credit' => 'Kur\'an meali',
];
