{{--
  قالب الدرس التعليمي — T-49.

  **الكثافةُ فضيلةٌ هنا لا عيب.** الدارس يعود إلى الصفحة مراجعاً، فيريد أن
  يرى أكثرَ ما يمكن في الشاشة الواحدة، ويجد قسمَه برقمه لا بتصفّحه.

  **والترقيم بعدّاد CSS لا بحرفٍ يُضاف إلى المتن** — فالمتن واحدٌ في القوالب
  كلِّها، وترقيمُه في ورقة الأنماط يجعله ترقيمَ عرضٍ يزول بتبدّل القالب.

  ولا لونَ صريحاً خارج الطباعة: كلُّه من متغيّرات `Palette`.
--}}

:root{
  --paper:#FBFAF7; --paper-2:#F2F1EC; --paper-3:#E4E3DC;
  --emerald:#1F6F5C; --emerald-deep:#14493D; --emerald-soft:#4E9384;
  --gold:#B07C2A; --gold-light:#D4A75C;
  --clay:#9A4034; --clay-soft:#BC7168;
  --ink:#1A1D1C; --ink-soft:#4F5654;
  --rule:rgba(26,29,28,.14);
}

*{margin:0;padding:0;box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:15.5px;line-height:1.85;color:var(--ink);background:var(--paper-2);
}
.wrap{max-width:820px;margin:0 auto;padding:0 16px 56px;background:var(--paper)}
h1,h2,h3,h4{line-height:1.4;font-weight:600}
.ruqaa{font-family:"Aref Ruqaa",serif}
p{margin-bottom:13px}
.lead{font-size:17px;font-weight:500;color:var(--emerald-deep);line-height:1.8}
.muted{font-size:14px;color:var(--ink-soft)}

/* ── السرلوح: بلا زخرفة، وشارةٌ تقول ما هذا ── */
.unwan{padding:34px 0 20px;text-align:center;border-bottom:3px solid var(--emerald)}
.unwan .crest{display:none}
.unwan .inner{max-width:640px;margin:0 auto}
.unwan .kicker{
  display:inline-block;font-size:11.5px;font-weight:600;letter-spacing:.14em;
  color:var(--paper);background:var(--emerald);padding:5px 14px;border-radius:20px;
  margin-bottom:14px;
}
.unwan h1{font-size:30px;font-weight:600;color:var(--emerald-deep);margin-bottom:8px}
.unwan .sub{font-size:16px;color:var(--ink-soft);margin:0}
.unwan .divider{display:none}
/* سرلوحٌ فاتح، فشريطُ البيانات حبريٌّ على أرضٍ أخفّ — T-108.
   **والوسمُ أدقّ من `.unwan .attrib` عمداً**: ورقةُ الهوية تُدرَج بعد أوراق
   القوالب وفيها اللونُ الورقيُّ للسرالح الداكنة، فلو تساويا غلبتْ هي وذهب
   الحبر. قِيس: كان لونُ الشريط ورقيّاً على أرضٍ فاتحة فلا يكاد يُقرأ. */
header.unwan .attrib{color:var(--ink);background:rgba(0,0,0,.045)}

/* ── صندوق آية الدرس ── */
.objective{
  margin:22px 0 8px;padding:18px 20px;
  background:var(--paper-2);border-inline-start:4px solid var(--gold);border-radius:0 8px 8px 0;
}
.objective-label{
  display:block;font-size:11.5px;font-weight:600;letter-spacing:.1em;
  color:var(--gold);margin-bottom:8px;
}
.ayah-hero{
  font-family:"Amiri Quran","Amiri",serif;font-size:19px;line-height:2.1;
  color:var(--emerald-deep);margin:0;
}
.ayah-hero .src,.objective .src{
  display:block;font-family:"IBM Plex Sans Arabic",sans-serif;
  font-size:12.5px;color:var(--ink-soft);margin-top:7px;
}
.ayah-no{font-family:"Amiri Quran",serif;color:var(--gold)}

.attrib{
  display:flex;flex-wrap:wrap;align-items:center;gap:8px;
  padding:13px 0;font-size:13.5px;color:var(--ink-soft);
  border-bottom:1px solid var(--rule);
}
.attrib b{font-weight:600;color:var(--ink)}
.attrib .dot{width:3px;height:3px;border-radius:50%;background:var(--rule)}

