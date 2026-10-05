# Modeller {#admin-models}

Hazırlığın her aşamasını, sizin seçtiğiniz **bir yapay zekâ modeli** yürütür. **النماذج** (modeller) ekranında her aşama için bir kart vardır.

![Modeller: her aşama için bir kart ve «تعديل» (düzenle) düğmesi.](shot:admin-models)

> [!WARNING]
> **Bir modeli değiştirmek, ürünün hem kalitesini hem maliyetini değiştirir** ve dağıtım veya yeniden başlatma olmadan, sonrasında başlayan her özet için **hemen** geçerli olur. Her değişiklik sizin adınıza kaydedilir.

## Aşamalar {#admin-models-stages}

| Aşama | Ne yapar |
|---|---|
| `cleaning` | Ders metnini temizler. |
| `extracting_structure` | Ana fikri ve bölümleri çıkarır. |
| `extracting_evidence` | Metinden ayet ve hadisleri yakalar (Doğrulama aracı da kullanır). |
| `writing` | Özet metnini yazar. |
| `output_metadata` | Sayfanın başlığı, açıklaması ve bağlantısı. |
| `translating` | Özeti yayın dillerine tercüme eder. |
| `carousel` | Instagram slayt metinleri. |
| `carousel_design` | Kurumlar için karusel şablonları önerir. |
| `quiz` | Anlama testinin soruları. |
| `lecture_details` | Meclis bilgilerini dersin afişinden okur (henüz bir ekrana bağlanmadı). |

## Kartın alanları {#admin-models-fields}

- **المزوّد** ve **النموذج** (sağlayıcı ve model): şirket ve modelin adı.
- **المزوّد البديل** ve **النموذج البديل** (yedek sağlayıcı ve model): ilki arızalanırsa kullanılır.
- **أقصى مخرَج** (en fazla çıktı), **مستوى التفكير** (düşünme seviyesi), **المهلة** (zaman aşımı, saniye), **المحاولات** (deneme sayısı) ve **عند الاستنفاد** (bütün denemeler başarısız olursa ne olacağı).
- **سعر المدخل / مليون** ve **سعر المخرَج / مليون** (milyon token başına girdi ve çıktı fiyatı): sağlayıcının fiyatları; maliyetler her yerde bunlardan hesaplanır. **Sağlayıcı fiyatlarını değiştirdiğinde bunları güncelleyin**, yoksa maliyet rakamları yanlış olur.
- **فعّال** (etkin).

**تعديل** (düzenle) düğmesine tıklayın, değişikliğinizi yapın, bir denemeyse **سبب التغيير** (değişikliğin sebebi) alanını yazın (isteğe bağlı) ve kaydedin.

> [!LIMIT]
> - **Sağlayıcı anahtarları burada değildir.** Yalnızca sunucu yapılandırmasında ayarlanır ve panelde asla görünmez.
> - **Ayet ve hadislerin kontrolünde model yoktur**; bu yüzden kartı da yoktur. Sabit bir metin eşleştirmesidir.
