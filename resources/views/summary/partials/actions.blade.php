{{-- أزرار المشاركة والطباعة وحوار نصيحة الطباعة — بنية القالب كما هي.
     ★ **ونصوصُها بلسان الصفحة** — T-87. كانت عربيةً في كلّ لغة، فقارئٌ لا
     يعرف العربية لا يعرف أيَّ الزرّين يحفظ الملفّ. --}}
<div class="actions">
  <button class="act primary" id="shareBtn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="18" cy="5.5" r="2.8"/><circle cx="6" cy="12" r="2.8"/><circle cx="18" cy="18.5" r="2.8"/><path d="M8.5 10.7l7-3.4M8.5 13.3l7 3.4"/></svg>
    {{ $strings['share_summary'] }}
  </button>
  <button class="act" id="printBtn">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M7 9V3.5h10V9M7 18.5H5a2 2 0 0 1-2-2V11a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5.5a2 2 0 0 1-2 2h-2"/><rect x="7" y="14.5" width="10" height="6"/></svg>
    {{ $strings['save_pdf'] }}
  </button>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<div class="print-tip" id="printTip" role="dialog" aria-modal="true" aria-labelledby="tipTitle">
  <div class="box">
    <h3 id="tipTitle">{{ $strings['print_tip_title'] }}</h3>
    <p>{{ $strings['print_tip_intro'] }}</p>
    <ol>
      <li>{{ $strings['print_tip_destination'] }}: <b>{{ $strings['save_pdf'] }}</b></li>
      <li>{{ $strings['print_tip_background'] }} <b>{{ $strings['print_tip_background_label'] }}</b>{{ $strings['print_tip_background_hint'] }}</li>
      <li>{{ $strings['print_tip_paper'] }}: <b>A4</b>{{ $strings['comma'] }}{{ $strings['print_tip_margins'] }}: <b>{{ $strings['print_tip_default'] }}</b></li>
    </ol>
    <div class="acts">
      <button class="go" id="tipGo">{{ $strings['print_tip_go'] }}</button>
      <button class="cancel" id="tipCancel">{{ $strings['print_tip_back'] }}</button>
    </div>
  </div>
</div>
