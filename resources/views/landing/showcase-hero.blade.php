{{--
  لوحُ البطل بخلاصةٍ منشورةٍ حقيقية — T-143.

  **البنيةُ والأصنافُ هي هي لوحُ المواضع الموصوفة** في `landing.blade.php`،
  والمبدَّلُ المحتوى وحده. فلا تتباعد الصورتان: من عدّل لوح المواضع رأى
  هذا بجانبه.

  **والعربيُّ موسومٌ حيث وقع.** البنيةُ والشواهدُ عربيّةٌ في كلّ لغة، والعنوانُ
  بلسان الصفحة إن نُشرت به — فكلُّ سطرٍ يحمل `lang` و`dir` لسانِه هو.
--}}
@php($quote = $showcase->heroQuote())
<div class="d">
  <div class="d-head">
    <div class="d-orn"><i></i><b></b><i></i></div>
    <p class="d-title" lang="{{ $showcase->titleLocale->value }}" dir="{{ $showcase->titleLocale->direction() }}">{{ $showcase->title }}</p>
    @if ($showcase->subtitle)
    <p class="d-sub" lang="{{ $showcase->titleLocale->value }}" dir="{{ $showcase->titleLocale->direction() }}">{{ $showcase->subtitle }}</p>
    @endif
    @if ($showcase->keyAyah)
    <p class="d-ayah" lang="ar" dir="rtl">{{ \App\Support\Quran\AyahText::decorate($showcase->keyAyah->text) }}</p>
    <p class="d-src" lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">{{ $showcase->keyAyah->citation($locale) }}</p>
    @endif
    @if ($showcase->speaker || $showcase->venue)
    <div class="d-attrib">
      <div><span>{{ __('landing.demo.speaker_label') }}</span><b dir="auto">{{ $showcase->speaker ?? '—' }}</b></div>
      <div><span>{{ __('landing.demo.place_label') }}</span><b dir="auto">{{ $showcase->venue ?? '—' }}</b></div>
    </div>
    @endif
  </div>
  {{-- الورقُ مقتطفٌ من صفحةٍ عربية، فيتّجه كلُّه بلسانها — لا سطورُه وحدها،
       وإلّا وقع الحدُّ الذهبيُّ في جهةٍ والنصُّ في أخرى على الصفحة اللاتينية. --}}
  <div class="d-paper d-peek" lang="ar" dir="rtl" aria-hidden="true">
    @if ($showcase->axisName)
    <div class="d-sechead"><i></i><h4>{{ $showcase->axisName }}</h4><u></u></div>
    @endif
    @if ($showcase->coreConcept)
    <p class="d-lead">{{ $showcase->coreConcept }}</p>
    @endif
    @if ($quote)
    <div class="d-sacred">
      <p>{{ $quote->kind === 'ayah' ? \App\Support\Quran\AyahText::decorate($quote->text) : $quote->text }}</p>
      <span lang="{{ $locale->value }}" dir="{{ $locale->direction() }}">{{ $quote->citation($locale) }}</span>
    </div>
    @endif
  </div>
</div>
<p class="shot-cap"><a href="{{ $showcase->url }}" target="_blank" rel="noopener">{{ __('landing.hero.caption_real') }}</a></p>
