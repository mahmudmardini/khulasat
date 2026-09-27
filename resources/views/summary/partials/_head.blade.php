{{--
  رأس وثيقة الملخّص — مشترَكٌ بين القوالب كلِّها، T-49.

  **استُخرج ولم يُغيَّر**: ما كان في `layout.blade.php` حرفاً بحرف. وعلّةُ
  استخراجه أنّ قوالب T-49 تملك صفحاتِها، وخطوطُ الوثيقة ولوحةُ الجهة
  وأوراقُ الأنماط واحدةٌ فيها كلِّها — فتكرارُها ثلاثاً يعني انحرافَها
  ثلاثاً يوم يتغيّر خطٌّ أو تُضاف ورقة.
--}}
<!DOCTYPE html>
{{--
  ★ **اللغة والاتّجاه من المخرَج لا مثبَّتان** — T-38.

  وكان `lang="ar" dir="rtl"` نصّاً، فصفحةٌ إنجليزية تخرج بمحاذاةٍ من اليمين
  وعلامات ترقيمٍ في غير مواضعها. و`dir` سمةٌ في HTML لا قاعدةٌ في ورقة
  الأنماط، فقلبُها **لا يمسّ CSS القالب بحرف** (§2 القاعدة الأولى):
  الخصائصُ المنطقية (`padding-inline`, `border-inline-start`) تتبعها وحدَها.

  والافتراض عربيّ: مخرَجٌ رُسم قبل هذه المهمّة لا يحمل لغةً، فيبقى كما كان.
--}}
<html lang="{{ $locale ?? 'ar' }}" dir="{{ $direction ?? 'rtl' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@php($pageTitle = $metaTitle ?? trim($title.($subtitle ? ' — '.$subtitle : '')))
<title>{{ $pageTitle }}</title>
{{--
  وسومُ الوصف والمشاركة — المرحلة ٦، T-57.

  **وكان الرأسُ عنواناً وحده**، فكلُّ صفحةٍ تُشارَك على واتساب أو تويتر
  تخرج بلا بطاقةِ معاينة — في منتجٍ غايتُه صفحةٌ تُشارَك.

  وتغيب كلُّها إن غاب الوصف: وسمٌ فارغ أسوأ من وسمٍ محذوف، فبطاقةٌ
  بعنوانٍ ووصفٍ فارغ تُعرض ناقصةً لا تُطوى.
--}}
@if($metaDescription)
<meta name="description" content="{{ $metaDescription }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
@endif
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:type" content="article">
<meta property="og:locale" content="{{ $locale ?? 'ar' }}">
{{--
  اللغاتُ البديلة — T-134.

  **وهذه لمحرّك البحث والمتصفّح لا للقارئ**: الأوّل يفهرس كلَّ لغةٍ على
  حِدَة ويُقدّم لكلّ باحثٍ لغتَه، والثاني يعرض «ترجمةَ هذه الصفحة؟» أو
  يعرف أنّ ثَمّ بديلاً بلغة القارئ. والشريطُ المرئيُّ فوق الغلاف.

  ولا `x-default`: لا صفحةَ محايدةَ اللغة عندنا — كلُّ مخرَجٍ بلسانٍ
  بعينه، والجذرُ لغةُ النشر الأولى لا «أيّاً كانت» (T-51).
--}}
@foreach(($localeLinks ?? []) as $alternate)
<link rel="alternate" hreflang="{{ $alternate['locale']->value }}" href="{{ $alternate['url'] }}">
@endforeach
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:card" content="summary_large_image">
{{--
  صورةُ المشاركة واسمُ المنصّة — T-144.

  ★ **وكان `summary_large_image` معلَناً بلا صورة**، فتخرج الخلاصةُ على
  واتساب وتيليغرام وX بطاقةً نصّيةً عارية. والرابطُ على المنصّة ويُجيب
  بصورةٍ دائماً (`ShareCard`)، فلا يُشير الوسمُ إلى فراغ.
--}}
@if(!empty($shareImage))
<meta property="og:image" content="{{ $shareImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $pageTitle }}">
<meta name="twitter:image" content="{{ $shareImage }}">
@endif
@if(!empty($siteName))
<meta property="og:site_name" content="{{ $siteName }}">
@endif
{{-- رابطُ الصفحة نفسها متى عُرف — وفي أوّل نشرٍ لا يُعرف قبل الرفع، فيأخذ
     المُعايِنُ الرابطَ الذي فُتح منه، وهو هو. --}}
@if(!empty($pageUrl))
<meta property="og:url" content="{{ $pageUrl }}">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:ital,wght@0,400;0,700;1,400&family=Aref+Ruqaa:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600&display=swap" rel="stylesheet">
{{-- خطُّ الكلمة المرسومة «خُلاصات» في سطر الاعتماد وحدها — الهوية §٠٨:
     Reem Kufi للشعار لا لغيره. و`text=` يقصر الملفّ على أحرفها الخمسة
     **وضمّتها** (T-99): حرفٌ لم يُطلب لا يحمله الملفّ، فتُرسم بخطٍّ آخر. --}}
<link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@600&text=%D8%AE%D9%8F%D9%84%D8%A7%D8%B5%D8%A7%D8%AA&display=swap" rel="stylesheet">
<style>
{{-- ورقة أنماط القالب المختار — T-45. و`classic` يشير إلى `_style` نفسه،
     فمخرَجُه يبقى بايتاً ببايت كما كان. --}}
@include($styleView ?? 'summary.partials._style')
{{-- مفردات كتل T-46، بعد ورقة القالب — وملفُّ المهارة يبقى بلا مساس. --}}
@include($blocksView ?? 'summary.blocks.classic')
{{-- الهوية البصرية الثانية — T-98: الشعار والاعتماد وألوان التاج، للقوالب كلّها. --}}
@include('summary.partials._identity')
{{-- لوحة الجهة — ستٌّ ثوابت، ولا حقول HEX (T-14 البند 4). --}}
:root{
{!! $palette->css() !!}
}
</style>
</head>
<body>
{{-- شريطُ اللغات — T-134، فوق الغلاف وقبل كلّ شيء. وهو في `_head` لأنّ
     القوالب الأربعة تشترك فيه، كما اشتركت في الشاهدة عبر `_tail`. --}}
@include('summary.partials._locales')
