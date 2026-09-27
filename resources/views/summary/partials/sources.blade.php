{{--
  مواضع الآيات والأحاديث — **قائمة التخريج**.

  وهي موضع سياسة البيان (§7-5): كلّ شاهدٍ بلفظ مصدره **مقروناً بدرجته**.
  ومنشورٌ ضعيفٌ بلا بيان درجته يُسقط البوّابة الحاكمة، فالدرجة تُطبع هنا
  ولا تُطوى.

  ★ **الموضعُ أوّلاً ثمّ طرفُ الشاهد** — T-81. كانت تُعيد نصَّ كلّ شاهدٍ
  كاملاً فتصير نسخةً ثانيةً من المتن، والتخريجُ عربيٌّ في الصفحة الإنجليزية.
  والقائمةُ حاشيةٌ تُراجَع (T-67): يُعرف منها الشاهدُ وموضعُه، ولفظُه كاملاً
  في المتن. **والدرجةُ باقيةٌ في التخريج** لا تُطوى.
--}}
@if(count($evidence) > 0)
  <div class="sources">
    <h3>{{ $strings['sources_title'] }}</h3>
    <ol>
      @foreach($evidence as $item)
        @php($citation = $item->citation($pageLocale))
        @php($url = $item->url($pageLocale))
        {{-- **لفظُ الشاهد وحده يُوسَم** — T-66. والتخريجُ يُترجَم مع ما
             حوله، فوسمُه بالعربية يقلبه في الصفحة الإنجليزية. --}}
        <li>@if($citation !== '')@if($url)<a class="ref" href="{{ $url }}" target="_blank" rel="noopener">{{ $citation }}</a>@else<span class="ref">{{ $citation }}</span>@endif @endif<span class="excerpt" lang="ar" dir="rtl">{!! \App\Support\Quran\AyahText::html($item->excerpt()) !!}</span></li>
      @endforeach
    </ol>
    {{-- `src-note` صنفٌ في `_identity` — كانت سمةَ `style=` (الهوية §٠١، T-98). --}}
    @if($sourcesNote)
      <p class="muted src-note">{{ $sourcesNote }}</p>
    @endif
    {{-- نسبةُ ترجمات الآيات — مرّةً هنا لا فوق كلّ آية، T-89. --}}
    @if($translationCredit ?? null)
      <p class="muted src-note">{{ $translationCredit }}</p>
    @endif
  </div>
@endif
