import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { durationLabel } from '@/lib/duration';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';
import { Icon, type IconName } from './Icon';
import type { StepState } from './StepTracker';

export interface WizardStep {
  key: string;
  state: StepState;
  /** دخولُ المرحلة من سجلّ الانتقالات — للجارية «منذ …». */
  started_at: string | null;
  /** ما استغرقته مرحلةٌ تمّت أو توقّفت، من انتقالَيها. */
  seconds: number | null;
  /** مراجعةٌ لم يُحتج إليها — تُعلَّم ولا تُنسب إليها مدّة. */
  skipped: boolean;
}

export type PhaseKey = 'listen' | 'understand' | 'write';

/**
 * المراحل الكبرى الثلاث — تجمع الثماني الحقيقية ولا تخترع غيرها.
 *
 * فلا مرحلة «ترجمة»: الترجمة داخل الإخراج، ولا حالة لها في آلة الحالات.
 */
const PHASES: ReadonlyArray<{ key: PhaseKey; steps: readonly string[] }> = [
  { key: 'listen', steps: ['transcribing', 'cleaning'] },
  { key: 'understand', steps: ['structuring', 'extracting', 'verifying', 'review'] },
  { key: 'write', steps: ['writing', 'rendering'] },
];

/** لكلّ مرحلةٍ أيقونةٌ بمعناها — تُعرف بنظرةٍ قبل أن يُقرأ اسمها. */
const ICONS: Record<string, IconName> = {
  transcribing: 'mic',
  cleaning: 'sparkle',
  structuring: 'layers',
  extracting: 'quote',
  verifying: 'shieldCheck',
  review: 'eye',
  writing: 'pen',
  rendering: 'page',
};

const SEGMENT: Record<StepState, string> = {
  done: 'bg-success',
  active: 'bg-info/20',
  awaiting: 'bg-warning',
  failed: 'bg-danger',
  pending: 'bg-border',
};

const ROW: Record<StepState, string> = {
  done: 'border-transparent',
  active: 'border-info/25 bg-info/5',
  awaiting: 'border-warning/35 bg-warning/8',
  failed: 'border-danger/30 bg-danger/5',
  pending: 'border-transparent',
};

const BADGE: Record<StepState, string> = {
  done: 'bg-success/10 text-success',
  active: 'bg-info text-white',
  awaiting: 'bg-warning text-white',
  failed: 'bg-danger/12 text-danger',
  pending: 'border border-border-strong bg-surface text-text-faint',
};

const TITLE: Record<StepState, string> = {
  done: 'text-text',
  active: 'font-semibold text-text',
  awaiting: 'font-semibold text-warning',
  failed: 'font-semibold text-danger',
  pending: 'text-text-faint',
};

/** المرحلة الكبرى التي فيها الخطوةُ الجارية أو المنتظِرة، إن كانت. */
export function currentPhase(steps: ReadonlyArray<{ key: string; state: StepState }>): PhaseKey | null {
  const live = steps.find((step) => step.state === 'active' || step.state === 'awaiting');

  return PHASES.find((phase) => live !== undefined && phase.steps.includes(live.key))?.key ?? null;
}

/**
 * معالجُ الإعداد — T-92، وأصلُه SCREENS.md §4.
 *
 * **الثماني ظاهرةٌ لا مطويّة** (وكانت خلف «تفاصيل المراحل» في T-83)،
 * مجمّعةً تحت المراحل الكبرى الثلاث، ولكلٍّ أيقونةٌ وسطرٌ يقول ما تفعله.
 * **والزمنُ منصوص** — «تمّت (✓ وزمنها)» كما في §4 — من سجلّ الانتقالات لا
 * تقديراً، والجاريةُ تعدّ «منذ …» إلى ساعة من يقرأ.
 *
 * ★ **ومراجعةُ الجهة بلا مدّة في لوحتها** — T-171: `decidedReview` يضع «حُسمت
 * بقرارك» مكان زمنها، فالوقتُ وقتُها لا وقتُ الإعداد. ولوحةُ المشرف لا تمرّره،
 * فتبقى له مدّتُها الفعلية، وهي تفيده تشغيلياً.
 *
 * ★ **وبلا نسبة مئوية.** الشريط في رأسه ثمانيةُ أجزاءٍ بعدد المراحل، والجاري
 * منها يمرّ فيه ضوءٌ لا يمتلئ: «يجري الآن» لا «٦٠٪».
 */
