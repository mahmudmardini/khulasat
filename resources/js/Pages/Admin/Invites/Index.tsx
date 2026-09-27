import { router } from '@inertiajs/react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';
import { plural } from '@/lib/plural';

interface Row {
  id: number;
  /** رسالةُ تواصلٍ أم طلبُ تجربةٍ بمحاضرة — T-158. */
  kind: 'contact' | 'lecture';
  name: string;
  /** الصفةُ في طلب التجربة وحده، ورسالةُ التواصل لا تسأل عنها. */
  role: string | null;
  contact: string;
  link: string | null;
  /** ما كتبه صاحبُ الطلب بنفسه — T-142، واختياريّ. */
  message: string | null;
  created_at: string | null;
  handled_at: string | null;
}

interface Props {
  requests: { data: Row[]; current_page: number; last_page: number; total: number };
  filters: { pending: boolean };
  counts: { pending: number };
}

/**
 * طلبات الدعوة — T-135، والنصفُ الغائب من T-113.
 *
 * ★ **وصندوقٌ يُفرَّغ لا قائمةٌ تُقرأ** — كشاشة الاعتراضات (T-28). فالمنتظِرُ
 * يتصدّر، والتعليمُ من موضعه بلا انتقالٍ إلى شاشةٍ أخرى.
 *
 * **وصاحبُ الطلب غريبٌ لا حساب له**: لا لوحةَ يشكو فيها ولا بريدَ نظاميّاً
 * يصله منّا، فوسيلةُ تواصله هي كلُّ ما نملك — ولذلك تُعرض قابلةً للنسخ
 * بـ`dir="ltr"`: بريدٌ أو رقمٌ لاتينيّ في صفحةٍ عربية ينقلب بلا هذا.
 */
export default function Invites({ requests, filters, counts }: Props) {
  return (
    <AdminLayout title={t('admin.invites.title')} description={t('admin.invites.subtitle')}>
      <div className="flex flex-col gap-5">
        {/*
          **والمنتظِرُ يُصدَّر ولا يُذكر في عدّ جامع.** «أربعون طلباً» لا تقول
          أنّ ثلاثةً منها لم يُجَب، وهذه الثلاثةُ هي العمل.
        */}
        {counts.pending > 0 ? (
          <div className="flex items-center gap-3 rounded-lg border border-accent/35 bg-accent/8 px-4 py-3">
            <Icon name="bell" size={19} className="shrink-0 text-accent" />
            <p className="text-[15px] font-medium text-text">
              {plural('admin.invites.pending', counts.pending)}
            </p>
          </div>
        ) : null}

        <div className="flex flex-wrap items-center gap-2">
          <Chip
            active={!filters.pending}
            onClick={() => router.get('/admin/invites', {}, { preserveState: true, replace: true })}
          >
            {t('admin.invites.all')}
          </Chip>
          <Chip
            active={filters.pending}
            onClick={() =>
              router.get('/admin/invites', { pending: '1' }, { preserveState: true, replace: true })
            }
          >
            {t('admin.invites.only_pending')}
          </Chip>
        </div>

        {requests.data.length === 0 ? (
          <Card>
            <EmptyState
              title={t(filters.pending ? 'admin.invites.empty_filtered' : 'admin.invites.empty')}
              body={t(filters.pending ? 'admin.invites.empty_filtered_body' : 'admin.invites.empty_body')}
            />
          </Card>
        ) : (
          <ul className="flex flex-col gap-4">
            {requests.data.map((row) => (
              // المعالَجُ يخفت ولا يُخفى: يبقى مقروءاً للمراجعة، ولا يزاحم المنتظِر.
              <li key={row.id} className={cn(row.handled_at !== null && 'opacity-70')}>
                <RequestCard row={row} />
              </li>
            ))}
          </ul>
        )}

        {requests.last_page > 1 ? (
          <nav className="flex items-center justify-between gap-3 text-[14px] text-text-muted">
            <span>
              {toArabicIndic(`${requests.current_page} ${t('common.table.of')} ${requests.last_page}`)}
            </span>
            <span className="flex gap-2">
              <Button
                variant="secondary"
                disabled={requests.current_page <= 1}
                onClick={() => page(filters, requests.current_page - 1)}
              >
                {t('common.actions.back')}
              </Button>
              <Button
                variant="secondary"
                disabled={requests.current_page >= requests.last_page}
                onClick={() => page(filters, requests.current_page + 1)}
              >
                {t('common.actions.next')}
              </Button>
            </span>
          </nav>
        ) : null}
      </div>
    </AdminLayout>
  );
}

