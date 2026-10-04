import { useEffect, useRef, useState, type RefObject } from 'react';
import { Link, router, usePage, usePoll } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { AddLocale, type LocaleAddition } from '@/Components/AddLocale';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { CostConfirm } from '@/Components/CostConfirm';
import { DeviceFrame, type Device } from '@/Components/DeviceFrame';
import { EmptyState } from '@/Components/EmptyState';
import { Icon, type IconName } from '@/Components/Icon';
import { Segmented } from '@/Components/Segmented';
import { SlideBody } from '@/Components/SlideBody';
import { SlideCarousel } from '@/Components/SlideCarousel';
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

/** لغةٌ من لغات النشر، وهل تُرجمت، ورابطُها إن نُشرت — T-84. */
interface LocaleOption {
  key: string;
  label: string;
  native: string;
  direction: 'rtl' | 'ltr';
  translated: boolean;
  /** طُلبت ترجمتُها ولم تنتهِ — T-166. */
  translating: boolean;
  public_url: string | null;
}

interface Props {
  job: {
    id: number;
    title: string | null;
    speaker: string | null;
    state: string;
    pending_evidence: number;
    renderable: boolean;
    published: boolean;
    unpublished: boolean;
    public_url: string | null;
  };
  outputs: {
    page: { produced: boolean; public_url: string | null };
    carousel: { produced: boolean; slides: Slide[]; plain_text: string };
    /** حزمة الصور — T-173. `state` حالُها في الطابور، و`urls` صورُها بترتيبها. */
    images: {
      produced: boolean;
      public_url: string | null;
      state: 'rendering' | 'ready' | 'failed' | null;
      error: string | null;
      enabled: boolean;
      urls: string[];
      /** قوالبُ الجهة المعتمدة — T-173. وأوّلُها افتراضيُّها. */
      designs: Array<{ id: string; name: string | null }>;
      design: string | null;
      /** ما التُقط من كم أثناء الإنشاء — T-197. */
      progress: { done: number; total: number } | null;
      /** صورٌ أقدمُ من شرائحها (صياغةٌ أو قالبٌ بعدها) — T-204. */
      stale: boolean;
    };
  };
  regenerations: { used: number; limit: number };
  can_publish: boolean;
  rich_outputs: boolean;
  locales: LocaleOption[];
  primary_locale: string;
  locale_additions: LocaleAddition[];
}

// تبويبان — T-199: الصورُ في تبويب الشرائح تحت عارضها، لا تبويباً ثالثاً يكرّرها.
type TabKey = 'page' | 'carousel';

const DEVICE_ICONS: Record<Device, IconName> = { mobile: 'phone', tablet: 'tablet', desktop: 'desktop' };
const DEVICE_KEY = 'khulasah.preview.device';

/**
 * معاينة ونشر — SCREENS.md §6، والمهمّة T-30، وأُعيد تصميمها في T-84.
 *
 * ★ **والفرق الذي يجب أن يظهر بوضوح فرقُ الكلفة** (§6): «إضافة مخرَجٍ جديد
 * لا تُحتسب من الحصة، وأعد التوليد وحده يُحتسب **ويبيّن المتبقّي قبل
 * التنفيذ**». فالزرّان لا يقفان متجاورين متساويَي الوزن.
 *
 * **وما تغيّر في T-84 — من تدقيق T-82:**
 * - المعاينة **بلغات النشر** لا بالعربية وحدها، ومبدّلٌ يقلب الاتّجاه معها.
 * - **أجهزةٌ بعرضها الحقيقي** (جوال · لوح · حاسوب) لا إطارٌ مضيَّق.
 * - **«انشر» في رأس الشاشة**، وكان تحت إطارٍ بارتفاع ٦٢٠ فيقع تحت الطيّة.
 * - نسخُ الرابط والمشاركة وPDF وHTML **فوق المعاينة**، لا خلف شاشةٍ أخرى.
 */
