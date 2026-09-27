{{-- قالب «العصري» — T-45.

     أبيضُ نظيفٌ ومسافاتٌ واسعة، بلا زخرفةٍ خلفية ولا خطٍّ مشبَك: للدرس
     التعليميّ والمحتوى العامّ حيث تكون الزخرفة الشرعية في غير موضعها.

     **والألوان من متغيّرات `Palette` نفسها**، فلوحة الجهة تعمل هنا كما
     تعمل في `classic` بلا إعدادٍ ثانٍ. وتُحقن `:root` بعد هذه الورقة،
     فتغلب القيمَ أدناه. --}}
@verbatim
:root{
  --paper:#FFFFFF;
  --paper-2:#F7F8F9;
  --paper-3:#EDEFF2;
  --emerald:#1F6F5C;
  --emerald-deep:#12463A;
  --emerald-soft:#4E9481;
  --gold:#B07C2A;
  --gold-light:#D8AE68;
  --clay:#A6402F;
  --clay-soft:#C8756A;
  --ink:#161A1D;
  --ink-soft:#5C6670;
  --rule:rgba(22,26,29,.12);
  --maxw:820px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  background:var(--paper-2);
  color:var(--ink);
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:17px;
  font-weight:300;
  line-height:1.95;
  -webkit-font-smoothing:antialiased;
}
.wrap{
  position:relative;
  max-width:var(--maxw);
  margin:0 auto;
  padding:0 20px 96px;
  background:var(--paper);
  box-shadow:0 1px 3px rgba(22,26,29,.05),0 12px 40px rgba(22,26,29,.06);
}

/* ── الطباعة ── */
h1,h2,h3,h4{font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;font-weight:600;line-height:1.5;margin:0}
.ruqaa{font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;font-weight:600}
p{margin:0 0 1.05em}
.lead{font-size:19px;line-height:2.05;color:var(--ink)}
.muted{color:var(--ink-soft)}

