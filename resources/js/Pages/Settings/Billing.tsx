import { AppLayout } from '@/Layouts/AppLayout';
import { Card } from '@/Components/Card';
import { DataTable, type Column } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { QuotaBar } from '@/Components/QuotaBar';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface Limits {
  monthly_quota: number;
  daily_cap: number;
  max_lecture_minutes: number;
  transcription_minutes_quota: number;
  regenerations_per_summary: number;
}

interface LedgerRow {
  id: number;
  event: 'generate' | 'regenerate' | 'transcribe';
  units: number;
  unit: 'summaries' | 'minutes';
  title: string | null;
  occurred_at: string;
}

interface Props {
  plan: { key: string; name: string; rich_outputs: boolean; customised: boolean };
  limits: Limits;
  usage: {
    summaries: { used: number; limit: number };
    today: { used: number; limit: number };
    transcription: { used: number; limit: number };
  };
  suspended: boolean;
  ledger: LedgerRow[];
}

/** الحدود بالترتيب الذي تُقرأ به: الأعمّ ثمّ الأخصّ. */
const LIMIT_FIELDS: Array<keyof Limits> = [
  'monthly_quota',
  'daily_cap',
  'max_lecture_minutes',
  'transcription_minutes_quota',
  'regenerations_per_summary',
];

/** ما يُقاس بالدقائق دون الملخّصات — فالوحدة تُكتب ولا تُترك للتخمين. */
const IN_MINUTES: Array<keyof Limits> = ['max_lecture_minutes', 'transcription_minutes_quota'];

/**
 * الاشتراك والاستهلاك — SCREENS.md الشاشة 9، والمهمّة T-23.
 *
 * ★ **بالملخّصات لا بالتوكنز.** «ولا تُذكر التوكنز ولا النماذج في أيّ شاشة
 * يراها مدير المحتوى» — والوحدة المحاسبية المعروضة هي الملخّص ودقيقة
 * التفريغ.
 *
 * **ولا زرّ شراءٍ ولا فواتير**: لا بوّابة دفع في هذه المرحلة، والترقية
 * مراجعةٌ لنا. وزرٌّ يَعِد بما ليس فيه أسوأ من غيابه.
 */
export default function Billing({ plan, limits, usage, suspended, ledger }: Props) {
  return (
    <AppLayout title={t('billing.page.title')} description={t('billing.page.subtitle')}>
      <div className="flex flex-col gap-5">
        {suspended ? (
          <div className="flex items-start gap-3 rounded-lg border border-warning/35 bg-warning/8 px-4 py-4">
            <Icon name="alert" size={19} className="mt-0.5 shrink-0 text-warning" />
            <div>
              <p className="text-[15px] font-medium text-text">{t('billing.suspended.title')}</p>
              <p className="mt-0.5 text-[13px] text-text-muted">{t('billing.suspended.body')}</p>
            </div>
          </div>
        ) : null}

        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div className="flex flex-col gap-5">
            <Card title={t('billing.page.usage')}>
              <div className="flex flex-col gap-5">
                <Meter label={t('billing.page.monthly_quota')} {...usage.summaries} />
                <Meter label={t('billing.page.daily_cap')} {...usage.today} />
                <Meter label={t('billing.page.transcription_minutes_quota')} {...usage.transcription} />
              </div>
            </Card>

            <Card title={t('billing.page.ledger')} flush>
              {ledger.length === 0 ? (
                <EmptyState
                  title={t('billing.page.ledger_empty')}
                  body={t('billing.page.ledger_empty_body')}
                />
              ) : (
                <Ledger rows={ledger} />
              )}
            </Card>
          </div>

          <div className="flex flex-col gap-5">
            <Card title={t('billing.page.plan_card')}>
              <p className="text-[19px] font-semibold text-text">{plan.name}</p>

              {/*
                **من رُفع له حدٌّ استثناءً يُقال له.** فرقمُ الشريحة ليس
                رقمَه، وشاشةٌ تعرض اسم الشريحة وحدها تُفاجئه عند التجديد.
              */}
              {plan.customised ? (
                <p className="mt-2 rounded-md border border-accent/30 bg-accent/6 px-3 py-2 text-[13px] text-text">
                  <span className="font-medium">{t('billing.page.customised')}</span>
                  {' — '}
                  {t('billing.page.customised_hint')}
                </p>
              ) : null}

              <p
                className={cn(
                  'mt-3 flex items-center gap-2 text-[13px]',
                  plan.rich_outputs ? 'text-success' : 'text-text-faint',
                )}
              >
                <Icon name={plan.rich_outputs ? 'check' : 'carousel'} size={15} className="shrink-0" />
                {t('billing.page.rich_outputs')}
                {' — '}
                {t(plan.rich_outputs ? 'billing.page.rich_outputs_on' : 'billing.page.rich_outputs_off')}
              </p>
            </Card>

            <Card title={t('billing.page.limits')}>
              <dl className="flex flex-col gap-2.5 text-[13px]">
                {LIMIT_FIELDS.map((field) => (
                  <div key={field} className="flex items-baseline justify-between gap-3">
                    <dt className="text-text-muted">{t(`billing.page.${field}`)}</dt>
                    <dd className="nums-tabular font-medium text-text">
                      {limits[field] === 0
                        ? t('billing.quota.unlimited')
                        : IN_MINUTES.includes(field)
                          ? toArabicIndic(t('billing.page.minutes', { count: limits[field] }))
                          : toArabicIndic(limits[field])}
                    </dd>
                  </div>
                ))}
              </dl>
            </Card>

            <Card title={t('billing.page.change')}>
              <p className="text-[13px] leading-relaxed text-text-muted">
                {t('billing.page.change_body')}
              </p>
            </Card>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}

/**
 * سطرُ حدٍّ واحد: اسمُه وشريطُه.
 *
 * **والرقم في الشريط وحده.** وكان مكرّراً فوقه، فيقرأ المستخدم «بلا حدّ»
 * مرّتين في سطرين متتاليين — وتكرارٌ كهذا يُقرأ خطأً في العرض لا تأكيداً.
 */
function Meter({ label, used, limit }: { label: string; used: number; limit: number }) {
  return (
    <div>
      <p className="mb-2 text-[14px] text-text">{label}</p>
      <QuotaBar used={used} limit={limit} />
    </div>
  );
}

function Ledger({ rows }: { rows: LedgerRow[] }) {
  const columns: Array<Column<LedgerRow>> = [
    {
      key: 'occurred_at',
      header: t('billing.page.ledger_when'),
      numeric: true,
      render: (row) => new Date(row.occurred_at).toLocaleDateString('ar-EG-u-nu-latn'),
    },
    {
      key: 'event',
      header: t('billing.page.ledger_what'),
      render: (row) => t(`billing.page.events.${row.event}`),
    },
    {
      key: 'title',
      header: t('billing.page.ledger_summary'),
      render: (row) => row.title ?? '—',
    },
    {
      key: 'units',
      header: t('billing.page.ledger_amount'),
      numeric: true,
      render: (row) => toArabicIndic(t(`billing.page.${row.unit}`, { count: row.units })),
    },
  ];

  return <DataTable columns={columns} rows={rows} rowKey={(row) => row.id} />;
}