export default function Preview({
  job, outputs, regenerations, can_publish, rich_outputs, locales, primary_locale, locale_additions,
}: Props) {
  const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
  const [tab, setTab] = useState<TabKey>('page');
  const [busy, setBusy] = useState(false);
  const [confirming, setConfirming] = useState(false);
  // بناءُ الشرائح نداءٌ للنموذج، فيُقرّ بخطوةٍ ثانية — T-203.
  const [confirmingCarousel, setConfirmingCarousel] = useState(false);
  const [confirmingRecondense, setConfirmingRecondense] = useState(false);

  const left = Math.max(0, regenerations.limit - regenerations.used);

  // حزمة الصور تُنشأ في الطابور — T-173. فتتحدّث الشاشة وحدها ما دامت جارية.
  const rendering = outputs.images.state === 'rendering';
  const poll = usePoll(3_000, { only: ['outputs'] }, { autoStart: false, keepAlive: false });

  useEffect(() => {
    if (rendering) {
      poll.start();
    } else {
      poll.stop();
    }

    return () => poll.stop();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rendering]);

  function publish(): void {
    setBusy(true);
    router.post(`/panel/summaries/${job.id}`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
  }

  function buildCarousel(): void {
    setBusy(true);
    router.post(
      `/panel/jobs/${job.id}/carousel/build`,
      { recondense: false },
      { preserveScroll: true, onFinish: () => setBusy(false) },
    );
  }

  // صياغةٌ جديدة لنصوص الشرائح — نداءٌ للنموذج يُبدِّل ما قبله (T-203)، وكان في
  // صفحة الشرائح المحذوفة (T-204).
  function recondense(): void {
    setBusy(true);
    router.post(
      `/panel/jobs/${job.id}/carousel/build`,
      { recondense: true },
      { preserveScroll: true, onFinish: () => setBusy(false) },
    );
  }

  function buildImages(design: string | null): void {
    setBusy(true);
    router.post(
      `/panel/jobs/${job.id}/images`,
      design === null ? {} : { design },
      { preserveScroll: true, onFinish: () => setBusy(false) },
    );
  }

  function regenerate(): void {
    setConfirming(false);
    setBusy(true);
    router.post(`/panel/jobs/${job.id}/retry`, {}, { onFinish: () => setBusy(false) });
  }

  return (
    <AppLayout
      title={t('jobs.preview.title')}
      description={job.title ?? t('jobs.preview.subtitle')}
      action={
        <div className="flex flex-wrap items-center gap-2">
          <LiveChip job={job} />
          {job.renderable && can_publish ? (
            <Button loading={busy} onClick={publish} title={t('jobs.preview.publish_hint')}>
              <Icon name="upload" size={16} />
              {t(job.published ? 'jobs.preview.republish' : 'jobs.preview.publish')}
            </Button>
          ) : null}
        </div>
      }
    >
      <div className="flex flex-col gap-5">
        {Object.entries(errors).map(([key, message]) => (
          <p
            key={key}
            className="rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger"
          >
            {message}
          </p>
        ))}

        {job.renderable ? (
          <>
            <Tabs
              active={tab}
              onChange={setTab}
              produced={{
                page: outputs.page.produced,
                carousel: outputs.carousel.produced,
              }}
            />

            {tab === 'page' ? (
              <PagePane
                job={job}
                locales={locales}
                primary={primary_locale}
                additions={can_publish ? locale_additions : []}
              />
            ) : null}
            {tab === 'carousel' ? (
              <CarouselPane
                job={job}
                carousel={outputs.carousel}
                images={outputs.images}
                rich={rich_outputs}
                busy={busy}
                onBuild={() => setConfirmingCarousel(true)}
                onBuildImages={buildImages}
                onRecondense={() => setConfirmingRecondense(true)}
              />
            ) : null}

            <Actions
              job={job}
              carouselProduced={outputs.carousel.produced}
              rich={rich_outputs}
              canPublish={can_publish}
              busy={busy}
              left={left}
              onBuildCarousel={() => setConfirmingCarousel(true)}
              onRegenerate={() => setConfirming(true)}
            />
          </>
        ) : (
          <Card>
            {job.pending_evidence > 0 ? (
              <EmptyState
                title={t('jobs.preview.blocked')}
                body={t('jobs.preview.blocked_body')}
                action={
                  <Button onClick={() => router.visit(`/panel/jobs/${job.id}/review`)}>
                    {t('jobs.follow.review_cta')}
                  </Button>
                }
              />
            ) : (
              <EmptyState
                title={t('jobs.preview.not_ready')}
                body={t('jobs.preview.not_ready_body')}
                action={
                  <Button variant="secondary" onClick={() => router.visit(`/panel/jobs/${job.id}`)}>
                    {t('jobs.follow.title')}
                  </Button>
                }
              />
            )}
          </Card>
        )}
      </div>

      {/*
        **إقرارُ الكلفة بأرقامها** — T-203: إعادةٌ من إعادات الملخّص، وملخّصٌ من
        الحصّة الشهرية (كلاهما يُحتسب)، وكم يبقى بعد كلٍّ منهما.
      */}
      <CostConfirm
        open={confirming}
        title={t('jobs.preview.regenerate')}
        action={t('jobs.preview.regenerate_action')}
        regenerations={regenerations}
        monthly
        confirmLabel={t('jobs.preview.regenerate')}
        onConfirm={regenerate}
        onCancel={() => setConfirming(false)}
      />

      <CostConfirm
        open={confirmingRecondense}
        title={t('jobs.carousel.recondense')}
        action={t('jobs.carousel.recondense_action')}
        replaces
        confirmLabel={t('jobs.carousel.recondense')}
        onConfirm={() => {
          setConfirmingRecondense(false);
          recondense();
        }}
        onCancel={() => setConfirmingRecondense(false)}
      />

      <CostConfirm
        open={confirmingCarousel}
        title={t('jobs.preview.add_carousel')}
        action={t('jobs.carousel.build_action')}
        confirmLabel={t('jobs.preview.add_carousel')}
        onConfirm={() => {
          setConfirmingCarousel(false);
          buildCarousel();
        }}
        onCancel={() => setConfirmingCarousel(false)}
      />
    </AppLayout>
  );
}

/** أمنشورٌ الآن أم لا — وهو أوّل ما يُسأل عنه في شاشة معاينة. */
function LiveChip({ job }: { job: Props['job'] }) {
  const live = job.published;

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[13px]',
        live ? 'border-success/35 bg-success/8 text-success' : 'border-border bg-surface-alt text-text-faint',
      )}
    >
      <Icon name={live ? 'check' : 'clock'} size={15} />
      {t(live ? 'jobs.preview.live' : 'jobs.preview.not_live')}
    </span>
  );
}

