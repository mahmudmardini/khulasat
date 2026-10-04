import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { BrandMark, Wordmark } from '@/Components/Brand';
import { Button } from '@/Components/Button';
import { DiffView } from '@/Components/DiffView';
import { Icon, type IconName } from '@/Components/Icon';
import { SlideBody } from '@/Components/SlideBody';
import { Toast } from '@/Components/Toast';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { useReducedMotion } from '@/lib/motion';
import { toArabicIndic } from '@/lib/numerals';
import { plural } from '@/lib/plural';

type Verdict = 'exact' | 'partial' | 'none' | 'unverifiable';
type Status = 'queued' | 'extracting' | 'verifying' | 'done' | 'failed';

interface Finding {
  index: number;
  kind: string;
  quoted: string;
  claimed: { source?: string; narrator?: string; takhrij?: string };
  verdict: Verdict;
  reason: { code: string; text: string };
  notes: Array<{ code: string; text: string }>;
  source: {
    text: string;
    /** المتنُ بلا سند — للحديث وحده، ومتى اختلف عن النصّ كاملاً. */
    matn?: string;
    reference?: string;
    url?: string;
    grade?: { value: string; label: string | null; stated: boolean };
    graders?: Array<{ name?: string; grade?: string }>;
    similarity?: number;
  } | null;
  is_fragment: boolean;
}

interface Check {
  id: string;
  status: Status;
  settled: boolean;
  purged: boolean;
  url: string;
  char_count: number;
  text: string | null;
  evidence_count: number | null;
  counts: Record<Verdict, number> | null;
  findings: Finding[] | null;
  error: { code: string; message: string } | null;
  created_at: string;
  completed_at: string | null;
  expires_at: string;
}

interface Props {
  check: Check | null;
  limits: { min_chars: number; max_chars: number; per_hour: number; retention_days: number };
  api_url: string;
}

const VERDICTS: Verdict[] = ['exact', 'partial', 'none', 'unverifiable'];

/** لونُ كلّ حكمٍ وأيقونتُه — **الأخضر للمطابق وحده**، فلا يُقرأ «قريب» موافقةً. */
const TONE: Record<Verdict, { icon: IconName; badge: string; mark: string; tile: string }> = {
  exact: {
    icon: 'check',
    badge: 'bg-success/12 text-success',
    mark: 'bg-success/12 decoration-success',
    tile: 'border-success/30 text-success',
  },
  partial: {
    icon: 'alert',
    badge: 'bg-warning/14 text-warning',
    mark: 'bg-warning/14 decoration-warning',
    tile: 'border-warning/35 text-warning',
  },
  none: {
    icon: 'close',
    badge: 'bg-danger/10 text-danger',
    mark: 'bg-danger/10 decoration-danger',
    tile: 'border-danger/30 text-danger',
  },
  unverifiable: {
    icon: 'quote',
    badge: 'bg-surface-alt text-text-muted',
    mark: 'bg-surface-alt decoration-border-strong',
    tile: 'border-border text-text-muted',
  },
};

/** الدرجةُ كما نصّ عليها المصدر — والضعيفُ والموضوعُ ظاهران لا مطويّان. */
const GRADE_TONE: Record<string, string> = {
  sahih: 'bg-success/12 text-success',
  hasan: 'bg-success/12 text-success',
  daif: 'bg-warning/14 text-warning',
  mawdu: 'bg-danger/10 text-danger',
};

const POLL_MS = 2000;

/**
 * أداة «تحقّق» — T-181.
 *
 * **صفحةٌ واحدة بثلاثة أحوال**: نموذجٌ يُلصق فيه النصّ، ثمّ مراحلُ تُتابَع،
 * ثمّ تقريرٌ برابطٍ ثابت يُشارَك. والتقريرُ يُقرأ من أعلاه في ثوانٍ: عدّاداتٌ
 * بالأحكام الأربعة، ثمّ النصُّ نفسه وقد عُلّمت شواهده بألوان أحكامها، ثمّ
 * بطاقةٌ لكلّ شاهدٍ فيها لفظُه وحكمُه وسببُه ولفظُ مصدره.
 *
 * ★ **ولا حكمَ في المتصفّح.** كلُّ ما يُعرض هنا — الحكمُ وسببُه والدرجةُ — جاء
 * من الخادم، والصفحةُ ترسمه ولا تستنبط منه شيئاً. وكذلك الواجهةُ البرمجية.
 */
