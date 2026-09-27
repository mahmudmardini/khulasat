import { useEffect } from 'react';
import { Link, router, usePoll } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { DataTable, type Column } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { StatusBadge, type JobStatus } from '@/Components/StatusBadge';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface JobRow {
  id: number;
  title: string | null;
  speaker: string | null;
  tenant: string | null;
  tenant_id: number;
  state: string;
  status: JobStatus;
  cost_usd: number;
  /** `null` = لم تُنشر بعد، فلا صفحةَ تُقاس — T-136. */
  views: number | null;
  started_at: string | null;
  finished_at: string | null;
}

interface Props {
  jobs: { data: JobRow[]; current_page: number; last_page: number; total: number };
  filters: { state: string | null; tenant: number | null; sort: 'id' | 'views' };
  states: string[];
  tenants: Array<{ id: number; name_ar: string }>;
}

// حالاتٌ لا تتقدّم من نفسها — استطلاعٌ يدور بعدها تبديدُ طلبات. T-22.
const SETTLED_STATES = new Set(['published', 'failed', 'cancelled', 'needs_review']);

/**
 * المهامّ عبر الجهات جميعاً — SCREENS.md §ب، والمهمّة T-21.
 *
 * **والكلفة معروضة هنا بالدولار.** وSCREENS.md تمنع كلمة «توكن» في شاشة
 * العميل لا في شاشة المشغّل، وهذه شاشته: من لا يرى ما يُنفَق لا يُدير منصّة.
 *
 * **وتتحدّث من نفسها** ما دامت مهمّةٌ جاريةً في الصفحة الحالية — T-22،
 * بنفس نمط استطلاع شاشة العميل. **بلا نسبة مئوية**: الحالة والشارة فقط،
 * وفاقاً لقرار {@see JobProgress} «النسبة المخترعة تكذب».
 */
