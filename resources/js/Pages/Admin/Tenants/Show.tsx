import { router, useForm, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { EmptyState } from '@/Components/EmptyState';
import { FieldGroup } from '@/Components/FieldGroup';
import { Icon } from '@/Components/Icon';
import { QuotaBar } from '@/Components/QuotaBar';
import { UnverifiedPolicyChoice, type UnverifiedPolicy } from '@/Components/UnverifiedPolicyChoice';
import { ViewsByLocale, type LocaleViews } from '@/Components/ViewsByLocale';
import { AuditList, type AuditRow } from '@/Pages/Admin/AuditList';
import { useState } from 'react';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

type Limits = {
  monthly_quota: number;
  daily_cap: number;
  max_lecture_minutes: number;
  transcription_minutes_quota: number;
  regenerations_per_summary: number;
};

interface PlanOption {
  key: string;
  name: string;
  limits: Limits;
  rich_outputs: boolean;
  matches: boolean;
}

interface Props {
  tenant: {
    id: number;
    name_ar: string;
    slug: string;
    plan: string;
    plan_label: string;
    status: string;
    on_unverified: UnverifiedPolicy;
    created_at: string | null;
    limits: Limits;
  };
  usage: {
    used: number;
    limit: number;
    cost_usd: number;
    views: number;
    views_recent: number;
    /** توزيعُ قراءات هذه الجهة على ألسنة صفحاتها — T-140. */
    views_by_locale: LocaleViews[];
  };
  plans: PlanOption[];
  owners: Array<{ id: number; name: string; email: string }>;
  audit: AuditRow[];
}

const LIMIT_FIELDS: Array<keyof Limits> = [
  'monthly_quota',
  'daily_cap',
  'max_lecture_minutes',
  'transcription_minutes_quota',
  'regenerations_per_summary',
];

/**
 * جهةٌ بعينها — SCREENS.md §أ، والمهمّة T-21.
 *
 * **وتعديل الحدود هنا حدثٌ ماليّ لا ضبطُ إعداد.** لا بوّابة دفع في هذه
 * المرحلة، فالجهة تحوّل إلى الحساب البنكي ثمّ تُرفع حدودها من هذه الشاشة —
 * وسببُ التغيير هو الرابط الوحيد بين المال المستلَم والحصّة المرفوعة.
 */
export default function TenantShow({ tenant, usage, plans, owners, audit }: Props) {
  const flash = usePage<{ props: { temporary_password?: string } }>().props as unknown as {
    temporary_password?: string;
  };

  return (
    <AdminLayout title={tenant.name_ar} description={tenant.slug}>
      <div className="flex flex-col gap-5">
        {typeof flash.temporary_password === 'string' ? (
          <TemporaryPassword password={flash.temporary_password} />
        ) : null}

        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div className="flex flex-col gap-5">
            <PlanForm tenant={tenant} plans={plans} />
            <LimitsForm tenant={tenant} />
            <VerificationForm tenant={tenant} />
            <StatusForm tenant={tenant} />

            <Card title={t('admin.audit.title')} flush>
              {audit.length === 0 ? (
                <EmptyState title={t('admin.audit.empty')} body={t('admin.dashboard.no_activity_body')} />
              ) : (
                <AuditList rows={audit} />
              )}
            </Card>
          </div>

          <div className="flex flex-col gap-5">
            <Card title={t('admin.tenants.usage')}>
              <QuotaBar used={usage.used} limit={usage.limit} />
              <dl className="mt-4 flex flex-col gap-2 text-[13px]">
                <Fact label={t('admin.tenants.plan')} value={tenant.plan_label} />
                {/* كلفةُ شهرها — T-103. ومن يرفع حدّاً يراها وهو يرفعه. */}
                <Fact label={t('admin.tenants.cost_month')} value={`$${usage.cost_usd.toFixed(4)}`} />
                {/*
                  المشاهدات — T-136. **وهي الوجهُ الآخر للكلفة في القرار
                  نفسه**: جهةٌ تنشر ولا يُفتح لها شيء حالُها غيرُ حالِ
                  جهةٍ تُقرأ، ومن يرفع حدّاً فليرَ الاثنين.
                */}
                <Fact
                  label={t('admin.tenants.views')}
                  value={toArabicIndic(`${usage.views_recent} / ${usage.views}`)}
                />
                <Fact label={t('admin.tenants.created_at')} value={tenant.created_at ?? '—'} />
              </dl>

              {/*
                ★ **وتوزيعُ القراءات على الألسنة — T-140.**

                فرقمٌ جامعٌ يقول «تُقرأ»، والتوزيعُ يقول **بأيّ لسان** —
                وجهةٌ تنشر بثلاث لغاتٍ وتُقرأ بواحدةٍ تُنصَح بغير ما تُنصَح
                به جهةٌ تُقرأ بالثلاث.
              */}
              {usage.views_by_locale.length > 1 ? (
                <ViewsByLocale rows={usage.views_by_locale} className="mt-4 border-t border-border pt-4" />
              ) : null}
            </Card>

            <Card title={t('admin.tenants.owners')}>
              <ul className="flex flex-col gap-2.5">
                {owners.map((owner) => (
                  <li key={owner.id}>
                    <p className="text-[14px] text-text">{owner.name}</p>
                    <p dir="ltr" className="text-start text-[12px] text-text-muted">{owner.email}</p>
                  </li>
                ))}
              </ul>

              <Impersonate tenant={tenant} disabled={owners.length === 0} />
            </Card>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}

/**
 * كلمة المرور المؤقّتة — تُعرض مرّةً ولا تُرسل بريداً.
 *
 * فالبريد غير مضبوط في هذه المرحلة، ووعدُ «دعوةٍ» لا تصل أسوأ من قول
 * الحقّ: يأخذها المشرف ويُسلّمها بيده. **ولا تُقيَّد في السجلّ** — سجلٌّ
 * يحمل كلمات المرور يصير هو الخطر الذي يحرس منه.
 */
function TemporaryPassword({ password }: { password: string }) {
  return (
    <div className="rounded-lg border border-accent/40 bg-accent/8 p-4">
      <div className="flex items-center gap-2">
        <Icon name="shield" size={18} className="shrink-0 text-accent" />
        <h2 className="text-[15px] font-semibold text-text">
          {t('admin.tenants.temporary_password')}
        </h2>
      </div>

      <p className="mt-1 text-[13px] text-text-muted">
        {t('admin.tenants.temporary_password_hint')}
      </p>

      <p
        dir="ltr"
        className="mt-3 rounded-md border border-border bg-surface px-3 py-2 text-start font-mono text-[16px] select-all"
      >
        {password}
      </p>
    </div>
  );
}

/**
 * الشريحة — T-23.
 *
 * **وهي نقطةُ بداية لا قفل**: تملأ الحدود الخمسة دفعةً واحدة، ثمّ يُعدَّل
 * منها ما شئتَ من النموذج الذي تحتها. ولذلك تُعرض حدودُ كلّ شريحة قبل
 * الضغط: **يُرى ما سيتغيّر قبل أن يتغيّر**، لا بعده.
 */
function PlanForm({ tenant, plans }: { tenant: Props['tenant']; plans: PlanOption[] }) {
  const current = plans.find((plan) => plan.key === tenant.plan);
  const form = useForm({ plan: tenant.plan, note: '' });

  return (
    <Card title={t('admin.plans.legend')}>
      <p className="text-[13px] text-text-muted">{t('admin.plans.intro')}</p>

      {/*
        الجهةُ على شريحةٍ وحدودُها ليست حدودَها — حالةٌ مقصودة لا خطأ،
        وإخفاؤها يجعل المشرف يظنّ أنّ الضغط على «طبّق» بلا أثر.
      */}
      {current !== undefined && !current.matches ? (
        <p className="mt-3 rounded-md border border-warning/35 bg-warning/8 px-3 py-2 text-[13px] text-text">
          <span className="font-medium">{t('admin.plans.customised')}</span>
          {' — '}
          {t('admin.plans.customised_hint')}
        </p>
      ) : null}

      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.put(`/admin/tenants/${tenant.id}/plan`, {
            preserveScroll: true,
            onSuccess: () => form.setData('note', ''),
          });
        }}
        className="mt-4 flex flex-col gap-4"
      >
        <ul className="grid gap-3 sm:grid-cols-2">
          {plans.map((plan) => (
            <li key={plan.key}>
              <label
                className={cn(
                  'flex cursor-pointer flex-col gap-1.5 rounded-lg border p-3 transition-colors',
                  form.data.plan === plan.key
                    ? 'border-primary bg-primary/6'
                    : 'border-border bg-surface-alt hover:border-border-strong',
                )}
              >
                <span className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="plan"
                    value={plan.key}
                    checked={form.data.plan === plan.key}
                    onChange={() => form.setData('plan', plan.key)}
                    className="accent-primary"
                  />
                  <span className="text-[15px] font-semibold text-text">{plan.name}</span>
                  {plan.key === tenant.plan ? (
                    <span className="rounded border border-border-strong px-1.5 py-0.5 text-[11px] text-text-muted">
                      {t('admin.plans.current')}
                    </span>
                  ) : null}
                </span>

                <span className="nums-tabular text-[12px] leading-relaxed text-text-muted">
                  {LIMIT_FIELDS.map((field) => `${t(`admin.limits.${field}`)}: ${plan.limits[field]}`).join(' · ')}
                </span>

                {plan.rich_outputs ? (
                  <span className="text-[12px] text-accent">{t('admin.plans.rich_outputs')}</span>
                ) : null}
              </label>
            </li>
          ))}
        </ul>

        <FieldGroup
          label={t('admin.plans.note')}
          hint={t('admin.plans.note_hint')}
          error={form.errors.note}
        >
          <input
            name="plan_note"
            autoComplete="off"
            value={form.data.note}
            onChange={(event) => form.setData('note', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <div>
          <Button type="submit" loading={form.processing}>{t('admin.plans.apply')}</Button>
        </div>
      </form>
    </Card>
  );
}

function LimitsForm({ tenant }: { tenant: Props['tenant'] }) {
  const form = useForm({ ...tenant.limits, note: '' });

  return (
    <Card title={t('admin.limits.legend')}>
      <p className="mb-4 rounded-md border border-accent/30 bg-accent/6 px-3 py-2 text-[13px] text-text">
        {t('admin.limits.intro')}
      </p>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.put(`/admin/tenants/${tenant.id}/limits`, {
            preserveScroll: true,
            onSuccess: () => form.setData('note', ''),
          });
        }}
        className="flex flex-col gap-4"
      >
        <div className="grid gap-4 sm:grid-cols-2">
          {LIMIT_FIELDS.map((field) => (
            <FieldGroup
              key={field}
              label={t(`admin.limits.${field}`)}
              hint={field === 'monthly_quota' ? t('admin.limits.unlimited_hint') : undefined}
              error={form.errors[field]}
              required
            >
              <input
                type="number"
                name={field}
                min={0}
                value={form.data[field]}
                onChange={(event) => form.setData(field, Number(event.target.value))}
                className="field nums-tabular"
              />
            </FieldGroup>
          ))}
        </div>

        <FieldGroup
          label={t('admin.limits.note')}
          hint={t('admin.limits.note_hint')}
          error={form.errors.note}
        >
          <input
            name="note"
            autoComplete="off"
            value={form.data.note}
            onChange={(event) => form.setData('note', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <div>
          <Button type="submit" loading={form.processing}>{t('common.actions.save')}</Button>
        </div>
      </form>
    </Card>
  );
}

/**
 * وضعُ البيان — T-163. **ويُبدَّل بسببٍ مكتوب** كتعليق الجهة: هو ما يقرّر
 * أيمرّ الحديث الضعيف مبيَّناً بلا إنسان أم يقف.
 */
function VerificationForm({ tenant }: { tenant: Props['tenant'] }) {
  const form = useForm({ on_unverified: tenant.on_unverified, note: '' });

  return (
    <Card title={t('admin.verification.legend')}>
      <p className="text-[13px] text-text-muted">{t('admin.verification.hint')}</p>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          form.put(`/admin/tenants/${tenant.id}/verification`, {
            preserveScroll: true,
            onSuccess: () => form.setData('note', ''),
          });
        }}
        className="mt-4 flex flex-col gap-4"
      >
        <UnverifiedPolicyChoice
          name="on_unverified"
          value={form.data.on_unverified}
          current={tenant.on_unverified}
          onChange={(policy) => form.setData('on_unverified', policy)}
        />

        <FieldGroup label={t('admin.limits.note')} error={form.errors.note}>
          <input
            name="verification_note"
            autoComplete="off"
            value={form.data.note}
            onChange={(event) => form.setData('note', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <div>
          <Button type="submit" loading={form.processing} disabled={form.data.on_unverified === tenant.on_unverified}>
            {t('admin.verification.apply')}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function StatusForm({ tenant }: { tenant: Props['tenant'] }) {
  const suspended = tenant.status === 'suspended';
  const form = useForm({ status: suspended ? 'active' : 'suspended', note: '' });
  const [confirming, setConfirming] = useState(false);

  return (
    <Card title={t('admin.status.legend')}>
      <p className="text-[13px] text-text-muted">{t('admin.status.suspend_hint')}</p>

      <div className="mt-4 flex flex-col gap-4">
        <FieldGroup label={t('admin.limits.note')} error={form.errors.note}>
          <input
            name="status_note"
            autoComplete="off"
            value={form.data.note}
            onChange={(event) => form.setData('note', event.target.value)}
            className="field"
          />
        </FieldGroup>

        <div>
          <Button
            variant={suspended ? 'primary' : 'danger-soft'}
            loading={form.processing}
            onClick={() => setConfirming(true)}
          >
            {t(suspended ? 'admin.status.resume' : 'admin.status.suspend')}
          </Button>
        </div>
      </div>

      <ConfirmDialog
        open={confirming}
        title={t(suspended ? 'admin.status.resume' : 'admin.status.suspend')}
        consequence={t('admin.status.suspend_hint')}
        onConfirm={() => {
          form.put(`/admin/tenants/${tenant.id}/status`, {
            preserveScroll: true,
            onFinish: () => setConfirming(false),
          });
        }}
        onCancel={() => setConfirming(false)}
      />
    </Card>
  );
}

/** «صلاحية خطيرة تُراقَب لا تُمنع» — فالتحذير قبلها، والتقييد بعدها. */
function Impersonate({ tenant, disabled }: { tenant: Props['tenant']; disabled: boolean }) {
  const [confirming, setConfirming] = useState(false);

  return (
    <div className="mt-4 border-t border-border pt-4">
      <Button
        variant="secondary"
        disabled={disabled}
        onClick={() => setConfirming(true)}
        block
      >
        <Icon name="eye" size={16} />
        {t('admin.impersonate.action')}
      </Button>

      <p className="mt-2 text-[12px] text-text-muted">{t('admin.impersonate.hint')}</p>

      <ConfirmDialog
        open={confirming}
        title={t('admin.impersonate.action')}
        consequence={t('admin.impersonate.hint')}
        onConfirm={() => {
          router.post(`/admin/tenants/${tenant.id}/impersonate`);
          setConfirming(false);
        }}
        onCancel={() => setConfirming(false)}
      />
    </div>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-text-faint">{label}</dt>
      <dd className="nums-tabular text-text">{value}</dd>
    </div>
  );
}