export default function Verify({ check: initial, limits, api_url: apiUrl }: Props) {
  const check = usePolledCheck(initial);

  return (
    <div className="min-h-screen bg-bg">
      <Head title={t('verify.meta_title')}>
        <meta name="description" content={t('verify.meta_description')} />
      </Head>

      <header className="border-b border-border bg-surface/80 backdrop-blur">
        <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-3">
          <Link href="/" className="flex items-center gap-2 text-primary">
            <BrandMark size={24} />
            <Wordmark className="text-[20px]" />
          </Link>
          <Link href="/verify" className="text-[14px] font-medium text-text-muted hover:text-text">
            {t('verify.eyebrow')}
          </Link>
        </div>
      </header>

      <main className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8 sm:py-10">
        {check === null ? (
          <Intake limits={limits} />
        ) : check.purged ? (
          <Purged days={limits.retention_days} />
        ) : !check.settled ? (
          <Progress check={check} />
        ) : check.error !== null ? (
          <Failed check={check} />
        ) : (
          <Report check={check} />
        )}

        <footer className="mt-4 space-y-2 border-t border-border pt-6 text-[13px] leading-relaxed text-text-faint">
          <p>{t('verify.footer.disclaimer')}</p>
          <p>{t('verify.footer.sources')}</p>
          <p>
            {t('verify.footer.api', { url: '' })}
            <code dir="ltr" className="rounded bg-surface-alt px-1.5 py-0.5 text-[12px] text-text-muted">
              POST {apiUrl}
            </code>
          </p>
        </footer>
      </main>
    </div>
  );
}

/* ── الحال الأولى: النموذج ─────────────────────────────────────────── */

function Intake({ limits }: { limits: Props['limits'] }) {
  const form = useForm({ text: '' });
  const area = useRef<HTMLTextAreaElement>(null);
  const length = [...form.data.text].length;
  const over = length > limits.max_chars;
  const near = !over && length > limits.max_chars * 0.9;

  function submit() {
    if (form.processing || over) {
      return;
    }

    form.post('/verify', { preserveScroll: true });
  }

  return (
    <>
      <section className="max-w-2xl">
        <p className="mb-2 text-[13px] font-semibold tracking-wide text-accent">{t('verify.eyebrow')}</p>
        <h1 className="text-[28px] leading-snug font-semibold text-text sm:text-[32px]">{t('verify.title')}</h1>
        <p className="mt-3 text-[16px] leading-relaxed text-text-muted">{t('verify.lede')}</p>
      </section>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
        className="rounded-lg border border-border bg-surface shadow-card"
      >
        <label htmlFor="verify-text" className="sr-only">
          {t('verify.form.label')}
        </label>
        <textarea
          id="verify-text"
          ref={area}
          dir="rtl"
          value={form.data.text}
          onChange={(event) => form.setData('text', event.target.value)}
          onKeyDown={(event) => {
            // ⌘/Ctrl + Enter يرسل — من يلصق نصّاً يداه على لوحة المفاتيح.
            if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
              event.preventDefault();
              submit();
            }
          }}
          placeholder={t('verify.form.placeholder')}
          aria-invalid={form.errors.text !== undefined}
          aria-describedby="verify-meta verify-error"
          rows={9}
          className="block min-h-56 w-full resize-y rounded-t-lg bg-transparent px-5 py-4 text-[16px] leading-8 text-text placeholder:text-text-faint focus:outline-none"
        />

        {form.errors.text !== undefined ? (
          <p id="verify-error" role="alert" className="mx-5 mb-3 flex items-start gap-2 rounded-md bg-danger/8 px-3 py-2 text-[14px] text-danger">
            <Icon name="alert" size={16} className="mt-1 shrink-0" />
            {form.errors.text}
          </p>
        ) : null}

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              onClick={() => {
                form.setData('text', t('verify.example_text'));
                form.clearErrors();
                area.current?.focus();
              }}
            >
              <Icon name="sparkle" size={16} />
              {t('verify.form.example')}
            </Button>
            {form.data.text !== '' ? (
              <Button
                type="button"
                variant="ghost"
                onClick={() => {
                  form.reset();
                  form.clearErrors();
                  area.current?.focus();
                }}
              >
                {t('verify.form.clear')}
              </Button>
            ) : null}
          </div>

          <div className="flex items-center gap-3">
            <span
              id="verify-meta"
              aria-live="polite"
              className={cn('nums-tabular text-[13px]', over ? 'font-semibold text-danger' : near ? 'text-warning' : 'text-text-faint')}
            >
              {toArabicIndic(t('verify.form.counter', { count: length, max: limits.max_chars }))}
            </span>
            <Button type="submit" loading={form.processing} disabled={over || form.data.text.trim() === ''}>
              <Icon name="search" size={16} />
              {t('verify.form.submit')}
            </Button>
          </div>
        </div>
      </form>

      <p className="-mt-3 flex flex-wrap gap-x-5 gap-y-1 px-1 text-[13px] text-text-faint">
        <span className="inline-flex items-center gap-1.5">
          <Icon name="shield" size={14} />
          {toArabicIndic(t('verify.form.privacy', { days: limits.retention_days }))}
        </span>
        <span className="inline-flex items-center gap-1.5">
          <Icon name="clock" size={14} />
          {toArabicIndic(t('verify.form.limit', { count: limits.per_hour }))}
        </span>
      </p>

      <section aria-label={t('verify.eyebrow')} className="grid gap-3 sm:grid-cols-3">
        {(
          [
            ['extract', 'quote'],
            ['match', 'layers'],
            ['explain', 'list'],
          ] as const
        ).map(([key, icon]) => (
          <div key={key} className="rounded-lg border border-border bg-surface px-4 py-4">
            <span aria-hidden="true" className="mb-2 flex size-8 items-center justify-center rounded-lg bg-primary/8 text-primary">
              <Icon name={icon} size={16} />
            </span>
            <h2 className="text-[15px] font-semibold text-text">{t(`verify.principles.${key}.title`)}</h2>
            <p className="mt-1 text-[13.5px] leading-relaxed text-text-muted">{t(`verify.principles.${key}.body`)}</p>
          </div>
        ))}
      </section>
    </>
  );
}

