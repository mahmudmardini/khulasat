import { useState, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { ConfirmDialog } from '@/Components/ConfirmDialog';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { Segmented } from '@/Components/Segmented';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';
import type { SharedProps } from '@/types/inertia';

interface Option {
  text: string;
  evidence: { kind: string; citation: string } | null;
}

interface Question {
  id: number;
  position: number;
  kind: 'single' | 'true_false' | 'evidence';
  level: 'recall' | 'understanding' | 'application' | null;
  prompt: string;
  explanation: string;
  correct_index: number;
  axis: string | null;
  options: Option[];
}

interface QuizData {
  state: 'ready' | 'failed';
  failure_reason: string | null;
  status: 'open' | 'closed';
  feedback: 'end' | 'immediate';
  url: string;
  attempts: number;
  generated_at: string | null;
  questions: Question[];
}

interface Props {
  job: { id: number; title: string | null; published: boolean; pending_evidence: number; has_structure: boolean };
  can_edit: boolean;
  quiz: QuizData | null;
}

/**
 * اختبارُ الفهم في اللوحة — T-195.
 *
 * **والمحاولاتُ تُقفل البنية**: بعد أوّل محاولةٍ يُعطَّل الحذفُ وإعادةُ
 * التوليد ويُقال السبب، ويبقى تعديلُ النصّ — بالحارس نفسه في الخادم.
 */
export default function Quiz({ job, can_edit, quiz }: Props) {
  const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
  const [busy, setBusy] = useState(false);
  const [confirmRegenerate, setConfirmRegenerate] = useState(false);

  function build(): void {
    setBusy(true);
    router.post(`/panel/jobs/${job.id}/quiz`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  }

  return (
    <AppLayout title={t('quiz.panel.title')} description={job.title ?? undefined}>
      <div className="flex flex-col gap-5">
        {errors.quiz !== undefined ? (
          <p role="alert" className="rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger">
            {errors.quiz}
          </p>
        ) : null}

        {job.pending_evidence > 0 && quiz === null ? (
          <Card>
            <EmptyState
              title={t('quiz.panel.blocked')}
              body={t('quiz.panel.blocked_body')}
              action={
                <Button onClick={() => router.visit(`/panel/jobs/${job.id}/review`)}>
                  {t('jobs.follow.review_cta')}
                </Button>
              }
            />
          </Card>
        ) : quiz === null ? (
          <Card>
            <EmptyState
              title={t('quiz.panel.empty')}
              body={job.has_structure ? t('quiz.panel.empty_body') : t('quiz.panel.no_structure')}
              action={
                can_edit && job.has_structure ? (
                  <Button loading={busy} onClick={build}>
                    <Icon name="check" size={16} />
                    {t('quiz.panel.build')}
                  </Button>
                ) : undefined
              }
            />
          </Card>
        ) : quiz.state === 'failed' ? (
          <Card>
            <EmptyState
              title={t('quiz.panel.failed_title')}
              body={t('quiz.panel.failed_body')}
              action={
                can_edit ? (
                  <Button loading={busy} onClick={build}>
                    {t('quiz.panel.retry')}
                  </Button>
                ) : undefined
              }
            />
          </Card>
        ) : (
          <>
            <LinkCard job={job} quiz={quiz} />
            {can_edit ? <Settings job={job} quiz={quiz} /> : (
              <p className="text-[13px] text-text-muted">{t('quiz.panel.read_only')}</p>
            )}

            {quiz.attempts > 0 ? (
              <p className="flex items-start gap-2 rounded-lg border border-border bg-surface-alt px-4 py-3 text-[13.5px] text-text-muted">
                <Icon name="shield" size={16} className="mt-0.5 shrink-0" />
                {t('quiz.panel.locked_note')}
              </p>
            ) : null}

            <Card
              title={t('quiz.panel.questions_title')}
              action={
                <span className="nums-tabular text-[13px] text-text-muted">
                  {toArabicIndic(quiz.questions.length)}
                </span>
              }
            >
              <ol className="flex flex-col gap-4">
                {quiz.questions.map((question) => (
                  <QuestionCard
                    key={question.id}
                    job={job}
                    question={question}
                    canEdit={can_edit}
                    locked={quiz.attempts > 0}
                    error={errors[`question.${question.id}`]}
                  />
                ))}
              </ol>
            </Card>

            {can_edit ? (
              <div className="flex flex-col gap-1.5">
                <div>
                  <Button
                    variant="ghost"
                    loading={busy}
                    disabled={quiz.attempts > 0}
                    onClick={() => setConfirmRegenerate(true)}
                  >
                    {t('quiz.panel.regenerate')}
                  </Button>
                </div>
                <span className="text-[13px] text-text-faint">
                  {quiz.attempts > 0 ? t('quiz.panel.locked_regenerate') : t('quiz.panel.regenerate_consequence')}
                </span>
              </div>
            ) : null}

            <ConfirmDialog
              open={confirmRegenerate}
              title={t('quiz.panel.regenerate')}
              consequence={t('quiz.panel.regenerate_consequence')}
              confirmLabel={t('quiz.panel.regenerate')}
              onConfirm={() => {
                setConfirmRegenerate(false);
                build();
              }}
              onCancel={() => setConfirmRegenerate(false)}
            />
          </>
        )}
      </div>
    </AppLayout>
  );
}

function LinkCard({ job, quiz }: { job: Props['job']; quiz: QuizData }) {
  const [copied, setCopied] = useState(false);

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(quiz.url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // الرابطُ ظاهرٌ في الحقل ويُحدَّد باليد.
    }
  }

  return (
    <Card
      title={t('quiz.panel.link')}
      action={
        <span className="text-[13px] text-text-muted">
          {toArabicIndic(t('quiz.panel.attempts', { count: quiz.attempts }))}
        </span>
      }
    >
      <div className="flex flex-col gap-3">
        <input
          readOnly
          value={quiz.url}
          dir="ltr"
          aria-label={t('quiz.panel.link')}
          onFocus={(event) => event.currentTarget.select()}
          className="nums-tabular w-full rounded-lg border border-border bg-surface-alt px-3 py-2.5 text-[14px] text-text"
        />
        <div className="flex flex-wrap gap-2">
          <Button onClick={copy}>
            <Icon name={copied ? 'check' : 'copy'} size={16} />
            {copied ? t('quiz.panel.copied') : t('quiz.panel.copy')}
          </Button>
          <Button variant="secondary" disabled={!job.published} onClick={() => window.open(quiz.url, '_blank')}>
            <Icon name="external" size={16} />
            {t('quiz.panel.open')}
          </Button>
        </div>
        {!job.published ? <p className="text-[13px] text-text-muted">{t('quiz.panel.not_published')}</p> : null}
      </div>
    </Card>
  );
}

function Settings({ job, quiz }: { job: Props['job']; quiz: QuizData }) {
  function save(data: Partial<Pick<QuizData, 'status' | 'feedback'>>): void {
    router.put(`/panel/jobs/${job.id}/quiz`, data, { preserveScroll: true });
  }

  return (
    <Card>
      <div className="grid gap-5 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <span className="text-[14px] font-medium text-text">{t('quiz.panel.status_legend')}</span>
          <Segmented
            legend={t('quiz.panel.status_legend')}
            value={quiz.status}
            options={[
              { key: 'open', label: t('quiz.panel.status_open') },
              { key: 'closed', label: t('quiz.panel.status_closed') },
            ]}
            onChange={(status) => save({ status })}
          />
          <span className="text-[12.5px] text-text-muted">
            {quiz.status === 'open' ? t('quiz.panel.status_hint_open') : t('quiz.panel.status_hint_closed')}
          </span>
        </div>

        <div className="flex flex-col gap-2">
          <span className="text-[14px] font-medium text-text">{t('quiz.panel.feedback_legend')}</span>
          <Segmented
            legend={t('quiz.panel.feedback_legend')}
            value={quiz.feedback}
            options={[
              { key: 'end', label: t('quiz.panel.feedback_end') },
              { key: 'immediate', label: t('quiz.panel.feedback_immediate') },
            ]}
            onChange={(feedback) => save({ feedback })}
          />
        </div>
      </div>
    </Card>
  );
}

function QuestionCard({
  job, question, canEdit, locked, error,
}: {
  job: Props['job'];
  question: Question;
  canEdit: boolean;
  locked: boolean;
  error: string | undefined;
}) {
  const [editing, setEditing] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [prompt, setPrompt] = useState(question.prompt);
  const [explanation, setExplanation] = useState(question.explanation);
  const [options, setOptions] = useState(question.options.map((option) => option.text));
  const [saving, setSaving] = useState(false);
  const editableOptions = question.kind === 'single';

  function save(): void {
    setSaving(true);
    router.put(
      `/panel/jobs/${job.id}/quiz/questions/${question.id}`,
      { prompt, explanation, options: editableOptions ? options : [] },
      {
        preserveScroll: true,
        onSuccess: (page) => {
          const failed = (page.props.errors as Record<string, string> | undefined)?.[`question.${question.id}`];
          if (failed === undefined) {
            setEditing(false);
          }
        },
        onFinish: () => setSaving(false),
      },
    );
  }

  const meta = [
    t(`quiz.panel.kinds.${question.kind}`),
    question.level !== null ? t(`quiz.panel.levels.${question.level}`) : null,
    question.axis !== null ? t('quiz.panel.axis', { axis: question.axis }) : null,
  ].filter((part): part is string => part !== null);

  return (
    <li className="rounded-lg border border-border bg-surface p-4">
      <div className="flex items-start gap-3">
        <span className="nums-tabular flex size-7 shrink-0 items-center justify-center rounded-full border border-border-strong text-[13px] text-text-muted">
          {toArabicIndic(question.position)}
        </span>

        <div className="min-w-0 flex-1">
          <p className="text-[12.5px] text-text-muted">{meta.join('، ')}</p>

          {editing ? (
            <div className="mt-3 flex flex-col gap-3">
              <Field label={t('quiz.panel.prompt_label')}>
                <textarea
                  value={prompt}
                  onChange={(event) => setPrompt(event.target.value)}
                  rows={2}
                  className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-[15px]"
                />
              </Field>

              {editableOptions ? (
                options.map((text, index) => (
                  <Field key={index} label={t('quiz.panel.option_label', { n: toArabicIndic(index + 1) })}>
                    <input
                      value={text}
                      onChange={(event) => setOptions(options.map((old, i) => (i === index ? event.target.value : old)))}
                      className={cn(
                        'w-full rounded-lg border bg-surface px-3 py-2 text-[14px]',
                        index === question.correct_index ? 'border-primary/50' : 'border-border',
                      )}
                    />
                  </Field>
                ))
              ) : (
                <p className="text-[12.5px] text-text-muted">
                  {question.kind === 'evidence' ? t('quiz.panel.evidence_locked') : t('quiz.panel.fixed_options')}
                </p>
              )}

              <Field label={t('quiz.panel.explanation_label')}>
                <textarea
                  value={explanation}
                  onChange={(event) => setExplanation(event.target.value)}
                  rows={2}
                  className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-[14px]"
                />
              </Field>

              {error !== undefined ? <p role="alert" className="text-[13px] text-danger">{error}</p> : null}

              <div className="flex gap-2">
                <Button loading={saving} onClick={save}>{t('quiz.panel.save')}</Button>
                <Button
                  variant="ghost"
                  onClick={() => {
                    setEditing(false);
                    setPrompt(question.prompt);
                    setExplanation(question.explanation);
                    setOptions(question.options.map((option) => option.text));
                  }}
                >
                  {t('quiz.panel.cancel')}
                </Button>
              </div>
            </div>
          ) : (
            <>
              <p className="mt-1.5 text-[15.5px] font-medium leading-7 text-text">{question.prompt}</p>

              <ul className="mt-3 flex flex-col gap-1.5">
                {question.options.map((option, index) => {
                  const correct = index === question.correct_index;

                  return (
                    <li
                      key={index}
                      className={cn(
                        'flex items-start gap-2 rounded-md border px-3 py-2 text-[14px]',
                        correct ? 'border-primary/45 bg-primary/5 text-text' : 'border-border text-text-muted',
                      )}
                    >
                      <Icon name={correct ? 'check' : 'close'} size={14} className={cn('mt-1 shrink-0', correct ? 'text-primary' : 'text-text-faint')} />
                      <span className="min-w-0 flex-1">
                        <span className={option.evidence?.kind === 'ayah' ? 'font-quran' : undefined}>{option.text}</span>
                        {option.evidence !== null && option.evidence.citation !== '' ? (
                          <span className="block text-[12px] text-text-faint">{option.evidence.citation}</span>
                        ) : null}
                      </span>
                      {correct ? <span className="shrink-0 text-[12px] font-medium text-primary">{t('quiz.panel.correct')}</span> : null}
                    </li>
                  );
                })}
              </ul>

              <p className="mt-3 border-s-2 border-border-strong ps-3 text-[13.5px] text-text-muted">{question.explanation}</p>

              {canEdit ? (
                <div className="mt-3 flex gap-2">
                  <Button variant="secondary" onClick={() => setEditing(true)}>
                    <Icon name="pen" size={14} />
                    {t('quiz.panel.edit')}
                  </Button>
                  <Button variant="danger-soft" disabled={locked} onClick={() => setConfirmDelete(true)}>
                    {t('quiz.panel.delete')}
                  </Button>
                </div>
              ) : null}
            </>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={confirmDelete}
        title={t('quiz.panel.delete_title')}
        consequence={t('quiz.panel.delete_consequence', { n: toArabicIndic(question.position) })}
        confirmLabel={t('quiz.panel.delete')}
        onConfirm={() => {
          setConfirmDelete(false);
          router.delete(`/panel/jobs/${job.id}/quiz/questions/${question.id}`, { preserveScroll: true });
        }}
        onCancel={() => setConfirmDelete(false)}
      />
    </li>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium text-text">{label}</span>
      {children}
    </label>
  );
}
