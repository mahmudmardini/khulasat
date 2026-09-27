{{--
  القالب البحثي التوثيقي — T-49.

  **رصانةٌ لا زينة**، على هيئة ورقةٍ علمية: خطٌّ مقروء وهوامشُ واسعة وألوانٌ
  خافتة. والشواهدُ والجداولُ أبرزُ ما في الصفحة، لأنّ من يفتح هذا القالب
  يوثّق ويراجع لا يتأمّل.

  **والهامش الجانبيّ يُفرد على الشاشة العريضة وحدها** (`min-width:900px`):
  تُزاح إليه الشواهد فتُقرأ إلى جانب ما تشهد له. وعلى الجوال عمودٌ واحد،
  فالعمودان على شاشةٍ ضيّقة يُنتجان سطوراً من كلمتين.

  ولا لونَ صريحاً خارج الطباعة: كلُّه من متغيّرات `Palette`.
--}}

:root{
  --paper:#FCFCFB; --paper-2:#F4F4F1; --paper-3:#E6E6E1;
  --emerald:#2E4A42; --emerald-deep:#1B2E29; --emerald-soft:#5B7A70;
  --gold:#8A6D3B; --gold-light:#B39760;
  --clay:#8B3A30; --clay-soft:#A9685F;
  --ink:#1C1E1D; --ink-soft:#525754;
  --rule:rgba(28,30,29,.16);
}

*{margin:0;padding:0;box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:16px;line-height:1.9;color:var(--ink);background:var(--paper-2);
}
.wrap{max-width:1000px;margin:0 auto;padding:0 20px 60px;background:var(--paper)}
h1,h2,h3,h4{line-height:1.45;font-weight:600}
.ruqaa{font-family:"Aref Ruqaa",serif}
p{margin-bottom:14px}
.lead{font-size:17px;color:var(--ink);line-height:1.95}
.muted{font-size:14.5px;color:var(--ink-soft);font-style:italic}

/* ── صدر الورقة ── */
.unwan{padding:44px 0 18px;border-bottom:1px solid var(--ink)}
.unwan .crest,.unwan .divider{display:none}
.unwan .inner{max-width:720px}
.unwan h1{font-family:"Aref Ruqaa",serif;font-size:32px;font-weight:400;color:var(--ink);margin-bottom:9px}
.unwan .sub{font-size:16.5px;color:var(--ink-soft);margin:0}
/* سرلوحٌ فاتح، فشريطُ البيانات حبريٌّ على أرضٍ أخفّ — T-108.
   والوسمُ أدقّ عمداً: ورقةُ الهوية بعدها، وفيها اللونُ الورقيُّ للداكنة. */
header.unwan .attrib{color:var(--ink);background:rgba(0,0,0,.045)}

/* ── الآية شاهداً موثَّقاً، لا لوحةً ── */
.citation-lead{margin:20px 0;padding:16px 20px;background:var(--paper-2);border-inline-start:3px solid var(--gold)}
.ayah-hero{font-family:"Amiri Quran","Amiri",serif;font-size:19px;line-height:2.15;color:var(--ink);margin:0}
.ayah-hero .src,.citation-lead .src{
  display:block;font-family:"IBM Plex Sans Arabic",sans-serif;
  font-size:12.5px;color:var(--ink-soft);margin-top:8px;
}
.ayah-no{font-family:"Amiri Quran",serif;color:var(--gold)}

.attrib{
  display:flex;flex-wrap:wrap;align-items:center;gap:9px;
  padding:14px 0;font-size:13.5px;color:var(--ink-soft);
}
.attrib b{font-weight:600;color:var(--ink)}
.attrib .dot{width:3px;height:3px;border-radius:50%;background:var(--rule)}

.majlis{margin:22px 0;padding:16px;background:var(--paper-2)}
.majlis .row{display:flex;gap:12px;padding:5px 0;font-size:14px}
.majlis .k{flex:none;width:104px;color:var(--ink-soft)}
.majlis .v{font-weight:500}
.majlis .v em{font-style:normal;color:var(--emerald)}
.majlis svg{width:17px;height:17px}

