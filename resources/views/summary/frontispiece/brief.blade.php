{{--
  سرلوح البطاقة الموجزة — T-49.

  **أقصرُ ما يمكن.** البطاقة تُقرأ في دقيقة وتُشارَك على الجوال، فسرلوحُها
  عنوانٌ وسطرٌ واحد. ولا آيةَ غلافٍ هنا: الشواهد تأتي في موضعها من المتن،
  وصدرُ البطاقة للخلاصة لا للزينة.
--}}
<header class="unwan">
  <div class="inner">
    @include('summary.partials.brand-mark')
    <h1>{{ $title }}</h1>
    @if($subtitle)
      <p class="sub">{{ $subtitle }}</p>
    @endif
  </div>

  {{-- بيانات المجلس ذيلاً للسرلوح، خارج `.inner` ليبلغ حافّتيه — T-106 وT-108.
       وكانت في ذيل البطاقة (`brief-foot`). --}}
  @include('summary.partials.attrib')
</header>
