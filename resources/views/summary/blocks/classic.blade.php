{{--
  مفردات كتل T-46 بمزاج القالب الشرعي — جدولٌ وقائمةٌ وأرقامٌ وخطُّ زمنٍ وتفريع.

  **وهي ملفٌّ مستقلّ عمداً.** القاعدة الأولى في `CLAUDE.md` تمنع تعديل CSS
  قالب المهارة، و`partials/_style.blade.php` يبقى بلا مساسٍ حرفاً واحداً.
  فالمفردات الجديدة تُضاف إلى جانبه لا فوقه، ويُدرج هذا بعده في `layout`.

  ولا لونَ صريحاً هنا: كلُّه من متغيّرات `Palette`، فلوحةُ الجهة تعمل فيه.
--}}

/* ═══ جدول البيانات ═══ */
.data{margin:26px 0}
.data h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:12px}
/* الجدول أعرضُ من الهاتف غالباً، فيُمرَّر داخل غلافه ولا يُفيض بالصفحة. */
.data table{
  width:100%;border-collapse:collapse;
  background:var(--paper);border:1px solid var(--rule);border-radius:3px;
  font-size:16px;
}
.data thead{background:var(--emerald)}
.data th{
  font-family:"Amiri",serif;font-size:17px;font-weight:700;
  color:var(--paper);padding:12px 14px;text-align:start;
}
.data td{padding:11px 14px;border-top:1px solid var(--rule);color:var(--ink);line-height:1.8}
.data tbody tr:nth-child(even){background:var(--paper-2)}
.data .note{margin-top:10px;font-size:14px;color:var(--ink-soft)}

/* ═══ القائمة ═══ */
.listing{margin:24px 0}
.listing h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:10px}
.listing .bullets,.listing .numbered{margin:0;padding-inline-start:26px}
/* عربية هندية كسائر أرقام الصفحة — T-75. المولِّد لا يعرف اتجاهاً فيتبع لغته. */
.listing .numbered{list-style-type:arabic-indic}
.listing li{font-size:16.5px;line-height:1.95;margin-bottom:8px;color:var(--ink)}
.listing li::marker{color:var(--gold)}
.listing .numbered li::marker{font-family:"Amiri",serif;font-weight:700}
.listing .note{margin-top:10px;font-size:14px;color:var(--ink-soft)}

/* ═══ الأرقام البارزة ═══ */
.figures-wrap{margin:28px 0}
.figures-wrap h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:14px}
.figures{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px}
.figure{
  background:linear-gradient(160deg,var(--paper),var(--paper-2));
  border:1px solid var(--rule);
  border-radius:3px 3px 26px 26px/3px 3px 16px 16px;
  padding:20px 14px 18px;text-align:center;
}
.fig-num{
  display:block;font-family:"Amiri",serif;font-size:38px;line-height:1.2;
  color:var(--emerald);margin-bottom:6px;
}
.fig-label{display:block;font-size:14.5px;line-height:1.7;color:var(--ink-soft)}
.figures-wrap .note{margin-top:12px;font-size:14px;color:var(--ink-soft);text-align:center}

/* ═══ خطّ الزمن — رأسيٌّ ليُقرأ على الهاتف ويُطبع بلا قصّ ═══ */
.timeline{margin:28px 0;padding-top:6px}
.timeline h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:16px}
.event{display:grid;grid-template-columns:92px 1fr;gap:0;align-items:start}
.when{
  font-family:"Amiri",serif;font-size:19px;color:var(--gold);
  padding-top:2px;padding-inline-end:12px;text-align:end;
}
/* الخطّ على الحافّة الداخلية — في RTL هي اليمنى، ملاصقةً لعمود التاريخ. */
.what{
  position:relative;border-inline-start:1px solid var(--rule);
  padding-inline-start:20px;padding-bottom:22px;
}
.what::before{
  content:'';position:absolute;top:6px;inset-inline-start:-5px;
  width:9px;height:9px;border-radius:50%;
  background:var(--emerald);border:2px solid var(--paper);
}
.event:last-of-type .what{border-inline-start-color:transparent;padding-bottom:0}
.what h4{font-size:19px;color:var(--emerald-deep);margin-bottom:5px}
.what p{font-size:16px;line-height:1.85;margin:0;color:var(--ink-soft)}
.timeline .note{margin-top:12px;font-size:14px;color:var(--ink-soft)}