/* ── المتن ── */
.research-body{padding-top:10px}
.research-body>section{margin:32px 0;padding-top:24px;border-top:1px solid var(--rule)}
.research-body>section:first-of-type{border-top:0;padding-top:6px}
.sec-head{display:flex;align-items:baseline;gap:10px;margin-bottom:16px}
.sec-head h2{font-family:"Aref Ruqaa",serif;font-size:23px;font-weight:400;color:var(--emerald-deep);flex:none}
.sec-head .mark{flex:none;width:4px;height:4px;border-radius:50%;background:var(--gold)}
.sec-head .rule{flex:1;height:1px;background:var(--rule)}

/* ── الشواهد: أبرز ما في هذا القالب ── */
.sacred{margin:20px 0;padding:16px 20px;background:var(--paper-2);border-inline-start:3px solid var(--emerald-soft)}
.sacred .text{font-family:"Amiri",serif;font-size:18.5px;line-height:2.1;margin:0;color:var(--ink)}
.sacred .text.hadith{font-size:17.5px}
.sacred .src{
  display:block;font-size:12.5px;color:var(--ink-soft);
  margin-top:9px;padding-top:7px;border-top:1px dotted var(--rule);
}
.sacred.warn{border-inline-start-color:var(--clay);background:rgba(139,58,48,.05)}
.sacred.warn .src{color:var(--clay);font-weight:600}
.sacred.dark{border-inline-start-color:var(--gold);background:var(--paper-3)}
.sacred.dark .text{color:var(--emerald-deep)}
.sacred.dark .src{color:var(--gold)}

.trio{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0;margin:22px 0;border-top:1px solid var(--rule)}
.imam{padding:16px 18px 16px 0;border-bottom:1px solid var(--rule)}
.imam:hover{background:var(--paper-2)}
.imam .ico{display:none}
.imam h3{font-size:16px;margin-bottom:5px;color:var(--emerald-deep)}
.imam p{font-size:15px;line-height:1.9;margin:0;color:var(--ink-soft)}

.compare{display:grid;gap:20px;margin:24px 0}
.axis-card{padding:0}
.axis-q{font-size:16px;font-weight:600;margin-bottom:12px;color:var(--emerald-deep);padding-bottom:6px;border-bottom:1px solid var(--rule)}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.side{padding:0}
.side p{font-size:15px;line-height:1.9;margin:0;color:var(--ink-soft)}
.side.calm{border-inline-start:2px solid var(--emerald-soft);padding-inline-start:14px}
.side.panic{border-inline-start:2px solid var(--clay-soft);padding-inline-start:14px}
.tag{display:inline-block;font-size:11px;font-weight:600;letter-spacing:.04em;margin-bottom:6px}
.side.calm .tag{color:var(--emerald)}
.side.panic .tag{color:var(--clay)}

.quad{display:grid;grid-template-columns:1fr 1fr;gap:0;margin:22px 0;border-top:1px solid var(--rule)}
.quad a{display:block;padding:15px 16px 15px 0;text-decoration:none;color:inherit;border-bottom:1px solid var(--rule)}
.quad a:hover h3{color:var(--emerald)}
.quad a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.quad .ico{display:none}
.quad h3{font-size:15.5px;margin-bottom:3px;transition:color .15s ease}
.quad span{font-size:14.5px;color:var(--ink-soft)}

.pillar{margin:26px 0}
.pillar-title{display:flex;align-items:baseline;gap:9px;margin-bottom:13px}
.pillar-title .ico{display:none}
.pillar-title h3{font-family:"Aref Ruqaa",serif;font-size:19px;font-weight:400;color:var(--emerald-deep)}
.step{display:flex;gap:14px;padding:12px 0;border-bottom:1px dotted var(--rule)}
.step:first-of-type{padding-top:0}
.step:last-of-type{border-bottom:0}
.step .num{flex:none;font-size:13px;font-weight:600;color:var(--gold);padding-top:4px}
.step h4{font-size:16px;margin-bottom:3px}
.step p{font-size:15px;line-height:1.9;margin:0;color:var(--ink-soft)}

