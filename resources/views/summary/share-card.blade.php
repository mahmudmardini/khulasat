{{--
  بطاقةُ مشاركة الخلاصة المنشورة — ١٢٠٠×٦٣٠، T-144.

  **لا سكربتَ فيها**: `Content-Security-Policy` يمنعه، ونصُّ المستخدم
  مهرَّبٌ بـ`{{ }}`. فالمتصفّحُ على الخادم لا ينفّذ إلّا ما رسمناه.

  **بألوان لوحة الجهة** — البطاقةُ تُرى قبل الصفحة، فتُعرف بها الجهة كما
  تُعرف صفحتُها. وشعارُ خُلاصات صغيرٌ في الذيل: الجهةُ صاحبةُ الخلاصة.
--}}
<!DOCTYPE html>
<html lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Reem+Kufi:wght@600&display=block" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{margin:0}
body{width:1200px; height:630px; overflow:hidden; position:relative;
  background:linear-gradient(160deg, {{ $palette['emerald'] }}, {{ $palette['emerald-deep'] }});
  color:#F6F3EA; font-family:"IBM Plex Sans Arabic","Noto Sans Arabic",system-ui,sans-serif}
.rule{position:absolute; inset-inline:0; height:2px; background:linear-gradient(90deg,transparent,{{ $palette['gold-light'] }},transparent)}
.rule.t{top:48px} .rule.b{bottom:118px}
.box{position:absolute; top:78px; bottom:150px; inset-inline:90px; display:flex; flex-direction:column;
  align-items:center; justify-content:center; gap:18px; text-align:center}
.logo{max-height:84px; max-width:220px; object-fit:contain}
.logo.plate{background:#F6F3EA; padding:10px 16px; border-radius:10px}
.venue{font-size:26px; color:{{ $palette['gold-light'] }}; margin:0}
.title{font-family:Amiri,"Noto Naskh Arabic",serif; font-weight:700; font-size:{{ $titleSize }}px; line-height:1.35; margin:0;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden}
html[dir="ltr"] .title{font-family:"IBM Plex Sans",system-ui,sans-serif; font-weight:600; letter-spacing:-.015em; line-height:1.2}
.sub{font-size:28px; color:#C9D0DE; margin:0; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden}
.speaker{font-size:27px; font-weight:500; color:#EDE6D6; margin:6px 0 0}
.foot{position:absolute; bottom:0; inset-inline:0; height:118px; display:flex; align-items:center;
  justify-content:space-between; padding:0 90px; background:rgba(0,0,0,.18)}
.mk{display:flex; align-items:center; gap:14px; color:#F2F1EC}
.mk b{font-family:"Reem Kufi",serif; font-weight:600; font-size:38px; line-height:1}
html[dir="ltr"] .mk b{font-family:"IBM Plex Sans",system-ui,sans-serif; font-size:30px; letter-spacing:-.01em}
.note{font-size:21px; color:#AEB8CC}
.domain{font-family:"IBM Plex Sans",system-ui,sans-serif; direction:ltr; letter-spacing:.16em; font-size:18px; color:#AEB8CC}
</style>
</head>
<body>
<span class="rule t"></span>
<div class="box">
  @if ($logoDataUri)
  <img class="logo{{ $logoTransparent ? ' plate' : '' }}" src="{{ $logoDataUri }}" alt="">
  @endif
  @if ($venue)
  <p class="venue" dir="auto">{{ $venue }}</p>
  @endif
  <h1 class="title" dir="auto">{{ $title }}</h1>
  @if ($subtitle)
  <p class="sub" dir="auto">{{ $subtitle }}</p>
  @endif
  @if ($speaker)
  <p class="speaker" dir="auto">{{ $speaker }}</p>
  @endif
</div>
<span class="rule b"></span>
<div class="foot">
  <div class="mk">
    <svg width="40" height="40" viewBox="0 0 64 64" aria-hidden="true">
      <path d="M23 14H14V50H23" fill="none" stroke="currentColor" stroke-width="6"/>
      <path d="M41 14H50V50H41" fill="none" stroke="currentColor" stroke-width="6"/>
      <circle cx="32" cy="32" r="6" fill="{{ $palette['gold-light'] }}"/>
    </svg>
    <b>{{ $platformName }}</b>
    <span class="note">{{ $note }}</span>
  </div>
  <span class="domain">{{ $domain }}</span>
</div>
</body>
</html>