/* ── الحال الثانية: المراحل ────────────────────────────────────────── */

function Progress({ check }: { check: Check }) {
  const order: Status[] = ['extracting', 'verifying', 'done'];
  const current = check.status === 'queued' ? -1 : order.indexOf(check.status);

  return (
    <section aria-live="polite" className="mx-auto w-full max-w-xl rounded-lg border border-border bg-surface shadow-card">
      <header className="border-b border-border px-5 py-4">
        <h1 className="text-[18px] font-semibold text-text">
          {check.status === 'queued' ? t('verify.steps.queued') : t(`verify.steps.${check.status}`)}
        </h1>
        <p className="mt-0.5 text-[13.5px] text-text-muted">{t('verify.steps.waiting')}</p>
      </header>

      <ol className="flex flex-col gap-1 px-3 py-3">
        {order.map((step, position) => {
          const state = position < current ? 'done' : position === current ? 'active' : 'pending';

          return (
            <li
              key={step}
              aria-current={state === 'active' ? 'step' : undefined}
              className={cn('flex items-start gap-3 rounded-lg px-3 py-2.5', state === 'active' && 'bg-info/5')}
            >
              <span
                aria-hidden="true"
                className={cn(
                  'relative flex size-8 shrink-0 items-center justify-center rounded-full',
                  state === 'done' && 'bg-success/12 text-success',
                  state === 'active' && 'bg-info text-white',
                  state === 'pending' && 'border border-border-strong text-text-faint',
                )}
              >
                {state === 'active' ? <span className="absolute inset-0 animate-ping rounded-full bg-info/25" /> : null}
                <Icon name={state === 'done' ? 'check' : step === 'extracting' ? 'quote' : step === 'verifying' ? 'layers' : 'list'} size={15} className="relative" />
              </span>
              <span className="pt-0.5">
                <span className={cn('block text-[15px]', state === 'pending' ? 'text-text-faint' : 'font-medium text-text')}>
                  {t(`verify.steps.${step}`)}
                </span>
                <span className="block text-[13px] text-text-muted">{t(`verify.steps.about.${step}`)}</span>
              </span>
            </li>
          );
        })}
      </ol>
    </section>
  );
}

/* ── الحال الثالثة: التقرير ────────────────────────────────────────── */