.majlis{margin:22px 0;padding:16px;background:var(--paper-2);border-radius:8px}
.majlis .row{display:flex;gap:10px;padding:6px 0;font-size:14px}
.majlis .k{flex:none;width:96px;color:var(--ink-soft)}
.majlis .v{font-weight:500}
.majlis .v em{font-style:normal;color:var(--emerald)}
.majlis svg{width:18px;height:18px}

/* ── المتن: أقسامٌ مرقّمة بعدّاد ── */
.lesson-body{counter-reset:lesson-section}
.lesson-body>section{
  counter-increment:lesson-section;
  margin:30px 0;padding-top:22px;border-top:1px solid var(--rule);
}
.lesson-body>section:first-of-type{border-top:0;padding-top:8px}
.sec-head{display:flex;align-items:center;gap:11px;margin-bottom:15px}
.sec-head h2{font-size:20px;font-weight:600;color:var(--emerald-deep);flex:none}
/* الترقيم عربيٌّ هنديّ كسائر أرقام القالب — `arabic-indic` نمطُ عدٍّ في CSS. */
.lesson-body>section>.sec-head h2::before{
  content:counter(lesson-section,arabic-indic) ". ";
  color:var(--gold);font-weight:700;
}
.sec-head .mark{
  flex:none;width:6px;height:22px;border-radius:3px;background:var(--gold);
}
.sec-head .rule{flex:1;height:1px;background:var(--rule)}

/* ── الشواهد ── */
.sacred{
  margin:17px 0;padding:15px 17px;
  background:var(--paper-2);border-inline-start:3px solid var(--emerald-soft);border-radius:0 8px 8px 0;
}
.sacred .text{font-family:"Amiri",serif;font-size:17.5px;line-height:2;margin:0;color:var(--emerald-deep)}
.sacred .text.hadith{font-size:16.5px}
.sacred .src{display:block;font-size:12.5px;color:var(--ink-soft);margin-top:7px}
.sacred.warn{border-inline-start-color:var(--clay);background:rgba(154,64,52,.06)}
.sacred.warn .src{color:var(--clay)}
.sacred.dark{background:var(--emerald-deep);border-inline-start-color:var(--gold)}
.sacred.dark .text{color:var(--paper)}
.sacred.dark .src{color:var(--gold-light)}

/* ── البطاقات ── */
.trio{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:20px 0}
.imam{padding:15px;background:var(--paper-2);border:1px solid var(--rule);border-radius:8px}
.imam:hover{border-color:var(--emerald-soft)}
.imam .ico{width:26px;height:26px;margin-bottom:8px;color:var(--emerald)}
.imam h3{font-size:15px;margin-bottom:5px;color:var(--emerald-deep)}
.imam p{font-size:14px;line-height:1.8;margin:0;color:var(--ink-soft)}

/* ── المقارنة ── */
.compare{display:grid;gap:13px;margin:20px 0}
.axis-card{padding:15px;background:var(--paper-2);border-radius:8px}
.axis-q{font-size:15px;font-weight:600;margin-bottom:11px;color:var(--emerald-deep)}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:11px}
.side{padding:12px;background:var(--paper);border-radius:6px;border:1px solid var(--rule)}
.side p{font-size:13.5px;line-height:1.8;margin:0;color:var(--ink-soft)}
.side.calm{border-color:rgba(31,111,92,.35)}
.side.panic{border-color:rgba(154,64,52,.3)}
.tag{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;margin-bottom:7px}
.side.calm .tag{background:rgba(31,111,92,.12);color:var(--emerald)}
.side.panic .tag{background:rgba(154,64,52,.1);color:var(--clay)}

/* ── المجالات ── */
.quad{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:11px;margin:20px 0}
.quad a{
  display:block;padding:14px;text-decoration:none;color:inherit;
  background:var(--paper-2);border:1px solid var(--rule);border-radius:8px;
}
.quad a:hover{border-color:var(--emerald-soft)}
.quad a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.quad .ico{width:24px;height:24px;margin-bottom:8px;color:var(--gold)}
.quad h3{font-size:14.5px;margin-bottom:4px}
.quad span{font-size:13px;color:var(--ink-soft)}

