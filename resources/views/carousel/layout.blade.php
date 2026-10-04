{{--
  الكاروسيل — المواصفة §8-أ، والمهمّة T-19.

  **ملفٌّ واحد قائم بذاته** كالصفحة: الأنماط داخله، والشعار Base64، ولا
  اعتماد خارجيّ إلّا خطوط Google. فمن حفظه أو رفعه إلى أيّ مكانٍ رآه كما هو.

  ولوحةُ الجهة تُحقن في `:root` كما في قالب الملخّص — الستّ الثوابت نفسها،
  فلا تختلف هويّة الشرائح عن هويّة الصفحة.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} — شرائح</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@php
  /*
   * مواصفةُ التصميم — T-173. وغيابُها هو الافتراضيّ: القالب كما كان.
   * و`$capture` شريحةٌ واحدة في وثيقتها لتُلتقط صورةً بمقاسها.
   */
  $design ??= \App\Support\Render\CarouselDesign::default();
  $capture ??= false;
  $fit ??= false;
  $strip ??= false;
  $fonts = implode('', array_map(static fn (string $font): string => '&family='.$font, $design->extraFonts()));
@endphp
<link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@300;400;500;600{!! $fonts !!}&display=swap" rel="stylesheet">
<style>
:root{
{!! $palette->css() !!}
}
@include('carousel.partials._style')
@include('carousel.partials._design')
</style>
</head>
<body>

<div class="deck {{ $design->deckClasses() }}{{ $capture ? ' capture' : '' }}{{ $fit ? ' fit' : '' }}{{ $strip ? ' strip' : '' }}">
  @foreach($deck->slides as $slide)
    @include('carousel.partials.slide', ['slide' => $slide])
  @endforeach
</div>

@if($beacon)
{{--
  شاهدة العدّ — SCREENS.md §7، والمهمّة T-31.

  ★ **صورةٌ لا شيفرة، وهذا هو الحدّ.** فـCLAUDE.md §2 القاعدة الأولى تمنع
  تعديل CSS القالب وJavaScriptه، **والبنية وحدها هي التي تُحوَّل**. ونقطةُ
  عدٍّ تُكتب بـ`fetch` تُخالف القاعدة نصّاً؛ وصورةٌ بحجم بكسل بنيةٌ محضة.

  و`hidden` سمةٌ في HTML لا قاعدةٌ في ورقة الأنماط، فلا حرف CSS يُضاف
  ولا بكسل يتزحزح: من غيرها يفتح الوسمُ سطراً في آخر الصفحة يزيد فراغها.
  والصورة داخله تُطلب من المتصفّح على كلّ حال، وهي المقصودة.
--}}
<div hidden><img src="{{ $beacon }}" alt="" width="1" height="1"></div>
@endif

</body>
</html>
