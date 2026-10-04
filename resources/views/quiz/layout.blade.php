{{--
  إطارُ صفحات الاختبار — T-195.

  **صفحاتٌ مستقلّة لا تمسّ قالب الملخّص**: أنماطُها في `_style` هنا وحده،
  وألوانُها متغيّراتُ لوحة الجهة نفسُها (`Palette::vars`)، وخطوطُها خطوطُ
  الملخّص. فالمشاركُ ينتقل من الملخّص إلى الاختبار فلا يحسّ أنّه غادر الكتاب.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>{{ $pageTitle ?? trans('quiz.public.title', [], 'ar') }} — {{ $title }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri+Quran&family=Amiri:wght@400;700&family=Aref+Ruqaa:wght@400;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
@foreach ($palette->vars as $name => $value)
  --{{ $name }}:{{ $value }};
@endforeach
}
</style>
@include('quiz.partials._style')
</head>
<body class="kq">

<header class="kq-head">
  <div class="kq-head-in">
    <div class="kq-venue">
      @if ($brand->logoDataUri)
        <span class="kq-logo {{ $brand->logoTransparent ? 'is-bare' : '' }}"><img src="{{ $brand->logoDataUri }}" alt="{{ $brand->venueShort ?? $brand->venueFull }}"></span>
      @endif
      <span class="kq-venue-name">{{ $brand->venueShort ?? $brand->venueFull }}</span>
    </div>
    <svg class="kq-star" viewBox="0 0 40 40" aria-hidden="true"><path d="M20 3l4.98 4.98h7.04v7.04L37 20l-4.98 4.98v7.04h-7.04L20 37l-4.98-4.98H7.98v-7.04L3 20l4.98-4.98V7.98h7.04z"/><circle cx="20" cy="20" r="4.2"/></svg>
    <p class="kq-kicker">{{ trans('quiz.public.kicker', [], 'ar') }}</p>
    <h1 class="kq-title">{{ $title }}</h1>
    @if (! empty($speaker))
      <p class="kq-speaker">{{ $speaker }}</p>
    @endif
  </div>
</header>

<main class="kq-main">
  @yield('content')
</main>

<footer class="kq-foot">
  @if (! empty($summaryUrl))
    <a href="{{ $summaryUrl }}">{{ trans('quiz.public.read_summary', [], 'ar') }}</a>
  @endif
</footer>

@stack('scripts')
</body>
</html>
