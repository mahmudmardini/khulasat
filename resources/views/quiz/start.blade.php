{{-- صفحةُ البداية — خانةٌ واحدة: الاسم. T-195. --}}
@extends('quiz.layout')

@section('content')
@php($n = \App\Support\Arabic::toArabicIndicDigits($count))
@php($minutes = \App\Support\Arabic::toArabicIndicDigits(max(3, (int) ceil($count * 0.6))))

<section class="kq-sheet kq-start">
  @if ($available)
    <p class="kq-lead">{{ trans('quiz.public.lead', ['count' => $n, 'minutes' => $minutes], 'ar') }}</p>

    <form method="post" action="{{ route('quiz.start', $quiz->token) }}" class="kq-form" novalidate>
      @csrf
      <label for="kq-name" class="kq-label">{{ trans('quiz.public.name_label', [], 'ar') }}</label>
      <input id="kq-name" name="name" type="text" class="kq-input" required minlength="2" maxlength="60"
             autocomplete="name" value="{{ old('name', $name) }}"
             aria-describedby="kq-name-hint{{ $errors->has('name') ? ' kq-name-error' : '' }}"
             @if ($errors->has('name')) aria-invalid="true" @endif>
      <p id="kq-name-hint" class="kq-hint">{{ trans('quiz.public.name_hint', [], 'ar') }}</p>
      @error('name')
        <p id="kq-name-error" class="kq-error" role="alert">{{ $message }}</p>
      @enderror

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
