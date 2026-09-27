import { Link, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Button } from '@/Components/Button';
import { FieldGroup } from '@/Components/FieldGroup';
import { t } from '@/lib/i18n';

interface Props {
  token: string;
  email: string;
}

/**
 * وضع كلمة مرور جديدة — SCREENS.md §1، والمهمّة T-32.
 *
 * **ولا دخول تلقائيّ بعدها**: المنتج بحارسين (`web` و`admin`)، ووسيطُ
 * الاستعادة على مزوّد الجهة وحده — فدخولٌ تلقائيّ يُدخل المشرفَ العامّ
 * على حارس الجهة. والتحويل إلى الدخول يُصيب الحارسَين معاً.
 */
export default function ResetPassword({ token, email }: Props) {
  const form = useForm({
    token,
    email,
    password: '',
    password_confirmation: '',
  });

  return (
    <AuthLayout
      title={t('auth.reset.new_title')}
      subtitle={t('auth.reset.new_subtitle')}
      footer={
        <Link href="/panel/login" className="text-primary underline-offset-4 hover:underline">
          {t('auth.reset.back_to_login')}
        </Link>
      }
    >
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/panel/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
          });
        }}
        className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-card"
      >
        {/*
          والبريد يُعرض ولا يُحرَّر: هو المكتوب في الرابط، وتبديلُه هنا
          يُفشل الوسيط برسالةٍ غامضة. ويُرسل في حقلٍ خفيّ.
        */}
        <FieldGroup label={t('auth.email')} error={form.errors.email} required>
          <input type="email" name="email" dir="ltr" value={form.data.email} readOnly className="field text-start" />
        </FieldGroup>

        <FieldGroup
          label={t('auth.reset.password')}
          hint={t('auth.reset.rules')}
          error={form.errors.password}
          required
        >
          <input
            type="password"
            name="password"
            autoComplete="new-password"
            autoFocus
            value={form.data.password}
            onChange={(event) => form.setData('password', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <FieldGroup
          label={t('auth.reset.confirm')}
          error={form.errors.password_confirmation}
          required
        >
          <input
            type="password"
            name="password_confirmation"
            autoComplete="new-password"
            value={form.data.password_confirmation}
            onChange={(event) => form.setData('password_confirmation', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <Button type="submit" loading={form.processing} block>
          {t('auth.reset.save')}
        </Button>
      </form>
    </AuthLayout>
  );
}
