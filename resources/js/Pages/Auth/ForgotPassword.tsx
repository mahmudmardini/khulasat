import { Link, useForm, usePage } from '@inertiajs/react';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Button } from '@/Components/Button';
import { FieldGroup } from '@/Components/FieldGroup';
import { t } from '@/lib/i18n';
import type { SharedProps } from '@/types/inertia';

/**
 * طلب رابط الاستعادة — SCREENS.md §1، والمهمّة T-32.
 *
 * ★ **والردّ واحدٌ سواءٌ وُجد البريد أم لم يوجد.** وهي عين علّة رسالة
 * الدخول الواحدة: لو قيل «لا حساب بهذا البريد» لصار النموذج **أداةَ
 * استطلاع** يُعرف بها أيُّ البُرد مسجَّل عندنا.
 */
export default function ForgotPassword() {
  const page = usePage<SharedProps>();
  const sent = page.props.flash?.message ?? null;

  const form = useForm({ email: '' });

  return (
    <AuthLayout
      title={t('auth.reset.title')}
      subtitle={t('auth.reset.subtitle')}
      footer={
        <Link href="/panel/login" className="text-primary underline-offset-4 hover:underline">
          {t('auth.reset.back_to_login')}
        </Link>
      }
    >
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/panel/forgot-password');
        }}
        className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-card"
      >
        {/*
          والتأكيد يبقى في الشاشة بعد الإرسال: من ضغط ولم يرَ شيئاً يضغط
          ثانيةً وثالثة، ثمّ يبلغ الخنق فيظنّ المنتج معطوباً.
        */}
        {sent !== null ? (
          <p className="rounded-lg border border-success/30 bg-success/8 px-4 py-3 text-[14px] leading-relaxed text-text">
            {sent}
          </p>
        ) : null}

        <FieldGroup label={t('auth.email')} error={form.errors.email} required>
          <input
            type="email"
            name="email"
            autoComplete="email"
            dir="ltr"
            autoFocus
            value={form.data.email}
            onChange={(event) => form.setData('email', event.target.value)}
            className="field text-start"
          />
        </FieldGroup>

        <Button type="submit" loading={form.processing} block>
          {t('auth.reset.submit')}
        </Button>
      </form>
    </AuthLayout>
  );
}
