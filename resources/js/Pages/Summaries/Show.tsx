import { useState, type ReactNode } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { AddLocale, type LocaleAddition } from '@/Components/AddLocale';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { ViewsByLocale, type LocaleViews } from '@/Components/ViewsByLocale';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { prose, toArabicIndic } from '@/lib/numerals';
import type { SharedProps } from '@/types/inertia';

interface OutputRow {
  type: string;
  /** لغةُ هذا المخرَج — T-64. ولكلّ لغةٍ صفحتُها ورابطُها. */
  locale: string;
  locale_label: string;
  produced: boolean;
  public_url: string | null;
  rendered_at: string | null;
}

interface Views {
  total: number;
  recent: number;
  /** توزيعُ القراءات على ألسنة الصفحات — T-140. */
  by_locale: LocaleViews[];
}

interface Props {
  job: {
    id: number;
    title: string | null;
    speaker: string | null;
    slug: string | null;
    state: string;
    published: boolean;
    published_at: string | null;
    unpublished_at: string | null;
    publishable: boolean;
  };
  outputs: OutputRow[];
  views: Views;
  can_publish: boolean;
  locale_additions: LocaleAddition[];
  quiz: {
    id: number;
    state: 'ready' | 'failed';
    status: 'open' | 'closed';
    attempts: number;
    participants: number;
    average: number | null;
  } | null;
}

/**
 * الملخّص المنشور — SCREENS.md §7، والمهمّة T-30.
 *
 * ★ **وإلغاء النشر لم يكن له مدخلٌ من الواجهة قطّ.** الفعل قائمٌ منذ T-15،
 * ولا يناديه إلّا الشيفرة ولوحة المشرف (T-28). فجهةٌ نشرت ملخّصاً ثمّ أرادت
 * سحبه — لخطأ فيه، أو لطلب الملقي — لم تجد إلّا أن تراسلنا وتنتظر. **وحقٌّ
 * لا مدخل له ليس حقّاً.**
 */