/**
 * تبويبات بعدد المخرجات — §6.
 *
 * **والتبويب الذي لم يُنتَج يبقى ظاهراً** كأيقونات الفهرس الرمادية (§2):
 * إخفاؤه يُخفي أنّ المنتج يُخرج شيئاً آخر أصلاً.
 */
function Tabs({
  active, onChange, produced,
}: {
  active: TabKey;
  onChange: (key: TabKey) => void;
  produced: Record<TabKey, boolean>;
}) {
  const tabs: Array<{ key: TabKey; icon: IconName }> = [
    { key: 'page', icon: 'page' },
    { key: 'carousel', icon: 'carousel' },
  ];

  return (
    <div role="tablist" aria-label={t('jobs.preview.title')} className="flex flex-wrap gap-1 border-b border-border">
      {tabs.map((item) => (
        <button
          key={item.key}
          type="button"
          role="tab"
          aria-selected={active === item.key}
          onClick={() => onChange(item.key)}
          className={cn(
            'flex items-center gap-2 rounded-t-md border-b-2 px-4 py-2.5 text-[15px] transition-colors',
            active === item.key
              ? 'border-primary font-medium text-primary'
              : 'border-transparent text-text-muted hover:bg-surface-alt hover:text-text',
          )}
        >
          <Icon name={item.icon} size={17} />
          <span>{t(`jobs.preview.tabs.${item.key}`)}</span>

          {/* الرمادي يعني غير مُنتَج — §2، والاصطلاح نفسه هنا. */}
          {produced[item.key] ? null : (
            <span className="rounded border border-border px-1.5 py-0.5 text-[11px] text-text-faint">
              {t('jobs.published.output_missing')}
            </span>
          )}
        </button>
      ))}
    </div>
  );
}

/**
 * الصفحة في جهاز، بلغتها — §6، وT-84.
 *
 * **وبالقالب الحقيقي لا بمحاكاة** (الشاشة 8): الإطار يعرض ما يرسمه الخادم
 * نفسه، بعرض الجهاز الحقيقي. من رأى محاكاةً ثمّ خالفها المنشور فقد الثقة
 * في المعاينة كلِّها، وصار ينشر ليرى.
 */
