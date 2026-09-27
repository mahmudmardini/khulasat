{{--
  الهوية البصرية الثانية في الصفحة المنشورة — T-98، وT-90 للشعار.

  **ورقةٌ تُدرج بعد ورقة القالب ومفردات كتله، ولا تمسّ منهما حرفاً.**
  `_style.blade.php` منقولٌ من `khulasah.skill` بايتاً ببايت ويحرسه اختبار
  المقارنة؛ والتعديلُ البصريّ هنا بطلبٍ صريح من مالك المنتج (CLAUDE.md §2
  القاعدة الأولى) — فيُضاف فوقه كما أُضيفت مفردات T-46، لا يُكتب فيه.

  وهي مشتركةٌ بين القوالب الستّة: شعارُ الجهة وسطرُ الاعتماد وألوانُ التاج.
  ولا لونَ صريحاً: كلُّه من متغيّرات `Palette`، فتعمل اللوحات الستّ.
--}}

/* ═══ شعار الجهة في الرأس — T-90 ═══
   على لوحٍ فاتح لا على الترويسة نفسها: الشعاراتُ تُرفع داكنةً على شفافٍ
   غالباً، فتغيب على سرلوحٍ داكن. والحشوةُ حولها هامشُها المحميّ. */
.brand-mark{margin:0 0 18px;line-height:0}
/* **الارتفاعُ صريحٌ والعرضُ يتبعه.** شعارُ SVG بـ`viewBox` وحده لا قياسَ له
   في ذاته، فبـ`max-height` وحده ينطوي إلى صفرٍ داخل صفٍّ مرن. */
.brand-mark img{
  display:inline-block;box-sizing:content-box;
  height:56px;width:auto;max-width:min(220px,60vw);
  object-fit:contain;
  padding:10px 14px;
  background:var(--paper);
  border:1px solid var(--rule);
  border-radius:10px;
}
/* شعارٌ بلا لوح — T-125: بطلبٍ صريح من صاحب هويةٍ فاتحةٍ أصلاً لا تغيب
   على الترويسة الداكنة، فلا حاجةَ للوحٍ يحميها. */
.brand-mark.no-plate img{
  padding:0;background:none;border:none;border-radius:0;
}

/* ═══ الشعار بجانب اسم الجهة في البصمة ═══ */
.colophon .venue.has-logo{
  display:flex;align-items:center;justify-content:center;
  gap:12px 18px;margin-bottom:22px;
}
.colophon .venue-logo{flex:0 0 auto;line-height:0}
.colophon .venue-logo img{
  display:block;box-sizing:content-box;
  height:52px;width:auto;max-width:150px;
  object-fit:contain;
  padding:8px 10px;
  background:var(--paper);
  border:1px solid var(--rule);
  border-radius:10px;
}
.colophon .venue-logo.no-plate img{
  padding:0;background:none;border:none;border-radius:0;
}
.colophon .venue.has-logo .venue-names{min-width:0;text-align:start}
.colophon .venue.has-logo .mosque-name{margin-bottom:2px}
.colophon .venue.has-logo .mosque-latin{margin-bottom:0}
@media (max-width:640px){
  .brand-mark img{height:42px}
  /* الاسمُ الطويل لا يُعصر بجانب الشعار على الجوال: الشعارُ فوقه. */
  .colophon .venue.has-logo{flex-direction:column}
  .colophon .venue.has-logo .venue-names{text-align:center}
}

/* ═══ رابط البلاغ وحواشي التخريج — كانت سماتِ `style=` في البنية ═══ */
.colophon .complaint{margin-top:10px}
.colophon .complaint a{color:var(--gold-light)}
.sources .src-note{margin-top:14px}

/* ═══ سطر الاعتماد — الهوية §٠٥ ═══
   **لا لونَ له**: يرث `currentColor` من البصمة، فيصير فاتحاً على الداكنة
   وحبرياً على الورق، بلا سطرِ تخصيصٍ لكلّ لوحة. **ولا رمزَ فيه**: القوسان
   والنقطة مصغّرةً يُقرآن «١٠١» بجانب الكلام العربي. */
