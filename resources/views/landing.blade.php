{{--
    صفحة التعريف العامّة — جذرُ الموقع (T-113)، وبأربع لغات (T-131).

    Blade ساكنة لا Inertia: زائرٌ بلا حساب لا يحتاج قوقعة React.

    ★ **ونصُّ الصفحة خرج إلى `lang/*/landing.php` في T-131.** وكان هنا
    بعلّةٍ صحيحة: نصٌّ تسويقيّ واحدٌ لا يُفرَّق عن تخطيطه. وقد صار أربعة،
    فانقلبت العلّةُ حجّةً عليه — أربعُ نسخٍ من ألف سطرٍ لا تُصان.

    **ولوحةُ الجهة ولوحةُ المشرف عربيّتان كما كانتا** (CLAUDE.md §1):
    المفتوحُ هذه الصفحةُ وحدها، لأنّها الوجهُ الذي يبلغ من لا يقرأ العربية.

    والأصولُ في `public/landing/`، والنموذجُ الحيّ في القسم الثالث
    مبنيٌّ بHTML لا بلقطات: **صفرُ بياناتٍ حقيقية في الصفحة** — لا اسمَ
    مُلقٍ ولا مكانَ محاضرةٍ ولا نصَّ درس، وكلُّ موضعٍ موصوفٌ بدوره.
--}}
@php
    use App\Enums\Locale;

    $locale = Locale::parse(app()->getLocale());
    $isRtl = $locale->isSource();

    /** رابطُ كلّ لغة — والعربيةُ على الجذر بلا بادئة. */
    $urlFor = static fn (Locale $l): string => $l->isSource()
        ? route('home')
        : route('home.locale', ['locale' => $l->value]);

    /*
     * الخطوط — لكلّ اتّجاهٍ حملُه هو.
     *
     * فالعربيةُ تحتاج «ريم كوفي» للعناوين و«بلكس عربي» للمتن، واللاتينيةُ
     * لا تنتفع بهما بحرف: «ريم كوفي» لا لاتينيةَ فيه أصلاً. **وأميري يبقى
     * في الحالين** — الآيةُ والحديثُ عربيّان في كلّ لغة (T-38).
     */
    $fonts = $isRtl
        ? 'family=IBM+Plex+Sans+Arabic:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500&family=Reem+Kufi:wght@600&family=Amiri'
        : 'family=IBM+Plex+Sans:wght@400;500;600&family=Amiri';
    $fontHref = 'https://fonts.googleapis.com/css2?'.$fonts.'&display=swap';

    $ld = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                'name' => __('landing.meta.site_name'),
                'alternateName' => 'Khulasat',
                'url' => route('home'),
                'description' => __('landing.meta.org_description'),
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => __('landing.meta.site_name'),
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'inLanguage' => $locale->value,
                'url' => $urlFor($locale),
                'description' => __('landing.meta.app_description'),
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('landing.meta.title') }}</title>
<meta name="description" content="{{ __('landing.meta.description') }}">
<link rel="canonical" href="{{ $urlFor($locale) }}">
{{-- بدائلُ اللغات — متبادلةٌ بين الأربع، و`x-default` على العربية لأنّها الأصل (T-131). --}}
@foreach (Locale::all() as $alt)
<link rel="alternate" hreflang="{{ $alt->value }}" href="{{ $urlFor($alt) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ route('home') }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="{{ __('landing.meta.og_locale') }}">
<meta property="og:url" content="{{ $urlFor($locale) }}">
<meta property="og:site_name" content="{{ __('landing.meta.site_name') }}">
<meta property="og:title" content="{{ __('landing.meta.og_title') }}">
<meta property="og:description" content="{{ __('landing.meta.og_description') }}">
{{-- لكلّ لغةٍ بطاقتُها — T-143. وكانت بطاقةً عربيةً واحدة للأربع، فتُشارَك
     `/en` بصورةٍ لا يقرؤها من أُرسلت إليه. --}}
@foreach (Locale::all() as $alt)
@continue($alt === $locale)
<meta property="og:locale:alternate" content="{{ __('landing.meta.og_locale', [], $alt->value) }}">
@endforeach
<meta property="og:image" content="{{ asset('landing/og-'.$locale->value.'.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ __('landing.meta.og_image_alt') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#243B6B">
<link rel="icon" href="{{ asset('landing/favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('landing/icon-180.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="{{ $fontHref }}">
<link rel="stylesheet" href="{{ $fontHref }}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{{ $fontHref }}"></noscript>
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<style>
:root{
  --brand:#243B6B; --brand-deep:#152444; --brand-soft:#4A5F8E; --brand-lift:#8AA8DC;
  --brand-tint:#E9EDF5;
  --night:#192848; --night-deep:#0F192F;
  --gold:#A87C33; --gold-light:#C6A468; --gold-text:#8A6425;
  --bg:#FBFAF7; --surface:#FFFFFF; --surface-alt:#F4F2EC;
  --border:#E4E0D6; --border-strong:#CFC9BA;
  --ink:#22201A; --muted:#6B6559; --faint:#706C62;
  --info:#2E5A8A; --success:#2F6B4F; --warning:#A8701F; --danger:#8E3B2E;
  --s1:4px; --s2:8px; --s3:12px; --s4:16px; --s6:24px; --s8:32px; --s12:48px;
  --s16:64px; --s24:96px;
  --r6:6px; --r10:10px; --r14:14px;
  --shadow:0 2px 8px rgba(34,32,26,.04), 0 12px 32px rgba(34,32,26,.08);
  --shadow-lift:0 4px 12px rgba(34,32,26,.06), 0 20px 48px rgba(34,32,26,.10);
  --ar:"IBM Plex Sans Arabic", system-ui, sans-serif;
  --lt:"IBM Plex Sans", system-ui, sans-serif;
  --kufi:"Reem Kufi", serif;
  --amiri:"Amiri", serif;
  --maxw:1120px;
  color-scheme: light;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth; scroll-padding-top:88px}
body{margin:0; background:var(--bg); color:var(--ink); font-family:var(--ar);
  font-size:16px; line-height:1.85; -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility; overflow-x:hidden}
img{max-width:100%; height:auto; display:block}
a{color:var(--brand); text-decoration-thickness:1px; text-underline-offset:3px}
p{max-width:68ch; text-wrap:pretty; margin:0 0 var(--s4)}
h1,h2,h3,h4{margin:0; text-wrap:balance; letter-spacing:0}
h1,h2{font-family:var(--kufi); font-weight:600; letter-spacing:-.02em}
h3,h4{font-weight:600}
b,strong{font-weight:600}
ul,ol{margin:0; padding:0; list-style:none}
*:focus-visible{outline:2px solid var(--gold); outline-offset:2px; border-radius:2px}
.hero .grid>*,.anat-row>*,.bento>*,.grid2>*,.grid3>*,.steps>*,.tracks>*,
.closing .cols>*,footer .cols>*,.diff>*,.axis>*,.pal>*{min-width:0}
.ltn{font-family:var(--lt); direction:ltr; unicode-bidi:isolate; font-variant-numeric:tabular-nums}
.num{font-family:var(--lt); direction:ltr; unicode-bidi:isolate; font-variant-numeric:tabular-nums}