/* ── العنوان ── */
.unwan{margin:0 -20px 26px;padding:44px 28px 30px;background:var(--emerald);color:#fff;text-align:center}
/* وحدَه هذا القالبُ يُحشّي السرلوحَ جانبياً، فشريطُ البيانات الغائر يُلغي
   حشوتَه هو أيضاً ليبلغ الحافّتين — T-108. وسرلوحُه داكن، فلونُ الشريط
   الافتراضيُّ (الورقيّ) هو الصحيح فيه. */
.unwan .attrib{margin-inline:-28px}
.unwan .crest{display:none}
.unwan .inner{max-width:640px;margin:0 auto}
.unwan h1{font-size:31px;font-weight:600;color:#fff;letter-spacing:-.01em}
.unwan .sub{margin-top:10px;font-size:16px;font-weight:300;color:rgba(255,255,255,.82)}
.unwan .divider{
  width:44px;height:3px;margin:18px auto 0;border-radius:2px;
  background:var(--gold-light);
}

/* ── النسبة والمجلس ── */
.attrib{
  display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px;
  margin:0 0 26px;padding-bottom:20px;
  font-size:14px;color:var(--ink-soft);border-bottom:1px solid var(--rule);
}
.attrib b{font-weight:600;color:var(--ink)}
.attrib .dot{width:3px;height:3px;border-radius:50%;background:var(--rule)}

.majlis{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;
  margin:0 0 34px;background:var(--rule);border:1px solid var(--rule);border-radius:10px;overflow:hidden;
}
.majlis .row{display:flex;flex-direction:column;gap:3px;padding:13px 15px;background:var(--paper)}
.majlis .k{font-size:12px;font-weight:500;color:var(--ink-soft)}
.majlis .v{font-size:15px;font-weight:500;color:var(--ink)}
.majlis .v em{font-style:normal;font-weight:300;color:var(--ink-soft)}
.majlis svg{width:15px;height:15px;opacity:.5}

/* ── عناوين الأقسام ── */
.sec-head{display:flex;align-items:center;gap:12px;margin:46px 0 20px}
.sec-head h2{font-size:22px;font-weight:600;color:var(--emerald-deep);white-space:nowrap}
.sec-head .mark{
  flex:none;width:5px;height:24px;border-radius:3px;background:var(--gold);
}
.sec-head .rule{flex:1;height:1px;background:var(--rule)}

/* ── الشواهد ── */
.sacred{
  margin:26px 0;padding:22px 24px;
  background:var(--paper-2);
  border:1px solid var(--rule);
  border-inline-start:4px solid var(--emerald);
  border-radius:10px;
}
.sacred .text{
  margin:0;font-size:20px;line-height:2.25;font-weight:400;color:var(--emerald-deep);
}
.sacred .text.hadith{font-size:18.5px;line-height:2.15;color:var(--ink)}
.sacred .src{
  display:block;margin-top:12px;font-size:13px;font-weight:500;color:var(--ink-soft);
}
.sacred.warn{border-inline-start-color:var(--clay);background:rgba(166,64,47,.045)}
.sacred.warn .src{color:var(--clay)}
.sacred.dark{background:var(--emerald-deep);border-color:transparent}
.sacred.dark .text{color:#fff}
.sacred.dark .src{color:rgba(255,255,255,.7)}

.ayah-hero{
  margin:30px 0;padding:34px 26px;text-align:center;
  background:linear-gradient(180deg,var(--paper-2),var(--paper));
  border:1px solid var(--rule);border-radius:14px;
}
.ayah-hero .src{display:block;margin-top:14px;font-size:13px;font-weight:500;color:var(--ink-soft)}
.ayah-no{
  display:inline-block;margin:0 4px;font-size:.82em;color:var(--gold);font-weight:500;
}

/* ── البطاقات الثلاثية ── */
.trio{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:26px 0}
.imam{
  padding:20px;background:var(--paper);border:1px solid var(--rule);border-radius:12px;
  transition:border-color .15s ease,box-shadow .15s ease;
}
.imam:hover{border-color:var(--emerald-soft);box-shadow:0 3px 12px rgba(22,26,29,.06)}
.imam .ico{
  display:flex;align-items:center;justify-content:center;
  width:34px;height:34px;margin-bottom:12px;border-radius:9px;
  background:rgba(31,111,92,.1);color:var(--emerald);
}
.imam h3{font-size:16px;font-weight:600;margin-bottom:7px;color:var(--emerald-deep)}
.imam p{font-size:14.5px;line-height:1.85;margin:0;color:var(--ink-soft)}

/* ── المقارنة ── */
.compare{display:grid;gap:16px;margin:26px 0}
.axis-card{padding:20px;background:var(--paper-2);border:1px solid var(--rule);border-radius:12px}
.axis-q{font-size:16px;font-weight:600;margin-bottom:14px;color:var(--emerald-deep)}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.side{padding:15px;border-radius:9px;background:var(--paper);border:1px solid var(--rule)}
.side p{margin:0;font-size:14.5px;line-height:1.85}
.side.calm{border-color:rgba(31,111,92,.35);background:rgba(31,111,92,.05)}
.side.panic{border-color:rgba(166,64,47,.3);background:rgba(166,64,47,.04)}
.tag{
  display:inline-block;margin-bottom:8px;padding:3px 10px;border-radius:20px;
  font-size:12px;font-weight:600;
}
.side.calm .tag{background:rgba(31,111,92,.14);color:var(--emerald-deep)}
.side.panic .tag{background:rgba(166,64,47,.12);color:var(--clay)}

/* ── المجالات ── */
.quad{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:26px 0}
.quad a{
  display:block;padding:18px;text-decoration:none;color:inherit;
  background:var(--paper);border:1px solid var(--rule);border-radius:12px;
  transition:border-color .15s ease,transform .15s ease;
}
.quad a:hover{border-color:var(--emerald-soft);transform:translateY(-2px)}
.quad a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.quad .ico{
  display:flex;align-items:center;justify-content:center;
  width:32px;height:32px;margin-bottom:10px;border-radius:9px;
  background:rgba(176,124,42,.12);color:var(--gold);
}
.quad h3{font-size:15.5px;font-weight:600;margin-bottom:5px}
.quad span{font-size:13.5px;color:var(--ink-soft)}

/* ── الأركان والخطوات ── */
.pillar{margin:26px 0;padding:22px;background:var(--paper-2);border:1px solid var(--rule);border-radius:12px}
.pillar-title{display:flex;align-items:center;gap:11px;margin-bottom:16px}
.pillar-title .ico{
  display:flex;align-items:center;justify-content:center;
  width:32px;height:32px;border-radius:9px;background:var(--emerald);color:#fff;flex:none;
}
.pillar-title h3{font-size:17px;font-weight:600;color:var(--emerald-deep)}
.step{display:flex;gap:13px;padding:13px 0;border-top:1px solid var(--rule)}
.step:first-of-type{border-top:0;padding-top:0}
.step .num{
  flex:none;display:flex;align-items:center;justify-content:center;
  width:26px;height:26px;border-radius:50%;
  background:var(--paper-3);color:var(--emerald-deep);
  font-size:13px;font-weight:600;
}
.step h4{font-size:15.5px;font-weight:600;margin-bottom:4px}
.step p{margin:0;font-size:14.5px;line-height:1.85;color:var(--ink-soft)}

/* ── المسارات ── */
.paths{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:26px 0}
.path{padding:18px;border-radius:12px;border:1px solid var(--rule);background:var(--paper)}
.path h4{font-size:15px;font-weight:600;margin-bottom:12px}
.path.good{border-color:rgba(31,111,92,.35);background:rgba(31,111,92,.04)}
.path.good h4{color:var(--emerald-deep)}
.path.bad{border-color:rgba(166,64,47,.3);background:rgba(166,64,47,.035)}
.path.bad h4{color:var(--clay)}
.node{
  padding:9px 13px;margin-bottom:8px;border-radius:8px;
  background:var(--paper);border:1px solid var(--rule);
  font-size:14px;
}
.path.bad .node{border-color:rgba(166,64,47,.2)}
.node.final{font-weight:600;background:var(--emerald);color:#fff;border-color:transparent}
.path.good .node.final{background:var(--emerald)}
.path.bad .node.final{background:var(--clay);color:#fff}
.arrow{display:block;text-align:center;color:var(--ink-soft);opacity:.45;font-size:13px;line-height:1}

/* ── الليل ── */
.night{
  margin:34px -20px;padding:38px 28px;
  background:var(--emerald-deep);color:#fff;
}
.night h2{font-size:21px;font-weight:600;color:#fff;margin-bottom:14px}
.night .moon{display:block;margin-bottom:14px;opacity:.55}
.night .q{font-size:18px;line-height:2.1;color:rgba(255,255,255,.94)}
.night .note{font-size:14px;color:rgba(255,255,255,.62)}
.checks{display:grid;gap:9px;margin-top:16px}
.checks label{
  display:flex;align-items:center;gap:10px;cursor:pointer;
  font-size:15px;color:rgba(255,255,255,.9);
}
.checks label:hover{color:#fff}
.checks input{
  appearance:none;flex:none;width:19px;height:19px;border-radius:6px;
  border:1.5px solid rgba(255,255,255,.42);background:transparent;cursor:pointer;
  position:relative;transition:background .15s ease,border-color .15s ease;
}
.checks input:checked{background:var(--gold);border-color:var(--gold)}
.checks input:checked::after{
  content:"";position:absolute;inset:0;
  background:no-repeat center/11px url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='white' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><path d='M3 8.5l3.5 3.5L13 5'/></svg>");
}
.checks input:focus-visible{outline:2px solid var(--gold-light);outline-offset:2px}

/* ── الختام ── */
.closing{
  margin:38px 0 0;padding:26px;text-align:center;
  font-size:18px;line-height:2.1;color:var(--emerald-deep);
  background:var(--paper-2);border:1px solid var(--rule);border-radius:12px;
}

/* ── المصادر ── */
.sources{margin:40px 0 0;padding-top:24px;border-top:1px solid var(--rule)}
.sources h3{font-size:15px;font-weight:600;margin-bottom:12px;color:var(--ink-soft)}
.sources ol{margin:0;padding-inline-start:20px}
.sources li{font-size:14px;line-height:1.9;color:var(--ink-soft);margin-bottom:5px}

/* ── الحاشية ── */
.colophon{
  margin:44px -20px -96px;padding:30px 28px 36px;
  text-align:center;background:var(--paper-2);border-top:1px solid var(--rule);
}
.colophon::before{content:none}
.colophon .note{font-size:13px;line-height:1.9;color:var(--ink-soft);margin:0 0 10px}
.colophon .sep{
  width:36px;height:2px;margin:14px auto;border-radius:2px;background:var(--rule);
}
.mosque-name{font-size:16px;font-weight:600;color:var(--emerald-deep)}
.mosque-latin{font-size:12px;letter-spacing:.1em;color:var(--ink-soft);text-transform:uppercase}
.links{display:flex;justify-content:center;gap:10px;margin-top:14px}
.links a{
  display:flex;align-items:center;gap:6px;padding:7px 13px;
  font-size:13px;text-decoration:none;color:var(--ink-soft);
  border:1px solid var(--rule);border-radius:20px;background:var(--paper);
  transition:color .15s ease,border-color .15s ease;
}
.links a:hover{color:var(--emerald);border-color:var(--emerald-soft)}
.links a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.links svg{width:15px;height:15px}

/* ── الأفعال ── */
.actions{display:flex;justify-content:center;gap:10px;margin:30px 0 0;flex-wrap:wrap}
.act{
  display:inline-flex;align-items:center;gap:7px;padding:10px 18px;
  font-family:inherit;font-size:14px;font-weight:500;cursor:pointer;
  color:var(--ink);background:var(--paper);
  border:1px solid var(--rule);border-radius:9px;
  transition:border-color .15s ease,background .15s ease;
}
.act:hover{border-color:var(--emerald-soft);background:var(--paper-2)}
.act:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.act svg{width:16px;height:16px}
.act.primary{background:var(--emerald);color:#fff;border-color:transparent}
.act.primary:hover{background:var(--emerald-deep)}
.act.primary:focus-visible{outline:2px solid var(--emerald-deep);outline-offset:2px}

/* ── نصيحة الطباعة ── */
.print-tip{
  position:fixed;inset:0;z-index:50;display:none;
  align-items:center;justify-content:center;padding:20px;
  background:rgba(22,26,29,.45);
}
.print-tip.open{display:flex}
.print-tip .box{
  max-width:420px;width:100%;padding:26px;
  background:var(--paper);border-radius:14px;
  box-shadow:0 16px 48px rgba(22,26,29,.22);
}
.print-tip h3{font-size:18px;font-weight:600;margin-bottom:10px}
.print-tip p{font-size:14.5px;color:var(--ink-soft)}
.print-tip ol{margin:0 0 18px;padding-inline-start:20px;font-size:14.5px;line-height:1.95}
.print-tip ol b{font-weight:600;color:var(--ink)}
.print-tip .acts{display:flex;gap:9px;justify-content:flex-end}
.print-tip button{
  font-family:inherit;font-size:14px;font-weight:500;padding:9px 17px;
  border-radius:9px;cursor:pointer;border:1px solid var(--rule);background:var(--paper);
}
.print-tip .go{background:var(--emerald);color:#fff;border-color:transparent}
.print-tip .go:hover{background:var(--emerald-deep)}
.print-tip .cancel{color:var(--ink-soft)}
.print-tip .cancel:hover{background:var(--paper-2)}

/* ── التنبيه ── */
.toast{
  position:fixed;inset-inline-start:50%;bottom:26px;transform:translate(50%,16px);
  z-index:60;padding:11px 20px;border-radius:9px;
  background:var(--ink);color:#fff;font-size:14px;
  opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;
}
.toast.show{opacity:1;transform:translate(50%,0)}

/* ── الجوال ── */
@media (max-width:640px){
  body{font-size:16px}
  .wrap{padding:0 16px 72px}
  .unwan{margin:0 -16px 22px;padding:34px 20px 24px}
  /* والحشوةُ تصغر على الجوال، فيتبعها الشريط — T-108. */
  .unwan .attrib{margin-inline:-20px}
  .unwan h1{font-size:25px}
  .pair,.paths{grid-template-columns:1fr}
  .night{margin:28px -16px;padding:30px 20px}
  .colophon{margin:36px -16px -72px}
}

/* ── الطباعة ── */
@media print{
  body{background:#fff;font-size:12pt}
  .wrap{box-shadow:none;max-width:100%;padding:0}
  .actions,.print-tip,.toast{display:none!important}
  .unwan{background:#fff;color:var(--ink);border-bottom:2px solid var(--emerald);margin:0 0 18px;padding:0 0 16px}
  .unwan h1{color:var(--emerald-deep)}
  .unwan .sub{color:var(--ink-soft)}
  .night{background:#fff;color:var(--ink);border:1px solid var(--rule);border-radius:10px;margin:20px 0;padding:22px}
  .night h2,.night .q{color:var(--emerald-deep)}
  .night .note{color:var(--ink-soft)}
  .checks label{color:var(--ink)}
  .checks input{border-color:var(--ink-soft)}
  .sacred,.imam,.axis-card,.pillar,.quad a,.path,.closing,.ayah-hero{
    break-inside:avoid;page-break-inside:avoid;
  }
  .sec-head{break-after:avoid;page-break-after:avoid}
  .colophon{margin:24px 0 0;background:#fff}
  a{text-decoration:none;color:inherit}
}
@endverbatim