function Report({ check }: { check: Check }) {
  const findings = check.findings ?? [];
  const counts = check.counts ?? { exact: 0, partial: 0, none: 0, unverifiable: 0 };
  const [filter, setFilter] = useState<Verdict | 'all'>('all');
  const [toast, setToast] = useState<string | null>(null);
  const reduced = useReducedMotion();

  const shown = filter === 'all' ? findings : findings.filter((finding) => finding.verdict === filter);

  function jump(index: number) {
    setFilter('all');

    window.requestAnimationFrame(() => {
      const card = document.getElementById(`finding-${index}`);
      card?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      card?.focus({ preventScroll: true });
    });
  }

  async function copy(text: string) {
    try {
      await navigator.clipboard.writeText(text);
      setToast(t('verify.actions.copied'));
    } catch {
      // متصفّحٌ يمنع الحافظة: لا شيء يُفعل، والرابطُ ظاهرٌ في شريط العنوان.
    }
  }

  return (
    <>
      <section className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-[26px] font-semibold text-text">{t('verify.summary.title')}</h1>
          <p className="mt-1 text-[14px] text-text-muted">
            {findings.length > 0 ? plural('verify.summary.found', findings.length) : t('verify.empty.title')}
            <span aria-hidden="true" className="mx-2 text-text-faint">·</span>
            {t('verify.summary.checked_at', { time: when(check.completed_at ?? check.created_at) })}
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => void copy(check.url)}>
            <Icon name="link" size={16} />
            {t('verify.actions.copy_link')}
          </Button>
          {findings.length > 0 ? (
            <Button variant="secondary" onClick={() => void copy(asText(check, findings))}>
              <Icon name="copy" size={16} />
              {t('verify.actions.copy_report')}
            </Button>
          ) : null}
          <Button variant="ghost" onClick={() => router.visit('/verify')}>
            <Icon name="create" size={16} />
            {t('verify.actions.new_check')}
          </Button>
        </div>
      </section>

      {findings.length === 0 ? (
        <section className="rounded-lg border border-border bg-surface px-6 py-12 text-center shadow-card">
          <span aria-hidden="true" className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-surface-alt text-text-faint">
            <Icon name="search" size={20} />
          </span>
          <h2 className="text-[17px] font-semibold text-text">{t('verify.empty.title')}</h2>
          <p className="mx-auto mt-1 max-w-md text-[14.5px] leading-relaxed text-text-muted">{t('verify.empty.body')}</p>
        </section>
      ) : (
        <>
          {/* العدّادات — ومرشِّحاتٌ في آن: النقرُ على حكمٍ يُبقي شواهده وحدها. */}
          <div role="group" aria-label={t('verify.summary.title')} className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {VERDICTS.map((verdict) => {
              const active = filter === verdict;
              const count = counts[verdict] ?? 0;

              return (
                <button
                  key={verdict}
                  type="button"
                  disabled={count === 0}
                  aria-pressed={active}
                  onClick={() => setFilter(active ? 'all' : verdict)}
                  className={cn(
                    'flex flex-col items-start gap-1 rounded-lg border bg-surface px-4 py-3 text-start transition-colors',
                    TONE[verdict].tile,
                    active ? 'ring-2 ring-current/40' : 'hover:bg-surface-alt',
                    count === 0 && 'cursor-default opacity-45 hover:bg-surface',
                  )}
                >
                  <span className="flex items-center gap-1.5 text-[13px] font-medium">
                    <Icon name={TONE[verdict].icon} size={14} />
                    {t(`verify.verdicts.${verdict}`)}
                  </span>
                  <span className="nums-tabular text-[26px] leading-none font-semibold text-text">{toArabicIndic(count)}</span>
                </button>
              );
            })}
          </div>

          {check.text !== null ? <MarkedText text={check.text} findings={findings} onJump={jump} /> : null}

          <section className="flex flex-col gap-4">
            {filter !== 'all' ? (
              <div className="flex items-center justify-between gap-3 text-[14px] text-text-muted">
                <span>{t(`verify.verdicts.${filter}`)}</span>
                <button type="button" onClick={() => setFilter('all')} className="font-medium text-primary hover:underline">
                  {t('verify.summary.all')}
                </button>
              </div>
            ) : null}

            {shown.map((finding) => (
              <FindingCard key={finding.index} finding={finding} />
            ))}
          </section>
        </>
      )}

      <Toast message={toast} tone="success" onDismiss={() => setToast(null)} />
    </>
  );
}

