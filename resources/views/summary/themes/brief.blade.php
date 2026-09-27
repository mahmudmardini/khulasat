{{--
  قالب البطاقة الموجزة — T-49.

  **مقاسٌ واحد يحكم الورقة كلَّها:** عرضٌ ضيّق ومسافاتٌ مقتصدة، لتُقرأ
  البطاقة على الهاتف دفعةً واحدة. وكلُّ زخرفةٍ حُذفت لأنّها تزاحم سطراً نافعاً.

  **والإيجازُ إيجازُ عرضٍ لا حذف** — المحتوى كامل، والكثافة هي التي تُختصر.

  ولا لونَ صريحاً خارج الطباعة: كلُّه من متغيّرات `Palette`.
--}}

:root{
  --paper:#FFFFFF; --paper-2:#F5F5F3; --paper-3:#E7E7E3;
  --emerald:#1F6F5C; --emerald-deep:#14493D; --emerald-soft:#4E9384;
  --gold:#B07C2A; --gold-light:#D4A75C;
  --clay:#9A4034; --clay-soft:#BC7168;
  --ink:#17191A; --ink-soft:#565C5E;
  --rule:rgba(23,25,26,.12);
}

*{margin:0;padding:0;box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:15px;line-height:1.75;color:var(--ink);background:var(--paper-2);
}
.wrap{max-width:560px;margin:0 auto;padding:0 15px 40px;background:var(--paper)}
h1,h2,h3,h4{line-height:1.35;font-weight:600}
.ruqaa{font-family:"Aref Ruqaa",serif}
p{margin-bottom:10px}
.lead{font-size:16px;font-weight:500;color:var(--emerald-deep);line-height:1.7}
.muted{font-size:13px;color:var(--ink-soft)}

/* ── سرلوحٌ في سطرين ── */
.unwan{padding:26px 0 14px}
.unwan .crest,.unwan .divider{display:none}
.unwan .inner{text-align:start}
.unwan h1{font-size:25px;font-weight:600;color:var(--emerald-deep);margin-bottom:5px}
.unwan .sub{font-size:14.5px;color:var(--ink-soft);margin:0}
/* سرلوحٌ فاتح، فشريطُ البيانات حبريٌّ على أرضٍ أخفّ — T-108.
   والوسمُ أدقّ عمداً: ورقةُ الهوية بعدها، وفيها اللونُ الورقيُّ للداكنة. */
header.unwan .attrib{color:var(--ink);background:rgba(0,0,0,.045)}

.ayah-hero{font-family:"Amiri Quran","Amiri",serif;font-size:17px;line-height:1.95;color:var(--emerald-deep)}
.ayah-hero .src{display:block;font-family:"IBM Plex Sans Arabic",sans-serif;font-size:12px;color:var(--ink-soft);margin-top:5px}
.ayah-no{font-family:"Amiri Quran",serif;color:var(--gold)}

/* ── المتن: مضغوطٌ وفواصلُه رفيعة ── */
.brief-body{padding-top:6px}
.brief-body>section{margin:18px 0;padding-top:14px;border-top:1px solid var(--rule)}
.brief-body>section:first-of-type{border-top:0;padding-top:0}
.sec-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.sec-head h2{font-size:16px;font-weight:600;color:var(--emerald-deep);flex:none}
.sec-head .mark{flex:none;width:5px;height:16px;border-radius:2px;background:var(--gold)}
.sec-head .rule{flex:1;height:1px;background:var(--rule)}

.sacred{margin:12px 0;padding:11px 13px;background:var(--paper-2);border-radius:7px}
.sacred .text{font-family:"Amiri",serif;font-size:16px;line-height:1.9;margin:0;color:var(--emerald-deep)}
.sacred .text.hadith{font-size:15px}
.sacred .src{display:block;font-size:11.5px;color:var(--ink-soft);margin-top:5px}
.sacred.warn{background:rgba(154,64,52,.07)}
.sacred.warn .src{color:var(--clay)}
.sacred.dark{background:var(--emerald-deep)}
.sacred.dark .text{color:var(--paper)}
.sacred.dark .src{color:var(--gold-light)}

.trio{display:grid;grid-template-columns:1fr;gap:8px;margin:14px 0}
.imam{padding:11px 13px;background:var(--paper-2);border-radius:7px}
.imam:hover{background:var(--paper-3)}
.imam .ico{display:none}
.imam h3{font-size:14px;margin-bottom:3px;color:var(--emerald-deep)}
.imam p{font-size:13.5px;line-height:1.75;margin:0;color:var(--ink-soft)}

