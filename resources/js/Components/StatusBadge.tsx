import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';

export type JobStatus =
  | 'queued' | 'transcribing' | 'cleaning' | 'structuring' | 'extracting'
  | 'verifying' | 'writing' | 'rendering'
  | 'needs_review' | 'published' | 'failed' | 'unpublished';

/**
 * حالة المهمّة — SCREENS.md §الحالات وألوانها.
 *
 * **`needs_review` وحدها بخلفية ملوّنة لا شارة فقط**، «هي الحالة الوحيدة
 * التي تطلب فعلاً من المستخدم». وهذا وزنٌ بصريّ مقصود: من يفتح الفهرس
 * يجب أن تقع عينه عليها قبل غيرها.
 *
 * **ونقطةٌ قبل النصّ منذ T-27** — إلّا في `needs_review`، فخلفيّتُها
 * الملوّنة تُغني. وبلا النقطة كانت الشارات تُقرأ أزراراً تُنقر: إطارٌ
 * ونصٌّ وحشوةٌ هي هيئة الزرّ نفسها. والنقطة تقول «هذا خبرٌ لا فعل».
 */
const TONES: Record<JobStatus, string> = {
  queued: 'text-info border-info/25 bg-info/6',
  transcribing: 'text-info border-info/25 bg-info/6',
  cleaning: 'text-info border-info/25 bg-info/6',
  structuring: 'text-info border-info/25 bg-info/6',
  extracting: 'text-info border-info/25 bg-info/6',
  verifying: 'text-info border-info/25 bg-info/6',
  writing: 'text-info border-info/25 bg-info/6',
  rendering: 'text-info border-info/25 bg-info/6',
  needs_review: 'text-white border-warning bg-warning font-semibold',
  published: 'text-success border-success/25 bg-success/6',
  failed: 'text-danger border-danger/25 bg-danger/6',
  unpublished: 'text-text-faint border-border bg-surface-alt',
};

/** المراحل الجارية تنبض، فيُعرف الواقفُ من المتحرّك بلا قراءة. */
const RUNNING: ReadonlySet<JobStatus> = new Set([
  'queued', 'transcribing', 'cleaning', 'structuring',
  'extracting', 'verifying', 'writing', 'rendering',
]);

export function StatusBadge({ status }: { status: JobStatus }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] whitespace-nowrap',
        TONES[status],
      )}
    >
      {status === 'needs_review' ? null : (
        <span
          aria-hidden="true"
          className={cn(
            'size-1.5 rounded-full bg-current',
            RUNNING.has(status) && 'animate-pulse',
          )}
        />
      )}

      {t(`jobs.status.${status}`)}
    </span>
  );
}