.attest{
  display:flex;flex-wrap:wrap;align-items:baseline;justify-content:center;
  gap:4px 7px;
  margin:22px 0 0;padding-top:16px;
  border-top:1px solid var(--rule);
  font-size:12.5px;line-height:1.9;
  opacity:.85;
}
.attest a{color:inherit;text-decoration:none}
.attest .wm{font-weight:600;font-size:14px;letter-spacing:.06em}
/* الكلمةُ المرسومة بخطّها وضمّتها — Reem Kufi للشعار وحده (الهوية §٠٣ و§٠٨).
   والضمّةُ بطلب مالك المنتج (T-99) ولو صغُر المقاس، فيُفسَح لها السطر. */
.attest .wm:lang(ar){
  font-family:"Reem Kufi","IBM Plex Sans Arabic",sans-serif;
  font-size:19px;line-height:1.7;letter-spacing:-.02em;
}
.attest a:hover .wm,.attest a:focus-visible .wm{
  text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:5px;
}

/* ═══ التاج المذهّب — ألوانُه من اللوحة ═══
   كان الرسمُ بذهب الزمرّدية نصّاً (#D2AC63) في كلّ لوحة. */
.crest .line{stroke:var(--gold-light)}
.crest .dot{fill:var(--gold-light)}
.crest .core{fill:var(--emerald-deep)}
.crest .gilt-hi,.crest .gilt-mid{stop-color:var(--gold-light)}
.crest .gilt-lo{stop-color:var(--gold)}
@supports (color:color-mix(in srgb,red 50%,blue)){
  /* لمعةُ التذهيب: ذهبٌ يفتح نحو الورق في أعلاه. */
  .crest .gilt-hi{stop-color:color-mix(in srgb,var(--gold-light) 55%,var(--paper))}
}
.unwan .divider{color:var(--gold)}

/* ═══ بيانات المجلس ذيلاً للسرلوح — T-105، وموضعُها T-106 ═══
   كانت سطراً مسروداً تفصله نقاط، فصارت حقائقَ لكلٍّ وسمُه فوقه وقيمتُه تحته؛
   ثمّ نُقلت إلى **داخل السرلوح** بطلب مالك المنتج — لوحٌ ثانٍ تحت البطاقة
   يُشتّت، وذيلُها يجمع.

   **ولا لونَ لها**: `currentColor` من `.inner`، فتصحّ على السرلوح الداكن
   (الشرعيّ والعصريّ) وعلى الفاتح (الدرس والبحثيّ والموجز والمجلّة) بلا
   سطرِ تخصيصٍ لكلّ قالب. والفاصلُ `--rule` — ذهبٌ شفيفٌ يُرى على الوجهين. */
/* ★ **وشريطاً غائراً يمتدّ إلى حافّتَي البطاقة** — T-108: كان داخل الحشوة
   بلا أرضٍ تميّزه فيبدو معلَّقاً.

   **والامتدادُ بإخراجه من `.inner` لا بهامشٍ سالب.** جُرّب الهامشُ السالب
   (`-26px`) فأخطأ مرّتين، وقِيس: حشوةُ `.inner` في `_style` وحده — **ولا
   يستعمل `_style` إلا الشرعيّ**، وللقوالب الخمسة أوراقُها — فجاوز الشريطُ
   حافّةَ البطاقة ٢٦ بكسل في كلّ جهة وفاضت الصفحةُ أفقياً على الجوال.
   وفي الشرعيّ بقي `max-width:640px` من `_style` فلم يمتدّ أصلاً.

   فصار الشريطُ ابناً مباشراً للسرلوح: عرضُه عرضُ البطاقة بلا حساب، و**تُطوى
   حشوتُها السفلى بـ`:has`** فينتهي عند حافّتها كما في اللقطة المرجعية. */
