import { usePage } from '@inertiajs/react';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface Props {
  open: boolean;
  title: string;
  /** ما سيحدث، بلا كلفته: الكلفةُ تحته بأرقامها. */
  action: string;
  confirmLabel: string;
  /** إعاداتُ هذا الملخّص، إن كان الفعلُ إعادةً تُحتسب منها. */
  regenerations?: { used: number; limit: number } | null;
  /** أيُحتسب ملخّصٌ من الحصّة الشهرية؟ والأرقامُ من `quota` المشتركة. */
  monthly?: boolean;
  /** فعلٌ يُبدِّل ما قبله (صياغةٌ تحلّ محلّ صياغة) — يُقال إنّه لا يُستردّ. */
  replaces?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

interface Line {
  counted: boolean;
  text: string;
}

/**
 * إقرارُ الكلفة قبل كلّ فعلٍ مدفوع — T-203، بطلب @HasanSiwi.
 *
 * **خطوةٌ ثانية تقول ما يُحتسب، وكم يبقى بعده.** والأرقامُ من الخادم:
 * إعاداتُ الملخّص من صفحته، والحصّةُ الشهرية من `quota` المشتركة. ولا تُقال
 * كلفةٌ لا تُعرف: فعلٌ لا يُحتسب يقول ذلك صراحة، لا يسكت عنه.
 *
 * **وحدٌّ استُنفد لا يُمضى**: يُقال إنّه استُنفد، و«إلغاء» وحده نافذ — فالخادمُ
 * يرفضه على كلّ حال، ورفضٌ بعد الضغط أسوأُ من منعٍ قبله.
 */
export function CostConfirm({
  open, title, action, confirmLabel, regenerations = null, monthly = false, replaces = false, onConfirm, onCancel,
}: Props) {
  const quota = usePage<{ quota?: { used: number; limit: number | null } | null }>().props.quota ?? null;

  const lines: Line[] = [];
  let blocked = false;

  if (regenerations !== null) {
    const left = Math.max(0, regenerations.limit - regenerations.used);

    if (left === 0) {
      blocked = true;
      lines.push({ counted: true, text: t('common.cost.regeneration_none', { limit: regenerations.limit }) });
    } else {
      lines.push({ counted: true, text: t('common.cost.regeneration', { left: left - 1, limit: regenerations.limit }) });
    }
  }

  if (monthly && quota !== null && quota.limit !== null) {
    const left = Math.max(0, quota.limit - quota.used);

    if (left === 0) {
      blocked = true;
      lines.push({ counted: true, text: t('common.cost.monthly_none', { limit: quota.limit }) });
    } else {
      lines.push({ counted: true, text: t('common.cost.monthly', { left: left - 1, limit: quota.limit }) });
    }
  }

  if (lines.length === 0) {
    lines.push({ counted: false, text: t('common.cost.free') });
  }

  return (
    <ConfirmDialog
      open={open}
      title={title}
      consequence={action}
      confirmLabel={confirmLabel}
      // ما يُحتسب لا يُستردّ، وما يُبدِّل ما قبله كذلك. وما سواهما لا يُهوَّل.
      reversible={!replaces && lines.every((line) => !line.counted)}
      blocked={blocked}
      details={
        <div className="mt-4 rounded-lg border border-border bg-surface-alt px-4 py-3">
          <p className="text-[13px] font-semibold text-text">{t('common.cost.title')}</p>
          <ul className="mt-1.5 flex flex-col gap-1.5">
            {lines.map((line) => (
              <li
                key={line.text}
                className={cn('flex items-start gap-2 text-[14px] leading-relaxed', line.counted ? 'text-text' : 'text-success')}
              >
                <Icon name={line.counted ? 'alert' : 'check'} size={15} className="mt-1 shrink-0" />
                <span className="nums-tabular">{toArabicIndic(line.text)}</span>
              </li>
            ))}
          </ul>
          <p className="mt-2 text-[12.5px] text-text-faint">{t('common.cost.model')}</p>
        </div>
      }
      onConfirm={onConfirm}
      onCancel={onCancel}
    />
  );
}
