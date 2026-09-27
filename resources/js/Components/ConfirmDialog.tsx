import { useEffect, useRef } from 'react';
import { t } from '@/lib/i18n';
import { Button } from './Button';

interface Props {
  open: boolean;
  title?: string;
  /** **ما سيُفقد بالضبط** — §القواعد العامّة. لا «هل أنت متأكّد؟». */
  consequence: string;
  confirmLabel?: string;
  /**
   * فعلٌ يُستردّ — T-97: تبديلُ صلاحيةٍ يُعاد بتبديلٍ آخر. فلا يُقال «لا يمكن
   * التراجع» ولا يُلوَّن الزرّ خطراً: التأكيدُ المبالغ فيه يُعلّم تجاهلَ التأكيد.
   */
  reversible?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

/**
 * تأكيد الفعل المدمّر — SCREENS.md §القواعد العامّة:
 * «كل فعل مدمّر له تأكيد **يذكر ما سيُفقَد بالضبط**».
 *
 * ولذلك `consequence` مطلوبة في النوع: حوارٌ بلا بيانٍ لما يضيع لا يُبنى
 * بهذا المكوّن أصلاً.
 *
 * ويستعمل `<dialog>` الأصلي: يحبس التركيز، ويغلق بـ Escape، ويُعلن مشروطاً
 * لقارئ الشاشة — كلّه بلا شيفرة نكتبها ونخطئ فيها.
 */
export function ConfirmDialog({
  open, title, consequence, confirmLabel, reversible = false, onConfirm, onCancel,
}: Props) {
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    const dialog = ref.current;

    if (dialog === null) {
      return;
    }

    if (open && !dialog.open) {
      dialog.showModal();
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  return (
    <dialog
      ref={ref}
      onCancel={(event) => { event.preventDefault(); onCancel(); }}
      className="m-auto w-[min(28rem,92vw)] rounded-lg border border-border bg-surface p-0 text-text shadow-card backdrop:bg-black/35"
    >
      <div className="p-5">
        <h2 className="text-[17px] font-semibold">{title ?? t('common.confirm.title')}</h2>

        <p className="mt-2 text-[15px] leading-relaxed text-text-muted">{consequence}</p>
        {reversible ? null : <p className="mt-1 text-[14px] text-danger">{t('common.confirm.irreversible')}</p>}

        <div className="mt-5 flex gap-2">
          <Button variant={reversible ? 'primary' : 'danger'} onClick={onConfirm}>
            {confirmLabel ?? t('common.actions.confirm')}
          </Button>
          <Button variant="secondary" onClick={onCancel}>
            {t('common.actions.cancel')}
          </Button>
        </div>
      </div>
    </dialog>
  );
}