/**
 * النصُّ نفسه وقد عُلّمت شواهده — **ليرى الباحث موضع كلّ حكمٍ في سياقه**.
 *
 * ويُبحث عن لفظ الشاهد في النصّ كما هو: المرحلةُ الثالثة تنقله حرفاً بحرف،
 * فإن لم يُوجد بعينه لم يُعلَّم، ولا يُخمَّن له موضع.
 */
function MarkedText({ text, findings, onJump }: { text: string; findings: Finding[]; onJump: (index: number) => void }) {
  const segments = useMemo(() => locate(text, findings), [text, findings]);
  const marked = segments.some((segment) => segment.finding !== null);

  if (!marked) {
    return null;
  }

  return (
    <section className="rounded-lg border border-border bg-surface shadow-card">
      <h2 className="border-b border-border px-5 py-3 text-[15px] font-semibold text-text">{t('verify.labels.marked_text')}</h2>
      <p dir="rtl" className="px-5 py-4 text-[16px] leading-9 whitespace-pre-wrap text-text">
        {segments.map((segment, position) =>
          segment.finding === null ? (
            <span key={position}>{segment.text}</span>
          ) : (
            <a
              key={position}
              href={`#finding-${segment.finding.index}`}
              onClick={(event) => {
                event.preventDefault();
                onJump(segment.finding!.index);
              }}
              aria-label={`${t('verify.labels.jump')} ${toArabicIndic(segment.finding.index)} — ${t(`verify.verdicts.${segment.finding.verdict}`)}`}
              className={cn(
                'rounded px-0.5 underline decoration-2 underline-offset-[6px] transition-colors hover:brightness-95',
                TONE[segment.finding.verdict].mark,
              )}
            >
              {segment.text}
              <sup className="nums-tabular ms-0.5 text-[11px] font-semibold text-text-muted no-underline">
                {toArabicIndic(segment.finding.index)}
              </sup>
            </a>
          ),
        )}
      </p>
    </section>
  );
}

function FindingCard({ finding }: { finding: Finding }) {
  const tone = TONE[finding.verdict];
  const scripture = finding.kind === 'ayah' || finding.kind === 'hadith';

  return (
    <article
      id={`finding-${finding.index}`}
      tabIndex={-1}
      className="scroll-mt-6 rounded-lg border border-border bg-surface shadow-card focus-visible:ring-2 focus-visible:ring-primary/40"
    >
      <header className="flex flex-wrap items-center gap-2 border-b border-border px-5 py-3">
        <span className="nums-tabular flex size-7 items-center justify-center rounded-full bg-surface-alt text-[13px] font-semibold text-text-muted">
          {toArabicIndic(finding.index)}
        </span>
        <span className="rounded-full border border-border px-2.5 py-0.5 text-[12.5px] text-text-muted">
          {t(`verify.kinds.${finding.kind}`)}
        </span>
        <span className={cn('ms-auto inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold', tone.badge)}>
          <Icon name={tone.icon} size={14} />
          {t(`verify.verdicts.${finding.verdict}`)}
        </span>
      </header>

      <div className="flex flex-col gap-4 px-5 py-4">
        <Field label={t('verify.labels.quoted')}>
          <p className={cn('text-text', scripture ? 'font-quran' : 'text-[16px] leading-8')}>{finding.quoted}</p>
        </Field>

        <p className="flex items-start gap-2 text-[14.5px] leading-relaxed text-text">
          <Icon name={tone.icon} size={16} className={cn('mt-1 shrink-0', tone.badge.split(' ').pop())} />
          {finding.reason.text}
        </p>

        {finding.source !== null ? <Source finding={finding} /> : null}

        {finding.notes.length > 0 || finding.claimed.source !== undefined || finding.claimed.takhrij !== undefined ? (
          <ul className="flex flex-col gap-1.5 text-[13.5px] text-text-muted">
            {finding.notes.map((note) => (
              <li key={note.code} className="flex items-start gap-2">
                <Icon name="alert" size={14} className="mt-1 shrink-0 text-text-faint" />
                {note.text}
              </li>
            ))}
            {finding.claimed.source !== undefined || finding.claimed.takhrij !== undefined ? (
              <li className="flex items-start gap-2">
                <Icon name="quote" size={14} className="mt-1 shrink-0 text-text-faint" />
                <span>
                  {t('verify.labels.claimed')}: {[finding.claimed.source, finding.claimed.takhrij].filter(Boolean).join('، ')}
                </span>
              </li>
            ) : null}
          </ul>
        ) : null}
      </div>
    </article>
  );
}

