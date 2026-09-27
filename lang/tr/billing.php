<?php

declare(strict_types=1);

/*
 * Kotalar ve paketler — SCREENS.md ekran 9, şartname §11. T-133'te uyarlandı.
 *
 * **Token ile değil özet ile sayılır**; «token» kelimesi hiçbir müşteri
 * ekranında geçmez (T-23).
 */

return [

    'quota' => [
        'used' => 'Bu ay :limit hakkınızın :used kadarını kullandınız',
        'remaining' => ':count kaldı',
        'exhausted' => 'Bu ayın kotası bitti',
        'unlimited' => 'Sınırsız',
        'label' => 'Bu ayın kotası',
    ],

    'plan' => [
        'label' => 'Paket',
        'upgrade' => 'Paketi yükselt',
        'locked_feature' => 'Bu özellik üst pakette',
    ],

    'suspended' => [
        'title' => 'Özet üretimi durduruldu',
        'body' => 'Yayımlanmış sayfalarınız olduğu gibi çalışıyor, hiçbirine dokunulmadı. Duran şey yeni özet oluşturmaktır.',
    ],

    'page' => [
        'title' => 'Abonelik ve kullanım',
        'subtitle' => 'Bu ay neye sahipsiniz ve bundan ne kullanıldı.',

        'plan_card' => 'Paketiniz',
        'customised' => 'Sınırlarınız özel olarak ayarlandı',
        'customised_hint' => 'Sizin için bir veya daha fazla sınır paketin üzerine çıkarıldı; aşağıdaki rakamlar hesabınızın fiilen çalıştığı değerlerdir.',

        'rich_outputs' => 'Karusel ve görsel paketi',
        'rich_outputs_on' => 'Paketinize dâhil',
        'rich_outputs_off' => 'Kurum paketi ve üzerinde',

        'limits' => 'Abonelik sınırlarınız',
        'usage' => 'Bu ayın kullanımı',

        'monthly_quota' => 'Aylık özet',
        'daily_cap' => 'Günlük özet',
        'max_lecture_minutes' => 'En uzun ders',
        'transcription_minutes_quota' => 'Ses deşifre dakikası',
        'regenerations_per_summary' => 'Özet başına yeniden üretim',

        'minutes' => ':count dakika',
        'summaries' => ':count özet',
        'used_of' => ':limit içinden :used',

        'ledger' => 'Bu ayın kaydı',
        'ledger_empty' => 'Bu ay hiçbir şey kullanılmadı',
        'ledger_empty_body' => 'Oluşturduğunuz her özet ve her deşifre dakikası burada tarihiyle görünür.',
        'ledger_when' => 'Tarih',
        'ledger_what' => 'Olay',
        'ledger_summary' => 'Özet',
        'ledger_amount' => 'Sayılan',

        'events' => [
            'generate' => 'Yeni özet',
            'regenerate' => 'Yeniden üretim',
            'transcribe' => 'Ses deşifresi',
        ],

        /*
         * **Çalışmayan düğme olmaz.** Bu aşamada ödeme geçidi yok, dolayısıyla
         * yükseltme bir düğme değil bizimle bir görüşmedir — ve olmayanı vaat
         * eden bir düğme, hiç olmamasından kötüdür.
         */
        'change' => 'Paketinizi yükseltin veya sınırlarınızı değiştirin',
        'change_body' => 'Kotanızı yükseltmek veya paketinizi değiştirmek için bize ulaşın.',
    ],
];
