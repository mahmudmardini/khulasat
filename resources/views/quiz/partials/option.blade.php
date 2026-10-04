{{--
  نصُّ الخيار — وخيارُ الشاهد **بلفظ مصدره وتخريجه**، والآيةُ بخطّ المصحف كما
  في المتن. واللفظُ من `matched_text` لا من النموذج (§2، القاعدة الثالثة).
--}}
<span class="kq-opt-text">
  @if ($option['evidence'])
    <span class="kq-ev {{ $option['evidence']->kind === 'ayah' ? 'is-ayah' : '' }}">
      @if ($option['evidence']->kind === 'ayah')﴿{{ $option['evidence']->text }}﴾@else«{{ $option['evidence']->text }}»@endif
    </span>
    @if ($option['evidence']->citation() !== '')
      <span class="kq-ev-src">{{ $option['evidence']->citation() }}</span>
    @endif
  @else
    {{ $option['text'] }}
  @endif
</span>