/* ── الأركان: خطواتٌ مرقّمة، وهي عصب الدرس ── */
.pillar{margin:22px 0;padding:17px;background:var(--paper-2);border-radius:8px}
.pillar-title{display:flex;align-items:center;gap:10px;margin-bottom:13px}
.pillar-title .ico{width:22px;height:22px;color:var(--emerald)}
.pillar-title h3{font-size:16px;color:var(--emerald-deep)}
.step{display:flex;gap:12px;padding:11px 0;border-top:1px solid var(--rule)}
.step:first-of-type{border-top:0;padding-top:0}
.step .num{
  flex:none;display:flex;align-items:center;justify-content:center;
  width:25px;height:25px;border-radius:6px;
  background:var(--emerald);color:var(--paper);font-size:13px;font-weight:600;
}
.step h4{font-size:14.5px;margin-bottom:3px}
.step p{font-size:13.5px;line-height:1.8;margin:0;color:var(--ink-soft)}

/* ── المسارات ── */
.paths{display:grid;grid-template-columns:1fr 1fr;gap:13px;margin:20px 0}
.path{padding:14px;border-radius:8px;border:1px solid var(--rule)}
.path h4{font-size:14px;margin-bottom:9px}
.path.good{background:rgba(31,111,92,.05);border-color:rgba(31,111,92,.28)}
.path.good h4{color:var(--emerald)}
.path.bad{background:rgba(154,64,52,.05);border-color:rgba(154,64,52,.25)}
.path.bad h4{color:var(--clay)}
.node{
  font-size:13px;line-height:1.7;padding:7px 10px;margin-bottom:6px;
  background:var(--paper);border-radius:5px;border:1px solid var(--rule);
}
.path.bad .node{border-color:rgba(154,64,52,.18)}
.node.final{font-weight:600;background:var(--emerald);color:var(--paper);border-color:transparent}
.path.bad .node.final{background:var(--clay)}
.path.good .node.final{background:var(--emerald)}
.arrow{display:block;text-align:center;color:var(--rule);font-size:12px}

/* ── كتلة الليل: مهامُّ الدارس ── */
.night{
  margin:26px 0;padding:20px;border-radius:10px;
  background:var(--emerald-deep);color:var(--paper);
}
.night h2{font-size:18px;color:var(--gold-light);margin-bottom:11px}
.night .moon{width:26px;height:26px;color:var(--gold-light)}
.night .q{font-family:"Amiri",serif;font-size:18px;line-height:1.95;margin-bottom:9px}
.night .note{font-size:13.5px;color:rgba(255,255,255,.72);margin-bottom:13px}
.checks{display:grid;gap:8px}
.checks label{
  display:flex;align-items:flex-start;gap:9px;font-size:14px;line-height:1.7;
  padding:9px 11px;background:rgba(255,255,255,.07);border-radius:6px;cursor:pointer;
}
.checks label:hover{background:rgba(255,255,255,.12)}
.checks input{
  appearance:none;flex:none;width:17px;height:17px;margin-top:3px;
  border:1.5px solid var(--gold-light);border-radius:4px;position:relative;cursor:pointer;
}
.checks input:checked{background:var(--gold-light)}
.checks input:checked::after{
  content:'';position:absolute;inset-inline-start:5px;top:1px;
  width:4px;height:9px;border:solid var(--emerald-deep);
  border-width:0 2px 2px 0;transform:rotate(45deg);
}
.checks input:focus-visible{outline:2px solid var(--gold-light);outline-offset:2px}

.closing{
  margin:26px 0;padding:17px 20px;text-align:center;
  font-family:"Amiri",serif;font-size:18px;line-height:1.95;color:var(--emerald-deep);
  background:var(--paper-2);border-radius:8px;
}

/* ── المصادر: قبل البصمة في هذا القالب ── */
.sources{margin:26px 0;padding:17px;background:var(--paper-2);border-radius:8px}
.sources h3{font-size:14px;color:var(--emerald-deep);margin-bottom:9px}
.sources ol{margin:0;padding-inline-start:20px}
.sources li{font-size:13px;line-height:1.85;margin-bottom:5px;color:var(--ink-soft)}

