<?php

declare(strict_types=1);

/*
 * Çıktı şablonlarının adları ve açıklamaları — T-45, T-133'te uyarlandı.
 *
 * Açıklama şablonun neye benzediğini değil, KİME uygun olduğunu söyler.
 * «Tırnaklı yazı tipi ve geniş kenar boşlukları» seçim yapan içerik
 * yöneticisine yardım etmez; «uzun ders için ve kâğıttan okuyan için» eder.
 */

return [

    'label' => 'Sayfa şablonu',
    'hint' => 'Yayımlanan sayfanın görünümü. Renk paletiniz bütün şablonlarda çalışır, bu yüzden şablon değiştirmek kimliğinizi bozmaz.',
    'current' => 'Şu an seçili',
    'saved' => 'Şablon kaydedildi.',

    'classic' => [
        'label' => 'Klasik',
        'description' => 'Tezhipli zemin, altın ve Amiri yazı tipi — meclisler ve dinî dersler için.',
    ],

    'modern' => [
        'label' => 'Modern',
        'description' => 'Temiz beyaz ve hafif kartlar — öğretici dersler ve genel içerik için.',
    ],

    'journal' => [
        'label' => 'Dergi',
        'description' => 'Numaralı bölümlerle editoryal — uzun ders için ve kâğıttan okuyan için.',
    ],

    'lesson' => [
        'label' => 'Öğretici ders',
        'description' => 'Numaralı bölümler, başta ayet kutusu ve öne çıkarılmış görevler — kurs ve müfredatlı ders için.',
    ],

    'brief' => [
        'label' => 'Özet kart',
        'description' => 'Süssüz ve sıkışık ölçü — bir dakikada okunur, telefonda paylaşılır.',
    ],

    'research' => [
        'label' => 'Araştırma',
        'description' => 'Kaynak gösterimi dipnotta değil başta, deliller için yan boşluk — belgeleyen ve gözden geçiren için.',
    ],
];
