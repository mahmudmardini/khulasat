{{-- قالب «المجلّة» — T-45.

     تحريريٌّ مطبوعُ الطابع: خطٌّ مشبَك، وخطوطٌ رفيعة بدل البطاقات، وأقسامٌ
     مرقّمة تلقائياً بعدّاد CSS. للدرس الطويل ولمن يقرأ على الورق.

     **والشواهد هنا اقتباساتٌ لا بطاقات** — تُزاح عن العمود بخطٍّ جانبيّ
     رفيع، كما تُصفّ في الكتب، لا كما تُعرض في الشاشات.

     والألوان من متغيّرات `Palette` نفسها، و`:root` تُحقن بعد هذه الورقة
     فتغلب القيمَ أدناه. --}}
@verbatim
:root{
  --paper:#FBFAF7;
  --paper-2:#F2F0EA;
  --paper-3:#E6E2D8;
  --emerald:#24544A;
  --emerald-deep:#14322C;
  --emerald-soft:#4C7A6E;
  --gold:#8A6A2F;
  --gold-light:#B9975B;
  --clay:#8C3A2B;
  --clay-soft:#AE6A5E;
  --ink:#1A1815;
  --ink-soft:#5A554C;
  --rule:rgba(26,24,21,.16);
  --maxw:720px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  background:var(--paper);
  color:var(--ink);
  font-family:"Amiri",Georgia,serif;
  font-size:19px;
  font-weight:400;
  line-height:2.05;
  -webkit-font-smoothing:antialiased;
}
.wrap{position:relative;max-width:var(--maxw);margin:0 auto;padding:0 24px 90px}

/* ── الطباعة ── */
h1,h2,h3,h4{font-family:"Amiri",Georgia,serif;font-weight:700;line-height:1.45;margin:0}
.ruqaa{font-family:"Aref Ruqaa",serif}
p{margin:0 0 1.1em}
.lead{
  font-size:21px;line-height:2.1;color:var(--ink);
}
.muted{color:var(--ink-soft);font-size:.94em}

/* ── العنوان: صفٌّ تحريريّ لا لافتة ── */
.unwan{margin:56px 0 0;padding-bottom:26px;border-bottom:2px solid var(--ink);text-align:center}
.unwan .crest{display:none}
.unwan .inner{max-width:100%}
.unwan h1{font-size:35px;font-weight:700;letter-spacing:-.01em;color:var(--ink)}
.unwan .sub{
  margin-top:12px;font-size:17px;font-style:italic;font-weight:400;color:var(--ink-soft);
}
.unwan .divider{
  width:100%;height:1px;margin:20px 0 0;background:var(--rule);
}
/* سرلوحٌ فاتح، فشريطُ البيانات حبريٌّ على أرضٍ أخفّ — T-108.
   والوسمُ أدقّ عمداً: ورقةُ الهوية بعدها، وفيها اللونُ الورقيُّ للداكنة. */
header.unwan .attrib{color:var(--ink);background:rgba(0,0,0,.045)}

/* ── النسبة والمجلس ── */
.attrib{
  display:flex;flex-wrap:wrap;align-items:baseline;justify-content:center;gap:7px;
  margin:14px 0 0;padding-bottom:16px;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:13px;font-weight:400;letter-spacing:.02em;color:var(--ink-soft);
  border-bottom:1px solid var(--rule);
}
.attrib b{font-weight:600;color:var(--ink)}
.attrib .dot{width:2px;height:2px;border-radius:50%;background:var(--ink-soft);opacity:.6}

.majlis{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0 22px;
  margin:0 0 40px;padding:16px 0;border-bottom:1px solid var(--rule);
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
}
.majlis .row{display:flex;flex-direction:column;gap:2px;padding:6px 0}
.majlis .k{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-soft)}
.majlis .v{font-size:14.5px;font-weight:500;color:var(--ink)}
.majlis .v em{font-style:italic;font-weight:400;color:var(--ink-soft)}
.majlis svg{width:14px;height:14px;opacity:.45}

