import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { ErrorState } from '@/Components/ErrorState';
import { Icon, type IconName } from '@/Components/Icon';
import { KnowledgeNuggets, type Nugget } from '@/Components/KnowledgeNuggets';
import { NotifyToggle } from '@/Components/NotifyToggle';
import { ProcessWizard, type WizardStep } from '@/Components/ProcessWizard';
import { StatusBadge, type JobStatus } from '@/Components/StatusBadge';
import { cn } from '@/lib/cn';
import { durationLabel } from '@/lib/duration';
import { t } from '@/lib/i18n';
import { useCompletionAlert } from '@/lib/notify';
import { plural } from '@/lib/plural';

interface Live {
  highlights: Nugget[];
  word_count: number | null;
  evidence_count: number | null;
  locales: string[];
}

interface Job {
  id: number;
  title: string;
  speaker: string;
  state: string;
  status: JobStatus;
  steps: WizardStep[];
  settled: boolean;
  needs_review: boolean;
  pending_evidence: number;
  started_at: string | null;
  finished_at: string | null;
  error: string | null;
  /** أيصلح تبديلُ المصدر هذا الخطأ فعلاً؟ — T-91. */
  error_offers_change_source: boolean;
  /** أُزيلت الصفحة من النشر، ونصّ ما يُبلَّغ به صاحبها — T-28. */
  takedown: { reason: string; at: string } | null;
  /** ما أُنتج حتى الآن — T-83. */
  live: Live;
}

/** كلّ ثلاث ثوانٍ — SCREENS.md §4. */
const POLL_MS = 3000;

/**
 * متابعة الإعداد — SCREENS.md §4، وأُعيد تصميمها في T-27، وصارت حيّةً في
 * T-83، ومعالجاً في T-92.
 *
 * **بلا نسب مئوية.** «النسبة المخترعة تكذب، والمستخدم يكتشف كذبها.» فالمعروض
 * مراحلُ مسمّاة بحالها وزمنها، من آلة الحالات وسجلّ انتقالاتها لا مقدَّرة.
 *
 * ★ **وما تغيّر في T-92 — ملاحظات مالك المنتج على T-83:**
 * - **«من نصّ الدرس» أُزيلت.** ترجماتُ يوتيوب بلا ترقيمٍ ولا همزات تُقرأ
 *   ضجيجاً، و«ملامح الدرس» تقول ما يُفهم منه.
 * - **المراحل الثماني ظاهرةٌ لا مطويّة**، معالجاً بأيقوناتٍ وأوصافٍ وأزمنة.
 * - **الحالةُ النهائية بالمعالج نفسه**، ومعه حصيلةٌ بأرقامها — لا قائمةٌ
 *   مجرّدة بعلامات صحّ.
 */
