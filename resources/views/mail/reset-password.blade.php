{{--
  رسالة استعادة كلمة المرور — T-32.

  **عربية RTL، وبنيةٌ بسيطة عمداً.** فعملاء البريد يجرّدون CSS الحديث،
  ورسالةٌ تنكسر عند القارئ تُقرأ احتيالاً فتُحذف. فالتنسيق بجداولٍ وسماتٍ
  داخلية كما تُكتب رسائل البريد، لا كما تُكتب صفحات الويب.

  **ولا شعار خارجيّ ولا صورة**: أكثر العملاء يحجب الصور افتراضاً، ورسالةٌ
  معناها في صورةٍ محجوبة رسالةٌ فارغة.

  **والهوية الثانية (§١٣) بلا صورةٍ أيضاً** — T-100: ترويسةٌ حبرية فيها
  الاسم نصّاً، وذيلٌ باسم المنصّة وحده (T-153). فالبريدُ يخرج باسم المنصّة.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ trans('auth.reset.mail_subject') }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f5f1;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6f5f1;padding:24px 12px;">
<tr><td align="center">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#ffffff;border:1px solid #e5e2da;border-radius:10px;border-collapse:separate;">
{{-- الترويسة الحبرية — والاسمُ بـReem Kufi إن وُجد، وإلّا فبخطّ النظام. --}}
<tr><td style="background:#243b6b;border-radius:10px 10px 0 0;padding:16px 26px;direction:rtl;text-align:right;">
  <span style="font-family:'Reem Kufi','Segoe UI',Tahoma,Arial,sans-serif;font-size:21px;font-weight:700;line-height:1.5;color:#f2f1ec;">{{ trans('common.product.name') }}</span>
</td></tr>

<tr><td style="padding:28px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#22201c;direction:rtl;text-align:right;">

  <p style="margin:0 0 14px;font-size:16px;line-height:1.9;">
    @if($name !== ''){{ trans('auth.reset.mail_greeting', ['name' => $name]) }}@else{{ trans('auth.reset.mail_greeting_plain') }}@endif
  </p>

  <p style="margin:0 0 22px;font-size:15px;line-height:1.9;color:#4a463f;">
    {{ trans('auth.reset.mail_intro') }}
  </p>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
    <tr><td style="background:#243b6b;border-radius:6px;">
      <a href="{{ $url }}" style="display:inline-block;padding:12px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
        {{ trans('auth.reset.mail_action') }}
      </a>
    </td></tr>
  </table>

  <p style="margin:0 0 14px;font-size:14px;line-height:1.9;color:#4a463f;">
    {{ trans('auth.reset.mail_expiry', ['minutes' => $minutes]) }}
  </p>

  {{--
    **ومن لم يطلب لا يُطلب منه شيء.** فسطرٌ يقول «إن لم تطلبها فتجاهلها»
    يُطمئن من وصلته الرسالة بلا طلب، ويمنعه من الظنّ أنّ حسابه اختُرق.
  --}}
  <p style="margin:0 0 20px;font-size:14px;line-height:1.9;color:#4a463f;">
    {{ trans('auth.reset.mail_ignore') }}
  </p>

  <hr style="border:none;border-top:1px solid #e5e2da;margin:0 0 14px;">

  {{-- الرابط نصّاً لمن لا يفتح الأزرار في بريده. و`ltr` لأنّه لاتينيّ. --}}
  <p style="margin:0;font-size:12px;line-height:1.8;color:#6f6a61;">
    {{ trans('auth.reset.mail_fallback') }}
    <br>
    <span dir="ltr" style="word-break:break-all;color:#243b6b;">{{ $url }}</span>
  </p>

</td></tr>

<tr><td style="border-top:1px solid #e5e2da;padding:13px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:12px;line-height:1.8;color:#706c62;direction:rtl;text-align:right;">
  {{ trans('common.product.name') }}
</td></tr>
</table>

</td></tr>
</table>
</body>
</html>
