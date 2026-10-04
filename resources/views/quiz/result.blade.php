{{--
  بطاقةُ النتيجة ومراجعةُ الأسئلة — T-195.

  **البطاقةُ باسم المشارك وعنوان الدرس وهوية الجهة** (قرار @HasanSiwi):
  تهنئةٌ عند ٨٠٪ فأكثر، وما دونه سطرٌ يدلّ على المراجعة بلا تقريع. وتُطبع
  صفحةً مرتّبة، فتُحفظ أو تُرسل.
--}}
@extends('quiz.layout', ['pageTitle' => trans('quiz.public.result_title', [], 'ar')])

@php($digits = static fn (int|string $n): string => \App\Support\Arabic::toArabicIndicDigits($n))
@php($seconds = (int) $attempt->duration_seconds)
@php($duration = $digits(intdiv($seconds, 60)).':'.$digits(str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT)))
@php($ring = 2 * M_PI * 52)

@section('content')
<section class="kq-card {{ $honoured ? 'is-honoured' : '' }}" aria-labelledby="kq-card-name">
  <div class="kq-card-frame">
    <p class="kq-card-hail">{{ $honoured ? trans('quiz.public.hail_honoured', [], 'ar') : trans('quiz.public.hail_done', [], 'ar') }}</p>
    <p class="kq-card-name" id="kq-card-name">{{ $attempt->participant_name }}</p>
    <p class="kq-card-line">{{ $honoured ? trans('quiz.public.line_honoured', [], 'ar') : trans('quiz.public.line_done', [], 'ar') }}</p>

    <div class="kq-medal" role="img" aria-label="{{ trans('quiz.public.score_aria', ['score' => $attempt->score, 'total' => $attempt->total], 'ar') }}">
      <svg viewBox="0 0 120 120" aria-hidden="true">
        <path class="kq-medal-star" d="M60 6l15.8 15.8h22.4v22.4L114 60l-15.8 15.8v22.4H75.8L60 114l-15.8-15.8H21.8V75.8L6 60l15.8-15.8V21.8h22.4z"/>
        <circle class="kq-medal-track" cx="60" cy="60" r="52"/>
        <circle class="kq-medal-arc" cx="60" cy="60" r="52"
                stroke-dasharray="{{ round($ring, 2) }}"
                stroke-dashoffset="{{ round($ring * (1 - $percent / 100), 2) }}"
                style="--kq-ring: {{ round($ring, 2) }}"/>
      </svg>
      <span class="kq-medal-score">{{ $digits((int) $attempt->score) }}<small>{{ trans('quiz.public.of_total', ['total' => $digits($attempt->total)], 'ar') }}</small></span>
    </div>

    <dl class="kq-card-facts">
      <div><dt>{{ trans('quiz.public.fact_percent', [], 'ar') }}</dt><dd>{{ $digits($percent) }}٪</dd></div>
      <div><dt>{{ trans('quiz.public.fact_time', [], 'ar') }}</dt><dd>{{ $duration }}</dd></div>
      @if ($attempt->attempt_number > 1)
        <div><dt>{{ trans('quiz.public.fact_attempt', [], 'ar') }}</dt><dd>{{ $digits($attempt->attempt_number) }}</dd></div>
      @endif
    </dl>

    <p class="kq-card-lesson">{{ trans('quiz.public.card_lesson', ['title' => $title], 'ar') }}</p>

    <p class="kq-card-venue">
      @if ($brand->logoDataUri)
        <img src="{{ $brand->logoDataUri }}" alt="">
      @endif
      <span>{{ $brand->venueShort ?? $brand->venueFull }}</span>
      <span class="kq-card-date">{{ $digits($attempt->finished_at->locale('ar')->translatedFormat('j F Y')) }}</span>
    </p>
  </div>
</section>

<div class="kq-actions">
  <a class="kq-btn kq-btn-primary" href="{{ route('quiz.show', [$quiz->token, 'name' => $attempt->participant_name]) }}">{{ trans('quiz.public.retake', [], 'ar') }}</a>
  @if ($summaryUrl)
    <a class="kq-btn" href="{{ $summaryUrl }}">{{ trans('quiz.public.read_summary', [], 'ar') }}</a>
  @endif
  <button type="button" class="kq-btn" data-print hidden>{{ trans('quiz.public.print', [], 'ar') }}</button>
</div>

<section class="kq-review" aria-labelledby="kq-review-title">
  <h2 id="kq-review-title" class="kq-review-title">{{ trans('quiz.public.review_title', [], 'ar') }}</h2>

  <ol class="kq-review-list">
    @foreach ($questions as $q)
      @php($right = $q['chosen'] === $q['correct'])
      <li class="kq-rq {{ $right ? 'is-right' : 'is-wrong' }}">
        <p class="kq-rq-head">
          <span class="kq-rq-mark" aria-hidden="true">{{ $right ? '✓' : '✗' }}</span>
          <span class="kq-rq-verdict">{{ $right ? trans('quiz.public.verdict_correct', [], 'ar') : ($q['chosen'] === null ? trans('quiz.public.verdict_skipped', [], 'ar') : trans('quiz.public.verdict_wrong', [], 'ar')) }}</span>
        </p>
        <p class="kq-rq-prompt">{{ $q['prompt'] }}</p>

        <ul class="kq-rq-options">
          @foreach ($q['options'] as $k => $option)
            @php($state = $k === $q['correct'] ? 'is-correct' : ($k === $q['chosen'] ? 'is-wrong' : ''))
            <li class="kq-opt kq-opt-static {{ $state }}">
              <span class="kq-opt-mark" aria-hidden="true"></span>
              @include('quiz.partials.option', ['option' => $option])
              @if ($state === 'is-correct')
                <span class="kq-opt-tag">{{ trans('quiz.public.tag_correct', [], 'ar') }}</span>
              @elseif ($state === 'is-wrong')
                <span class="kq-opt-tag">{{ trans('quiz.public.tag_yours', [], 'ar') }}</span>
              @endif
            </li>
          @endforeach
        </ul>

        <p class="kq-rq-why">{{ $q['explanation'] }}</p>
        @if ($q['axis'])
          <p class="kq-rq-axis">{{ trans('quiz.public.review_axis', ['axis' => $q['axis']], 'ar') }}</p>
        @endif
      </li>
    @endforeach
  </ol>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    var print = document.querySelector('[data-print]');
    if (!print) return;
    print.hidden = false;
    print.addEventListener('click', function () { window.print(); });
  })();
</script>
@endpush