export default function AdminJobsIndex({ jobs, filters, states, tenants }: Props) {
  const running = jobs.data.some((row) => !SETTLED_STATES.has(row.state));
  const poll = usePoll(5_000, { only: ['jobs'] }, { autoStart: false, keepAlive: false });

  useEffect(() => {
    if (running) {
      poll.start();
    } else {
      poll.stop();
    }

    return () => poll.stop();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [running]);

  const columns: Array<Column<JobRow>> = [
    {
      key: 'id',
      header: '#',
      numeric: true,
      render: (row) => (
        <Link href={`/admin/jobs/${row.id}`} className="text-primary hover:underline">
          {row.id}
        </Link>
      ),
    },
    {
      key: 'title',
      header: t('lectures.index.columns.title'),
      render: (row) => (
        <Link
          href={`/admin/jobs/${row.id}`}
          className="font-medium text-text underline-offset-4 hover:text-primary hover:underline"
        >
          {row.title ?? '—'}
        </Link>
      ),
    },
    {
      key: 'tenant',
      header: t('admin.jobs.tenant'),
      render: (row) => (
        <Link href={`/admin/tenants/${row.tenant_id}`} className="text-text-muted hover:text-primary">
          {row.tenant ?? '—'}
        </Link>
      ),
    },
    {
      key: 'status',
      header: t('admin.jobs.state'),
      render: (row) => <StatusBadge status={row.status} />,
    },
    {
      key: 'cost',
      header: t('admin.jobs.cost'),
      numeric: true,
      render: (row) => (row.cost_usd > 0 ? `$${row.cost_usd.toFixed(4)}` : '—'),
    },
    {
      key: 'views',
      header: t('admin.jobs.views'),
      numeric: true,
      /*
        **و`null` تُقرأ شرطةً لا صفراً** — T-136. فمهمّةٌ لم تُنشر لا صفحةَ
        لها تُفتح، وصفرٌ عندها حكمٌ على عملٍ لم يُعرض بعد.
      */
      render: (row) =>
        row.views === null ? (
          <span className="text-text-faint">—</span>
        ) : (
          <span className={cn(row.views === 0 && 'text-text-faint')}>{toArabicIndic(row.views)}</span>
        ),
    },
    {
      key: 'finished',
      header: t('admin.jobs.finished_at'),
      numeric: true,
      render: (row) => <span className="text-text-muted">{row.finished_at ?? '—'}</span>,
    },
  ];

  const apply = (patch: Record<string, string>) =>
    router.get('/admin/jobs', patch, { preserveState: true, replace: true });

  return (
    <AdminLayout title={t('admin.jobs.title')} description={t('admin.jobs.subtitle')}>
      <div>
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <select
            value={filters.state ?? ''}
            aria-label={t('admin.jobs.state')}
            onChange={(event) => apply({ state: event.target.value })}
            className={cn('field w-auto shrink-0 text-[14px]', filters.state !== null && 'border-primary/40 bg-primary/5 font-medium text-primary')}
          >
            <option value="">{t('admin.jobs.state')}</option>
            {states.map((state) => (
              <option key={state} value={state}>{state}</option>
            ))}
          </select>

          <select
            value={filters.tenant ?? ''}
            aria-label={t('admin.jobs.tenant')}
            onChange={(event) => apply({ tenant: event.target.value })}
            className={cn('field w-auto shrink-0 text-[14px]', filters.tenant !== null && 'border-primary/40 bg-primary/5 font-medium text-primary')}
          >
            <option value="">{t('admin.jobs.tenant')}</option>
            {tenants.map((tenant) => (
              <option key={tenant.id} value={tenant.id}>{tenant.name_ar}</option>
            ))}
          </select>

          {/*
            الفرزُ بالمشاهدات — T-136. **ويحفظ التصفيةَ القائمة** بخلاف
            القائمتين أعلاه: فرزٌ يُلغي تصفيةَ جهةٍ يُري المشرف جدولاً آخر
            وهو يظنّ أنّه رتّب جدولَه.
          */}
          <Button
            variant={filters.sort === 'views' ? 'secondary' : 'ghost'}
            onClick={() =>
              router.get(
                '/admin/jobs',
                {
                  ...(filters.state !== null ? { state: filters.state } : {}),
                  ...(filters.tenant !== null ? { tenant: String(filters.tenant) } : {}),
                  ...(filters.sort === 'views' ? {} : { sort: 'views' }),
                },
                { preserveState: true, replace: true },
              )
            }
          >
            {t(filters.sort === 'views' ? 'admin.jobs.sort_newest' : 'admin.jobs.sort_views')}
          </Button>

          {filters.state !== null || filters.tenant !== null ? (
            <Button variant="ghost" onClick={() => router.visit('/admin/jobs')}>
              {t('common.actions.clear_filters')}
            </Button>
          ) : null}

          <span className="ms-auto text-[13px] text-text-muted">
            {toArabicIndic(jobs.total)}
          </span>
        </div>

        <Card flush>
          <DataTable
            columns={columns}
            rows={jobs.data}
            rowKey={(row) => row.id}
            highlight={(row) => row.state === 'failed'}
            empty={<EmptyState title={t('admin.jobs.empty')} body={t('admin.jobs.empty_body')} />}
          />
        </Card>

        {jobs.last_page > 1 ? (
          <nav className="mt-4 flex items-center justify-between gap-3 text-[14px] text-text-muted">
            <span>{toArabicIndic(`${jobs.current_page} ${t('common.table.of')} ${jobs.last_page}`)}</span>
            <span className="flex gap-2">
              <Button
                variant="secondary"
                disabled={jobs.current_page <= 1}
                onClick={() => router.get('/admin/jobs', { page: jobs.current_page - 1 })}
              >
                {t('common.actions.back')}
              </Button>
              <Button
                variant="secondary"
                disabled={jobs.current_page >= jobs.last_page}
                onClick={() => router.get('/admin/jobs', { page: jobs.current_page + 1 })}
              >
                {t('common.actions.next')}
              </Button>
            </span>
          </nav>
        ) : null}
      </div>
    </AdminLayout>
  );
}
