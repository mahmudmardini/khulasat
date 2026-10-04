import { cn } from '@/lib/cn';
import { when } from '@/lib/quizReport';

/**
 * رسومُ تقارير الاختبارات — T-201. **بلا مكتبة رسوم**: أعمدةٌ بـCSS بلونٍ
 * واحد (مقياسٌ واحد، فلا تلوينَ تصنيفيّ)، وجدولٌ مكافئ للقارئ الشاشي.
 */

/** رقمٌ بارز بتسميته — أرقامُ رأس التقرير. */
export function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-lg border border-border bg-surface px-4 py-3.5">
      <div className="text-[13px] text-text-muted">{label}</div>
      <div className="nums-tabular mt-1 text-[24px] font-semibold leading-tight text-text">{value}</div>
      {hint !== undefined ? <div className="mt-0.5 text-[12px] text-text-faint">{hint}</div> : null}
    </div>
  );
}

/** أعمدةٌ رأسية: يومٌ لكلّ عمود، أو درجةٌ لكلّ عمود. */
export function Columns({
  items, label, caption, highlightLast = false,
}: {
  items: ReadonlyArray<{ key: string; value: number; tick: string }>;
  label: (key: string) => string;
  caption: string;
  highlightLast?: boolean;
}) {
  const max = Math.max(1, ...items.map((item) => item.value));

  return (
    <div>
      <div className="flex h-36 items-end gap-[3px]" aria-hidden="true">
        {items.map((item, index) => (
          <div key={item.key} className="flex h-full min-w-0 flex-1 flex-col justify-end" title={`${label(item.key)}: ${item.value}`}>
            <div
              className={cn(
                'w-full rounded-t-sm',
                item.value === 0 ? 'bg-border' : highlightLast && index === items.length - 1 ? 'bg-primary' : 'bg-primary/80',
              )}
              style={{ height: item.value === 0 ? '2px' : `${Math.max(4, (item.value / max) * 100)}%` }}
            />
          </div>
        ))}
      </div>
      <div className="mt-1.5 flex gap-[3px] text-[11px] text-text-faint" aria-hidden="true">
        {items.map((item) => (
          <span key={item.key} className="nums-tabular min-w-0 flex-1 overflow-visible whitespace-nowrap text-center">{item.tick}</span>
        ))}
      </div>

      <table className="sr-only">
        <caption>{caption}</caption>
        <tbody>
          {items.map((item) => (
            <tr key={item.key}>
              <th scope="row">{label(item.key)}</th>
              <td>{item.value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** المحاولاتُ في اليوم — آخر ثلاثين يوماً، والتسميةُ كلَّ أسبوع. */
export function DailyColumns({ days, caption }: { days: ReadonlyArray<{ date: string; count: number }>; caption: string }) {
  return (
    <Columns
      caption={caption}
      highlightLast
      label={(key) => when(key)}
      items={days.map((day, index) => ({
        key: day.date,
        value: day.count,
        // تسميةٌ كلَّ أسبوعٍ عدّاً من اليوم، بصيغة يوم/شهر — فلا تتراكب تسميتان.
        tick: (days.length - 1 - index) % 7 === 0 ? `${Number(day.date.slice(8))}/${Number(day.date.slice(5, 7))}` : '',
      }))}
    />
  );
}