export default function Show({ job: initial }: { job: Job }) {
  const [job, setJob] = useState(initial);
  const [confirmingCancel, setConfirmingCancel] = useState(false);
  const alert = useCompletionAlert(`job-${initial.id}`);

  const running = !job.settled && !job.needs_review;
  const now = useNow(running);

  useEffect(() => setJob(initial), [initial]);

  // **والاستطلاع يقف عند الحالة النهائية.** ولو تُرك يدور بعد النشر لظلّ
  // يضرب الخادم من كلّ تبويب مفتوح نُسي، بلا شيء يتغيّر.
  useEffect(() => {
    if (!running) {
      return undefined;
    }

    const timer = window.setInterval(async () => {
      try {
        const response = await fetch(`/panel/jobs/${job.id}/status`, {
          headers: { Accept: 'application/json' },
        });

        if (response.ok) {
          const body = (await response.json()) as { job: Job };
          setJob(body.job);
        }
      } catch {
        // انقطاعُ شبكةٍ لحظيّ لا يُفرَغ على المستخدم: المحاولة التالية بعد
        // ثلاث ثوانٍ، والصفحة تبقى على آخر حالٍ معلوم.
      }
    }, POLL_MS);

    return () => window.clearInterval(timer);
  }, [job.id, running]);

  /*
   * **النبأ يقع عند الانتقال من «جارٍ» إلى غيره، لا عند فتح صفحةٍ انتهت.**
   * ومن ألغى بنفسه لا يُنبَّه إلى ما فعله للتوّ.
   */
  const wasRunning = useRef(running);

  useEffect(() => {
    if (wasRunning.current && !running && job.state !== 'cancelled') {
      const outcome = job.needs_review ? 'review' : job.state === 'published' ? 'published' : 'failed';

      alert.fire(
        t(`jobs.live.notify.${outcome}`, { title: job.title }),
        t(`jobs.live.notify.${outcome}_body`),
        t(`jobs.live.notify.marker_${outcome}`),
      );
    }

    wasRunning.current = running;
  }, [running]); // eslint-disable-line react-hooks/exhaustive-deps

  const elapsed = elapsedSeconds(job, now);

  return (
    <AppLayout title={job.title} description={job.speaker} action={<StatusBadge status={job.status} />}>
      <div className="flex flex-col gap-5">
        <Outcome
          job={job}
          onCancelClick={() => setConfirmingCancel(true)}
          notify={
            <NotifyToggle
              enabled={alert.enabled}
              permission={alert.permission}
              onEnable={() => void alert.enable()}
              onDisable={alert.disable}
            />
          }
        />

        {/*
          المعالجُ أوسعُ العمودين وأوّلُهما — هو ما جاء له من فتح الصفحة:
          أين وصل الإعداد. والجانبيّ ما أُنتج: ملامحُ الدرس والحصيلة.
        */}
        <div className="grid gap-5 lg:grid-cols-5 lg:items-start">
          <div className="lg:col-span-3">
            <ProcessWizard
              steps={job.steps}
              now={now}
              aside={
                elapsed !== null ? (
                  <span className="inline-flex items-center gap-1.5 rounded-full bg-surface-alt px-3 py-1 text-[13px] text-text-muted">
                    <Icon name="clock" size={15} />
                    {t(job.settled ? 'jobs.live.wizard.took' : 'jobs.live.elapsed', { time: durationLabel(elapsed) })}
                  </span>
                ) : null
              }
            />
          </div>

          <aside className="flex flex-col gap-5 lg:sticky lg:top-0 lg:col-span-2">
            {running ? <KnowledgeNuggets items={job.live.highlights} /> : null}
            <Tally job={job} elapsed={elapsed} />
          </aside>
        </div>
      </div>

      <ConfirmDialog
        open={confirmingCancel}
        title={t('jobs.follow.cancel')}
        consequence={t('jobs.follow.cancel_hint')}
        onConfirm={() => {
          setConfirmingCancel(false);
          router.post(`/panel/jobs/${job.id}/cancel`);
        }}
        onCancel={() => setConfirmingCancel(false)}
      />
    </AppLayout>
  );
}

/**
 * الحصيلة — T-92: ما أُنتج بأرقامه، **والرقمُ لا يُقال قبل أن يُعرف**.
 *
 * فعددُ الشواهد قبل استخراجها «يُعرف بعد استخراج الشواهد» لا «صفر» — الصفر
 * يُقرأ «لا شواهد في الدرس»، وهو خبرٌ لم يقع.
 */
