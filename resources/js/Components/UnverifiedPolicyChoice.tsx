import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export type UnverifiedPolicy = 'disclose' | 'review';

const POLICIES: UnverifiedPolicy[] = ['disclose', 'review'];

/**
 * اختيارُ وضع البيان — T-163، والمواصفة §7-5.
 *
 * **بطاقتان تُريان معاً لا قائمةٌ منسدلة**: الفرقُ بين الوضعين هو ما يُنشر
 * من الحديث الضعيف بلا إنسان، فيُقرأ شرحُ كلٍّ منهما قبل الاختيار لا بعده.
 */
export function UnverifiedPolicyChoice({
  name, value, current, onChange,
}: {
  name: string;
  value: UnverifiedPolicy;
  /** الوضعُ المحفوظ — يُوسَم في صفحة الجهة، ولا يُمرَّر عند الإنشاء. */
  current?: UnverifiedPolicy;
  onChange: (policy: UnverifiedPolicy) => void;
}) {
  return (
    <ul className="grid gap-3 sm:grid-cols-2">
      {POLICIES.map((policy) => (
        <li key={policy}>
          <label
            className={cn(
              'flex h-full cursor-pointer flex-col gap-1.5 rounded-lg border p-3 transition-colors',
              value === policy
                ? 'border-primary bg-primary/6'
                : 'border-border bg-surface-alt hover:border-border-strong',
            )}
          >
            <span className="flex items-center gap-2">
              <input
                type="radio"
                name={name}
                value={policy}
                checked={value === policy}
                onChange={() => onChange(policy)}
                className="accent-primary"
              />
              <span className="text-[15px] font-semibold text-text">{t(`admin.verification.${policy}`)}</span>
              {policy === current ? (
                <span className="rounded border border-border-strong px-1.5 py-0.5 text-[11px] text-text-muted">
                  {t('admin.verification.current')}
                </span>
              ) : null}
            </span>

            <span className="text-[12px] leading-relaxed text-text-muted">
              {t(`admin.verification.${policy}_hint`)}
            </span>
          </label>
        </li>
      ))}
    </ul>
  );
}
