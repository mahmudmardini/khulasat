{{--
  أنماطُ صفحات الاختبار — T-195. **ورقةٌ مستقلّة**، لا تُحمَّل في صفحة الملخّص
  ولا تمسّ قالبه. وكلُّ لونٍ فيها متغيّرٌ من لوحة الجهة، فلا لونَ جديد.
--}}
<style>
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body.kq{
  margin:0;min-height:100vh;
  background:var(--paper);color:var(--ink);
  font:400 16px/1.75 "IBM Plex Sans Arabic",system-ui,sans-serif;
  -webkit-font-smoothing:antialiased;
}
.kq a{color:var(--emerald)}
.kq :focus-visible{outline:3px solid var(--gold);outline-offset:2px}

/* ── الرأس: ليلُ اللوحة، والنجمةُ ذهبُها ─────────────────────── */
.kq-head{
  background:
    radial-gradient(120% 90% at 50% 0%, color-mix(in srgb, var(--gold) 22%, transparent) 0%, transparent 62%),
    var(--night);
  color:var(--paper);
  text-align:center;
  padding:22px 16px 84px;
}
.kq-head-in{max-width:640px;margin:0 auto}
.kq-venue{display:flex;align-items:center;justify-content:center;gap:10px;font-size:14px;opacity:.92}
.kq-logo{display:inline-flex;align-items:center;justify-content:center;height:40px;min-width:40px;padding:4px 8px;border-radius:9px;background:#fff}
.kq-logo.is-bare{background:transparent;padding:0}
.kq-logo img{max-height:32px;max-width:120px;display:block}
.kq-star{width:30px;height:30px;margin:18px auto 2px;display:block}
.kq-star path{fill:color-mix(in srgb, var(--gold-light) 30%, transparent);stroke:var(--gold-light);stroke-width:1.4}
.kq-star circle{fill:var(--gold-light)}
.kq-kicker{margin:0;font:700 19px/1.4 "Aref Ruqaa",serif;color:var(--gold-light)}
.kq-title{margin:6px 0 0;font:700 clamp(24px,6.4vw,34px)/1.45 "Amiri",serif;text-wrap:balance}
.kq-speaker{margin:6px 0 0;font-size:15px;opacity:.78}
.kq-head.is-compact{padding:14px 16px 64px}
.kq-head.is-compact .kq-star,.kq-head.is-compact .kq-kicker,.kq-head.is-compact .kq-speaker{display:none}
.kq-head.is-compact .kq-title{margin-top:8px;font-size:clamp(19px,5vw,24px)}
.kq-head.is-compact .kq-logo{height:32px;min-width:32px}
.kq-head.is-compact .kq-logo img{max-height:24px}

/* ── الورقة ─────────────────────────────────────────────────── */
.kq-main{max-width:640px;margin:-56px auto 0;padding:0 16px 40px;position:relative}
.kq-sheet{
  background:color-mix(in srgb, var(--paper) 45%, #fff);
  border:1px solid var(--rule);
  border-radius:16px;
  box-shadow:0 24px 48px -32px color-mix(in srgb, var(--night-deep) 70%, transparent);
  padding:28px 22px;
}
.kq-lead{margin:0 0 20px;font:400 19px/1.7 "Amiri",serif;text-align:center}
.kq-label{display:block;font:700 20px/1.5 "Amiri",serif;margin-bottom:8px}
.kq-input{
  width:100%;min-height:54px;padding:10px 16px;
  font:500 18px/1.4 "IBM Plex Sans Arabic",sans-serif;color:var(--ink);
  background:#fff;border:1.5px solid var(--paper-3);border-radius:12px;
}
.kq-input:focus{outline:none;border-color:var(--emerald);box-shadow:0 0 0 3px color-mix(in srgb, var(--emerald) 22%, transparent)}
.kq-input[aria-invalid="true"]{border-color:var(--clay)}
.kq-hint{margin:6px 0 0;font-size:14px;color:var(--ink-soft)}
.kq-error{margin:6px 0 0;font-size:14px;font-weight:600;color:var(--clay)}
.kq-form .kq-btn{width:100%;margin-top:22px}
.kq-privacy{margin:18px 0 0;padding-top:14px;border-top:1px solid var(--rule);font-size:13.5px;color:var(--ink-soft);text-align:center}
.kq-closed-title{margin:0 0 8px;text-align:center;font:700 26px/1.4 "Amiri",serif;color:var(--emerald-deep)}
.kq-start > .kq-btn{display:flex}

/* ── الأزرار ────────────────────────────────────────────────── */
.kq-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:50px;padding:0 24px;border-radius:12px;
  font:600 16px/1 "IBM Plex Sans Arabic",sans-serif;text-decoration:none;cursor:pointer;
  border:1.5px solid var(--gold);background:transparent;color:var(--emerald-deep);
  transition:background .15s ease,color .15s ease,border-color .15s ease;
}
.kq-btn:hover{background:color-mix(in srgb, var(--gold) 14%, transparent)}
.kq-btn-primary{background:var(--emerald);border-color:var(--emerald);color:var(--paper)}
.kq-btn-primary:hover{background:var(--emerald-deep);border-color:var(--emerald-deep)}
.kq-btn[disabled]{opacity:.5;cursor:not-allowed}
.kq-btn[hidden]{display:none}

/* ── الأسئلة ────────────────────────────────────────────────── */
.kq-progress{display:flex;gap:4px;margin:0 0 22px}
.kq-seg{flex:1;height:6px;border-radius:3px;background:var(--paper-3);transition:background .2s ease}
.kq-seg.is-done{background:var(--gold)}
.kq-seg.is-current{background:var(--emerald)}
.kq-q{border:0;margin:0 0 30px;padding:0;min-width:0}
.kq-q-legend{display:block;width:100%;padding:0;outline:none}
.kq-q-num{display:block;font-size:14px;font-weight:600;color:var(--gold);margin-bottom:6px}
.kq-q-prompt{display:block;font:700 clamp(20px,5.2vw,23px)/1.65 "Amiri",serif;color:var(--ink)}
.kq-options{display:grid;gap:10px;margin-top:18px;padding:0}

.kq-opt{
  position:relative;display:grid;grid-template-columns:24px 1fr auto;align-items:center;gap:12px;
  min-height:56px;padding:12px 16px;list-style:none;
  background:#fff;border:1.5px solid var(--paper-3);border-radius:12px;cursor:pointer;
  transition:border-color .15s ease,background .15s ease;
}
.kq-opt input{position:absolute;inset:0;opacity:0;margin:0;cursor:inherit}
.kq-opt:hover{border-color:color-mix(in srgb, var(--emerald) 45%, var(--paper-3))}
.kq-opt:has(input:focus-visible){outline:3px solid var(--gold);outline-offset:2px}
.kq-opt:has(input:checked){border-color:var(--emerald);background:color-mix(in srgb, var(--emerald) 7%, #fff)}
.kq-opt:has(input:disabled){cursor:default}
.kq-opt-mark{
  width:22px;height:22px;border-radius:50%;border:2px solid var(--ink-soft);
  display:inline-flex;align-items:center;justify-content:center;
  font:700 13px/1 "IBM Plex Sans Arabic",sans-serif;color:#fff;
}
.kq-opt:has(input:checked) .kq-opt-mark{border-color:var(--emerald);box-shadow:inset 0 0 0 4px #fff;background:var(--emerald)}
.kq-opt-text{font-size:16.5px;line-height:1.65}
.kq-opt-tag{font-size:13px;font-weight:600;white-space:nowrap}
.kq-opt-tag:empty{display:none}

/* الحكم: علامةٌ وكلمةٌ مع اللون، لا اللونُ وحده. */
.kq-opt.is-correct{border-color:var(--emerald);background:color-mix(in srgb, var(--emerald) 11%, #fff)}
.kq-opt.is-correct .kq-opt-mark{border-color:var(--emerald);background:var(--emerald);box-shadow:none}
.kq-opt.is-correct .kq-opt-mark::after{content:"✓"}
.kq-opt.is-correct .kq-opt-tag{color:var(--emerald)}
.kq-opt.is-wrong{border-color:var(--clay);background:color-mix(in srgb, var(--clay) 9%, #fff)}
.kq-opt.is-wrong .kq-opt-mark{border-color:var(--clay);background:var(--clay);box-shadow:none}
.kq-opt.is-wrong .kq-opt-mark::after{content:"✗"}
.kq-opt.is-wrong .kq-opt-tag{color:var(--clay)}
.kq-opt-static{cursor:default}
.kq-opt-static:hover{border-color:var(--paper-3)}
.kq-opt-static.is-correct:hover{border-color:var(--emerald)}
.kq-opt-static.is-wrong:hover{border-color:var(--clay)}

.kq-ev{display:block;font:400 19px/1.9 "Amiri",serif}
.kq-ev.is-ayah{font-family:"Amiri Quran","Amiri",serif}
.kq-ev-src{display:block;font-size:13px;color:var(--ink-soft);margin-top:2px}

.kq-verdict{margin-top:16px;padding:14px 16px;border-radius:10px;border-inline-start:4px solid var(--gold);background:color-mix(in srgb, var(--gold) 9%, #fff);outline:none}
.kq-verdict.is-correct{border-color:var(--emerald);background:color-mix(in srgb, var(--emerald) 8%, #fff)}
.kq-verdict.is-wrong{border-color:var(--clay);background:color-mix(in srgb, var(--clay) 7%, #fff)}
.kq-verdict-head{margin:0;font-weight:600}
.kq-verdict.is-correct .kq-verdict-head{color:var(--emerald)}
.kq-verdict.is-wrong .kq-verdict-head{color:var(--clay)}
.kq-verdict-body{margin:4px 0 0;font-size:15px;color:var(--ink-soft)}

.kq-nav{display:flex;gap:10px;margin-top:8px}
.kq-nav [data-finish]{flex:1}
.kq-status{min-height:1.6em;margin:12px 0 0;font-size:14px;color:var(--ink-soft);text-align:center}
.kq-status.is-error{color:var(--clay);font-weight:600}

/* مع السكربت: سؤالٌ في الشاشة. وبلاه: الأسئلةُ كلُّها وزرُّ الإنهاء. */
.kq-nav [data-prev],.kq-nav [data-next]{display:none}
.js .kq-q{display:none}
.js .kq-q.is-current{display:block}
.js .kq-nav [data-prev],.js .kq-nav [data-next]{display:inline-flex}
.js .kq-nav [data-next]{flex:1}
.js .kq-nav [hidden]{display:none}

/* ── بطاقةُ النتيجة ─────────────────────────────────────────── */
.kq-card{
  padding:9px;border-radius:18px;
  background:color-mix(in srgb, var(--paper) 45%, #fff);
  border:1px solid var(--gold);
  box-shadow:0 30px 60px -36px color-mix(in srgb, var(--night-deep) 80%, transparent);
}
.kq-card-frame{
  border:3px double var(--gold);border-radius:12px;
  padding:30px 18px 22px;text-align:center;
  background:radial-gradient(90% 60% at 50% 0%, color-mix(in srgb, var(--gold) 13%, transparent), transparent 70%);
}
.kq-card-hail{margin:0;font:700 clamp(30px,8.5vw,42px)/1.3 "Aref Ruqaa",serif;color:var(--gold)}
.kq-card.is-honoured .kq-card-hail{font-size:clamp(38px,11vw,56px)}
.kq-card-name{margin:6px 0 0;font:700 clamp(28px,8.5vw,44px)/1.35 "Aref Ruqaa",serif;color:var(--emerald-deep);overflow-wrap:anywhere}
.kq-card-line{margin:8px auto 0;max-width:34ch;font-size:15.5px;color:var(--ink-soft)}

.kq-medal{position:relative;width:176px;height:176px;margin:22px auto 8px}
.kq-medal svg{width:100%;height:100%;display:block;overflow:visible}
.kq-medal-star{fill:color-mix(in srgb, var(--gold) 10%, transparent);stroke:color-mix(in srgb, var(--gold) 70%, transparent);stroke-width:1.2}
.kq-medal-track{fill:none;stroke:var(--paper-3);stroke-width:7}
.kq-medal-arc{
  fill:none;stroke:var(--emerald);stroke-width:7;stroke-linecap:round;
  transform:rotate(-90deg);transform-origin:60px 60px;
  animation:kq-ring 1.1s cubic-bezier(.2,.7,.2,1) .15s both;
}
.kq-card.is-honoured .kq-medal-arc{stroke:var(--gold)}
@keyframes kq-ring{from{stroke-dashoffset:var(--kq-ring)}}
.kq-medal-score{
  position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;
  font:700 50px/1 "Amiri",serif;color:var(--emerald-deep);
}
.kq-medal-score small{display:block;margin-top:6px;font:500 15px/1 "IBM Plex Sans Arabic",sans-serif;color:var(--ink-soft)}

.kq-card-facts{display:flex;justify-content:center;gap:34px;margin:6px 0 0}
.kq-card-facts div{min-width:64px}
.kq-card-facts dt{font-size:13px;color:var(--ink-soft)}
.kq-card-facts dd{margin:0;font:700 23px/1.4 "Amiri",serif;color:var(--ink)}
.kq-card-lesson{margin:18px auto 0;max-width:36ch;font:400 18px/1.7 "Amiri",serif}
.kq-card-venue{
  display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:6px 10px;
  margin:18px 0 0;padding-top:14px;border-top:1px solid var(--rule);font-size:14px;color:var(--ink-soft);
}
.kq-card-venue img{height:28px;max-width:90px;object-fit:contain}
.kq-card-date{font-size:13px}

.kq-actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:22px 0 38px}
.kq-actions .kq-btn{flex:1 1 180px}

/* ── المراجعة ───────────────────────────────────────────────── */
.kq-review-title{margin:0 0 14px;font:700 24px/1.4 "Amiri",serif;color:var(--emerald-deep)}
.kq-review-list{list-style:none;margin:0;padding:0;counter-reset:kq}
.kq-rq{
  background:color-mix(in srgb, var(--paper) 45%, #fff);
  border:1px solid var(--rule);border-radius:14px;padding:18px;margin:0 0 14px;
  break-inside:avoid;
}
.kq-rq-head{display:flex;align-items:center;gap:8px;margin:0;font-size:14px;font-weight:600}
.kq-rq-mark{width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:13px}
.kq-rq.is-right .kq-rq-mark{background:var(--emerald)}
.kq-rq.is-right .kq-rq-verdict{color:var(--emerald)}
.kq-rq.is-wrong .kq-rq-mark{background:var(--clay)}
.kq-rq.is-wrong .kq-rq-verdict{color:var(--clay)}
.kq-rq-prompt{margin:8px 0 12px;font:700 19px/1.65 "Amiri",serif}
.kq-rq-options{display:grid;gap:8px;margin:0;padding:0}
.kq-rq-options .kq-opt{min-height:48px;padding:10px 14px}
.kq-rq-why{margin:14px 0 0;padding-inline-start:12px;border-inline-start:3px solid var(--gold);font-size:15px;color:var(--ink-soft)}
.kq-rq-axis{margin:8px 0 0;font-size:13.5px;color:var(--gold)}

.kq-foot{text-align:center;padding:0 16px 32px;font-size:14px}

@media (prefers-reduced-motion: reduce){
  .kq-medal-arc{animation:none}
  .kq-seg,.kq-opt,.kq-btn{transition:none}
}

@media print{
  @page{size:A4;margin:14mm}
  body.kq{background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .kq-head{padding:14px 0 18px}
  .kq-main{margin-top:12px;max-width:none}
  .kq-actions,.kq-foot{display:none}
  .kq-card{box-shadow:none;break-inside:avoid}
  .kq-medal-arc{animation:none}
  .kq-review{break-before:page}
}
</style>
