# Anlama testi {#quiz}

**Anlama testi**, dersin kendisinden hazırlanmış kısa sorulardır. Paylaştığınız bir bağlantıyla herkes **ad vermeden ve kaydolmadan** çözebilir. Sonuçları [Test raporları](#reports) bölümünde toplu olarak görürsünüz.

## Testi oluşturmak {#quiz-build}

İki yol:

- **Özeti oluştururken**: [Ne üretiyoruz](#create-outputs) adımında «Anlama testi» kutusunu işaretleyin.
- **Daha sonra**: özet önizlemesini, sonra **Test** sekmesini açıp **Test oluştur** düğmesine tıklayın.

Test, özetin bölümlerinden ve doğrulanmış ayet ve hadislerinden alınan 5 ile {{quiz_max_questions}} arası sorudan oluşur. **Özet kotanızdan düşülmez.**

> [!NOTE]
> Karara bağlanmamış delil içeren bir özet için test oluşturulmaz; çünkü sorular yalnızca doğrulanmış delillerden hazırlanır.

## Test sekmesi {#quiz-manage}

![Test sekmesi: 1 test bağlantısı, 2 test durumu ve katılımcının cevabı ne zaman gördüğü.](shot:preview-quiz)

- **Test bağlantısı**: **Bağlantıyı kopyala**, **Testi aç** ve **Rapor** düğmeleriyle ve şu ana kadarki deneme sayısıyla. Özet yayımlanmadan bağlantı herkese açılmaz.
- **Test durumu**:
  - **Açık**: deneme kabul eder ve özet sayfasında «Anlayışını sına» düğmesi görünür.
  - **Kapalı**: deneme kabul etmez ve düğmesi sayfadan kaybolur.
- **Katılımcı doğru cevabı ne zaman görür**: bütün soruları bitirdikten sonra **Sonda** veya **Her sorudan sonra**.

## Sorular ve düzenlenmeleri {#quiz-questions}

![Soru listesi: türü, seviyesi ve bölümü; doğru seçenek işaretli.](shot:quiz-questions)

Her sorunun:

- **Türü**: Çoktan seçmeli, Doğru mu yanlış mı veya **Delil** (katılımcı doğru ayeti veya hadisi seçer).
- **Seviyesi**: Hatırlama, Anlama veya Uygulama.
- Dersteki **bölümü**, işaretli **doğru seçeneği** ve katılımcının cevaptan sonra gördüğü **açıklaması** vardır.

Şunları yapabilirsiniz:

- **Düzenle**: sorunun metnini, seçeneklerini ve açıklamasını değiştirip **Kaydet**. «Delil» sorularının seçenekleri kaynak lafzıyla olduğu için düzenlenemez; «Doğru mu yanlış mı» sorularının iki seçeneği de sabittir.
- **Sil**: soru silinir ve sonrakiler yeniden numaralanır. Bir test {{quiz_min_questions}} sorudan az olamaz.
- **Testi yeniden oluştur**: tamamen yeni sorular, mevcut soruların ve düzenlemelerinizin yerini alır.

> [!WARNING]
> **İnsanlar testi çözmeye başladıktan sonra** sonuçlar karışmasın diye hiçbir soru silinemez ve test yeniden oluşturulamaz. Metinleri düzenlemek ise mümkün kalır.

## Katılımcı ne görür? {#quiz-public}

1. Bağlantıyı açar ve dersin başlığını, soru sayısını, yaklaşık süreyi ve **Teste başla** düğmesini görür. Ne adı ne de kendisiyle ilgili herhangi bir bilgi sorulur.
2. Soruları birer birer cevaplar; **Önceki** ve **Sonraki** ile gezinir. Her cevap seçildiği anda kaydedilir.
3. **Testi bitir** düğmesine tıklar. Cevapsız bir soru yanlış sayılır; sistem bitirmeden önce onu uyarır.
4. **Sonuç** görünür: puan, yüzde, süre ve her sorunun doğru cevabı ile dersin hangi bölümüne tekrar bakılacağını gösteren bir gözden geçirme. **Tekrar çöz** veya **Kartı yazdır** seçenekleri vardır.

> [!NOTE]
> Test sayfasının kendisi, yayın dilleri ne olursa olsun **her zaman Arapçadır** ve soruları Arapça özetten gelir.

![Katılımcının gördüğü hâliyle testin başlangıcı.](shot:quiz-start)

![Testten bir soru.](shot:quiz-question)

![Sonuç kartı ve soruların gözden geçirilmesi.](shot:quiz-result)

> [!LIMIT]
> - Test **anonimdir**: kimin cevapladığını bilmezsiniz, bir kişinin cevaplarını göremezsiniz ve cihaz adresi saklanmaz.
> - Sistem aynı kişinin testi tekrar çözmesini engellemez. Tamamlanan her deneme raporda sayılır.
> - Sertifika veya katılımcı sıralaması yoktur.
