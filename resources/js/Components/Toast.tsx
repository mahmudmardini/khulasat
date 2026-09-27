import { useEffect } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export type ToastTone = 'success' | 'danger' | 'info';

interface Props {
  message: string | null;
  tone?: ToastTone;
  onDismiss: () => void;
}

const TONES: Record<ToastTone, string> = {
  success: 'border-success/40 bg-success/10 text-text',
  danger: 'border-danger/40 bg-danger/10 text-text',
  info: 'border-info/40 bg-info/10 text-text',
};

/**
 * إشعار عابر.
 *
 * و`aria-live="polite"` لا `assertive`: الإشعار لا يقاطع قارئ الشاشة في
 * منتصف جملة، وإنّما يُنطق عند أوّل سكتة. والمقاطعة تُترك للخطأ الحاجب.
 */
export function Toast({ message, tone = 'info', onDismiss }: Props) {
  useEffect(() => {
    if (message === null) {
      return;
    }

    const timer = window.setTimeout(onDismiss, 6000);

    return () => window.clearTimeout(timer);
  }, [message, onDismiss]);

  return (
    // أعلى الشاشة لا أسفلها — عمداً منذ T-107: كان ثابتاً فوق حافّة الشاشة
    // بمسافة صمّاء (`bottom-20`) لا تعرف ارتفاع `StickyBar` تحته، فيقع
    // الإشعار فوق زرّ الحفظ نفسه حين يطول الشريط. أعلى الشاشة لا يصطدم
    // بأيّ شريطٍ سفليٍّ مهما طال.
    <div aria-live="polite" className="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4">
      {message !== null ? (
        <div
          className={cn(
            'pointer-events-auto flex items-center gap-3 rounded-lg border px-4 py-3 shadow-card',
            TONES[tone],
          )}
        >
          <p className="text-[14px]">{message}</p>
          <button
            type="button"
            onClick={onDismiss}
            className="text-text-muted hover:text-text"
            aria-label={t('common.actions.close')}
          >
            <svg className="size-4" viewBox="0 0 16 16" aria-hidden="true">
              <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
            </svg>
          </button>
        </div>
      ) : null}
    </div>
  );
}