function PagePane({
  job, locales, primary, additions,
}: {
  job: Props['job'];
  locales: LocaleOption[];
  primary: string;
  additions: LocaleAddition[];
}) {
  const [device, setDevice] = useState<Device>(rememberedDevice);
  const [locale, setLocale] = useState(primary);
  const frame = useRef<HTMLIFrameElement>(null);

  const current = locales.find((item) => item.key === locale) ?? locales[0];
  const src = `/panel/jobs/${job.id}/preview/page?locale=${encodeURIComponent(locale)}`;

  return (
    <Card flush>
      <div className="flex flex-col gap-3 border-b border-border px-4 py-3 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex flex-wrap items-center gap-2">
          <Segmented
            legend={t('jobs.preview.device.legend')}
            value={device}
            onChange={(next) => {
              setDevice(next);
              rememberDevice(next);
            }}
            options={(['mobile', 'tablet', 'desktop'] as const).map((key) => ({
              key,
              label: t(`jobs.preview.device.${key}`),
              icon: DEVICE_ICONS[key],
            }))}
          />

          {/* لغةٌ واحدة لا مبدّل لها: خيارٌ واحد ليس اختياراً. */}
          {locales.length > 1 ? (
            <Segmented
              legend={t('jobs.preview.locale.legend')}
              value={locale}
              onChange={setLocale}
              options={locales.map((item) => ({
                key: item.key,
                label: item.label,
                note: item.translated
                  ? undefined
                  : t(item.translating ? 'jobs.add_locale.translating_short' : 'jobs.preview.locale.untranslated'),
              }))}
            />
          ) : null}
        </div>

        <QuickActions
          jobId={job.id}
          title={job.title ?? ''}
          locale={locale}
          url={current?.public_url ?? null}
          src={src}
          frame={frame}
        />
      </div>

      {/*
        «أضف لغة» — T-166. **تحت المبدّل لا بعيداً عنه**: من نظر في لغات
        المعاينة فوجد ما ينقصه، وجد إضافتَه في موضعه.
      */}
      {additions.length > 0 ? (
        <div className="border-b border-border px-4 py-3">
          <AddLocale
            jobId={job.id}
            additions={additions}
            published={job.published}
            reload={['locales', 'locale_additions', 'job', 'outputs']}
          />
        </div>
      ) : null}

      {current !== undefined && !current.translated ? (
        <p role="status" className="flex items-center gap-2 border-b border-warning/25 bg-warning/8 px-4 py-2.5 text-[13px] text-text">
          <Icon name="alert" size={16} className="shrink-0 text-warning" />
          {t(current.translating ? 'jobs.add_locale.translating_note' : 'jobs.preview.locale.untranslated_note')}
        </p>
      ) : null}

      {/* **يُعاد تحميلُ الإطار حين تكتمل الترجمة**: الرابطُ لم يتبدّل، فبلا مفتاحٍ يبقى العربيُّ معروضاً. */}
      <DeviceFrame
        key={`${locale}:${current?.translated ? 'ready' : 'pending'}`}
        device={device}
        src={src}
        title={t('jobs.preview.tabs.page')}
        frameRef={frame}
      />
    </Card>
  );
}

/**
 * نسخ الرابط · المشاركة · PDF · HTML · تبويبٌ مستقلّ — T-84.
 *
 * **والرابط رابطُ اللغة المعروضة**: من عاين الإنجليزية ونسخ فقد أراد
 * الإنجليزية. ولغةٌ لم تُنشر لا رابطَ لها، فيُعطَّل الزرّان ويُقال لماذا.
 *
 * و**PDF طباعةُ الإطار نفسه** بـ`contentWindow.print()`: القالب مضبوطٌ
 * للطباعة أصلاً (CLAUDE.md §2 القاعدة الأولى)، فلا مولِّد PDF في الخادم ولا
 * نسخةٌ ثانية من الصفحة تفترق عن الأولى.
 */