export default function Show({ job, outputs, views, can_publish, locale_additions, quiz }: Props) {
  const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
  const [busy, setBusy] = useState(false);
  const [asking, setAsking] = useState<'unpublish' | 'delete' | null>(null);

  const page = outputs.find((output) => output.type === 'page') ?? null;
  // عددُ لغات الصفحة — به يُعرف أيُلصَق وسمُ اللغة أم يُستغنى عنه.
  const pageLocales = outputs.filter((output) => output.type === 'page').length;
  const url = page?.public_url ?? null;

  function publish(): void {
    setBusy(true);
    router.post(`/panel/summaries/${job.id}`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  }

  function unpublish(): void {
    setAsking(null);
    setBusy(true);
    router.delete(`/panel/summaries/${job.id}/publication`, {
      preserveScroll: true,
      onFinish: () => setBusy(false),
    });
  }

  function purge(): void {
    setAsking(null);
    setBusy(true);
    router.delete(`/panel/summaries/${job.id}`, { onFinish: () => setBusy(false) });
  }

  return (
    <AppLayout title={t('jobs.published.title')} description={job.title ?? undefined}>
      <div className="flex flex-col gap-5">
        {Object.entries(errors).map(([key, message]) => (
          <p
            key={key}
            className="rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger"
          >
            {message}
          </p>
        ))}

        {job.published ? (
          <>
            <Card title={t('jobs.published.link')}>
              <LinkRow url={url} />

              <dl className="mt-5 grid gap-4 border-t border-border pt-4 sm:grid-cols-2">
                <Fact label={t('jobs.published.published_at')} value={day(job.published_at)} />

                {/*
                  ★ **عددٌ حقيقيّ، وصفرُه صادق** — T-31.
                  وكان قبله «لا تُقاس بعد»، لأنّ الصفحات تُخدَم من خارج
                  اللوحة فلا يمرّ بنا عدُّ فاتحيها. وصار يمرّ بشاهدةٍ في
                  الصفحة، **فصفرٌ اليوم يعني «لم تُفتح» لا «لا نعلم»**.
                */}
                <Fact
                  label={t('jobs.published.visits')}
                  value={
                    views.total === 0
                      ? t('jobs.published.visits_none')
                      : toArabicIndic(views.total)
                  }
                  hint={t('jobs.published.visits_hint')}
                >
                  {/*
                    **والتراكميّ وحده يُخفي صفحةً مات عنها القرّاء منذ شهور.**
                    فآخرُ ثلاثين يوماً معه — **ولا يُعرض وهو عينُ المجموع**:
                    صفحةٌ عمرُها أسبوع رقماها واحد، وتكرارُه سطرٌ لا يقول شيئاً.
                  */}
                  {detail(views).length > 0 ? (
                    <p className="mt-1 text-[12px] text-text-muted">{detail(views).join(' · ')}</p>
                  ) : null}
                </Fact>

                {/*
                  ★ **وتوزيعُها على الألسنة — T-140، بلاغُ مالك المنتج.**

                  فصاحبُ المحتوى هو من اختار اللغات ودفع ثمنها، **وهو أوّلُ
                  من يحقّ له أن يعرف أنفعَها**. ورقمٌ جامعٌ يقول «تُقرأ» ولا
                  يقول بأيّ لسان، فلا يُبنى عليه قرارُ الملخّص القادم.

                  **ويأخذ العرضَ كلَّه** لا خليّةً في الشبكة: أشرطةٌ تُقارَن
                  بأطوالها، ونصفُ العرض يُضيّقها فلا يُقارَن شيءٌ بشيء.
                */}
                {views.by_locale.length > 1 ? (
                  <div className="sm:col-span-2">
                    <ViewsByLocale rows={views.by_locale} />
                  </div>
                ) : null}
              </dl>
            </Card>

            <Card title={t('jobs.published.outputs')}>
              <ul className="flex flex-col gap-2">
                {outputs.map((output) => (
                  <OutputLine
                    // **المفتاح بالنوع واللغة** — T-64. فمفتاحٌ بالنوع وحده
                    // يتكرّر عند تعدّد اللغات، فتدهس صفحةٌ صفحةً في العرض.
                    key={`${output.type}:${output.locale}`}
                    output={output}
                    showLocale={output.type === 'page' && pageLocales > 1}
                  />
                ))}
              </ul>

              {/* «أضف لغة» — T-166. تحت صفحات اللغات، فيُرى ما نُشر وما يُضاف معاً. */}
              {can_publish && locale_additions.length > 0 ? (
                <div className="mt-4 border-t border-border pt-4">
                  <AddLocale
                    jobId={job.id}
                    additions={locale_additions}
                    published={job.published}
                    reload={['outputs', 'locale_additions', 'job']}
                  />
                </div>
              ) : null}
            </Card>

            <QuizCard jobId={job.id} quiz={quiz} />
          </>
        ) : (
          <Card>
            <EmptyState
              title={t(job.unpublished_at === null ? 'jobs.published.not_published_yet' : 'jobs.published.unpublished_note')}
              /*
                **وما أُزيل غير ما لم يُنشر قطّ.** فالأوّل رابطُه قائمٌ يردّ
                ٤١٠، وصاحبُه يسأل عمّا صار إليه؛ والثاني لا رابط له أصلاً.
                ونصٌّ واحد للحالين يُضلّل إحداهما — §القواعد العامّة.
              */
              body={t(job.unpublished_at === null ? 'jobs.published.not_published_body' : 'jobs.published.unpublished_body')}
              action={
                <Button variant="secondary" onClick={() => router.visit(`/panel/jobs/${job.id}/preview`)}>
                  {t('jobs.published.preview_cta')}
                </Button>
              }
            />
          </Card>
        )}

        {can_publish ? (
          <Card
            title={t('jobs.preview.manage')}
            footer={<p className="text-[13px] text-text-faint">{t('jobs.published.refresh_hint')}</p>}
          >
            <div className="flex flex-wrap items-center gap-3">
              {job.published ? (
                <Button variant="secondary" loading={busy} disabled={!job.publishable} onClick={publish}>
                  <Icon name="upload" size={16} />
                  {t('jobs.published.refresh')}
                </Button>
              ) : (
                <Button loading={busy} disabled={!job.publishable} onClick={publish}>
                  <Icon name="upload" size={16} />
                  {t(job.unpublished_at === null ? 'jobs.published.publish' : 'jobs.published.republish')}
                </Button>
              )}

              <Link
                href={`/panel/jobs/${job.id}/preview`}
                className="inline-flex shrink-0 items-center gap-2 rounded px-4 py-2 text-[15px] font-medium text-primary transition-colors hover:bg-surface-alt"
              >
                <Icon name="eye" size={16} />
                {t('jobs.published.preview_cta')}
              </Link>

              {/*
                **الفعلان المدمّران آخِراً وبوزنٍ أخفّ** — ترتيب الأزرار
                ترتيبُ الرجحان (§5). و«ألغِ النشر» يُستردّ بضغطة،
                و«احذف نهائياً» لا يُستردّ — فبينهما فرقُ اللون.
              */}
              {job.published ? (
                <Button variant="danger-soft" disabled={busy} onClick={() => setAsking('unpublish')}>
                  {t('jobs.published.unpublish')}
                </Button>
              ) : null}

              <Button variant="ghost" disabled={busy} onClick={() => setAsking('delete')}>
                {t('jobs.published.delete')}
              </Button>
            </div>

            {/* وسطرُ «تُزال الصفحة» لا معنى له وهي مُزالة أصلاً. */}
            {job.published ? (
              <p className="mt-3 text-[13px] text-text-faint">{t('jobs.published.unpublish_hint')}</p>
            ) : null}
          </Card>
        ) : null}
      </div>

      <ConfirmDialog
        open={asking !== null}
        title={t(asking === 'delete' ? 'jobs.published.delete' : 'jobs.published.unpublish')}
        consequence={t(asking === 'delete' ? 'jobs.published.delete_confirm' : 'jobs.published.unpublish_confirm')}
        confirmLabel={t(asking === 'delete' ? 'jobs.published.delete' : 'jobs.published.unpublish')}
        onConfirm={asking === 'delete' ? purge : unpublish}
        onCancel={() => setAsking(null)}
      />
    </AppLayout>
  );
}

/** الرابط العلني وزرّ نسخه — §7. */
function LinkRow({ url }: { url: string | null }) {
  const [copied, setCopied] = useState(false);

  if (url === null) {
    return <p className="text-[14px] text-text-muted">{t('jobs.published.link_missing')}</p>;
  }

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(url ?? '');
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // متصفّحٌ منع الحافظة: الرابط ظاهرٌ أمام العين ويُحدَّد باليد.
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {/* الرابط لاتينيّ فيُقرأ `ltr` ولو كانت الصفحة `rtl`. */}
      <code
        dir="ltr"
        className="min-w-0 flex-1 truncate rounded-md border border-border bg-surface-alt px-3 py-2 text-start text-[13px] text-text"
      >
        {url}
      </code>

      <Button variant="secondary" onClick={copy}>
        <Icon name={copied ? 'check' : 'copy'} size={16} />
        {t(copied ? 'common.actions.copied' : 'jobs.published.copy_link')}
      </Button>

      <a
        href={url}
        target="_blank"
        rel="noreferrer"
        className="inline-flex shrink-0 items-center gap-2 rounded px-3 py-2 text-[15px] font-medium text-primary transition-colors hover:bg-surface-alt"
      >
        <Icon name="external" size={16} />
        {t('jobs.preview.open_public')}
      </a>
    </div>
  );
}

