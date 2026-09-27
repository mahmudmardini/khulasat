{{--
  سرلوح البحثي التوثيقي — T-49.

  **رصينٌ بلا زخرفة**، على هيئة صدر ورقةٍ علمية: عنوانٌ وعنوانٌ فرعيّ ثمّ
  خطٌّ فاصل. والآيةُ المفتاح تُعرض شاهداً موثَّقاً بتخريجه لا لوحةً مذهّبة —
  فالتوثيق هو ما يميّز هذا القالب.
--}}
<header class="unwan">
  <div class="inner">
    @include('summary.partials.brand-mark')
    <h1>{{ $title }}</h1>
    @if($subtitle)
      <p class="sub">{{ $subtitle }}</p>
    @endif
  </div>

  {{-- بيانات المجلس ذيلاً للسرلوح، خارج `.inner` ليبلغ حافّتيه — T-106 وT-108. --}}
  @include('summary.partials.attrib')
</header>

@if($heroAyah)
  <div class="citation-lead">
    {{-- اللفظُ عربيٌّ باتّجاهه في كلّ لغة (T-66)، وموضعُه بلسان الصفحة (T-81). --}}
    <p class="ayah-hero" lang="ar" dir="rtl">{{ $heroAyah->text }}</p>
    @if($heroAyah->citation($pageLocale) !== '')
      <span class="src">{{ $heroAyah->citation($pageLocale) }}</span>
    @endif
  </div>
@endif
