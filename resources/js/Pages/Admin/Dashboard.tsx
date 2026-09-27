import { Link } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Card } from '@/Components/Card';
import { EmptyState } from '@/Components/EmptyState';
import { Icon, type IconName } from '@/Components/Icon';
import { AuditList, type AuditRow } from '@/Pages/Admin/AuditList';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface Props {
  counts: {
    tenants: number;
    suspended: number;
    jobs: number;
    needs_review: number;
    failed: number;
    running: number;
    complaints: number;
    overdue: number;
  };
  /** مجموعُ الفتحات، وآخرُ ثلاثين يوماً — T-136. */
  views: { total: number; recent: number };
  audit: AuditRow[];
}

/**
 * صدر لوحة المشرف — T-21.
 *
 * **وما يُبرَز هنا ما يحتاج فعلاً**: المتوقّف أوّلاً فهو عطلٌ عندنا، ثمّ ما
 * ينتظر الجهات فهو وقوفٌ عندهم. والباقي عدٌّ يُطمئن ولا يُطلب منه شيء.
 */
export default function Dashboard({ counts, views, audit }: Props) {
  return (
    <AdminLayout title={t('admin.dashboard.title')} description={t('admin.dashboard.subtitle')}>
      <div className="flex flex-col gap-5">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {/*
            **المتأخّرة عن مهلتها أوّلَ ما يُرى** — T-28. وهي وعدٌ معلن مضى
            وقتُه، وخطرٌ على السمعة قبل أن يكون نقصاً في ميزة.
          */}
          {counts.overdue > 0 ? (
            <Stat
              icon="shield"
              tone="danger"
              value={counts.overdue}
              label={t('admin.dashboard.overdue')}
              href="/admin/takedowns?status=open"
            />
          ) : null}

          <Stat
            icon="alert"
            tone={counts.failed > 0 ? 'danger' : 'plain'}
            value={counts.failed}
            label={t('admin.dashboard.failed')}
            href="/admin/jobs?state=failed"
          />

          <Stat
            icon="shield"
            tone={counts.complaints > 0 ? 'warning' : 'plain'}
            value={counts.complaints}
            label={t('admin.dashboard.complaints')}
            href="/admin/takedowns?status=open"
          />
          <Stat
            icon="clock"
            tone={counts.needs_review > 0 ? 'warning' : 'plain'}
            value={counts.needs_review}
            label={t('admin.dashboard.needs_review')}
            href="/admin/jobs?state=needs_review"
          />
          <Stat
            icon="sparkle"
            tone="plain"
            value={counts.running}
            label={t('admin.dashboard.running')}
            href="/admin/jobs"
          />
          <Stat
            icon="building"
            tone="plain"
            value={counts.tenants}
            label={t('admin.dashboard.tenants')}
            href="/admin/tenants"
          />
          <Stat
            icon="close"
            tone={counts.suspended > 0 ? 'warning' : 'plain'}
            value={counts.suspended}
            label={t('admin.dashboard.suspended')}
            href="/admin/tenants?status=suspended"
          />
          <Stat
            icon="page"
            tone="plain"
            value={counts.jobs}
            label={t('admin.dashboard.jobs')}
            href="/admin/jobs"
          />
          {/*
            المشاهدات — T-136. **وآخرُ ثلاثين يوماً هي المعروضة** لا
            التراكميُّ: «رقمٌ تراكميّ وحده يُخفي صفحةً مات عنها القرّاء منذ
            شهور» (T-31). والتراكميُّ في السطر تحته.
          */}
          <Stat
            icon="eye"
            tone="plain"
            value={views.recent}
            label={t('admin.dashboard.views_recent')}
            href="/admin/jobs?sort=views"
          />
        </div>

        <p className="mt-2 text-[13px] text-text-muted">
          {t('admin.dashboard.views_total', { count: toArabicIndic(views.total) })}
        </p>

        <Card title={t('admin.dashboard.recent')} flush>
          {audit.length === 0 ? (
            <EmptyState
              title={t('admin.dashboard.no_activity')}
              body={t('admin.dashboard.no_activity_body')}
            />
          ) : (
            <AuditList rows={audit} />
          )}
        </Card>
      </div>
    </AdminLayout>
  );
}

const TONES = {
  plain: 'border-border bg-surface',
  warning: 'border-warning/35 bg-warning/8',
  danger: 'border-danger/35 bg-danger/8',
} as const;

const MARKS = {
  plain: 'bg-surface-alt text-text-muted',
  warning: 'bg-warning/15 text-warning',
  danger: 'bg-danger/15 text-danger',
} as const;

function Stat({
  icon, tone, value, label, href,
}: {
  icon: IconName;
  tone: keyof typeof TONES;
  value: number;
  label: string;
  href: string;
}) {
  return (
    <Link
      href={href}
      className={cn(
        'flex items-center gap-3.5 rounded-lg border p-4 shadow-card transition-colors hover:border-border-strong',
        TONES[tone],
      )}
    >
      <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', MARKS[tone])}>
        <Icon name={icon} size={19} />
      </span>

      <span className="min-w-0">
        {/* لاتينية بـ`tabular-nums`: أعدادٌ تُقارن ببعضها في شبكة — §الخطوط. */}
        <span className="block nums-tabular text-[24px] leading-none font-semibold text-text">
          {value}
        </span>
        <span className="mt-1 block truncate text-[13px] text-text-muted">{label}</span>
        <span className="sr-only">{toArabicIndic(value)}</span>
      </span>
    </Link>
  );
}
