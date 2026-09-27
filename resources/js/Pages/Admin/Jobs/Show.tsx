import { useEffect, useState } from 'react';
import { Link, router, usePoll } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { Icon } from '@/Components/Icon';
import { ProcessWizard, type WizardStep } from '@/Components/ProcessWizard';
import { StatusBadge, type JobStatus } from '@/Components/StatusBadge';
import { ViewsByLocale, type LocaleViews } from '@/Components/ViewsByLocale';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';

interface StageCost {
  stage: string;
  cost_usd: number;
  provider: string | null;
  model_id: string | null;
  input_tokens: number | null;
  output_tokens: number | null;
}

interface Props {
  job: {
    id: number;
    title: string | null;
    speaker: string | null;
    tenant: string | null;
    tenant_id: number;
    state: string;
    status: JobStatus;
    steps: WizardStep[];
    settled: boolean;
    attempt: number;
    error_code: string | null;
    error_detail: string | null;
    total_cost_usd: number;
    cost_breakdown: Record<string, unknown> | null;
    /** `null` = لم تُنشر بعد، فلا صفحةَ تُقاس — T-136. */
    views: number | null;
    started_at: string | null;
    finished_at: string | null;
  };
  stage_costs: StageCost[];
  /** توزيعُ القراءات على ألسنة الصفحات — T-140. */
  views_by_locale: LocaleViews[];
  transitions: Array<{
    id: number;
    from_state: string | null;
    to_state: string;
    attempt: number;
    error_code: string | null;
    occurred_at: string | null;
  }>;
}

/**
 * مهمّةٌ بعينها في لوحة المشرف — SCREENS.md §ب، والمهمّة T-21.
 *
 * **وهذه شاشة تشخيص لا شاشة متابعة.** فشاشة الجهة (§4) تُخفي رمز الخطأ
 * وتُترجمه، وهذه تعرضه كما هو: من يُصلح العطل يحتاج الرمز لا وصفَه.
 *
 * ★ **والمعالجُ هو معالجُ لوحة الجهة نفسُه** — T-103. وكانت هنا قائمةَ
 * علاماتِ صحّ (`StepTracker`)، و`JobProgress::steps()` يُخرج شكل
 * {@link WizardStep} كاملاً أصلاً — الزمنَ والبدءَ والمتجاوَزة — فلم يكن
 * ينقص المشرفَ إلّا أن يُرسَم له ما يُرسم لغيره. **وبلا نسبة مئوية.**
 */