function OutputLine({ output, showLocale }: { output: OutputRow; showLocale: boolean }) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border px-4 py-3">
      <span className="flex items-center gap-2 text-[15px] text-text">
        <Icon
          name={output.type === 'page' ? 'page' : 'carousel'}
          size={17}
          className={cn(output.produced ? 'text-primary' : 'text-text-faint')}
        />
        {t(`jobs.outputs.${output.type}`)}

        {/*
          **وسمُ اللغة يظهر عند التعدّد وحده** — T-64. فوسمٌ «العربية» فوق
          ملخّصٍ عربيٍّ وحده زيادةٌ لا تُفيد، وغيابُه عند لغتين يُخفي إحداهما.
        */}
        {showLocale ? (
          <span className="rounded bg-surface-alt px-2 py-0.5 text-[12px] font-medium text-text-muted">
            {output.locale_label}
          </span>
        ) : null}
      </span>

      {output.public_url === null ? (
        <span className="text-[13px] text-text-faint">{t('jobs.published.output_missing')}</span>
      ) : (
        <a
          href={output.public_url}
          target="_blank"
          rel="noreferrer"
          dir="ltr"
          className="max-w-full truncate text-[13px] text-primary underline-offset-4 hover:underline"
        >
          {output.public_url}
        </a>
      )}
    </li>
  );
}

