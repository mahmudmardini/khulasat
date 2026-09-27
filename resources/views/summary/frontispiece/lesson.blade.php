{{--
  سرلوح الدرس التعليمي — T-49.

  **لا سرلوحَ مذهّباً ولا نجمةً ثمانية.** ذاك زخرفُ المجالس، ودرسُ الدورة
  يُفتتح بما يُعين الدارس: عنوانٌ صريح، ثمّ صندوقُ أهدافٍ يقول له ما سيخرج
  به. والآيةُ المفتاح تُعرض بعده لا قبله — فهي مادّةُ الدرس لا غلافُه.
--}}
<header class="unwan">
  <div class="inner">
    @include('summary.partials.brand-mark')
    {{-- وسما السرلوح بلسان الصفحة — T-87. --}}
    <span class="kicker">{{ $strings['lesson_kicker'] }}</span>
    <h1>{{ $title }}</h1>
    @if($subtitle)
      <p class="sub">{{ $subtitle }}</p>
    @endif
  </div>

  {{-- بيانات المجلس ذيلاً للسرلوح، خارج `.inner` ليبلغ حافّتيه — T-106 وT-108. --}}
  @include('summary.partials.attrib')
</header>

@if($heroAyah)
  {{--
    الآية بلفظ مصدرها لا بلفظ النموذج — `PageRenderer::heroAyah()` يردّ
    المحسوم وحده، فما لم يبلغ التحقّق لا يُعرض.
  --}}
  <div class="objective">
    <span class="objective-label">{{ $strings['lesson_ayah'] }}</span>
    {{-- اللفظُ عربيٌّ باتّجاهه في كلّ لغة (T-66)، وموضعُه بلسان الصفحة (T-81). --}}
    <p class="ayah-hero" lang="ar" dir="rtl">{{ $heroAyah->text }}</p>
    @if($heroAyah->citation($pageLocale) !== '')
      <span class="src">{{ $heroAyah->citation($pageLocale) }}</span>
    @endif
  </div>
@endif
