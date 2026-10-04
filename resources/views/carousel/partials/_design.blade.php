/*
  مواصفةُ التصميم — T-173.

  أصنافٌ على `.deck` من `CarouselDesign::deckClasses()`، وعلى كلّ شريحة
  `l-<تخطيط>`. ★ **ولا قاعدة هنا لقيمةٍ افتراضية في أيّ حقل**: الافتراضيُّ هو
  `_style` كما هو، فلا يتبدّل كاروسيلٌ قائم بمجيء المواصفة.

  و«شرائح المتن» ما سوى الأولى والأخيرة: `.slide:not(.cover):not(.closing)`.
*/

/* ── الالتقاط: شريحةٌ واحدة في وثيقتها، بلا هامش ولا فاصل ── */
.deck.capture{padding:0;gap:0}

/* ── سطحُ شرائح المتن ── */
.surface-paper-2 .slide:not(.cover):not(.closing){background-color:var(--paper-2)}

.surface-night .slide:not(.cover):not(.closing){background-color:var(--night);color:var(--paper)}
.surface-night .slide:not(.cover):not(.closing) .heading{color:var(--gold-light)}
.surface-night .slide:not(.cover):not(.closing) .body{color:var(--paper)}
.surface-night .slide.ayah .body{background:rgba(255,255,255,.05)}
.surface-night .slide:not(.cover):not(.closing) .frame{border-color:rgba(210,172,99,.34)}
.surface-night .slide:not(.cover):not(.closing) .foot{border-top-color:rgba(210,172,99,.3);color:var(--paper-3)}
.surface-night .slide:not(.cover):not(.closing) .num{border-color:rgba(210,172,99,.4);color:var(--gold-light)}
.surface-night .slide:not(.cover):not(.closing) .source{color:var(--gold-light)}

/* ── الأولى والأخيرة على الورق ── */
.bookends-paper .slide.cover,.bookends-paper .slide.closing{background-color:var(--paper);color:var(--ink)}
.bookends-paper .slide.cover .heading,.bookends-paper .slide.closing .heading{color:var(--emerald)}
.bookends-paper .slide.cover .body,.bookends-paper .slide.closing .body{color:var(--ink)}
.bookends-paper .slide.cover .frame,.bookends-paper .slide.closing .frame{border-color:var(--rule)}
.bookends-paper .slide.cover .foot,.bookends-paper .slide.closing .foot{border-top-color:var(--rule);color:var(--ink-soft)}
.bookends-paper .slide.cover .num,.bookends-paper .slide.closing .num{border-color:var(--rule);color:var(--gold)}
.bookends-paper .slide.cover .eyebrow{color:var(--gold)}
.bookends-paper .slide.closing .link{color:var(--gold)}

/* ── الخلفية ──
   التدرّجُ على الشريحة كلّها. **والنقشُ حول الإطار لا تحته**: داخلُ الإطار
   بلون الشريحة، فيبقى المتنُ على صفحةٍ صافية ويقرأ كما يقرأ بلا نقش. */
.background-gradient .slide{background-image:linear-gradient(160deg,rgba(255,255,255,.10),rgba(0,0,0,.12))}
.background-dots .slide{background-image:radial-gradient(var(--rule) 2px,transparent 2.6px);background-size:28px 28px}
.background-lines .slide{background-image:repeating-linear-gradient(135deg,var(--rule) 0 1.5px,transparent 1.5px 22px)}
.background-lattice .slide{
  background-image:
    repeating-linear-gradient(60deg,var(--rule) 0 1.5px,transparent 1.5px 36px),
    repeating-linear-gradient(-60deg,var(--rule) 0 1.5px,transparent 1.5px 36px),
    repeating-linear-gradient(0deg,var(--rule) 0 1.5px,transparent 1.5px 31.2px);
}
.background-dots .frame,.background-lines .frame,.background-lattice .frame{background-color:inherit}

/* ── الإطار ── */
.frame-double .frame{border:5px double var(--gold)}
.frame-double .slide.cover .frame,.frame-double .slide.closing .frame{border-color:var(--gold-light)}
.frame-none .frame{border-color:transparent}
.frame-corners .frame{
  border-color:transparent;
  --c:var(--gold);
  background-image:
    linear-gradient(var(--c),var(--c)),linear-gradient(var(--c),var(--c)),
    linear-gradient(var(--c),var(--c)),linear-gradient(var(--c),var(--c)),
    linear-gradient(var(--c),var(--c)),linear-gradient(var(--c),var(--c)),
    linear-gradient(var(--c),var(--c)),linear-gradient(var(--c),var(--c));
  background-size:96px 3px,3px 96px,96px 3px,3px 96px,96px 3px,3px 96px,96px 3px,3px 96px;
  background-position:top right,top right,top left,top left,bottom right,bottom right,bottom left,bottom left;
  background-repeat:no-repeat;
}
.frame-corners .slide.cover .frame,.frame-corners .slide.closing .frame{--c:var(--gold-light)}
.bookends-paper.frame-corners .slide.cover .frame,.bookends-paper.frame-corners .slide.closing .frame{--c:var(--gold)}