/* ★ **و«ألقاها» وحدها تقع وسط الشريط رأسيّاً لا في أعلاه كأختيها** — T-110،
   بلاغُ مالك المنتج بلقطة. **والعلّةُ قياسٌ لا حدس**: `.attrib{}` قديمةٌ من
   قبل T-105 — سطرٌ مسرودٌ بنقاطٍ (`.dot`) — لا تزال حيّةً في `_style` وأوراق
   القوالب الخمسة، **ولم تُحذف لأنّ القاعدة الأولى في CLAUDE.md §2 تمنع لمسها
   حرفاً**، وفيها `align-items:center`. وورقةُ الهوية لا تُعرّف `align-items`
   لعمود `.attrib` أصلاً، فيتسرّب مركَزُها القديم عبر الخاصّية التي لم
   تُعرَّف هنا — الخاصّياتُ تُحسَم واحدةً واحدة، لا الوسمَ كلَّه دفعة. فمن كان
   محتواه سطرين فقط (الملقي وحده، بلا مكانٍ فرعيّ) عام في وسط صفّه بارتفاع
   الأطول من إخوته، بدل أن يبدأ من أعلاه كهما. **قِسته**: `getBoundingClientRect`
   لعمود المُلقي مركزُه الرأسيّ ٦٤٩px يطابق مركزَ عمود المكان ٦٤٩px حرفاً —
   توسيطٌ لا خطأ قياس. `align-items:stretch` هنا يُسقط تلك القيمة المتسرّبة
   بخاصّيةٍ أعلى تخصيصاً، فيبدأ الثلاثة من حافّة الصفّ العليا معاً. */
.unwan .attrib{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
  align-items:stretch;
  gap:0;
  /* **المحورُ الكتليّ وحده** — `margin` المختصرة تصفّر المحور السطريّ،
     وورقةُ الهوية تُدرَج بعد أوراق القوالب، فتمحو ما يُلغيه قالبٌ من حشوته
     الجانبية (العصريّ). قِيس: بقي شريطُه ٥٦ بكسل أضيقَ من بطاقته. */
  margin-block:26px 0;padding:0;max-width:none;
  background:rgba(0,0,0,.14);
  border:0;border-top:1px solid var(--rule);border-radius:0;
  color:var(--paper);text-align:center;
  font-size:15px;line-height:1.75;
}
.unwan:has(> .attrib){padding-bottom:0}
/* الفاصلُ حدٌّ منطقيٌّ لا فيزيائيّ: ينقلب مع `dir` في الصفحة اللاتينية. */
.unwan .attrib .fact{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  padding:14px 12px;
  border-inline-start:1px solid var(--rule);
}
.unwan .attrib .fact:first-child{border-inline-start:0}
.unwan .attrib .k{font-size:11.5px;font-weight:400;letter-spacing:.09em;opacity:.6}
.unwan .attrib .v{font-size:15.5px;font-weight:600;line-height:1.7}
.unwan .attrib .sub{font-size:12px;letter-spacing:.03em;opacity:.6}
@media (max-width:640px){
  /* عمودٌ واحدٌ بفواصلَ أفقية — ثلاثةُ أعمدةٍ على شاشةٍ ضيّقة تعصر الأسماء.
     و`gap` يُصفَّر هنا أيضاً: في `_style` فجوةٌ للسطر المسرود، وهي في الشبكة
     تفتح شقّاً يقطع الفواصل. */
  .unwan .attrib{grid-template-columns:1fr;gap:0;font-size:14px;margin-top:22px}
  .unwan .attrib .fact{border-inline-start:0;border-top:1px solid var(--rule);padding:11px 12px}
  .unwan .attrib .fact:first-child{border-top:0}
}

/* ═══ لوحُ صانع الصفحة — T-108 ═══
   توقيعٌ في آخر الصفحة بعد المواضع. **والرمزُ على مربّعٍ مصمت وحده** — وهو
   موضعُه المجاز في §٠٥، إذ لا نصّ يجاوره داخل المربّع؛ والمنهيُّ عنه
   مصغّراً داخل سطر الكلام. والكلمةُ بخطّ الشعار وحده (§٠٣ و§٠٨). */
