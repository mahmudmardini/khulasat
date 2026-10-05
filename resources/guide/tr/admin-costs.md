# Maliyet ve harcama tavanı {#admin-costs}

**الكلفة** (maliyet), platformun yapay zekâ modellerine ne kadar harcadığını **tahmini değil gerçek dolar** olarak gösterir: her aşama, model ve kurum için.

![Maliyet: 1 harcama tavanı, 2 temel rakamlar, 3 aşamalara ve modellere göre dağılım.](shot:admin-costs)

## Harcama tavanı {#admin-costs-cap}

Platformun **günde** ve **ayda** harcayabileceği üst sınır; her birinden ne kadar harcandığıyla birlikte.

- Harcama tavana ulaşırsa **bütün platformda özet oluşturma** kendiliğinden ve bütün kurumlar için birlikte **durur**; panelinizde «الطابور موقوفٌ الآن» (kuyruk şu an durduruldu) çubuğu görünür.
- **أوقف الطابور** (kuyruğu durdur): siz elle durdurursunuz (bir sağlayıcıda arıza olduğunda veya beklenmedik bir harcamada).
- **ارفعِ الوقف** (durdurmayı kaldır): oluşturma hemen yeniden başlar.

Her birinin **سبب التغيير** (değişikliğin sebebi) alanı vardır ve kayda geçer; böylece aylar sonra inceleyen kişi kuyruğun neden durdurulduğunu veya neden yeniden açıldığını bilir.

> [!NOTE]
> Tavan değerlerinin kendisi panelden değil, sunucu yapılandırmasından ayarlanır. Panel yalnızca durdurur ve durdurmayı kaldırır.

## Temel rakamlar {#admin-costs-kpis}

- **كلفة اليوم** ve **كلفة هذا الشهر** (bugünün ve bu ayın maliyeti).
- **متوسّط الملخّص** (özet başına ortalama): bir özetin ortalama maliyeti.
- **نسبة الإخفاق (آخر ساعة)** (başarısızlık oranı, son saat): son bir saatte kaç işin başarısız olduğu. Ani bir yükseliş genellikle bir sağlayıcıda sorun olduğunu gösterir.

## Dağılımlar {#admin-costs-breakdown}

![Modellere göre dağılım, aylık eğilim, video uzunluğuna ve kurumlara göre.](shot:admin-costs-breakdown)

- **Aşamalara göre**: hangi aşamanın daha pahalı olduğu (genellikle yazma).
- **Modellere ve sağlayıcılara göre**.
- **Kurumlara göre**: her kurumun maliyeti, paketi, abonelik fiyatı ve aradaki **kâr marjı** (paketin kayıtlı bir fiyatı varsa).
- **Video uzunluğuna göre**: dersin uzunluğuna göre bir özetin maliyeti.
- **Aylık eğilim**: ay ay maliyet.

> [!NOTE]
> Rakamlar yalnızca bu aya aittir. Ses deşifre maliyeti toplama ve aşama, model ve kurum dağılımlarına dâhildir; video uzunluğuna göre dağılıma dâhil değildir.
