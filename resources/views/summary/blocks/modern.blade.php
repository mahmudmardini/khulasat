{{--
  مفردات كتل T-46 بمزاج القالب العصري — بطاقاتٌ خفيفة وحوافُّ مستديرة
  ومسافاتٌ واسعة، بلا زخرفة. ولا لونَ صريحاً: كلُّه من متغيّرات `Palette`.
--}}

/* ═══ جدول البيانات ═══ */
.data{margin:26px 0}
.data h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:11px}
.data table{
  width:100%;border-collapse:separate;border-spacing:0;
  background:var(--paper);border:1px solid var(--rule);border-radius:12px;
  overflow:hidden;font-size:14.5px;
}
.data thead{background:var(--paper-2)}
.data th{
  font-size:13.5px;font-weight:600;letter-spacing:.02em;
  color:var(--emerald);padding:12px 14px;text-align:start;
  border-bottom:1px solid var(--rule);
}
.data td{padding:12px 14px;border-bottom:1px solid var(--rule);color:var(--ink-soft);line-height:1.8}
.data tbody tr:last-child td{border-bottom:0}
.data .note{margin-top:9px;font-size:13px;color:var(--ink-soft)}

/* ═══ القائمة ═══ */
.listing{margin:24px 0}
.listing h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:9px}
.listing .bullets,.listing .numbered{margin:0;padding-inline-start:22px}
/* عربية هندية كسائر أرقام الصفحة — T-75. المولِّد لا يعرف اتجاهاً فيتبع لغته. */
.listing .numbered{list-style-type:arabic-indic}
.listing li{font-size:14.5px;line-height:1.9;margin-bottom:7px;color:var(--ink-soft)}
.listing li::marker{color:var(--emerald-soft);font-weight:600}
.listing .note{margin-top:9px;font-size:13px;color:var(--ink-soft)}

/* ═══ الأرقام البارزة ═══ */
.figures-wrap{margin:28px 0}
.figures-wrap h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:13px}
.figures{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.figure{
  padding:20px 16px;background:var(--paper);border:1px solid var(--rule);
  border-radius:12px;text-align:center;
  transition:border-color .15s ease,box-shadow .15s ease;
}
.figure:hover{border-color:var(--emerald-soft);box-shadow:0 3px 12px rgba(22,26,29,.06)}
.fig-num{
  display:block;font-size:32px;font-weight:600;line-height:1.2;
  color:var(--emerald);margin-bottom:5px;
}
.fig-label{display:block;font-size:13.5px;line-height:1.65;color:var(--ink-soft)}
.figures-wrap .note{margin-top:11px;font-size:13px;color:var(--ink-soft);text-align:center}

/* ═══ خطّ الزمن ═══ */
.timeline{margin:28px 0}
.timeline h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:15px}
.event{display:grid;grid-template-columns:80px 1fr;gap:0;align-items:start}
.when{
  font-size:13.5px;font-weight:600;color:var(--gold);
  padding-top:3px;padding-inline-end:10px;text-align:end;
}
.what{
  position:relative;border-inline-start:2px solid var(--rule);
  padding-inline-start:18px;padding-bottom:20px;
}
.what::before{
  content:'';position:absolute;top:5px;inset-inline-start:-6px;
  width:10px;height:10px;border-radius:50%;
  background:var(--emerald);border:2px solid var(--paper);
}
.event:last-of-type .what{border-inline-start-color:transparent;padding-bottom:0}
.what h4{font-size:15.5px;font-weight:600;color:var(--emerald-deep);margin-bottom:4px}
.what p{font-size:14.5px;line-height:1.85;margin:0;color:var(--ink-soft)}
.timeline .note{margin-top:11px;font-size:13px;color:var(--ink-soft)}