/* ═══ التفريع — مستويان بشبكة CSS، بلا SVG ولا مكتبة ═══ */
.tree{margin:30px 0;text-align:center}
.tree h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:14px}
.root{
  display:inline-block;
  background:var(--emerald);color:var(--paper);
  font-family:"Amiri",serif;font-size:20px;
  padding:10px 22px;border-radius:3px;margin-bottom:0;
}
.branches{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:14px;margin-top:20px;
}
/* واصلٌ رأسيّ من الجذر إلى كل فرع — حدودٌ لا رسمَ متجهيّ. */
.branch{position:relative;padding-top:16px}
.branch::before{
  content:'';position:absolute;top:0;inset-inline-start:calc(50% - 1px);
  width:1px;height:16px;background:var(--rule);
}
.branch h4{
  font-family:"Amiri",serif;font-size:18px;color:var(--emerald-deep);
  background:var(--paper-2);border:1px solid var(--rule);border-radius:3px;
  padding:9px 12px;margin-bottom:10px;
}
.leaf{
  font-size:15px;line-height:1.75;color:var(--ink-soft);
  background:var(--paper);border:1px solid var(--rule);border-radius:3px;
  padding:8px 10px;margin-bottom:7px;
}
.tree .note{margin-top:12px;font-size:14px;color:var(--ink-soft)}
/* فرعٌ داخل فرعٍ — T-72. يتراجع قليلاً ويتّخذ حدّاً جانبياً فيُقرأ تابعاً
   لا شقيقاً، بلا صنفٍ جديد: نفس .branch متداخلاً. */
.branch .branch{
  margin-top:10px;padding-top:0;background:none;
  border-inline-start:2px solid var(--rule);padding-inline-start:12px;
}
.branch .branch::before{display:none}
.branch .branch h4{font-size:16px;background:none;border:none;padding:0 0 6px}

/* ═══ الهرم — طبقاتٌ يتدرّج عرضها بترتيبها لا بصنفٍ، T-72 ═══ */
.pyramid{margin:30px 0}
.pyramid h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:14px;text-align:center}
.tiers{display:flex;flex-direction:column;align-items:center;gap:8px}
.tier{
  width:100%;box-sizing:border-box;text-align:center;
  background:var(--paper-2);border:1px solid var(--rule);border-radius:3px;
  padding:12px 16px;
}
.tier h4{font-family:"Amiri",serif;font-size:18px;color:var(--emerald-deep);margin-bottom:4px}
.tier p{font-size:15px;line-height:1.75;color:var(--ink-soft);margin:0}
/* أوّل الطبقات قمّةُ الهرم وأضيقُها، وآخرُها قاعدتُه. */
.tiers .tier:nth-child(1){max-width:40%;background:var(--emerald);border-color:var(--emerald)}
.tiers .tier:nth-child(1) h4,.tiers .tier:nth-child(1) p{color:var(--paper)}
.tiers .tier:nth-child(2){max-width:60%}
.tiers .tier:nth-child(3){max-width:80%}
.tiers .tier:nth-child(n+4){max-width:100%}
.pyramid .note{margin-top:12px;font-size:14px;color:var(--ink-soft);text-align:center}

/* ═══ العجلة — حلقةٌ متدرّجة الامتلاء بعشر درجاتٍ ثابتة، T-72 ═══ */
.gauge{margin:30px 0;text-align:center}
.gauge h3{font-family:"Aref Ruqaa",serif;font-size:22px;color:var(--emerald);margin-bottom:14px}
.ring{
  --track:var(--rule);--fill:var(--emerald);
  width:120px;height:120px;border-radius:50%;margin:0 auto;
  display:flex;align-items:center;justify-content:center;
  background:conic-gradient(var(--fill) 0%,var(--track) 0%);
}
.ring .reading{
  width:92px;height:92px;border-radius:50%;background:var(--paper);
  display:flex;align-items:center;justify-content:center;
  font-family:"Amiri",serif;font-size:30px;color:var(--emerald-deep);
}
.ring.fill-0{background:conic-gradient(var(--fill) 0%,var(--track) 0%)}
.ring.fill-10{background:conic-gradient(var(--fill) 10%,var(--track) 10%)}
.ring.fill-20{background:conic-gradient(var(--fill) 20%,var(--track) 20%)}
.ring.fill-30{background:conic-gradient(var(--fill) 30%,var(--track) 30%)}
.ring.fill-40{background:conic-gradient(var(--fill) 40%,var(--track) 40%)}
.ring.fill-50{background:conic-gradient(var(--fill) 50%,var(--track) 50%)}
.ring.fill-60{background:conic-gradient(var(--fill) 60%,var(--track) 60%)}
.ring.fill-70{background:conic-gradient(var(--fill) 70%,var(--track) 70%)}
.ring.fill-80{background:conic-gradient(var(--fill) 80%,var(--track) 80%)}
.ring.fill-90{background:conic-gradient(var(--fill) 90%,var(--track) 90%)}
.ring.fill-100{background:conic-gradient(var(--fill) 100%,var(--track) 100%)}
.poles{display:flex;justify-content:space-between;max-width:220px;margin:12px auto 0;gap:10px}
.pole{font-size:14px;color:var(--ink-soft)}
.gauge .note{margin-top:10px;font-size:14px;color:var(--ink-soft)}

