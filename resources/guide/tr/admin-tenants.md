# Kurumlar {#admin-tenants}

**الجهات** (kurumlar), platforma abone olan her kurumu listeler.

![Kurumların listesi.](shot:admin-tenants)

- Ada veya Latin tanımlayıcıya göre **arama**, duruma göre **filtreleme** (hepsi, etkin, durdurulmuş).
- Her kurum için: adı, Latin tanımlayıcısı, paketi, aylık kotası, özet sayısı ve durumu.
- [Sayfasını](#admin-tenant) açmak için kurumun adına tıklayın.

## Yeni bir kurum oluşturmak {#admin-tenants-create}

Khulasat'ta hesaplar davetledir; **bu yüzden her kurum buradan başlar**.

1. **جهة جديدة** (yeni kurum) düğmesine tıklayın.
2. Şunları doldurun:
   - Arapça olarak **اسم الجهة** (kurumun adı).
   - **المعرّف اللاتيني** (Latin tanımlayıcı): küçük Latin harfleri, rakamlar ve kısa çizgiler; örneğin `masjid-alhay`. **Kurumun yayımladığı her sayfanın bağlantısında görünür ve yayımdan sonra değiştirilmez.** Bazı kelimeler sisteme ayrılmıştır ve kabul edilmez (`admin`, `panel`, `verify` ve `guide` gibi).
   - **اسم المالك** ve **بريد المالك** (sahibin adı ve e-postası).
   - **وضع الشواهد** (delil modu): «يُنشر مع بيان درجته» (derecesi belirtilerek yayımlanır, varsayılan) veya «يقف للمراجعة» (gözden geçirme için durur). Bkz. [Delil modu](#admin-tenant-verification).
3. **جهة جديدة** düğmesine tıklayın.
4. Sahibin **geçici parolası** görünür.

![Yeni kurum formu.](shot:admin-tenant-new)

> [!WARNING]
> **Geçici parolayı şimdi kopyalayın ve sahibine kendiniz teslim edin.** Bir daha gösterilmez, kayda alınmaz ve sistem onu e-postayla göndermez. Sahibi ilk girişten sonra onu değiştirmelidir («Parolanızı mı unuttunuz?» yoluyla).

Yeni bir kurum **küçük deneme sınırlarıyla** başlar: ayda üç özet, en fazla 90 dakikalık dersler ve hiç ses deşifre dakikası yok. Bu yüzden paketini [sayfasından](#admin-tenant-plan) uygulayın.