/* ═══ التفريع — شبكة CSS، بلا SVG ولا مكتبة ═══ */
.tree{margin:30px 0;text-align:center}
.tree h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:13px}
.root{
  display:inline-block;padding:11px 22px;border-radius:12px;
  background:var(--emerald);color:var(--paper);
  font-size:15.5px;font-weight:600;
}
.branches{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  gap:12px;margin-top:20px;
}
.branch{position:relative;padding-top:16px}
.branch::before{
  content:'';position:absolute;top:0;inset-inline-start:calc(50% - 1px);
  width:2px;height:16px;background:var(--rule);
}
.branch h4{
  font-size:14.5px;font-weight:600;color:var(--emerald-deep);
  background:var(--paper-2);border:1px solid var(--rule);border-radius:10px;
  padding:10px 12px;margin-bottom:9px;
}
.leaf{
  font-size:13.5px;line-height:1.7;color:var(--ink-soft);
  background:var(--paper);border:1px solid var(--rule);border-radius:9px;
  padding:9px 11px;margin-bottom:7px;
}
.tree .note{margin-top:11px;font-size:13px;color:var(--ink-soft)}
/* فرعٌ داخل فرعٍ — T-72. نفسُ .branch متداخلاً، بلا صنفٍ جديد. */
.branch .branch{
  margin-top:9px;padding-top:0;background:none;
  border-inline-start:2px solid var(--rule);padding-inline-start:12px;
}
.branch .branch::before{display:none}
.branch .branch h4{font-size:13.5px;background:none;border:none;padding:0 0 6px}

/* ═══ الهرم — طبقاتٌ يتدرّج عرضها بترتيبها لا بصنفٍ، T-72 ═══ */
.pyramid{margin:30px 0}
.pyramid h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:13px;text-align:center}
.tiers{display:flex;flex-direction:column;align-items:center;gap:8px}
.tier{
  width:100%;box-sizing:border-box;text-align:center;
  background:var(--paper);border:1px solid var(--rule);border-radius:12px;
  padding:13px 16px;
}
.tier h4{font-size:14.5px;font-weight:600;color:var(--emerald-deep);margin-bottom:4px}
.tier p{font-size:13.5px;line-height:1.75;color:var(--ink-soft);margin:0}
.tiers .tier:nth-child(1){max-width:40%;background:var(--emerald);border-color:var(--emerald)}
.tiers .tier:nth-child(1) h4,.tiers .tier:nth-child(1) p{color:var(--paper)}
.tiers .tier:nth-child(2){max-width:60%}
.tiers .tier:nth-child(3){max-width:80%}
.tiers .tier:nth-child(n+4){max-width:100%}
.pyramid .note{margin-top:11px;font-size:13px;color:var(--ink-soft);text-align:center}