function QuickActions({
  jobId, title, locale, url, src, frame,
}: {
  jobId: number;
  title: string;
  locale: string;
  url: string | null;
  src: string;
  frame: RefObject<HTMLIFrameElement | null>;
}) {
  const [copied, setCopied] = useState(false);
  const missing = url === null ? t('jobs.preview.quick.needs_publish') : undefined;

  async function copy(): Promise<void> {
    if (url === null) {
      return;
    }

    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // متصفّحٌ منع الحافظة: الرابط في «إدارة النشر» ظاهرٌ ويُحدَّد باليد.
    }
  }

  async function share(): Promise<void> {
    if (url === null) {
      return;
    }

    if (typeof navigator.share === 'function') {
      try {
        await navigator.share({ title, url });
      } catch {
        // أغلق المستخدم نافذة المشاركة — ليس خطأً يُقال.
      }
      return;
    }

    await copy();
  }

  const linkClass =
    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium text-text-muted transition-colors hover:bg-surface-alt hover:text-text';

  return (
    <div role="group" aria-label={t('jobs.preview.quick.legend')} className="flex flex-wrap items-center gap-1">
      <QuickButton icon={copied ? 'check' : 'link'} onClick={copy} disabled={url === null} title={missing}>
        {t(copied ? 'jobs.preview.quick.copied' : 'jobs.preview.quick.copy_link')}
      </QuickButton>

      <QuickButton icon="share" onClick={share} disabled={url === null} title={missing}>
        {t('jobs.preview.quick.share')}
      </QuickButton>

      <QuickButton
        icon="printer"
        onClick={() => frame.current?.contentWindow?.print()}
        title={t('jobs.preview.quick.pdf_hint')}
      >
        {t('jobs.preview.quick.pdf')}
      </QuickButton>

      {/*
        التنزيل رابطٌ لا زرّ: الخادم يردّه مرفقاً بـ`Content-Disposition`،
        فالمتصفّح يحفظه بلا شيفرة.
      */}
      <a href={`/panel/jobs/${jobId}/download/page?locale=${encodeURIComponent(locale)}`} className={linkClass}>
        <Icon name="upload" size={15} className="rotate-180" />
        {t('jobs.preview.quick.html')}
      </a>

      <a href={src} target="_blank" rel="noopener" className={linkClass}>
        <Icon name="expand" size={15} />
        {t('jobs.preview.quick.open')}
      </a>
    </div>
  );
}

function QuickButton({
  icon, onClick, disabled = false, title, children,
}: {
  icon: IconName;
  onClick: () => void;
  disabled?: boolean;
  title?: string;
  children: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      title={title}
      className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-[13px] font-medium text-text-muted transition-colors hover:bg-surface-alt hover:text-text disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
    >
      <Icon name={icon} size={15} />
      <span>{children}</span>
    </button>
  );
}

/** الشرائح متجاورةً، لكلٍّ نسخ النصّ، وزرّ نسخ الكلّ — §6. */
function CarouselPane({
  job, carousel, images, rich, busy, onBuild, onBuildImages, onRecondense,
}: {
  job: Props['job'];
  carousel: Props['outputs']['carousel'];
  images: Props['outputs']['images'];
  rich: boolean;
  busy: boolean;
  onBuild: () => void;
  onBuildImages: (design: string | null) => void;
  onRecondense: () => void;
}) {
  if (!carousel.produced || carousel.slides.length === 0) {
    return (
      <Card>
        <EmptyState
          title={rich ? t('jobs.preview.carousel_empty') : t('jobs.carousel.locked')}
          body={rich ? t('jobs.preview.free_hint') : t('jobs.carousel.locked_body')}
          action={
            rich ? (
              <Button loading={busy} onClick={onBuild}>
                <Icon name="grid" size={16} />
                {t('jobs.preview.add_carousel')}
              </Button>
            ) : (
              <Button variant="secondary" onClick={() => router.visit('/panel/settings/billing')}>
                {t('jobs.carousel.locked_cta')}
              </Button>
            )
          }
        />
      </Card>
    );
  }

  return (
    <>
      <Card
        title={t('jobs.preview.slides')}
        action={
          <span className="nums-tabular text-[13px] text-text-muted">
            {toArabicIndic(t('jobs.carousel.count', { count: carousel.slides.length }))}
          </span>
        }
      >
        <SlideCarousel
          label={t('jobs.preview.slides')}
          slides={carousel.slides.map((slide, position) =>
            // **الصورةُ الملتقَطة متى وُجدت** — T-173: فلا تختلف المعاينة عن
            // التنزيل. وعددٌ لا يطابق الشرائح يعني صوراً من كاروسيلٍ سابق.
            // وصورٌ أقدمُ من شرائحها لا تُعرض حاليّةً — T-204 (إلّا أثناء إنشاء بدلها).
            images.produced && images.urls.length === carousel.slides.length && (!images.stale || images.state === 'rendering') ? (
              <img
                key={slide.index}
                src={images.urls[position]}
                alt={t('jobs.images.slide_alt', { slide: toArabicIndic(slide.index) })}
                // أثناء إنشاء صورٍ جديدة تبقى القديمةُ معتمةً حتى تحلّ محلّها — T-197.
                className={cn(
                  'aspect-[4/5] w-full max-w-md rounded-lg border border-border transition-opacity',
                  images.state === 'rendering' && 'opacity-40',
                )}
              />
            ) : (
              <SlideFace key={slide.index} slide={slide} />
            ),
          )}
        />
      </Card>

      {/* الصور تحت عارضها — T-199: القالبُ والإنشاءُ والتنزيلُ في موضعٍ واحد. */}
      <ImagesPane job={job} images={images} rich={rich} busy={busy} onBuild={onBuildImages} />

      <Card
        title={t('jobs.preview.slide_texts')}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Button variant="ghost" loading={busy} onClick={onRecondense}>
              {t('jobs.carousel.recondense')}
            </Button>
            <CopyButton text={carousel.plain_text} label={t('jobs.carousel.copy_all')} variant="secondary" />
          </div>
        }
      >
        <ul className="grid gap-3 sm:grid-cols-2">
          {carousel.slides.map((slide) => (
            <li key={slide.index} className="flex flex-col gap-2 rounded-lg border border-border bg-surface-alt p-4">
              <div className="flex items-center gap-2">
                <span className="nums-tabular flex size-6 items-center justify-center rounded-full border border-border-strong text-[12px] text-text-muted">
                  {toArabicIndic(slide.index)}
                </span>
                <h3 className="min-w-0 flex-1 truncate text-[15px] font-semibold text-text">{slide.heading}</h3>
              </div>

              <SlideBody
                kind={slide.kind}
                body={slide.body}
                className="wrap-anywhere text-[14px] leading-relaxed text-text-muted"
              />

              <div className="mt-auto pt-1">
                <CopyButton
                  text={forPost(
                    [slide.heading, slide.body, slide.source_line]
                      .filter((line): line is string => line !== null && line !== '')
                      .join('\n'),
                  )}
                  label={t('jobs.carousel.copy')}
                  variant="ghost"
                />
              </div>
            </li>
          ))}
        </ul>
      </Card>

    </>
  );
}

