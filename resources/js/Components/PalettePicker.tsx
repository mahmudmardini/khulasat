import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export interface Palette {
  key: string;
  name: string;
  swatches: string[];
}

interface Props {
  palettes: Palette[];
  value: string;
  onChange: (key: string) => void;
  /**
   * `chips` رقاقاتٌ مدمجة بثلاث دوائر لونية — T-95. فاللوحةُ في شاشة الإنشاء
   * خيارٌ ثانويّ، وستُّ بطاقاتٍ بأشرطةٍ عريضة تشغل منها ما يشغله المصدر.
   */
  variant?: 'cards' | 'chips';
}

/**
 * لوحات الجهة — SCREENS.md الشاشة 8، وT-14 البند الرابع.
 *
 * **بطاقات مرئية، ولا حقول HEX من المستخدم.** فاللوحات ستٌّ مضبوطة يُختار
 * منها، ومن فتح حقل لونٍ حرّ فتح باب تباينٍ لا يُصلَح ونصٍّ لا يُقرأ.
 *
 * والتنقّل بالأسهم داخل المجموعة كما تقتضي `radiogroup`، لا بـ Tab بين ستّ.
 */
export function PalettePicker({ palettes, value, onChange, variant = 'cards' }: Props) {
  if (variant === 'chips') {
    return (
      <div role="radiogroup" aria-label={t('common.palette.legend')} className="flex flex-wrap gap-2">
        {palettes.map((palette) => {
          const selected = palette.key === value;

          return (
            <button
              key={palette.key}
              type="button"
              role="radio"
              aria-checked={selected}
              tabIndex={selected ? 0 : -1}
              onClick={() => onChange(palette.key)}
              className={cn(
                'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[13.5px] transition-colors',
                selected
                  ? 'border-primary bg-primary/5 font-medium text-primary ring-1 ring-primary'
                  : 'border-border bg-surface text-text hover:border-border-strong',
              )}
            >
              <span className="flex" aria-hidden="true">
                {palette.swatches.map((color, index) => (
                  <span
                    key={color}
                    className={cn('size-4 rounded-full ring-2 ring-surface', index > 0 && '-ms-1.5')}
                    style={{ backgroundColor: color }}
                  />
                ))}
              </span>

              <span>{palette.name}</span>

              {selected ? <span className="sr-only">{t('common.palette.selected')}</span> : null}
            </button>
          );
        })}
      </div>
    );
  }

  return (
    <div role="radiogroup" aria-label={t('common.palette.legend')} className="grid gap-3 sm:grid-cols-3">
      {palettes.map((palette) => {
        const selected = palette.key === value;

        return (
          <button
            key={palette.key}
            type="button"
            role="radio"
            aria-checked={selected}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(palette.key)}
            className={cn(
              'flex flex-col gap-2 rounded-lg border p-3 text-start transition-colors',
              selected
                ? 'border-primary ring-1 ring-primary'
                : 'border-border hover:border-border-strong',
            )}
          >
            <span className="flex gap-1" aria-hidden="true">
              {palette.swatches.map((color) => (
                <span
                  key={color}
                  className="h-6 flex-1 rounded"
                  style={{ backgroundColor: color }}
                />
              ))}
            </span>

            <span className="text-[14px] text-text">{palette.name}</span>

            {selected ? (
              <span className="sr-only">{t('common.palette.selected')}</span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