function Source({ finding }: { finding: Finding }) {
  const source = finding.source!;

  return (
    <div className="flex flex-col gap-3 rounded-lg bg-surface-alt/60 p-4">
      {finding.verdict === 'partial' ? (
        <>
          <DiffView source={source.matn ?? source.text} quoted={finding.quoted} quotedLabel={t('verify.labels.quoted')} />
          <p className="text-[12.5px] text-text-faint">{t('verify.labels.comparison')}</p>
        </>
      ) : (
        <Field label={t('verify.labels.source_text')}>
          <SlideBody kind={finding.kind === 'ayah' ? 'ayah' : 'evidence'} body={source.matn ?? source.text} className="" />
        </Field>
      )}

      {source.matn !== undefined ? (
        <details className="text-[13px] text-text-muted">
          <summary className="cursor-pointer select-none font-medium hover:text-text">{t('verify.labels.full_text')}</summary>
          <p className="mt-2 font-quran text-[17px] text-text-muted">{source.text}</p>
        </details>
      ) : null}

      <dl className="flex flex-wrap items-center gap-x-5 gap-y-2 text-[14px]">
        {source.reference !== undefined ? (
          <div className="flex items-center gap-2">
            <dt className="text-text-faint">{t('verify.labels.reference')}</dt>
            <dd className="font-medium text-text">{source.reference}</dd>
          </div>
        ) : null}

        {source.grade !== undefined && source.grade.label !== null ? (
          <div className="flex items-center gap-2">
            <dt className="text-text-faint">{t('verify.labels.grade')}</dt>
            <dd className={cn('rounded-full px-2.5 py-0.5 text-[13px] font-semibold', GRADE_TONE[source.grade.value] ?? 'bg-surface text-text-muted')}>
              {source.grade.label}
            </dd>
          </div>
        ) : null}

        {source.url !== undefined ? (
          <a
            href={source.url}
            target="_blank"
            rel="noopener"
            className="inline-flex items-center gap-1 text-[13.5px] font-medium text-primary hover:underline"
          >
            {t('verify.labels.open_quran')}
            <Icon name="external" size={13} />
          </a>
        ) : null}
      </dl>

      {source.graders !== undefined && source.graders.length > 0 ? (
        <details className="group text-[13px] text-text-muted">
          <summary className="cursor-pointer select-none font-medium text-text-muted hover:text-text">
            {t('verify.labels.graders')}
          </summary>
          <ul dir="auto" className="mt-2 flex flex-col gap-1 ps-4">
            {source.graders.map((grader, position) => (
              <li key={position} className="list-disc">
                {[grader.name, grader.grade].filter(Boolean).join(' — ')}
              </li>
            ))}
          </ul>
        </details>
      ) : null}
    </div>
  );
}

/* ── الأحوال الأخرى ───────────────────────────────────────────────── */

function Failed({ check }: { check: Check }) {
  const [busy, setBusy] = useState(false);

  return (
    <section role="alert" className="mx-auto w-full max-w-xl rounded-lg border border-danger/30 bg-surface px-6 py-8 text-center shadow-card">
      <span aria-hidden="true" className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-danger/10 text-danger">
        <Icon name="alert" size={20} />
      </span>
      <p className="text-[16px] text-text">{check.error?.message}</p>
      <div className="mt-5 flex flex-wrap justify-center gap-2">
        {check.text !== null ? (
          <Button
            loading={busy}
            onClick={() => {
              setBusy(true);
              router.post('/verify', { text: check.text ?? '' }, { onFinish: () => setBusy(false) });
            }}
          >
            {t('verify.actions.retry')}
          </Button>
        ) : null}
        <Button variant="secondary" onClick={() => router.visit('/verify')}>
          {t('verify.actions.new_check')}
        </Button>
      </div>
    </section>
  );
}

