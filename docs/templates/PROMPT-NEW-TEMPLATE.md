# برومبت: قالبُ مخرَجٍ جديد لِخُلاصة

**متى يُستعمل:** حين تريد *شكلاً* جديداً للصفحة المنشورة — ألواناً وخطوطاً
ومسافاتٍ وحدوداً. القالبُ **ورقةُ أنماطٍ فقط**، لا بنيةَ فيه ولا محتوى.

**متى لا يُستعمل:** حين تريد *نوع محتوًى* جديداً — جدولاً، خريطةً ذهنية،
مخطّطاً، خطَّ زمن. تلك كتلةٌ لا قالب، وبرومبتُها في
[`PROMPT-NEW-BLOCK.md`](PROMPT-NEW-BLOCK.md). **ولا يستطيع قالبٌ أن يُنشئ
خريطةً ذهنية**، لأنّ القالب لا يرى إلا ما تُخرجه الكتل الموجودة.

**كيف يُستعمل:** انسخ كلّ ما تحت الخطّ إلى كلود، **وأرفق معه ملفّ
`BLOCKS-REFERENCE.html`** الذي بجانب هذا الملفّ. ثمّ غيّر «شخصية القالب»
في آخره بما تريد.

---

أنت تصمّم ورقة أنماط CSS لصفحةِ ملخّصٍ عربية تُنشر على الإنترنت. اقرأ
القيود كاملةً قبل أن تكتب حرفاً.

## ١. ما تُخرجه

**ملفّ واحد فيه CSS فقط** — لا وسوم `<style>`، ولا HTML، ولا شرحٍ قبله ولا
بعده. سيُدرَج نصُّك حرفيّاً داخل `<style>` في صفحةٍ جاهزة.

## ٢. البنية التي تُنسّقها — وهي ثابتةٌ لا تُغيّرها

الصفحة عربية `dir="rtl"` على `<html>`، وهيكلها:

```html
<body>
  <div class="wrap">
    <header class="unwan">…</header>     ← ترويسة: اسم الجهة والعنوان
    <div class="attrib">…</div>          ← المُلقي والتاريخ
    …متن الملخّص…                        ← الكتل، وهي في الملفّ المرفق
    <p class="closing">…</p>             ← جملة الختام
    <div class="colophon">…</div>        ← بيانات التوثيق
    <div class="sources">…</div>         ← المصادر
    <div class="actions">…</div>         ← أزرار (تختفي عند الطباعة)
  </div>
</body>
```

**ولا تُغيّر بنيةَ HTML ولا تقترح تغييرها.** البنية يُولّدها الخادم من JSON
محقَّق، ثمّ تمرّ على منقٍّ بقائمة سماحٍ **مغلقة**: أيّ صنفٍ خارج القائمة
أدناه **يُحذف صامتاً** قبل بلوغ الصفحة. فقاعدةٌ كتبتَها لصنفٍ مخترَع لن
تعمل، ولن يُخبرك أحدٌ بذلك.

## ٣. قائمة الأصناف — كاملةً، ولا شيء خارجها

**أصناف المتن (٦٤):**

```
sec-head · mark · rule · lead · muted · num · tag · ico
ayah-hero · ayah-no · src · sacred · hadith · imam
axis-card · axis-q · compare · good · bad · warn · calm
pair · side · trio · quad · node · step · arrow · path · paths
pillar · pillar-title · checks · panic · final · q · text
moon · night · dark · divider · dot · sep · box · note · closing
data · listing · bullets · numbered
figures-wrap · figures · figure · fig-num · fig-label
timeline · event · when · what
tree · root · branches · branch · leaf
```

**والسطور الأربعة الأخيرة مفرداتُ T-46** — الجدول والقائمة والأرقام وخطُّ
الزمن والتفريع.

**أصناف الإطار (خارج المتن):**

```
wrap · unwan · attrib · colophon · sources · actions · majlis
crest · mosque-name · mosque-latin · sub · row · v · k
links · acts · act · primary · print · draw · go · cancel
inner · toast · print-tip
```

**الوسوم المسموحة:** `div p span h2 h3 h4 ul ol li b strong i em br hr
blockquote table thead tbody tr th td small sup section article figure
figcaption label input`

**ولا شيء غيرها** — ولا `script` ولا `iframe` ولا `style=` ولا `on*=`.

## ٤. الألوان — تُستعمل ولا تُكتب

الجهةُ تختار لوحتها من ستّ لوحاتٍ جاهزة، وتُحقن متغيّراتُها **بعد** ورقتك
فتغلبُها. **فاستعمل المتغيّرات ولا تكتب لوناً صريحاً** في أيّ قاعدةٍ تتبع
الهوية، وإلّا انكسرت لوحةُ الجهة.

| المتغيّر | معناه |
|---|---|
| `--paper` `--paper-2` `--paper-3` | أرضيّات، من الأفتح إلى الأغمق |
| `--emerald` `--emerald-deep` `--emerald-soft` | اللون الأساس ودرجاته |
| `--gold` `--gold-light` | لون الإبراز |
| `--clay` `--clay-soft` | لون التنبيه |
| `--ink` `--ink-soft` | لون النصّ |
| `--rule` | لون الخطوط والحدود |

