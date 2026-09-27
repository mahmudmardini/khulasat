import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export interface Column<Row> {
  key: string;
  header: string;
  /** الأرقام تُحاذى بـ `nums-tabular` — §الخطوط. */
  numeric?: boolean;
  render: (row: Row) => ReactNode;
}

interface Props<Row> {
  columns: Array<Column<Row>>;
  rows: Row[];
  rowKey: (row: Row) => string | number;
  /** يُبرز الصفّ الذي يطلب فعلاً — `needs_review` وحدها. */
  highlight?: (row: Row) => boolean;
  loading?: boolean;
  empty?: ReactNode;
}

export function DataTable<Row>({
  columns, rows, rowKey, highlight, loading = false, empty,
}: Props<Row>) {
  if (loading) {
    return <SkeletonRows columns={columns.length} />;
  }

  if (rows.length === 0) {
    return <>{empty ?? <p className="px-5 py-10 text-center text-text-muted">{t('common.table.empty')}</p>}</>;
  }

  return (
    // الجدول العريض ينزلق داخل حاويته ولا يُمدّد الصفحة أفقياً.
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-start text-[14px]">
        <thead>
          <tr className="border-b border-border bg-surface-alt">
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className="px-4 py-2.5 font-medium whitespace-nowrap text-text-muted"
              >
                {column.header}
              </th>
            ))}
          </tr>
        </thead>

        <tbody>
          {rows.map((row) => (
            <tr
              key={rowKey(row)}
              className={cn(
                'border-b border-border transition-colors last:border-0 hover:bg-surface-alt',
                highlight?.(row) === true && 'bg-warning/5',
              )}
            >
              {columns.map((column) => (
                <td
                  key={column.key}
                  className={cn('px-4 py-3 align-middle', column.numeric === true && 'nums-tabular')}
                >
                  {column.render(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function SkeletonRows({ columns }: { columns: number }) {
  return (
    <div className="p-4" aria-busy="true" aria-label={t('common.table.loading')}>
      {Array.from({ length: 5 }, (_, row) => (
        <div key={row} className="flex gap-4 border-b border-border py-3 last:border-0">
          {Array.from({ length: columns }, (_, cell) => (
            <div key={cell} className="h-4 flex-1 animate-pulse rounded bg-surface-alt" />
          ))}
        </div>
      ))}
    </div>
  );
}