/* ═══ تعريف المصطلح — لغةً واصطلاحاً، T-76 ═══ */
.gloss{margin:30px 0;border-inline-start:3px solid var(--gold);padding-inline-start:1rem}
.gloss-head{display:flex;align-items:baseline;gap:.6rem;margin-bottom:10px}
.gloss-term{font-family:"Amiri",serif;font-size:20px;color:var(--emerald-deep)}
.gloss-root{font-size:13px;color:var(--ink-soft);letter-spacing:.12em}
.gloss-body{display:grid;grid-template-columns:max-content 1fr;column-gap:.9rem;row-gap:.55rem}
.gloss-label{font-size:14px;color:var(--ink-soft)}
.gloss-text{font-size:16px;line-height:1.85;color:var(--ink);margin:0}
.gloss .note{margin-top:12px;font-size:14px;color:var(--ink-soft)}

/* ═══ سؤالٌ وجوابه — شارةٌ عربية هندية بعدّاد CSS، T-76 ═══ */
.qa{margin:28px 0;counter-reset:qa}
.qa-item{counter-increment:qa;padding-block:12px;border-block-end:1px solid var(--rule)}
.qa-item:last-child{border-block-end:0}
.qa-q{font-weight:700;color:var(--emerald-deep);margin-bottom:6px}
.qa-a{color:var(--ink)}
.qa-q::before,.qa-a::before{
  display:inline-grid;place-items:center;vertical-align:middle;
  inline-size:1.9em;block-size:1.9em;border-radius:50%;
  margin-inline-end:.6rem;font-size:.75em;
}
.qa-q::before{content:"س"counter(qa,arabic-indic);background:var(--emerald);color:var(--paper)}
.qa-a::before{content:"ج";border:1px solid var(--rule);color:var(--ink-soft)}

/* ═══ فائدة أو تنبيه جانبي، T-76 ═══ */
.cnote{margin:22px 0;display:grid;grid-template-columns:max-content 1fr;column-gap:.8rem;
  padding:14px 16px;border-inline-start:4px solid var(--rule);background:var(--paper-2)}
.cn-mark{font-size:18px;line-height:1}
.cn-benefit{border-inline-start-color:var(--emerald)}
.cn-benefit .cn-mark::before{content:"✺"}
.cn-warning{border-inline-start-color:var(--gold)}
.cn-warning .cn-mark::before{content:"!"}
.cn-subtle{border-inline-start-color:var(--rule)}
.cn-subtle .cn-mark::before{content:"·"}
.cn-title{font-family:"Amiri",serif;font-size:16px;color:var(--emerald-deep);margin:0 0 4px}
.cn-text{font-size:15px;line-height:1.8;color:var(--ink-soft);margin:0}

