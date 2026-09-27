{{-- بطاقة المجلس: أربعة صفوف، ويُطوى الصفّ الذي لا بيان له. --}}
{{-- عناوينُ الصفوف بلسان الصفحة (T-69). **وعنوانُ الملخّص لا يُوسَم**: يُترجَم،
     فوسمُه يقلب ما تُرجم. ★ **وما سواه يُوسَم عربياً** — T-87: اسمُ الشيخ
     والتاريخُ والمكان من بيانات المحاضرة العربية، لا تُترجَم في أيّ لغة، وبلا
     وسمٍ ينقلب ترتيبُها في السطر اللاتينيّ. --}}
<div class="majlis">
  <div class="row">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V9l8-5 8 5v11M9 20v-6h6v6"/><path d="M3 20h18"/></svg>
    <div>
      <span class="k">{{ $strings['majlis_lecture'] }}</span>
      <span class="v">{{ $title }}</span>
    </div>
  </div>

  @if($sheikhFull)
    <div class="row">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.6"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/></svg>
      <div>
        <span class="k">{{ $strings['majlis_speaker'] }}</span>
        <span class="v" lang="ar" dir="rtl">{{ $sheikhFull }}</span>
      </div>
    </div>
  @endif

  @if($dateGregorian || $dateHijri)
    <div class="row">
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
      <div>
        <span class="k">{{ $strings['majlis_date'] }}</span>
        <span class="v" lang="ar" dir="rtl">{{ $weekday }} {{ $dateGregorian }}{{ $timeNote }} @if($dateHijri)<em>— {{ $dateHijri }}</em>@endif</span>
      </div>
    </div>
  @endif

  @if($venueMode->showsVenueBlock() && $brand->venueFull)
    <div class="row">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
      <div>
        <span class="k">{{ $strings['majlis_venue'] }}</span>
        <span class="v" lang="ar" dir="rtl">{{ $brand->venueFull }}</span>
      </div>
    </div>
  @endif
</div>
