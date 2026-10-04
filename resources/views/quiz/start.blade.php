{{-- صفحةُ البداية — T-195. **بلا حقلٍ واحد**: زرٌّ يبدأ، ولا يُطلب من المشارك شيء. --}}
@extends('quiz.layout')

@section('content')
@php($n = \App\Support\Arabic::toArabicIndicDigits($count))
@php($minutes = \App\Support\Arabic::toArabicIndicDigits(max(3, (int) ceil($count * 0.6))))

<section class="kq-sheet kq-start">
  @if ($available)
    <p class="kq-lead">{{ trans('quiz.public.lead', ['count' => $n, 'minutes' => $minutes], 'ar') }}</p>

    <form method="post" action="{{ route('quiz.start', $quiz->token) }}" class="kq-form">
      @csrf
      <button type="submit" class="kq-btn kq-btn-primary">{{ trans('quiz.public.start', [], 'ar') }}</button>
    </form>

    <p class="kq-privacy">{{ trans('quiz.public.privacy', [], 'ar') }}</p>
  @else
    <h2 class="kq-closed-title">{{ trans('quiz.public.closed_title', [], 'ar') }}</h2>
    <p class="kq-lead">{{ trans('quiz.public.closed_body', [], 'ar') }}</p>
    @if ($summaryUrl)
      <a class="kq-btn kq-btn-primary" href="{{ $summaryUrl }}">{{ trans('quiz.public.read_summary', [], 'ar') }}</a>
    @endif
  @endif
</section>
@endsection
