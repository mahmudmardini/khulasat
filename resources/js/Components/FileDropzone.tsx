import { useRef, useState, type DragEvent } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { Button } from './Button';

interface Props {
  accept: string[];
  maxBytes: number;
  maxLabel: string;
  onSelect: (file: File) => void;
  /**
   * أُزيل المختار — T-85. وكان «أزِل» يُفرغ الحقل وحده، فيبقى الأبُ على
   * الملفّ القديم: معاينةٌ بشعارٍ أُزيل من الحقل.
   */
  onClear?: () => void;
  disabled?: boolean;
  /** اسمُ الحقل — يبلغه التمريرُ إلى أوّل خطأٍ بعد الإرسال. */
  name?: string;
}

/**
 * رفع ملفّ — SCREENS.md الشاشة 3، والمواصفة §5-أ-4-ب.
 *
 * والفحص هنا **راحةٌ لا أمان**: النوع يُتحقَّق منه في الخادم بالمحتوى لا
 * بالامتداد (§12)، وهذا يمنع رحلةً ضائعة إلى الخادم فقط. ومن اعتمد على
 * فحص المتصفّح فقد وثق بما يملكه المهاجم.
 */
export function FileDropzone({ accept, maxBytes, maxLabel, onSelect, onClear, disabled = false, name }: Props) {
  const input = useRef<HTMLInputElement>(null);
  const [over, setOver] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [chosen, setChosen] = useState<File | null>(null);

  function take(file: File | undefined): void {
    if (file === undefined) {
      return;
    }

    if (file.size > maxBytes) {
      setError(t('common.dropzone.too_large', { size: maxLabel }));
      return;
    }

    const extension = `.${file.name.split('.').pop()?.toLowerCase() ?? ''}`;

    if (!accept.includes(extension)) {
      setError(t('common.dropzone.wrong_type', { types: accept.join(' · ') }));
      return;
    }

    setError(null);
    setChosen(file);
    onSelect(file);
  }

  function onDrop(event: DragEvent<HTMLDivElement>): void {
    event.preventDefault();
    setOver(false);

    if (!disabled) {
      take(event.dataTransfer.files[0]);
    }
  }

  return (
    <div>
      <div
        onDragOver={(event) => { event.preventDefault(); setOver(true); }}
        onDragLeave={() => setOver(false)}
        onDrop={onDrop}
        className={cn(
          'flex flex-col items-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors',
          over ? 'border-primary bg-primary/5' : 'border-border-strong bg-surface',
          disabled && 'opacity-45',
        )}
      >
        <p className="text-[15px] text-text-muted">
          {over ? t('common.dropzone.drop_now') : t('common.dropzone.prompt')}
        </p>

        <Button variant="secondary" disabled={disabled} onClick={() => input.current?.click()}>
          {t('common.actions.browse')}
        </Button>

        <p className="text-[13px] text-text-faint">
          {t('common.dropzone.max_size', { size: maxLabel })} · {accept.join(' · ')}
        </p>

        {/*
          الحقل مخفيّ بصرياً لا بـ `display:none`: المخفيّ بالأخيرة يخرج من
          ترتيب لوحة المفاتيح، فلا يبلغه من لا يستعمل الفأرة.
        */}
        <input
          ref={input}
          type="file"
          name={name}
          className="sr-only"
          accept={accept.join(',')}
          disabled={disabled}
          onChange={(event) => take(event.target.files?.[0])}
        />
      </div>

      {chosen !== null ? (
        // فجوةٌ لا هامشٌ: «logo.svgإزالة الملفّ» كانت متلاصقة — T-85.
        <p className="mt-2 flex flex-wrap items-center gap-3 text-[14px] text-text">
          <span dir="ltr" className="wrap-anywhere">{chosen.name}</span>
          <button
            type="button"
            className="text-text-muted underline hover:text-danger"
            onClick={() => { setChosen(null); if (input.current) { input.current.value = ''; } onClear?.(); }}
          >
            {t('common.dropzone.remove')}
          </button>
        </p>
      ) : null}

      {error !== null ? (
        <p role="alert" className="mt-2 text-[13px] text-danger">{error}</p>
      ) : null}
    </div>
  );
}
