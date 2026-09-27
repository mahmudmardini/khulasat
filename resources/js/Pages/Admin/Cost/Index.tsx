import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { BarList } from '@/Components/Charts/BarList';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { DataTable, type Column } from '@/Components/DataTable';
import { EmptyState } from '@/Components/EmptyState';
import { FieldGroup } from '@/Components/FieldGroup';
import { Icon, type IconName } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

interface TenantRow {
  tenant_id: number;
  name: string;
  plan: string;
  cost_usd: number;
  price_usd: number | null;
  margin_usd: number | null;
}

interface Props {
  today_cost_usd: number;
  month_cost_usd: number;
  avg_cost_per_summary_usd: number;
  spend_cap: {
    daily_usd: number;
    monthly_usd: number;
    halted: boolean;
    halt_reason: string | null;
  };
  by_stage: Array<{ stage: string; cost_usd: number }>;
  by_model: Array<{ provider: string; model_id: string; cost_usd: number }>;
  by_tenant: TenantRow[];
  by_video_length: Array<{ bucket: string; cost_usd: number; count: number }>;
  monthly_trend: Array<{ month: string; cost_usd: number }>;
  failure_rate_last_hour: number;
}

const money = (value: number): string => `$${value.toFixed(4)}`;

/**
 * شاشة الكلفة — T-22. رُفع تأجيلها بقرار مالك المنتج (11 أيلول 2026) بعد أن
 * صار `MODEL_GATEWAY=real` مُشغَّلاً فعلاً.
 *
 * **ونبرتها كنبرة بقية لوحة المشرف**: بالدولار، وبالنموذج باسمه، بلا تلطيف
 * — {@see \App\Http\Controllers\Admin\DashboardController}.
 */
