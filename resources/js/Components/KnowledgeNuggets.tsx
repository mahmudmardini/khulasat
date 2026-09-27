import { useEffect, useState } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { useReducedMotion } from '@/lib/motion';
import { toArabicIndic } from '@/lib/numerals';
import { Icon } from './Icon';

export interface Nugget {
  kind: string;
  text: string;
  detail: string | null;
}

/** سبع ثوانٍ لكلّ ملمح: تكفي لقراءة سطرين وتفصيلٍ تحتهما. */
const ROTATE_MS = 7000;

/**
 * ملامح الدرس — T-83.
 *
 * من بنية الدرس كما استخرجتها المرحلة الثانية: الفكرةُ الجامعة والتشخيصُ
 * والمحاور. **لا اقتباسٌ مصوغٌ للتسلية**، ولا نصُّ آيةٍ أو حديثٍ لم يُتحقَّق
 * منه — {@see App\Support\Ui\LiveFeed} في الخادم.
 *
 * **والتبديل الآليّ يُوقَف**: بزرٍّ ظاهر (إتاحة: ما يتبدّل من نفسه فوق خمس
 * ثوانٍ يحتاج إيقافاً)، وبالتحويم والتركيز، ولا يبدأ أصلاً لمن طلب تقليل
 * الحركة.
 */
export function KnowledgeNuggets({ items }: { items: Nugget[] }) {
  const reduced = useReducedMotion();
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const [holding, setHolding] = useState(false);

  const count = items.length;
  const position = count === 0 ? 0 : index % count;

  useEffect(() => {
    if (count < 2 || paused || holding || reduced) {
      return undefined;
    }

    const id = window.setInterval(() => setIndex((value) => value + 1), ROTATE_MS);
    return () => window.clearInterval(id);
  }, [count, paused, holding, reduced]);

  const item = count > 0 ? items[position] : null;

  return (
    <section
      className="flex h-full flex-col rounded-lg border border-border bg-surface shadow-card"
      onMouseEnter={() => setHolding(true)}
      onMouseLeave={() => setHolding(false)}
      onFocus={() => setHolding(true)}
      onBlur={() => setHolding(false)}
    >
      <header className="border-b border-border px-5 py-3">
        <h2 className="text-[16px] font-semibold text-text">{t('jobs.live.nuggets_title')}</h2>
        <p className="text-[12.5px] text-text-faint">{t('jobs.live.nuggets_hint')}</p>
      </header>

      <div className="flex min-h-[176px] flex-1 flex-col justify-center px-5 py-4">
        {item === null ? (
          <div className="flex flex-col gap-3">
            <span aria-hidden="true" className="skeleton block h-5 w-20" />
            <span aria-hidden="true" className="skeleton block h-4 w-[88%]" />
            <p className="mt-1 text-[13px] text-text-faint">{t('jobs.live.nuggets_waiting')}</p>
          </div>
        ) : (
          <div key={position} className="animate-rise" aria-live="polite">
            <span className="inline-block rounded-full border border-accent/35 bg-accent/8 px-2.5 py-0.5 text-[12px] font-medium text-accent">
              {t(`jobs.live.nugget_kinds.${item.kind}`)}
            </span>
            <p className="mt-3 text-[18px] leading-relaxed font-semibold text-text">{item.text}</p>
            {item.detail !== null ? (
              <p className="mt-1.5 text-[14px] leading-relaxed text-text-muted">{item.detail}</p>
            ) : null}
          </div>
        )}
      </div>

      {count > 1 ? (
        <footer className="flex items-center justify-between gap-3 border-t border-border px-3 py-2">
          <span aria-hidden="true" className="flex items-center gap-1.5 ps-2">
            {items.map((nugget, dot) => (
              <span
                key={`${nugget.kind}-${dot}`}
                className={cn(
                  'h-1.5 rounded-full transition-all',
                  dot === position ? 'w-4 bg-primary' : 'w-1.5 bg-border-strong',
                )}
              />
            ))}
          </span>

          <span className="flex items-center gap-1">
            <span className="nums-tabular me-1 text-[12.5px] text-text-faint">
              {toArabicIndic(t('jobs.live.nugget_position', { current: position + 1, total: count }))}
            </span>

            <button
              type="button"
              aria-label={t('jobs.live.previous')}
              onClick={() => setIndex((value) => (value - 1 + count) % count)}
              className="rounded-md p-1.5 text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
            >
              <Icon name="chevron" size={16} />
            </button>

            <button
              type="button"
              aria-label={t(paused ? 'jobs.live.resume' : 'jobs.live.pause')}
              aria-pressed={paused}
              onClick={() => setPaused((value) => !value)}
              className="rounded-md p-1.5 text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
            >
              <Icon name={paused ? 'play' : 'pause'} size={16} />
            </button>

            <button
              type="button"
              aria-label={t('jobs.live.next')}
              onClick={() => setIndex((value) => (value + 1) % count)}
              className="rounded-md p-1.5 text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
            >
              {/* «التالي» في RTL إلى اليسار. */}
              <Icon name="chevron" size={16} className="rotate-180" />
            </button>
          </span>
        </footer>
      ) : null}
    </section>
  );
}
