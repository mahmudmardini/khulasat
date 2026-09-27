@php
    use App\Enums\Locale;

    $locale = Locale::parse(app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- يقرؤه الفحص المسبق: نداء fetch خارج Inertia يحتاج الرمز بنفسه — T-16 --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name') }}</title>

    {{--
      IBM Plex Sans Arabic للواجهة، وAmiri للنصوص الشرعية — انظر SCREENS.md §الخطوط.

      ★ **والكيريلّية ليست في العربي منه** (T-133): «بلكس عربي» يحمل
      اللاتينية فيقوم بالإنجليزية والتركية، ولا يحمل الكيريلّية — فتُضاف
      «بلكس» اللاتيني للروسية وحدها، ولا يُحمَّل على غيرها.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    @if ($locale === Locale::Ru)
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif
    {{--
      Reem Kufi للشعار وحده — الهوية البصرية الثانية §٠٨، T-100. و`text=` يقصر
      الملفّ على أحرف «خُلاصات» وضمّتها، كما في رأس الصفحة المنشورة (T-98).
    --}}
    <link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@600&text=%D8%AE%D9%8F%D9%84%D8%A7%D8%B5%D8%A7%D8%AA&display=swap" rel="stylesheet">

    {{--
      الرمز على مربّعه المصمت — الهوية §٠٤ و§١١. الـSVG لمن يقرؤه، والـICO
      ذو ٣٢ بكسل لمن لا يقرؤه، وصورةُ ١٨٠ لشاشة الجوال الرئيسية. والثلاثة
      رُسمت من `favicon.svg` نفسِه، فلا تفترق.
    --}}
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