/* ── عناوين الأقسام: مرقّمة بعدّاد ── */
.wrap{counter-reset:sec}
.sec-head{
  display:flex;align-items:baseline;gap:13px;margin:52px 0 18px;
  counter-increment:sec;
}
.sec-head::before{
  content:counter(sec,decimal-arabic);
  flex:none;font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:13px;font-weight:600;color:var(--gold);
  padding-top:4px;
}
.sec-head h2{font-size:25px;font-weight:700;color:var(--ink)}
.sec-head .mark{display:none}
.sec-head .rule{flex:1;height:1px;background:var(--rule);align-self:center}

/* ── الشواهد: اقتباسٌ مُزاح لا بطاقة ── */
.sacred{
  margin:28px 0;padding:2px 22px;
  border-inline-start:2px solid var(--gold);
}
.sacred .text{
  margin:0;font-size:22px;line-height:2.3;color:var(--emerald-deep);
}
.sacred .text.hadith{font-size:20px;line-height:2.2;color:var(--ink)}
.sacred .src{
  display:block;margin-top:10px;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:12.5px;letter-spacing:.02em;color:var(--ink-soft);
}
.sacred.warn{border-inline-start-color:var(--clay)}
.sacred.warn .src{color:var(--clay)}
.sacred.dark{
  border:0;padding:24px 26px;background:var(--emerald-deep);border-radius:2px;
}
.sacred.dark .text{color:#fff}
.sacred.dark .src{color:rgba(255,255,255,.66)}

.ayah-hero{
  margin:34px 0;padding:30px 0;text-align:center;
  border-top:1px solid var(--rule);border-bottom:1px solid var(--rule);
}
.ayah-hero .src{
  display:block;margin-top:12px;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:12.5px;color:var(--ink-soft);
}
.ayah-no{display:inline-block;margin:0 3px;font-size:.8em;color:var(--gold)}

/* ── البطاقات الثلاثية: صفوفٌ بخطٍّ فاصل ── */
.trio{display:grid;gap:0;margin:26px 0;border-top:1px solid var(--rule)}
.imam{padding:18px 0;border-bottom:1px solid var(--rule)}
.imam .ico{display:none}
.imam h3{
  font-size:17px;font-weight:700;margin-bottom:5px;color:var(--emerald-deep);
}
.imam p{font-size:17px;line-height:1.95;margin:0;color:var(--ink-soft)}

/* ── المقارنة ── */
.compare{display:grid;gap:22px;margin:28px 0}
.axis-card{padding:0}
.axis-q{
  font-size:18px;font-weight:700;margin-bottom:12px;color:var(--ink);
  padding-bottom:7px;border-bottom:1px solid var(--rule);
}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.side{padding:0 0 0 0}
.side p{margin:0;font-size:16.5px;line-height:1.95;color:var(--ink-soft)}
.side.calm{border-inline-start:2px solid var(--emerald-soft);padding-inline-start:14px}
.side.panic{border-inline-start:2px solid var(--clay-soft);padding-inline-start:14px}
.tag{
  display:block;margin-bottom:7px;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
}
.side.calm .tag{color:var(--emerald)}
.side.panic .tag{color:var(--clay)}

/* ── المجالات ── */
.quad{display:grid;grid-template-columns:1fr 1fr;gap:0;margin:26px 0;border-top:1px solid var(--rule)}
.quad a{
  display:block;padding:16px 0;text-decoration:none;color:inherit;
  border-bottom:1px solid var(--rule);
}
.quad a:hover h3{color:var(--emerald)}
.quad a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.quad .ico{display:none}
.quad h3{font-size:16.5px;font-weight:700;margin-bottom:3px;transition:color .15s ease}
.quad span{font-size:15px;color:var(--ink-soft)}

/* ── الأركان والخطوات ── */
.pillar{margin:30px 0;padding:0}
.pillar-title{
  display:flex;align-items:baseline;gap:10px;margin-bottom:14px;
  padding-bottom:7px;border-bottom:1px solid var(--rule);
}
.pillar-title .ico{display:none}
.pillar-title h3{font-size:19px;font-weight:700;color:var(--emerald-deep)}
.step{display:flex;gap:14px;padding:12px 0;border-bottom:1px dotted var(--rule)}
.step:last-of-type{border-bottom:0}
.step .num{
  flex:none;font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:13px;font-weight:600;color:var(--gold);padding-top:5px;
}
.step h4{font-size:17px;font-weight:700;margin-bottom:3px}
.step p{margin:0;font-size:16.5px;line-height:1.95;color:var(--ink-soft)}

/* ── المسارات ── */
.paths{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:28px 0}
.path{padding:0}
.path h4{
  font-size:16px;font-weight:700;margin-bottom:11px;padding-bottom:6px;
  border-bottom:1px solid var(--rule);
}
.path.good h4{color:var(--emerald-deep)}
.path.bad h4{color:var(--clay)}
.node{
  padding:7px 0 7px 0;font-size:16px;color:var(--ink-soft);
  border-bottom:1px dotted var(--rule);
}
.node.final{font-weight:700;color:var(--ink);border-bottom:0}
.path.good .node.final{color:var(--emerald-deep)}
.path.bad .node.final{color:var(--clay)}
.arrow{display:none}

/* ── الليل ── */
.night{
  margin:40px 0;padding:32px 28px;
  background:var(--emerald-deep);color:#fff;border-radius:2px;
}
.night h2{font-size:23px;font-weight:700;color:#fff;margin-bottom:12px}
.night .moon{display:block;margin-bottom:12px;opacity:.5}
.night .q{font-size:20px;line-height:2.15;color:rgba(255,255,255,.95)}
.night .note{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:13.5px;color:rgba(255,255,255,.6);
}
.checks{display:grid;gap:8px;margin-top:16px}
.checks label{
  display:flex;align-items:center;gap:11px;cursor:pointer;
  font-size:17px;color:rgba(255,255,255,.9);
}
.checks label:hover{color:#fff}
.checks input{
  appearance:none;flex:none;width:17px;height:17px;
  border:1px solid rgba(255,255,255,.45);background:transparent;cursor:pointer;
  position:relative;transition:background .15s ease,border-color .15s ease;
}
.checks input:checked{background:var(--gold-light);border-color:var(--gold-light)}
.checks input:checked::after{
  content:"";position:absolute;inset:0;
  background:no-repeat center/10px url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%2314322C' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><path d='M3 8.5l3.5 3.5L13 5'/></svg>");
}
.checks input:focus-visible{outline:2px solid var(--gold-light);outline-offset:2px}

/* ── الختام ── */
.closing{
  margin:42px 0 0;padding:26px 0 0;text-align:center;
  font-size:20px;line-height:2.15;font-style:italic;color:var(--emerald-deep);
  border-top:2px solid var(--ink);
}

/* ── المصادر ── */
.sources{margin:44px 0 0;padding-top:22px;border-top:1px solid var(--rule)}
.sources h3{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:12.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
  margin-bottom:11px;color:var(--ink-soft);
}
.sources ol{margin:0;padding-inline-start:20px}
.sources li{font-size:15.5px;line-height:1.9;color:var(--ink-soft);margin-bottom:4px}

/* ── الحاشية ── */
.colophon{margin:46px 0 0;padding-top:26px;text-align:center;border-top:1px solid var(--rule)}
.colophon::before{content:none}
.colophon .note{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:12.5px;line-height:1.9;color:var(--ink-soft);margin:0 0 10px;
}
.colophon .sep{width:28px;height:1px;margin:14px auto;background:var(--rule)}
.mosque-name{font-size:19px;font-weight:700;color:var(--emerald-deep)}
.mosque-latin{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:11px;letter-spacing:.14em;color:var(--ink-soft);text-transform:uppercase;
}
.links{display:flex;justify-content:center;gap:16px;margin-top:14px}
.links a{
  display:flex;align-items:center;gap:6px;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:12.5px;text-decoration:none;color:var(--ink-soft);
  border-bottom:1px solid transparent;transition:color .15s ease,border-color .15s ease;
}
.links a:hover{color:var(--emerald);border-bottom-color:var(--emerald-soft)}
.links a:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.links svg{width:14px;height:14px}

/* ── الأفعال ── */
.actions{display:flex;justify-content:center;gap:12px;margin:30px 0 0;flex-wrap:wrap}
.act{
  display:inline-flex;align-items:center;gap:7px;padding:9px 17px;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:13.5px;font-weight:500;cursor:pointer;
  color:var(--ink);background:transparent;
  border:1px solid var(--rule);border-radius:2px;
  transition:border-color .15s ease,color .15s ease;
}
.act:hover{border-color:var(--emerald);color:var(--emerald)}
.act:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}
.act svg{width:15px;height:15px}
.act.primary{background:var(--emerald-deep);color:#fff;border-color:var(--emerald-deep)}
.act.primary:hover{background:var(--ink);border-color:var(--ink);color:#fff}
.act.primary:focus-visible{outline:2px solid var(--emerald);outline-offset:2px}

/* ── نصيحة الطباعة ── */
.print-tip{
  position:fixed;inset:0;z-index:50;display:none;
  align-items:center;justify-content:center;padding:20px;
  background:rgba(26,24,21,.5);
}
.print-tip.open{display:flex}
.print-tip .box{
  max-width:420px;width:100%;padding:26px;
  background:var(--paper);border:1px solid var(--rule);border-radius:2px;
  box-shadow:0 18px 50px rgba(26,24,21,.24);
}
.print-tip h3{font-size:20px;font-weight:700;margin-bottom:10px}
.print-tip p{font-size:16px;color:var(--ink-soft)}
.print-tip ol{margin:0 0 18px;padding-inline-start:20px;font-size:16px;line-height:1.95}
.print-tip ol b{font-weight:700;color:var(--ink)}
.print-tip .acts{display:flex;gap:9px;justify-content:flex-end}
.print-tip button{
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:13.5px;font-weight:500;padding:9px 17px;
  border-radius:2px;cursor:pointer;border:1px solid var(--rule);background:transparent;
}
.print-tip .go{background:var(--emerald-deep);color:#fff;border-color:var(--emerald-deep)}
.print-tip .go:hover{background:var(--ink);border-color:var(--ink)}
.print-tip .cancel{color:var(--ink-soft)}
.print-tip .cancel:hover{border-color:var(--ink-soft)}

/* ── التنبيه ── */
.toast{
  position:fixed;inset-inline-start:50%;bottom:26px;transform:translate(50%,16px);
  z-index:60;padding:11px 20px;border-radius:2px;
  background:var(--ink);color:#fff;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;font-size:13.5px;
  opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;
}
.toast.show{opacity:1;transform:translate(50%,0)}

/* ── الجوال ── */
@media (max-width:640px){
  body{font-size:18px}
  .wrap{padding:0 18px 70px}
  .unwan{margin:34px 0 0}
  .unwan h1{font-size:28px}
  .pair,.paths,.quad{grid-template-columns:1fr}
  .night{margin:30px -18px;padding:28px 20px;border-radius:0}
}

/* ── الطباعة: هذا القالب مطبوعُ الطابع أصلاً ── */
@media print{
  body{background:#fff;font-size:11.5pt;line-height:1.85}
  .wrap{max-width:100%;padding:0}
  .actions,.print-tip,.toast{display:none!important}
  .night{
    background:#fff;color:var(--ink);
    border:1px solid var(--rule);margin:20px 0;padding:20px;
  }
  .night h2,.night .q{color:var(--emerald-deep)}
  .night .note{color:var(--ink-soft)}
  .checks label{color:var(--ink)}
  .checks input{border-color:var(--ink-soft)}
  .sacred.dark{background:#fff;color:var(--ink);border:1px solid var(--rule)}
  .sacred.dark .text{color:var(--emerald-deep)}
  .sacred.dark .src{color:var(--ink-soft)}
  .sacred,.imam,.axis-card,.pillar,.path,.closing,.ayah-hero,.step{
    break-inside:avoid;page-break-inside:avoid;
  }
  .sec-head{break-after:avoid;page-break-after:avoid}
  a{text-decoration:none;color:inherit}
}
@endverbatim
