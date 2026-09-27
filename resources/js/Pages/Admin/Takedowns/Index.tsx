import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { EmptyState } from '@/Components/EmptyState';
import { FieldGroup } from '@/Components/FieldGroup';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

type Status = 'open' | 'unpublished' | 'resolved' | 'dismissed';

interface Row {
  id: number;
  kind: string;
  kind_label: string;
  status: Status;
  url: string;
  contact: string;
  detail: string | null;
  tenant: string | null;
  summary_job_id: number | null;
  target: 'live' | 'removed' | 'unknown';
  received_at: string;
  due_at: string;
  hours_left: number;
  overdue: boolean;
  resolution: string | null;
  resolved_at: string | null;
}

interface Props {
  complaints: { data: Row[]; current_page: number; last_page: number; total: number };
  filters: { status: string | null; kind: string | null };
  counts: { open: number; overdue: number };
  kinds: string[];
  statuses: string[];
}

/**
 * الاعتراضات — SCREENS.md §هـ، والمهمّة T-28.
 *
 * ★ **وترتيبُها بالمهلة لا بالوصول.** فصندوقٌ مرتَّبٌ بالأحدث يدفن شكوى
 * الأمس تحت شكاوى اليوم، **وهي التي بقيت لها ساعتان**. والمهلة تختلف
 * بالنوع: طلبُ إزالةٍ ثمانٍ وأربعون ساعة، وخطأُ تخريجٍ أسبوع.
 *
 * **وليست قائمةً تُقرأ بل صندوقاً يُفرَّغ**: كلّ صفٍّ يُحسم من مكانه بلا
 * انتقالٍ إلى شاشةٍ أخرى، فالحسم فعلُ دقيقةٍ لا رحلة.
 */
export default function Takedowns({ complaints, filters, counts, kinds, statuses }: Props) {
  const apply = (patch: Record<string, string>) => {
    router.get('/admin/takedowns', { ...cleaned(filters), ...patch }, { preserveState: true, replace: true });
  };

  return (
    <AdminLayout title={t('admin.takedowns.title')} description={t('admin.takedowns.subtitle')}>
      <div className="flex flex-col gap-5">
        {/*
          **والمتأخّرة تُصدَّر ولا تُذكر في عدّ جامع.** «سبعٌ مفتوحة» لا
          تقول أنّ فيها اثنتين مضت مهلتُهما، وهاتان هما اللتان يُسأل عنهما.
        */}
        {counts.overdue > 0 ? (
          <div className="flex items-center gap-3 rounded-lg border border-danger/35 bg-danger/8 px-4 py-3">
            <Icon name="alert" size={19} className="shrink-0 text-danger" />
            <p className="text-[15px] font-medium text-text">
              {toArabicIndic(counts.overdue)} {t('admin.dashboard.overdue')}
            </p>
          </div>
        ) : null}

        <div className="flex flex-wrap items-center gap-2">
          <Chip active={filters.status === null} onClick={() => apply({ status: '' })}>
            {t('admin.takedowns.all')}
          </Chip>

          {statuses.map((status) => (
            <Chip key={status} active={filters.status === status} onClick={() => apply({ status })}>
              {t(`admin.takedowns.status.${status}`)}
            </Chip>
          ))}

          <span className="mx-1 h-5 w-px bg-border" />

          {kinds.map((kind) => (
            <Chip
              key={kind}
              active={filters.kind === kind}
              onClick={() => apply({ kind: filters.kind === kind ? '' : kind })}
            >
              {t(`admin.takedowns.kinds.${kind}`)}
            </Chip>
          ))}
        </div>

        {complaints.data.length === 0 ? (
          <Card>
            <EmptyState
              title={t(counts.open > 0 ? 'admin.takedowns.empty_filtered' : 'admin.takedowns.empty')}
              body={t(counts.open > 0 ? 'admin.takedowns.empty_filtered_body' : 'admin.takedowns.empty_body')}
            />
          </Card>
        ) : (
          <ul className="flex flex-col gap-4">
            {complaints.data.map((row) => (
              <li key={row.id}>
                <ComplaintCard row={row} />
              </li>
            ))}
          </ul>
        )}
      </div>
    </AdminLayout>
  );
}

function ComplaintCard({ row }: { row: Row }) {
  const settled = row.status !== 'open';

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-[15px] font-semibold text-text">{row.kind_label}</p>
          <p dir="ltr" className="wrap-anywhere mt-1 text-start text-[13px] text-text-muted">{row.url}</p>
        </div>

        <Deadline row={row} />
      </div>

      <dl className="mt-4 grid gap-3 text-[13px] sm:grid-cols-2">
        <Fact label={t('admin.takedowns.tenant')} value={row.tenant ?? '—'} />
        <Fact label={t('admin.takedowns.contact')} value={row.contact} ltr />
        <Fact label={t('admin.takedowns.received')} value={stamp(row.received_at)} />
        <Fact label={t('admin.takedowns.target')} value={t(`admin.takedowns.target_${row.target}`)} />
      </dl>

      {row.detail !== null && row.detail !== '' ? (
        <div className="mt-4 rounded-md border border-border bg-surface-alt p-3">
          <p className="mb-1 text-[12px] text-text-faint">{t('admin.takedowns.detail')}</p>
          <p className="wrap-anywhere text-[14px] leading-relaxed text-text">{row.detail}</p>
        </div>
      ) : null}

      {settled ? <Settled row={row} /> : <Decide row={row} />}
    </Card>
  );
}

