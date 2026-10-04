import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { CostConfirm } from '@/Components/CostConfirm';
import { EmptyState } from '@/Components/EmptyState';
import { Icon } from '@/Components/Icon';
import { SlideBody } from '@/Components/SlideBody';
import { forPost } from '@/lib/ayah';
import { cn } from '@/lib/cn';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';
import type { SharedProps } from '@/types/inertia';

interface Slide {
  index: number;
  kind: string;
  heading: string;
  body: string;
  source_line: string | null;
  anchored: boolean;
}

interface Props {
  job: { id: number; title: string | null; state: string; pending_evidence: number; locked: boolean };
  carousel: {
    slides: Slide[];
    plain_text: string;
    public_url: string | null;
    rendered_at: string | null;
    renderer_version: string;
  } | null;
}

/**
 * شرائح إنستغرام — SCREENS.md §6، والمهمّة T-19.
 *
 * وشقّان: **المعاينة بالقالب الحقيقي** في إطار — كما في شاشة الهوية، فلا
 * تُرضي محاكاةٌ ثمّ يخالف المنشور — **ونصوصُ الشرائح للنسخ**، لأنّ الصور
 * (T-20) لم تُبنَ بعد، ومن أراد النشر اليوم نسخ النصّ ورفع تصميمه.
 *
 * **والفرق الذي يجب أن يظهر بوضوح** (SCREENS.md §6): إعادةُ الرسم مجّانية،
 * وطلبُ نصٍّ جديد هو وحده ما يُصرف عليه.
 */
export default function Carousel({ job, carousel }: Props) {
  const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
  const [busy, setBusy] = useState(false);
  // بناءُ النصّ وإعادةُ صياغته نداءان للنموذج، فيُقرّان بخطوةٍ ثانية — T-203.
  // وإعادةُ الرسم من النصّ المحفوظ مجّانيّةٌ بلا نافذة.
  const [confirming, setConfirming] = useState<'build' | 'recondense' | null>(null);

  function build(recondense: boolean): void {
    setBusy(true);
    router.post(
      `/panel/jobs/${job.id}/carousel`,
      { recondense },
      { preserveScroll: true, onFinish: () => setBusy(false) },
    );
  }

  return (
    <AppLayout title={t('jobs.carousel.title')} description={job.title ?? undefined}>
      <div className="flex flex-col gap-5">
        {errors.carousel !== undefined ? (
          <p className="rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger">
            {errors.carousel}
          </p>
        ) : null}

        {job.locked ? (
          <Card>
            {/*
              **معطَّلة لا مخفيّة** — SCREENS.md §3-ب. ولا يُخفى ما لا
              يملكه المستخدم: رؤيتُه دافعُ الترقية، وإخفاؤه يمنع معرفته.
            */}
            <EmptyState
              title={t('jobs.carousel.locked')}
              body={t('jobs.carousel.locked_body')}
              action={
                <Button variant="secondary" onClick={() => router.visit('/panel/settings/billing')}>
                  {t('jobs.carousel.locked_cta')}
                </Button>
              }
            />
          </Card>
        ) : job.pending_evidence > 0 ? (
          <Card>
            <EmptyState
              title={t('jobs.carousel.blocked')}
              body={t('jobs.carousel.blocked_body')}
              action={
                <Button onClick={() => router.visit(`/panel/jobs/${job.id}/review`)}>
                  {t('jobs.follow.review_cta')}
                </Button>
              }
            />
          </Card>
        ) : carousel === null ? (
          <Card>
            <EmptyState
              title={t('jobs.carousel.empty')}
              body={t('jobs.carousel.empty_body')}
              action={
                <Button loading={busy} onClick={() => setConfirming('build')}>
                  <Icon name="grid" size={16} />
                  {t('jobs.carousel.build')}
                </Button>
              }
            />
          </Card>
        ) : (
          <Deck job={job} carousel={carousel} busy={busy} onBuild={build} onRecondense={() => setConfirming('recondense')} />
        )}
      </div>

      <CostConfirm
        open={confirming !== null}
        title={t(confirming === 'recondense' ? 'jobs.carousel.recondense' : 'jobs.carousel.build')}
        action={t(confirming === 'recondense' ? 'jobs.carousel.recondense_action' : 'jobs.carousel.build_action')}
        replaces={confirming === 'recondense'}
        confirmLabel={t(confirming === 'recondense' ? 'jobs.carousel.recondense' : 'jobs.carousel.build')}
        onConfirm={() => {
          const recondense = confirming === 'recondense';
          setConfirming(null);
          build(recondense);
        }}
        onCancel={() => setConfirming(null)}
      />
    </AppLayout>
  );
}

