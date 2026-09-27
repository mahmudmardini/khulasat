{{-- سكربت القالب — منقولٌ حرفاً بحرف من `khulasah.skill`.
     `@@verbatim` تمنع Blade من تفسير `@media` و`{{` وما شابه، فيخرج
     الملفّ كما دخل بايتاً ببايت. **ولا يُعدَّل منه حرف** — CLAUDE.md
     §2 القاعدة الأولى.

     ★ **إلّا موضعَ المشاركة** — T-55. و`@@verbatim` حفظت `{{TITLE}}`
     و`{{SUBTITLE}}` و`{{SHEIKH_FULL}}` و`{{VENUE_SHORT}}` كما حفظت ما
     حولها، **وهي مواضعُ تُملأ لا تُصان**. فكان زرُّ المشاركة يعرض اسم
     المتغيّر نفسه لمن يشارك الصفحة.

     والقيمُ تُبنى هنا بـ`Js::from` لا بالتهريب الاعتيادي: النصّ يدخل
     سلسلةَ JavaScript لا متنَ HTML، و`htmlspecialchars` لا تحرس هذا
     الموضع. وبقيّةُ السكربت داخل `@@verbatim` كما كانت.

     ★ **ورسائلُ النسخ الثلاث كذلك** — T-87. كانت عربيةً ثابتةً في السكربت،
     فتظهر لقارئ الصفحة الإنجليزية رسالةٌ لا يقرؤها. فتُملأ من `PageStrings`
     بلسان الصفحة، وحارسُ القالب يردّها إلى لفظها قبل المقارنة. --}}
<?php
$shareTitle = trim($title.($subtitle ? ' — '.$subtitle : ''));
$shareText = $sheikhFull
    ? str_replace(':sheikh', $sheikhFull, $strings['share_text'])
        .($brand->venueShort ? str_replace(':venue', $brand->venueShort, $strings['share_text_venue']) : '').'.'
    : $shareTitle;
?>
var KHULASAH_SHARE = {title: {!! \Illuminate\Support\Js::from($shareTitle) !!}, text: {!! \Illuminate\Support\Js::from($shareText) !!}, copied: {!! \Illuminate\Support\Js::from($strings['toast_copied']) !!}, copyFailed: {!! \Illuminate\Support\Js::from($strings['toast_copy_failed']) !!}, copyManual: {!! \Illuminate\Support\Js::from($strings['toast_copy_manual']) !!}};
@verbatim
(function(){
  var tip=document.getElementById('printTip');
  var open=function(){tip.classList.add('open');document.getElementById('tipGo').focus();};
  var close=function(){tip.classList.remove('open');};
  document.getElementById('printBtn').addEventListener('click',open);

  var toast=document.getElementById('toast');
  var t;
  function say(msg){
    toast.textContent=msg;
    toast.classList.add('show');
    clearTimeout(t);
    t=setTimeout(function(){toast.classList.remove('show');},2600);
  }

  document.getElementById('shareBtn').addEventListener('click',function(){
    var url=window.location.href;
    var data={
      title:KHULASAH_SHARE.title,
      text:KHULASAH_SHARE.text,
      url:url
    };
    if(navigator.share){
      navigator.share(data).catch(function(){});
      return;
    }
    if(navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(url).then(function(){
        say(KHULASAH_SHARE.copied);
      }).catch(function(){say(KHULASAH_SHARE.copyFailed);});
      return;
    }
    say(KHULASAH_SHARE.copyManual);
  });
  document.getElementById('tipCancel').addEventListener('click',close);
  document.getElementById('tipGo').addEventListener('click',function(){close();setTimeout(window.print,120);});
  tip.addEventListener('click',function(e){if(e.target===tip)close();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
})();
@endverbatim