تُكتب هكذا: `color: var(--ink);`

**واكتب `:root` بقيمٍ افتراضية في أوّل ورقتك** ليعمل القالب وحده إن غابت
اللوحة.

## ٥. الخطوط المتاحة — ولا تُحمّل غيرها

محمّلةٌ سلفاً من Google Fonts، ولا تُضف `@import` ولا `<link>`:

- `'Amiri Quran'` — للقرآن وحده
- `'Amiri'` — للنصّ الشرعي والاقتباس
- `'Aref Ruqaa'` — للعناوين الكبيرة
- `'IBM Plex Sans Arabic'` — للمتن والواجهة

## ٦. قيودٌ لا تُتجاوز

1. **RTL أصيل** — استعمل `margin-inline-start` و`padding-inline-end`
   و`inset-inline` بدل `left`/`right` حيثما أمكن.
2. **الجوال أوّلاً.** أكثر القرّاء على الهاتف. اجعل الأساس للشاشة الضيّقة
   ثمّ وسّعه بـ`@media (min-width: …)`.
3. **الطباعة تعمل.** أضف `@media print`: تُخفى `.actions` و`.toast`
   و`.print-tip`، وتُضبط الألوان للورق الأبيض، ولا يُقطع قسمٌ بين صفحتين
   (`break-inside: avoid`).
4. **ملفّ واحد قائم بذاته** — لا صور خارجية ولا خطوط إضافية ولا طلبات شبكة.
   وإن أردتَ زخرفةً فبـCSS أو `data:` URI مضمَّن.
5. **الأرقام العربية الهندية** (١٢٣) تظهر في `.num` — فاختر لها خطّاً يعرضها.
6. **لا تُنسّق صنفاً ليس في القائمة**، ولا تفترض وجود عنصرٍ لم تره في الملفّ
   المرفق.

## ٧. غطِّ كلّ كتلةٍ في الملفّ المرفق

في `BLOCKS-REFERENCE.html` **مثالٌ واحدٌ من كلّ كتلةٍ يُخرجها النظام**.
مرَّ عليها واحدةً واحدة، ولا تترك واحدةً بلا تنسيق — الكتلةُ بلا قاعدةٍ
تخرج نصّاً عارياً في صفحةٍ منشورة. وهذه هي:

| الكتلة | الأصناف |
|---|---|
| رأس القسم | `.sec-head` `.mark` `h2` `.rule` |
| الافتتاح والفقرات | `p.lead` `p` `p.muted` |
| الشاهد | `.sacred` · `.sacred.dark` · `.sacred.warn` · `p.text` · `p.text.hadith` · `.src` |
| البطاقات | `.trio` `article.imam` `h3` `p` |
| المقارنة | `.compare` `article.axis-card` `.axis-q` `.pair` `.side.calm` `.side.panic` `.tag` |
| المجالات | `.quad` `.box` `h3` `span` |
| الأركان | `.pillar` `.pillar-title` `.step` `.num` `h4` `p` |
| المسارات | `.paths` `.path.good` `.path.bad` `h4` `.node` `.node.final` |
| الليل | `section.night` `h2` `p.q` `p.note` `.checks` `label` `input[type=checkbox]` |
| **الجدول** | `.data` `h3` `table` `thead` `th` `tbody` `td` `p.note` |
| **القائمة** | `.listing` `h3` `ul.bullets` `ol.numbered` `li` |
| **الأرقام** | `.figures-wrap` `.figures` `.figure` `.fig-num` `.fig-label` |
| **خطّ الزمن** | `.timeline` `.event` `.when` `.what` `h4` `p` |
| **التفريع** | `.tree` `.root` `.branches` `.branch` `h4` `.leaf` |
| الختام | `p.closing` |

**والجدول يُمرَّر أفقياً على الهاتف** — `.data{overflow-x:auto}` مع
`min-width` على `table`، وإلّا أفاض بالصفحة كلِّها.

**و`.sacred.warn` نبرةُ إنذار** — تُستعمل للضعيف المبيَّن وحده، فليكن لونها
مختلفاً عن `.sacred` العادية اختلافاً بيّناً.

## ٨. شخصية هذا القالب

<!-- ← غيّر ما تحت هذا السطر، وهو كلّ ما يتبدّل بين قالبٍ وآخر -->

**الاسم:** …

**لمن:** …

**المزاج:** …

**ثلاثة قوالبَ موجودةٌ فليكن مختلفاً عنها بيّناً:**

- **الشرعي** — ورقٌ مزخرف، ذهبٌ، خطّ أميري، حدودٌ مزيّنة. للمجالس الشرعية.
- **العصري** — أبيض، مسافاتٌ واسعة، بطاقاتٌ خفيفة، بلا زخرفة. للتعليمي والعامّ.
- **المجلّة** — تحريريّ، أقسامٌ مرقّمة، خطوطٌ رفيعة، أعمدة. للطويل والمطبوع.