.compare{display:grid;gap:9px;margin:14px 0}
.axis-card{padding:11px 13px;background:var(--paper-2);border-radius:7px}
.axis-q{font-size:14px;font-weight:600;margin-bottom:8px;color:var(--emerald-deep)}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.side{padding:9px 10px;background:var(--paper);border-radius:5px}
.side p{font-size:12.5px;line-height:1.7;margin:0;color:var(--ink-soft)}
.side.calm{box-shadow:inset 2px 0 0 var(--emerald-soft)}
.side.panic{box-shadow:inset 2px 0 0 var(--clay-soft)}
.tag{display:inline-block;font-size:10.5px;font-weight:600;padding:1px 6px;border-radius:3px;margin-bottom:5px}
.side.calm .tag{background:rgba(31,111,92,.12);color:var(--emerald)}
.side.panic .tag{background:rgba(154,64,52,.1);color:var(--clay)}

.quad{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:14px 0}
.quad a{display:block;padding:11px;text-decoration:none;color:inherit;background:var(--paper-2);border-radius:7px}
.quad a:hover{background:var(--paper-3)}
.quad a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.quad .ico{display:none}
.quad h3{font-size:13.5px;margin-bottom:2px}
.quad span{font-size:12.5px;color:var(--ink-soft)}

.pillar{margin:14px 0}
.pillar-title{display:flex;align-items:center;gap:8px;margin-bottom:9px}
.pillar-title .ico{display:none}
.pillar-title h3{font-size:15px;color:var(--emerald-deep)}
.step{display:flex;gap:9px;padding:7px 0}
.step .num{
  flex:none;display:flex;align-items:center;justify-content:center;
  width:20px;height:20px;border-radius:50%;
  background:var(--emerald);color:var(--paper);font-size:11.5px;font-weight:600;
}
.step:first-of-type{padding-top:0}
.step h4{font-size:13.5px;margin-bottom:2px}
.step p{font-size:13px;line-height:1.75;margin:0;color:var(--ink-soft)}

.paths{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:14px 0}
.path{padding:10px;border-radius:7px;background:var(--paper-2)}
.path h4{font-size:13px;margin-bottom:7px}
.path.good h4{color:var(--emerald)}
.path.bad h4{color:var(--clay)}
.path.good{box-shadow:inset 2px 0 0 var(--emerald-soft)}
.path.bad{box-shadow:inset 2px 0 0 var(--clay-soft)}
.node{font-size:12.5px;line-height:1.6;padding:5px 8px;margin-bottom:5px;background:var(--paper);border-radius:4px}
.path.bad .node{color:var(--ink-soft)}
.node.final{font-weight:600;background:var(--emerald);color:var(--paper)}
.path.good .node.final{background:var(--emerald)}
.path.bad .node.final{background:var(--clay)}
.arrow{display:block;text-align:center;color:var(--rule);font-size:11px}

.night{margin:18px 0;padding:15px;border-radius:9px;background:var(--emerald-deep);color:var(--paper)}
.night h2{font-size:15.5px;color:var(--gold-light);margin-bottom:8px}
.night .moon{width:20px;height:20px;color:var(--gold-light)}
.night .q{font-family:"Amiri",serif;font-size:16.5px;line-height:1.85;margin-bottom:7px}
.night .note{font-size:12.5px;color:rgba(255,255,255,.7);margin-bottom:10px}
.checks{display:grid;gap:6px}
.checks label{
  display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.6;
  padding:7px 9px;background:rgba(255,255,255,.07);border-radius:5px;cursor:pointer;
}
.checks label:hover{background:rgba(255,255,255,.12)}
.checks input{
  appearance:none;flex:none;width:15px;height:15px;margin-top:2px;
  border:1.5px solid var(--gold-light);border-radius:3px;position:relative;cursor:pointer;
}
.checks input:checked{background:var(--gold-light)}
.checks input:checked::after{
  content:'';position:absolute;inset-inline-start:4px;top:1px;
  width:4px;height:8px;border:solid var(--emerald-deep);
  border-width:0 2px 2px 0;transform:rotate(45deg);
}
.checks input:focus-visible{outline:2px solid var(--gold-light);outline-offset:2px}

.closing{
  margin:18px 0;padding:13px 15px;text-align:center;
  font-family:"Amiri",serif;font-size:16.5px;line-height:1.85;
  color:var(--emerald-deep);background:var(--paper-2);border-radius:7px;
}

/* ── الذيل: المصدر والتخريج والبصمة، مقاسٌ أصغر ── */
/* و`brief-foot` رُفع في T-106: بيانات المجلس صارت ذيلاً للسرلوح، ففرغ وعاؤه. */
.attrib{
  display:flex;flex-wrap:wrap;align-items:center;gap:7px;
  font-size:12.5px;color:var(--ink-soft);margin-bottom:12px;
}
.attrib b{font-weight:600;color:var(--ink)}
.attrib .dot{width:3px;height:3px;border-radius:50%;background:var(--rule)}

