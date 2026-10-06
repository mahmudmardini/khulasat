<?php

declare(strict_types=1);

/*
 * Kullanıcıya gösterilen hata mesajları. `lang/ar/errors.php` esas alınarak
 * T-133'te uyarlandı.
 *
 * Bu mesajların yazımını iki kural yönetir:
 *   1. **Hata kodu kullanıcıya asla gösterilmez** — şartname §5-a-7. Kod
 *      kayıt ve ölçüm içindir, mesaj insan içindir.
 *   2. Her mesaj **bir eylem önerir**. «Deşifre başarısız» bir mesaj değildir,
 *      çünkü içerik yöneticisini olduğu yerde bırakır. Arızalarımızın bir
 *      kısmını kullanıcı girdinin kendisinde düzeltir, bir kısmı elle
 *      yürütülen yola geçer — mesaj hangisi olduğunu söyler.
 */

return [

    'lecture' => [

        'image_too_large' => 'Görsel boyut sınırını aşıyor. Sınır 10 MB\'dır; afişten hafif bir biçim fazlasıyla yeter.',

        'image_type' => 'Bu görsel biçimi kabul edilmiyor. PNG, JPEG veya WEBP kullanın.',

        'image_read_failed' => 'Ders bilgilerini bu görselden okuyamadık. Elle girin — aşağıdaki alanlar hazır.',

    ],

    'brand' => [

        'logo_too_large' => 'Amblem izin verilen boyutu aşıyor. Sınır 500 KB, kabul edilen biçimler PNG ve SVG.',

        'logo_type' => 'Bu dosya biçimi kabul edilmiyor. PNG veya SVG kullanın. Ambleminiz başka bir biçimdeyse dönüştürüp tekrar yükleyin.',

        'logo_unsafe' => 'Bu amblemi kabul edemedik. Tasarım programınızdan sade bir SVG olarak dışa aktarmayı deneyin ya da PNG olarak yükleyin.',

    ],

    'transcript' => [

        'host_not_allowed' => 'Bu bağlantı desteklemediğimiz bir platformdan. Şu anda YouTube bağlantılarını destekliyoruz. Ders dosyasını cihazınızdan yükleyebilir veya ders metnini doğrudan yapıştırabilirsiniz.',

        'duration_exceeded' => 'Bu dersin süresi kurumunuzun aboneliğinde izin verilen sınırı aşıyor. Dersi iki parçaya bölüp her parça için ayrı özet oluşturun ya da aboneliği yükseltin.',

        'video_unavailable' => 'Bu videoyu bulamadık. Bağlantıyı kontrol edin; platformdan silinmiş olabilir. Metin elinizdeyse doğrudan yapıştırın.',

        'video_private' => 'Bu video özel ve sahibinin hesabı dışından okunamıyor. Herkese açık ya da «listelenmemiş» yapın veya ders dosyasını cihazınızdan yükleyin.',

        'geo_blocked' => 'Bu video coğrafi olarak kısıtlı, bu yüzden erişemiyoruz. Ders dosyasını cihazınızdan yükleyin ya da ders metnini yapıştırın.',

        'bot_check' => 'Platform bu dersi okumamızı geçici olarak engelledi. Talebinizi elle yürütülen yola aktardık: ses veya video dosyasını cihazınızdan yükleyin ya da ders metnini yapıştırın. Girdiğiniz hiçbir şey kaybolmadı.',

        'no_arabic_source' => 'Bu videonun Arapça altyazısı yok. Sesini deşifre edeceğiz ve bu, kurumunuzun aboneliğindeki deşifre dakikalarından düşülür.',

        'transcription_failed' => 'Bu dersi deşifre edemedik. Tekrar deneyin; sürerse daha net bir ses dosyası yükleyin ya da ders metnini yapıştırın.',

        'transcript_too_short' => 'Çıkarılan metin :min kelimenin altında; tam bir ders olamayacak kadar kısa ve videonun altyazısı ya da sesi eksik görünüyor. Kaynağı kontrol edin ya da dersin tam metnini yapıştırın. Kotanızdan hiçbir şey düşülmedi.',

        'ytdlp_timeout' => 'Bu dersin okunması izin verilenden uzun sürdü, biz de durdurduk. Tekrar deneyin ya da ders dosyasını cihazınızdan yükleyin.',

        'media_tool_unavailable' => 'Sesi şu an işleyemiyoruz; sorun sizin dosyanızda değil, bizim tarafımızda. Biraz sonra tekrar deneyin ya da ders metnini yapıştırın.',

        'playlist_given' => 'Bu bir oynatma listesi bağlantısı, tek bir ders bağlantısı değil. Kastettiğiniz dersi açıp kendi bağlantısını kopyalayın; her dersin kendi özeti olur.',

    ],

    'quota' => [

        'monthly_quota' => 'Bu ayın özet kotası tükendi. Gelecek ayın başında yenilenir; şimdi ek özet satın alabilirsiniz.',

        'daily_cap' => 'Günlük özet sınırına ulaştınız. Yarın tekrar deneyin ya da sınır işinize dar geliyorsa paketinizi gözden geçirin.',

        'lecture_duration' => 'Bu dersin süresi aboneliğinizde izin verilen sınırı aşıyor. Dersi iki parçaya bölüp her parça için ayrı özet oluşturun ya da aboneliği yükseltin.',

        'regeneration' => 'Bu özet için yeniden üretim sınırına ulaştınız. Çıktıyı gözden geçirip elle düzenleyin ya da yeni bir özet oluşturun — bu aylık kotanızdan düşülür.',

        'transcription_minutes' => 'Aboneliğinizin bu aya ait deşifre dakikaları tükendi. Yine de ders metnini yapıştırabilir veya altyazı dosyası yükleyebilirsiniz; bunlar dakikalardan düşülmez.',

        /*
         * **Mesaj, engeli açıklamadan önce yayımlanmış olan için içini
         * rahatlatır**: «durduruldu» okuyanın aklına ilk gelen, sayfalarının
         * düştüğüdür — oysa ayakta ve eskisi gibi hizmet veriyorlar.
         */
        'suspended' => 'Aboneliğiniz durduruldu, bu yüzden şu anda yeni özet oluşturulamıyor. Yayımlanmış sayfalarınız olduğu gibi çalışıyor, hiçbirine dokunulmadı. Yeniden etkinleştirmek için bize ulaşın.',

        'spend_cap' => 'Kendi tarafımızdaki operasyonel bir inceleme için özet oluşturmayı geçici olarak durdurduk. Girdiğiniz hiçbir şey kaybolmadı; hizmet döner dönmez size haber vereceğiz.',

    ],

    /*
     * Deşifreden sonra bozulanlar — T-91.
     *
     * **Bu, ne ders kaynağıyla ne de kullanıcının yapıştırdığıyla ilgisi
     * olmayan iki arızayı kapsar**: model sağlayıcısının bozulması (kotası
     * veya bağlantısı) ve bizdeki dâhilî bir arıza. İkisi de eskiden «ders
     * kaynağını değiştirin» mesajına düşüyordu — yalnızca deşifre arızası
     * için doğru olan, gerisinde ise suçu kullanıcıya yükleyen bir mesaj.
     */
    'pipeline' => [

        'rate_limited' => 'Bu iş, kullandığımız yapay zekâ hizmetinde izin verilen sınırı geçici olarak aştı; ders kaynağınızın bununla ilgisi yok. Birkaç dakika sonra tekrar deneyin.',

        'connection_failed' => 'Kullandığımız yapay zekâ hizmetine bağlanamadık; ders kaynağınızın bununla ilgisi yok. Tekrar deneyin, bu genellikle kendiliğinden geçer.',

        'provider_error' => 'Kullandığımız yapay zekâ hizmeti geçici olarak bozuldu; ders kaynağınızın bununla ilgisi yok. Birazdan tekrar deneyin.',

        /*
         * Dâhilî arıza grubu: bizdeki ayar ya da kod. Kaynağı değiştirmek
         * bunu düzeltmez, kullanıcı da çoğu zaman düzeltemez. Mesaj bilerek
         * hepsinde aynıdır — kodları arasındaki fark kayıt içindir, kullanıcı
         * için değil. Hepsi «bu sizden değil, biz bakacağız» der.
         */
        'authentication_failed' => 'Yapay zekâ hizmetinin bizim tarafımızdaki ayarı bozuldu; ders kaynağınızın bununla ilgisi yok. Birazdan tekrar deneyin, sürerse bize ulaşın.',

        'content_rejected' => 'Yapay zekâ hizmeti bu dersin içeriğini işlemeyi reddetti. Sebebini incelememiz için bize ulaşın.',

        'fixture_broken' => 'Kendi iç ayarımızdaki bir arıza yüzünden bu işi tamamlayamadık; ders kaynağınızın bununla ilgisi yok. Lütfen bize ulaşın.',

        'model_not_configured' => 'Model ayarımızın gözden geçirilmesi gerekiyor; ders kaynağınızın bununla ilgisi yok. Bize ulaşın ya da daha sonra tekrar deneyin.',

        'provider_not_configured' => 'Model ayarımızın gözden geçirilmesi gerekiyor; ders kaynağınızın bununla ilgisi yok. Bize ulaşın ya da daha sonra tekrar deneyin.',

        'schema_validation_failed' => 'Model, hazırlığın bir aşamasında beklenen biçimde yanıt vermedi; ders kaynağınızın bununla ilgisi yok. Tekrar deneyin, bu genellikle yeniden denemeyle geçer.',

        'structure_missing' => 'Bu özet hazırlanırken dâhilî bir hata oluştu. Ders kaynağınızla da girdiğiniz hiçbir şeyle de ilgisi yok. Tekrar deneyin, sürerse bize ulaşın.',

        'transcript_missing' => 'Bu özet hazırlanırken dâhilî bir hata oluştu. Ders kaynağınızla da girdiğiniz hiçbir şeyle de ilgisi yok. Tekrar deneyin, sürerse bize ulaşın.',

        'body_missing' => 'Bu özet hazırlanırken dâhilî bir hata oluştu. Ders kaynağınızla da girdiğiniz hiçbir şeyle de ilgisi yok. Tekrar deneyin, sürerse bize ulaşın.',

        'body_empty' => 'Bu özet hazırlanırken dâhilî bir hata oluştu. Ders kaynağınızla da girdiğiniz hiçbir şeyle de ilgisi yok. Tekrar deneyin, sürerse bize ulaşın.',

        'evidence_unsettled' => 'Bu özet hazırlanırken dâhilî bir hata oluştu. Ders kaynağınızla da girdiğiniz hiçbir şeyle de ilgisi yok. Tekrar deneyin, sürerse bize ulaşın.',

        /*
         * ★ Kodu olmayan istisna — `RunSummaryPipeline::describe()`.
         * Öngörmediğimiz her arıza buraya düşer. **Bu dosyadaki en önemli
         * satır budur**: arıza kaynaktan ne kadar uzak olursa olsun eskiden
         * «ders kaynağını değiştirin» diye görünen şey buydu.
         */
        'corpus_unavailable' => 'Hadisler şu anda kaynaklarıyla karşılaştırılamadı. Sorun bizde, dersinizde değil. Hiçbir şey yayımlanmadı ve yapılan iş korunuyor. Biraz sonra tekrar deneyin.',

        'pipeline_failed' => 'Bu özet hazırlanırken dâhilî bir hata oluştu. Ders kaynağınızla da girdiğiniz hiçbir şeyle de ilgisi yok. Tekrar deneyin, sürerse bize ulaşın.',

    ],

];
