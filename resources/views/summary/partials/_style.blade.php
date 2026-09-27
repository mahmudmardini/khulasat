{{-- ورقة أنماط القالب — منقولٌ حرفاً بحرف من `khulasah.skill`.
     `@@verbatim` تمنع Blade من تفسير `@media` و`{{` وما شابه، فيخرج
     الملفّ كما دخل بايتاً ببايت. **ولا يُعدَّل منه حرف** — CLAUDE.md
     §2 القاعدة الأولى. --}}
@verbatim
:root{
  --paper:#F3EEE1;
  --paper-2:#E9E1CD;
  --paper-3:#DED3B9;
  --emerald:#1B4D3E;
  --emerald-deep:#0E2C24;
  --emerald-soft:#3A6B58;
  --gold:#A87C33;
  --gold-light:#D2AC63;
  --clay:#8E3B2E;
  --clay-soft:#B0685C;
  --ink:#22201A;
  --ink-soft:#565043;
  --rule:rgba(168,124,51,.28);
  --maxw:860px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  background:var(--paper);
  color:var(--ink);
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  font-size:17px;
  font-weight:300;
  line-height:2;
  -webkit-font-smoothing:antialiased;
}
/* ورقة المخطوطة: زخرفة نجمية خفيفة خلف كل شيء */
body::before{
  content:"";
  position:fixed;
  inset:0;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='84' height='84' viewBox='0 0 84 84'><g fill='none' stroke='%23A87C33' stroke-width='1' opacity='0.16'><path d='M42 6 51 24 69 15 60 33 78 42 60 51 69 69 51 60 42 78 33 60 15 69 24 51 6 42 24 33 15 15 33 24Z'/><circle cx='42' cy='42' r='9'/></g></svg>");
  opacity:.5;
  pointer-events:none;
  z-index:0;
}
.wrap{position:relative;z-index:1;max-width:var(--maxw);margin:0 auto;padding:0 22px 90px}

/* ── الطباعة ── */
h1,h2,h3,h4{font-family:"Amiri",serif;font-weight:700;line-height:1.4;margin:0}
.ruqaa{font-family:"Aref Ruqaa",serif}
p{margin:0 0 1em}
.lead{font-size:19px;line-height:2.1}
.muted{color:var(--ink-soft)}

/* ── العنوان المذهّب (سرلوح) ── */
.unwan{
  margin:34px 0 0;
  background:linear-gradient(165deg,var(--emerald) 0%,var(--emerald-deep) 100%);
  border:1px solid var(--gold);
  border-radius:4px 4px 60px 60px/4px 4px 30px 30px;
  padding:0 0 44px;
  position:relative;
  overflow:hidden;
  box-shadow:0 18px 40px -28px rgba(14,44,36,.9);
}
.unwan .crest{display:block;width:100%;height:auto}
.unwan .inner{padding:0 26px;text-align:center;color:var(--paper)}
.unwan h1{
  font-family:"Aref Ruqaa",serif;
  font-size:clamp(40px,11vw,74px);
  color:#F6EBD2;
  letter-spacing:0;
  margin:6px 0 14px;
  text-shadow:0 2px 0 rgba(0,0,0,.25);
}
.unwan .sub{
  font-family:"Amiri",serif;
  font-size:clamp(17px,4.4vw,23px);
  color:var(--gold-light);
  line-height:1.7;
  margin:0 auto 26px;
  max-width:30ch;
}
.unwan .divider{width:150px;height:14px;margin:0 auto 24px;opacity:.85}
.ayah-hero{
  font-family:"Amiri",serif;
  font-size:clamp(19px,5vw,26px);
  line-height:2.15;
  color:#F1E3C4;
  max-width:34ch;
  margin:0 auto 10px;
}
.ayah-hero .src{
  display:block;
  font-family:"IBM Plex Sans Arabic",sans-serif;
  font-size:13px;
  color:var(--gold-light);
  letter-spacing:.03em;
  margin-top:10px;
  font-weight:400;
}

/* ── الأقسام ── */
section{margin:64px 0 0;scroll-margin-top:70px}
.sec-head{display:flex;align-items:center;gap:14px;margin-bottom:26px}
.sec-head .mark{flex:0 0 auto;width:34px;height:34px}
.sec-head h2{font-size:clamp(24px,6vw,32px);color:var(--emerald)}
.sec-head .rule{flex:1;height:1px;background:linear-gradient(to left,var(--rule),transparent)}