.paths{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin:24px 0}
.path{padding:0}
.path h4{font-size:15px;margin-bottom:9px;padding-bottom:5px;border-bottom:1px solid var(--rule)}
.path.good h4{color:var(--emerald)}
.path.bad h4{color:var(--clay)}
.path.good{border-inline-start:2px solid var(--emerald-soft);padding-inline-start:14px}
.path.bad{border-inline-start:2px solid var(--clay-soft);padding-inline-start:14px}
.node{font-size:14.5px;line-height:1.85;padding:5px 0;color:var(--ink-soft)}
.path.bad .node{color:var(--ink-soft)}
.node.final{font-weight:600;color:var(--emerald-deep);border-top:1px dotted var(--rule);padding-top:8px;margin-top:4px}
.path.good .node.final{color:var(--emerald)}
.path.bad .node.final{color:var(--clay)}
.arrow{display:block;color:var(--rule);font-size:12px}

.night{margin:28px 0;padding:20px;background:var(--paper-2);border:1px solid var(--rule)}
.night h2{font-family:"Aref Ruqaa",serif;font-size:19px;font-weight:400;color:var(--emerald-deep);margin-bottom:11px}
.night .moon{width:22px;height:22px;color:var(--gold)}
.night .q{font-family:"Amiri",serif;font-size:18px;line-height:2;margin-bottom:9px;color:var(--ink)}
.night .note{font-size:14px;color:var(--ink-soft);font-style:italic;margin-bottom:13px}
.checks{display:grid;gap:7px}
.checks label{display:flex;align-items:flex-start;gap:9px;font-size:14.5px;line-height:1.8;cursor:pointer}
.checks label:hover{color:var(--emerald)}
.checks input{
  appearance:none;flex:none;width:15px;height:15px;margin-top:5px;
  border:1px solid var(--emerald-soft);border-radius:2px;position:relative;cursor:pointer;
}
.checks input:checked{background:var(--emerald)}
.checks input:checked::after{
  content:'';position:absolute;inset-inline-start:4px;top:1px;
  width:4px;height:8px;border:solid var(--paper);
  border-width:0 2px 2px 0;transform:rotate(45deg);
}
.checks input:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}

.closing{
  margin:30px 0;padding:18px 0;text-align:center;
  font-family:"Amiri",serif;font-size:19px;line-height:2;color:var(--emerald-deep);
  border-top:1px solid var(--ink);border-bottom:1px solid var(--ink);
}

/* ── التخريج: في الصدر، فيأخذ حجمَ ما يستحقّ ── */
.sources{margin:22px 0;padding:18px 20px;background:var(--paper-2);border:1px solid var(--rule)}
.sources h3{font-size:13px;font-weight:700;letter-spacing:.06em;color:var(--gold);margin-bottom:11px}
.sources ol{margin:0;padding-inline-start:20px}
.sources li{font-size:14px;line-height:1.95;margin-bottom:7px;color:var(--ink-soft)}

.colophon{margin:34px 0 0;padding-top:22px;border-top:1px solid var(--rule);text-align:center}
.colophon::before{content:''}
.colophon .note{font-size:13px;color:var(--ink-soft);line-height:1.85;font-style:italic}
.colophon .sep{width:40px;height:1px;background:var(--rule);margin:12px auto}
.mosque-name{font-family:"Aref Ruqaa",serif;font-size:18px;font-weight:400;color:var(--emerald-deep)}
.mosque-latin{font-size:12px;letter-spacing:.07em;color:var(--ink-soft)}
.links{display:flex;justify-content:center;gap:14px;margin-top:12px;flex-wrap:wrap}
.links a{display:inline-flex;align-items:center;gap:5px;font-size:13px;color:var(--emerald);text-decoration:none}
.links a:hover{text-decoration:underline}
.links a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.links svg{width:15px;height:15px}