/* ── زخرفةُ التاج: الجديدات بـ`currentColor` ── */
.ornament-star .crown,.ornament-rosette .crown{color:var(--gold-light)}
.bookends-paper.ornament-star .crown,.bookends-paper.ornament-rosette .crown{color:var(--gold)}
.ornament-star .crown .crest,.ornament-rosette .crown .crest{width:520px}

/* ── خطُّ العنوان، من القائمة المعتمدة وحدها ── */
.heading-font-reem-kufi .heading{font-family:"Reem Kufi","IBM Plex Sans Arabic",sans-serif;font-weight:700;line-height:1.45}
.heading-font-aref-ruqaa .heading{font-family:"Aref Ruqaa","Amiri",serif;font-weight:700;line-height:1.6}
.heading-font-plex .heading{font-family:"IBM Plex Sans Arabic",sans-serif;font-weight:600;line-height:1.45}

/* ── لونُ التمييز في شرائح المتن ── */
.accent-clay .slide:not(.cover):not(.closing) .source,
.accent-clay .slide:not(.cover):not(.closing) .num{color:var(--clay)}
.accent-emerald .slide:not(.cover):not(.closing) .source,
.accent-emerald .slide:not(.cover):not(.closing) .num{color:var(--emerald-soft)}
.surface-night.accent-clay .slide:not(.cover):not(.closing) .source,
.surface-night.accent-clay .slide:not(.cover):not(.closing) .num{color:var(--clay-soft)}
.surface-night.accent-emerald .slide:not(.cover):not(.closing) .source,
.surface-night.accent-emerald .slide:not(.cover):not(.closing) .num{color:var(--paper-3)}

/* ── الرقم ── */
.number-plain .num{border:none;width:auto;height:auto}
.number-bar .num{border:none;border-radius:0;width:auto;height:auto;gap:14px}
.number-bar .num::before{content:"";display:block;width:46px;height:3px;background:currentColor}

/* ══ التخطيطات ══
   بـ`.slide.l-*` لا `.l-*` وحدها: قواعدُ الأولى والأخيرة في `_style` بثلاثة
   أصناف (`.slide.cover .heading`)، وتخطيطٌ أضعف منها لا يُرى عليها. */

/* شريط: العنوانُ في شريطٍ يمتدّ إلى حدّي الإطار. */
.slide.l-band .heading{
  margin-inline:-62px;
  padding:26px 40px;
  background:var(--emerald);
  color:var(--paper);
}
.slide.cover.l-band .heading,.slide.closing.l-band .heading{background:var(--gold-light);color:var(--emerald-deep)}
.bookends-paper .slide.cover.l-band .heading,.bookends-paper .slide.closing.l-band .heading{background:var(--emerald);color:var(--paper)}
.surface-night .slide.l-band:not(.cover):not(.closing) .heading{background:var(--gold);color:var(--night-deep)}

/* جانب: شريطٌ عريض في أوّل السطر، والنصّ إليه لا في الوسط.
   **عنصرٌ لا حدّ**: حدودُ الإطار تتبدّل بالسطح والإطار المختار، والشريطُ ثابت. */
.slide.l-side .frame::before{
  content:"";
  position:absolute;
  inset-block:-1px;
  inset-inline-start:-1px;
  width:12px;
  background:var(--gold);
}
.slide.l-side .heading,.slide.l-side .source{text-align:start}
.slide.l-side .body{justify-content:flex-start;text-align:start}

/* وسام: لفظُ المصدر في إطارٍ مزدوج مستدير. */
.slide.l-medallion .body{
  margin:12px 0;
  padding:40px 44px;
  border:4px double var(--gold);
  border-radius:30px;
}
.slide.ayah.l-medallion .body{background:rgba(27,77,62,.05)}
.surface-night .slide.l-medallion .body{background:rgba(255,255,255,.05);border-color:var(--gold-light)}

/* ملصق: التاجُ في الأعلى، والعنوانُ كبيراً في الأسفل إلى أوّل السطر. */
.slide.l-poster .crown{margin-bottom:auto}
.slide.l-poster .eyebrow,.slide.l-poster .heading{text-align:start}
.slide.cover.l-poster .heading,.slide.closing.l-poster .heading{font-size:104px;line-height:1.3}
.slide.l-poster .body{flex:0 0 auto;justify-content:flex-start;text-align:start;padding:20px 4px 36px}
/* `end` لا `start`: الرابط `direction:ltr`، فنهايتُه يمينُ الشريحة كسائر سطورها. */
.slide.l-poster .link{text-align:end}