/* ═══ العجلة — حلقةٌ متدرّجة الامتلاء بعشر درجاتٍ ثابتة، T-72 ═══ */
.gauge{margin:30px 0;text-align:center}
.gauge h3{font-size:16px;font-weight:600;color:var(--emerald-deep);margin-bottom:13px}
.ring{
  --track:var(--rule);--fill:var(--emerald);
  width:112px;height:112px;border-radius:50%;margin:0 auto;
  display:flex;align-items:center;justify-content:center;
  background:conic-gradient(var(--fill) 0%,var(--track) 0%);
}
.ring .reading{
  width:86px;height:86px;border-radius:50%;background:var(--paper);
  display:flex;align-items:center;justify-content:center;
  font-size:26px;font-weight:600;color:var(--emerald-deep);
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
.poles{display:flex;justify-content:space-between;max-width:210px;margin:11px auto 0;gap:10px}
.pole{font-size:13px;color:var(--ink-soft)}
.gauge .note{margin-top:10px;font-size:13px;color:var(--ink-soft)}

/* ═══ تعريف المصطلح — لغةً واصطلاحاً، T-76 ═══ */
.gloss{margin:26px 0;background:var(--paper);border:1px solid var(--rule);border-radius:12px;padding:16px 18px}
.gloss-head{display:flex;align-items:baseline;gap:.6rem;margin-bottom:10px}
.gloss-term{font-size:17px;font-weight:600;color:var(--emerald-deep)}
.gloss-root{font-size:12.5px;color:var(--ink-soft);letter-spacing:.1em}
.gloss-body{display:grid;grid-template-columns:max-content 1fr;column-gap:.9rem;row-gap:.5rem}
.gloss-label{font-size:13px;color:var(--ink-soft)}
.gloss-text{font-size:14.5px;line-height:1.8;color:var(--ink-soft);margin:0}
.gloss .note{margin-top:11px;font-size:13px;color:var(--ink-soft)}

/* ═══ سؤالٌ وجوابه، T-76 ═══ */
.qa{margin:24px 0;counter-reset:qa}
.qa-item{counter-increment:qa;padding-block:11px;border-block-end:1px solid var(--rule)}
.qa-item:last-child{border-block-end:0}
.qa-q{font-size:14.5px;font-weight:600;color:var(--emerald-deep);margin-bottom:6px}
.qa-a{font-size:14.5px;color:var(--ink-soft)}
.qa-q::before,.qa-a::before{
  display:inline-grid;place-items:center;vertical-align:middle;
  inline-size:1.8em;block-size:1.8em;border-radius:50%;
  margin-inline-end:.55rem;font-size:.72em;
}
.qa-q::before{content:"س"counter(qa,arabic-indic);background:var(--emerald);color:var(--paper)}
.qa-a::before{content:"ج";border:1px solid var(--rule);color:var(--ink-soft)}

/* ═══ فائدة أو تنبيه جانبي، T-76 ═══ */
.cnote{margin:20px 0;display:grid;grid-template-columns:max-content 1fr;column-gap:.8rem;
  padding:13px 15px;border-radius:10px;border-inline-start:4px solid var(--rule);background:var(--paper)}
.cn-mark{font-size:16px;line-height:1}
.cn-benefit{border-inline-start-color:var(--emerald)}
.cn-benefit .cn-mark::before{content:"✺"}
.cn-warning{border-inline-start-color:var(--gold)}
.cn-warning .cn-mark::before{content:"!"}
.cn-subtle{border-inline-start-color:var(--rule)}
.cn-subtle .cn-mark::before{content:"·"}
.cn-title{font-size:14.5px;font-weight:600;color:var(--emerald-deep);margin:0 0 4px}
.cn-text{font-size:14px;line-height:1.75;color:var(--ink-soft);margin:0}

/* ═══ ما يُظنّ مقابل الصواب، T-76 ═══ */
.mfix{margin:24px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:11px}
.mfix-claim,.mfix-fix{padding:13px 15px;border-radius:12px}
.mfix-claim{border:2px dashed var(--rule);color:var(--ink-soft)}
.mfix-fix{border:2px solid var(--emerald);background:var(--paper)}
.mfix-tag{display:block;font-size:11.5px;font-weight:600;letter-spacing:.05em;margin-bottom:5px}
.mfix-claim .mfix-tag{color:var(--ink-soft)}
.mfix-fix .mfix-tag{color:var(--emerald-deep)}
.mfix p{margin:0;font-size:14px;line-height:1.75}
.mfix-evidence{grid-column:1/-1;margin:9px 0 0;font-size:13px;color:var(--ink-soft)}

/* ═══ قائمة التخريج — T-81 ═══ */
.sources .ref{font-weight:600;color:var(--emerald);text-decoration:none}
.sources a.ref:hover,.sources a.ref:focus-visible{text-decoration:underline;text-underline-offset:3px}
.sources .excerpt{font-family:"Amiri",serif;font-size:1.06em;margin-inline-start:.5em}

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
  .event{grid-template-columns:64px 1fr}
  .when{font-size:12.5px}
  .fig-num{font-size:28px}
  .branches{grid-template-columns:1fr 1fr;gap:9px}
  .ring{width:96px;height:96px}
  .ring .reading{width:72px;height:72px;font-size:22px}
  .gloss-body{grid-template-columns:1fr}
}

/* ═══ الطباعة ═══ */
@media print{
  .data,.listing,.figures-wrap,.timeline,.tree,.pyramid,.gauge,
  .gloss,.qa,.cnote,.mfix{break-inside:avoid;page-break-inside:avoid}
  .event,.figure,.branch,.tier,.qa-item,.mfix-claim,.mfix-fix{break-inside:avoid;page-break-inside:avoid}
  .figure:hover{box-shadow:none}
  .data table{border-color:#999}
  .data thead{background:#EEE}
  .data th{color:#000;border-bottom-color:#999}
  .data td{border-bottom-color:#DDD}
  .root{background:#EEE;color:#000;border:1px solid #999}
  .tiers .tier:nth-child(1){background:#EEE;border-color:#999}
  .tiers .tier:nth-child(1) h4,.tiers .tier:nth-child(1) p{color:#000}
  .ring{--track:#CCC;--fill:#666}
  .cnote{background:none}
  .qa-q::before{background:#DDD;color:#000;border:1px solid #999}
  .qa-a::before{border-color:#999}
  .mfix-fix{background:none}
}