function Tally({ job, elapsed }: { job: Job; elapsed: number | null }) {
  const { live } = job;

  const rows: Array<{ icon: IconName; label: string; value: string; detail?: string; known: boolean }> = [
    {
      icon: 'clock',
      label: t('jobs.live.wizard.tally.time'),
      value: elapsed === null ? t('jobs.live.wizard.tally.not_started') : durationLabel(elapsed),
      detail:
        job.started_at !== null
          ? t('jobs.live.wizard.tally.span', {
            from: clock(job.started_at),
            to: job.finished_at !== null ? clock(job.finished_at) : t('jobs.live.wizard.tally.now'),
          })
          : undefined,
      known: elapsed !== null,
    },
    {
      icon: 'text',
      label: t('jobs.live.wizard.tally.words'),
      value: live.word_count !== null ? plural('jobs.live.words', live.word_count) : t('jobs.live.wizard.tally.words_pending'),
      known: live.word_count !== null,
    },
    {
      icon: 'quote',
      label: t('jobs.live.wizard.tally.evidence'),
      value:
        live.evidence_count === null
          ? t('jobs.live.wizard.tally.evidence_pending')
          : live.evidence_count === 0
            ? t('jobs.live.evidence_none')
            : plural('jobs.live.evidence', live.evidence_count),
      known: live.evidence_count !== null,
    },
    {
      icon: 'globe',
      label: t('jobs.live.wizard.tally.locales'),
      value: live.locales.join('، '),
      known: live.locales.length > 0,
    },
  ];

  return (
    <section className="rounded-lg border border-border bg-surface shadow-card">
      <header className="border-b border-border px-5 py-3">
        <h2 className="text-[16px] font-semibold text-text">
          {t(job.settled ? 'jobs.live.wizard.tally.done' : 'jobs.live.wizard.tally.running')}
        </h2>
      </header>

      <dl className="flex flex-col divide-y divide-border">
        {rows.map((row) => (
          <div key={row.label} className="flex items-start gap-3 px-5 py-3">
            <span
              aria-hidden="true"
              className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-lg',
                row.known ? 'bg-primary/8 text-primary' : 'bg-surface-alt text-text-faint',
              )}
            >
              <Icon name={row.icon} size={16} />
            </span>

            <div className="min-w-0">
              <dt className="text-[12.5px] text-text-faint">{row.label}</dt>
              <dd className={cn('text-[15px] leading-snug', row.known ? 'font-medium text-text' : 'text-text-muted')}>
                {row.value}
              </dd>
              {row.detail !== undefined ? (
                <dd className="nums-tabular mt-0.5 text-[12.5px] text-text-faint">{row.detail}</dd>
              ) : null}
            </div>
          </div>
        ))}
      </dl>
    </section>
  );
}

/**
 * الخلاصة — ما على المستخدم أن يفعله الآن، أو أنّه لا شيء عليه.
 *
 * وكانت هذه البطاقات مبعثرةً أسفل الصفحة تحت المراحل، فمن فتح شاشةً
 * حالُها `needs_review` رأى سبعَ مراحل قبل أن يرى أنّ الوقوف عنده هو.
 */
function Outcome({ job, onCancelClick, notify }: { job: Job; onCancelClick: () => void; notify: ReactNode }) {
  /*
   * ★ **الإزالة تُقال قبل كلّ شيء** — T-28.
   *
   * وتسبق حتى بطاقة «نُشر»: مهمّةٌ أُزيلت صفحتُها تبقى حالتُها `published`
   * في آلة الحالات، فلو قُرئت الحالةُ وحدها لقيل لصاحبها «نُشر» ورابطُه
   * يردّ ٤١٠. **ومن رأى رابطاً كان يعمل فلا يعمل ظنّ العطل عندنا.**
   */
  if (job.takedown !== null) {
    return (
      <Banner
        tone="muted"
        icon="alert"
        title={t('jobs.follow.takedown')}
        body={t('jobs.follow.takedown_body')}
        action={
          job.takedown.reason === '' ? undefined : (
            <div className="min-w-0 basis-full rounded-md border border-border bg-surface p-3">
              <p className="mb-1 text-[12px] text-text-faint">{t('jobs.follow.takedown_reason')}</p>
              <p className="wrap-anywhere text-[14px] leading-relaxed text-text">{job.takedown.reason}</p>
            </div>
          )
        }
      />
    );
  }

  if (job.error !== null) {
    // «أعد المحاولة» و«غيّر المصدر» بديلان للخطأ نفسه، فيقفان معاً فيه — T-83.
    // ★ و«غيّر المصدر» لا يظهر إلا حين يكون تبديل المصدر فعلاً مُصلحاً
    // للخطأ — T-91: تعطّل مزوّد النماذج أو عطلٌ داخليّ لا يُصلحهما تبديل
    // المصدر، وعرضُ فعلٍ لا أثر له يُضلّل.
    return (
      <ErrorState
        message={job.error}
        onRetry={() => router.post(`/panel/jobs/${job.id}/retry`)}
        secondary={
          job.error_offers_change_source ? (
            <Button variant="ghost" onClick={() => router.visit('/panel/lectures/create')}>
              {t('jobs.follow.change_source')}
            </Button>
          ) : undefined
        }
      />
    );
  }

  if (job.needs_review) {
    return (
      <Banner
        tone="warning"
        icon="alert"
        title={t('jobs.follow.needs_review_note')}
        body={plural('jobs.follow.pending', job.pending_evidence)}
        action={
          <Button onClick={() => router.visit(`/panel/jobs/${job.id}/review`)}>
            {t('jobs.follow.review_cta')}
          </Button>
        }
      />
    );
  }

  if (job.state === 'published') {
    return (
      <Banner
        tone="success"
        icon="check"
        title={t('jobs.follow.published_note')}
        action={
          <div className="flex flex-wrap gap-2">
            {/*
              **والمعاينة أوّلاً لا الرابط العلني** (T-30): هذه هي الشاشة
              التي يُفتح عليها الملخّص ساعةَ يصير جاهزاً، وأوّل ما يُراد
              حينها رؤيةُ ما خرج — لا فتحُ ما نُشر على الناس.
            */}
            <Button onClick={() => router.visit(`/panel/jobs/${job.id}/preview`)}>
              <Icon name="eye" size={16} />
              {t('jobs.published.preview_cta')}
            </Button>

            <Button variant="secondary" onClick={() => router.visit(`/panel/summaries/${job.id}`)}>
              <Icon name="external" size={16} />
              {t('jobs.follow.view_cta')}
            </Button>
          </div>
        }
      />
    );
  }

  if (job.state === 'cancelled') {
    return <Banner tone="muted" icon="close" title={t('jobs.follow.cancelled')} />;
  }

  return (
    <Banner
      tone="info"
      icon="clock"
      title={t('jobs.follow.running_note')}
      body={t('jobs.follow.running_body')}
      action={
        <div className="flex flex-wrap items-start gap-2">
          {notify}
          <Button variant="danger-soft" onClick={onCancelClick}>
            {t('jobs.follow.cancel')}
          </Button>
        </div>
      }
    />
  );
}

