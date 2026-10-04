{{--
  زخرفةُ التاج في الأولى والأخيرة — T-173. والقيمةُ من كتالوج
  {@see App\Support\Render\CarouselDesign}، فلا SVG يصل من خارج هذا الملفّ.

  و«crest» هي زخرفةُ القالب قبل المواصفة حرفاً، فلا يتبدّل كاروسيلٌ قائم.
  والباقيات بـ`currentColor`، ولونُها من `.crown` في `_design`.
--}}
@if($ornament === 'crest')
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
@elseif($ornament === 'star')
<svg class="crest" viewBox="0 0 800 130" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <g fill="none" stroke="currentColor" stroke-width="1.8">
    <path d="M70 65 H320 M480 65 H730"/>
    <path d="M60 65 l10 -10 l10 10 l-10 10 Z M720 65 l10 -10 l10 10 l-10 10 Z"/>
    <rect x="358" y="23" width="84" height="84"/>
    <rect x="358" y="23" width="84" height="84" transform="rotate(45 400 65)"/>
    <circle cx="400" cy="65" r="26"/>
    <circle cx="400" cy="65" r="11" fill="currentColor" stroke="none"/>
  </g>
</svg>
@elseif($ornament === 'rosette')
<svg class="crest" viewBox="0 0 800 130" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <g fill="none" stroke="currentColor" stroke-width="1.6">
    <path d="M80 65 H320 M480 65 H720"/>
    <circle cx="400" cy="65" r="60"/>
    <circle cx="400" cy="65" r="26"/>
    <circle cx="438.00" cy="65.00" r="16"/>
    <circle cx="426.87" cy="91.87" r="16"/>
    <circle cx="400.00" cy="103.00" r="16"/>
    <circle cx="373.13" cy="91.87" r="16"/>
    <circle cx="362.00" cy="65.00" r="16"/>
    <circle cx="373.13" cy="38.13" r="16"/>
    <circle cx="400.00" cy="27.00" r="16"/>
    <circle cx="426.87" cy="38.13" r="16"/>
    <circle cx="400" cy="65" r="9" fill="currentColor" stroke="none"/>
    <circle cx="320" cy="65" r="5" fill="currentColor" stroke="none"/>
    <circle cx="480" cy="65" r="5" fill="currentColor" stroke="none"/>
  </g>
</svg>
@endif
