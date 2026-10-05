# Kurum sayfası {#admin-tenant}

Listede herhangi bir kurumun adına tıklayarak sayfasını açın. Aboneliği ve desteğiyle ilgili her şey buradadır.

![Kurum sayfası: 1 bu ayın kullanımı, 2 kurum sahipleri ve kurumun kimliğiyle girme, 3 paket.](shot:admin-tenant)

## Bu ayın kullanımı {#admin-tenant-usage}

Yandaki bir kart: kurumun kotasından kaç özet kullandığı, paketi, bu ay platforma maliyeti, sayfalarının okunma sayıları (30 gün / tümü) dillere göre ve abonelik tarihi.

## Paket {#admin-tenant-plan}

Khulasat'ta ödeme altyapısı yoktur: kurum banka hesabına havale yapar, siz de paketini burada uygularsınız.

1. Paketi seçin: **مجاني** (ücretsiz), **فردي** (bireysel), **أعمال** (işletme) veya **مؤسّسي** (kurumsal). Her paketin altında sınırları ve içeriyorsa «شرائح وصور» (slaytlar ve görseller) yazar.
2. **سبب التغيير** (değişikliğin sebebi) alanına havale referansını ve tutarını yazın.
3. **طبّق الباقة** (paketi uygula) düğmesine tıklayın.

Paketi uygulamak beş sınırı tek seferde doldurur. Kurumun sınırları paketiyle uyuşmuyorsa «الحدود معدَّلة عن الباقة» (sınırlar paketten farklı) yazısını görürsünüz; bir sınır istisna olarak yükseltildiğinde bu bilinçli bir durumdur.

## Kurumun sınırları {#admin-tenant-limits}

![Kurumun beş sınırı ve değişikliğin sebebi.](shot:admin-tenant-limits)

| Sınır | Anlamı |
|---|---|
| ملخّصات في الشهر (aylık özet) | Aylık kota. |
| ملخّصات في اليوم (günlük özet) | Tek bir günde başlayabilecek en fazla özet. |
| أقصى مدّة درس (en uzun ders, dakika) | Daha uzun bir ders reddedilir. |
| دقائق التفريغ الصوتي (ses deşifre dakikası) | Her ay sesten yazıya dökülebilecek miktar. |
| مرّات إعادة التوليد لكلّ ملخّص (özet başına yeniden üretim) | — |

- **Sıfır, sınırsız demektir.**
- **Değişikliğin sebebi zorunludur**: havale referansını, tutarını ve tarihini yazın. Bu, aylar sonra incelenen mali bir olaydır ve sebepsiz kaydedilmez.

## Abonelik durumu {#admin-tenant-status}

- **أوقف الاشتراك** (aboneliği durdur): kurumun yeni özet oluşturmasını engeller. **Yayımlanmış sayfaları olduğu gibi çalışmaya devam eder.**
- **أعد التفعيل** (yeniden etkinleştir): oluşturmayı yeniden açar.

Her birinin yazılan ve kaydedilen bir sebebi vardır.

## Delil modu {#admin-tenant-verification}

**Kurumdan kimse gözden geçirmeden** hangi ayet ve hadislerin yayımlanacağını belirler:

![Delil modu ve abonelik durumu.](shot:admin-tenant-modes)

- **يُنشر مع بيان درجته** (derecesi belirtilerek yayımlanır, varsayılan): kaynağı bilinen her ayet veya hadis, tahrici ve derecesiyle yayımlanır; zayıf olanlar da zayıflığı belirtilerek.
- **يقف للمراجعة** (gözden geçirme için durur): yalnızca kaynağıyla tam eşleşen ve sahih olanlar kendiliğinden yayımlanır. Geri kalan her şey kurumdan bir denetçiyi bekler; bu yüzden yayım gecikir.

Her iki modda da **kaynağı bilinmeyen her şey silinir ve yayımlanmaz.** Modu değiştirmek yazılı bir sebep gerektirir; çünkü gözden geçirmeden neyin yayımlanacağını değiştirir.

## Bu kurumun karusel şablonları {#admin-tenant-carousel}

- **تعليمات توليد القوالب لهذه الجهة** (bu kurum için şablon üretme talimatları): yalnızca bu kurum için varsayılan talimatların yerine geçen metin. Varsayılana dönmek için boş bırakın ya da düzenlemek için **ابدأ من الافتراضية** (varsayılandan başla) düğmesine tıklayın.
- Bu talimatlarla **şablon üretin** ve onları kurumun göreceği şekilde önizleyin. Üretmek, harcama tavanının koruduğu bir model çağrısıdır.

## Kurumun kimliğiyle girme {#admin-tenant-impersonate}

Teknik destek için: kurumun panelini **tam olarak sahibinin gördüğü gibi** görürsünüz.

1. **مالكو الجهة** (kurum sahipleri) kartında **ادخل بهوية الجهة** (kurumun kimliğiyle gir) düğmesine tıklayın.
2. Kurumun paneli açılır; en üstte o kurumun kimliğiyle girdiğinizi söyleyen **kırmızı bir çubuk** bulunur.
3. İşiniz bitince çubuktaki **اخرج من الهوية** (kimlikten çık) düğmesine tıklayın.

![Bir kurumun kimliğiyle girildiğinde görünen kırmızı çubuk.](shot:admin-impersonating)

> [!WARNING]
> Kurumun kimliğiyle girmişken yaptığınız her şey **onun adına yapılır**. Oturumun tamamı kayda geçer: kimin girdiği, ne zaman ve ne kadar kaldığı. Yalnızca destek için kullanın.

## Bu kurumun kaydı {#admin-tenant-audit}

Sayfanın sonunda bu kurumun denetim kaydı vardır: paketinde, sınırlarında, durumunda veya delil modunda yapılan her değişiklik ve onun kimliğiyle yapılan her giriş.

![Kurum sayfasının sonu: şablon önizlemeleri ve denetim kaydı.](shot:admin-tenant-support)
