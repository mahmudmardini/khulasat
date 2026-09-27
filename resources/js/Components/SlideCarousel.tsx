import { useState, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface Props {
  slides: ReactNode[];
  label: string;
}

/**
 * معاينة شرائح الكاروسيل — SCREENS.md، ويستهلكه T-19.
 *
 * **الأسهم تنعكس في RTL**: «التالي» يمضي إلى اليسار لا اليمين. وهذا أكثر
 * ما يُنسى في RTL (بند 7 في برومبت T-15ب)، ولذلك السهم هنا يُرسم بـ
 * `scale-x-[-1]` تحت `[dir=rtl]` بدل أن يُكتب سهمان.
 */
export function SlideCarousel({ slides, label }: Props) {
  const [index, setIndex] = useState(0);
  const count = slides.length;

  function move(delta: number): void {
    setIndex((current) => Math.min(Math.max(current + delta, 0), count - 1));
  }

  return (
    <div aria-roledescription="carousel" aria-label={label}>
      <div className="overflow-hidden rounded-lg border border-border bg-surface-alt">
        <div
          aria-live="polite"
          className="flex items-center justify-center p-4"
        >
          {slides[index]}
        </div>
      </div>

      <div className="mt-3 flex items-center justify-between">
        <NavButton
          direction="previous"
          disabled={index === 0}
          onClick={() => move(-1)}
        />

        <p className="nums-tabular text-[14px] text-text-muted">
          {toArabicIndic(index + 1)} {t('common.table.of')} {toArabicIndic(count)}
        </p>

        <NavButton
          direction="next"
          disabled={index >= count - 1}
          onClick={() => move(1)}
        />
      </div>
    </div>
  );
}

function NavButton({
  direction, disabled, onClick,
}: { direction: 'previous' | 'next'; disabled: boolean; onClick: () => void }) {
  const label = direction === 'next' ? t('common.actions.next') : t('common.actions.back');

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      className={cn(
        'rounded border border-border-strong bg-surface p-2 transition-colors',
        'hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-surface',
      )}
    >
      {/*
        المسار يرسم سهماً **إلى اليسار**. وفي RTL يمضي «التالي» يساراً
        فيبقى بلا دوران، ويشير «رجوع» يميناً فيدور ١٨٠.
        وهذا عين ما ينقلب في LTR — والواجهة عربية RTL وحدها في البوّابة
        الأولى (صدر SCREENS.md)، فالاتّجاه مثبَّت لا مشتقّ.
      */}
      <svg
        className={cn('size-4', direction === 'previous' && 'rotate-180')}
        viewBox="0 0 16 16"
        aria-hidden="true"
      >
        <path d="M10 3l-5 5 5 5" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </button>
  );
}