.maker{
  display:flex;flex-direction:column;align-items:center;gap:11px;
  margin:46px 0 0;padding:28px 0 6px;
  border-top:1px solid var(--rule);
  text-align:center;
}
.maker-lockup{display:inline-flex;align-items:center;gap:13px;text-decoration:none;color:inherit}
.maker-tile{
  display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;
  width:40px;height:40px;border-radius:11px;
  background:var(--emerald);color:var(--paper);
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.maker-tile svg{display:block;width:25px;height:25px}
/* النقطةُ ذهبٌ كما في الهوية، وهي على أرضٍ داكنة فتُرفع إلى الذهب الفاتح. */
.maker-tile .pip{fill:var(--gold-light)}
.maker .wm{
  font-family:"Reem Kufi","IBM Plex Sans Arabic",sans-serif;
  font-weight:600;font-size:34px;line-height:1.5;letter-spacing:-.02em;
  color:var(--emerald);
}
.maker-latin{margin:0;font-size:11px;letter-spacing:.2em;color:var(--ink-soft)}
/* رابطُ khulasat.io — T-109. ترث لون السطر ولا تُزخرَف: النقرُ
   وحده المضاف، بلا تغييرٍ في الحالة الساكنة. */
.maker-latin a{color:inherit;text-decoration:none}
.maker-lockup:hover .wm,.maker-lockup:focus-visible .wm{
  text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:6px;
}
@media (max-width:640px){
  .maker{margin-top:36px;padding-top:22px}
  /* والضمّةُ تبقى: الكلمةُ فوق ٣٢ بكسل في الحالين (§٠٣). */
  .maker .wm{font-size:30px}
  .maker-tile{width:34px;height:34px;border-radius:9px}
  .maker-tile svg{width:21px;height:21px}
}

@media print{
  .attest{opacity:1}
  .brand-mark img,.colophon .venue-logo img{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}

/* ═══ شريطُ لغات الملخّص — T-134، وهيئتُه من T-139 ═══
   **سطرٌ رقيقٌ فوق الغلاف**، لا بطاقةٌ ولا ترويسةٌ ثانية: المقصودُ أن يجده
   من لا يقرأ لغةَ الصفحة، لا أن يزاحم العنوان. وكلُّ لونٍ من متغيّرات
   `Palette`، فتعمل اللوحات الستّ بلا حرفٍ صريح. */
.locale-strip{
  display:flex;align-items:center;justify-content:center;flex-wrap:wrap;
  max-width:var(--maxw);
  margin:0 auto;
  padding:14px 20px 0;
  font-family:"IBM Plex Sans Arabic",sans-serif;
  font-size:12.5px;
}
.locale-strip ul{
  display:flex;align-items:center;flex-wrap:wrap;gap:8px;
  margin:0;padding:0;list-style:none;
}
/* ★ **حلقةٌ لا خطٌّ تحته — T-139.** فالسطرُ لمّا خلا من عنوانه بقيت فيه
   أسماءُ لغاتٍ مسطَّرة، وهي تُقرأ **حواشيَ نصٍّ** لا أزراراً تُضغط.
   والحلقةُ شكلٌ مغلقٌ له حدٌّ وأرضٌ وحَشوٌ، فتُقرأ زرّاً من غير لونٍ ولا
   عنوانٍ يشرحها — **والشكلُ هو المحدِّد لا اللونُ** (§إتاحة). */
.locale-strip a{
  display:inline-block;
  color:var(--emerald);
  text-decoration:none;
  padding:5px 13px;
  border:1px solid var(--paper-3);
  border-radius:999px;
  background:var(--paper-2);
  line-height:1.4;
  transition:background-color .15s ease,border-color .15s ease,color .15s ease;
}
/* والمرورُ يقلب الحلقةَ إلى زرٍّ ممتلئ: تبدُّلُ الأرض أظهرُ من تبدُّل لون
   الحرف، ولا يعتمد على تمييز درجتين متقاربتين. */
.locale-strip a:hover,.locale-strip a:focus-visible{
  background:var(--emerald);
  border-color:var(--emerald);
  color:var(--paper);
}
/* وحلقةُ التركيز خارج الحدّ لا محلَّه: من يتنقّل بالمفتاح يراها على أرضٍ
   ممتلئةٍ كما يراها على فارغة. */
.locale-strip a:focus-visible{outline:2px solid var(--gold);outline-offset:2px}

@media (max-width:640px){
  .locale-strip{padding:10px 16px 0;font-size:12px}
  .locale-strip ul{gap:6px}
  .locale-strip a{padding:4px 11px}
}

/* ★ **ولا يُطبع.** الورقةُ المطبوعة لا يُنقر فيها رابط، وسطرُ لغاتٍ في
   رأسها استهلاكُ حبرٍ في صدر وثيقةٍ أُعدّت للحفظ. */
@media print{
  .locale-strip{display:none}
}