export function ProcessWizard({
  steps, now, aside, decidedReview = false,
}: {
  steps: WizardStep[];
  now: number;
  aside?: ReactNode;
  decidedReview?: boolean;
}) {
  const byKey = new Map(steps.map((step) => [step.key, step]));
  const liveIndex = steps.findIndex((step) => step.state === 'active' || step.state === 'awaiting');
  const failed = steps.find((step) => step.state === 'failed');

  let summary: string;

  if (failed !== undefined) {
    summary = t('jobs.live.wizard.stopped_at', { name: t(`jobs.steps.${failed.key}`) });
  } else if (liveIndex >= 0 && steps[liveIndex].state === 'awaiting') {
    summary = t('jobs.live.wizard.awaiting');
  } else if (liveIndex >= 0) {
    summary = toArabicIndic(
      t('jobs.live.wizard.position', {
        current: liveIndex + 1,
        total: steps.length,
        name: t(`jobs.steps.${steps[liveIndex].key}`),
      }),
    );
  } else if (steps.length > 0 && steps.every((step) => step.state === 'done')) {
    summary = t('jobs.live.wizard.all_done');
  } else {
    summary = t('jobs.live.wizard.queued');
  }

  return (
    <section className="rounded-lg border border-border bg-surface shadow-card">
      <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-4">
        <div className="min-w-0">
          <h2 className="text-[17px] font-semibold text-text">{t('jobs.steps_label')}</h2>
          <p aria-live="polite" className="mt-0.5 text-[13.5px] text-text-muted">{summary}</p>
        </div>
        {aside}
      </header>

      {/* الشريط: جزءٌ لكلّ مرحلة، والمراحل الكبرى مفصولةٌ بفجوة. */}
      <div aria-hidden="true" className="flex gap-3 px-5 pt-4 pb-2">
        {PHASES.map((phase) => (
          <div key={phase.key} className="flex gap-1" style={{ flexGrow: phase.steps.length, flexBasis: 0 }}>
            {phase.steps.map((key) => {
              const state = byKey.get(key)?.state ?? 'pending';

              return (
                <span key={key} className={cn('relative h-1.5 flex-1 overflow-hidden rounded-full', SEGMENT[state])}>
                  {state === 'active' ? (
                    <span className="animate-sweep absolute inset-y-0 w-2/5 rounded-full bg-info" />
                  ) : null}
                </span>
              );
            })}
          </div>
        ))}
      </div>

      <div className="flex flex-col gap-4 px-2 pt-2 pb-4 sm:px-3">
        {PHASES.map((phase, position) => (
          <section key={phase.key} aria-labelledby={`phase-${phase.key}`}>
            <h3
              id={`phase-${phase.key}`}
              className="mb-1 flex items-center gap-2 px-3 text-[12.5px] font-semibold tracking-wide text-text-faint"
            >
              <span className="nums-tabular">{toArabicIndic(position + 1)}</span>
              <span aria-hidden="true" className="h-px w-3 bg-border-strong" />
              {t(`jobs.live.phases.${phase.key}`)}
            </h3>

            <ol className="flex flex-col gap-0.5">
              {phase.steps.map((key, index) => {
                const step = byKey.get(key);

                return step === undefined ? null : (
                  <StepRow
                    key={key}
                    step={step}
                    now={now}
                    last={index === phase.steps.length - 1}
                    decided={decidedReview && key === 'review'}
                  />
                );
              })}
            </ol>
          </section>
        ))}
      </div>
    </section>
  );
}

