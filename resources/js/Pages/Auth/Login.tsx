import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AuthLayout } from '@/Layouts/AuthLayout';
import { Button } from '@/Components/Button';
import { FieldGroup } from '@/Components/FieldGroup';
import { PasswordInput } from '@/Components/PasswordInput';
import { t } from '@/lib/i18n';

/**
 * تسجيل الدخول — SCREENS.md §1: «حقلان وزر واستعادة كلمة المرور».
 *
 * والهيكل في {@see AuthLayout} منذ T-32، حين صارت شاشات الباب ثلاثاً.
 */
type JudgeRole = 'admin' | 'owner' | 'editor';

interface Props {
  /** أزرارُ لجنة التحكيم — T-186. لا تصل إلّا لمن يحقّ له، وإلّا `null`. */
  judges: { key: string | null; roles: JudgeRole[] } | null;
}

export default function Login({ judges }: Props) {
  const form = useForm({ email: '', password: '', remember: false });
  const [showPassword, setShowPassword] = useState(false);
  const [entering, setEntering] = useState<JudgeRole | null>(null);

  return (
    <AuthLayout
      title={t('auth.title')}
      subtitle={t('auth.subtitle')}
      /* لا تسجيل ذاتي — الحسابات بدعوة. فيُقال صراحةً بدل رابطٍ لا وجود له. */
      footer={t('auth.no_signup')}
    >
      {judges !== null ? (
        <section
          aria-labelledby="judges-title"
          className="mb-5 space-y-3 rounded-xl border border-primary/30 bg-primary/5 p-5"
        >
          <div>
            <h2 id="judges-title" className="text-[15px] font-semibold text-text">
              {t('auth.judges.title')}
            </h2>
            <p className="mt-1 text-[13.5px] text-text-muted">{t('auth.judges.hint')}</p>
          </div>
          <div className="flex flex-col gap-2">
            {judges.roles.map((role) => (
              <Button
                key={role}
                variant={role === 'owner' ? 'primary' : 'secondary'}
                block
                loading={entering === role}
                disabled={entering !== null}
                onClick={() => {
                  setEntering(role);
                  router.post(
                    '/panel/login/judge',
                    { role, judge: judges.key },
                    { onFinish: () => setEntering(null) },
                  );
                }}
              >
                {t(`auth.judges.roles.${role}`)}
              </Button>
            ))}
          </div>
          <p className="pt-1 text-center text-[13px] text-text-faint">{t('auth.judges.or')}</p>
        </section>
      ) : null}

      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/panel/login', {
            // الكلمةُ تُمسح بعد كلّ إرسال، فلا تبقى الرؤيةُ مفتوحةً على حقلٍ فارغ.
            onFinish: () => {
              form.reset('password');
              setShowPassword(false);
            },
          });
        }}
        className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-card"
      >
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

        <FieldGroup label={t('auth.password')} error={form.errors.password} required>
          <PasswordInput
            name="password"
            autoComplete="current-password"
            visible={showPassword}
            onVisibleChange={setShowPassword}
            value={form.data.password}
            onChange={(event) => form.setData('password', event.target.value)}
          />
        </FieldGroup>

        <div className="flex flex-wrap items-center justify-between gap-2">
          <label className="flex cursor-pointer items-center gap-2 text-[14px] text-text-muted">
            <input
              type="checkbox"
              name="remember"
              checked={form.data.remember}
              onChange={(event) => form.setData('remember', event.target.checked)}
              className="size-4 rounded border-border accent-[var(--primary)]"
            />
            {t('auth.remember')}
          </label>

          {/*
            ★ **والاستعادة هنا لا في التذييل** — SCREENS.md §1 يعدّها من
            الشاشة نفسها. ومن نسي كلمته يبحث عنها **عند حقل الكلمة**، لا
            في سطرٍ رماديّ أسفل الصفحة.
          */}
          <Link
            href="/panel/forgot-password"
            className="text-[14px] text-primary underline-offset-4 hover:underline"
          >
            {t('auth.reset.link')}
          </Link>
        </div>

        <Button type="submit" loading={form.processing} block>
          {t('auth.submit')}
        </Button>
      </form>
    </AuthLayout>
  );
}