function Purged({ days }: { days: number }) {
  return (
    <section className="mx-auto w-full max-w-xl rounded-lg border border-border bg-surface px-6 py-10 text-center shadow-card">
      <span aria-hidden="true" className="mx-auto mb-3 flex size-11 items-center justify-center rounded-full bg-surface-alt text-text-faint">
        <Icon name="clock" size={20} />
      </span>
      <h1 className="text-[18px] font-semibold text-text">{t('verify.errors.purged_title')}</h1>
      <p className="mt-1 text-[14.5px] text-text-muted">{toArabicIndic(t('verify.errors.purged_body', { days }))}</p>
      <div className="mt-5">
        <Button onClick={() => router.visit('/verify')}>{t('verify.actions.new_check')}</Button>
      </div>
    </section>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <p className="mb-1 text-[12.5px] font-medium text-text-faint">{label}</p>
      {children}
    </div>
  );
}

/* ── أدوات ─────────────────────────────────────────────────────────── */

/**
 * الحالُ الحيّة لطلبٍ لم يكتمل — **والاستطلاعُ يقف عند الحال النهائية**، فلا
 * يبقى تبويبٌ منسيّ يضرب الخادم بعد أن جهز التقرير.
 */
function usePolledCheck(initial: Check | null): Check | null {
  const [check, setCheck] = useState(initial);

  useEffect(() => setCheck(initial), [initial]);

  const id = check?.id;
  const waiting = check !== null && !check.settled && !check.purged;

  useEffect(() => {
    if (!waiting || id === undefined) {
      return undefined;
    }

    const timer = window.setInterval(async () => {
      try {
        const response = await fetch(`/verify/${id}/status`, { headers: { Accept: 'application/json' } });

        if (response.ok) {
          const body = (await response.json()) as { check: Check };
          setCheck(body.check);
        }
      } catch {
        // انقطاعٌ لحظيّ: المحاولةُ التالية بعد ثانيتين، والصفحةُ على آخر حالٍ معلوم.
      }
    }, POLL_MS);

    return () => window.clearInterval(timer);
  }, [id, waiting]);

  return check;
}

interface Segment {
  text: string;
  finding: Finding | null;
}

/** مواضعُ الشواهد في النصّ، بترتيب ورودها ولا يتداخل اثنان. */
function locate(text: string, findings: Finding[]): Segment[] {
  const spans: Array<{ start: number; end: number; finding: Finding }> = [];
  let cursor = 0;

  for (const finding of findings) {
    const needle = finding.quoted.trim();

    if (needle === '') {
      continue;
    }

    let start = text.indexOf(needle, cursor);

    if (start < 0) {
      start = text.indexOf(needle);
    }

    if (start < 0) {
      continue;
    }

    const end = start + needle.length;

    if (spans.some((span) => start < span.end && end > span.start)) {
      continue;
    }

    spans.push({ start, end, finding });
    cursor = end;
  }

  spans.sort((a, b) => a.start - b.start);

  const segments: Segment[] = [];
  let position = 0;

  for (const span of spans) {
    if (span.start > position) {
      segments.push({ text: text.slice(position, span.start), finding: null });
    }

    segments.push({ text: text.slice(span.start, span.end), finding: span.finding });
    position = span.end;
  }

  if (position < text.length) {
    segments.push({ text: text.slice(position), finding: null });
  }

  return segments;
}

/** التقرير نصّاً يُلصق في رسالة — بالحكم وسببه وموضعه، لكلّ شاهد. */
function asText(check: Check, findings: Finding[]): string {
  const lines = [`${t('verify.summary.title')} — ${check.url}`, ''];

  for (const finding of findings) {
    lines.push(`${toArabicIndic(finding.index)}. [${t(`verify.kinds.${finding.kind}`)}] «${finding.quoted}»`);
    lines.push(`${t(`verify.verdicts.${finding.verdict}`)}: ${finding.reason.text}`);

    if (finding.source?.reference !== undefined) {
      lines.push(`${t('verify.labels.reference')}: ${finding.source.reference}`);
    }

    for (const note of finding.notes) {
      lines.push(note.text);
    }

    lines.push('');
  }

  lines.push(t('verify.footer.disclaimer'));

  return lines.join('\n');
}

function when(iso: string): string {
  const at = new Date(iso);

  return Number.isNaN(at.getTime())
    ? ''
    : at.toLocaleString('ar', { dateStyle: 'medium', timeStyle: 'short' });
}