const TONES = {
  warning: 'border-warning/35 bg-warning/8 text-warning',
  success: 'border-success/30 bg-success/8 text-success',
  info: 'border-info/25 bg-info/6 text-info',
  muted: 'border-border bg-surface-alt text-text-faint',
} as const;

function Banner({
  tone, icon, title, body, action,
}: {
  tone: keyof typeof TONES;
  icon: IconName;
  title: string;
  body?: string;
  action?: ReactNode;
}) {
  return (
    <div className={cn('flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border px-4 py-4', TONES[tone])}>
      <Icon name={icon} size={19} className="shrink-0" />

      {/*
        عرضٌ أدنى للنصّ قبل أن يلتفّ الفعلُ تحته. وبدونه كان الزرّان على
        ٣٩٠px يعصران «الإعداد جارٍ» إلى كلمةٍ في كلّ سطر — T-83.
      */}
      <div className="min-w-[min(100%,14rem)] flex-1">
        <p className="text-[15px] font-medium text-text">{title}</p>
        {body !== undefined ? <p className="mt-0.5 text-[13px] text-text-muted">{body}</p> : null}
      </div>

      {action !== undefined ? <div className="shrink-0">{action}</div> : null}
    </div>
  );
}

/** ساعةٌ تدقّ كلّ ثانية ما دام الإعداد يجري — لعدّاد «منذ …» في المرحلة الجارية. */
function useNow(ticking: boolean): number {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!ticking) {
      return undefined;
    }

    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [ticking]);

  return now;
}

/** الساعة والدقيقة بتوقيت من يقرأ — والخادم لا يعرفه. */
function clock(iso: string): string {
  const at = new Date(iso);

  return Number.isNaN(at.getTime())
    ? '—'
    : at.toLocaleTimeString('ar', { hour: '2-digit', minute: '2-digit', hour12: false });
}

/**
 * الثواني المنقضية — إلى الانتهاء إن انتهت، وإلى الآن إن كانت تجري.
 * و`null` لما لم يبدأ: مهمّةٌ في الطابور لا زمنَ لها بعد.
 */
function elapsedSeconds(job: Job, now: number): number | null {
  if (job.started_at === null) {
    return null;
  }

  const from = new Date(job.started_at).getTime();
  const to = job.finished_at === null ? now : new Date(job.finished_at).getTime();

  return Number.isNaN(from) || Number.isNaN(to) || to < from ? null : Math.round((to - from) / 1000);
}
