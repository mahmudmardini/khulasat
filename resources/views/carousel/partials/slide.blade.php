{{--
  شريحةٌ واحدة. و`$slide` من {@see App\Support\Render\Slide}.

  **والشعار في الأولى والأخيرة فقط** — §8-أ. والقرار في
  {@see App\Enums\SlideKind::bearsLogo()} لا هنا، فلا يتفرّق على قالبين.
--}}
<figure class="slide {{ $slide->kind->value }} {{ $sizeClass($slide) }}" data-index="{{ $slide->index }}">
  <div class="frame">

    @if($slide->kind->bearsLogo())
      <div class="crown">
        <svg class="crest" viewBox="0 0 800 130" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <g fill="none" stroke="#D2AC63" stroke-width="1.4">
            <path d="M0 118 H800 M0 112 H800"/>
            <g opacity=".9">
              <path d="M400 14 L414 44 L446 30 L432 62 L464 76 L432 90 L446 122 L414 108 L400 138 L386 108 L354 122 L368 90 L336 76 L368 62 L354 30 L386 44 Z" transform="translate(0,-14)"/>
              <circle cx="400" cy="62" r="15"/>
              <circle cx="400" cy="62" r="7" fill="#D2AC63" stroke="none"/>
            </g>
          </g>
        </svg>

        @if($brand->logoDataUri)
          <img class="logo" src="{{ $brand->logoDataUri }}" alt="{{ $brand->venueFull }}">
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