.majlis{margin:12px 0;padding:11px;background:var(--paper-2);border-radius:7px}
.majlis .row{display:flex;gap:8px;padding:4px 0;font-size:12.5px}
.majlis .k{flex:none;width:82px;color:var(--ink-soft)}
.majlis .v{font-weight:500}
.majlis .v em{font-style:normal;color:var(--emerald)}
.majlis svg{width:15px;height:15px}

.sources{margin:12px 0}
.sources h3{font-size:12.5px;color:var(--ink-soft);margin-bottom:6px}
.sources ol{margin:0;padding-inline-start:17px}
.sources li{font-size:11.5px;line-height:1.75;margin-bottom:3px;color:var(--ink-soft)}

.colophon{margin-top:14px;padding-top:12px;border-top:1px solid var(--rule);text-align:center}
.colophon::before{content:''}
.colophon .note{font-size:11.5px;color:var(--ink-soft);line-height:1.7}
.colophon .sep{width:30px;height:1px;background:var(--rule);margin:8px auto}
.mosque-name{font-size:14px;font-weight:600;color:var(--emerald-deep)}
.mosque-latin{font-size:11px;letter-spacing:.05em;color:var(--ink-soft)}
.links{display:flex;justify-content:center;gap:11px;margin-top:8px;flex-wrap:wrap}
.links a{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;color:var(--emerald);text-decoration:none}
.links a:hover{text-decoration:underline}
.links a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.links svg{width:13px;height:13px}

.actions{display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap}
.act{
  display:inline-flex;align-items:center;gap:5px;
  font-family:"IBM Plex Sans Arabic",sans-serif;font-size:13px;
  padding:8px 15px;border-radius:20px;cursor:pointer;
  background:var(--paper-2);color:var(--ink);border:1px solid var(--rule);
}
.act:hover{background:var(--paper-3)}
.act:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.act svg{width:14px;height:14px}
.act.primary{background:var(--emerald);color:var(--paper);border-color:transparent}
.act.primary:hover{background:var(--emerald-deep)}
.act.primary:focus-visible{outline:2px solid var(--emerald-deep);outline-offset:2px}

.print-tip{position:fixed;inset:0;z-index:50;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45);padding:15px}
.print-tip.open{display:flex}
.print-tip .box{background:var(--paper);border-radius:10px;padding:20px;max-width:400px;width:100%}
.print-tip h3{font-size:16px;color:var(--emerald-deep);margin-bottom:8px}
.print-tip p{font-size:13px;color:var(--ink-soft)}
.print-tip ol{margin:0 0 15px;padding-inline-start:18px;font-size:13px;line-height:1.9}
.print-tip ol b{color:var(--emerald-deep);font-weight:600}
.print-tip .acts{display:flex;gap:8px;flex-wrap:wrap}
.print-tip button{
  flex:1 1 120px;font-family:"IBM Plex Sans Arabic",sans-serif;font-size:13.5px;
  padding:8px;border-radius:7px;cursor:pointer;border:1px solid var(--rule);
}
.print-tip .go{background:var(--emerald);color:var(--paper);border-color:transparent}
.print-tip .go:hover{background:var(--emerald-deep)}
.print-tip .cancel{background:var(--paper-2);color:var(--ink)}
.print-tip .cancel:hover{background:var(--paper-3)}

.toast{
  position:fixed;bottom:18px;inset-inline-start:50%;transform:translateX(50%) translateY(70px);
  background:var(--emerald-deep);color:var(--paper);font-size:13px;
  padding:9px 18px;border-radius:18px;opacity:0;transition:.25s ease;z-index:60;
}
.toast.show{opacity:1;transform:translateX(50%) translateY(0)}

@media (max-width:640px){
  body{font-size:14.5px}
  .wrap{padding:0 13px 32px}
  .unwan{padding:20px 0 12px}
  .unwan h1{font-size:22px}
  .pair,.paths{grid-template-columns:1fr}
  .night{padding:13px}
  .colophon{margin-top:12px}
}

@media print{
  body{background:#FFF;font-size:10.5pt}
  .wrap{max-width:100%;padding:0}
  .actions,.print-tip,.toast{display:none}
  .unwan h1{color:#000}
  .unwan .sub{color:#333}
  .night{background:#F2F2F2;color:#000;border:1px solid #999}
  .night h2,.night .q{color:#000}
  .night .note{color:#333}
  .checks label{background:none;border:1px solid #CCC}
  .checks input{border-color:#666}
  .sacred,.imam,.axis-card,.pillar,.quad a,.path,.closing,.ayah-hero{break-inside:avoid;page-break-inside:avoid}
  .brief-body>section{break-inside:avoid;page-break-inside:avoid}
  .sec-head{break-after:avoid}
  .colophon{border-top-color:#CCC}
  a{color:#000;text-decoration:none}
}
