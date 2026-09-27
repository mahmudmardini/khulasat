import type { ReactNode } from 'react';
import { Button } from './Button';
import { t } from '@/lib/i18n';

interface Props {
  message: string;
  onRetry?: () => void;
  /**
   * فعلٌ بديل بجانب «أعد المحاولة» — T-83. وكان «غيّر المصدر» يقف خارج
   * البطاقة تحتها، فلا يُقرأ الفعلان بديلين للخطأ نفسه.
   */
  secondary?: ReactNode;
}

/**
 * الخطأ — §القواعد العامّة: «كل خطأ يذكر ما حدث ولماذا وما الإجراء.
 * **لا كود خطأ**».
 *
 * فالمكوّن لا يقبل `code` أصلاً. والرسالة تأتي مصاغةً من `lang/ar/errors.php`
 * حيث كُتبت كلّ واحدة تقترح إجراءً — المواصفة §5-أ-7.
 */
export function ErrorState({ message, onRetry, secondary }: Props) {
  return (
    <div
      role="alert"
      className="rounded-lg border border-danger/30 bg-danger/5 px-5 py-4"
    >
      <p className="text-[15px] leading-relaxed text-text">{message}</p>

      {onRetry !== undefined || secondary !== undefined ? (
        <div className="mt-3 flex flex-wrap gap-2">
          {onRetry !== undefined ? (
            <Button variant="secondary" onClick={onRetry}>
              {t('common.actions.retry')}
            </Button>
          ) : null}
          {secondary}
        </div>
      ) : null}
    </div>
  );
}
