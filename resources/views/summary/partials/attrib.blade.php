{{--
  بطاقة بيانات المجلس تحت السرلوح. و**نمط النسبة يحكم ما يظهر فيها** —
  `venue_mode` من المحاضرة: جهةٌ ومكان، أو الشيخ وحده، أو الناشر وحده.
--}}
{{-- ★ **وكانت سطراً مسروداً** تفصله نقاطٌ صغيرة — T-105، بلاغُ مالك المنتج
     بلقطة: «ألقاها فلان · في جامع كذا · الجمعة ٢٠٢٦/٠٩/١١» يُقرأ جملةً
     واحدة، فلا يُعرف أين ينتهي اسمٌ ويبدأ مكان. فصارت حقائقَ لكلٍّ وسمُه
     فوقه وقيمتُه تحته، كبطاقة المجلس في البصمة. --}}
{{-- ★ **وتتبع لسانَ الصفحة واتّجاهَها** — T-87. فالوسومُ بلسانها، والموسومُ
     عربياً ما يبقى عربياً في كلّ لسان: اسما الشيخ والجهة، واليومُ والتاريخ
     الهجريّ. --}}
@php
    /*
     * القيمةُ البارزة في حقل التاريخ: اليومُ والتاريخ الهجريّ. والتحتيّةُ:
     * الميلاديُّ ووقتُه. **وإن غاب الهجريُّ واليومُ صعد الميلاديُّ مكانَه**،
     * فلا يبقى وسمٌ فوق فراغ.
     */
    $dateMain = trim($weekday.' '.(string) $dateHijri);
    // **وفاصلٌ بين التاريخ ووقته**: `time_note` يُحفظ بلا فاصلٍ في أوّله
    // («بعد صلاة الجمعة»)، فوصلُه بالتاريخ رأساً يلصق الحرفَ بالرقم.
    $dateSub = implode('، ', array_filter([trim((string) $dateGregorian), trim($timeNote)]));

    if ($dateMain === '') {
        $dateMain = $dateSub;
        $dateSub = '';
    }

    // والوسمُ العربيُّ على ما فيه عربيّة وحدها — حارسُ T-87 يعدّ ما خرج عنه.
    $dateMainArabic = preg_match('/\p{Arabic}/u', $dateMain) === 1;
    $dateSubArabic = preg_match('/\p{Arabic}/u', $dateSub) === 1;

    $showsVenue = $venueMode->showsVenueBlock() && $brand->venueShort;
@endphp

@if($sheikh || $showsVenue || $dateMain !== '')
  <div class="attrib">
    @if($sheikh)
      <div class="fact">
        <span class="k">{{ $strings['majlis_speaker'] }}</span>
        <span class="v" lang="ar" dir="rtl">{{ $sheikh }}</span>
      </div>
    @endif

    @if($showsVenue)
      <div class="fact">
        <span class="k">{{ $strings['majlis_venue'] }}</span>
        <span class="v" lang="ar" dir="rtl">{{ $brand->venueShort }}</span>
        @if($brand->venueLatin)
          <span class="sub">{{ $brand->venueLatin }}</span>
        @endif
      </div>
    @endif

    @if($dateMain !== '')
      <div class="fact">
        <span class="k">{{ $strings['majlis_date'] }}</span>
        <span class="v"@if($dateMainArabic) lang="ar" dir="rtl"@endif>{{ $dateMain }}</span>
        @if($dateSub !== '')
          <span class="sub"@if($dateSubArabic) lang="ar" dir="rtl"@endif>{{ $dateSub }}</span>
        @endif
      </div>
    @endif
  </div>
@endif
