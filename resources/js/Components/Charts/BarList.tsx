interface Item {
  label: string;
  value: number;
  /** يُعرض تحت التسمية — مثل اسم الشريحة أو عدد الملخّصات. */
  hint?: string;
}

interface Props {
  items: Item[];
  /** نصّ القيمة كما يُعرض — `(v) => `$${v.toFixed(4)}`` مثلاً. */
  formatValue: (value: number) => string;
}

/**
 * قائمة أشرطة أفقية بلون واحد — dataviz: «مقياسٌ واحد فلا تلوين تصنيفي».
 *
 * والقيمة تُكتب عند طرف الشريط لا داخله — dataviz «التسمية عند الطرف لا
 * مكدَّسة على المخطّط»، وجدولٌ مخفيٌّ للقارئ الشاشي يرافقها دوماً.
 */
export function BarList({ items, formatValue }: Props) {
  const max = Math.max(1, ...items.map((item) => item.value));

  return (
    <div>
      <div className="flex flex-col gap-2.5" aria-hidden="true">
        {items.map((item) => (
          <div key={item.label} className="flex items-center gap-3">
            <div className="w-28 shrink-0 sm:w-36">
              <div className="truncate text-[13px] text-text">{item.label}</div>
              {item.hint !== undefined ? (
                <div className="truncate text-[12px] text-text-faint">{item.hint}</div>
              ) : null}
            </div>

            <div className="h-5 min-w-0 flex-1 rounded bg-surface-alt">
              <div
                className="h-5 rounded bg-primary"
                style={{ width: `${Math.max(2, (item.value / max) * 100)}%` }}
              />
            </div>

            <div className="w-24 shrink-0 text-end nums-tabular text-[13px] font-medium text-text">
              {formatValue(item.value)}
            </div>
          </div>
        ))}
      </div>

      {/* جدولٌ مكافئ للقارئ الشاشي — dataviz «جدولٌ مكافئ لكل مخطّط». */}
      <table className="sr-only">
        <tbody>
          {items.map((item) => (
            <tr key={item.label}>
              <th scope="row">{item.label}</th>
              <td>{formatValue(item.value)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