function Fact({
  label, value, hint, children,
}: {
  label: string;
  value: string;
  hint?: string;
  children?: ReactNode;
}) {
  return (
    <div>
      <dt className="text-[13px] text-text-faint">{label}</dt>
      <dd className="mt-0.5 nums-tabular text-[15px] text-text">{value}</dd>
      {children}
      {hint !== undefined ? <p className="mt-1 text-[12px] text-text-faint">{hint}</p> : null}
    </div>
  );
}

/**
 * أسطرُ التفصيل تحت العدد — وما لا يُضيف لا يُعرض.
 *
 * @return string[]
 */
function detail(views: Views): string[] {
  const lines: string[] = [];

  if (views.recent !== views.total) {
    lines.push(toArabicIndic(t('jobs.published.visits_recent', { count: views.recent })));
  }

  return lines;
}

/** اليوم بتقويم من يقرأ — والخادم لا يعرف ساعته. */
function day(iso: string | null): string {
  if (iso === null) {
    return '—';
  }

  const at = new Date(iso);

  return Number.isNaN(at.getTime())
    ? '—'
    : at.toLocaleDateString('ar', { year: 'numeric', month: 'long', day: 'numeric' });
}

/**
 * اختبارُ الفهم — T-195، وتقريرُه في T-201: حالُه ومشاركوه ومتوسّطُ درجتهم،
 * ومدخلا إدارته وتقريره.
 */
function QuizCard({ jobId, quiz }: { jobId: number; quiz: Props['quiz'] }) {
  const linkClass = 'inline-flex shrink-0 items-center gap-2 rounded px-4 py-2 text-[15px] font-medium text-primary transition-colors hover:bg-surface-alt';

  const state = quiz === null
    ? t('quiz.panel.empty')
    : quiz.state === 'failed'
      ? t('quiz.panel.failed_title')
      : [
        quiz.status === 'open' ? t('quiz.panel.status_open') : t('quiz.panel.status_closed'),
        quiz.participants > 0
          ? prose(t('quiz.reports.summary_card', { count: quiz.participants, average: quiz.average ?? 0 }))
          : t('quiz.reports.none'),
      ].join('، ');

  return (
    <Card title={t('quiz.panel.title')}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-[14px] text-text-muted">{state}</p>
        <div className="flex flex-wrap gap-1">
          {quiz !== null && quiz.state === 'ready' ? (
            <Link href={`/panel/quizzes/${quiz.id}`} className={linkClass}>
              <Icon name="list" size={16} />
              {t('quiz.reports.report')}
            </Link>
          ) : null}
          <Link href={`/panel/jobs/${jobId}/quiz`} className={linkClass}>
            <Icon name="check" size={16} />
            {quiz === null ? t('quiz.panel.build') : t('quiz.panel.manage')}
          </Link>
        </div>
      </div>
    </Card>
  );
}