function Deck({
  job, carousel, busy, onBuild, onRecondense,
}: {
  job: Props['job'];
  carousel: NonNullable<Props['carousel']>;
  busy: boolean;
  onBuild: (recondense: boolean) => void;
  onRecondense: () => void;
}) {
  return (
    <>
      <Card
        title={t('jobs.carousel.preview')}
        action={
          <span className="nums-tabular text-[13px] text-text-muted">
            {toArabicIndic(t('jobs.carousel.count', { count: carousel.slides.length }))}
          </span>
        }
        flush
      >
        {/*
          **المعاينة بالقالب الحقيقي لا بمحاكاة** — الشاشة 8 قالت ذلك في
          الهوية، والعلّة هنا هي هي: من رأى محاكاةً ثمّ خالفها المنشور فقد
          الثقة في المعاينة كلِّها، وصار ينشر ليرى.
        */}
        <iframe
          src={`/panel/jobs/${job.id}/carousel/preview`}
          title={t('jobs.carousel.preview')}
          className="h-[520px] w-full rounded-b-lg border-0 bg-surface-alt"
        />
      </Card>

      <div className="flex flex-wrap items-center gap-3">
        <CopyButton text={carousel.plain_text} label={t('jobs.carousel.copy_all')} variant="primary" />

        {carousel.public_url !== null ? (
          <Button variant="secondary" onClick={() => window.open(carousel.public_url ?? '', '_blank')}>
            <Icon name="external" size={16} />
            {t('jobs.carousel.open')}
          </Button>
        ) : null}

        <Button variant="secondary" loading={busy} onClick={() => onBuild(false)}>
          {t('jobs.carousel.rebuild')}
        </Button>

        <Button
          variant="ghost"
          loading={busy}
          onClick={onRecondense}
        >
          {t('jobs.carousel.recondense')}
        </Button>
      </div>

      {/*
        **كلفةُ كلّ زرٍّ مكتوبة** — T-196. «أعد الإنشاء» مجّانيّ، و«اطلب صياغة
        جديدة» وحده نداءٌ للنموذج، ونصُّه كان في الترجمة ولا يُعرض.
      */}
      <div className="flex flex-col gap-1 text-[13px] text-text-faint">
        <span>{t('jobs.carousel.rebuild_hint')}</span>
        <span>{t('jobs.carousel.recondense_hint')}</span>
      </div>

      <Card title={t('jobs.carousel.texts')}>
        <ul className="grid gap-4 sm:grid-cols-2">
          {carousel.slides.map((slide) => (
            <SlideCard key={slide.index} slide={slide} />
          ))}
        </ul>
      </Card>
    </>
  );
}

function SlideCard({ slide }: { slide: Slide }) {
  const text = forPost(
    [slide.heading, slide.body, slide.source_line]
      .filter((line): line is string => line !== null && line !== '')
      .join('\n'),
  );

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border bg-surface-alt p-4">
      <div className="flex items-center gap-2">
        <span className="nums-tabular flex size-6 items-center justify-center rounded-full border border-border-strong text-[12px] text-text-muted">
          {toArabicIndic(slide.index)}
        </span>

        <h3 className="min-w-0 flex-1 truncate text-[15px] font-semibold text-text">{slide.heading}</h3>

        {/*
          **المثبَّتة تُعلَّم**: متنُها لفظُ المصدر كاملاً لا صياغةُ التكثيف،
          ومن نسخه فقد نسخ ما تحقّقنا منه — لا ما كُتب عنه.
        */}
        {slide.anchored ? (
          <span
            title={t('jobs.carousel.anchored_hint')}
            className="shrink-0 rounded border border-accent/40 px-1.5 py-0.5 text-[11px] text-accent"
          >
            {t('jobs.carousel.anchored')}
          </span>
        ) : null}
      </div>

      <SlideBody
        kind={slide.kind}
        body={slide.body}
        className={cn('wrap-anywhere text-[14px] leading-relaxed text-text-muted', slide.anchored && 'text-text')}
      />

      {slide.source_line !== null ? (
        <p className="text-[12px] text-text-faint">{slide.source_line}</p>
      ) : null}

      <div className="mt-auto pt-1">
        <CopyButton text={text} label={t('jobs.carousel.copy')} variant="ghost" />
      </div>
    </li>
  );
}

/** النسخ إلى الحافظة، بتأكيدٍ يُرى — فالنسخ فعلٌ بلا أثرٍ ظاهر بغيره. */
function CopyButton({
  text, label, variant,
}: {
  text: string;
  label: string;
  variant: 'primary' | 'ghost';
}) {
  const [done, setDone] = useState(false);

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(text);
      setDone(true);
      window.setTimeout(() => setDone(false), 2000);
    } catch {
      // متصفّحٌ منع الحافظة: النصّ ظاهرٌ في البطاقة ويُحدَّد باليد،
      // فلا تُعرض رسالة خطأ على فعلٍ له بديلٌ أمام عين المستخدم.
    }
  }

  return (
    <Button variant={variant} onClick={copy}>
      <Icon name={done ? 'check' : 'copy'} size={16} />
      {done ? t('jobs.carousel.copied') : label}
    </Button>
  );
}