/**
 * حزمة الصور — T-173، وتستوعب T-20. **وتحت عارض الشرائح منذ T-199**.
 *
 * بلا شبكة صورٍ هنا: العارضُ فوقها يعرض الصورَ الملتقَطة متى وُجدت، فشبكةٌ
 * ثانية تكرّرها. وهنا ما يخصّ الحزمة: القالبُ، والإنشاءُ وإعادتُه، والتنزيلُ،
 * والتقدّمُ، والكلفةُ مكتوبة. والتنزيلُ رابطٌ لا زرّ، كسائر التنزيلات هنا.
 */
function ImagesPane({
  job, images, rich, busy, onBuild,
}: {
  job: Props['job'];
  images: Props['outputs']['images'];
  rich: boolean;
  busy: boolean;
  onBuild: (design: string | null) => void;
}) {
  // القالب: ما صُنعت به الحزمة الحاليّة، وإلّا افتراضيُّ الجهة.
  const [design, setDesign] = useState<string | null>(images.design ?? images.designs[0]?.id ?? null);
  const rendering = images.state === 'rendering';

  if (!rich || !images.enabled) {
    return (
      <Card title={t('jobs.preview.tabs.images')}>
        <p className="text-[14px] text-text-muted">
          {t(!rich ? 'jobs.carousel.locked_body' : 'jobs.images.disabled')}
        </p>
      </Card>
    );
  }

  return (
    <Card
      title={t('jobs.preview.tabs.images')}
      action={
        <div className="flex flex-wrap items-center gap-2">
          {/* لا اختيار إلّا لجهةٍ اعتمدت قالباً — ومن لم تعتمد فقالبُها الأصل. */}
          {images.designs.length > 0 ? (
            <label className="flex items-center gap-2 text-[14px] text-text-muted">
              <span>{t('common.carousel_designs.choose')}</span>
              <select
                value={design ?? ''}
                onChange={(event) => setDesign(event.target.value)}
                disabled={rendering}
                className="field w-auto py-1.5"
              >
                {images.designs.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.name ?? t('common.carousel_designs.unnamed')}
                  </option>
                ))}
                <option value="default">{t('common.carousel_designs.default_original')}</option>
              </select>
            </label>
          ) : null}

          <Button
            loading={busy || rendering}
            onClick={() => onBuild(design)}
            // صورٌ قديمةٌ تُنشأ من جديد أوّلاً، فالزرُّ أبرزُ ما في البطاقة حينها.
            variant={images.produced && !images.stale ? 'secondary' : 'primary'}
          >
            <Icon name="images" size={16} />
            {t(images.produced ? 'jobs.images.recreate' : 'jobs.images.create')}
          </Button>

          {images.produced ? (
            <a
              href={`/panel/jobs/${job.id}/download/image_set`}
              className="inline-flex shrink-0 items-center justify-center gap-2 rounded bg-primary px-4 py-2 text-[15px] font-medium whitespace-nowrap text-white transition-colors duration-150 hover:bg-primary-hover"
            >
              <Icon name="upload" size={16} className="rotate-180" />
              {t('jobs.images.download')}
            </a>
          ) : null}
        </div>
      }
    >
      {rendering ? <ImagesProgress progress={images.progress} /> : null}

      {images.stale && !rendering ? (
        <p className="mb-4 rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-[14px] text-text">
          {t('jobs.images.stale')}
        </p>
      ) : null}

      {images.state === 'failed' && images.error !== null ? (
        <p className="mb-4 rounded-lg border border-danger/35 bg-danger/8 px-4 py-3 text-[14px] text-danger">
          {images.error}
        </p>
      ) : null}

      <div className="flex flex-col gap-1 text-[13px] text-text-faint">
        <span>{t(images.produced ? 'jobs.images.ready_hint' : 'jobs.images.empty_body')}</span>
        {/* القالبُ قالبُ الشرائح كلِّها، لا الصور وحدها — T-204. */}
        {images.designs.length > 0 ? <span>{t('jobs.images.design_scope')}</span> : null}
        {/* الكلفةُ مكتوبةٌ تحت الزرّ لا مفترَضة — T-196. */}
        <span className="font-medium text-text-muted">{t('jobs.images.free_hint')}</span>
      </div>
    </Card>
  );
}