.actions{display:flex;gap:10px;justify-content:center;margin-top:30px;flex-wrap:wrap}
.act{
  display:inline-flex;align-items:center;gap:6px;
  font-family:"IBM Plex Sans Arabic",sans-serif;font-size:14px;
  padding:9px 18px;cursor:pointer;
  background:var(--paper);color:var(--ink);border:1px solid var(--ink);
}
.act:hover{background:var(--paper-2)}
.act:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.act svg{width:15px;height:15px}
.act.primary{background:var(--emerald-deep);color:var(--paper);border-color:var(--emerald-deep)}
.act.primary:hover{background:var(--ink)}
.act.primary:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}

.print-tip{position:fixed;inset:0;z-index:50;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45);padding:16px}
.print-tip.open{display:flex}
.print-tip .box{background:var(--paper);padding:24px;max-width:430px;width:100%;border:1px solid var(--ink)}
.print-tip h3{font-size:17px;color:var(--emerald-deep);margin-bottom:9px}
.print-tip p{font-size:14px;color:var(--ink-soft)}
.print-tip ol{margin:0 0 18px;padding-inline-start:20px;font-size:14px;line-height:2}
.print-tip ol b{color:var(--emerald-deep);font-weight:600}
.print-tip .acts{display:flex;gap:10px;flex-wrap:wrap}
.print-tip button{
  flex:1 1 130px;font-family:"IBM Plex Sans Arabic",sans-serif;font-size:14px;
  padding:9px;cursor:pointer;border:1px solid var(--ink);
}
.print-tip .go{background:var(--emerald-deep);color:var(--paper)}
.print-tip .go:hover{background:var(--ink)}
.print-tip .cancel{background:var(--paper);color:var(--ink)}
.print-tip .cancel:hover{background:var(--paper-2)}

.toast{
  position:fixed;bottom:20px;inset-inline-start:50%;transform:translateX(50%) translateY(80px);
  background:var(--emerald-deep);color:var(--paper);font-size:13.5px;
  padding:10px 20px;opacity:0;transition:.25s ease;z-index:60;
}
.toast.show{opacity:1;transform:translateX(50%) translateY(0)}

/* ── الهامش الجانبيّ: على العريضة وحدها ── */
@media (min-width:900px){
  .research-body>section{
    display:grid;grid-template-columns:1fr 260px;
    column-gap:30px;align-items:start;
  }
  .research-body>section>*{grid-column:1}
  /* الشاهد يُزاح إلى الهامش فيُقرأ إلى جانب ما يشهد له. */
  .research-body>section>.sacred{
    grid-column:2;margin:0 0 16px;font-size:15px;
    padding:13px 15px;background:var(--paper-2);
  }
  .research-body>section>.sacred .text{font-size:16px;line-height:1.95}
  .research-body>section>.sacred .text.hadith{font-size:15px}
  .research-body>section>.sec-head{grid-column:1 / -1}
}

@media (max-width:640px){
  body{font-size:15.5px}
  .wrap{padding:0 15px 44px}
  .unwan{padding:30px 0 15px}
  .unwan h1{font-size:25px}
  .pair,.paths{grid-template-columns:1fr}
  .quad{grid-template-columns:1fr}
  .night{padding:16px}
  .colophon{margin-top:26px}
}

@media print{
  body{background:#FFF;font-size:11pt}
  .wrap{max-width:100%;padding:0}
  .actions,.print-tip,.toast{display:none}
  .unwan{border-bottom-color:#000}
  .unwan h1{color:#000}
  .unwan .sub{color:#333}
  .night{background:#F7F7F7;border-color:#999}
  .night h2,.night .q{color:#000}
  .night .note{color:#333}
  .checks label{color:#000}
  .checks input{border-color:#666}
  .sacred,.imam,.axis-card,.pillar,.quad a,.path,.closing,.ayah-hero,.citation-lead,.sources{
    break-inside:avoid;page-break-inside:avoid;
  }
  .research-body>section{break-inside:auto}
  .sec-head{break-after:avoid}
  .colophon{border-top-color:#999}
  a{color:#000;text-decoration:none}
}