export default function CostIndex({
  today_cost_usd: todayCost,
  month_cost_usd: monthCost,
  avg_cost_per_summary_usd: avgCost,
  spend_cap: spendCap,
  by_stage: byStage,
  by_model: byModel,
  by_tenant: byTenant,
  by_video_length: byVideoLength,
  monthly_trend: monthlyTrend,
  failure_rate_last_hour: failureRate,
}: Props) {
  const hasAnyData = monthCost > 0 || todayCost > 0;

  const tenantColumns: Array<Column<TenantRow>> = [
    { key: 'name', header: t('admin.cost.tenant'), render: (row) => row.name },
    {
      key: 'plan',
      header: t('admin.cost.plan'),
      render: (row) => <span className="text-text-muted">{row.plan}</span>,
    },
    {
      key: 'cost',
      header: t('admin.cost.cost'),
      numeric: true,
      render: (row) => money(row.cost_usd),
    },
    {
      key: 'price',
      header: t('admin.cost.price'),
      numeric: true,
      render: (row) => (row.price_usd === null ? '—' : `$${row.price_usd.toFixed(2)}`),
    },
    {
      key: 'margin',
      header: t('admin.cost.margin'),
      numeric: true,
      render: (row) =>
        row.margin_usd === null ? (
          <span className="text-text-faint">{t('admin.cost.margin_unknown')}</span>
        ) : (
          <span className={row.margin_usd < 0 ? 'font-medium text-danger' : 'text-success'}>
            {money(row.margin_usd)}
          </span>
        ),
    },
  ];

  return (
    <AdminLayout title={t('admin.cost.title')} description={t('admin.cost.subtitle')}>
      {/*
        بطاقةُ سقف الإنفاق تظهر ولو لم تُصرف كلفةٌ بعد — T-151. فوقفٌ
        يدويٌّ (كوقفٍ للاختبار، كما وقع فعلاً) يترك `hasAnyData` زائفاً،
        ومن يفتح هذه الشاشة يبحث عن زرّ الرفع، لا عن بطاقات الكلفة.
      */}
      <div className="flex flex-col gap-5">
        <SpendCapCard spendCap={spendCap} todayCost={todayCost} monthCost={monthCost} />

        {!hasAnyData ? (
          <Card>
            <EmptyState title={t('admin.cost.empty')} body={t('admin.cost.empty_body')} />
          </Card>
        ) : (
          <div className="flex flex-col gap-5">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <Stat icon="money" value={money(todayCost)} label={t('admin.cost.today')} />
              <Stat icon="money" value={money(monthCost)} label={t('admin.cost.month')} />
              <Stat icon="sparkle" value={money(avgCost)} label={t('admin.cost.avg_per_summary')} />
              <Stat
                icon="alert"
                tone={failureRate > 0.1 ? 'danger' : 'plain'}
                value={`${(failureRate * 100).toFixed(1)}%`}
                label={t('admin.cost.failure_rate')}
              />
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
              <Card title={t('admin.cost.by_stage')}>
                {byStage.length === 0 ? (
                  <p className="text-[13px] text-text-muted">{t('admin.cost.empty')}</p>
                ) : (
                  <BarList
                    items={byStage.map((row) => ({ label: row.stage, value: row.cost_usd }))}
                    formatValue={money}
                  />
                )}
              </Card>

              <Card title={t('admin.cost.by_model')}>
                {/*
                  ★ **فراغُ هذه البطاقة يُفسَّر ولا يُترك** — T-103. فتفصيل
                  النموذج والتوكنز لا مصدرَ له إلّا `model_calls`، وهو جدولٌ
                  وُلد مع T-22: خلوُّه يعني «لم يُقيَّد بعد» لا «لا كلفة».
                  والكلفةُ نفسها كاملةٌ في بطاقة المراحل إلى جانبها.
                */}
                {byModel.length === 0 ? (
                  <div>
                    <p className="text-[13px] font-medium text-text">{t('admin.cost.by_model_empty')}</p>
                    <p className="mt-1 text-[12.5px] leading-relaxed text-text-muted">
                      {t('admin.cost.by_model_empty_body')}
                    </p>
                  </div>
                ) : (
                  <BarList
                    items={byModel.map((row) => ({
                      label: row.model_id,
                      hint: row.provider,
                      value: row.cost_usd,
                    }))}
                    formatValue={money}
                  />
                )}
              </Card>

              <Card title={t('admin.cost.by_video_length')}>
                {byVideoLength.length === 0 ? (
                  <p className="text-[13px] text-text-muted">{t('admin.cost.empty')}</p>
                ) : (
                  <BarList
                    items={byVideoLength.map((row) => ({
                      label: row.bucket,
                      hint: t('admin.cost.summaries_count', { count: row.count }),
                      value: row.cost_usd,
                    }))}
                    formatValue={money}
                  />
                )}
              </Card>

              <Card title={t('admin.cost.monthly_trend')}>
                <BarList
                  items={monthlyTrend.map((row) => ({ label: row.month, value: row.cost_usd }))}
                  formatValue={money}
                />
              </Card>
            </div>

            <Card title={t('admin.cost.by_tenant')} footer={<p className="text-[12px] text-text-muted">{t('admin.cost.note')}</p>} flush>
              <DataTable
                columns={tenantColumns}
                rows={byTenant}
                rowKey={(row) => row.tenant_id}
                empty={<EmptyState title={t('admin.cost.empty')} body={t('admin.cost.empty_body')} />}
              />
            </Card>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}

const TONES = {
  plain: 'border-border bg-surface',
  danger: 'border-danger/35 bg-danger/8',
} as const;

const MARKS = {
  plain: 'bg-surface-alt text-text-muted',
  danger: 'bg-danger/15 text-danger',
} as const;

function Stat({
  icon, value, label, tone = 'plain',
}: {
  icon: IconName;
  value: string;
  label: string;
  tone?: keyof typeof TONES;
}) {
  return (
    <div className={cn('flex items-center gap-3.5 rounded-lg border p-4 shadow-card', TONES[tone])}>
      <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg', MARKS[tone])}>
        <Icon name={icon} size={19} />
      </span>

      <span className="min-w-0">
        <span className="block nums-tabular text-[22px] leading-none font-semibold text-text">
          {value}
        </span>
        <span className="mt-1 block truncate text-[13px] text-text-muted">{label}</span>
      </span>
    </div>
  );
}

function SpendCapCard({
  spendCap,
  todayCost,
  monthCost,
}: {
  spendCap: Props['spend_cap'];
  todayCost: number;
  monthCost: number;
}) {
  if (spendCap.daily_usd <= 0 && spendCap.monthly_usd <= 0) {
    return (
      <Card title={t('admin.cost.spend_cap')}>
        <p className="text-[13px] text-text-muted">{t('admin.cost.spend_cap_none')}</p>
        <SpendCapControl halted={spendCap.halted} />
      </Card>
    );
  }

  return (
    <Card title={t('admin.cost.spend_cap')}>
      {spendCap.halted ? (
        <div className="mb-3 flex items-center gap-2 rounded-md border border-danger/35 bg-danger/8 px-3 py-2.5 text-[13px] text-danger">
          <Icon name="alert" size={16} className="shrink-0" />
          {t('admin.cost.spend_cap_halted', { reason: spendCap.halt_reason ?? '—' })}
        </div>
      ) : null}

      <div className="flex flex-col gap-2 text-[13px] text-text-muted">
        {spendCap.daily_usd > 0 ? (
          <CapMeter
            label={t('admin.cost.spend_cap_daily', { spent: todayCost.toFixed(2), cap: spendCap.daily_usd.toFixed(2) })}
            ratio={todayCost / spendCap.daily_usd}
          />
        ) : null}

        {spendCap.monthly_usd > 0 ? (
          <CapMeter
            label={t('admin.cost.spend_cap_monthly', { spent: monthCost.toFixed(2), cap: spendCap.monthly_usd.toFixed(2) })}
            ratio={monthCost / spendCap.monthly_usd}
          />
        ) : null}
      </div>

      <SpendCapControl halted={spendCap.halted} />
    </Card>
  );
}

/**
 * التحكّم اليدويّ بالوقف — T-151.
 *
 * **وسببٌ مكتوبٌ إلزاميّ كبقية الأفعال المالية في هذه اللوحة** — تعليقُ
 * جهة يطلبه، وتعديلُ حدودها يطلبه، ووقفٌ يمنع الطابور كلّه أولى.
 */
function SpendCapControl({ halted }: { halted: boolean }) {
  const form = useForm({ halted: !halted, note: '' });
  const [confirming, setConfirming] = useState(false);

  return (
    <div className="mt-4 flex flex-col gap-3 border-t border-border pt-4">
      <FieldGroup label={t('admin.cost.spend_cap_note')} error={form.errors.note}>
        <input
          name="spend_cap_note"
          autoComplete="off"
          value={form.data.note}
          onChange={(event) => form.setData('note', event.target.value)}
          className="field"
        />
      </FieldGroup>
      <p className="text-[12px] text-text-muted">{t('admin.cost.spend_cap_note_hint')}</p>

      <div>
        <Button
          variant={halted ? 'primary' : 'danger-soft'}
          loading={form.processing}
          onClick={() => setConfirming(true)}
        >
          {t(halted ? 'admin.cost.spend_cap_release' : 'admin.cost.spend_cap_halt')}
        </Button>
      </div>

      <ConfirmDialog
        open={confirming}
        title={t(halted ? 'admin.cost.spend_cap_release' : 'admin.cost.spend_cap_halt')}
        consequence={t(halted ? 'admin.cost.spend_cap_confirm_release' : 'admin.cost.spend_cap_confirm_halt')}
        onConfirm={() => {
          form.put('/admin/spend-cap', {
            preserveScroll: true,
            onSuccess: () => form.setData('note', ''),
            onFinish: () => setConfirming(false),
          });
        }}
        onCancel={() => setConfirming(false)}
      />
    </div>
  );
}

/** الامتلاء لونٌ من نفس السلّم — dataviz «العتبة أخضر ثم أصفر ثم أحمر». */
function CapMeter({ label, ratio }: { label: string; ratio: number }) {
  const pct = Math.min(100, Math.max(0, ratio * 100));
  const tone = ratio >= 1 ? 'bg-danger' : ratio >= 0.8 ? 'bg-warning' : 'bg-success';

  return (
    <div>
      <p className="mb-1">{label}</p>
      <div className="h-2 w-full overflow-hidden rounded-full bg-surface-alt">
        <div className={cn('h-2 rounded-full', tone)} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}
