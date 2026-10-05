# İşler {#admin-jobs}

Bir **iş**, bütün hazırlık aşamalarıyla tek bir özettir. **المهامّ** (işler), bütün kurumlarda platformda olan her şeyi gösterir.

![İşlerin listesi.](shot:admin-jobs)

- Duruma ve kuruma göre **filtreleme**.
- En yeniye veya en çok okunana göre **sıralama**.
- Her iş için: numarası, ders başlığı, kurum, durum, dolar olarak maliyet, okunma sayısı ve bitiş zamanı.

## İş sayfası {#admin-jobs-show}

Açmak için herhangi bir işe tıklayın.

![İş sayfası: 1 hazırlık aşamaları, 2 maliyet ve süreler, 3 okunmalar.](shot:admin-job)

- **مراحل إعداد الملخّص** (hazırlık aşamaları): kurumun gördüğü gibi, her aşamanın süresiyle.
- **الكلفة** (maliyet): toplam, kurum, deneme numarası, başlangıç ve bitiş zamanları ile ayrı ayrı **زمن الإعداد** (hazırlık süresi) ve **زمن المراجعة** (gözden geçirme süresi); böylece kurumun kararını bekleyen süre platformun hesabına yazılmaz.
- Dillere göre **القراءات** (okunmalar).
- **كلفة المراحل** (aşama maliyetleri): her model çağrısı için sağlayıcısı, adı, gönderilen ve alınan token sayısı ve maliyeti.
- **سجلّ الانتقالات** (geçiş kaydı): bir durumdan diğerine her geçiş zamanıyla ve başarısız olduysa **رمز الخطأ** (hata kodu).

## Durmuş bir işi yeniden başlatmak {#admin-jobs-retry}

Bir iş, kurumun kaynağındaki değil bizim tarafımızdaki bir arıza yüzünden durduysa **أعد من المرحلة المتوقّفة** (durduğu aşamadan yeniden başlat) düğmesine tıklayın:

- **Başarısız olan aşamadan başlayan** ve öncesindekileri (metin, yapı ve deliller) taşıyan yeni bir iş oluşturulur. Daha önce deşifre edilen hiçbir şey yeniden deşifre edilmez.
- Arıza bizde olduğu için **kurumun kotasından düşülmez**.
- Ancak **harcama tavanı** onun için de geçerlidir.

## Takılmış bir işi iptal etmek {#admin-jobs-cancel}

**ألغِ المهمّة** (işi iptal et), ilerlemeyen işler içindir. İptal kesindir.

> [!LIMIT]
> - Yalnızca **durmuş** bir iş yeniden başlatılabilir; bitmiş bir iş iptal edilemez.
> - Bir özetin delilleri buradan gözden geçirilmez. Gözden geçirme kuruma aittir; destek için [onun kimliğiyle girerseniz](#admin-tenant-impersonate) size.
