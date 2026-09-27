{{--
  مفردات كتل T-46 بمزاج قالب المجلّة — تحريريّ: خطوطٌ رفيعة بدل البطاقات،
  وخطٌّ أكبر للقراءة الطويلة، وبلا حوافَّ مستديرة ولا ظلال.
  ولا لونَ صريحاً: كلُّه من متغيّرات `Palette`.
--}}

/* ═══ جدول البيانات — خطوطٌ أفقية وحدها، كجداول المطبوعات ═══ */
.data{margin:28px 0}
.data h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:10px;text-transform:uppercase;
}
.data table{
  width:100%;border-collapse:collapse;font-size:16.5px;
  border-top:2px solid var(--ink);border-bottom:1px solid var(--rule);
}
.data th{
  font-size:14px;font-weight:700;color:var(--emerald-deep);
  padding:10px 0;padding-inline-start:12px;text-align:start;
  border-bottom:1px solid var(--ink);
}
.data td{
  padding:12px 12px 12px 0;border-bottom:1px dotted var(--rule);
  color:var(--ink-soft);line-height:1.9;
}
.data tbody tr:last-child td{border-bottom:0}
.data .note{margin-top:9px;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ القائمة ═══ */
.listing{margin:26px 0}
.listing h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:9px;text-transform:uppercase;
}
.listing .bullets,.listing .numbered{margin:0;padding-inline-start:24px}
/* عربية هندية كسائر أرقام الصفحة — T-75. المولِّد لا يعرف اتجاهاً فيتبع لغته. */
.listing .numbered{list-style-type:arabic-indic}
.listing li{font-size:17px;line-height:1.95;margin-bottom:8px;color:var(--ink-soft)}
.listing li::marker{color:var(--gold);font-weight:700}
.listing .note{margin-top:9px;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ الأرقام البارزة — بلا صناديق، تفصلها خطوطٌ رفيعة ═══ */
.figures-wrap{margin:30px 0}
.figures-wrap h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:12px;text-transform:uppercase;
}
.figures{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
  gap:0;border-top:1px solid var(--rule);border-bottom:1px solid var(--rule);
}
.figure{padding:18px 14px;text-align:center;border-inline-start:1px dotted var(--rule)}
.figure:first-child{border-inline-start:0}
.fig-num{
  display:block;font-family:"Amiri",serif;font-size:36px;line-height:1.15;
  color:var(--emerald-deep);margin-bottom:4px;
}
.fig-label{display:block;font-size:14px;line-height:1.65;color:var(--ink-soft)}
.figures-wrap .note{margin-top:10px;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ خطّ الزمن ═══ */
.timeline{margin:30px 0}
.timeline h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:14px;text-transform:uppercase;
}
.event{display:grid;grid-template-columns:84px 1fr;gap:0;align-items:start}
.when{
  font-family:"Amiri",serif;font-size:18px;color:var(--gold);
  padding-top:12px;padding-inline-end:12px;text-align:end;
}
.what{padding-inline-start:0;padding:12px 0;border-top:1px solid var(--rule)}
.event:first-of-type .what,.event:first-of-type .when{border-top:0;padding-top:0}
.event:first-of-type .when{padding-top:0}
.what h4{font-size:17px;font-weight:700;color:var(--emerald-deep);margin-bottom:3px}
.what p{font-size:16.5px;line-height:1.95;margin:0;color:var(--ink-soft)}
.timeline .note{margin-top:10px;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ التفريع — أعمدةٌ يفصلها خطّ، بلا SVG ولا مكتبة ═══ */
.tree{margin:32px 0}
.tree h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:12px;text-transform:uppercase;
}
.root{
  display:block;font-family:"Aref Ruqaa",serif;font-size:22px;
  color:var(--emerald-deep);padding-bottom:12px;
  border-bottom:2px solid var(--ink);margin-bottom:0;
}
.branches{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0;
}
.branch{padding:16px 14px 16px 0;border-inline-start:1px dotted var(--rule)}
.branch:first-child{border-inline-start:0;padding-inline-end:0}
.branch h4{
  font-size:16px;font-weight:700;color:var(--emerald);
  margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--rule);
}
.leaf{font-size:15px;line-height:1.85;color:var(--ink-soft);margin-bottom:5px}
.leaf::before{content:'—';color:var(--gold);margin-inline-end:6px}
.tree .note{margin-top:10px;font-size:14px;font-style:italic;color:var(--ink-soft)}
/* فرعٌ داخل فرعٍ — T-72. نفسُ .branch متداخلاً، بلا صنفٍ جديد. */
.branch .branch{
  margin-top:8px;padding:0 0 0 14px;border-inline-start:1px dotted var(--rule);
}
.branch .branch h4{font-size:15px;border-bottom:0;padding-bottom:2px;margin-bottom:4px}

