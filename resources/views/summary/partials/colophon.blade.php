{{--
  بصمة المجلس — والصيغ الثلاث من `variants.html` يُختار بينها بـ `venue_mode`.

  ★ **وهي ذيلُ الصفحة** — T-98، فصارت `<footer>` لا `<section>`: ذيلُ
  الوثيقة لقارئ الشاشة، ولا تُعدّ قسماً من أقسام المتن. **وبعدها المواضعُ
  وحدها** — T-99، قرار مالك المنتج: الأزرارُ ثمّ البصمةُ ثمّ المواضع.
--}}
<footer class="colophon">
  @if($venueMode->showsVenueBlock())
    <div class="venue{{ $brand->logoDataUri ? ' has-logo' : '' }}">
      {{-- الشعارُ بجانب الاسم — T-90. والاسمُ يقرؤه، فالصورةُ هنا بلا نصٍّ بديل
           لا يُكرَّر به الاسم على قارئ الشاشة. --}}
      @if($brand->logoDataUri)
        <span class="venue-logo{{ $brand->logoTransparent ? ' no-plate' : '' }}"><img src="{{ $brand->logoDataUri }}" alt=""></span>
      @endif
      <div class="venue-names">
        {{-- اسمُ الجهة عربيٌّ في كلّ لغة، فاتّجاهُه عليه — T-87. --}}
        <p class="mosque-name" lang="ar" dir="rtl">{{ $brand->venueFull }}</p>
        @if($brand->venueLatin)
          <p class="mosque-latin">{{ $brand->venueLatin }}</p>
        @endif
      </div>
    </div>
    <div class="sep"></div>
  @endif

  @include('summary.partials.majlis')

  @if($lectureUrl || $brand->socialUrl)
    <div class="links">
      @if($lectureUrl)
        <a href="{{ $lectureUrl }}" target="_blank" rel="noopener" data-url="{{ $lectureUrlShort }}">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3z"/></svg>
          {{ $strings['watch_full'] }}
        </a>
      @endif
      @if($brand->socialUrl)
        <a href="{{ $brand->socialUrl }}" target="_blank" rel="noopener" data-url="{{ $socialShort }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>
          {{ $strings['venue_page'] }}
        </a>
      @endif
    </div>
  @endif

  {{--
    التنويه **لا يُحذف ولا يُختصر**: الملخّص ليس نصّاً حرفياً للمحاضرة،
    وقولُ ذلك صراحةً شرطُ أمانةٍ لا زينة.
  --}}
  {{--
    `dir="auto"` لا `rtl` ولا اتّجاهُ الصفحة — T-66 وT-69. فافتراضُ التنويه
    صار بلسان الصفحة، **وما كتبته الجهةُ بنفسها يبقى كما كتبته**: قد تكتبه
    بالعربية على صفحةٍ إنجليزية. و`auto` تقرأ أوّل حرفٍ ذي اتّجاه فتصيب
    الحالين، ولا تُلزمنا بتخمين لغةِ نصٍّ لا نملكه.
  --}}
  <p class="note" dir="auto">{{ $disclaimer }}</p>

  {{--
    رابط الاعتراض — T-24 البند الأوّل: «نموذج شكوى عام برابط في تذييل
    **كل صفحة منشورة**».

    وهو في القالب لا في اللوحة، لأنّ الصفحة تُخدَم من CDN ولا تمرّ على
    Laravel — فمن لم يُدرجه هنا لم يُدرجه أصلاً. ونصُّه هادئٌ بلا دعوة:
    من احتاجه وجده، ومن لم يحتجه لم يُشوَّش عليه.
  --}}
  {{--
    ولونُه في `_identity` — T-98. كان سمةَ `style=` هنا لأنّ ورقة القالب
    المنقولة حرفاً لا تعرف وسمَ `<a>` هذا، ولونُ المتصفّح الافتراضيّ أزرق
    لا يُقرأ فوق البصمة الداكنة. فصار للمضاف ورقتُه، والبنيةُ بلا سمات.
  --}}
  <p class="note complaint">
    <a href="{{ $complaintUrl }}" rel="nofollow">{{ $strings['complaint'] }}</a>
  </p>

  {{--
    سطرُ الاعتماد — الهوية البصرية الثانية §٠٥، T-98.

    **الجهةُ تتصدّر، والمنصّةُ سطرٌ واحد في الهامش.** واسمُها بخطّ شعارها
    ورابطُها، **ولا رمزَ في السطر** (القوسان والنقطة مصغّرةً يُقرآن «١٠١»)
    **ولا طرفَ ثالث**: صفحةُ الجهة لا يُذكر فيها من لم تتعاقد معه.
    وموضعُ الاسم يختلف باللسان، فـ`:brand` في النصّ يقسمه.
  --}}
  @php([$madeBefore, $madeAfter] = array_pad(explode(':brand', $strings['made_with'], 2), 2, ''))
  <p class="attest">
    @if(trim($madeBefore) !== '')<span>{{ trim($madeBefore) }}</span>@endif
    <a href="{{ $platformUrl }}" target="_blank" rel="noopener"><b class="wm"@if($pageLocale->value === 'ar') lang="ar"@endif>{{ $strings['platform_name'] }}</b></a>
    @if(trim($madeAfter) !== '')<span>{{ trim($madeAfter) }}</span>@endif
  </p>
</footer>
