import { useEffect, useRef, useState, type ReactNode } from 'react';
import { router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { CarouselDesigns, type CarouselDesignsData } from '@/Components/CarouselDesigns';
import { DeviceFrame, type Device } from '@/Components/DeviceFrame';
import { FieldGroup } from '@/Components/FieldGroup';
import { FileDropzone } from '@/Components/FileDropzone';
import { Icon } from '@/Components/Icon';
import { PalettePicker, type Palette } from '@/Components/PalettePicker';
import { Segmented } from '@/Components/Segmented';
import { StickyBar } from '@/Components/StickyBar';
import { Toast } from '@/Components/Toast';
import { cn } from '@/lib/cn';
import { csrfToken } from '@/lib/csrf';
import { t } from '@/lib/i18n';

interface TenantBrand {
  name_ar: string;
  name_ar_full: string | null;
  name_latin: string | null;
  disclaimer_text: string | null;
  palette: string;
  template: string;
  youtube_url: string | null;
  social_url: string | null;
  has_logo: boolean;
  /** الشعار المحفوظ — T-85. ليُرى قبل أن يُستبدل. */
  logo_url: string | null;
  /** اللوح الفاتح خلف الشعار — T-90. اختياريّ منذ T-125. */
  logo_transparent: boolean;
  locales: string[];
}

interface SummaryTemplate {
  key: string;
  name: string;
  description: string;
}

interface OutputLocale {
  key: string;
  name: string;
  native: string;
  direction: string;
  is_source: boolean;
  quran_translation: string | null;
}

interface Props {
  tenant: TenantBrand;
  palettes: Palette[];
  templates: SummaryTemplate[];
  locales: OutputLocale[];
  /** قوالبُ الكاروسيل — T-173. */
  carousel_designs: CarouselDesignsData;
  rich_outputs: boolean;
}

/** مهلةٌ بعد آخر حرف قبل إعادة الرسم — لا يُرسم القالب كلّه بكلّ حرف. */
const PREVIEW_DEBOUNCE_MS = 450;

const SECTIONS = ['identity', 'appearance', 'locales', 'links'] as const;
type SectionKey = (typeof SECTIONS)[number];

/**
 * هوية الجهة — SCREENS.md الشاشة 8.
 *
 * عمودان: النموذج، **ومعاينة حيّة بالقالب الحقيقي لا بمحاكاة**. فمحاكاةٌ
 * تُرضي في الشاشة ثمّ تخالف المنشور تُفقد الثقة في المعاينة كلِّها، ويصير
 * المستخدم ينشر ليرى.
 *
 * ★ **وصارت المعاينة تتبع كلّ حقلٍ قبل الحفظ** — T-85. **والشعارُ معها**
 * منذ T-90 وT-98: قالبُ الصفحة يرسمه في رأسها وبصمتها، فيُرسل المختارُ
 * إلى المعاينة ويُنقّيه الخادم قبل رسمه، كما يُنقّيه عند الحفظ.
 *
 * ★ **وما تغيّر في T-96 — مراجعة مالك المنتج، ١١ أيلول ٢٠٢٦:**
 * - أربعةُ أقسامٍ بأسمائها وروابطِ انتقال: الهوية (الاسم والشعار معاً) ·
 *   المظهر (القالب واللوحة) · اللغات · الروابط.
 * - **نطاقُ الأثر مكتوب**: افتراضاتُ الجديد، والمنشور لا يتغيّر حتى يُحدَّث.
 * - القوالبُ قائمةً بعمودين لا ثلاثة أعمدةٍ ضيّقة، والمعاينةُ بجهازين.
 * - «تراجع»، وتنبيهٌ عند المغادرة بتغييراتٍ لم تُحفظ.
 */
export default function Brand({ tenant, palettes, templates, locales, carousel_designs, rich_outputs }: Props) {
  const [toast, setToast] = useState<string | null>(null);
  const [device, setDevice] = useState<Device>('mobile');
  // مفتاحٌ يُبدَّل بـ«تراجع» فتُعاد منطقة الرفع إلى أوّلها — تحفظ اسمَ الملفّ المختار في نفسها.
  const [dropzoneKey, setDropzoneKey] = useState(0);
  const frame = useRef<HTMLIFrameElement>(null);

  const form = useForm({
    name_ar: tenant.name_ar,
    name_ar_full: tenant.name_ar_full ?? '',
    name_latin: tenant.name_latin ?? '',
    palette: tenant.palette,
    template: tenant.template,
    youtube_url: tenant.youtube_url ?? '',
    social_url: tenant.social_url ?? '',
    disclaimer_text: tenant.disclaimer_text ?? '',
    locales: tenant.locales,
    logo: null as File | null,
    // إزالةُ الشعار المحفوظ — T-99. تُطلب هنا وتقع عند «حفظ»، و«أبقِه» يعيده.
    remove_logo: false,
    logo_transparent: tenant.logo_transparent,
  });

  const preview = useLivePreview(form.data);
  const logo = useLogoThumbnail(form.data.logo, form.data.remove_logo ? null : tenant.logo_url);

  useLeaveGuard(form.isDirty);

  function submit(event: React.FormEvent) {
    event.preventDefault();

    form.post('/panel/settings/brand', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        setToast(t('common.brand.saved'));
        // المحفوظ صار الأصل: «لم تُحفظ» تنطفئ حتى يتغيّر شيءٌ بعده.
        form.setDefaults();
      },
    });
  }

  const chosenLocales = locales.filter((locale) => form.data.locales.includes(locale.key));

  return (
    <AppLayout title={t('common.brand.title')} description={t('common.brand.subtitle')}>
      <div className="grid gap-6 lg:grid-cols-2">
        {/*
          `lg:self-start` كما في عمود المعاينة — T-107: بلاها تمدّدت شبكةُ
          CSS هذا العمود بارتفاع العمود الآخر (`align-items: stretch`
          الافتراضي)، ففراغٌ داخل صندوق النموذج تحت `StickyBar` لا يملؤه
          شيء، فيظهر خلفه المحتوى الحقيقيّ أثناء التمرير.
        */}
        <form onSubmit={submit} className="flex min-w-0 flex-col gap-5 lg:self-start">
          {/*
            **نطاقُ الأثر مكتوب** — T-96. تبديلُ الهوية لا يمسّ ما نُشر حتى
            يُعاد نشرُه، ومن لم يعرف ذلك ظنّ الحفظ لم يعمل، أو خشي أن يُفسد
            صفحاتٍ منشورة بتجربة لون.
          */}
          <p role="note" className="flex items-start gap-2.5 rounded-lg border border-info/25 bg-info/6 px-4 py-3 text-[13.5px] leading-relaxed text-text">
            <Icon name="alert" size={17} className="mt-0.5 shrink-0 text-info" />
            {t('common.brand.scope_note')}
          </p>

          <nav aria-label={t('common.brand.nav_label')} className="flex flex-wrap gap-1.5">
            {SECTIONS.map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => document.getElementById(`brand-${key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })}
                className="rounded-full border border-border bg-surface px-3 py-1 text-[13px] text-text-muted transition-colors hover:border-border-strong hover:text-text"
              >
                {sectionTitle(key)}
              </button>
            ))}
          </nav>

          {/* ١. الهوية — الاسم والشعار معاً: كلاهما «من نحن»، وكان الشعار في آخر الصفحة. */}
          <Section id="identity">
            <Card title={sectionTitle('identity')}>
              <div className="flex flex-col gap-4">
                <FieldGroup label={t('common.brand.name_ar')} required error={form.errors.name_ar}>
                  <input
                    type="text"
                    name="name_ar"
                    autoComplete="organization"
                    value={form.data.name_ar}
                    onChange={(e) => form.setData('name_ar', e.target.value)}
                    className="field"
                  />
                </FieldGroup>

                <FieldGroup label={t('common.brand.name_ar_full')} hint={t('common.brand.name_ar_full_hint')}>
                  <input
                    type="text"
                    name="name_ar_full"
                    autoComplete="off"
                    value={form.data.name_ar_full}
                    onChange={(e) => form.setData('name_ar_full', e.target.value)}
                    className="field"
                  />
                </FieldGroup>

                <FieldGroup label={t('common.brand.name_latin')}>
                  <input
                    type="text"
                    name="name_latin"
                    autoComplete="off"
                    dir="ltr"
                    value={form.data.name_latin}
                    onChange={(e) => form.setData('name_latin', e.target.value)}
                    className="field text-start"
                  />
                </FieldGroup>

                <div className="border-t border-border pt-4">
                  {/* الشعار اختياريّ — T-99: والصفحةُ بلا شعارٍ تامّةٌ لا ناقصة. */}
                  <h3 className="mb-3 flex items-baseline gap-2 text-[14px] font-medium text-text">
                    {t('common.brand.section_logo')}
                    <span className="text-[12.5px] font-normal text-text-muted">{t('common.brand.logo_optional')}</span>
                  </h3>

                  {/*
                    الشعار يُرى قبل أن يُستبدل — T-85. و`<img>` لا يُنفّذ سكربتاً
                    في SVG، فمصغّرةُ الملفّ المختار آمنةٌ قبل أن يُنقّيه الخادم.
                  */}
                  {logo.src !== null ? (
                    <div className="mb-3 flex flex-wrap items-center gap-4 rounded-lg border border-border bg-surface-alt p-3">
                      <img
                        src={logo.src}
                        alt={t(logo.fresh ? 'common.brand.logo_chosen' : 'common.brand.logo_current')}
                        className="h-14 w-auto max-w-[160px] rounded bg-surface object-contain p-1.5"
                      />
                      <p className="min-w-0 flex-1 text-[13px] leading-relaxed text-text-muted">
                        {t(logo.fresh ? 'common.brand.logo_chosen' : 'common.brand.logo_current')}
                      </p>
                      {/*
                        إزالةُ المحفوظ — T-99. والمختارُ لم يُحفظ بعد، فيُلغى من منطقة
                        الرفع نفسها: لا زرّان لفعلٍ واحد.
                      */}
                      {logo.fresh ? null : (
                        <Button variant="ghost" onClick={() => form.setData('remove_logo', true)}>
                          {t('common.brand.logo_remove')}
                        </Button>
                      )}
                    </div>
                  ) : form.data.remove_logo ? (
                    <div role="status" className="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-warning/30 bg-warning/6 p-3">
                      <p className="min-w-0 flex-1 text-[13px] leading-relaxed text-text">{t('common.brand.logo_removing')}</p>
                      <Button variant="ghost" onClick={() => form.setData('remove_logo', false)}>
                        {t('common.brand.logo_keep')}
                      </Button>
                    </div>
                  ) : null}

                  <FileDropzone
                    key={dropzoneKey}
                    accept={['.png', '.svg']}
                    maxBytes={512_000}
                    maxLabel={t('common.brand.logo_max')}
                    // ملفٌّ جديد يُلغي طلبَ الإزالة: من اختار شعاراً أراد شعاراً.
                    onSelect={(file) => form.setData({ ...form.data, logo: file, remove_logo: false })}
                    onClear={() => form.setData('logo', null)}
                  />
                  {form.errors.logo ? (
                    <p role="alert" className="mt-2 text-[13px] text-danger">{form.errors.logo}</p>
                  ) : null}

                  {/*
                    اللوح خلف الشعار اختياريّ — T-125. بلا معنى بلا شعار،
                    فيظهر فقط حين يُرى شعارٌ (مختارٌ أو محفوظ).
                  */}
                  {logo.src !== null ? (
                    <label className="mt-3 flex cursor-pointer items-start gap-2.5">
                      <input
                        type="checkbox"
                        checked={form.data.logo_transparent}
                        onChange={(e) => form.setData('logo_transparent', e.target.checked)}
                        className="mt-0.5 size-4 shrink-0 accent-[var(--primary)]"
                      />
                      <span className="text-[13px] leading-relaxed text-text-muted">
                        <span className="text-text">{t('common.brand.logo_transparent')}</span>
                        {' — '}
                        {t('common.brand.logo_transparent_hint')}
                      </span>
                    </label>
                  ) : null}
                </div>
              </div>
            </Card>
          </Section>

          {/*
            ٢. المظهر — القالب فوق اللوحة: **الشكل قبل اللون** (T-45)، وكلاهما
            يحرّك المعاينة. والقوالب قائمةٌ بعمودين (T-96): ثلاثةُ أعمدةٍ في
            نصف الشاشة كانت تلفّ الوصف أربعة أسطر. **عنوانٌ ووصف** لا صور — T-60.
          */}
          <Section id="appearance">
            <Card title={sectionTitle('appearance')}>
              <p className="mb-4 text-[13px] leading-relaxed text-text-muted">{t('common.brand.appearance_hint')}</p>

              <div role="radiogroup" aria-labelledby="brand-template-label">
                <h3 id="brand-template-label" className="mb-1 text-[14px] font-medium text-text">{t('templates.label')}</h3>
                <p className="mb-3 text-[12.5px] leading-relaxed text-text-muted">{t('templates.hint')}</p>

                <div className="grid gap-2 sm:grid-cols-2">
                  {templates.map((template) => {
                    const chosen = form.data.template === template.key;

                    return (
                      <label
                        key={template.key}
                        className={cn(
                          'flex cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2.5 transition-colors',
                          chosen ? 'border-primary bg-primary/5' : 'border-border bg-surface hover:border-border-strong',
                        )}
                      >
                        <input
                          type="radio"
                          name="template"
                          value={template.key}
                          checked={chosen}
                          onChange={() => form.setData('template', template.key)}
                          className="mt-1 size-4 shrink-0 accent-[var(--primary)]"
                        />
                        <span className="min-w-0">
                          <span className="block text-[14px] font-medium text-text">{template.name}</span>
                          <span className="block text-[12.5px] leading-snug text-text-muted">{template.description}</span>
                        </span>
                      </label>
                    );
                  })}
                </div>
              </div>

              <div className="mt-5 border-t border-border pt-5">
                <h3 className="mb-3 text-[14px] font-medium text-text">{t('common.brand.section_palette')}</h3>
                {/* **لا حقول HEX** — اللوحات ستٌّ مضبوطة يُختار منها (§الشاشة 8). */}
                <PalettePicker
                  palettes={palettes}
                  value={form.data.palette}
                  onChange={(key) => form.setData('palette', key)}
                />
              </div>
            </Card>
          </Section>

          {/*
            ٣. لغات النشر الافتراضية — T-38. **الواجهة تبقى عربية** (CLAUDE.md §1)،
            والمترجَم هو الملخّص المنشور وحده. رقاقاتٌ كما في شاشة الإنشاء (T-95).
          */}
          <Section id="locales">
            <Card title={sectionTitle('locales')}>
              <p className="mb-3 text-[13px] leading-relaxed text-text-muted">{t('locales.hint')}</p>

              <div className="flex flex-wrap gap-2">
                {locales.map((locale) => {
                  const chosen = form.data.locales.includes(locale.key);
                  // ★★ العربيةُ تُنزع كغيرها — T-51. والشرطُ لغةٌ واحدة على الأقلّ.
                  const last = chosen && form.data.locales.length === 1;

                  return (
                    <button
                      key={locale.key}
                      type="button"
                      role="checkbox"
                      aria-checked={chosen}
                      aria-disabled={last || undefined}
                      title={last ? t('locales.min') : undefined}
                      onClick={() => {
                        if (last) {
                          return;
                        }

                        form.setData(
                          'locales',
                          chosen
                            ? form.data.locales.filter((key) => key !== locale.key)
                            : [...form.data.locales, locale.key],
                        );
                      }}
                      className={cn(
                        'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-[14px] transition-colors',
                        chosen ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-text hover:border-border-strong',
                        last && 'cursor-default',
                      )}
                    >
                      {chosen ? <Icon name="check" size={14} /> : null}
                      <span>{locale.name}</span>
                      {locale.native !== locale.name ? (
                        <span dir="ltr" className={cn('text-[12px]', chosen ? 'text-white/80' : 'text-text-faint')}>
                          {locale.native}
                        </span>
                      ) : null}
                    </button>
                  );
                })}
              </div>

              {chosenLocales.some((locale) => locale.quran_translation !== null) ? (
                <ul className="mt-3 flex flex-col gap-1">
                  {chosenLocales
                    .filter((locale) => locale.quran_translation !== null)
                    .map((locale) => (
                      <li key={locale.key} className="flex items-center gap-1.5 text-[12.5px] text-text-muted">
                        <Icon name="check" size={13} className="shrink-0 text-success" />
                        {t('lectures.create.produce.quran', { language: locale.name, name: locale.quran_translation ?? '' })}
                      </li>
                    ))}
                </ul>
              ) : null}
            </Card>
          </Section>

          {/* ٤. الروابط والتنويه */}
          <Section id="links">
            <Card title={sectionTitle('links')}>
              <div className="flex flex-col gap-4">
                <FieldGroup label={t('common.brand.youtube_url')} error={form.errors.youtube_url}>
                  <input
                    type="url"
                    name="youtube_url"
                    autoComplete="url"
                    dir="ltr"
                    value={form.data.youtube_url}
                    onChange={(e) => form.setData('youtube_url', e.target.value)}
                    className="field text-start"
                  />
                </FieldGroup>

                <FieldGroup label={t('common.brand.social_url')} error={form.errors.social_url}>
                  <input
                    type="url"
                    name="social_url"
                    autoComplete="url"
                    dir="ltr"
                    value={form.data.social_url}
                    onChange={(e) => form.setData('social_url', e.target.value)}
                    className="field text-start"
                  />
                </FieldGroup>

                <FieldGroup label={t('common.brand.disclaimer')} hint={t('common.brand.disclaimer_hint')}>
                  <textarea
                    rows={3}
                    name="disclaimer_text"
                    value={form.data.disclaimer_text}
                    onChange={(e) => form.setData('disclaimer_text', e.target.value)}
                    className="field"
                  />
                </FieldGroup>
              </div>
            </Card>
          </Section>

          {/*
            «لم تُحفظ» و«تراجع» بجانب الزرّ — T-85 وT-96. وشكلُ الشريط في
            `StickyBar`: بساطٌ صلبٌ إلى الحافّة لا بطاقةٌ طافية (T-102).
          */}
          <StickyBar>
            <span aria-live="polite" className="flex items-center gap-2 text-[13px] text-warning">
              {form.isDirty ? (
                <>
                  <span aria-hidden="true" className="size-2 rounded-full bg-warning" />
                  {t('common.brand.unsaved')}
                </>
              ) : null}
            </span>

            <span className="flex items-center gap-2">
              {form.isDirty ? (
                <Button
                  variant="ghost"
                  onClick={() => {
                    form.reset();
                    form.clearErrors();
                    setDropzoneKey((key) => key + 1);
                  }}
                >
                  {t('common.brand.reset')}
                </Button>
              ) : null}

              <Button type="submit" loading={form.processing}>
                {t('common.actions.save')}
              </Button>
            </span>
          </StickyBar>
        </form>

        {/*
          المعاينة في إطار: القالب يحمل ورقة أنماطه كاملةً، وإدراجه في
          صفحة اللوحة يخلط أنماطه بأنماطها. والإطار يعزلهما بلا حيلة.
          وبجهازين (T-96) — `DeviceFrame` نفسه الذي في المعاينة والإنشاء.
        */}
        <div className="lg:sticky lg:top-0 lg:self-start">
          <Card
            title={t('common.brand.preview')}
            action={
              <Segmented
                legend={t('jobs.preview.device.legend')}
                value={device}
                onChange={setDevice}
                options={[
                  { key: 'mobile', label: t('jobs.preview.device.mobile'), icon: 'phone' },
                  { key: 'desktop', label: t('jobs.preview.device.desktop'), icon: 'desktop' },
                ]}
              />
            }
            flush
          >
            <p aria-live="polite" className="border-b border-border px-5 py-2 text-[12.5px] text-text-faint">
              {preview.loading
                ? t('common.brand.preview_updating')
                : preview.failed
                  ? t('common.brand.preview_failed')
                  : t('common.brand.preview_live')}
            </p>

            {preview.html === null ? (
              preview.failed ? (
                // فشلٌ صريحٌ لا هيكلةٌ رمادية إلى الأبد — T-107: كان الفشلُ
                // يُبلَع صامتاً فتبقى الهيكلةُ معلَّقةً ولو انتهى التحميل.
                <div className="flex h-[70vh] flex-col items-center justify-center gap-3 p-6 text-center">
                  <Icon name="alert" size={22} className="text-danger" />
                  <p className="max-w-[28ch] text-[13.5px] leading-relaxed text-text-muted">
                    {t('common.brand.preview_failed')}
                  </p>
                  <Button variant="secondary" onClick={preview.retry}>
                    {t('common.actions.retry')}
                  </Button>
                </div>
              ) : (
                <div className="flex h-[70vh] flex-col gap-3 p-6" aria-hidden="true">
                  <span className="skeleton block h-40 w-full" />
                  <span className="skeleton block h-4 w-2/3" />
                  <span className="skeleton block h-4 w-1/2" />
                </div>
              )
            ) : (
              <DeviceFrame device={device} srcDoc={preview.html} title={t('common.brand.preview')} frameRef={frame} />
            )}
          </Card>
        </div>
      </div>

      {/*
        قوالبُ الكاروسيل — T-173. **خارج النموذج**: أفعالُها تقع فوراً ولا تنتظر
        «حفظ»، وتوليدُها نداءٌ مدفوعٌ لا يجوز أن يُطلقه حفظُ اسمٍ أو لون.
      */}
      <div id="brand-carousel" className="mt-6 scroll-mt-4">
        <CarouselDesigns
          designs={carousel_designs}
          base="/panel/settings/brand/carousel-designs"
          prop="carousel_designs"
          manage
          locked={!rich_outputs}
        />
      </div>

      <Toast message={toast} tone="success" onDismiss={() => setToast(null)} />
    </AppLayout>
  );
}

/** قسمٌ يُنتقل إليه — `scroll-mt` كي لا يلتصق عنوانه بحافّة منطقة التمرير. */
function Section({ id, children }: { id: SectionKey; children: ReactNode }) {
  return (
    <div id={`brand-${id}`} className="scroll-mt-4">
      {children}
    </div>
  );
}

function sectionTitle(key: SectionKey): string {
  return {
    identity: t('common.brand.section_identity'),
    appearance: t('common.brand.section_appearance'),
    locales: t('common.brand.section_locales'),
    links: t('common.brand.section_links'),
  }[key];
}

/**
 * لا تُفقد تغييراتٌ لم تُحفظ بنقرةٍ خاطئة — T-96.
 *
 * `beforeunload` لإغلاق التبويب وتحديثه، و`router.on('before')` للتنقّل
 * داخل اللوحة — فروابطُ Inertia لا تُطلق الأوّل. **والحفظُ نفسه لا يُسأل عنه**:
 * زيارةٌ غيرُ `GET` هي «حفظ» لا مغادرة.
 */
function useLeaveGuard(dirty: boolean): void {
  useEffect(() => {
    if (!dirty) {
      return undefined;
    }

    const unload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
    };

    window.addEventListener('beforeunload', unload);

    const off = router.on('before', (event) => {
      if (event.detail.visit.method !== 'get') {
        return;
      }

      if (!window.confirm(t('common.brand.leave_confirm'))) {
        event.preventDefault();
      }
    });

    return () => {
      window.removeEventListener('beforeunload', unload);
      off();
    };
  }, [dirty]);
}

type PreviewFields = {
  name_ar: string;
  name_ar_full: string;
  name_latin: string;
  palette: string;
  template: string;
  disclaimer_text: string;
  /** الشعارُ المختار ولم يُحفظ — يُنقّى في الخادم قبل رسمه (T-98). */
  logo: File | null;
  /** إزالةُ المحفوظ ولم تُحفظ بعد — T-99. */
  remove_logo: boolean;
  /** اللوح خلف الشعار — T-125. */
  logo_transparent: boolean;
};

/**
 * المعاينة تتبع النموذج — T-85.
 *
 * **بعد توقّف الكتابة لا بكلّ حرف**، والطلبُ الأسبق يُلغى إن سبقه أحدث —
 * فلا تصل معاينةٌ قديمة بعد جديدة فتُري ما مُحي. وانقطاعُ الشبكة يُبقي
 * آخر معاينةٍ صحيحة ولا يُفرغ الإطار.
 *
 * ★ **وأوّل طلبٍ يفشل لا يُبلَع صامتاً** — T-107: لم تكن هناك معاينةٌ
 * سابقة يُرتدّ إليها، فبقيت الهيكلةُ الرمادية إلى الأبد بلا علمٍ ولا
 * إعادة محاولة. فشلُ ما بعد أوّل معاينةٍ ناجحة يبقى صامتاً كما كان —
 * القاعدةُ نفسها: لا تُفقد معاينةٌ صحيحة لعلّةٍ عابرة.
 */
function useLivePreview(data: PreviewFields) {
  const [html, setHtml] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const first = useRef(true);
  const hasHtml = useRef(false);

  useEffect(() => {
    const controller = new AbortController();

    const timer = window.setTimeout(async () => {
      setLoading(true);

      const body = new FormData();
      body.append('name_ar', data.name_ar);
      body.append('name_ar_full', data.name_ar_full);
      body.append('name_latin', data.name_latin);
      body.append('disclaimer_text', data.disclaimer_text);
      body.append('palette', data.palette);
      body.append('template', data.template);
      body.append('logo_transparent', data.logo_transparent ? '1' : '0');

      if (data.logo !== null) {
        body.append('logo', data.logo);
      } else if (data.remove_logo) {
        body.append('remove_logo', '1');
      }

      try {
        const response = await fetch('/panel/settings/brand/preview', {
          method: 'POST',
          body,
          signal: controller.signal,
          headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });

        if (response.ok) {
          setHtml(await response.text());
          hasHtml.current = true;
          setFailed(false);
        } else if (!hasHtml.current) {
          setFailed(true);
        }
      } catch (error) {
        // أُلغي لأحدث منه: ليس فشلاً يُعرض. وإلا فانقطاعُ شبكةٍ حقيقيّ.
        const aborted = error instanceof DOMException && error.name === 'AbortError';

        if (!aborted && !hasHtml.current) {
          setFailed(true);
        }
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }, first.current ? 0 : PREVIEW_DEBOUNCE_MS);

    first.current = false;

    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [
    data.name_ar,
    data.name_ar_full,
    data.name_latin,
    data.disclaimer_text,
    data.palette,
    data.template,
    data.logo,
    data.remove_logo,
    data.logo_transparent,
    attempt,
  ]);

  return { html, loading, failed, retry: () => setAttempt((n) => n + 1) };
}

/** مصغّرةُ الشعار: المختارُ إن اختير، وإلّا المحفوظ. */
function useLogoThumbnail(file: File | null, saved: string | null) {
  const [chosen, setChosen] = useState<string | null>(null);

  useEffect(() => {
    if (file === null) {
      setChosen(null);
      return undefined;
    }

    const url = URL.createObjectURL(file);
    setChosen(url);

    return () => URL.revokeObjectURL(url);
  }, [file]);

  return { src: chosen ?? saved, fresh: chosen !== null };
}