export default function AdminJobShow({
  job,
  stage_costs: stageCosts,
  views_by_locale: viewsByLocale,
  transitions,
}: Props) {
  const [confirming, setConfirming] = useState<'retry' | 'cancel' | null>(null);

  // تتحدّث من نفسها ما دامت غير مستقرّة — T-22. بلا نسبة مئوية، كما في الفهرس.
  const poll = usePoll(4_000, { only: ['job', 'stage_costs', 'transitions'] }, { autoStart: false, keepAlive: false });
  const now = useNow(!job.settled);

  useEffect(() => {
    if (job.settled) {
      poll.stop();
    } else {
      poll.start();
    }

    return () => poll.stop();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [job.settled]);

  return (
    <AdminLayout
      title={job.title ?? `#${job.id}`}
      description={job.speaker ?? undefined}
      action={<StatusBadge status={job.status} />}
    >
      <div className="flex flex-col gap-5">
        {job.error_code !== null ? (
          <div className="rounded-lg border border-danger/35 bg-danger/8 px-4 py-3.5">
            <div className="flex items-center gap-2">
              <Icon name="alert" size={18} className="shrink-0 text-danger" />
              {/* الرمز كما هو — هذه لوحة المشغّل، وهو ما يُبحث به. */}
              <code dir="ltr" className="text-[14px] font-semibold text-danger">
                {job.error_code}
              </code>
            </div>
            {job.error_detail !== null ? (
              <p className="mt-1.5 text-[13px] text-text-muted">{job.error_detail}</p>
            ) : null}
          </div>
        ) : null}

        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
          <div className="flex flex-col gap-5">
            <ProcessWizard steps={job.steps} now={now} />

            <StageCosts rows={stageCosts} />

            <Card title={t('admin.jobs.transitions')} flush>
              <div className="overflow-x-auto">
                <table className="w-full border-collapse text-right text-[13px]">
                  <thead>
                    <tr className="border-b border-border bg-surface-alt text-text-muted">
                      <th scope="col" className="px-4 py-2.5 font-medium">{t('admin.jobs.from_state')}</th>
                      <th scope="col" className="px-4 py-2.5 font-medium">{t('admin.jobs.to_state')}</th>
                      <th scope="col" className="px-4 py-2.5 font-medium">{t('admin.jobs.attempt')}</th>
                      <th scope="col" className="px-4 py-2.5 font-medium">{t('admin.jobs.error_code')}</th>
                      <th scope="col" className="px-4 py-2.5 font-medium">{t('admin.audit.title')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {/*
                      ★ **ولا عمود كلفةٍ هنا** — T-103. كان يُعرض «—» في كلّ
                      صفّ لأنّ `summary_job_transitions.cost_usd` لا كاتبَ له
                      في الكود كلِّه. والكلفةُ الحقيقية في بطاقة المراحل فوق.
                    */}
                    {transitions.map((row) => (
                      <tr
                        key={row.id}
                        className={cn(
                          'border-b border-border last:border-0',
                          row.to_state === 'failed' && 'bg-danger/5',
                        )}
                      >
                        <td dir="ltr" className="px-4 py-2.5 text-start text-text-muted">{row.from_state ?? '—'}</td>
                        <td dir="ltr" className="px-4 py-2.5 text-start font-medium text-text">{row.to_state}</td>
                        <td className="px-4 py-2.5 nums-tabular">{row.attempt}</td>
                        <td dir="ltr" className="px-4 py-2.5 text-start text-danger">{row.error_code ?? '—'}</td>
                        <td className="px-4 py-2.5 nums-tabular text-text-faint">{row.occurred_at ?? '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          </div>

          <div className="flex flex-col gap-5">
            <Card title={t('admin.jobs.cost')}>
              <p className="nums-tabular text-[24px] font-semibold text-text">
                ${job.total_cost_usd.toFixed(4)}
              </p>

              <dl className="mt-4 flex flex-col gap-2 text-[13px]">
                <Fact label={t('admin.jobs.tenant')} value={job.tenant ?? '—'} href={`/admin/tenants/${job.tenant_id}`} />
                <Fact label={t('admin.jobs.attempt')} value={String(job.attempt)} />
                <Fact label={t('admin.jobs.started_at')} value={job.started_at ?? '—'} />
                <Fact label={t('admin.jobs.finished_at')} value={job.finished_at ?? '—'} />
              </dl>
            </Card>

            {/*
              ★ **القراءاتُ بجانب الكلفة لا في شاشةٍ أخرى — T-140.**

              فالسؤالُ في لوحة المشغّل واحد: **أيستحقّ ما أُنفق ما قُرئ؟**
              وكلفةٌ في بطاقةٍ وقراءاتٌ في شاشةٍ تُفتح تُجعلان السؤالَ عملاً
              لا نظرة.

              **و`null` تُقرأ شرطةً لا صفراً**: مهمّةٌ لم تُنشر لا صفحةَ لها
              تُفتح، وصفرٌ عندها حكمٌ على عملٍ لم يُعرض بعد.
            */}
            <Card title={t('admin.jobs.views')}>
              <p className="nums-tabular text-[24px] font-semibold text-text">
                {job.views === null ? (
                  <span className="text-text-faint">—</span>
                ) : (
                  toArabicIndic(job.views)
                )}
              </p>

              {viewsByLocale.length > 1 ? (
                <ViewsByLocale rows={viewsByLocale} className="mt-4 border-t border-border pt-4" />
              ) : null}
            </Card>

            <Card title={t('common.actions.retry')}>
              <div className="flex flex-col gap-3">
                <div>
                  <Button
                    disabled={job.state !== 'failed'}
                    onClick={() => setConfirming('retry')}
                    block
                  >
                    {t('admin.jobs.retry')}
                  </Button>
                  <p className="mt-1.5 text-[12px] text-text-muted">{t('admin.jobs.retry_hint')}</p>
                </div>

                <div className="border-t border-border pt-3">
                  <Button
                    variant="danger-soft"
                    disabled={job.settled}
                    onClick={() => setConfirming('cancel')}
                    block
                  >
                    {t('admin.jobs.cancel')}
                  </Button>
                  <p className="mt-1.5 text-[12px] text-text-muted">{t('admin.jobs.cancel_hint')}</p>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={confirming !== null}
        title={t(confirming === 'cancel' ? 'admin.jobs.cancel' : 'admin.jobs.retry')}
        consequence={t(confirming === 'cancel' ? 'admin.jobs.cancel_hint' : 'admin.jobs.retry_hint')}
        onConfirm={() => {
          router.post(`/admin/jobs/${job.id}/${confirming}`);
          setConfirming(null);
        }}
        onCancel={() => setConfirming(null)}
      />
    </AdminLayout>
  );
}

/**
 * كلفةُ كلّ مرحلة — T-103، وهي أوّلُ ما يُسأل عنه في لوحة المشغّل.
 *
 * **والمرحلةُ باسمها الخام والنموذجُ باسمه**: هذه شاشته لا شاشة العميل،
 * و«لا تظهر كلمة توكن» قيدٌ على تلك وحدها.
 */
function StageCosts({ rows }: { rows: StageCost[] }) {
  if (rows.length === 0) {
    return (
      <Card title={t('admin.jobs.stage_costs')}>
        <p className="text-[13px] text-text-muted">{t('admin.jobs.no_cost')}</p>
      </Card>
    );
  }

  const max = Math.max(...rows.map((row) => row.cost_usd), 0.0001);

  return (
    <Card
      title={t('admin.jobs.stage_costs')}
      footer={<p className="text-[12px] text-text-muted">{t('admin.jobs.stage_costs_hint')}</p>}
    >
      <ul className="flex flex-col gap-3">
        {rows.map((row) => (
          <li key={row.stage} className="flex items-center gap-3">
            <div className="w-28 shrink-0 sm:w-40">
              <div dir="ltr" className="truncate text-start text-[13px] text-text">{row.stage}</div>
              <div dir="ltr" className="truncate text-start text-[12px] text-text-faint">
                {row.model_id ?? t('admin.jobs.no_model_recorded')}
              </div>
            </div>

            <div className="h-4 min-w-0 flex-1 rounded bg-surface-alt">
              <div
                className="h-4 rounded bg-primary"
                style={{ width: `${Math.max(2, (row.cost_usd / max) * 100)}%` }}
              />
            </div>

            <div className="w-24 shrink-0 text-end">
              <div className="nums-tabular text-[13px] font-medium text-text">
                ${row.cost_usd.toFixed(4)}
              </div>
              {row.input_tokens !== null && row.output_tokens !== null ? (
                <div dir="ltr" className="nums-tabular text-end text-[11.5px] text-text-faint">
                  {row.input_tokens} → {row.output_tokens}
                </div>
              ) : null}
            </div>
          </li>
        ))}
      </ul>
    </Card>
  );
}

/**
 * ساعةٌ تدقّ ما دامت المهمّة تجري — لعدّاد «منذ …» في المعالج.
 *
 * ونظيرتُها في شاشة الجهة خاصّةٌ بها: سطورٌ قليلة لا تُستخرج في مكوّنٍ
 * مشترك يربط شاشتين لا تتبدّلان معاً.
 */
function useNow(ticking: boolean): number {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!ticking) {
      return undefined;
    }

    const id = window.setInterval(() => setNow(Date.now()), 1_000);

    return () => window.clearInterval(id);
  }, [ticking]);

  return now;
}

function Fact({ label, value, href }: { label: string; value: string; href?: string }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="shrink-0 text-text-faint">{label}</dt>
      <dd className="truncate nums-tabular text-text">
        {href === undefined ? value : (
          <Link href={href} className="text-primary hover:underline">{value}</Link>
        )}
      </dd>
    </div>
  );
}
