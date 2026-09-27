import { cn } from '@/lib/cn';
import { Icon, type IconName } from './Icon';

export interface SegmentOption<K extends string> {
  key: K;
  label: string;
  icon?: IconName;
  /** تنبيهٌ صغير على الخيار — كلغةٍ لم تُترجَم بعد. يُرى نقطةً ويُقرأ نصّاً. */
  note?: string;
}

/**
 * مبدّلٌ بين خياراتٍ متجاورة — T-84.
 *
 * أزرارٌ بـ`aria-pressed` داخل مجموعةٍ مسمّاة، كمبدّل الجهاز الذي سبقه في
 * المعاينة — لا قائمةٌ منسدلة: ثلاثة أجهزة وأربع لغات تُرى كلّها بنظرة،
 * والمنسدلة تُخفي ما لم يُختر.
 */
export function Segmented<K extends string>({
  legend, value, options, onChange,
}: {
  legend: string;
  value: K;
  options: ReadonlyArray<SegmentOption<K>>;
  onChange: (key: K) => void;
}) {
  return (
    <div
      role="group"
      aria-label={legend}
      className="inline-flex flex-wrap items-center gap-1 rounded-lg border border-border bg-surface-alt p-1"
    >
      {options.map((option) => {
        const on = option.key === value;

        return (
          <button
            key={option.key}
            type="button"
            aria-pressed={on}
            title={option.note}
            onClick={() => onChange(option.key)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[13px] whitespace-nowrap transition-colors',
              on ? 'bg-surface font-medium text-primary shadow-card' : 'text-text-muted hover:text-text',
            )}
          >
            {option.icon !== undefined ? <Icon name={option.icon} size={15} /> : null}
            <span>{option.label}</span>
            {option.note !== undefined ? (
              <>
                <span aria-hidden="true" className="size-1.5 rounded-full bg-warning" />
                <span className="sr-only">{option.note}</span>
              </>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
