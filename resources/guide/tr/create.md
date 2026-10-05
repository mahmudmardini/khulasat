# Yeni özet oluşturmak {#create}

Yan menüde veya özet listesinin üstünde **Yeni özet** düğmesine tıklayın. Dört adımlı bir ekran açılır; yanında seçtiklerinizi toplayan ve eksikleri hatırlatan **Talebinizin özeti** kutusu bulunur.

![Yeni özet ekranı: 1 ders kaynağı, 2 Talebinizin özeti.](shot:create-source)

Dört adım:

1. [Ders kaynağı](#create-source): metnin nereden geleceği.
2. [Meclis bilgileri](#create-details): başlık, konuşmacı ve tarih.
3. [Ne üretiyoruz](#create-outputs): sayfa, slaytlar, test ve yayın dilleri.
4. [Görünüm](#create-appearance): sayfa şablonu ve renkleri.

Ardından [Hazırlamaya başla](#create-start) düğmesine tıklarsınız.

## 1. adım: ders kaynağı {#create-source}

Üç yoldan birini seçin:

### YouTube bağlantısı {#create-youtube}

1. Dersin bağlantısını yapıştırın, örneğin `https://www.youtube.com/watch?v=…`.
2. **Bağlantıyı kontrol et** düğmesine tıklayın. Sistem videonun bilgilerini **hiçbir kaynak harcamadan önce** okur ve size şunları gösterir:
   - **Başlık**: «Ders başlığı» alanı boşsa oraya yazılır ve «Önerilen» olarak işaretlenir. İsterseniz değiştirin.
   - **Süre**, dakika olarak.
   - **Videoyla gelen Arapça altyazı**: varsa sistem metni doğrudan ondan okur; bu daha hızlıdır. Yoksa sesi dinleyip yazıya döker; bu daha yavaştır.

> [!WARNING]
> Kurumunuzun daha önce kullandığı bir bağlantıyı girerseniz sistem sizi uyarır ve önceki özetin adını verir. Ondan ikinci bir özet istiyorsanız (başka bir dilde veya şablonda), «Tekrar olduğunu biliyorum» kutusunu işaretleyip yeniden tıklayın.

### Ses veya video dosyası {#create-upload}

1. Dosyayı kutuya sürükleyin veya **Cihazdan seç** düğmesine tıklayın.
2. Dosya parça parça yüklenir; ne kadarının yüklendiğini bir çubuk gösterir. İnternet kesilirse endişelenmeyin: yüklenen kısım saklanır. Kaldığı yerden sürdürmek için **Yüklemeye devam et** düğmesine tıklayın.
3. Yüklemeden sonra sistem dosyayı kontrol eder ve süresini gösterir. Alan boşsa dosyanın adı (uzantısı olmadan) önerilen ders başlığı olur.

- **En büyük boyut**: {{upload_max_mb}} MB.
- **Kabul edilen türler**: mp3, m4a, wav, aac, ogg ve opus; video olarak mp4, mov ve webm.

### Metin deşifresi {#create-text}

Dersin metni elinizdeyse kutuya yapıştırın veya bir altyazı dosyası (srt ya da vtt) ya da metin dosyası (txt) yükleyin.

![Metin yapıştırılmış «Metin deşifresi» seçeneği.](shot:create-text)

> [!LIMIT]
> **{{transcript_min_words}} kelimeden** kısa bir metin özetlenmez. Kısa bir metin genellikle eksik bir deşifre veya dersin tamamı değil bir paragrafıdır; hazırlık durur, size bunu söyler ve kotanızdan hiçbir şey düşülmez.

## 2. adım: meclis bilgileri {#create-details}

![Meclis bilgileri ve nispet biçimi.](shot:create-details)

- **Ders başlığı** ve **Konuşmacının adı** zorunludur. Konuşmacının adı sayfada tam olarak sizin yazdığınız gibi görünür; sistem kendiliğinden «Şeyh» veya «Dr.» gibi bir unvan eklemez.
- **Nispet biçimi**: dersin sayfanın üst kısmında nasıl anılacağı:
  - **Kurumun adıyla**: konuşmacının adı ve kurumunuzun adı birlikte (en yaygın olanı).
  - **Yalnızca konuşmacının adıyla**: kurum adı olmadan.
  - **Nispetsiz**: dış bir kaynaktan alınmış bir ders için; kurumunuzun adı sayfanın üst kısmında görünmez.

  Seçeneklerin altındaki bir satır, **bunun sayfada nasıl görüneceğini** gösterir.
- **İsteğe bağlı meclis bilgileri**: tıklayınca şu alanlar görünür: alt başlık, miladi tarih, hicri tarih (görünmesini istediğiniz gibi yazılır, örneğin «12 Receb 1447»), gün (miladi tarihten kendiliğinden doldurulur) ve anlatım saati (örneğin «akşam namazından sonra»).

## 3. adım: ne üretiyoruz {#create-outputs}

![Çıktılar ve yayın dilleri.](shot:create-outputs)

- **Özet**: her zaman üretilir. Paylaşılabilir bağlantısı olan, yayımlanmış bir sayfadır.
- **Instagram karuseli**: aynı özetten altı ile on arası slayt. Yalnızca bazı paketlerde vardır; kilitliyse yanında bunu görürsünüz. Bkz. [Slaytlar ve Instagram görselleri](#carousel).
- **Anlama testi**: paylaşılabilir bir bağlantıyla 5 ile {{quiz_max_questions}} arası soru. Bkz. [Anlama testi](#quiz).
- **Yayın dilleri**: Arapça her zaman; yanında İngilizce, Türkçe ve Rusça'dan dilediklerinizi seçersiniz. Her dilin kendi sayfası olur. Düğmelerin altındaki bir satır, her dilde Kur'an meallerinden hangi onaylı tercümenin kullanıldığını söyler.

> [!NOTE]
> Ayet ve hadisler her zaman Arapça aslı üzerinden karşılaştırılır. Tercüme bundan sonra gelir; hiçbir ayet veya hadis Arapça olarak karşılaştırılmadan başka bir dile geçmez. Tercüme edilmiş sayfalardaki ayetleri de **yapay zekâ çevirmez**: onlar onaylı, yayımlanmış bir mealden alınır.

## 4. adım: görünüm {#create-appearance}

Özet, **sayfa şablonunu** ve **renk paletini** [Kurum kimliği](#brand) ayarlarından kendiliğinden alır. Yalnızca bu özet için farklı bir görünüm istiyorsanız:

1. **Değiştir** düğmesine tıklayın.
2. Altı şablondan birini ve bir renk paleti seçin.
3. Seçtiğiniz görünümde örnek bir sayfa görmek için **Görünümü önizle** düğmesine tıklayın.
4. Geri dönmek için **Kurum varsayılanına dön** düğmesine tıklayın.

![Bu özet için sayfa şablonu ve renk paleti seçimi.](shot:create-appearance)

Bu değişiklik kurumunuzun ayarlarına dokunmaz; yalnızca bu özet için geçerlidir.

## Hazırlamaya başla {#create-start}

**Talebinizin özeti** kutusunda kaynağı, dersi, çıktıları, dilleri ve görünümü görürsünüz. Zorunlu bir şey eksikse «Başlamadan önce» altında görünür; örneğin «Ders kaynağı» veya «Konuşmacının adı».

1. **Hazırlamaya başla** düğmesine tıklayın.
2. Neyin düşüleceğini söyleyen bir pencere açılır; örneğin aylık kotanızdan bir özetin düşüleceği ve kaç tane kalacağı.
3. Onaylamak için bu penceredeki **Hazırlamaya başla** düğmesine, geri dönmek için **İptal** düğmesine tıklayın.

![Başlamadan önceki onay penceresi ve maliyet.](shot:create-confirm)

Ardından [Hazırlığı izleme](#progress) ekranına geçersiniz.

> [!LIMIT]
> - **Ders süresi**: her paketin dakika olarak bir azami ders süresi vardır. Daha uzun bir ders, bağlantı veya dosya kontrol edilirken reddedilir ve sizden daha kısa bir ders veya daha yüksek bir sınır istenir.
> - **Sayı**: her kurumun aylık bir kotası ve günlük bir sınırı vardır. Bunlara ulaştığınızda sınır yenilenene kadar yeni bir özet başlamaz. Bkz. [Abonelik ve kullanım](#billing).
> - **Dersin dili**: sistem Arapça verilen dersler için tasarlanmıştır.
> - **YouTube bağlantıları**: tek bir video bağlantısı olmalı, oynatma listesi değil. Gizli veya silinmiş videolar okunamaz.