/**
 * ما التُقط من كم — T-197.
 *
 * **عددٌ لا دوّارٌ مبهم** (§القواعد العامّة): الإنشاءُ عمليةٌ طويلة لها حالٌ
 * معلومة، فتُعرض. والعددُ يُحدَّث بعد كلّ دفعةٍ من ثماني شرائح.
 */
function ImagesProgress({ progress }: { progress: { done: number; total: number } | null }) {
  const done = progress?.done ?? 0;
  const total = progress?.total ?? 0;
  const ratio = total > 0 ? done / total : 0;

  return (
    <div role="status" aria-live="polite" className="mb-4 rounded-lg border border-info/25 bg-info/6 px-4 py-3">
      <div className="flex flex-wrap items-center justify-between gap-2 text-[14px]">
        <span className="font-medium text-text">{t('jobs.images.rendering')}</span>
        {total > 0 ? (
          <span className="nums-tabular text-text-muted">{toArabicIndic(t('jobs.images.progress', { done, total }))}</span>
        ) : null}
      </div>
      <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-border">
        <div
          className="h-full rounded-full bg-primary transition-[width] duration-500"
          style={{ width: `${Math.max(6, Math.round(ratio * 100))}%` }}
        />
      </div>
      <p className="mt-2 text-[13px] text-text-muted">{t('jobs.images.rendering_body')}</p>
    </div>
  );
}

/** وجه الشريحة في العارض — عنوانٌ ومتنٌ وسطر مصدر. */
function SlideFace({ slide }: { slide: Slide }) {
  return (
    <div className="flex min-h-[220px] w-full max-w-md flex-col justify-center gap-3 rounded-lg border border-border bg-surface p-6 text-center">
      <h3 className="text-[18px] font-semibold text-text">{slide.heading}</h3>
      <SlideBody
        kind={slide.kind}
        body={slide.body}
        className="wrap-anywhere text-[15px] leading-relaxed text-text-muted"
      />
      {slide.source_line !== null ? (
        <p className="text-[12px] text-text-faint">{slide.source_line}</p>
      ) : null}
    </div>
  );
}

/**
 * ما بقي من أفعالٍ تحت المعاينة — §6.
 *
 * **والنشر صعد إلى رأس الشاشة، وتنزيلُ الصفحة إلى شريط المعاينة** (T-84).
 * وبقي هنا ما يخصّ المخرجات كلَّها: إضافةُ مخرَجٍ بلا خصم، وتنزيلُ الشرائح،
 * وإعادةُ التوليد وحدها تُخصم — فتقف آخراً بوزنٍ خفيف وخلف تأكيد.
 */