function StepRow({ step, now, last, decided }: { step: WizardStep; now: number; last: boolean; decided: boolean }) {
  return (
    <li
      aria-current={step.state === 'active' ? 'step' : undefined}
      className={cn('relative flex items-start gap-3 rounded-lg border px-3 py-2.5 transition-colors', ROW[step.state])}
    >
      {/*
        الواصل خطٌّ لا سهم — منذ StepTracker: السهمُ في RTL ينقلب معناه إن
        نُسي عكسُه، والخطّ لا اتّجاه له. و`start` منطقيّةٌ فتقع يميناً.
      */}
      {!last ? (
        <span
          aria-hidden="true"
          className={cn(
            'absolute start-[29.5px] top-[46px] -bottom-1 w-px',
            step.state === 'done' ? 'bg-success/35' : 'bg-border',
          )}
        />
      ) : null}

      <span
        aria-hidden="true"
        className={cn(
          'relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full',
          BADGE[step.state],
          step.skipped && 'opacity-55',
        )}
      >
        {step.state === 'active' ? <span className="absolute inset-0 animate-ping rounded-full bg-info/25" /> : null}
        <Icon name={ICONS[step.key] ?? 'clock'} size={17} className="relative" />

        {step.state === 'done' ? (
          <span className="absolute -end-0.5 -bottom-0.5 flex size-4 items-center justify-center rounded-full bg-success text-white ring-2 ring-surface">
            <svg className="size-2.5" viewBox="0 0 16 16" aria-hidden="true">
              <path d="M3 8.5l3.2 3.2L13 5" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </span>
        ) : null}
      </span>

      <span className="min-w-0 flex-1 pt-0.5">
        <span className={cn('block text-[15px] leading-snug', TITLE[step.state])}>{t(`jobs.steps.${step.key}`)}</span>
        <span className="mt-0.5 block text-[13px] leading-snug text-text-muted">
          {t(`jobs.live.wizard.about.${step.key}`)}
        </span>
      </span>

      <span className="shrink-0 pt-1">
        <Meta step={step} now={now} decided={decided} />
      </span>
    </li>
  );
}

/** ما يُقال في طرف السطر: زمنُ ما تمّ، وعدّادُ الجارية، وحالُ ما وقف. */
function Meta({ step, now, decided }: { step: WizardStep; now: number; decided: boolean }) {
  if (step.state === 'done') {
    return (
      <span className="nums-tabular text-[12.5px] text-text-faint">
        {step.skipped
          ? t('jobs.live.wizard.skipped')
          : decided
            ? t('jobs.live.wizard.decided')
            : step.seconds !== null
              ? durationLabel(step.seconds)
              : t('jobs.step_state.done')}
      </span>
    );
  }

  if (step.state === 'active') {
    const started = step.started_at === null ? Number.NaN : Date.parse(step.started_at);
    const since = Number.isNaN(started) ? null : Math.max(0, Math.round((now - started) / 1000));

    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-info/10 px-2.5 py-1 text-[12.5px] font-medium whitespace-nowrap text-info">
        <span aria-hidden="true" className="size-1.5 animate-pulse rounded-full bg-info" />
        {since === null
          ? t('jobs.step_state.active')
          : since < 5
            ? t('jobs.live.wizard.just_started')
            : t('jobs.live.wizard.running_since', { time: durationLabel(since) })}
      </span>
    );
  }

  if (step.state === 'awaiting' || step.state === 'failed') {
    return (
      <span
        className={cn(
          'inline-flex rounded-full px-2.5 py-1 text-[12.5px] font-medium whitespace-nowrap',
          step.state === 'awaiting' ? 'bg-warning/15 text-warning' : 'bg-danger/10 text-danger',
        )}
      >
        {t(`jobs.step_state.${step.state}`)}
      </span>
    );
  }

  return <span className="sr-only">{t('jobs.step_state.pending')}</span>;
}