.skip{position:absolute; inset-inline-start:-9999px; top:0; z-index:100;
  background:var(--brand); color:#fff; padding:10px 18px; border-radius:0 0 var(--r6) var(--r6)}
.skip:focus{inset-inline-start:var(--s6)}

.wrap{max-width:var(--maxw); margin-inline:auto; padding-inline:var(--s6)}
section{padding-block:clamp(60px,8.5vw,120px)}
.eyebrow{font-size:12px; font-weight:500; color:var(--faint); letter-spacing:.02em; margin:0 0 var(--s3);
  display:flex; align-items:center; gap:10px}
.eyebrow::before{content:""; width:48px; height:1px; background:var(--gold); flex:none}
h2{font-size:clamp(26px,3.2vw,36px); line-height:1.35; margin:0 0 var(--s4)}
.lede{font-size:17px; color:var(--muted); max-width:66ch}
.sub{font-size:13.5px; line-height:1.8; color:var(--muted)}
.tiny{font-size:12px; line-height:1.75; color:var(--faint)}

/* ── buttons ── */
.btn{display:inline-flex; align-items:center; justify-content:center; gap:9px;
  min-height:44px; padding:11px 22px; border-radius:var(--r6); font-family:var(--ar);
  font-size:15px; font-weight:500; border:1px solid transparent; cursor:pointer;
  text-decoration:none; transition:background .16s ease-out, border-color .16s ease-out, transform .16s ease-out}
.btn-primary{background:var(--brand); color:#fff}
.btn-primary:hover{background:var(--brand-deep)}
.btn-ghost{background:transparent; color:var(--brand); border-color:var(--border-strong)}
.btn-ghost:hover{border-color:var(--brand); background:var(--brand-tint)}
.btn-light{background:#fff; color:var(--brand)}
.btn-light:hover{background:var(--brand-tint)}
.btn-sm{min-height:44px; padding:10px 16px; font-size:14px}

/* ── topbar ── */
.topbar{position:sticky; top:0; z-index:50; background:color-mix(in srgb, var(--bg) 92%, transparent);
  backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);
  border-bottom:1px solid transparent; transition:border-color .2s ease-out}
.topbar.scrolled{border-bottom-color:var(--border)}
.topbar .wrap{display:flex; align-items:center; gap:var(--s6); height:64px}
.logo{display:flex; align-items:center; gap:9px; min-height:44px; text-decoration:none; color:var(--ink); flex:none}
.logo svg{color:var(--brand); flex:none}
.logo b{font-family:var(--kufi); font-weight:600; font-size:20px; line-height:1}
.navlinks{display:flex; gap:var(--s1); margin-inline-start:auto; font-size:14px}
.navlinks a{white-space:nowrap; color:var(--muted); text-decoration:none; padding:8px 11px; border-radius:var(--r6);
  transition:color .16s ease-out}
.navlinks a:hover{color:var(--ink)}
.navlinks a[aria-current="true"]{color:var(--brand); font-weight:500}
.navcta{display:flex; align-items:center; gap:var(--s2); flex:none}
.burger{display:none; width:44px; height:44px; align-items:center; justify-content:center;
  background:transparent; border:1px solid var(--border); border-radius:var(--r6); color:var(--ink); cursor:pointer}
.drawer{display:none; border-bottom:1px solid var(--border); background:var(--bg)}
.drawer.open{display:block}
.drawer ul{padding:var(--s3) var(--s6) var(--s4)}
/* ★ `ul a` لا `a` — T-145. كانت `display:block` تغلب `inline-flex` الزرِّ
   (وهي أخصُّ منها وبعدها)، فيفقد توسيطَه ويلتصق نصُّه بحافّته. */
.drawer ul a{display:block; padding:12px 4px; color:var(--ink); text-decoration:none; font-size:15px;
  border-bottom:1px solid var(--border)}
.drawer .navcta{padding:var(--s4) var(--s6) var(--s6); gap:var(--s3)}
.drawer .navcta .btn{flex:1}
/*
  ★ **والدرجُ مثبَّتٌ لا يُمرَّر** — بلاغُ مالك المنتج، ١٦ أيلول ٢٠٢٦.
  كان في مجرى الصفحة تحت ترويسةٍ لاصقة، فمن فتح القائمة ثمّ مرّر ذهبت
  عنه وبقيت الترويسةُ وحدها. فصار لوحاً مثبَّتاً تحت الترويسة، يُمرَّر في
  نفسه إن طال، والخلفُ محبوسٌ عن التمرير ما دام مفتوحاً.
  و`top` يُحسب من ارتفاع الترويسة الفعليّ في السكربت.
*/
@media (max-width:1023px){
  .drawer.open{position:fixed; inset-inline:0; bottom:0; z-index:45; overflow-y:auto;
    -webkit-overflow-scrolling:touch; border-bottom:0; box-shadow:0 14px 34px rgba(34,32,26,.14);
    overscroll-behavior:contain}
  body.nav-open{overflow:hidden}
}

/* ── hero ── */
.hero{padding-block:clamp(44px,6vw,88px) clamp(56px,7vw,104px)}
.hero .grid{display:grid; grid-template-columns:52% 1fr; gap:clamp(28px,4vw,56px); align-items:center}
.hero h1{font-size:clamp(36px,5.4vw,62px); line-height:1.3; margin:0 0 var(--s4)}
.hero .lede{font-size:17.5px; margin-bottom:var(--s6)}
.hero .acts{display:flex; flex-wrap:wrap; gap:var(--s3); margin-bottom:var(--s4)}
.assure{display:flex; flex-wrap:wrap; gap:6px 14px; font-size:12px; color:var(--faint); margin:0; max-width:none}
.assure span{display:inline-flex; align-items:center; gap:5px}
.assure svg{color:var(--gold); flex:none}
.shot{position:relative}
.shot .d{box-shadow:var(--shadow-lift)}
.d-peek{-webkit-mask-image:linear-gradient(180deg,#000 44%,transparent 97%);
  mask-image:linear-gradient(180deg,#000 44%,transparent 97%)}
.shot.tilt .d{transform:rotate(-1.2deg)}
.shot-cap{font-size:11.5px; color:var(--faint); margin:var(--s6) 0 0; line-height:1.7; text-align:center}
.shot-cap a{color:var(--brand); font-weight:500; text-underline-offset:3px}
.hero .eyebrow{margin-bottom:var(--s4)}
.access{font-size:12.5px; color:var(--muted); margin:var(--s3) 0 0}
.tpl-chips{display:flex; flex-wrap:wrap; gap:var(--s2); margin-top:var(--s4)}
figcaption{font-size:11.5px; color:var(--faint); margin-top:var(--s3); line-height:1.7}

/* ── dark block ── */
.dark{background:var(--night-deep); color:#E4E9F4}
.dark h2{color:#F1F3F8}
.dark .eyebrow{color:#8A93A8}
.dark .eyebrow::before{background:var(--gold-light)}
.dark .lede{color:#A7B0C4}
.dark .sub{color:#96A0B8}

/* ── generic cards ── */
.card{background:var(--surface); border:1px solid var(--border); border-radius:var(--r10);
  padding:var(--s6); box-shadow:var(--shadow);
  transition:transform .16s ease-out, box-shadow .16s ease-out}
.card h3{font-size:19px; margin:0 0 var(--s2)}
.grid2{display:grid; grid-template-columns:1fr 1fr; gap:var(--s4)}
.grid3{display:grid; grid-template-columns:repeat(3,1fr); gap:var(--s4)}
.icon{width:24px; height:24px; flex:none}

/* ── problem table ── */
.axes{margin-top:var(--s8); border-top:1px solid #2A3550}
.axis{display:grid; grid-template-columns:22% 34% 1fr; gap:var(--s6); align-items:baseline;
  padding:var(--s4) 0; border-bottom:1px solid #2A3550}
.axis b{font-weight:600; font-size:15px; color:#DCE3F0}
.axis p{margin:0; font-size:14px; color:#96A0B8; max-width:none}
.axis p.eff{color:#B9C2D6}
/* ترويسةُ الجدول — T-145. وكانت ثلاثةَ أعمدةٍ بلا عنوان، فلا يعرف القارئ
   أنّ الثاني حالٌ والثالثَ أثرُه. */
.axis-head{border-bottom-color:#3A4763}
.axis-head b,.axis-head p{font-size:11px; font-weight:500; letter-spacing:.04em;
  color:#78849F; text-transform:uppercase}
/* وسهمُ السببيّة يتبع اتّجاه الصفحة، فيقرأ العينُ «حالٌ ← أثر». */
.axis p.eff::before{content:"←"; color:#5E6B87; margin-inline-end:8px}
html[dir="ltr"] .axis p.eff::before{content:"→"}
.axis-head p.eff::before{content:none}
.axis .lbl{display:none}
.verdict{margin-top:var(--s12); border-inline-start:3px solid var(--gold);
  padding-inline-start:var(--s6); max-width:62ch}
.verdict p{font-size:clamp(17px,2.1vw,21px); font-weight:600; line-height:1.7; color:#F1F3F8; margin:0}

/* ── anatomy: نموذجٌ حيّ، لا لقطات ── */
.anat{margin-top:var(--s12); display:flex; flex-direction:column; gap:var(--s12)}
.anat-row{display:grid; grid-template-columns:32% 1fr; gap:clamp(20px,3vw,44px); align-items:start}
.anat-say{position:sticky; top:96px}
.anat-n{width:30px; height:30px; border-radius:50%; background:var(--brand); color:#fff;
  display:flex; align-items:center; justify-content:center; font-family:var(--lt); font-size:13px;
  font-weight:500; margin-bottom:var(--s3)}
.anat-say h3{font-size:19px; margin:0 0 var(--s2)}
.anat-say p{font-size:14px; color:var(--muted); margin:0; max-width:38ch}
.anat-extra{margin-top:var(--s12); display:flex; align-items:flex-start; gap:var(--s3);
  padding:var(--s4) var(--s6); background:var(--surface-alt); border-radius:var(--r10);
  border:1px solid var(--border)}
.anat-extra svg{color:var(--gold); margin-top:4px; flex:none}
.anat-extra p{margin:0; font-size:14px; color:var(--muted)}
.demo-note{margin-top:var(--s6); font-size:12px; color:var(--faint); display:flex; gap:9px;
  align-items:flex-start; max-width:74ch}
.demo-note svg{color:var(--gold); flex:none; margin-top:3px}
.showcase{margin-top:var(--s6); background:var(--brand-tint); border:1px solid #C6CFE2;
  border-radius:var(--r10); padding:var(--s6); display:flex; gap:var(--s6); flex-wrap:wrap;
  align-items:center; justify-content:space-between}
.showcase b{display:block; font-size:14px; color:var(--brand); margin-bottom:4px}
.showcase p{margin:0; font-size:13.5px; color:var(--ink); max-width:56ch}
.showcase .btn{flex:none}

/* ── لوح النموذج: ألوان القالب وبنيته، ومحتواه مواضعُ موصوفة ── */
.d{--d-paper:#F2F1EC; --d-paper2:#E6E5DE; --d-brand:#243B6B; --d-deep:#152444;
   --d-gold:#9A7B3C; --d-gold-l:#C6A468; --d-ink:#1E1E24; --d-soft:#4F4F58;
   --d-rule:rgba(154,123,60,.28);
   border-radius:var(--r10); overflow:hidden; box-shadow:var(--shadow);
   border:1px solid var(--border); background:var(--d-paper); color:var(--d-ink)}
.d-slot{color:var(--d-soft); font-style:normal}

/* الترويسة */
.d-head{background:linear-gradient(180deg,var(--d-brand),var(--d-deep)); color:#F2F1EC;
  padding:clamp(20px,3vw,34px) clamp(16px,3vw,30px); text-align:center}
.d-orn{display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:var(--s6)}
.d-orn i{height:1px; flex:1; background:linear-gradient(90deg,transparent,var(--d-gold-l))}
.d-orn i:last-child{background:linear-gradient(270deg,transparent,var(--d-gold-l))}
.d-orn b{width:9px; height:9px; transform:rotate(45deg); background:var(--d-gold-l); flex:none}
.d-title{font-family:var(--amiri); font-size:clamp(24px,3.6vw,34px); line-height:1.5; margin:0 0 6px;
  color:#F6F3EA; max-width:none}
.d-sub{font-size:13px; color:#B9C2D6; margin:0 auto var(--s6); max-width:44ch}
.d-ayah{font-family:var(--amiri); font-size:clamp(15px,1.9vw,18px); line-height:2; color:#EDE6D6;
  margin:0 auto; max-width:46ch}
.d-src{font-size:11px; color:var(--d-gold-l); margin:8px 0 0}
.d-attrib{display:grid; grid-template-columns:1fr 1fr; margin:var(--s6) calc(-1 * clamp(16px,3vw,30px)) calc(-1 * clamp(20px,3vw,34px));
  border-top:1px solid rgba(198,164,104,.22); background:rgba(0,0,0,.16)}
.d-attrib div{padding:var(--s4) var(--s3)}
.d-attrib div+div{border-inline-start:1px solid rgba(198,164,104,.18)}
.d-attrib span{display:block; font-size:10.5px; color:#94A0BC; margin-bottom:4px}
.d-attrib b{font-size:13.5px; font-weight:500; color:#E7E2D5}

/* الورق */
.d-paper{padding:clamp(18px,2.6vw,30px)}
.d-sechead{display:flex; align-items:center; gap:10px; margin-bottom:var(--s4)}
.d-sechead h4{font-family:var(--amiri); font-size:20px; font-weight:400; margin:0; color:var(--d-ink)}
.d-sechead i{width:18px; height:18px; border:1.5px solid var(--d-gold); border-radius:50%; flex:none}
.d-sechead u{flex:1; height:1px; background:var(--d-rule); text-decoration:none}
.d-lead{font-size:13.5px; line-height:1.95; color:var(--d-soft); margin:0 0 var(--s4); max-width:none}
.d-sacred{background:#EDEAE0; border-inline-start:4px solid var(--d-gold); border-radius:0 var(--r6) var(--r6) 0;
  padding:var(--s4) var(--s6)}
.d-sacred p{font-family:var(--amiri); font-size:16px; line-height:2; margin:0; max-width:none; color:var(--d-ink)}
.d-sacred span{display:block; font-size:11px; color:var(--d-gold); margin-top:8px}

/* قائمة التخريج */
.d-srchead{font-family:var(--amiri); font-size:18px; font-weight:400; margin:0 0 var(--s4);
  padding-bottom:9px; border-bottom:1px solid var(--d-rule)}
.d-list{counter-reset:d}
.d-list li{counter-increment:d; display:flex; gap:10px; align-items:baseline; padding:9px 0; font-size:12.5px}
.d-list li+li{border-top:1px solid rgba(0,0,0,.05)}
.d-list li::before{content:counter(d); font-family:var(--lt); font-size:11px; color:var(--d-gold);
  min-width:14px; flex:none}
.d-list .r{color:var(--d-brand); font-weight:500; flex:none}
.d-list .x{color:var(--d-soft); font-family:var(--amiri); font-size:14px; min-width:0; overflow-wrap:anywhere}
/*
  ★ **وعلى الجوال يُنضَّد الموضعُ فوق نصّه** — بلاغُ مالك المنتج، ١٦ أيلول
  ٢٠٢٦. فالصفُّ الواحد يحبس لفظَ الحديث في عمودٍ ضيّقٍ بجانب تخريجه، فيخرج
  مقطَّعاً سطرين وثلاثة بكلمتين في السطر. **ولفظُ الشاهد أولى الصفحة بعرضها.**
*/
@media (max-width:767px){
  .d-list li{display:grid; grid-template-columns:auto 1fr; column-gap:10px; row-gap:4px; align-items:start}
  .d-list li::before{grid-row:1; grid-column:1}
  .d-list .r{grid-column:2; grid-row:1}
  .d-list .x{grid-column:2; grid-row:2; font-size:15px; line-height:1.95}
}

/* النسبة الأمينة */
.d-attest{padding:var(--s6) clamp(18px,2.6vw,30px); background:#EDEAE0; text-align:center}
.d-attest p{font-size:11.5px; line-height:1.9; color:var(--d-soft); margin:0 auto; max-width:52ch}
.d-attest a{color:var(--d-brand); font-size:11.5px; display:inline-block; margin-top:6px}
.d-attest .mk{display:flex; align-items:center; justify-content:center; gap:7px; margin-top:var(--s4);
  padding-top:var(--s4); border-top:1px solid var(--d-rule); font-size:11px; color:var(--d-soft)}
.d-attest .mk b{font-family:var(--kufi); font-size:15px; color:var(--d-brand); font-weight:600}

/* ── steps ── */
.steps{display:grid; grid-template-columns:repeat(3,1fr); gap:var(--s4); margin-top:var(--s8)}
.step{background:var(--surface); border:1px solid var(--border); border-radius:var(--r10);
  padding:var(--s4) var(--s4) var(--s6); position:relative;
  transition:transform .16s ease-out, box-shadow .16s ease-out}
.step:hover{transform:translateY(-2px); box-shadow:var(--shadow)}
.step .n{font-family:var(--lt); font-size:12px; font-weight:500; color:var(--faint);
  width:26px; height:26px; border-radius:50%; border:1px solid var(--border-strong);
  display:flex; align-items:center; justify-content:center; margin-bottom:var(--s3)}
.step h3{font-size:15.5px; margin:0 0 4px}
.step p{font-size:13px; color:var(--muted); margin:0; max-width:none}
.step.gate{background:var(--brand-tint); border-color:#C6CFE2}
.step.gate .n{background:var(--warning); color:#fff; border-color:var(--warning)}
.step.gate .flag{display:block; margin-top:var(--s3); font-size:11.5px; color:var(--gold-text); font-weight:500}

/* ── verify ── */
.tracks{display:grid; grid-template-columns:repeat(3,1fr); gap:var(--s4); margin-top:var(--s8)}
.track{border-radius:var(--r10); padding:var(--s6); border:1px solid var(--border);
  background:var(--surface); box-shadow:var(--shadow); border-top-width:3px}
.track.ok{border-top-color:var(--success)}
.track.mid{border-top-color:var(--warning)}
.track.no{border-top-color:var(--danger)}
.track .tag{display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:600;
  padding:4px 10px; border-radius:100px; margin-bottom:var(--s3)}
.track.ok .tag{background:#E9F0EC; color:var(--success)}
.track.mid .tag{background:#F7F0E4; color:var(--gold-text)}
.track.no .tag{background:#F7EDEA; color:var(--danger)}
.track h3{font-size:17px; margin:0 0 var(--s2)}
.track p{font-size:13.5px; color:var(--muted); margin:0; max-width:none}

.gatebox{margin-top:var(--s12); background:var(--surface); border:1px solid var(--border);
  border-radius:var(--r14); box-shadow:var(--shadow); overflow:hidden}
.gatebox .head{padding:var(--s4) var(--s6); border-bottom:1px solid var(--border);
  background:var(--surface-alt); display:flex; align-items:center; gap:var(--s3); flex-wrap:wrap}
.gatebox .head b{font-size:14px; font-weight:600}
.gatebox .head .pill{font-size:11.5px; font-weight:600; color:var(--gold-text);
  background:#F7F0E4; padding:3px 10px; border-radius:100px}
.diff{display:grid; grid-template-columns:1fr 1fr; gap:0}
.diff>div{padding:var(--s6)}
.diff>div+div{border-inline-start:1px solid var(--border)}
.diff .k{font-size:11.5px; color:var(--faint); display:block; margin-bottom:var(--s3)}
.diff .t{font-family:var(--amiri); font-size:19px; line-height:2.1; margin:0; max-width:none}
.diff mark{background:#F7E7CB; color:inherit; padding:1px 3px; border-radius:3px}
.diff .srcline{font-size:11.5px; color:var(--muted); margin:var(--s3) 0 0; max-width:none}
.gatebox .foot{padding:var(--s4) var(--s6); border-top:1px solid var(--border);
  display:flex; gap:var(--s3); flex-wrap:wrap; align-items:center}
.mock-btn{display:inline-flex; align-items:center; gap:7px; font-size:13.5px; font-weight:500;
  padding:9px 16px; border-radius:var(--r6)}
.mock-btn.p{background:var(--brand); color:#fff}
.mock-btn.s{border:1px solid var(--border-strong); color:var(--muted)}
.gatebox figcaption{padding:0 var(--s6) var(--s4); margin:0}
.notebox{margin-top:var(--s8); background:var(--brand-tint); border:1px solid #C6CFE2;
  border-radius:var(--r10); padding:var(--s6)}
.notebox b{display:block; font-size:12px; color:var(--brand); margin-bottom:var(--s2); font-weight:600}
.notebox p{margin:0; font-size:14px; color:var(--ink); max-width:72ch}

/* ── bento ── */
.bento{display:grid; grid-template-columns:repeat(5,1fr); gap:var(--s4); margin-top:var(--s8)}
.bento>.card:nth-child(1),.bento>.card:nth-child(4){grid-column:span 3}
.bento>.card:nth-child(2),.bento>.card:nth-child(3){grid-column:span 2}
/* «من يستفيد» أربعُ بطاقاتٍ بمحتوًى متساوٍ (ثلاثُ نقاط لكلٍّ)، فالشبكةُ
   غير المتساوية تترك نصفَ العريضتين فارغاً. شبكةٌ ٢×٢ تملؤها كلَّها. */
#audience .bento{grid-template-columns:repeat(2,1fr)}
#audience .bento>.card{grid-column:span 1}
.card:hover{transform:translateY(-2px); box-shadow:var(--shadow-lift)}
.card .ico{width:38px; height:38px; border-radius:var(--r6); background:var(--brand-tint);
  color:var(--brand); display:flex; align-items:center; justify-content:center; margin-bottom:var(--s4)}
.pain{font-size:13.5px; color:var(--gold-text); margin:0 0 var(--s4); max-width:none}
.wins{display:flex; flex-direction:column; gap:var(--s2)}
.wins li{display:flex; gap:9px; font-size:14px; color:var(--muted); line-height:1.75}
.wins svg{color:var(--success); flex:none; margin-top:5px}

/* ── brand section ── */
.pal{display:grid; grid-template-columns:repeat(3,1fr); gap:var(--s3); margin-top:var(--s4)}
.pal .p{border:1px solid var(--border); border-radius:var(--r6); overflow:hidden; background:var(--surface)}
.pal .sw{display:flex; height:34px; border-bottom:1px solid var(--border)}
.pal .sw i{flex:1}
/* الورقيّ فاتحٌ يكاد يذوب في بياض البطاقة — حدٌّ داخليّ يُظهر حدَّه. */
.pal .sw i:first-child{box-shadow:inset 0 0 0 1px rgba(34,32,26,.10)}
.pal .nm{font-size:11.5px; color:var(--muted); padding:6px 8px; text-align:center}
.langrow{display:flex; flex-wrap:wrap; gap:var(--s2); margin-top:var(--s4)}
.chip{font-size:12.5px; padding:5px 12px; border-radius:100px; background:var(--surface-alt);
  border:1px solid var(--border); color:var(--muted)}

/* ── faq ── */
.faq{margin-top:var(--s8); max-width:820px}
.faq details{border-bottom:1px solid var(--border)}
.faq summary{display:flex; align-items:flex-start; gap:var(--s4); padding:var(--s4) 0; cursor:pointer;
  font-size:16.5px; font-weight:500; list-style:none; min-height:44px}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:""; width:13px; height:13px; flex:none; margin-inline-start:auto; margin-top:9px;
  background:linear-gradient(var(--brand),var(--brand)) center/13px 1.5px no-repeat,
             linear-gradient(var(--brand),var(--brand)) center/1.5px 13px no-repeat;
  transition:transform .18s ease-out}
.faq details[open] summary::after{
  background:linear-gradient(var(--brand),var(--brand)) center/13px 1.5px no-repeat}
.faq .a{padding:0 0 var(--s6)}
.faq .a p{font-size:15px; color:var(--muted); margin:0}

/* ── closing ── */
.closing{background:var(--brand); color:#fff; padding-block:clamp(56px,7vw,104px)}
.closing h2{color:#fff; font-size:clamp(26px,3.4vw,40px); margin-bottom:var(--s4)}
.closing .lede{color:#C8D2E6; margin-bottom:var(--s8)}
.closing .cols{display:grid; grid-template-columns:1fr 1fr; gap:clamp(28px,4vw,64px); align-items:start}
.closing .alt{margin-top:var(--s4)}
.closing .alt a{color:#fff; font-size:14px}
.form{background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.16);
  border-radius:var(--r14); padding:clamp(20px,2.6vw,32px)}
.field{margin-bottom:var(--s4)}
.field label{display:block; font-size:13px; color:#C8D2E6; margin-bottom:6px}
.field input,.field select,.field textarea{width:100%; min-height:44px; padding:10px 14px; font-family:var(--ar);
  font-size:15px; color:#fff; background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.22); border-radius:var(--r6); appearance:none}
.field select{background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%23C8D2E6' stroke-width='1.6'><path d='M4 6l4 4 4-4'/></svg>");
  background-repeat:no-repeat; background-position:left 14px center}
/* حقلُ الرسالة — T-142. **ويُمدّ بالكتابة ولا يَحبسها**: `resize:vertical`
   والحدُّ الأدنى ثلاثةُ أسطر، فيُقرأ موضعَ كلامٍ لا حقلاً آخر من التصنيف. */
.field textarea{min-height:92px; line-height:1.7; resize:vertical}
.field input::placeholder,.field textarea::placeholder{color:rgba(255,255,255,.45)}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--gold-light); background:rgba(255,255,255,.12)}
.form .btn{width:100%}
.form .tiny{color:#A9B6D0; margin-top:var(--s3)}
.field .err{font-size:12.5px; color:#F4C9A8; margin:6px 0 0; max-width:none}
.field [aria-invalid="true"]{border-color:#E0A07A}
/* تبويبا النموذج — T-158. **ويظهران مع السكربت وحده**: بلاه يُعرض النموذجان
   متتاليَين بعنوانيهما (`.panel-t`)، فلا يُخفى نموذجٌ لا يُبلغ. */
.tabs{display:none; gap:4px; padding:4px; margin-bottom:var(--s6);
  background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.16); border-radius:var(--r10)}
.js .tabs{display:flex}
.tabs [role="tab"]{flex:1; min-height:42px; padding:8px 12px; border:0; border-radius:var(--r6);
  background:transparent; color:#C8D2E6; font:inherit; font-size:14.5px; font-weight:600; cursor:pointer;
  transition:background-color .15s, color .15s}
.tabs [role="tab"]:hover{color:#fff; background:rgba(255,255,255,.08)}
.tabs [role="tab"][aria-selected="true"]{background:#fff; color:var(--brand)}
.js .panel[data-off]{display:none}
.panel+.panel{margin-top:var(--s8); padding-top:var(--s8); border-top:1px solid rgba(255,255,255,.16)}
.js .panel+.panel{margin-top:0; padding-top:0; border-top:0}
.panel-t{font-size:17px; color:#fff; margin:0 0 var(--s2)}
.js .panel-t{display:none}
.panel .hint{font-size:14px; line-height:1.75; color:#C8D2E6; margin:0 0 var(--s4); max-width:none}
.field .opt{color:#A9B6D0}
.form-done{display:flex; gap:var(--s4); align-items:flex-start}
.form-done svg{color:var(--gold-light); flex:none; margin-top:2px}
.form-done p{margin:0; color:#E8EEF9; font-size:15px}

footer{background:var(--night-deep); color:#A7B0C4; padding-block:var(--s12) var(--s8)}
footer .cols{display:grid; grid-template-columns:1.3fr 1fr 1fr; gap:var(--s8)}
footer .logo{color:#F1F3F8}
footer .logo svg{color:var(--brand-lift)}
footer .slogan{font-size:13.5px; color:#8A93A8; margin:var(--s4) 0 0; max-width:34ch}
footer h3{font-size:12px; color:#F1F3F8; margin:0 0 var(--s3); font-weight:600}
footer li{margin-bottom:9px}
footer a{color:#A7B0C4; text-decoration:none; font-size:13.5px}
footer a:hover{color:#F1F3F8; text-decoration:underline}
footer .bar{margin-top:var(--s12); padding-top:var(--s6); border-top:1px solid #212C44;
  display:flex; justify-content:space-between; gap:var(--s4); flex-wrap:wrap; align-items:center}
footer .lat{font-family:var(--lt); direction:ltr; letter-spacing:.2em; font-size:11px; color:#8A95AE}
footer .lat a{color:inherit; text-decoration:none; border-bottom:1px solid rgba(138,149,174,.4)}
footer .lat a:hover{color:#F1F3F8; border-color:currentColor}
footer .rights{font-size:12px; color:#8A95AE; margin:0}

/* ── reveal ── */
.js .rv{opacity:0; transform:translateY(14px); transition:opacity .4s ease-out, transform .4s ease-out}
.js .rv.in{opacity:1; transform:none}

/* ── responsive ── */
@media (max-width:1023px){
  .navlinks{display:none}
  /* ★ الترويسةُ وحدها — T-132. القاعدةُ قُصد بها شريطاً ضيّقاً فيه زرّان،
     فأصابت الدرجَ بالعرَض وأسقطت «دخول» من القائمة التي فُتحت لتعرض كلَّ شيء. */
  .topbar .navcta .btn-ghost{display:none}
  .burger{display:flex}
  .navcta{margin-inline-start:auto}
  .hero .grid{grid-template-columns:1fr; gap:var(--s8)}
  .anat-row{grid-template-columns:1fr; gap:var(--s4)}
  .anat-say{position:static}
  .steps{grid-template-columns:1fr}
  .bento{grid-template-columns:repeat(2,1fr)}
  .bento>.card{grid-column:span 1 !important}
  .closing .cols{grid-template-columns:1fr}
  footer .cols{grid-template-columns:1fr 1fr}
}
/*
  بطاقتان على شاشةٍ ضيّقة تكسران الكلمةَ ثلاثةَ أسطر — T-145.

  ★ **و`#audience .bento` تُنقض بمثلها** (T-146): قاعدةُ المُعرِّف أخصُّ من
  الصنف، **والاستعلامُ لا يزيد أخصّيّة**. فكانت بطاقاتُ «من يستفيد» عمودين
  على الجوال منذ بنائها، ولا ينقضها `.bento{1fr}` مهما ضاقت الشاشة.
*/
@media (max-width:899px){
  .bento,#audience .bento{grid-template-columns:1fr}
}
@media (max-width:767px){
  body{font-size:15.5px}
  .wrap{padding-inline:18px}
  .axis{grid-template-columns:1fr; gap:var(--s1)}
  .axis-head{display:none}
  .axis .lbl{display:block; font-size:11px; letter-spacing:.03em; color:#78849F; margin-bottom:1px}
  /* والسهمُ يسقط في العمود الواحد — و`html[dir]` أخصُّ من الصنف، فتُنقض معه. */
  .axis p.eff::before,html[dir="ltr"] .axis p.eff::before{content:none}
  .axis p.eff{margin-top:var(--s2)}
  .steps{grid-template-columns:1fr}
  .tracks{grid-template-columns:1fr}
  .grid2,.grid3,.bento{grid-template-columns:1fr}
  .d-attrib{grid-template-columns:1fr}
  .d-attrib div+div{border-inline-start:0; border-top:1px solid rgba(198,164,104,.18)}
  .diff{grid-template-columns:1fr}
  .diff>div+div{border-inline-start:0; border-top:1px solid var(--border)}
  .pal{grid-template-columns:repeat(2,1fr)}
  footer .cols{grid-template-columns:1fr; gap:var(--s6)}
  .shot.tilt .d{transform:none}
  .showcase{flex-direction:column; align-items:stretch; text-align:center}
  .showcase .btn{width:100%}
}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{animation:none !important; transition:none !important}
  .rv{opacity:1 !important; transform:none !important}
  .card:hover,.step:hover{transform:none}
}
@media print{.topbar,.drawer,.closing .form{display:none}}

/* ————— تعدّد اللغات — T-131 ————— */
/* اللاتينيةُ والكيريلّية لا تنتفعان بريم كوفي ولا ببلكس عربي، ولا يُحمَّلان لهما. */
html[dir="ltr"] body{font-family:var(--lt)}
html[dir="ltr"] h1,html[dir="ltr"] h2{font-family:var(--lt); font-weight:600; letter-spacing:-.015em}
/* والعنوانُ اللاتينيُّ أطولُ كلماتٍ من نظيره العربيّ، فيُصغَّر كي لا يبلغ ثلاثة أسطر — T-143. */
html[dir="ltr"] .hero h1{font-size:clamp(34px,4.3vw,52px); line-height:1.15}
html[dir="ltr"] p{max-width:74ch}
html[dir="ltr"] .dir{transform:scaleX(-1)}
/* والشاهدُ الشرعيّ عربيٌّ في كلّ لغة (T-38)، فيُقلب اتّجاهُه وحده. */
html[dir="ltr"] .ar{font-family:var(--amiri); direction:rtl; text-align:start; display:block}
.langsw{position:relative; flex:none}
.langsw>summary{display:flex; align-items:center; gap:6px; min-height:44px; padding:0 10px;
  border-radius:var(--r6); font-family:var(--lt); font-size:13px; color:var(--muted);
  cursor:pointer; white-space:nowrap; list-style:none}
.langsw>summary::-webkit-details-marker{display:none}
.langsw>summary::marker{content:""}
.langsw>summary:hover,.langsw[open]>summary{color:var(--ink); background:var(--surface-alt)}
.langsw .chev{transition:transform .15s ease-out}
.langsw[open] .chev{transform:rotate(180deg)}
.langsw>ul{position:absolute; inset-inline-end:0; top:calc(100% + 4px); z-index:60;
  min-width:152px; padding:var(--s1); background:var(--surface);
  border:1px solid var(--border); border-radius:var(--r10); box-shadow:var(--shadow-lift)}
.langsw>ul a{display:block; padding:8px 10px; border-radius:var(--r6); font-family:var(--lt);
  font-size:13px; line-height:1.6; color:var(--ink); text-decoration:none; white-space:nowrap}
.langsw>ul a:hover{background:var(--surface-alt)}
.langsw>ul a[aria-current="true"]{color:var(--brand); font-weight:500; background:var(--brand-tint)}
/* وفي الدرج صفٌّ مبسوط — قائمةٌ داخل قائمةٍ نقرتان بلا سبب. */
.langrow-sw ul{display:flex; flex-wrap:wrap; gap:var(--s2); padding:0 var(--s6) var(--s6)}
.drawer .langrow-sw a{display:inline-block; padding:7px 13px; border:1px solid var(--border-strong);
  border-radius:999px; font-family:var(--lt); font-size:13px; line-height:1.5;
  color:var(--muted); text-decoration:none}
.drawer .langrow-sw a[aria-current="true"]{color:#fff; background:var(--brand); border-color:var(--brand)}
@media (max-width:1023px){ .navcta .langsw{display:none} }
</style>
</head>
<body>
<script>document.documentElement.className="js"</script>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="mk" viewBox="0 0 64 64">
    <path d="M23 14H14V50H23" fill="none" stroke="currentColor" stroke-width="6"/>
    <path d="M41 14H50V50H41" fill="none" stroke="currentColor" stroke-width="6"/>
    <circle cx="32" cy="32" r="6" fill="#A87C33"/>
  </symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></symbol>
    <symbol id="i-dot" viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="3.2"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A1.5 1.5 0 015.5 4H11v16H5.5A1.5 1.5 0 014 18.5zM20 5.5A1.5 1.5 0 0018.5 4H13v16h5.5a1.5 1.5 0 001.5-1.5z"/></symbol>
    <symbol id="i-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0013 0M12 18v3"/></symbol>
    <symbol id="i-house" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5L12 4l8 6.5V20H4z"/><path d="M10 20v-5h4v5"/></symbol>
    <symbol id="i-cap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 9L12 5l9.5 4L12 13z"/><path d="M6.5 11v5c0 1.4 2.5 2.5 5.5 2.5s5.5-1.1 5.5-2.5v-5"/></symbol>
    <symbol id="i-palette" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5a8.5 8.5 0 000 17c1.4 0 2-.9 2-1.8 0-1.6-1.4-1.8-1.4-3 0-.9.7-1.6 1.7-1.6h1.4A4.8 4.8 0 0020.5 9C20.5 5.9 16.7 3.5 12 3.5z"/><circle cx="8" cy="9.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="15.8" cy="10" r="1"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.2 2.4 3.2 5.4 3.2 8.5s-1 6.1-3.2 8.5c-2.2-2.4-3.2-5.4-3.2-8.5S9.8 5.9 12 3.5z"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13.5a3.5 3.5 0 005 0l3-3a3.5 3.5 0 00-5-5l-1 1"/><path d="M14 10.5a3.5 3.5 0 00-5 0l-3 3a3.5 3.5 0 005 5l1-1"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5l7 2.5v5.5c0 4-2.9 7.5-7 9-4.1-1.5-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/></symbol>
    <symbol id="i-layers" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5L3.5 8 12 12.5 20.5 8z"/><path d="M3.5 12.5L12 17l8.5-4.5"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></symbol>
    <symbol id="i-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9.5l6 6 6-6"/></symbol>
</svg>

<a class="skip" href="#main">{{ __('landing.nav.skip') }}</a>

{{--
    مبدّلُ اللغة — موضعٌ واحد بصورتين.

    **وأربعةُ أسماءٍ مبسوطةً في الترويسة خطأ**: صارت أعرضَ من روابط
    الأقسام كلِّها وزاحمت زرَّ الدعوة، والزائرُ يقرأ لغته مرّةً ثمّ لا
    يعود — فلا تستحقّ هذا الحيّز. فيُعرض المختارُ وحده، والبقيّةُ عند
    الطلب.

    **وفي الدرج تبقى مبسوطة**: القائمةُ المنسدلة داخل قائمةٍ منسدلة
    نقرتان بلا سبب، والعرضُ هناك متاح.
--}}
@php
    $langSwitch = static function (string $variant = 'bar') use ($locale, $urlFor): string {
        $items = '';
        foreach (Locale::all() as $l) {
            $items .= '<li><a href="'.e($urlFor($l)).'" lang="'.$l->value.'"'
                .($l === $locale ? ' aria-current="true"' : '')
                .'>'.e($l->nativeName()).'</a></li>';
        }

        $label = e(__('landing.nav.lang_aria'));

        if ($variant === 'drawer') {
            return '<nav class="langrow-sw" aria-label="'.$label.'"><ul>'.$items.'</ul></nav>';
        }

        return '<details class="langsw">'
            .'<summary aria-label="'.$label.'">'
            .'<svg width="16" height="16" aria-hidden="true"><use href="#i-globe"/></svg>'
            .'<span lang="'.$locale->value.'">'.e($locale->nativeName()).'</span>'
            .'<svg class="chev" width="12" height="12" aria-hidden="true"><use href="#i-chev"/></svg>'
            .'</summary><ul>'.$items.'</ul></details>';
    };
@endphp

<header class="topbar" id="topbar">
  <div class="wrap">
    <a class="logo" href="#hero" aria-label="{{ __('landing.nav.home_aria') }}">
      <svg width="24" height="24" aria-hidden="true"><use href="#mk"/></svg>
      <b>{{ __('landing.meta.site_name') }}</b>
    </a>
    <nav class="navlinks" aria-label="{{ __('landing.nav.sections_aria') }}">
      <a href="#anatomy">{{ __('landing.nav.anatomy') }}</a>
      <a href="#how">{{ __('landing.nav.how') }}</a>
      <a href="#verify">{{ __('landing.nav.verify') }}</a>
      <a href="#audience">{{ __('landing.nav.audience') }}</a>
      <a href="#faq">{{ __('landing.nav.faq') }}</a>
    </nav>
    <div class="navcta">
      {!! $langSwitch() !!}
      <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">{{ __('landing.nav.login') }}</a>
      <a class="btn btn-primary btn-sm" href="#invite">{{ __('landing.nav.contact') }}</a>
      <button class="burger" id="burger" aria-expanded="false" aria-controls="drawer" aria-label="{{ __('landing.nav.menu_aria') }}">
        <svg width="22" height="22" aria-hidden="true"><use href="#i-menu"/></svg>
      </button>
    </div>
  </div>
</header>
<div class="drawer" id="drawer">
  <ul>
    <li><a href="#anatomy">{{ __('landing.nav.anatomy') }}</a></li>
    <li><a href="#how">{{ __('landing.nav.how') }}</a></li>
    <li><a href="#verify">{{ __('landing.nav.verify') }}</a></li>
    <li><a href="#audience">{{ __('landing.nav.audience') }}</a></li>
    <li><a href="#faq">{{ __('landing.nav.faq') }}</a></li>
  </ul>
  <div class="navcta">
    <a class="btn btn-ghost" href="{{ route('login') }}">{{ __('landing.nav.login') }}</a>
    <a class="btn btn-primary" href="#invite">{{ __('landing.nav.contact') }}</a>
  </div>
  {!! $langSwitch('drawer') !!}
</div>

<main id="main">

<!-- ١ · البطل -->
<section class="hero" id="hero">
  <div class="wrap">
    <div class="grid">
      <div>
        {{-- الشعارُ فوق العنوان، والعنوانُ يقول ما يفعله المنتج — T-143. --}}
        <p class="eyebrow">{{ __('landing.hero.eyebrow') }}</p>
        <h1>{{ __('landing.hero.h1') }}</h1>
        <p class="lede">{{ __('landing.hero.lede') }}</p>
        <div class="acts">
          <a class="btn btn-primary" href="#invite">{{ __('landing.hero.cta_primary') }}</a>
          @if ($showcase)
          <a class="btn btn-ghost" href="{{ $showcase->url }}" target="_blank" rel="noopener">{{ __('landing.hero.cta_real') }}
            <svg class="dir" width="17" height="17" aria-hidden="true"><use href="#i-arrow"/></svg></a>
          @else
          <a class="btn btn-ghost" href="#anatomy">{{ __('landing.hero.cta_ghost') }}
            <svg class="dir" width="17" height="17" aria-hidden="true"><use href="#i-arrow"/></svg></a>
          @endif
        </div>
        <p class="assure">
          @foreach (['time', 'youtube', 'noinstall', 'langs'] as $badge)
          <span><svg width="14" height="14" aria-hidden="true"><use href="#i-check"/></svg> {{ __('landing.hero.assure_'.$badge) }}</span>
          @endforeach
        </p>
        <p class="access">{{ __('landing.hero.access') }}</p>
      </div>
      <div class="shot tilt">
        @if ($showcase)
        @include('landing.showcase-hero', ['showcase' => $showcase, 'locale' => $locale])
        @else
        <div class="d">
          <div class="d-head">
            <div class="d-orn"><i></i><b></b><i></i></div>
            <p class="d-title d-slot">{{ __('landing.demo.title') }}</p>
            <p class="d-sub d-slot">{{ __('landing.demo.subtitle') }}</p>
            <p class="d-ayah d-slot">{{ __('landing.demo.ayah') }}</p>
            <p class="d-src">{{ __('landing.demo.ayah_src') }}</p>
            <div class="d-attrib">
              <div><span>{{ __('landing.demo.speaker_label') }}</span><b class="d-slot">{{ __('landing.demo.speaker') }}</b></div>
              <div><span>{{ __('landing.demo.place_label') }}</span><b class="d-slot">{{ __('landing.demo.place') }}</b></div>
            </div>
          </div>
          <div class="d-paper d-peek" aria-hidden="true">
            <div class="d-sechead"><i></i><h4 class="d-slot">{{ __('landing.demo.axis_title') }}</h4><u></u></div>
            <p class="d-lead d-slot">{{ __('landing.demo.lead') }}</p>
            <div class="d-sacred">
              <p class="d-slot">{{ __('landing.demo.sacred') }}</p>
              <span>{{ __('landing.demo.ayah_src') }}</span>
            </div>
          </div>
        </div>
        <p class="shot-cap">{{ __('landing.hero.caption') }}</p>
        @endif
      </div>
    </div>
  </div>
</section>

<!-- ٢ · المشكلة -->
<section class="dark" id="problem">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.problem.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.problem.h2') }}</h2>
    <p class="lede rv">{{ __('landing.problem.lede') }}</p>
    <div class="axes rv">
      {{-- ترويسةٌ تقول ما الأعمدة — T-145. وعلى الجوال تسقط، وتحلّ محلَّها
           تسميةٌ فوق كلّ قيمة، فالعمودُ الواحد لا ترويسةَ له. --}}
      <div class="axis axis-head">
        <b>{{ __('landing.problem.head_aspect') }}</b>
        <p>{{ __('landing.problem.head_now') }}</p>
        <p class="eff">{{ __('landing.problem.head_effect') }}</p>
      </div>
      @foreach (__('landing.problem.axes') as $axis)
      <div class="axis">
        <b>{{ $axis['t'] }}</b>
        <p><span class="lbl">{{ __('landing.problem.head_now') }}</span>{{ $axis['f'] }}</p>
        <p class="eff"><span class="lbl">{{ __('landing.problem.head_effect') }}</span>{{ $axis['e'] }}</p>
      </div>
      @endforeach
    </div>
    <div class="verdict rv">
      <p>{{ __('landing.problem.verdict') }}</p>
    </div>
  </div>
</section>

<!-- ٣ · تشريح المخرَج -->
<section id="anatomy">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.anatomy.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.anatomy.h2') }}</h2>
    @if ($showcase)
    <p class="lede rv">{{ __('landing.anatomy.lede_real') }}</p>
    @else
    <p class="lede rv">{!! __('landing.anatomy.lede_html') !!}</p>
    @endif

    @if ($showcaseUrl)
      <div class="showcase rv">
        <div>
          <b>{{ __('landing.anatomy.showcase_title') }}</b>
          <p>{{ __('landing.anatomy.showcase_body') }}</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="{{ $showcaseUrl }}" target="_blank" rel="noopener">{{ __('landing.anatomy.showcase_cta') }}
          <svg class="dir" width="17" height="17" aria-hidden="true"><use href="#i-arrow"/></svg></a>
      </div>
    @endif

    <div class="anat">

      <div class="anat-row rv">
        <div class="anat-say"><div class="anat-n">1</div>
          <h3>{{ __('landing.anatomy.rows.0.t') }}</h3>
          <p>{{ __('landing.anatomy.rows.0.b') }}</p></div>
        <div class="d">
          @if ($showcase)
          <div class="d-paper" lang="ar" dir="rtl">
            @if ($showcase->secondAxisName)
            <div class="d-sechead"><i></i><h4>{{ $showcase->secondAxisName }}</h4><u></u></div>
            @endif
            @if ($showcase->secondAxisSummary)
            <p class="d-lead">{{ $showcase->secondAxisSummary }}</p>
            @endif
            @if ($showcase->keyAyah)
            <div class="d-sacred">
              <p>{{ \App\Support\Quran\AyahText::decorate($showcase->keyAyah->text) }}</p>
              <span lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">{{ $showcase->keyAyah->citation($locale) }}</span>
            </div>
            @endif
          </div>
          @else
          <div class="d-paper">
            <div class="d-sechead"><i></i><h4 class="d-slot">{{ __('landing.demo.axis_title') }}</h4><u></u></div>
            <p class="d-lead d-slot">{{ __('landing.demo.lead') }}</p>
            <div class="d-sacred">
              <p class="d-slot">{{ __('landing.demo.sacred') }}</p>
              <span>{{ __('landing.demo.ayah_src') }}</span>
            </div>
          </div>
          @endif
        </div>
      </div>

      <div class="anat-row rv">
        <div class="anat-say"><div class="anat-n">2</div>
          <h3>{{ __('landing.anatomy.rows.1.t') }}</h3>
          <p>{{ __('landing.anatomy.rows.1.b') }}</p></div>
        <div class="d">
          <div class="d-paper">
            <h4 class="d-srchead">{{ __('landing.demo.sources_head') }}</h4>
            @if ($showcase && $showcase->evidence !== [])
            <ol class="d-list" lang="ar" dir="rtl">
              @foreach ($showcase->evidence as $item)
              <li><span class="r" lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">{{ $item->citation($locale) }}</span><span class="x">{{ $item->kind === 'ayah' ? \App\Support\Quran\AyahText::decorate($item->text) : $item->text }}</span></li>
              @endforeach
            </ol>
            @else
            <ol class="d-list">
              <li><span class="r">{{ __('landing.demo.ayah_ref') }}</span><span class="x d-slot">{{ __('landing.demo.ayah_text') }}</span></li>
              <li><span class="r">{{ __('landing.demo.ayah_ref') }}</span><span class="x d-slot">{{ __('landing.demo.ayah_text') }}</span></li>
              <li><span class="r">{{ __('landing.demo.hadith_ref') }}</span><span class="x d-slot">{{ __('landing.demo.hadith_text') }}</span></li>
              <li><span class="r">{{ __('landing.demo.hadith_ref') }}</span><span class="x d-slot">{{ __('landing.demo.hadith_text') }}</span></li>
            </ol>
            @endif
          </div>
        </div>
      </div>

      <div class="anat-row rv">
        <div class="anat-say"><div class="anat-n">3</div>
          <h3>{{ __('landing.anatomy.rows.2.t') }}</h3>
          <p>{{ __('landing.anatomy.rows.2.b') }}</p></div>
        <div class="d">
          <div class="d-attest">
            <p>{{ __('landing.demo.attest') }}</p>
            <a href="#">{{ __('landing.demo.attest_link') }}</a>
            <div class="mk"><span>{{ __('landing.demo.mk_by') }}</span> <b>{{ __('landing.demo.mk_name') }}</b></div>
          </div>
        </div>
      </div>

    </div>

    <p class="demo-note rv">
      <svg width="16" height="16" aria-hidden="true"><use href="#i-shield"/></svg>
      <span>{{ $showcase ? __('landing.anatomy.note_real') : __('landing.anatomy.note') }}</span>
    </p>

    <div class="anat-extra rv">
      <svg width="20" height="20" aria-hidden="true"><use href="#i-layers"/></svg>
      <p>{{ __('landing.anatomy.extra') }}</p>
    </div>
  </div>
</section>

<!-- ٤ · كيف يعمل -->
<section id="how" style="background:var(--surface-alt); border-block:1px solid var(--border)">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.how.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.how.h2') }}</h2>
    <p class="lede rv">{{ __('landing.how.lede') }}</p>
    <div class="steps rv">
      @foreach (__('landing.how.steps') as $i => $step)
      <div class="step{{ $i === 1 ? ' gate' : '' }}"><div class="n">{{ $i + 1 }}</div><h3>{{ $step['t'] }}</h3><p>{{ $step['b'] }}</p>@if ($i === 1)<span class="flag">{{ __('landing.how.gate_flag') }}</span>@endif</div>
      @endforeach
    </div>
  </div>
</section>

<!-- ٥ · طبقة التحقّق -->
<section id="verify">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.verify.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.verify.h2') }}</h2>
    <p class="lede rv">{{ __('landing.verify.lede') }}</p>
    <p class="lede rv"><b>{{ __('landing.verify.mission') }}</b></p>

    <div class="tracks rv">
      @foreach (__('landing.verify.tracks') as $i => $track)
      <div class="track {{ ['ok', 'mid', 'no'][$i] }}">
        <span class="tag"><svg width="14" height="14" aria-hidden="true"><use href="#{{ ['i-check', 'i-dot', 'i-x'][$i] }}"/></svg> {{ $track['tag'] }}</span>
        <h3>{{ $track['t'] }}</h3>
        <p>{{ $track['b'] }}</p>
      </div>
      @endforeach
    </div>

    <figure class="gatebox rv" style="margin:var(--s12) 0 0">
      <div class="head">
        <b>{{ __('landing.verify.gate.title') }}</b>
        <span class="pill">{{ __('landing.verify.gate.pill') }}</span>
        <span class="tiny" style="margin-inline-start:auto">{{ __('landing.verify.gate.counter') }}</span>
      </div>
      {{--
        اللفظان عربيّان في كلّ لغة، والترجمةُ تحتهما موسومةً — كما يُنشر
        الملخّصُ غيرُ العربي فعلاً (T-38، و`lang/ar/locales.php`). وترجمةٌ
        بلا وسمٍ تُقرأ حديثاً بلغةٍ أخرى، وذلك نسبةُ لفظٍ لم يُقَل.
      --}}
      <div class="diff">
        <div>
          <span class="k">{{ __('landing.verify.gate.from_key') }}</span>
          <p class="t ar">إنّما الأعمال بالنيّات، <mark>ولكلِّ امرئٍ ما نوى</mark></p>
          @unless ($locale->isSource())
            <p class="srcline"><b>{{ $locale->meaningLabel() }}</b> — {{ __('landing.verify.gate.from_meaning') }}</p>
          @endunless
          <p class="srcline">{{ __('landing.verify.gate.from_src') }}</p>
        </div>
        <div>
          <span class="k">{{ __('landing.verify.gate.to_key') }}</span>
          <p class="t ar">إنّما الأعمالُ بالنيّاتِ، <mark>وإنّما لكلِّ امرئٍ ما نوى</mark></p>
          @unless ($locale->isSource())
            <p class="srcline"><b>{{ $locale->meaningLabel() }}</b> — {{ __('landing.verify.gate.to_meaning') }}</p>
          @endunless
          <p class="srcline">{{ __('landing.verify.gate.to_src') }}</p>
        </div>
      </div>
      <div class="foot">
        <span class="mock-btn p">{{ __('landing.verify.gate.btn_keep') }}</span>
        <span class="mock-btn s">{{ __('landing.verify.gate.btn_drop') }}</span>
        <span class="tiny" style="margin-inline-start:auto">{{ __('landing.verify.gate.foot') }}</span>
      </div>
      <figcaption>{{ __('landing.verify.gate.caption') }}</figcaption>
    </figure>

    <div class="notebox rv">
      <b>{{ __('landing.verify.note_title') }}</b>
      <p>{{ __('landing.verify.note_body') }}</p>
    </div>
  </div>
</section>

<!-- ٦ · من يستفيد -->
<section id="audience" style="background:var(--surface-alt); border-block:1px solid var(--border)">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.audience.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.audience.h2') }}</h2>
    <div class="bento rv">
      @foreach (__('landing.audience.cards') as $i => $card)
      <div class="card">
        <div class="ico"><svg width="20" height="20" aria-hidden="true"><use href="#{{ ['i-cap', 'i-mic', 'i-house', 'i-book'][$i] }}"/></svg></div>
        <h3>{{ $card['t'] }}</h3>
        <p class="pain">{{ $card['pain'] }}</p>
        <ul class="wins">
          @foreach ($card['wins'] as $win)
          <li><svg width="15" height="15" aria-hidden="true"><use href="#i-check"/></svg>{{ $win }}</li>
          @endforeach
        </ul>
      </div>
      @endforeach
    </div>
  </div>
</section>

<!-- ٧ · القالب والهوية -->
<section id="brand">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.brand.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.brand.h2') }}</h2>
    <p class="lede rv">{{ __('landing.brand.lede') }}</p>
    <div class="bento rv">

      <div class="card">
        <div class="ico"><svg width="20" height="20" aria-hidden="true"><use href="#i-layers"/></svg></div>
        <h3>{{ __('landing.brand.templates_title') }}</h3>
        <p class="sub">{{ __('landing.brand.templates_sub') }}</p>
        {{-- الأسماءُ وحدها — T-143. ووصفُ الستّة كان أطولَ ما في القسم، وموضعُه
             شاشةُ الإنشاء حيث يُختار القالب، لا الزيارةُ الأولى. --}}
        <div class="tpl-chips">
          @foreach (__('landing.brand.templates') as $tpl)
          <span class="chip">{{ $tpl }}</span>
          @endforeach
        </div>
      </div>

      <div class="card">
        <div class="ico"><svg width="20" height="20" aria-hidden="true"><use href="#i-palette"/></svg></div>
        <h3>{{ __('landing.brand.palettes_title') }}</h3>
        <p class="sub">{{ __('landing.brand.palettes_sub') }}</p>
        <div class="pal">
          @php
            // اللوحاتُ الستّ — ألوانُها ثابتةٌ في كلّ لغة، وأسماؤها وحدها تُترجَم.
            $swatches = [
                ['#F3EEE1', '#1B4D3E', '#A87C33'],
                ['#F2F1EC', '#243B6B', '#9A7B3C'],
                ['#F4EEE4', '#5A4227', '#9E7A38'],
                ['#F2F2F0', '#33403F', '#8E7E52'],
                ['#F4EFEC', '#4B2740', '#A07539'],
                ['#EFF2F1', '#1C4A4C', '#98803F'],
            ];
          @endphp
          @foreach (__('landing.brand.palettes') as $i => $name)
          <div class="p"><div class="sw">@foreach ($swatches[$i] as $hex)<i style="background:{{ $hex }}"></i>@endforeach</div><div class="nm">{{ $name }}</div></div>
          @endforeach
        </div>
      </div>

      <div class="card">
        <div class="ico"><svg width="20" height="20" aria-hidden="true"><use href="#i-shield"/></svg></div>
        <h3>{{ __('landing.brand.logo_title') }}</h3>
        <p class="sub">{{ __('landing.brand.logo_sub') }}</p>
      </div>

      <div class="card">
        <div class="ico"><svg width="20" height="20" aria-hidden="true"><use href="#i-globe"/></svg></div>
        <h3>{{ __('landing.brand.langs_title') }}</h3>
        <p class="sub">{{ __('landing.brand.langs_sub') }}</p>
        <div class="langrow">
          @foreach (Locale::all() as $l)
          <span class="chip">{{ $l->label() }}</span>
          @endforeach
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ٨ · الأسئلة -->
<section id="faq" style="background:var(--surface-alt); border-block:1px solid var(--border)">
  <div class="wrap">
    <p class="eyebrow rv">{{ __('landing.faq.eyebrow') }}</p>
    <h2 class="rv">{{ __('landing.faq.h2') }}</h2>
    <div class="faq rv">
      @foreach (__('landing.faq.items') as $i => $item)
      <details @if ($i === 0) open @endif><summary>{{ $item['q'] }}</summary>
        <div class="a"><p>{{ $item['a'] }}</p></div></details>
      @endforeach
    </div>
  </div>
</section>

</main>

<!-- ١٠ · الدعوة الأخيرة -->
<section class="closing" id="invite">
  <div class="wrap">
    <div class="cols">
      <div>
        <h2>{{ __('landing.invite.h2') }}</h2>
        <p class="lede">{{ __('landing.invite.lede') }}</p>
        @if ($showcaseUrl)
          <p class="alt"><a href="{{ $showcaseUrl }}" target="_blank" rel="noopener">{{ __('landing.invite.alt_showcase') }}</a></p>
        @else
          <p class="alt"><a href="#anatomy">{{ __('landing.invite.alt_anatomy') }}</a></p>
        @endif
      </div>

      @if (session('invite_sent'))
        <div class="form form-done" role="status">
          <svg width="26" height="26" aria-hidden="true"><use href="#i-check"/></svg>
          <p>{{ session('invite_sent') }}</p>
        </div>
      @else
      {{--
        ★ **تبويبان: «تواصل معنا» و«أرسل محاضرة»** — T-158، طلبُ مالك المنتج.

        كان النموذجُ طلبَ تجربةٍ وحده، فمن جاء بسؤالٍ عابرٍ سُئل عن صفته ورابط
        قناته قبل أن يكتب سطره. **والتواصلُ هو الافتراضيّ** لأنّه أخفُّ الاثنين،
        وزرُّ «تواصل معنا» في الترويسة والصدر يصل إليه مباشرة.

        **وكلُّ تبويبٍ نموذجٌ مستقلّ** بحقوله وزرّه، فلا يُرسَل حقلٌ مخفيّ من
        الآخر. والخطأُ يُعيد الزائرَ إلى تبويبه (`old('kind')`) بما كتب، وأخطاؤه
        تُعرض فيه وحده.

        **وبلا JavaScript يظهر النموذجان معاً** بعنوانيهما: الإخفاءُ تحت `.js`
        وحدها، فلا يضيع نموذجٌ على من عطّل السكربت.
      --}}
      @php($activeKind = old('kind') === 'lecture' ? 'lecture' : 'contact')
      <div class="form">
        <div class="tabs" role="tablist" aria-label="{{ __('landing.invite.tabs_aria') }}">
          @foreach (['contact', 'lecture'] as $kind)
            <button type="button" role="tab" id="tab-{{ $kind }}" aria-controls="panel-{{ $kind }}"
                    aria-selected="{{ $activeKind === $kind ? 'true' : 'false' }}"
                    tabindex="{{ $activeKind === $kind ? '0' : '-1' }}">{{ __('landing.invite.tab_'.$kind) }}</button>
          @endforeach
        </div>

        {{-- ١ · تواصل معنا --}}
        @php($on = $activeKind === 'contact')
        <form class="panel" id="panel-contact" role="tabpanel" aria-labelledby="tab-contact"
              method="POST" action="{{ route('invite.store') }}" @unless ($on) data-off @endunless>
          @csrf
          {{-- لغةُ الصفحة تُرافق الطلب، فيرجع الخطأ بلغتها لا بالعربية دائماً (T-131). --}}
          <input type="hidden" name="locale" value="{{ $locale->value }}">
          <input type="hidden" name="kind" value="contact">
          <h3 class="panel-t">{{ __('landing.invite.tab_contact') }}</h3>
          <p class="hint">{{ __('landing.invite.contact_hint') }}</p>
          <div class="field">
            <label for="c-name">{{ __('landing.invite.name') }}</label>
            <input id="c-name" name="name" type="text" autocomplete="name" value="{{ old('name') }}"
                   required @if ($on && $errors->has('name')) aria-invalid="true" aria-describedby="ce-name" @endif>
            @if ($on) @error('name')<p class="err" id="ce-name">{{ $message }}</p>@enderror @endif
          </div>
          <div class="field">
            <label for="c-contact">{{ __('landing.invite.contact') }}</label>
            <input id="c-contact" name="contact" type="text" inputmode="email" value="{{ old('contact') }}"
                   required @if ($on && $errors->has('contact')) aria-invalid="true" aria-describedby="ce-contact" @endif>
            @if ($on) @error('contact')<p class="err" id="ce-contact">{{ $message }}</p>@enderror @endif
          </div>
          {{-- الرسالةُ هنا هي الطلبُ نفسُه، فهي إلزاميّة (T-158). --}}
          <div class="field">
            <label for="c-message">{{ __('landing.invite.message') }}</label>
            <textarea id="c-message" name="message" rows="4" placeholder="{{ __('landing.invite.message_placeholder') }}"
                      required @if ($on && $errors->has('message')) aria-invalid="true" aria-describedby="ce-message" @endif>{{ $on ? old('message') : '' }}</textarea>
            @if ($on) @error('message')<p class="err" id="ce-message">{{ $message }}</p>@enderror @endif
          </div>
          <button class="btn btn-light" type="submit">{{ __('landing.invite.submit_contact') }}</button>
        </form>

        {{-- ٢ · أرسل محاضرة --}}
        @php($on = $activeKind === 'lecture')
        <form class="panel" id="panel-lecture" role="tabpanel" aria-labelledby="tab-lecture"
              method="POST" action="{{ route('invite.store') }}" @unless ($on) data-off @endunless>
          @csrf
          <input type="hidden" name="locale" value="{{ $locale->value }}">
          <input type="hidden" name="kind" value="lecture">
          <h3 class="panel-t">{{ __('landing.invite.tab_lecture') }}</h3>
          <p class="hint">{{ __('landing.invite.lecture_hint') }}</p>
          <div class="field">
            <label for="l-name">{{ __('landing.invite.name') }}</label>
            <input id="l-name" name="name" type="text" autocomplete="name" value="{{ old('name') }}"
                   required @if ($on && $errors->has('name')) aria-invalid="true" aria-describedby="le-name" @endif>
            @if ($on) @error('name')<p class="err" id="le-name">{{ $message }}</p>@enderror @endif
          </div>
          <div class="field">
            <label for="l-role">{{ __('landing.invite.role') }}</label>
            <select id="l-role" name="role" required @if ($on && $errors->has('role')) aria-invalid="true" aria-describedby="le-role" @endif>
              <option value="">{{ __('landing.invite.role_placeholder') }}</option>
              @foreach (__('landing.invite.roles') as $role)
                <option @selected(old('role') === $role)>{{ $role }}</option>
              @endforeach
            </select>
            @if ($on) @error('role')<p class="err" id="le-role">{{ $message }}</p>@enderror @endif
          </div>
          <div class="field">
            <label for="l-contact">{{ __('landing.invite.contact') }}</label>
            <input id="l-contact" name="contact" type="text" inputmode="email" value="{{ old('contact') }}"
                   required @if ($on && $errors->has('contact')) aria-invalid="true" aria-describedby="le-contact" @endif>
            @if ($on) @error('contact')<p class="err" id="le-contact">{{ $message }}</p>@enderror @endif
          </div>
          <div class="field">
            <label for="l-link">{{ __('landing.invite.link') }}</label>
            <input id="l-link" name="link" type="url" dir="ltr" class="ltn" placeholder="https://" value="{{ old('link') }}"
                   required @if ($on && $errors->has('link')) aria-invalid="true" aria-describedby="le-link" @endif>
            @if ($on) @error('link')<p class="err" id="le-link">{{ $message }}</p>@enderror @endif
          </div>
          {{--
            موضعُ الكلام — T-142. **وآخرُ الحقول لا أوّلَها**: ما فوقه يُملأ في
            ثوانٍ، وحقلٌ مفتوحٌ في الصدر يُقرأ كُلفةً فيُهجَر النموذج. واختياريٌّ
            بنصّ الوسم لا بغياب `required` وحده، فيُقرأ الاختيارُ ولا يُستنتج.
          --}}
          <div class="field">
            <label for="l-message">{{ __('landing.invite.lecture_message') }} <span class="opt">{{ __('landing.invite.optional') }}</span></label>
            <textarea id="l-message" name="message" rows="3" placeholder="{{ __('landing.invite.lecture_message_placeholder') }}"
                      @if ($on && $errors->has('message')) aria-invalid="true" aria-describedby="le-message" @endif>{{ $on ? old('message') : '' }}</textarea>
            @if ($on) @error('message')<p class="err" id="le-message">{{ $message }}</p>@enderror @endif
          </div>
          <button class="btn btn-light" type="submit">{{ __('landing.invite.submit_lecture') }}</button>
        </form>

        <p class="tiny">{{ __('landing.invite.tiny') }}</p>
      </div>
      @endif
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="cols">
      <div>
        <span class="logo">
          <svg width="26" height="26" aria-hidden="true"><use href="#mk"/></svg>
          <b>{{ __('landing.meta.site_name') }}</b>
        </span>
        <p class="slogan">{{ __('landing.footer.slogan') }}</p>
      </div>
      <div>
        <h3>{{ __('landing.footer.page') }}</h3>
        <ul>
          <li><a href="#anatomy">{{ __('landing.nav.anatomy') }}</a></li>
          <li><a href="#how">{{ __('landing.nav.how') }}</a></li>
          <li><a href="#verify">{{ __('landing.nav.verify') }}</a></li>
          <li><a href="#audience">{{ __('landing.nav.audience') }}</a></li>
          <li><a href="#faq">{{ __('landing.nav.faq') }}</a></li>
        </ul>
      </div>
      <div>
        <h3>{{ __('landing.footer.links') }}</h3>
        <ul>
          <li><a href="#invite">{{ __('landing.nav.contact') }}</a></li>
          <li><a href="{{ route('login') }}">{{ __('landing.footer.login_tenants') }}</a></li>
          <li><a href="{{ route('complaint.create') }}">{{ __('landing.footer.complaint') }}</a></li>
          <li><a href="#anatomy">{{ __('landing.footer.anatomy_link') }}</a></li>
        </ul>
      </div>
    </div>
    <div class="bar">
      <p class="rights">{{ __('landing.footer.rights') }}</p>
      <span class="lat"><a href="{{ $urlFor($locale) }}">khulasat.io</a></span>
    </div>
  </div>
</footer>
<script>
(function(){
  var d=document, topbar=d.getElementById('topbar'), drawer=d.getElementById('drawer'), burger=d.getElementById('burger');
  burger.addEventListener('click',function(){
    var open=drawer.classList.toggle('open');
    // الدرجُ يبدأ تحت الترويسة مهما كان ارتفاعُها، والخلفُ لا يُمرَّر خلفه.
    drawer.style.top=open?topbar.offsetHeight+'px':'';
    d.body.classList.toggle('nav-open',open);
    burger.setAttribute('aria-expanded',open?'true':'false');
    burger.innerHTML='<svg width="22" height="22" aria-hidden="true"><use href="#i-'+(open?'x':'menu')+'"/></svg>';
  });
  drawer.addEventListener('click',function(e){ if(e.target.tagName==='A') burger.click(); });
  d.addEventListener('keydown',function(e){ if(e.key==='Escape'&&drawer.classList.contains('open')) burger.click(); });

  var lang=d.querySelector('.topbar .langsw');
  if(lang){
    d.addEventListener('click',function(e){ if(lang.open && !lang.contains(e.target)) lang.open=false; });
    d.addEventListener('keydown',function(e){
      if(e.key==='Escape'&&lang.open){ lang.open=false; lang.querySelector('summary').focus(); }
    });
  }

  addEventListener('scroll',function(){ topbar.classList.toggle('scrolled',scrollY>24); },{passive:true});

  if(!matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){
      es.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
    },{rootMargin:'0px 0px -8% 0px'});
    d.querySelectorAll('.rv').forEach(function(el,i){
      el.style.transitionDelay=(Math.min(i%4,3)*60)+'ms'; io.observe(el);
    });
  } else { d.querySelectorAll('.rv').forEach(function(el){ el.classList.add('in'); }); }

  var links=[].slice.call(d.querySelectorAll('.navlinks a'));
  var spy=new IntersectionObserver(function(es){
    es.forEach(function(e){
      if(!e.isIntersecting) return;
      links.forEach(function(a){ a.setAttribute('aria-current', a.getAttribute('href')==='#'+e.target.id?'true':'false'); });
    });
  },{rootMargin:'-45% 0px -50% 0px'});
  ['anatomy','how','verify','audience','faq'].forEach(function(id){ var s=d.getElementById(id); if(s) spy.observe(s); });

  // تبويبا نموذج التواصل — T-158. الأسهمُ تتبع اتجاه الصفحة: في RTL يتقدّم اليسار.
  var tabs=[].slice.call(d.querySelectorAll('#invite [role="tab"]'));
  function pick(tab,focus){
    tabs.forEach(function(t){
      var on=t===tab, panel=d.getElementById(t.getAttribute('aria-controls'));
      t.setAttribute('aria-selected',on?'true':'false'); t.tabIndex=on?0:-1;
      if(on) panel.removeAttribute('data-off'); else panel.setAttribute('data-off','');
    });
    if(focus) tab.focus();
  }
  tabs.forEach(function(t,i){
    t.addEventListener('click',function(){ pick(t); });
    t.addEventListener('keydown',function(e){
      var n=tabs.length, rtl=d.documentElement.dir==='rtl', j=null;
      if(e.key==='ArrowRight') j=rtl?i-1:i+1;
      else if(e.key==='ArrowLeft') j=rtl?i+1:i-1;
      else if(e.key==='Home') j=0;
      else if(e.key==='End') j=n-1;
      if(j===null) return;
      e.preventDefault(); pick(tabs[(j+n)%n],true);
    });
  });
})();
</script>
</body>
</html>
