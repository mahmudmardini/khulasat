{{--
  تنبيه كلفةٍ أو أداءٍ — T-22.

  عربية RTL وبنيةٌ بسيطة عمداً — كرسالة استعادة كلمة المرور (T-32):
  عملاء البريد يجرّدون CSS الحديث، فالتنسيق بجداولٍ وسماتٍ داخلية.

  والترويسة والذيل كأختها منذ T-100 (الهوية الثانية §١٣). و«تنبيه» يبقى
  بلون الخطر تحت الترويسة: هذا بريدٌ يُفتح لأنّ شيئاً تجاوز حدّه.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f5f1;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6f5f1;padding:24px 12px;">
<tr><td align="center">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#ffffff;border:1px solid #e5e2da;border-radius:10px;border-collapse:separate;">
<tr><td style="background:#243b6b;border-radius:10px 10px 0 0;padding:16px 26px;direction:rtl;text-align:right;">
  <span style="font-family:'Reem Kufi','Segoe UI',Tahoma,Arial,sans-serif;font-size:21px;font-weight:700;line-height:1.5;color:#f2f1ec;">{{ trans('common.product.name') }}</span>
</td></tr>

<tr><td style="padding:28px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#22201c;direction:rtl;text-align:right;">

  <p style="margin:0 0 18px;font-size:19px;font-weight:700;color:#8e3b2e;">تنبيه</p>

  <p style="margin:0 0 18px;font-size:16px;line-height:1.9;font-weight:600;">
    {{ $subject }}
  </p>

  <p style="margin:0 0 22px;font-size:15px;line-height:1.9;color:#4a463f;">
    {{ $message }}
  </p>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
    <tr><td style="background:#243b6b;border-radius:6px;">
      <a href="{{ $url }}" style="display:inline-block;padding:12px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
        افتحْ شاشة الكلفة
      </a>
    </td></tr>
  </table>

</td></tr>

<tr><td style="border-top:1px solid #e5e2da;padding:13px 26px;font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:12px;line-height:1.8;color:#706c62;direction:rtl;text-align:right;">
  {{ trans('common.product.name') }}
</td></tr>
</table>

</td></tr>
</table>
</body>
</html>
