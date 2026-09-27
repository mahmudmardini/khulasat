import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export interface AuditRow {
  id: number;
  action: string;
  sensitive?: boolean;
  admin_name: string;
  subject_label?: string | null;
  changes?: Record<string, { from: unknown; to: unknown }>;
  note?: string | null;
  occurred_at: string | null;
}

/**
 * سجلّ التدقيق معروضاً — T-21.
 *
 * **ويُقرأ كجملة لا كصفٍّ في جدول**: «فلانٌ رفع حصّة جهة الاختبار من ٣ إلى
 * ٤٠، لأنّ حوالة TR-8891 وصلت». وجدولٌ بأعمدةٍ يُفكّك الجملة فيصير كلّ
 * عمودٍ صحيحاً والمعنى ضائعاً بينها.
 *
 * وهو في `Pages/` لا `Components/` عمداً: خاصٌّ بلوحة المشرف، ولا يُبنى
 * عليه في لوحة الجهة.
 */
export function AuditList({ rows }: { rows: AuditRow[] }) {
  return (
    <ul className="divide-y divide-border">
      {rows.map((row) => (
        <li key={row.id} className="flex gap-3 px-5 py-3.5">
          <span
            className={cn(
              'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md',
              row.sensitive === true
                ? 'bg-warning/12 text-warning'
                : 'bg-surface-alt text-text-faint',
            )}
          >
            <Icon name={row.sensitive === true ? 'shield' : 'check'} size={15} />
          </span>

          <div className="min-w-0 flex-1">
            <p className="text-[14px] text-text">
              {t(`admin.audit.actions.${row.action}`)}
              {row.subject_label ? (
                <span className="font-medium"> — {row.subject_label}</span>
              ) : null}
            </p>

            {row.changes !== undefined && Object.keys(row.changes).length > 0 ? (
              <ul className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                {Object.entries(row.changes).map(([field, change]) => (
                  <li key={field} className="text-[12px] text-text-muted">
                    <span className="text-text-faint">{field}</span>{' '}
                    {/*
                      **من ثمّ إلى** — والسهم في RTL يُقرأ من اليمين، فتُكتب
                      القيمة القديمة أوّلاً ثمّ السهم ثمّ الجديدة، ويبقى
                      المعنى مستقيماً بلا عكسٍ للرمز.
                    */}
                    <span className="nums-tabular">{render(change.from)}</span>
                    <span className="mx-1 text-text-faint" aria-hidden="true">←</span>
                    <span className="nums-tabular font-medium text-text">{render(change.to)}</span>
                  </li>
                ))}
              </ul>
            ) : null}

            {row.note ? (
              <p className="mt-1.5 border-s-2 border-border ps-2.5 text-[12px] text-text-muted">
                {row.note}
              </p>
            ) : null}
          </div>

          <div className="shrink-0 text-end">
            <p className="text-[12px] text-text-muted">{row.admin_name}</p>
            <p className="mt-0.5 nums-tabular text-[11px] text-text-faint">{row.occurred_at ?? '—'}</p>
          </div>
        </li>
      ))}
    </ul>
  );
}

/** القيمة الغائبة تُكتب شرطةً لا «null» — والسجلّ يُقرأ بشراً. */
function render(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  return typeof value === 'boolean' ? String(value) : String(value);
}
