import { Link, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Button } from '@/Components/Button';
import { FieldGroup } from '@/Components/FieldGroup';
import { t } from '@/lib/i18n';

interface Props {
  token: string;
  valid: boolean;
  email: string | null;
  tenant: string | null;
  role: string | null;
}

/**
 * قبول دعوة الفريق — SCREENS.md §10، والمهمّة T-33.
 *
 * **وهو المسار الوحيد الذي يُنشأ به حساب**: لا تسجيل ذاتي (§1)، فالدعوة
 * بديلُه — ولا حساب إلّا بها.
 */
export default function AcceptInvitation({ token, valid, email, tenant, role }: Props) {
  const form = useForm({ token, name: '', password: '', password_confirmation: '' });

  if (!valid) {
    return (
      <AuthLayout
        title={t('team.accept.invalid')}
        subtitle={t('team.accept.invalid_body')}
        footer={
          <Link href="/panel/login" className="text-primary underline-offset-4 hover:underline">
            {t('auth.reset.back_to_login')}
          </Link>
        }
      >
        {/*
          **والحال تُقال ولا يُردّ ٤٠٤.** فرابطٌ منتهٍ يردّ «غير موجود»
          يُقرأ عطلاً عندنا، ويُراسَل به من دعا فيظنّ المنتج معطوباً.
        */}
        <div className="rounded-xl border border-border bg-surface p-6 text-center text-[14px] leading-relaxed text-text-muted shadow-card">
          {t('auth.no_signup')}
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title={t('team.accept.title')}
      subtitle={t('team.accept.subtitle', {
        tenant: tenant ?? '',
        role: t(`team.roles.${role ?? 'viewer'}`),
      })}
    >
      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/panel/invitations', {
            onFinish: () => form.reset('password', 'password_confirmation'),
          });
        }}
        className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-card"
      >
        {/* والبريد من الدعوة لا من المدعوّ: هو ما دُعي به، ولا يُبدَّل. */}
        <FieldGroup label={t('auth.email')} required>
          <input type="email" dir="ltr" value={email ?? ''} readOnly className="field text-start" />
        </FieldGroup>

        <FieldGroup label={t('team.accept.name')} error={form.errors.name} required>
          <input
            type="text"
            name="name"
            autoComplete="name"
            autoFocus
            value={form.data.name}
            onChange={(event) => form.setData('name', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <FieldGroup
          label={t('team.accept.password')}
          hint={t('team.accept.rules')}
          error={form.errors.password}
          required
        >
          <input
            type="password"
            name="password"
            autoComplete="new-password"
            value={form.data.password}
            onChange={(event) => form.setData('password', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <FieldGroup
          label={t('team.accept.confirm')}
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

        {form.errors.token !== undefined ? (
          <p className="text-[13px] text-danger">{form.errors.token}</p>
        ) : null}

        <Button type="submit" loading={form.processing} block>
          {t('team.accept.submit')}
        </Button>
      </form>
    </AuthLayout>
  );
}