/* ═══ ما يُظنّ مقابل الصواب، T-76 ═══ */
.mfix{margin:28px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.mfix-claim,.mfix-fix{padding:14px 16px}
.mfix-claim{border:2px dashed var(--rule);color:var(--ink-soft)}
.mfix-fix{border:2px solid var(--emerald);background:var(--paper-2)}
.mfix-tag{display:block;font-size:12px;font-weight:700;letter-spacing:.06em;margin-bottom:6px}
.mfix-claim .mfix-tag{color:var(--ink-soft)}
.mfix-fix .mfix-tag{color:var(--emerald-deep)}
.mfix p{margin:0;font-size:15px;line-height:1.8}
.mfix-evidence{grid-column:1/-1;margin:10px 0 0;font-size:14px;color:var(--ink-soft)}

/* ═══ بطاقة التطبيق — T-79 ═══
   تدرّجُها في `_style` صريحٌ بلون الزمرّدية (#123028 → #0A1F19)، فتبقى
   خضراءَ في كلّ لوحة بين ترويسةٍ وكولوفونٍ بلون الجهة. فيُقرأ من اللوحة،
   والزمرّديةُ تحمل القيمتين نفسيهما حرفاً فلا يتبدّل مخرَجُها. */
.night{background:linear-gradient(170deg,var(--night),var(--night-deep))}
.checks input:checked::after{color:var(--night-deep)}
/* تحت عنوان قسمها يكفي هامشُ العنوان — و`margin-top:64px` فوقه فجوةٌ عريضة. */
.sec-head + .night{margin-top:0}
/* الملاحظةُ والمهامُّ بعرضٍ واحد: كانت الملاحظةُ بعرض البطاقة كلّها والمهامُّ
   عموداً من ٣٣٠px تحتها، فيتكسّر البندُ الطويل أسطراً أربعة. */
.night .note{max-width:34em;margin-inline:auto}
/* و`text-align:right` في `_style` يدفع البندَ الإنجليزيّ إلى الطرف الخطأ؛
   و`start` يمينٌ في العربية كما كان، ويسارٌ في اللاتينية. */
.checks{max-width:34em;text-align:start}

/* ═══ ترجمةُ المعنى تحت الشاهد — T-80 ═══
   `.sacred .src` سطرُ تخريجٍ قصير: ذهبيٌّ صغيرٌ ثقيل. وترجمةُ الآية ومعنى
   الحديث فقرةٌ تُقرأ، فتُكتب بلون المتن ووزنه، ويبقى وسمُها ذهبياً. */
.sacred p.src{color:var(--ink-soft);font-weight:400;font-size:15px}
.sacred p.src em{color:var(--gold);font-weight:500}

/* ═══ قائمة التخريج — T-81 ═══
   الموضعُ أوّلاً ثمّ طرفُ الشاهد، والموضعُ رابطٌ إلى الآية حيث وُجد. */
.sources .ref{font-weight:500;color:var(--emerald);text-decoration:none}
.sources a.ref:hover,.sources a.ref:focus-visible{text-decoration:underline;text-underline-offset:3px}
.sources .excerpt{font-family:"Amiri",serif;font-size:1.06em;margin-inline-start:.5em}

/* ═══ وسما السؤال والجواب بلسان الصفحة — T-87 ═══
   «س» و«ج» ثابتان في `content`، فيخرجان عربيّين في كلّ لغة. */
html[lang="en"] .qa-q::before{content:"Q" counter(qa)}
html[lang="en"] .qa-a::before{content:"A"}
html[lang="tr"] .qa-q::before{content:"S" counter(qa)}
html[lang="tr"] .qa-a::before{content:"C"}
html[lang="ru"] .qa-q::before{content:"В" counter(qa)}
html[lang="ru"] .qa-a::before{content:"О"}

/* ═══ الاتّجاه في الصفحة غير العربية — T-87 ═══
   في `_style` اتّجاهاتٌ فيزيائية تصحّ في العربية وتنقلب في اللاتينية، فيُعلى
   عليها هنا بخصائص منطقية **تطابق العربيةَ حرفاً** وتنقلب مع `dir`. */
.sacred{border-right:1px solid var(--rule);border-inline-start:4px solid var(--gold)}
.sacred.warn{border-right-color:var(--rule);border-inline-start-color:var(--clay)}
.side + .side{border-right:0;border-inline-start:1px solid var(--rule)}
.majlis{text-align:start}
.sources ol{padding-right:0;padding-inline-start:20px}
.print-tip ol{padding-right:0;padding-inline-start:20px}
[dir="ltr"] .sec-head .rule{background:linear-gradient(to right,var(--rule),transparent)}
@media (max-width:640px){
  .side + .side{border-inline-start:0}
}

/* ═══ الهاتف ═══ */
@media (max-width:640px){
  /* الجدول وحده يُمرَّر أفقياً؛ وبقيّة الصفحة لا تفيض. */
  .data{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .data table{min-width:420px}
  .event{grid-template-columns:70px 1fr}
  .when{font-size:17px}
  .fig-num{font-size:32px}
  .branches{grid-template-columns:1fr 1fr;gap:10px}
  .ring{width:100px;height:100px}
  .ring .reading{width:76px;height:76px;font-size:25px}
  .gloss-body{grid-template-columns:1fr}
}

/* ═══ الطباعة — ولا تُقطع كتلةٌ بين صفحتين ═══ */
@media print{
  .data,.listing,.figures-wrap,.timeline,.tree,.pyramid,.gauge,
  .gloss,.qa,.cnote,.mfix{break-inside:avoid;page-break-inside:avoid}
  .event,.figure,.branch,.tier,.qa-item,.mfix-claim,.mfix-fix{break-inside:avoid;page-break-inside:avoid}
  .data table{border-color:#999}
  .data thead{background:#EEE}
  .data th{color:#000}
  .data td{border-top-color:#CCC}
  .root{background:#EEE;color:#000;border:1px solid #999}
  .figure{background:none;border-color:#CCC}
  .tiers .tier:nth-child(1){background:#EEE;border-color:#999}
  .tiers .tier:nth-child(1) h4,.tiers .tier:nth-child(1) p{color:#000}
  .ring{--track:#CCC;--fill:#666}
  .cnote{background:none}
  .qa-q::before{background:#DDD;color:#000;border:1px solid #999}
  .qa-a::before{border-color:#999}
  .mfix-fix{background:none}
}

/* ═══ الهوية البصرية الثانية — T-98 ═══
   بطلب مالك المنتج الصريح (CLAUDE.md §2 القاعدة الأولى)، **فوق** `_style`
   لا فيه: الورقةُ المنقولة تبقى بايتاً ببايت، ويحرسها اختبار المقارنة. */

/* ── نمط الخلفية: شبكةٌ نجمية ثمانية متّصلة ──
   كان نجمةً منفردة كلّ ٨٤ بكسل بذهب الزمرّدية نصّاً. وصار شبكةً هندسية:
   نجمةٌ من مربّعين متداخلين تصلها بجاراتها خيوطٌ مستقيمة، وعند كلّ ملتقى
   أربعٍ معيّنٌ صغير. **والرسمُ قناعٌ واللونُ من اللوحة**: الصورة في `url()`
   لا ترى متغيّرات CSS، والقناعُ يُلبسها ذهبَ الجهة. والشفافيةُ أخفض من
   كثافة الشبكة، فتُحسّ ولا تزاحم المتن. */
body::before{
  background-image:none;
  background-color:var(--gold);
  -webkit-mask-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'><g fill='none' stroke='black' stroke-width='1'><path d='M36 19L40.98 23.98L48.02 23.98L48.02 31.02L53 36L48.02 40.98L48.02 48.02L40.98 48.02L36 53L31.02 48.02L23.98 48.02L23.98 40.98L19 36L23.98 31.02L23.98 23.98L31.02 23.98Z'/><circle cx='36' cy='36' r='6.5'/><path d='M53 36H72M0 36H19M36 0V19M36 53V72M48.02 48.02L69 69M23.98 23.98L3 3M48.02 23.98L69 3M23.98 48.02L3 69'/><path d='M0 -6L6 0L0 6L-6 0ZM72 -6L78 0L72 6L66 0ZM0 66L6 72L0 78L-6 72ZM72 66L78 72L72 78L66 72Z'/></g></svg>");
  mask-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='72' height='72' viewBox='0 0 72 72'><g fill='none' stroke='black' stroke-width='1'><path d='M36 19L40.98 23.98L48.02 23.98L48.02 31.02L53 36L48.02 40.98L48.02 48.02L40.98 48.02L36 53L31.02 48.02L23.98 48.02L23.98 40.98L19 36L23.98 31.02L23.98 23.98L31.02 23.98Z'/><circle cx='36' cy='36' r='6.5'/><path d='M53 36H72M0 36H19M36 0V19M36 53V72M48.02 48.02L69 69M23.98 23.98L3 3M48.02 23.98L69 3M23.98 48.02L3 69'/><path d='M0 -6L6 0L0 6L-6 0ZM72 -6L78 0L72 6L66 0ZM0 66L6 72L0 78L-6 72ZM72 66L78 72L72 78L66 72Z'/></g></svg>");
  -webkit-mask-size:72px 72px;mask-size:72px 72px;
  -webkit-mask-repeat:repeat;mask-repeat:repeat;
  opacity:.075;
}

/* ── لوحُ الشعار على السرلوح الداكن: إطارٌ ذهبيّ وظلٌّ خفيف ── */
.unwan .brand-mark{margin:2px 0 20px}
.unwan .brand-mark img{
  border-color:var(--gold);
  box-shadow:0 14px 28px -16px var(--emerald-deep);
}
/* شعارٌ بلا لوح — T-125: لا إطار ذهبيّاً ولا ظلّاً لصورةٍ بلا خلفية. */
.unwan .brand-mark.no-plate img{border:none;box-shadow:none}

@media print{
  body::before{opacity:.06}
}
