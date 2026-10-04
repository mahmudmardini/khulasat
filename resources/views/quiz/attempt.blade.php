{{--
  الأسئلة — T-195.

  **نموذجٌ واحدٌ يعمل بلا JavaScript**: الأسئلةُ كلُّها `fieldset`، وخياراتُها
  `radio` حقيقية، و«أنهِ الاختبار» يرسلها مرّةً. والسكربتُ تحسينٌ فوقه: سؤالٌ
  في الشاشة، وكلُّ جوابٍ يُحفظ لحظةَ اختياره.

  **ولا يحمل HTML هذه الصفحة الجوابَ الصحيح** إلّا لسؤالٍ كُشف حكمُه في وضع
  «بعد كلّ سؤال».
--}}
{{-- **رأسٌ مضغوط** هنا: على الجوال يُرى السؤالُ وخياراتُه من أوّل نظرة. --}}
@extends('quiz.layout', ['compact' => true])

@php($total = count($questions))
@php($digits = static fn (int $n): string => \App\Support\Arabic::toArabicIndicDigits($n))

@section('content')
<form id="kq-quiz" class="kq-sheet kq-quiz" method="post"
      action="{{ route('quiz.finish', [$quiz->token, $attempt->token]) }}"
      data-answer-url="{{ route('quiz.answer', [$quiz->token, $attempt->token]) }}"
      data-immediate="{{ $immediate ? '1' : '0' }}">
  @csrf

  <div class="kq-progress" aria-hidden="true">
    @foreach ($questions as $q)
      <span class="kq-seg {{ $q['chosen'] !== null ? 'is-done' : '' }}" data-seg="{{ $q['id'] }}"></span>
    @endforeach
  </div>

  @foreach ($questions as $i => $q)
    @php($revealed = $q['correct'] !== null)
    <fieldset class="kq-q" id="kq-q-{{ $q['id'] }}" data-qid="{{ $q['id'] }}" data-index="{{ $i }}"
              data-answered="{{ $q['chosen'] !== null ? '1' : '0' }}">
      <legend class="kq-q-legend" tabindex="-1">
        <span class="kq-q-num">{{ trans('quiz.public.question_of', ['n' => $digits($i + 1), 'total' => $digits($total)], 'ar') }}</span>
        <span class="kq-q-prompt">{{ $q['prompt'] }}</span>
      </legend>

      <div class="kq-options">
        @foreach ($q['options'] as $k => $option)
          @php($state = ! $revealed ? '' : ($k === $q['correct'] ? 'is-correct' : ($k === $q['chosen'] ? 'is-wrong' : '')))
          <label class="kq-opt {{ $state }}" data-option="{{ $k }}">
            <input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $k }}"
                   @checked($q['chosen'] === $k) @disabled($revealed)>
            <span class="kq-opt-mark" aria-hidden="true"></span>
            @include('quiz.partials.option', ['option' => $option])
            <span class="kq-opt-tag" data-tag>
              @if ($state === 'is-correct'){{ trans('quiz.public.tag_correct', [], 'ar') }}@elseif ($state === 'is-wrong'){{ trans('quiz.public.tag_yours', [], 'ar') }}@endif
            </span>
          </label>
        @endforeach
      </div>

      <div class="kq-verdict {{ $revealed ? ($q['chosen'] === $q['correct'] ? 'is-correct' : 'is-wrong') : '' }}"
           data-verdict tabindex="-1" @if (! $revealed) hidden @endif>
        @if ($revealed)
          <p class="kq-verdict-head">{{ $q['chosen'] === $q['correct'] ? trans('quiz.public.verdict_correct', [], 'ar') : trans('quiz.public.verdict_wrong', [], 'ar') }}</p>
          <p class="kq-verdict-body">{{ $q['explanation'] }}</p>
        @else
          <p class="kq-verdict-head"></p>
          <p class="kq-verdict-body"></p>
        @endif
      </div>
    </fieldset>
  @endforeach

  <div class="kq-nav">
    <button type="button" class="kq-btn" data-prev>{{ trans('quiz.public.prev', [], 'ar') }}</button>
    <button type="button" class="kq-btn kq-btn-primary" data-next>{{ trans('quiz.public.next', [], 'ar') }}</button>
    <button type="submit" class="kq-btn kq-btn-primary" data-finish>{{ trans('quiz.public.finish', [], 'ar') }}</button>
  </div>

  <p class="kq-status" id="kq-status" role="status" aria-live="polite"></p>
</form>

@php($strings = [
  'saved' => trans('quiz.public.saved', [], 'ar'),
  'save_failed' => trans('quiz.public.save_failed', [], 'ar'),
  'unanswered' => trans('quiz.public.unanswered', [], 'ar'),
  'verdict_correct' => trans('quiz.public.verdict_correct', [], 'ar'),
  'verdict_wrong' => trans('quiz.public.verdict_wrong', [], 'ar'),
  'tag_correct' => trans('quiz.public.tag_correct', [], 'ar'),
  'tag_yours' => trans('quiz.public.tag_yours', [], 'ar'),
])
<script type="application/json" id="kq-strings">{!! json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endsection

@push('scripts')
  @include('quiz.partials._script')
@endpush
