# Doğrulama aracı {#verify}

**Doğrulama aracı** (تحقّق), herkesin **hesap açmadan ve kaydolmadan** kullanabildiği herkese açık bir sayfadır. Bir makale, hutbe veya elden ele dolaşan bir mesaj yapıştırırsınız; araç içindeki ayet ve hadisleri çıkarır, her birini kaynağıyla karşılaştırır ve neyi bulup neyi bulamadığını, nedenini de söyleyerek bildirir.

Yan menüdeki **Doğrulama aracı** bağlantısından veya Khulasat adresinin sonuna `/verify` ekleyerek açın.

> [!NOTE]
> Araç, bu kılavuzun bütün dillerinde **yalnızca Arapçadır**; çünkü incelediği metinler Arapçadır. Düğmeleri aşağıda ekranda göründükleri gibi, anlamlarıyla birlikte verilmiştir.

## Bir metni kontrol etmek {#verify-check}

1. Metni kutuya yapıştırın. Altındaki bir sayaç, en fazla karakter sayısından kaçını yazdığınızı gösterir.
2. Ya da bir kelimesi değiştirilmiş bir ayet ve aslı olmayan bir hadis içeren örnek üzerinde aracın çalışmasını görmek için **جرّب بنصٍّ جاهز** (hazır bir metinle dene) düğmesine tıklayın.
3. **تحقّق من النصّ** (metni kontrol et) düğmesine tıklayın.

![Kontrolden önce Doğrulama sayfası.](shot:verify-form)

Üç adım görünür: metinden delillerin çıkarılması, her birinin kaynağıyla eşleştirilmesi ve ardından «rapor hazır». Bu genellikle **bir dakikadan kısa** sürer.

## Raporu okumak {#verify-report}

![Doğrulama raporu: 1 her hükümdeki delil sayısı, 2 delilleri işaretlenmiş metin, 3 her delilin kartı.](shot:verify-report)

1. **Raporun üstü**: her hükümdeki delil sayısı. Yalnızca o hükmün delillerini görmek için bir hükme tıklayın.
2. **Delilleri işaretlenmiş metin**: yapıştırdığınız metin, içindeki her delil vurgulanmış hâlde. Kartına gitmek için birine tıklayın.
3. **Her delilin kartı**:
   - **Türü**: ayet, hadis, sahabe sözü veya âlim sözü.
   - **Hükmü** ve açıkça yazılmış sebebi.
   - **Metinde geçtiği hâli** ve yanında **kaynak lafzı**; ayetten düşen veya ayete eklenen kelimeler.
   - **Yeri**: sure ve ayet numarası ya da kitap ve hadis numarası.
   - **Sıhhat derecesi**, **kaynakta geçtiği şekliyle muhaddislerin hükümleri** ve **senediyle birlikte tam metin**.
   - **Ayete quran.com'da** giden bir bağlantı veya **hadisi ed-Dürerü's-Seniyye'de (Dorar.net) arama** bağlantısı.

| Hüküm | Anlamı |
|---|---|
| **مطابق** (birebir) | Kaynakta lafzıyla bulundu. |
| **قريبٌ من لفظ المصدر** (kaynak lafzına yakın) | Benzer ama aynı değil; benzerlik oranıyla birlikte. |
| **لم نجده في مصادرنا** (kaynaklarımızda bulamadık) | Mushaf'ta veya yedi kitapta yok. Bu, uydurma olduğuna dair bir hüküm değildir. |
| **لا مصدر لنوعه** (türü için kaynak yok) | Bir âlim sözü veya benzeri; karşılaştırabileceğimiz bir başvuru kaynağı yok. |

## Paylaşma {#verify-share}

- **انسخ رابط التقرير** (rapor bağlantısını kopyala): gönderdiğiniz kişide aynı raporu açan bir bağlantı.
- **انسخ التقرير نصّاً** (raporu metin olarak kopyala): bir mesaja yapıştırmak için yazılı bir kopya.
- **تحقّق من نصٍّ آخر** (başka bir metni kontrol et): kontrol sayfasına geri döner.

> [!LIMIT]
> - Kullanıcı başına **saatte {{verify_per_hour}} istek**. Bu sınıra ulaştığınızda araç kaç dakika beklemeniz gerektiğini söyler.
> - **Metin uzunluğu** {{verify_min_chars}} ile {{verify_max_chars}} karakter arasıdır. Daha uzun bir metin bölünür ve her parça ayrı kontrol edilir.
> - **Metin ve raporu {{verify_retention_days}} gün sonra silinir**; rapor bağlantısı da bundan sonra çalışmaz.
> - Yapay zekâ yalnızca metinden ayet ve hadisleri **çıkarmak** için kullanılır. **Hüküm** ise yapay zekâ olmadan, kaynaklarla yapılan metin eşleştirmesidir.
> - Rapor **fetva değildir ve bir hadis hakkında hüküm değildir**. Hadis hakkında hüküm vermek âlimlerin işidir.