/**
 * المهلة المتبقّية — **مُبرَزةٌ عند قربها ومُصرَّحٌ بها عند مضيّها**.
 *
 * ولا تُحسب في المتصفّح: ساعةُ من يقرأ قد تكون مضبوطةً على غير الحقيقة،
 * ووعدٌ يُقاس بساعة القارئ ليس مقيساً.
 */
function Deadline({ row }: { row: Row }) {
  if (row.status !== 'open') {
    return (
      <span className="rounded border border-border px-2 py-1 text-[12px] text-text-muted">
        {t(`admin.takedowns.status.${row.status}`)}
      </span>
    );
  }

  const over = row.hours_left < 0;
  const near = !over && row.hours_left <= 12;

  return (
    <span
      className={cn(
        'nums-tabular rounded border px-2 py-1 text-[12px] font-medium',
        over
          ? 'border-danger/40 bg-danger/10 text-danger'
          : near
            ? 'border-warning/40 bg-warning/10 text-warning'
            : 'border-border text-text-muted',
      )}
    >
      {toArabicIndic(
        t(over ? 'admin.takedowns.hours_over' : 'admin.takedowns.hours_left', {
          count: Math.abs(row.hours_left),
        }),
      )}
    </span>
  );
}

function Settled({ row }: { row: Row }) {
  return (
    <div className="mt-4 rounded-md border border-border bg-surface-alt p-3">
      <p className="mb-1 text-[12px] text-text-faint">
        {row.resolved_at === null
          ? t('admin.takedowns.resolution')
          : t('admin.takedowns.settled_at', { date: stamp(row.resolved_at) })}
      </p>
      <p className="wrap-anywhere text-[14px] leading-relaxed text-text">{row.resolution ?? '—'}</p>
    </div>
  );
}

const ACTIONS = [
  { status: 'unpublished', variant: 'danger-soft', confirm: true },
  { status: 'resolved', variant: 'primary', confirm: false },
  { status: 'dismissed', variant: 'ghost', confirm: false },
] as const;

function Decide({ row }: { row: Row }) {
  const form = useForm({ status: '', resolution: '' });
  const [confirming, setConfirming] = useState(false);

  const submit = (status: string) => {
    // `transform` تعود بلا قيمة في Inertia 2، فتُضبط ثمّ يُرسل النموذج.
    form.transform((data) => ({ ...data, status }));
    form.put(`/admin/takedowns/${row.id}`, { preserveScroll: true });
  };

  return (
    <div className="mt-4 flex flex-col gap-3 border-t border-border pt-4">
      <FieldGroup
        label={t('admin.takedowns.resolution')}
        hint={t('admin.takedowns.resolution_hint')}
        error={form.errors.resolution ?? form.errors.status}
        required
      >
        <textarea
          rows={2}
          name={`resolution_${row.id}`}
          value={form.data.resolution}
          onChange={(event) => form.setData('resolution', event.target.value)}
          className="field leading-relaxed"
        />
      </FieldGroup>

      <div className="flex flex-wrap items-center gap-2">
        {ACTIONS.map((action) => (
          <Button
            key={action.status}
            variant={action.variant}
            loading={form.processing}
            title={t(`admin.takedowns.do_${short(action.status)}_hint`)}
            onClick={() => (action.confirm ? setConfirming(true) : submit(action.status))}
          >
            {t(`admin.takedowns.do_${short(action.status)}`)}
          </Button>
        ))}
      </div>

      {/*
        **الإزالة وحدها تُؤكَّد.** فهي الفعل الذي لا يُستردّ بضغطة: الملفّات
        تُمسح وتحلّ شاهدةُ ٤١٠ مكانها. وأمّا «عولجت» و«لا إجراء» فقرارٌ
        يُراجَع، وتأكيدُ كلّ فعلٍ يُفقد التأكيدَ معناه.
      */}
      <ConfirmDialog
        open={confirming}
        title={t('admin.takedowns.do_unpublish')}
        consequence={t('admin.takedowns.confirm_unpublish')}
        confirmLabel={t('admin.takedowns.do_unpublish')}
        onCancel={() => setConfirming(false)}
        onConfirm={() => {
          setConfirming(false);
          submit('unpublished');
        }}
      />
    </div>
  );
}

/** `unpublished` ← `unpublish` — مفاتيح الأفعال بصيغة الأمر لا الحال. */
function short(status: string): string {
  return status === 'unpublished' ? 'unpublish' : status === 'resolved' ? 'resolve' : 'dismiss';
}

function Fact({ label, value, ltr = false }: { label: string; value: string; ltr?: boolean }) {
  return (
    <div>
      <dt className="text-[12px] text-text-faint">{label}</dt>
      <dd
        dir={ltr ? 'ltr' : undefined}
        className={cn('wrap-anywhere text-[14px] text-text', ltr && 'text-start')}
      >
        {value}
      </dd>
    </div>
  );
}

function Chip({
  active, onClick, children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        'rounded-full border px-3 py-1 text-[13px] transition-colors',
        active
          ? 'border-primary bg-primary/10 text-primary'
          : 'border-border bg-surface text-text-muted hover:bg-surface-alt',
      )}
    >
      {children}
    </button>
  );
}

function stamp(iso: string): string {
  return new Date(iso).toLocaleString('ar-EG-u-nu-latn', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}

function cleaned(filters: Props['filters']): Record<string, string> {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== null).map(([key, value]) => [key, String(value)]),
  );
}
