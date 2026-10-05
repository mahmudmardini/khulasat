# İtirazlar {#admin-takedowns}

Yayımlanan her sayfanın altında **bu özetteki bir hatayı bildirme** bağlantısı vardır. Okuyucuların oradan gönderdikleri, **duyurulmuş bir cevap süresiyle** birlikte buraya ulaşır.

![İtirazlar: 1 süresi geçenler uyarısı, 2 duruma ve türe göre filtreleme, 3 itiraz kartı.](shot:admin-takedowns)

## İtiraz türleri ve süreleri {#admin-takedowns-kinds}

| Tür | Genellikle gönderen | Süre |
|---|---|---|
| **خطأ تخريج** (tahriç hatası) | Yanlış bir tahriç veya hüküm gören okuyucu. | {{evidence_complaint_days}} gün |
| **اعتراض نسبة** (nispet itirazı) | Kendisine nispet edilen sözlere itiraz eden konuşmacı. | {{takedown_hours}} saat |
| **طلب إزالة** (kaldırma talebi) | Sayfanın silinmesini isteyen kişi. | {{takedown_hours}} saat |
| **غير ذلك** (diğer) | Çalışmayan bir bağlantı gibi başka bir not. | {{evidence_complaint_days}} gün |

## İtiraz kartı {#admin-takedowns-card}

- **Tür**, **وصل** (ne zaman ulaştığı) ve **المهلة** (süre): kalan saat veya geciken saat.
- **Sayfa** ve bağlantısı, **kurum** ve sayfanın şu anki durumu: hâlâ yayımda, kaldırılmış veya «bu bağlantıya uyan bir özet yok».
- **وسيلة التواصل** (iletişim yolu) ve **نصّ الاعتراض** (itiraz metni).

## Bir itirazı karara bağlamak {#admin-takedowns-resolve}

Önce **ما يُبلَّغ به صاحب الجهة** (kurum sahibine bildirilecek metin) alanını yazın: sayfası kaldırılırsa kurumun özet ekranında göreceği metin. Onu kurum için yazın **ve içine itiraz edenin e-postasını veya şikâyet metnini koymayın**. Ardından seçin:

- **أزل الصفحة** (sayfayı kaldır): sayfanın dosyaları silinir ve yerine «Bu özet kaldırıldı» diyen bir sayfa gelir (404 değil 410 koduyla; böylece bağlantıyı açan sayfanın var olduğunu ve kaldırıldığını bilir). Sistem onay ister ve bir tıkla geri alınamaz.
- **عولجت بغير إزالة** (kaldırılmadan çözüldü): hata düzeltildi veya itiraz edene cevap verildi; sayfa kalır.
- **لا إجراء** (işlem yok): sebebini yazın; reddedilen bir itiraz da bir kaldırma gibi aylar sonra incelenir.

Her karar [denetim kaydına](#admin-audit) geçer ve bir itiraz iki kez karara bağlanamaz.

> [!LIMIT]
> - Sistem **itiraz edene kendisi cevap vermez**. Yazdığı iletişim bilgileriyle onunla siz iletişime geçersiniz.
> - Yönetici bir hatayı düzeltmek için sayfa metnini düzenleyemez. Kurum düzeltir (örneğin yeniden üreterek) veya sayfa kaldırılır.