function Actions({
  job, carouselProduced, rich, canPublish, busy, left, onBuildCarousel, onRegenerate,
}: {
  job: Props['job'];
  carouselProduced: boolean;
  rich: boolean;
  canPublish: boolean;
  busy: boolean;
  left: number;
  onBuildCarousel: () => void;
  onRegenerate: () => void;
}) {
  return (
    <Card
      // بعنوانها — T-196: بلا عنوانٍ كان ما فوقها يُقرأ عنواناً لها.
      title={t('jobs.preview.more_title')}
      footer={
        <div className="flex flex-col gap-1 text-[13px] text-text-faint">
          <span>{t('jobs.preview.publish_hint')}</span>
          <span>{t('jobs.preview.download_hint')}</span>
        </div>
      }
    >
      <div className="flex flex-wrap items-center gap-3">
        {carouselProduced ? (
          <a
            href={`/panel/jobs/${job.id}/download/carousel`}
            className="inline-flex shrink-0 items-center justify-center gap-2 rounded px-4 py-2 text-[15px] font-medium whitespace-nowrap text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
          >
            <Icon name="text" size={16} />
            {t('jobs.preview.download_carousel')}
          </a>
        ) : null}

        {/* «أضف مخرَجاً إن لم تُنتَج كلّها» — §6، ومعه أنّه بلا خصم. */}
        {!carouselProduced && rich && canPublish ? (
          <Button variant="secondary" loading={busy} onClick={onBuildCarousel}>
            <Icon name="grid" size={16} />
            {t('jobs.preview.add_output')}
          </Button>
        ) : null}

        {job.published ? (
          <Link
            href={`/panel/summaries/${job.id}`}
            className="inline-flex shrink-0 items-center gap-2 rounded px-4 py-2 text-[15px] font-medium text-primary transition-colors hover:bg-surface-alt"
          >
            <Icon name="link" size={16} />
            {t('jobs.preview.manage')}
          </Link>
        ) : null}
      </div>

      {canPublish ? (
        <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4">
          <Button variant="ghost" disabled={left === 0 || busy} onClick={onRegenerate}>
            <Icon name="clock" size={16} />
            {t('jobs.preview.regenerate')}
          </Button>

          {/*
            **المتبقّي يُبيَّن قبل التنفيذ** — §6 نصّاً. ورقمٌ يُقال بعد
            الضغط لا يُغني: القرار وقع.
          */}
          <span className="text-[13px] text-text-muted">
            {toArabicIndic(
              left === 0
                ? t('jobs.preview.regenerate_none')
                : t('jobs.preview.regenerate_left', { count: left }),
            )}
          </span>

          <span className="basis-full text-[13px] text-text-faint">{t('jobs.preview.regenerate_hint')}</span>
        </div>
      ) : null}
    </Card>
  );
}

/** النسخ إلى الحافظة، بتأكيدٍ يُرى — فالنسخ فعلٌ بلا أثرٍ ظاهر بغيره. */
function CopyButton({
  text, label, variant,
}: {
  text: string;
  label: string;
  variant: 'secondary' | 'ghost';
}) {
  const [done, setDone] = useState(false);

  async function copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(text);
      setDone(true);
      window.setTimeout(() => setDone(false), 2000);
    } catch {
      // متصفّحٌ منع الحافظة: النصّ ظاهرٌ أمام العين ويُحدَّد باليد.
    }
  }

  return (
    <Button variant={variant} onClick={copy}>
      <Icon name={done ? 'check' : 'copy'} size={16} />
      {done ? t('jobs.carousel.copied') : label}
    </Button>
  );
}

/** الجهاز المختار يبقى بين المعاينات — من يراجع على الجوال يراجع كلَّها عليه. */
function rememberedDevice(): Device {
  try {
    const stored = window.localStorage.getItem(DEVICE_KEY);
    return stored === 'mobile' || stored === 'tablet' || stored === 'desktop' ? stored : 'desktop';
  } catch {
    return 'desktop';
  }
}

function rememberDevice(device: Device): void {
  try {
    window.localStorage.setItem(DEVICE_KEY, device);
  } catch {
    // تخزينٌ محجوب: يبقى الاختيار لهذه الصفحة وحدها.
  }
}
