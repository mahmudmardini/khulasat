import { useId, type ReactElement, cloneElement } from 'react';
import { t } from '@/lib/i18n';

interface Props {
  label: string;
  hint?: string;
  error?: string;
  required?: boolean;
  suggested?: boolean;
  children: ReactElement<{ id?: string; 'aria-describedby'?: string; 'aria-invalid'?: boolean }>;
}

/**
 * غلاف الحقل: تسمية، وتلميح، وخطأ — مربوطةً بالحقل بـ `aria`.
 *
 * والربط يُبنى هنا مرّةً لا في كل شاشة، لأنّ `aria-describedby` المنسيّ
 * لا يُرى في المراجعة البصرية ويُسقط قارئ الشاشة.
 */
export function FieldGroup({ label, hint, error, required, suggested, children }: Props) {
  const id = useId();
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;

  const describedBy = [hint ? hintId : null, error ? errorId : null]
    .filter(Boolean)
    .join(' ');

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="flex items-center gap-2 text-[14px] font-medium text-text">
        {label}
        {required === true ? (
          <span className="text-danger" aria-label={t('common.state.required')}>*</span>
        ) : (
          <span className="text-[13px] font-normal text-text-faint">
            {t('common.state.optional')}
          </span>
        )}
        {suggested === true ? (
          <span className="rounded border border-accent/40 bg-accent/10 px-1.5 py-0.5 text-[12px] font-normal text-accent">
            {t('common.state.suggested')}
          </span>
        ) : null}
      </label>

      {cloneElement(children, {
        id,
        'aria-describedby': describedBy === '' ? undefined : describedBy,
        'aria-invalid': error !== undefined ? true : undefined,
      })}

      {hint !== undefined ? (
        <p id={hintId} className="text-[13px] text-text-muted">{hint}</p>
      ) : null}

      {error !== undefined ? (
        <p id={errorId} role="alert" className="text-[13px] text-danger">{error}</p>
      ) : null}
    </div>
  );
}