/* ── كتلة النص المقدّس ── */
.sacred{
  background:var(--paper-2);
  border:1px solid var(--rule);
  border-right:4px solid var(--gold);
  border-radius:3px;
  padding:20px 22px 16px;
  margin:22px 0;
}
.sacred .text{
  font-family:"Amiri",serif;
  font-size:clamp(18px,4.6vw,22px);
  line-height:2.25;
  color:#1A2B24;
  margin:0;
}
.sacred .text.hadith{font-size:clamp(17px,4.3vw,20px)}
.sacred .src{
  display:block;
  margin-top:12px;
  padding-top:10px;
  border-top:1px dotted var(--rule);
  font-size:13.5px;
  color:var(--gold);
  font-weight:500;
  line-height:1.8;
}
.sacred.dark{background:rgba(27,77,62,.06)}

/* ── علامة رقم الآية (نجمة ثمانية مرسومة والرقم داخلها) ── */
.ayah-no{
  font-family:"Amiri","Amiri Quran",serif;
  font-size:1.06em;
  font-weight:400;
  margin:0 .1em;
  color:inherit;
}
.sacred.warn{border-right-color:var(--clay);background:rgba(142,59,46,.05)}
.sacred.warn .src{color:var(--clay)}

/* ── بطاقات الأئمة ── */
.trio{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:24px}
.imam{
  background:#FBF8F0;
  border:1px solid var(--rule);
  border-radius:3px 3px 26px 26px/3px 3px 16px 16px;
  padding:22px 18px 20px;
  text-align:center;
}
.imam .ico{width:42px;height:42px;margin:0 auto 12px;display:block}
.imam h3{font-size:21px;color:var(--emerald);margin-bottom:8px}
.imam p{font-size:15.5px;line-height:1.95;margin:0;color:var(--ink-soft)}

