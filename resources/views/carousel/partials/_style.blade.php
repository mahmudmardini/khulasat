/*
  أنماط الكاروسيل — قالبٌ **مستقلّ** عن قالب الملخّص.

  ولا يشترك معه في حرفٍ من CSS عمداً: ذاك مضبوطٌ لصفحةٍ تُقرأ على جوّال
  وتُطبع على ورق، وهذا لبطاقةٍ مربّعة الأبعاد تُلتقط صورةً. وخلطهما يجرّ
  تعديلاً في أحدهما إلى كسر الآخر — CLAUDE.md §2 القاعدة الأولى تحمي ذاك،
  وهذا الفصل هو ما يجعل حمايتها ممكنة.

  والقياسات **بالبكسل الثابت لا بالنسب**: الشريحة ١٠٨٠×١٣٥٠ مقاسُ إنستغرام،
  وT-20 تلتقطها كما هي بلا حساب.
*/
*{box-sizing:border-box;margin:0;padding:0}

body{
  background:var(--ink);
  direction:rtl;
  font-family:"IBM Plex Sans Arabic",system-ui,sans-serif;
  -webkit-font-smoothing:antialiased;
}

/* الشرائح متجاورة، والقراءة من اليمين لليسار — §8-أ. */
.deck{
  display:flex;
  gap:36px;
  padding:36px;
  align-items:flex-start;
  width:max-content;
}

.slide{
  position:relative;
  flex:0 0 auto;
  width:1080px;
  height:1350px;
  overflow:hidden;
  background:var(--paper);
  color:var(--ink);
}

.slide.cover,.slide.closing{background:var(--emerald-deep);color:var(--paper)}

.frame{
  position:absolute;
  inset:46px;
  border:1px solid var(--rule);
  display:flex;
  flex-direction:column;
  padding:64px 62px 48px;
}
.slide.cover .frame,.slide.closing .frame{border-color:rgba(210,172,99,.42)}

/* ── التاج والشعار: الأولى والأخيرة فقط ── */
.crown{display:flex;flex-direction:column;align-items:center;gap:26px;margin-bottom:26px}
.crown .crest{width:520px;height:auto;display:block}
.crown .logo{max-height:132px;max-width:340px;object-fit:contain;display:block}

/* ── العنوان ── */
.eyebrow{
  font-size:27px;
  letter-spacing:.02em;
  color:var(--gold);
  text-align:center;
  margin-bottom:22px;
}
.heading{
  font-family:"Amiri",serif;
  font-weight:700;
  line-height:1.5;
  text-align:center;
  color:var(--emerald);
  font-size:56px;
}
.slide.cover .heading{font-size:88px;color:var(--gold-light)}
.slide.closing .heading{color:var(--gold-light)}

/* ── المتن: يتوسّط ما بقي من الشريحة ── */
.body{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  font-family:"Amiri",serif;
  line-height:1.95;
  padding:34px 4px;
}
.slide.cover .body,.slide.closing .body{color:var(--paper)}

/*
  القياس يصغُر ولا يُقتطع النصّ — §8-أ: «الشاهد الذي لا يسع الشريحة يُنقل
  إلى شريحة خاصّة به»، **ولا يُختصر بحال**.
*/
.sz-lg .body{font-size:62px}
.sz-md .body{font-size:52px}
.sz-sm .body{font-size:43px}
.sz-xs .body{font-size:35px}

/* ── لفظ المصدر: آيةً أو حديثاً ── */
.slide.ayah .body,.slide.evidence .body{
  font-family:"Amiri Quran","Amiri",serif;
  line-height:2.25;
  color:var(--emerald);
}
.slide.ayah .body{background:rgba(27,77,62,.05)}

.source{
  font-family:"IBM Plex Sans Arabic",sans-serif;
  font-size:28px;
  line-height:1.8;
  color:var(--gold);
  text-align:center;
  padding-top:22px;
}

.link{
  font-family:"IBM Plex Sans Arabic",sans-serif;
  font-size:26px;
  color:var(--gold-light);
  text-align:center;
  padding-top:18px;
  direction:ltr;
}

/* ── التذييل: الرقم والجهة ── */
.foot{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:20px;
  padding-top:26px;
  border-top:1px solid var(--rule);
  font-size:26px;
  color:var(--ink-soft);
}
.slide.cover .foot,.slide.closing .foot{border-top-color:rgba(210,172,99,.32);color:var(--gold-light)}

.num{
  font-family:"Amiri",serif;
  font-size:30px;
  width:56px;
  height:56px;
  display:flex;
  align-items:center;
  justify-content:center;
  border:1px solid var(--rule);
  border-radius:50%;
  color:var(--gold);
}
.slide.cover .num,.slide.closing .num{border-color:rgba(210,172,99,.4)}

.venue{text-align:left;line-height:1.6}
.venue b{display:block;font-weight:600;font-size:28px;color:inherit}

/*
  ★ **تصغيرٌ للعرض على شاشةٍ ضيّقة وحده.**

  والحدّ ١٠٧٩ لا ١٠٨٠ عمداً: T-20 تفتح المتصفّح بعرض ١٠٨٠ فما فوق ليخرج
  البكسل مطابقاً، فلا تبلغها هذه القاعدة أبداً. ومن رفعها إلى ١٢٠٠ صغّر
  الشريحة تحت أعين الملتقِط وأخرج صورةً بنصف المقاس.
*/
@media screen and (max-width:1079px){
  .deck{zoom:.34}
}
