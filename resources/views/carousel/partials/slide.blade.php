{{--
  شريحةٌ واحدة. و`$slide` من {@see App\Support\Render\Slide}.

  **والشعار في الأولى والأخيرة فقط** — §8-أ. والقرار في
  {@see App\Enums\SlideKind::bearsLogo()} لا هنا، فلا يتفرّق على قالبين.
--}}
<figure class="slide {{ $slide->kind->value }} {{ $sizeClass($slide) }} l-{{ $design->layoutFor($slide->kind) }}" data-index="{{ $slide->index }}">
  <div class="frame">

    @if($slide->kind->bearsLogo())
      <div class="crown">
        @include('carousel.partials.ornament', ['ornament' => $design->value('ornament')])

        @if($brand->logoDataUri)
          <img class="logo{{ $brand->logoTransparent ? ' no-plate' : '' }}" src="{{ $brand->logoDataUri }}" alt="{{ $brand->venueFull }}">
        @endif
      </div>
    @endif

    @if($slide->kind === \App\Enums\SlideKind::Cover && $sheikh)
      <p class="eyebrow">{{ $sheikh }}</p>
    @endif

    @if($slide->heading !== '')
      <h2 class="heading">{{ $slide->heading }}</h2>
    @endif

    <div class="body"><p>{{ $slide->body }}</p></div>

    @if($slide->sourceLine)
      <p class="source">{{ $slide->sourceLine }}</p>
    @endif

    @if($slide->kind === \App\Enums\SlideKind::Closing && $pageUrl)
      <p class="link">{{ \App\Support\Render\BrandKit::shorten($pageUrl) }}</p>
    @endif

    <div class="foot">
      <span class="num">{{ $slide->label() }}</span>
      <span class="venue">
        <b>{{ $brand->venueShort ?? $brand->venueFull }}</b>
        @if($brand->venueLatin){{ $brand->venueLatin }}@endif
      </span>
    </div>

  </div>
</figure>