function RequestCard({ row }: { row: Row }) {
  const handled = row.handled_at !== null;

  const toggle = () => {
    router.put(`/admin/invites/${row.id}`, { handled: !handled }, { preserveScroll: true });
  };

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="flex flex-wrap items-center gap-2">
            <span className="text-[15px] font-semibold text-text">{row.name}</span>
            <span className="rounded-full border border-border px-2 py-0.5 text-[12px] text-text-muted">
              {t(`admin.invites.kinds.${row.kind}`)}
            </span>
          </p>
          {row.role !== null && row.role !== '' ? (
            <p className="mt-1 text-[13px] text-text-muted">{row.role}</p>
          ) : null}
        </div>

        <span
          className={cn(
            'shrink-0 rounded-full px-2.5 py-1 text-[12px]',
            handled ? 'bg-success/12 text-success' : 'bg-accent/12 text-accent',
          )}
        >
          {t(handled ? 'admin.invites.handled' : 'admin.invites.pending')}
        </span>
      </div>

      <dl className="mt-4 grid gap-3 text-[13px] sm:grid-cols-2">
        <Fact label={t('admin.invites.contact')} value={row.contact} ltr />
        <Fact
          label={t('admin.invites.received')}
          value={row.created_at === null ? '—' : stamp(row.created_at)}
        />
      </dl>

      {/*
        ★ **رسالةُ صاحب الطلب — T-142، وهي متنٌ لا حقلٌ في شبكة.**

        فما فوقها تصنيفٌ اخترناه له (صفةٌ · وسيلةٌ · تاريخ)، وهذه كلماتُه.
        فتُعرض كتلةً لها أرضُها وحدُّها، ويبقى فيها ما كتبه كما كتبه:
        `whitespace-pre-line` يحفظ الأسطر — **وفقرتان تُلصَقان في سطرٍ
        واحدٍ تُغيّران ما قيل**.

        و`dir="auto"` لا `rtl`: النموذجُ بأربع لغاتٍ منذ T-131، فمن أرسل من
        `/en` كتب لاتينيةً — وهي تنقلب في بطاقةٍ عربيةٍ بلا هذا.
      */}
      {row.message !== null && row.message.trim() !== '' ? (
        <div className="mt-4 rounded-md border border-border bg-surface-alt px-4 py-3">
          <p className="text-[12px] text-text-faint">{t('admin.invites.message')}</p>
          <p dir="auto" className="mt-1.5 whitespace-pre-line wrap-anywhere text-[14px] leading-[1.75] text-text">
            {row.message}
          </p>
        </div>
      ) : null}

      {row.link !== null && row.link !== '' ? (
        <p className="mt-3 text-[13px]">
          <span className="text-[12px] text-text-faint">{t('admin.invites.link')}</span>{' '}
          {/*
            **ورابطُ الغريب `nofollow` و`noreferrer`**: نفتح ما كتبه زائرٌ لا
            نعرفه، فلا نمنحه ثِقلَ إحالةٍ ولا نُخبر موقعَه من أين جاءه الفتح.
          */}
          <a
            href={row.link}
            target="_blank"
            rel="nofollow noopener noreferrer"
            dir="ltr"
            className="wrap-anywhere inline-flex items-start gap-1 text-start text-accent hover:underline"
          >
            {row.link}
            <Icon name="external" size={14} className="mt-0.5 shrink-0" />
          </a>
        </p>
      ) : null}

      <div className="mt-4 flex justify-end">
        <Button variant="secondary" onClick={toggle}>
          <Icon name={handled ? 'clock' : 'check'} size={16} />
          {t(handled ? 'admin.invites.mark_pending' : 'admin.invites.mark_handled')}
        </Button>
      </div>
    </Card>
  );
}

function Fact({ label, value, ltr = false }: { label: string; value: string; ltr?: boolean }) {
  return (
    <div>
      <dt className="text-[12px] text-text-faint">{label}</dt>
      <dd
        dir={ltr ? 'ltr' : undefined}
        className={cn('wrap-anywhere text-[14px] text-text', ltr && 'text-start')}
      >
        {value}
      </dd>
    </div>
  );
}

function Chip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        'rounded-full border px-3 py-1 text-[13px] transition-colors',
        active
          ? 'border-accent bg-accent/10 text-accent'
          : 'border-border text-text-muted hover:border-accent/40',
      )}
    >
      {children}
    </button>
  );
}

/** الترقيم يحفظ التصفية: صفحةٌ ثانية بلا `pending` تُرجع الكلّ بلا أن يطلبه أحد. */
function page(filters: Props['filters'], to: number): void {
  router.get('/admin/invites', {
    ...(filters.pending ? { pending: '1' } : {}),
    page: String(to),
  });
}

function stamp(iso: string): string {
  return new Date(iso).toLocaleString('ar-EG-u-nu-latn', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}
