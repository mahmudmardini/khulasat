{{-- الترويسة والتاج والفاصل والآية المفتاح — بنية القالب كما هي. --}}
<header class="unwan">
  {{--
    التاج — أُعيد رسمه في T-98 بطلب مالك المنتج، على الهوية البصرية الثانية.

    نجمةٌ ثمانية من مربّعين متداخلين هندسيّةُ الأبعاد (لا مرسومةٌ باليد)،
    تحيط بها دائرةٌ منقوطة، وفي قلبها نجمةٌ مصمتة. وعلى الجانبين خيطٌ يخفت
    نحو الطرفين عليه نجومٌ تصغر. **وألوانُه من اللوحة** (`_identity`)،
    والتدرّجُ ذهبٌ يلمع نحو الورق — وكان ذهبَ الزمرّدية نصّاً في كلّ لوحة.
  --}}
  <svg class="crest" viewBox="0 0 800 140" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
      <linearGradient id="crest-gilt" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" class="gilt-hi"/>
        <stop offset=".5" class="gilt-mid"/>
        <stop offset="1" class="gilt-lo"/>
      </linearGradient>
      <linearGradient id="crest-fade-a" gradientUnits="userSpaceOnUse" x1="352" y1="0" x2="24" y2="0">
        <stop offset="0" class="gilt-mid"/>
        <stop offset="1" class="gilt-mid" stop-opacity="0"/>
      </linearGradient>
      <linearGradient id="crest-fade-b" gradientUnits="userSpaceOnUse" x1="448" y1="0" x2="776" y2="0">
        <stop offset="0" class="gilt-mid"/>
        <stop offset="1" class="gilt-mid" stop-opacity="0"/>
      </linearGradient>
    </defs>
    <g fill="none" stroke-linejoin="round">
      <path class="draw line" d="M0 128 H800 M0 122 H800" stroke-width="1.2"/>

      <path d="M352 64 H24" stroke="url(#crest-fade-a)"/>
      <path d="M448 64 H776" stroke="url(#crest-fade-b)"/>
      <g class="dot" stroke="none">
        <circle cx="269" cy="64" r="1.6" opacity=".8"/><circle cx="207" cy="64" r="1.5" opacity=".6"/><circle cx="145" cy="64" r="1.4" opacity=".4"/>
        <circle cx="531" cy="64" r="1.6" opacity=".8"/><circle cx="593" cy="64" r="1.5" opacity=".6"/><circle cx="655" cy="64" r="1.4" opacity=".4"/>
      </g>
      <g fill="url(#crest-gilt)" stroke="none">
        <path opacity=".95" d="M300 55L302.64 57.64L306.36 57.64L306.36 61.36L309 64L306.36 66.64L306.36 70.36L302.64 70.36L300 73L297.36 70.36L293.64 70.36L293.64 66.64L291 64L293.64 61.36L293.64 57.64L297.36 57.64Z"/>
        <path opacity=".75" d="M238 56.5L240.2 58.7L243.3 58.7L243.3 61.8L245.5 64L243.3 66.2L243.3 69.3L240.2 69.3L238 71.5L235.8 69.3L232.7 69.3L232.7 66.2L230.5 64L232.7 61.8L232.7 58.7L235.8 58.7Z"/>
        <path opacity=".55" d="M176 58L177.76 59.76L180.24 59.76L180.24 62.24L182 64L180.24 65.76L180.24 68.24L177.76 68.24L176 70L174.24 68.24L171.76 68.24L171.76 65.76L170 64L171.76 62.24L171.76 59.76L174.24 59.76Z"/>
        <path opacity=".35" d="M114 59.5L115.32 60.82L117.18 60.82L117.18 62.68L118.5 64L117.18 65.32L117.18 67.18L115.32 67.18L114 68.5L112.68 67.18L110.82 67.18L110.82 65.32L109.5 64L110.82 62.68L110.82 60.82L112.68 60.82Z"/>
        <path opacity=".95" d="M500 55L502.64 57.64L506.36 57.64L506.36 61.36L509 64L506.36 66.64L506.36 70.36L502.64 70.36L500 73L497.36 70.36L493.64 70.36L493.64 66.64L491 64L493.64 61.36L493.64 57.64L497.36 57.64Z"/>
        <path opacity=".75" d="M562 56.5L564.2 58.7L567.3 58.7L567.3 61.8L569.5 64L567.3 66.2L567.3 69.3L564.2 69.3L562 71.5L559.8 69.3L556.7 69.3L556.7 66.2L554.5 64L556.7 61.8L556.7 58.7L559.8 58.7Z"/>
        <path opacity=".55" d="M624 58L625.76 59.76L628.24 59.76L628.24 62.24L630 64L628.24 65.76L628.24 68.24L625.76 68.24L624 70L622.24 68.24L619.76 68.24L619.76 65.76L618 64L619.76 62.24L619.76 59.76L622.24 59.76Z"/>
        <path opacity=".35" d="M686 59.5L687.32 60.82L689.18 60.82L689.18 62.68L690.5 64L689.18 65.32L689.18 67.18L687.32 67.18L686 68.5L684.68 67.18L682.82 67.18L682.82 65.32L681.5 64L682.82 62.68L682.82 60.82L684.68 60.82Z"/>
      </g>

      <circle class="line" cx="400" cy="64" r="57" stroke-width="1.3" stroke-linecap="round" stroke-dasharray="0.01 5.4" opacity=".7"/>
      <path class="draw" d="M400 18L413.47 31.47L432.53 31.47L432.53 50.53L446 64L432.53 77.47L432.53 96.53L413.47 96.53L400 110L386.53 96.53L367.47 96.53L367.47 77.47L354 64L367.47 50.53L367.47 31.47L386.53 31.47Z" fill="url(#crest-gilt)" fill-opacity=".16" stroke="url(#crest-gilt)" stroke-width="1.6"/>
      <path class="line" d="M400 27L410.84 37.84L426.16 37.84L426.16 53.16L437 64L426.16 74.84L426.16 90.16L410.84 90.16L400 101L389.16 90.16L373.84 90.16L373.84 74.84L363 64L373.84 53.16L373.84 37.84L389.16 37.84Z" stroke-width=".9" opacity=".75"/>
      <circle class="line" cx="400" cy="64" r="20"/>
      <path d="M400 51L403.81 54.81L409.19 54.81L409.19 60.19L413 64L409.19 67.81L409.19 73.19L403.81 73.19L400 77L396.19 73.19L390.81 73.19L390.81 67.81L387 64L390.81 60.19L390.81 54.81L396.19 54.81Z" fill="url(#crest-gilt)"/>
      <circle class="core" cx="400" cy="64" r="3.4"/>
    </g>
  </svg>

  <div class="inner">
    @include('summary.partials.brand-mark')

    <h1>{{ $title }}</h1>
    @if($subtitle)
      <p class="sub">{{ $subtitle }}</p>
    @endif

    {{-- لونُه من اللوحة (`currentColor`) — T-98. --}}
    <svg class="divider" viewBox="0 0 150 14" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <g fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M0 7 H55 M95 7 H150"/>
        <path d="M75 1 L81 7 L75 13 L69 7 Z"/>
        <circle cx="61" cy="7" r="2.2"/><circle cx="89" cy="7" r="2.2"/>
      </g>
    </svg>

    @if($heroAyah)
      {{-- الآيةُ عربيةٌ في كلّ اللغات، فاتّجاهُها عليها لا على الصفحة — T-66. --}}
      <p class="ayah-hero" lang="ar" dir="rtl">
        {{-- علامةُ الآية تُلبَس صنفَها `.ayah-no` — T-56، والنصّ مهرَّبٌ داخلها. --}}
        {!! \App\Support\Quran\AyahText::html($heroAyah->text) !!}
        {{-- وموضعُها بلسان الصفحة، ووسمُه عليه — T-81 وT-87. --}}
        @if($heroAyah->citation($pageLocale) !== '')
          <span class="src" lang="{{ $pageLocale->value }}" dir="{{ $pageLocale->direction() }}">{{ $heroAyah->citation($pageLocale) }}</span>
        @endif
      </p>
    @endif
  </div>

  {{-- بيانات المجلس ذيلاً للسرلوح لا لوحاً تحته — T-106.
       **وخارج `.inner` لا داخله** — T-108: الحشوةُ الجانبية على `.inner`،
       فما دام فيه لم يبلغ حافّتَي البطاقة. --}}
  @include('summary.partials.attrib')
</header>