/* ═══ الهرم — طبقاتٌ يتدرّج عرضها بترتيبها لا بصنفٍ، T-72 ═══ */
.pyramid{margin:32px 0}
.pyramid h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:14px;text-transform:uppercase;text-align:center;
}
.tiers{display:flex;flex-direction:column;align-items:center;gap:0}
.tier{
  width:100%;box-sizing:border-box;text-align:center;
  border-top:1px dotted var(--rule);padding:14px 12px;
}
.tier:first-child{border-top:0}
.tier h4{font-family:"Aref Ruqaa",serif;font-size:19px;color:var(--emerald-deep);margin-bottom:4px}
.tier p{font-size:16px;line-height:1.85;color:var(--ink-soft);margin:0}
.tiers .tier:nth-child(1){max-width:45%}
.tiers .tier:nth-child(1) h4{color:var(--gold)}
.tiers .tier:nth-child(2){max-width:65%}
.tiers .tier:nth-child(3){max-width:85%}
.tiers .tier:nth-child(n+4){max-width:100%}
.pyramid .note{margin-top:10px;font-size:14px;font-style:italic;color:var(--ink-soft);text-align:center}

/* ═══ العجلة — حلقةٌ متدرّجة الامتلاء بعشر درجاتٍ ثابتة، T-72 ═══ */
.gauge{margin:32px 0;text-align:center}
.gauge h3{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin-bottom:14px;text-transform:uppercase;
}
.ring{
  --track:var(--rule);--fill:var(--emerald-deep);
  width:118px;height:118px;border-radius:50%;margin:0 auto;
  display:flex;align-items:center;justify-content:center;
  background:conic-gradient(var(--fill) 0%,var(--track) 0%);
}
.ring .reading{
  width:94px;height:94px;border-radius:50%;background:var(--paper);
  display:flex;align-items:center;justify-content:center;
  font-family:"Amiri",serif;font-size:28px;color:var(--emerald-deep);
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
.pole{font-size:14px;font-style:italic;color:var(--ink-soft)}
.gauge .note{margin-top:10px;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ تعريف المصطلح — لغةً واصطلاحاً، T-76 ═══ */
.gloss{margin:30px 0;padding-block:14px;border-block:1px solid var(--ink)}
.gloss-head{display:flex;align-items:baseline;gap:.6rem;margin-bottom:10px}
.gloss-term{font-family:"Aref Ruqaa",serif;font-size:21px;color:var(--emerald-deep)}
.gloss-root{font-size:13px;color:var(--ink-soft);letter-spacing:.12em}
.gloss-body{display:grid;grid-template-columns:max-content 1fr;column-gap:1rem;row-gap:.55rem}
.gloss-label{font-size:13px;font-weight:700;letter-spacing:.06em;color:var(--gold);text-transform:uppercase}
.gloss-text{font-size:16.5px;line-height:1.9;color:var(--ink-soft);margin:0}
.gloss .note{margin-top:11px;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ سؤالٌ وجوابه، T-76 ═══ */
.qa{margin:28px 0;counter-reset:qa}
.qa-item{counter-increment:qa;padding-block:13px;border-block-end:1px dotted var(--rule)}
.qa-item:last-child{border-block-end:0}
.qa-q{font-size:16px;font-weight:700;color:var(--emerald-deep);margin-bottom:6px}
.qa-a{font-size:16px;color:var(--ink-soft)}
.qa-q::before,.qa-a::before{
  display:inline-grid;place-items:center;vertical-align:middle;
  inline-size:1.8em;block-size:1.8em;border-radius:50%;
  margin-inline-end:.6rem;font-size:.7em;font-weight:700;
}
.qa-q::before{content:"س"counter(qa,arabic-indic);border:1px solid var(--ink);color:var(--ink)}
.qa-a::before{content:"ج";color:var(--gold);border:1px dotted var(--rule)}

/* ═══ فائدة أو تنبيه جانبي، T-76 ═══ */
.cnote{margin:24px 0;padding-inline-start:1rem;border-inline-start:2px solid var(--rule)}
.cn-mark{display:none}
.cn-benefit{border-inline-start-color:var(--emerald-deep)}
.cn-warning{border-inline-start-color:var(--gold)}
.cn-subtle{border-inline-start-color:var(--rule)}
.cn-title{
  font-size:13px;font-weight:700;letter-spacing:.08em;
  color:var(--gold);margin:0 0 5px;text-transform:uppercase;
}
.cn-text{font-size:15.5px;line-height:1.85;color:var(--ink-soft);margin:0;font-style:italic}

/* ═══ ما يُظنّ مقابل الصواب، T-76 ═══ */
.mfix{margin:28px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0}
.mfix-claim,.mfix-fix{padding:14px 16px 14px 0;border-inline-start:1px dotted var(--rule)}
.mfix-claim:first-child,.mfix-fix:first-child{border-inline-start:0}
.mfix-claim{color:var(--ink-soft)}
.mfix-fix{color:var(--ink)}
.mfix-tag{
  display:block;font-size:12px;font-weight:700;letter-spacing:.08em;
  margin-bottom:6px;text-transform:uppercase;
}
.mfix-claim .mfix-tag{color:var(--ink-soft)}
.mfix-fix .mfix-tag{color:var(--gold)}
.mfix p{margin:0;font-size:15.5px;line-height:1.9}
.mfix-evidence{grid-column:1/-1;margin:10px 0 0;font-size:14px;font-style:italic;color:var(--ink-soft)}

/* ═══ قائمة التخريج — T-81 ═══ */
.sources .ref{font-weight:600;color:var(--emerald);text-decoration:none}
.sources a.ref:hover,.sources a.ref:focus-visible{text-decoration:underline;text-underline-offset:3px}
.sources .excerpt{font-family:"Amiri",serif;font-size:1.04em;margin-inline-start:.5em}

/* ═══ وسما السؤال والجواب بلسان الصفحة — T-87 ═══ */
html[lang="en"] .qa-q::before{content:"Q" counter(qa)}
html[lang="en"] .qa-a::before{content:"A"}
html[lang="tr"] .qa-q::before{content:"S" counter(qa)}
html[lang="tr"] .qa-a::before{content:"C"}
html[lang="ru"] .qa-q::before{content:"В" counter(qa)}
html[lang="ru"] .qa-a::before{content:"О"}

/* ═══ الهاتف ═══ */
@media (max-width:640px){
  .data{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .data table{min-width:420px}
  .event{grid-template-columns:66px 1fr}
  .when{font-size:16px}
  .fig-num{font-size:30px}
  .figures{grid-template-columns:1fr 1fr}
  .figure:nth-child(odd){border-inline-start:0}
  .branches{grid-template-columns:1fr}
  .branch{border-inline-start:0;border-top:1px dotted var(--rule);padding-inline-end:0}
  .branch:first-child{border-top:0}
  .ring{width:98px;height:98px}
  .ring .reading{width:76px;height:76px;font-size:23px}
  .gloss-body{grid-template-columns:1fr}
  .mfix-claim,.mfix-fix{border-inline-start:0;border-top:1px dotted var(--rule);padding-inline-end:0;padding-inline-start:0}
  .mfix-claim:first-child,.mfix-fix:first-child{border-top:0}
}

/* ═══ الطباعة ═══ */
@media print{
  .data,.listing,.figures-wrap,.timeline,.tree,.pyramid,.gauge,
  .gloss,.qa,.cnote,.mfix{break-inside:avoid;page-break-inside:avoid}
  .event,.figure,.branch,.tier,.qa-item,.mfix-claim,.mfix-fix{break-inside:avoid;page-break-inside:avoid}
  .data table{border-top-color:#000;border-bottom-color:#999}
  .data th{color:#000;border-bottom-color:#000}
  .data td{border-bottom-color:#CCC}
  .root{color:#000;border-bottom-color:#000}
  .tiers .tier:nth-child(1) h4{color:#000}
  .ring{--track:#CCC;--fill:#666}
  .qa-q::before{color:#000;border-color:#000}
}