.colophon{margin:26px 0 0;padding-top:20px;border-top:1px solid var(--rule);text-align:center}
.colophon::before{content:''}
.colophon .note{font-size:12.5px;color:var(--ink-soft);line-height:1.8}
.colophon .sep{width:38px;height:1px;background:var(--rule);margin:11px auto}
.mosque-name{font-size:16px;font-weight:600;color:var(--emerald-deep)}
.mosque-latin{font-size:12px;letter-spacing:.06em;color:var(--ink-soft)}
.links{display:flex;justify-content:center;gap:13px;margin-top:11px;flex-wrap:wrap}
.links a{
  display:inline-flex;align-items:center;gap:5px;
  font-size:12.5px;color:var(--emerald);text-decoration:none;
}
.links a:hover{text-decoration:underline}
.links a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.links svg{width:15px;height:15px}

.actions{display:flex;gap:9px;justify-content:center;margin-top:26px;flex-wrap:wrap}
.act{
  display:inline-flex;align-items:center;gap:6px;
  font-family:"IBM Plex Sans Arabic",sans-serif;font-size:13.5px;
  padding:9px 17px;border-radius:7px;cursor:pointer;
  background:var(--paper-2);color:var(--ink);border:1px solid var(--rule);
}
.act:hover{border-color:var(--emerald-soft)}
.act:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.act svg{width:15px;height:15px}
.act.primary{background:var(--emerald);color:var(--paper);border-color:transparent}
.act.primary:hover{background:var(--emerald-deep)}
.act.primary:focus-visible{outline:2px solid var(--emerald-deep);outline-offset:2px}

.print-tip{position:fixed;inset:0;z-index:50;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45);padding:16px}
.print-tip.open{display:flex}
.print-tip .box{background:var(--paper);border-radius:10px;padding:22px;max-width:420px;width:100%}
.print-tip h3{font-size:17px;color:var(--emerald-deep);margin-bottom:9px}
.print-tip p{font-size:13.5px;color:var(--ink-soft)}
.print-tip ol{margin:0 0 17px;padding-inline-start:20px;font-size:13.5px;line-height:1.95}
.print-tip ol b{color:var(--emerald-deep);font-weight:600}
.print-tip .acts{display:flex;gap:9px;flex-wrap:wrap}
.print-tip button{
  flex:1 1 130px;font-family:"IBM Plex Sans Arabic",sans-serif;font-size:14px;
  padding:9px;border-radius:7px;cursor:pointer;border:1px solid var(--rule);
}
.print-tip .go{background:var(--emerald);color:var(--paper);border-color:transparent}
.print-tip .go:hover{background:var(--emerald-deep)}
.print-tip .cancel{background:var(--paper-2);color:var(--ink)}
.print-tip .cancel:hover{background:var(--paper-3)}

.toast{
  position:fixed;bottom:20px;inset-inline-start:50%;transform:translateX(50%) translateY(80px);
  background:var(--emerald-deep);color:var(--paper);font-size:13.5px;
  padding:10px 20px;border-radius:20px;opacity:0;transition:.25s ease;z-index:60;
}
.toast.show{opacity:1;transform:translateX(50%) translateY(0)}

@media (max-width:640px){
  body{font-size:15px}
  .wrap{padding:0 13px 42px}
  .unwan{padding:24px 0 16px}
  .unwan h1{font-size:24px}
  .pair,.paths{grid-template-columns:1fr}
  .night{padding:16px}
  .colophon{margin-top:22px}
}

@media print{
  body{background:#FFF;font-size:11.5pt}
  .wrap{max-width:100%;padding:0}
  .actions,.print-tip,.toast{display:none}
  .unwan{border-bottom-color:#000}
  .unwan h1{color:#000}
  .unwan .sub{color:#333}
  .unwan .kicker{background:none;color:#000;border:1px solid #999}
  .night{background:#F2F2F2;color:#000;border:1px solid #999}
  .night h2,.night .q{color:#000}
  .night .note{color:#333}
  .checks label{background:none;border:1px solid #CCC}
  .checks input{border-color:#666}
  .sacred,.imam,.axis-card,.pillar,.quad a,.path,.closing,.ayah-hero,.objective{
    break-inside:avoid;page-break-inside:avoid;
  }
  .lesson-body>section{break-inside:avoid;page-break-inside:avoid}
  .sec-head{break-after:avoid}
  .colophon{border-top-color:#999}
  a{color:#000;text-decoration:none}
}