/* ── الميزان: سكينة × هلع ── */
.compare{display:grid;gap:16px;margin-top:24px}
.axis-card{border:1px solid var(--rule);border-radius:3px;overflow:hidden;background:#FBF8F0}
.axis-q{
  font-family:"Amiri",serif;
  font-size:19px;
  color:var(--emerald-deep);
  background:var(--paper-3);
  padding:12px 16px;
  text-align:center;
}
.pair{display:grid;grid-template-columns:1fr 1fr}
.side{padding:16px}
.side + .side{border-right:1px solid var(--rule)}
.side.calm{background:rgba(27,77,62,.055)}
.side.panic{background:rgba(142,59,46,.055)}
.tag{
  display:inline-block;
  font-size:12.5px;font-weight:500;
  padding:3px 12px;border-radius:20px;
  margin-bottom:9px;color:#FBF8F0;
}
.side.calm .tag{background:var(--emerald)}
.side.panic .tag{background:var(--clay)}
.side p{margin:0;font-size:15.5px;line-height:1.9}

/* ── المجالات الأربعة ── */
.quad{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:24px}
.quad a{
  display:block;
  text-decoration:none;
  color:inherit;
  background:linear-gradient(160deg,#FBF8F0,#F1EADA);
  border:1px solid var(--rule);
  border-radius:3px;
  padding:20px 18px;
  text-align:center;
  transition:background .25s ease,border-color .25s ease;
}
.quad a:hover,.quad a:focus-visible{background:var(--paper-2);border-color:var(--gold)}
.quad .ico{width:40px;height:40px;margin:0 auto 10px;display:block}
.quad h3{font-size:20px;color:var(--emerald);margin-bottom:4px}
.quad span{font-size:14px;color:var(--ink-soft);line-height:1.7;display:block}

/* ── الركن الواحد ── */
.pillar{margin-top:44px;padding-top:26px;border-top:1px solid var(--rule)}
.pillar-title{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.pillar-title .ico{width:32px;height:32px;flex:0 0 auto}
.pillar-title h3{font-size:24px;color:var(--emerald)}
.step{
  display:grid;
  grid-template-columns:44px 1fr;
  gap:16px;
  align-items:start;
  margin:22px 0;
}
.step .num{
  width:44px;height:44px;border-radius:50%;
  background:var(--emerald);color:#F6EBD2;
  display:grid;place-items:center;
  font-family:"Amiri",serif;font-size:20px;
}
.step h4{font-size:19px;color:var(--emerald-deep);margin-bottom:6px}
.step p{font-size:16px;margin-bottom:0}

/* ── مسار المداومة ── */
.paths{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:26px}
.path{border:1px solid var(--rule);border-radius:3px;padding:18px 16px}
.path.good{background:rgba(27,77,62,.05);border-color:rgba(27,77,62,.3)}
.path.bad{background:rgba(142,59,46,.05);border-color:rgba(142,59,46,.28)}
.path h4{font-family:"Amiri",serif;font-size:19px;text-align:center;margin-bottom:14px}
.path.good h4{color:var(--emerald)}
.path.bad h4{color:var(--clay)}
.node{
  background:#FBF8F0;
  border:1px solid var(--rule);
  border-radius:3px;
  padding:10px 12px;
  font-size:15px;
  line-height:1.75;
  text-align:center;
}
.path.bad .node{transform:rotate(-.5deg)}
.path.bad .node:nth-child(4){transform:rotate(.6deg)}
.arrow{display:block;margin:8px auto;width:14px;height:22px}
.node.final{font-family:"Amiri",serif;font-size:18px;color:#FBF8F0;border:none}
.path.good .node.final{background:var(--emerald)}
.path.bad .node.final{background:var(--clay);transform:none}

/* ── بطاقة الليل ── */
.night{
  margin-top:64px;
  background:linear-gradient(170deg,#123028,#0A1F19);
  border:1px solid var(--gold);
  border-radius:3px;
  padding:34px 26px 30px;
  color:#EFE6D0;
  text-align:center;
  position:relative;
  overflow:hidden;
}
.night .moon{width:46px;height:46px;margin:0 auto 16px;display:block}
.night h2{font-family:"Amiri",serif;font-size:clamp(22px,5.5vw,28px);color:#F6EBD2;margin-bottom:18px}
.night .q{
  font-family:"Amiri",serif;
  font-size:clamp(19px,4.9vw,24px);
  line-height:2.1;
  color:var(--gold-light);
  max-width:32ch;
  margin:0 auto 8px;
}
.night .note{font-size:15px;color:rgba(239,230,208,.72);margin-bottom:24px}
.checks{display:grid;gap:10px;max-width:330px;margin:0 auto;text-align:right}
.checks label{
  display:flex;align-items:center;gap:12px;
  background:rgba(246,235,210,.07);
  border:1px solid rgba(210,172,99,.35);
  border-radius:3px;
  padding:11px 14px;
  font-size:15.5px;
  cursor:pointer;
  transition:background .2s ease;
}
.checks label:hover{background:rgba(246,235,210,.13)}
.checks input{
  appearance:none;-webkit-appearance:none;
  width:19px;height:19px;flex:0 0 auto;
  border:1.5px solid var(--gold-light);
  border-radius:2px;background:transparent;cursor:pointer;
  display:grid;place-items:center;
}
.checks input:checked{background:var(--gold);border-color:var(--gold)}
.checks input:checked::after{content:"✓";color:#0A1F19;font-size:14px;line-height:1}
.checks input:focus-visible{outline:2px solid var(--gold-light);outline-offset:2px}
.checks label:has(input:checked){background:rgba(210,172,99,.18)}

/* ── الخاتمة والتخريج ── */
.closing{
  margin-top:56px;text-align:center;
  font-family:"Amiri",serif;
  font-size:clamp(19px,4.8vw,24px);
  line-height:2.1;color:var(--emerald);
  max-width:32ch;margin-inline:auto;
}

/* ── شريط نسبة المجلس ── */
.attrib{
  display:flex;flex-wrap:wrap;align-items:center;justify-content:center;
  gap:7px 14px;
  margin:20px auto 0;padding:12px 20px;
  max-width:640px;
  background:rgba(27,77,62,.05);
  border-top:1px solid var(--rule);
  border-bottom:1px solid var(--rule);
  font-size:14px;color:var(--ink-soft);text-align:center;line-height:1.8;
}
.attrib b{color:var(--emerald);font-weight:500}
.attrib .dot{width:4px;height:4px;border-radius:50%;background:var(--gold);opacity:.65;flex:0 0 auto}

/* ── بطاقة المجلس (الكولوفون) ── */
.colophon{
  margin-top:60px;
  position:relative;overflow:hidden;
  background:linear-gradient(160deg,var(--emerald) 0%,var(--emerald-deep) 100%);
  border:1px solid var(--gold);
  border-radius:30px 30px 4px 4px/16px 16px 4px 4px;
  color:#D9CFB4;
  padding:34px 26px 30px;
  text-align:center;
}
.colophon::before{
  content:"";position:absolute;inset:0;z-index:0;
  background:linear-gradient(118deg,rgba(246,235,210,.05) 0 34%,transparent 34%);
}
.colophon > *{position:relative;z-index:1}
.mosque-name{
  font-family:"Amiri",serif;font-weight:700;
  font-size:clamp(26px,6.6vw,38px);
  color:#F6EBD2;line-height:1.6;margin:0 0 6px;
}
.mosque-latin{
  font-size:12.5px;color:var(--gold-light);
  letter-spacing:.14em;margin:0 0 22px;
}
.colophon .sep{
  width:190px;height:1px;margin:0 auto 24px;
  background:linear-gradient(to left,transparent,var(--gold-light),transparent);
}
.majlis{
  display:grid;gap:14px;
  max-width:430px;margin:0 auto 26px;
  text-align:right;
}
.majlis .row{display:grid;grid-template-columns:22px 1fr;gap:12px;align-items:start}
.majlis svg{width:20px;height:20px;margin-top:4px;stroke:var(--gold-light);fill:none;stroke-width:1.5}
.majlis .k{font-size:12.5px;color:rgba(217,207,180,.6);display:block;line-height:1.7}
.majlis .v{font-size:15.5px;color:#F1E3C4;line-height:1.85}
.majlis .v em{font-style:normal;color:var(--gold-light);font-size:13.5px}
.links{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
.links a{
  display:inline-flex;align-items:center;gap:9px;
  text-decoration:none;font-size:14.5px;
  padding:11px 20px;border-radius:3px;
  border:1px solid rgba(210,172,99,.5);
  color:#F1E3C4;
  transition:background .2s ease,border-color .2s ease;
}
.links a:hover,.links a:focus-visible{background:rgba(210,172,99,.16);border-color:var(--gold-light)}
.links svg{width:17px;height:17px;flex:0 0 auto}
.colophon .note{margin:24px 0 0;font-size:13px;color:rgba(217,207,180,.62);line-height:1.9}
@media (max-width:640px){
  .attrib{font-size:13px;gap:6px 12px}
  .links a{flex:1 1 100%;justify-content:center}
}
.sources{margin-top:52px;padding-top:22px;border-top:1px solid var(--rule);font-size:14px;color:var(--ink-soft);line-height:2}
.sources h3{font-size:18px;color:var(--emerald);margin-bottom:12px}
.sources ol{padding-right:20px;margin:0}
.sources li{margin-bottom:6px}

/* ── نافذة إعدادات الحفظ PDF ── */
.print-tip{
  position:fixed;inset:0;z-index:50;
  display:none;place-items:center;
  background:rgba(14,44,36,.55);
  padding:20px;
}
.print-tip.open{display:grid}
.print-tip .box{
  background:var(--paper);
  border:1px solid var(--gold);
  border-radius:4px;
  max-width:420px;width:100%;
  padding:26px 24px 22px;
  box-shadow:0 24px 60px -30px rgba(0,0,0,.6);
}
.print-tip h3{font-size:21px;color:var(--emerald);margin-bottom:12px}
.print-tip p{font-size:14.5px;line-height:1.95;color:var(--ink-soft);margin-bottom:14px}
.print-tip ol{margin:0 0 20px;padding-right:20px;font-size:14.5px;line-height:2.05}
.print-tip ol b{color:var(--emerald-deep);font-weight:500}
.print-tip .acts{display:flex;gap:10px;flex-wrap:wrap}
.print-tip button{
  flex:1 1 140px;
  font-family:"IBM Plex Sans Arabic",sans-serif;font-size:15px;
  padding:11px 18px;border-radius:3px;cursor:pointer;
  border:1px solid var(--gold);
}
.print-tip .go{background:var(--emerald);border-color:var(--emerald);color:#F6EBD2}
.print-tip .go:hover{background:var(--emerald-deep)}
.print-tip .cancel{background:transparent;color:var(--gold)}
.print-tip .cancel:hover{background:rgba(168,124,51,.12)}
.actions{
  display:flex;gap:12px;flex-wrap:wrap;justify-content:center;
  margin:36px auto 0;max-width:460px;
}
.act{
  flex:1 1 190px;
  display:inline-flex;align-items:center;justify-content:center;gap:9px;
  font-family:"IBM Plex Sans Arabic",sans-serif;font-size:15px;
  padding:12px 20px;border-radius:3px;cursor:pointer;
  border:1px solid var(--gold);
  background:transparent;color:var(--gold);
  transition:background .2s ease,color .2s ease;
}
.act:hover,.act:focus-visible{background:var(--gold);color:#FBF8F0}
.act.primary{background:var(--emerald);border-color:var(--emerald);color:#F6EBD2}
.act.primary:hover,.act.primary:focus-visible{background:var(--emerald-deep);border-color:var(--emerald-deep)}
.act svg{width:18px;height:18px;flex:0 0 auto}
.toast{
  position:fixed;z-index:60;
  right:50%;transform:translateX(50%) translateY(14px);
  bottom:26px;
  background:var(--emerald-deep);color:#F1E3C4;
  border:1px solid var(--gold);border-radius:3px;
  padding:12px 22px;font-size:14.5px;
  opacity:0;pointer-events:none;
  transition:opacity .25s ease,transform .25s ease;
}
.toast.show{opacity:1;transform:translateX(50%) translateY(0)}

/* ── الحركة الوحيدة: رسم التذهيب عند الفتح ── */
.draw{stroke-dasharray:1400;stroke-dashoffset:1400;animation:draw 2.4s ease forwards .25s}
@keyframes draw{to{stroke-dashoffset:0}}

@media (max-width:640px){
  body{font-size:16px}
  .trio,.quad,.paths{grid-template-columns:1fr}
  .pair{grid-template-columns:1fr}
  .side + .side{border-right:none;border-top:1px solid var(--rule)}
  .axis-q{font-size:17.5px}
  .step{grid-template-columns:36px 1fr;gap:12px}
  .step .num{width:36px;height:36px;font-size:17px}
}
@media (prefers-reduced-motion:reduce){
  .draw{animation:none;stroke-dashoffset:0}
  *{transition:none!important}
}
@media print{
  /* إبقاء الألوان كما هي على الورق وفي ملف PDF */
  html,body,.unwan,.night,.colophon,.attrib,.sacred,.axis-card,.side,
  .imam,.quad a,.step .num,.path,.node,.axis-q,.checks label{
    -webkit-print-color-adjust:exact;
    print-color-adjust:exact;
  }

  @page{size:A4;margin:12mm 11mm}

  body{
    background:var(--paper);
    position:relative;
    font-size:11.4pt;
    line-height:1.95;
  }
  /* الزخرفة تمتدّ على كل الصفحات بدل أن تثبت على الأولى */
  body::before{position:absolute;height:100%;opacity:.34}

  .wrap{max-width:none;width:100%;padding:0 2mm 0}
  .actions,.print-tip,.toast{display:none!important}
  .draw{stroke-dashoffset:0;animation:none}

  /* منع تقطيع الكتل في منتصفها */
  .sacred,.imam,.axis-card,.step,.path,.night,.colophon,.quad a,
  .attrib,.trio,.quad,.sources,.closing,.unwan{
    break-inside:avoid;page-break-inside:avoid;
  }
  .sec-head,.pillar-title,.axis-q,h2,h3,h4{
    break-after:avoid;page-break-after:avoid;
  }
  section{margin-top:26px}
  .pillar{margin-top:24px;padding-top:16px}
  .sacred{margin:14px 0}

  /* الخاتمة تبدأ بصفحة مستقلة */
  .colophon{break-before:page;page-break-before:always;margin-top:0}
  .sources{break-before:auto}

  /* إظهار الروابط لمن يقرأ على الورق */
  .links a::after{
    content:" (" attr(data-url) ")";
    font-size:9.5pt;color:var(--gold-light);
  }
}
@endverbatim
