import { useEffect, useRef, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Layouts/AppLayout';
import { Button } from '@/Components/Button';
import { Card } from '@/Components/Card';
import { CostConfirm } from '@/Components/CostConfirm';
import { DeviceFrame, type Device } from '@/Components/DeviceFrame';
import { ErrorState } from '@/Components/ErrorState';
import { FieldGroup } from '@/Components/FieldGroup';
import { FileDropzone } from '@/Components/FileDropzone';
import { PalettePicker, type Palette } from '@/Components/PalettePicker';
import { Icon, type IconName } from '@/Components/Icon';
import { Segmented } from '@/Components/Segmented';
import { StickyBar } from '@/Components/StickyBar';
import { cn } from '@/lib/cn';
import { discardUpload, uploadInChunks, UploadError } from '@/lib/chunkedUpload';
import { csrfToken } from '@/lib/csrf';
import { toGregorian, toHijri } from '@/lib/hijri';
import { t } from '@/lib/i18n';
import { toArabicIndic } from '@/lib/numerals';
import type { SharedProps } from '@/types/inertia';

type SourceKind = 'url' | 'upload' | 'text';

/**
 * حالُ رفع الملفّ — يبدأ لحظةَ اختياره، فيملأ المستخدم بقيّة النموذج وهو يُرفع.
 * و`paused`: انقطع الاتّصال وما رُفع محفوظ، فيُكمَل من حيث وقف.
 */
type Upload =
  | { phase: 'idle' }
  | { phase: 'uploading' | 'checking'; file: File; sent: number; id: string | null }
  | { phase: 'paused'; file: File; sent: number; id: string | null; message: string }
  | { phase: 'ready'; file: File; id: string; seconds: number }
  | { phase: 'error'; file: File; message: string };

interface Preflight {
  ok: boolean;
  title?: string | null;
  duration_minutes?: number | null;
  has_arabic_captions?: boolean;
  limit_minutes?: number;
  exceeds_limit?: boolean;
  message?: string;
}

interface OutputTemplate {
  key: string;
  name: string;
  description: string;
}

interface OutputLocale {
  key: string;
  name: string;
  native: string;
  is_source: boolean;
  /** ترجمة الآيات المعتمدة لهذه اللغة — T-95. */
  quran_translation: string | null;
}

interface Props {
  limits: {
    max_lecture_minutes: number;
    upload_max_bytes: number;
    text_extensions: string[];
    media_extensions: string[];
  };
  rich_outputs: boolean;
  venue_modes: string[];
  templates: OutputTemplate[];
  locales: OutputLocale[];
  palettes: Palette[];
  defaults: { template: string; locales: string[]; palette: string };
  /** سطرُ النسبة بألفاظ الصفحة نفسها — T-95. */
  attribution: { lecture_by: string; lecture_at: string; venue: string };
}

/**
 * المصادر الثلاثة. **والرفعُ يعمل** — §5-أ-4-ب: كان «قريباً» (T-150) حتى
 * صار له مفرِّغ (Gemini) ومكانٌ ينتظر فيه الملفّ عاملَ الطابور. و`soon`
 * باقٍ لمصدرٍ يُعرض قبل أن يُبنى، فيُرى آتياً لا مخفيّاً.
 */
const SOURCES: ReadonlyArray<{ kind: SourceKind; icon: IconName; soon?: boolean }> = [
  { kind: 'url', icon: 'link' },
  { kind: 'upload', icon: 'upload' },
  { kind: 'text', icon: 'text' },
];

/**
 * ملخّص جديد — SCREENS.md §3، وأُعيد تصميمه في T-27، وفي T-95.
 *
 * **ثلاث خطوات في صفحة واحدة، بلا معالج متعدّد الصفحات.** فالمعالج يُخفي
 * ما بقي فلا يُقدَّر الوقت، ويُجبر على ترتيبٍ لا يلزم: من عنده النصّ ملصوقاً
 * لا ينتظر فحص رابط.
 *
 * ★ **وما تغيّر في T-95 — مراجعة مالك المنتج، ١١ أيلول ٢٠٢٦:**
 * - الخطواتُ مرقّمةٌ بعلامة اكتمال، **والصفحة واحدةٌ** كما كانت.
 * - **«المظهر» مطويٌّ ولا يُخفي ما سيُطبَّق**: القالب واللوحة رقاقاتٌ
 *   ظاهرة، موسومةً «افتراض جهتكم» أو «مخصّص»، و«عاين المظهر» بالقالب الحقيقي.
 * - **لغاتُ النشر خرجت منه إلى «ما الذي نُخرجه»** — اللغةُ قرارُ محتوًى.
 * - نمطُ النسبة ثلاثةُ خيارات بجملةٍ حيّة بألفاظ الصفحة، واليومُ من التاريخ.
 * - **عمودُ «ملخّص طلبك»** يقول ما سيُنتج وكم يكلّف قبل الضغط.
 * - ✗ **ولا فحصَ تلقائيّاً عند اللصق** — استبعده مالك المنتج.
 */
export default function Create({
  limits, rich_outputs, venue_modes, templates, locales, palettes, defaults, attribution,
}: Props) {
  // الحصّة تُرفض في الخادم قبل الإنشاء، فتصل خطأً عامّاً لا خطأ حقل.
  const page = usePage<SharedProps & { errors: Record<string, string> }>();
  const shared = page.props.errors;
  const quota = page.props.quota ?? null;

  // الرابط الملصوق في الفهرس يصل هنا في الاستعلام — T-27.
  const handedUrl = new URLSearchParams(page.url.split('?')[1] ?? '').get('source_url') ?? '';

  const [kind, setKind] = useState<SourceKind>('url');
  const [preflight, setPreflight] = useState<Preflight | null>(null);
  const [checking, setChecking] = useState(false);
  const [details, setDetails] = useState(false);

  /*
    «المظهر» مطويٌّ افتراضاً — طلبُ مالك المنتج، ٩ أيلول ٢٠٢٦. فأكثرُ
    المستخدمين يريدون افتراضَ جهتهم، ومن أراد غيرَه فتحه.
  */
  const [appearanceOpen, setAppearanceOpen] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [suggested, setSuggested] = useState({ title: false, hijri: false, gregorian: false, weekday: false });

  const form = useForm({
    source_kind: 'url' as SourceKind,
    source_url: handedUrl,
    // إقرارُ التكرار — T-65. يبدأ مطفأً دائماً، ويُعرض عند التنبيه وحده.
    confirm_duplicate: false,
    transcript_text: '',
    upload_id: '',
    duration_seconds: 0,
    title_ar: '',
    subtitle_ar: '',
    speaker_name: '',
    gregorian_date: '',
    hijri_date: '',
    weekday: '',
    time_note: '',
    venue_mode: venue_modes[0] ?? 'institution',
    want_carousel: false,
    // الافتراضُ ما اختارته الجهة، فمن لم يفتح «المظهر» خرج ملخّصُه كما كان.
    template: defaults.template,
    locales: defaults.locales,
    palette: defaults.palette,
  });

  /**
   * الفحص المسبق — «قبل أي صرف موارد»، ويعرض النتيجة خلال ثوانٍ.
   * ولا يُحسب تجاوزُ الحدّ هنا: الخادم يقوله، فلا يُبدَّل من أدوات المطوّر.
   */
  const runPreflight = async () => {
    setChecking(true);
    setPreflight(null);

    try {
      const response = await fetch('/panel/lectures/preflight', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          Accept: 'application/json',
        },
        body: JSON.stringify({ source_url: form.data.source_url }),
      });

      if (response.status === 419) {
        setPreflight({ ok: false, message: t('lectures.create.preflight.session_expired') });
        return;
      }

      if (!response.ok) {
        setPreflight({ ok: false, message: t('lectures.create.preflight.unavailable') });
        return;
      }

      const body = (await response.json()) as Preflight;
      setPreflight(body);

      if (body.ok && body.title && form.data.title_ar === '') {
        form.setData('title_ar', body.title);
        setSuggested((state) => ({ ...state, title: true }));
      }

      if (body.ok && body.duration_minutes) {
        form.setData('duration_seconds', body.duration_minutes * 60);
      }
    } catch {
      setPreflight({ ok: false, message: t('lectures.create.preflight.unavailable') });
    } finally {
      setChecking(false);
    }
  };

  /**
   * اليومُ من التاريخ الميلادي — T-95. **اقتراحٌ لا فرض**: يُملأ ما دام
   * الحقل فارغاً أو مقترَحاً، ومن كتب فيه بيده لا يُكتب فوقه.
   */
  const suggestWeekday = (gregorian: string) => {
    const day = weekdayOf(gregorian);

    if (day !== '' && (form.data.weekday === '' || suggested.weekday)) {
      form.setData('weekday', day);
      setSuggested((state) => ({ ...state, weekday: true }));
    }
  };

  /*
    الرفعُ أجزاءً — `lib/chunkedUpload`. **ولا يبقى على الخادم ما أُلغي**: إزالةُ
    الملفّ، أو اختيارُ غيره، أو مغادرةُ الصفحة قبل الإرسال — كلّها تحذفه.
  */
  const [upload, setUpload] = useState<Upload>({ phase: 'idle' });
  const uploadAbort = useRef<AbortController | null>(null);
  const uploadId = useRef<string | null>(null);
  const submitted = useRef(false);
  // الإرسالُ يستهلك ملخّصاً من الحصّة، فيُقرّ بخطوةٍ ثانية بأرقامها — T-203.
  const formRef = useRef<HTMLFormElement>(null);
  const [confirmingCreate, setConfirmingCreate] = useState(false);

  const dropUpload = () => {
    uploadAbort.current?.abort();
    uploadAbort.current = null;

    if (uploadId.current !== null) {
      discardUpload(uploadId.current);
      uploadId.current = null;
    }

    form.setData('upload_id', '');
    setUpload({ phase: 'idle' });
  };

  const startUpload = async (file: File, resumeId?: string) => {
    const controller = new AbortController();
    uploadAbort.current = controller;
    form.setData('upload_id', '');
    form.clearErrors('upload_id');
    setUpload({ phase: 'uploading', file, sent: 0, id: resumeId ?? null });

    try {
      const info = await uploadInChunks(file, {
        signal: controller.signal,
        onStarted: (id) => {
          uploadId.current = id;
        },
        onProgress: (sent) => setUpload({ phase: 'uploading', file, sent, id: uploadId.current }),
        onChecking: () => setUpload({ phase: 'checking', file, sent: file.size, id: uploadId.current }),
      }, resumeId);

      form.setData('upload_id', info.id);
      setUpload({ phase: 'ready', file, id: info.id, seconds: info.duration_seconds ?? 0 });
    } catch (error) {
      if (controller.signal.aborted) {
        return;
      }

      const message = error instanceof UploadError ? error.message : t('lectures.create.source.upload_failed');

      if (error instanceof UploadError && error.resumable && uploadId.current !== null) {
        setUpload((state) => ({
          phase: 'paused',
          file,
          sent: 'sent' in state ? state.sent : 0,
          id: uploadId.current,
          message,
        }));

        return;
      }

      // رفضٌ لا يُستأنف (نوع، صوت، مدّة، حصّة): الخادم حذف ما رُفع، فلا معرّف يبقى.
      uploadId.current = null;
      setUpload({ phase: 'error', file, message });
    }
  };

  // مغادرةُ الصفحة أو إغلاقُها قبل الإرسال: يُحذف الرفع، ولا ينتظر كنسَ الساعات.
  useEffect(() => {
    const leave = () => {
      if (uploadId.current !== null && !submitted.current) {
        uploadAbort.current?.abort();
        discardUpload(uploadId.current, true);
      }
    };

    window.addEventListener('pagehide', leave);

    return () => {
      window.removeEventListener('pagehide', leave);
      leave();
    };
  }, []);

  const uploadBusy = upload.phase === 'uploading' || upload.phase === 'checking';
  const blocked = (preflight?.ok === true && preflight.exceeds_limit === true) || (kind === 'upload' && uploadBusy);

  const sourceReady =
    kind === 'url'
      ? form.data.source_url.trim() !== ''
      : kind === 'text'
        ? form.data.transcript_text.trim() !== ''
        : upload.phase === 'ready';
  const meetingReady = form.data.title_ar.trim() !== '' && form.data.speaker_name.trim() !== '';

  const template = templates.find((item) => item.key === form.data.template);
  const palette = palettes.find((item) => item.key === form.data.palette);
  const chosenLocales = locales.filter((item) => form.data.locales.includes(item.key));
  const appearanceCustom = form.data.template !== defaults.template || form.data.palette !== defaults.palette;
  const localesCustom = !sameSet(form.data.locales, defaults.locales);

  const cost = [
    t('lectures.create.submit_hint'),
    quota !== null && quota.limit !== null && quota.limit !== undefined
      ? toArabicIndic(t('lectures.create.summary.left', { left: Math.max(0, quota.limit - quota.used), limit: quota.limit }))
      : null,
  ].filter((line): line is string => line !== null).join(' ');

  function send(): void {
    const formEl = formRef.current;

    if (formEl === null) {
      return;
    }

    // ملفٌّ رُفع ثمّ اختير مصدرٌ آخر: لا يبقى على الخادم بلا مهمّة.
    if (kind !== 'upload' && upload.phase !== 'idle') {
      dropUpload();
    }

    // أُرسل: فلا تحذف المغادرةُ رفعاً صار ملكَ المهمّة.
    submitted.current = true;
    form.post('/panel/lectures', {
      /*
       * خطأٌ كالتكرار (confirm_duplicate) يظهر أعلى الخطوة الأولى،
       * وزرّ الإرسال في أسفلها أو في الشريط السفليّ على الجوال — فيضغط
       * المستخدم ولا يرى شيئاً تغيّر حتى يُعيد التمرير للأعلى بنفسه.
       * فنُمرّر إلى أوّل حقلٍ فيه خطأ بعد أن يرسمه React.
       */
      onError: (errors) => {
        submitted.current = false;
        const firstKey = Object.keys(errors)[0];
        if (firstKey === undefined) return;
        requestAnimationFrame(() => {
          const target = formEl.querySelector<HTMLElement>(`[name="${firstKey}"]`);
          target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target?.focus?.();
        });
      },
    });
  }

  return (
    <AppLayout title={t('lectures.create.title')} description={t('lectures.create.subtitle')}>
      <form
        ref={formRef}
        onSubmit={(event) => {
          event.preventDefault();
          setConfirmingCreate(true);
        }}
        className="pb-2"
      >

        {/*
          عمودان على الحاسوب: الخطواتُ، و«ملخّص طلبك» ثابتاً بجانبها — يقول
          ما سيُنتج وكم يكلّف قبل الضغط. وعلى الجوال عمودٌ واحد وشريطٌ سفليّ.
        */}
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_19rem] lg:items-start">
          <div className="flex min-w-0 flex-col gap-5">
            {shared.quota ? <ErrorState message={shared.quota} /> : null}

            {/* ١. المصدر */}
            <Card title={<StepTitle n={1} done={sourceReady} text={t('lectures.create.source.legend')} />}>
              <SourcePicker
                kind={kind}
                onChange={(next) => {
                  setKind(next);
                  form.setData('source_kind', next);
                  setPreflight(null);
                }}
              />

              {kind === 'url' ? (
                <div className="mt-5 flex flex-col gap-3">
                  <FieldGroup
                    label={t('lectures.create.source.url_label')}
                    hint={t('lectures.create.source.url_hint')}
                    error={form.errors.source_url}
                    required
                  >
                    {/*
                      الرابط والفحص في سطرٍ واحد. وكان الزرّ تحت الحقل بمسافةٍ
                      منه، فبدا فعلاً منفصلاً لا خطوةً تالية له.
                    */}
                    <div className="flex flex-col gap-2 sm:flex-row">
                      <input
                        type="url"
                        name="source_url"
                        inputMode="url"
                        autoComplete="url"
                        dir="ltr"
                        autoFocus
                        placeholder="https://www.youtube.com/watch?v=…"
                        value={form.data.source_url}
                        onChange={(event) => form.setData('source_url', event.target.value)}
                        className="field text-start"
                      />

                      <Button
                        type="button"
                        variant="secondary"
                        loading={checking}
                        disabled={form.data.source_url === ''}
                        onClick={runPreflight}
                      >
                        {checking ? t('lectures.create.preflight.running') : t('lectures.create.preflight.run')}
                      </Button>
                    </div>
                  </FieldGroup>

                  {/*
                    إقرارُ التكرار — T-65. **ولا يظهر إلّا بعد التنبيه.** فخانةٌ
                    دائمة تسأل عن تكرارٍ لم يقع تُربك من لا مصدرَ مكرَّراً عنده.
                  */}
                  {form.errors.confirm_duplicate !== undefined ? (
                    <div className="rounded border border-border bg-surface-alt px-3 py-2.5">
                      <p className="text-[14px] text-text">{form.errors.confirm_duplicate}</p>

                      <label className="mt-2.5 flex items-start gap-2.5 text-[14px] text-text">
                        <input
                          type="checkbox"
                          name="confirm_duplicate"
                          className="mt-0.5"
                          checked={form.data.confirm_duplicate}
                          onChange={(event) => form.setData('confirm_duplicate', event.target.checked)}
                        />
                        {t('lectures.create.source.duplicate_confirm')}
                      </label>
                    </div>
                  ) : null}

                  {preflight ? <PreflightPanel result={preflight} limit={limits.max_lecture_minutes} /> : null}
                </div>
              ) : null}

              {kind === 'upload' ? (
                <div className="mt-5 flex flex-col gap-2">
                  <p className="flex items-center gap-2 text-[14px] font-medium text-text">
                    {t('lectures.create.source.upload_label')}
                    <span className="text-danger" aria-label={t('common.state.required')}>*</span>
                  </p>

                  {/*
                    الفحص في المتصفّح راحةٌ لا أمان — الخادم يفحص المحتوى والمدّة
                    وحدَّ الاشتراك عند اكتمال الرفع (§5-أ-4-ب).
                  */}
                  <FileDropzone
                    name="upload_id"
                    accept={limits.media_extensions.map((extension) => `.${extension}`)}
                    maxBytes={limits.upload_max_bytes}
                    maxLabel={t('lectures.create.source.upload_max')}
                    disabled={form.processing}
                    onSelect={(file) => {
                      dropUpload();
                      void startUpload(file);
                    }}
                    onClear={dropUpload}
                  />

                  <p className="text-[13px] text-text-muted">{t('lectures.create.source.upload_hint')}</p>

                  <UploadStatus
                    upload={upload}
                    onResume={() => {
                      if (upload.phase === 'paused') {
                        void startUpload(upload.file, upload.id ?? undefined);
                      }
                    }}
                    onCancel={dropUpload}
                  />

                  {form.errors.upload_id ? (
                    <p role="alert" className="text-[13px] text-danger">{form.errors.upload_id}</p>
                  ) : null}
                </div>
              ) : null}

              {kind === 'text' ? (
                <div className="mt-5">
                  <FieldGroup
                    label={t('lectures.create.source.text_label')}
                    hint={t('lectures.create.source.text_hint')}
                    error={form.errors.transcript_text}
                    required
                  >
                    <textarea
                      rows={10}
                      name="transcript_text"
                      value={form.data.transcript_text}
                      onChange={(event) => form.setData('transcript_text', event.target.value)}
                      placeholder={t('lectures.create.source.text_placeholder')}
                      className="field leading-[1.9]"
                    />
                  </FieldGroup>
                </div>
              ) : null}
            </Card>

            {/* ٢. بيانات المجلس */}
            <Card title={<StepTitle n={2} done={meetingReady} text={t('lectures.create.meeting.legend')} />}>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldGroup
                  label={t('lectures.create.meeting.title')}
                  error={form.errors.title_ar}
                  required
                  suggested={suggested.title}
                >
                  <input
                    name="title_ar"
                    autoComplete="off"
                    value={form.data.title_ar}
                    onChange={(event) => {
                      form.setData('title_ar', event.target.value);
                      setSuggested((state) => ({ ...state, title: false }));
                    }}
                    className="field"
                  />
                </FieldGroup>

                <FieldGroup label={t('lectures.create.meeting.speaker')} error={form.errors.speaker_name} required>
                  <input
                    name="speaker_name"
                    autoComplete="off"
                    value={form.data.speaker_name}
                    onChange={(event) => form.setData('speaker_name', event.target.value)}
                    className="field"
                  />
                </FieldGroup>
              </div>

              <Attribution
                modes={venue_modes}
                value={form.data.venue_mode}
                onChange={(mode) => form.setData('venue_mode', mode)}
                speaker={form.data.speaker_name}
                attribution={attribution}
                error={form.errors.venue_mode}
              />

              {/*
                الاختياريّ يُطوى. وستّةُ حقولٍ لا يلزم واحدٌ منها، مبسوطةً بين
                الثلاثة التي تلزم، تُخفي أنّ المطلوب ثلاثة.
              */}
              <button
                type="button"
                onClick={() => setDetails((open) => !open)}
                aria-expanded={details}
                className="mt-5 flex items-center gap-2 rounded-md py-1.5 text-[14px] font-medium text-primary transition-colors hover:text-primary-hover"
              >
                <Icon
                  name="chevron"
                  size={15}
                  className={cn('transition-transform', details ? 'rotate-90' : 'rotate-180')}
                />
                {t(details ? 'lectures.create.details.hide' : 'lectures.create.details.show')}
              </button>

              {details ? (
                <div className="mt-4 flex flex-col gap-5 border-t border-border pt-5">
                  {/*
                    **ولا حقلَ للّقب** — T-102، قرار مالك المنتج: «الشيخ»
                    وأمثالُه تُكتب في اسم الملقي إن أرادها صاحبُها، فحقلٌ
                    ثانٍ لها يسأل عمّا يسعه الحقلُ الأوّل.
                  */}
                  <FieldGroup label={t('lectures.create.meeting.subtitle')} error={form.errors.subtitle_ar}>
                    <input
                      name="subtitle_ar"
                      autoComplete="off"
                      value={form.data.subtitle_ar}
                      onChange={(event) => form.setData('subtitle_ar', event.target.value)}
                      className="field"
                    />
                  </FieldGroup>

                  {/* الموعد مجموعةً واحدة: التاريخان يقترح أحدُهما الآخر، واليومُ منهما. */}
                  <div role="group" aria-labelledby="when-label">
                    <h3 id="when-label" className="mb-3 text-[13px] font-semibold tracking-wide text-text-faint">
                      {t('lectures.create.groups.when')}
                    </h3>

                    <div className="grid gap-4 sm:grid-cols-2">
                      {/*
                        «إدخال أحدهما يقترح الآخر، والاقتراح قابل للتعديل» — §3-ج.
                        **اقتراحٌ لا تحويل**: بداية الشهر تختلف باختلاف الجهة.
                      */}
                      <FieldGroup
                        label={t('lectures.create.meeting.gregorian')}
                        error={form.errors.gregorian_date}
                        suggested={suggested.gregorian}
                      >
                        <input
                          type="date"
                          name="gregorian_date"
                          value={form.data.gregorian_date}
                          onChange={(event) => {
                            const value = event.target.value;
                            form.setData('gregorian_date', value);
                            setSuggested((state) => ({ ...state, gregorian: false }));

                            const hijri = toHijri(value);

                            if (hijri !== '' && form.data.hijri_date === '') {
                              form.setData('hijri_date', hijri);
                              setSuggested((state) => ({ ...state, hijri: true }));
                            }

                            suggestWeekday(value);
                          }}
                          className="field nums-tabular"
                        />
                      </FieldGroup>

                      <FieldGroup
                        label={t('lectures.create.meeting.hijri')}
                        hint={t('lectures.create.meeting.hijri_hint')}
                        error={form.errors.hijri_date}
                        suggested={suggested.hijri}
                      >
                        <input
                          name="hijri_date"
                          autoComplete="off"
                          value={form.data.hijri_date}
                          onChange={(event) => {
                            const value = event.target.value;
                            form.setData('hijri_date', value);
                            setSuggested((state) => ({ ...state, hijri: false }));

                            const gregorian = toGregorian(value);

                            if (gregorian !== '' && form.data.gregorian_date === '') {
                              form.setData('gregorian_date', gregorian);
                              setSuggested((state) => ({ ...state, gregorian: true }));
                              suggestWeekday(gregorian);
                            }
                          }}
                          className="field"
                        />
                      </FieldGroup>

                      <FieldGroup
                        label={t('lectures.create.meeting.weekday')}
                        hint={t('lectures.create.weekday_hint')}
                        error={form.errors.weekday}
                        suggested={suggested.weekday}
                      >
                        <input
                          name="weekday"
                          autoComplete="off"
                          value={form.data.weekday}
                          onChange={(event) => {
                            form.setData('weekday', event.target.value);
                            setSuggested((state) => ({ ...state, weekday: false }));
                          }}
                          className="field"
                        />
                      </FieldGroup>

                      <FieldGroup label={t('lectures.create.meeting.time_note')} error={form.errors.time_note}>
                        <input
                          name="time_note"
                          autoComplete="off"
                          value={form.data.time_note}
                          onChange={(event) => form.setData('time_note', event.target.value)}
                          className="field"
                        />
                      </FieldGroup>
                    </div>
                  </div>
                </div>
              ) : null}
            </Card>

            {/* ٣. ما الذي نُخرجه — الصيغُ واللغات */}
            <Card title={<StepTitle n={3} done text={t('lectures.create.produce.legend')} />}>
              {/*
                «الصفحة» سطرٌ ثابت لا خانةٌ معطّلة — T-95: خانةٌ لا تُلغى
                تُقرأ خياراً وليست خياراً. و«حزمة الصور — قريباً» خرجت من مسار
                الإنشاء؛ تبويبُ المعاينة يقولها لمن يطلبها.
              */}
              <div className="flex items-start gap-3 rounded-lg border border-primary/30 bg-primary/5 px-3.5 py-3">
                <Icon name="page" size={18} className="mt-0.5 shrink-0 text-primary" />
                <span>
                  <span className="block text-[14px] font-medium text-text">{t('lectures.create.produce.page')}</span>
                  <span className="mt-0.5 block text-[12.5px] text-text-muted">{t('lectures.create.produce.page_hint')}</span>
                </span>
              </div>

              <div className="mt-2.5">
                <OutputOption
                  icon="carousel"
                  label={t('lectures.create.outputs.carousel')}
                  note={rich_outputs ? undefined : t('lectures.create.outputs.locked')}
                  checked={form.data.want_carousel}
                  disabled={!rich_outputs}
                  onChange={(next) => form.setData('want_carousel', next)}
                />
              </div>

              {/*
                ★ **لغاتُ النشر هنا لا في «المظهر»** — T-95، ونقضٌ لموضعها في
                T-50 بقرار مالك المنتج: اللغةُ قرارُ محتوًى وجمهور، لا شكل.
                و**العربيةُ تُنزع كغيرها** (T-51)؛ والشرطُ لغةٌ واحدة على الأقلّ.
              */}
              <div role="group" aria-labelledby="languages-label" className="mt-5 border-t border-border pt-5">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h3 id="languages-label" className="text-[14px] font-medium text-text">
                    {t('lectures.create.produce.languages')}
                  </h3>

                  {localesCustom ? (
                    <button
                      type="button"
                      onClick={() => form.setData('locales', defaults.locales)}
                      className="text-[13px] font-medium text-primary underline-offset-4 hover:underline"
                    >
                      {t('lectures.create.appearance.reset')}
                    </button>
                  ) : (
                    <span className="text-[12.5px] text-text-faint">{t('lectures.create.appearance.default')}</span>
                  )}
                </div>

                <p className="mt-0.5 mb-3 text-[12.5px] leading-relaxed text-text-muted">
                  {t('lectures.create.produce.languages_hint')}
                </p>

                <div className="flex flex-wrap gap-2">
                  {locales.map((locale) => {
                    const chosen = form.data.locales.includes(locale.key);
                    const last = chosen && form.data.locales.length === 1;

                    return (
                      <button
                        key={locale.key}
                        type="button"
                        role="checkbox"
                        aria-checked={chosen}
                        aria-disabled={last || undefined}
                        title={last ? t('lectures.create.produce.languages_min') : undefined}
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
                          chosen
                            ? 'border-primary bg-primary text-white'
                            : 'border-border bg-surface text-text hover:border-border-strong',
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

                {/* ترجمةُ الآيات المعتمدة لكلّ لغةٍ مختارة — تُبذر ولا يترجمها نموذج (T-38). */}
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
              </div>
            </Card>

            {/*
              المظهر — مطويٌّ افتراضاً (قرار ٩ أيلول) **ولا يُخفي ما سيُطبَّق**:
              القالب واللوحة ظاهران رقاقتين وهو مطويّ — T-95.
            */}
            <Card
              title={t('lectures.create.appearance.legend')}
              action={
                <span
                  className={cn(
                    'rounded-full px-2.5 py-0.5 text-[12px] font-medium',
                    appearanceCustom ? 'bg-accent/10 text-accent' : 'bg-surface-alt text-text-muted',
                  )}
                >
                  {t(appearanceCustom ? 'lectures.create.appearance.custom' : 'lectures.create.appearance.default')}
                </span>
              }
            >
              {appearanceOpen ? (
                <div className="flex flex-col gap-5">
                  <p className="text-[12.5px] leading-relaxed text-text-muted">{t('lectures.create.appearance.hint')}</p>

                  {/* ★ عنوانٌ ووصفٌ لا صورٌ مصغّرة — T-60. والرؤيةُ في «عاين المظهر» بالقالب الحقيقي. */}
                  <div role="radiogroup" aria-labelledby="template-label">
                    <h3 id="template-label" className="mb-2 text-[14px] font-medium text-text">
                      {t('lectures.create.appearance.template')}
                    </h3>

                    <div className="grid gap-2 sm:grid-cols-2">
                      {templates.map((item) => {
                        const chosen = form.data.template === item.key;

                        return (
                          <label
                            key={item.key}
                            className={cn(
                              'flex cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2.5 transition-colors',
                              chosen ? 'border-primary bg-primary/5' : 'border-border bg-surface hover:border-border-strong',
                            )}
                          >
                            <input
                              type="radio"
                              name="template"
                              value={item.key}
                              checked={chosen}
                              onChange={() => form.setData('template', item.key)}
                              className="mt-1 size-4 shrink-0 accent-[var(--primary)]"
                            />
                            <span className="min-w-0">
                              <span className="block text-[14px] font-medium text-text">{item.name}</span>
                              <span className="block text-[12.5px] leading-snug text-text-muted">{item.description}</span>
                            </span>
                          </label>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <h3 className="mb-2 text-[14px] font-medium text-text">{t('lectures.create.appearance.palette')}</h3>
                    <PalettePicker
                      variant="chips"
                      palettes={palettes}
                      value={form.data.palette}
                      onChange={(key) => form.setData('palette', key)}
                    />
                  </div>

                  <div className="flex flex-wrap items-center gap-2 border-t border-border pt-4">
                    <Button variant="secondary" onClick={() => setPreviewOpen(true)}>
                      <Icon name="eye" size={16} />
                      {t('lectures.create.appearance.preview')}
                    </Button>

                    {appearanceCustom ? (
                      <Button
                        variant="ghost"
                        onClick={() => {
                          form.setData('template', defaults.template);
                          form.setData('palette', defaults.palette);
                        }}
                      >
                        {t('lectures.create.appearance.reset')}
                      </Button>
                    ) : null}

                    <span className="ms-auto">
                      <Button variant="ghost" onClick={() => setAppearanceOpen(false)}>
                        {t('lectures.create.appearance.done')}
                      </Button>
                    </span>
                  </div>
                </div>
              ) : (
                <div className="flex flex-wrap items-center gap-2">
                  <span className="inline-flex items-center gap-2 rounded-full border border-border bg-surface-alt px-3 py-1 text-[13.5px] text-text">
                    <Icon name="page" size={15} className="text-text-muted" />
                    {template?.name}
                  </span>

                  <span className="inline-flex items-center gap-2 rounded-full border border-border bg-surface-alt px-3 py-1 text-[13.5px] text-text">
                    <Swatches colors={palette?.swatches ?? []} />
                    {palette?.name}
                  </span>

                  <span className="ms-auto flex flex-wrap gap-2">
                    <Button variant="ghost" onClick={() => setPreviewOpen(true)}>
                      <Icon name="eye" size={16} />
                      {t('lectures.create.appearance.preview')}
                    </Button>
                    <Button variant="secondary" onClick={() => setAppearanceOpen(true)}>
                      {t('lectures.create.appearance.change')}
                    </Button>
                  </span>

                  {appearanceCustom ? (
                    <button
                      type="button"
                      onClick={() => {
                        form.setData('template', defaults.template);
                        form.setData('palette', defaults.palette);
                      }}
                      className="basis-full text-start text-[13px] font-medium text-primary underline-offset-4 hover:underline"
                    >
                      {t('lectures.create.appearance.reset')}
                    </button>
                  ) : null}
                </div>
              )}
            </Card>

            {/*
              على الجوال شريطٌ سفليّ صلبٌ بعرض البطاقات (T-94، وشكلُه في
              `StickyBar` — T-102)؛ وعلى الحاسوب يخلفه عمودُ «ملخّص طلبك».
            */}
            <StickyBar className="lg:hidden">
              <p className="min-w-0 flex-1 text-[13px] text-text-muted">{cost}</p>
              {/* الحدّ يمنع البدء هنا، فلا يُصرف موردٌ ثمّ يُقال «تجاوزت». */}
              <Button type="submit" loading={form.processing} disabled={blocked}>
                {t('lectures.create.submit')}
              </Button>
            </StickyBar>
          </div>

          <aside className="hidden lg:sticky lg:top-0 lg:block">
            <RequestSummary
              rows={[
                {
                  icon: kind === 'text' ? 'text' : kind === 'upload' ? 'upload' : 'link',
                  label: t('lectures.create.summary.source'),
                  value: sourceLabel(kind, form.data.source_url, form.data.transcript_text, upload.phase === 'idle' ? null : upload.file, preflight),
                },
                {
                  icon: 'page',
                  label: t('lectures.create.summary.lesson'),
                  value: meetingReady ? `${form.data.title_ar.trim()} — ${form.data.speaker_name.trim()}` : null,
                },
                {
                  icon: 'carousel',
                  label: t('lectures.create.summary.outputs'),
                  value: [
                    t('lectures.create.summary.page'),
                    form.data.want_carousel ? t('lectures.create.summary.carousel') : null,
                  ].filter((part): part is string => part !== null).join('، '),
                },
                {
                  icon: 'globe',
                  label: t('lectures.create.summary.languages'),
                  value: chosenLocales.map((locale) => locale.name).join('، '),
                },
                {
                  icon: 'brand',
                  label: t('lectures.create.summary.appearance'),
                  value: `${template?.name ?? ''} · ${palette?.name ?? ''}`,
                },
              ]}
              todo={[
                sourceReady ? null : t('lectures.create.summary.todo_source'),
                form.data.title_ar.trim() !== '' ? null : t('lectures.create.summary.todo_title'),
                form.data.speaker_name.trim() !== '' ? null : t('lectures.create.summary.todo_speaker'),
              ].filter((item): item is string => item !== null)}
              cost={cost}
              blocked={blocked}
              processing={form.processing}
            />
          </aside>
        </div>
      </form>

      <AppearanceDrawer
        open={previewOpen}
        template={form.data.template}
        palette={form.data.palette}
        venueMode={form.data.venue_mode}
        onClose={() => setPreviewOpen(false)}
      />
      <CostConfirm
        open={confirmingCreate}
        title={t('lectures.create.submit')}
        action={t('lectures.create.confirm_action')}
        monthly
        confirmLabel={t('lectures.create.submit')}
        onConfirm={() => {
          setConfirmingCreate(false);
          send();
        }}
        onCancel={() => setConfirmingCreate(false)}
      />
    </AppLayout>
  );
}

/** عنوانُ الخطوة برقمها — وعلامةُ اكتمالٍ مكان الرقم حين تكتمل. */
function StepTitle({ n, done, text }: { n: number; done: boolean; text: string }) {
  return (
    <span className="flex items-center gap-2.5">
      <span
        aria-hidden="true"
        className={cn(
          'nums-tabular flex size-6 shrink-0 items-center justify-center rounded-full text-[12.5px] font-semibold',
          done ? 'bg-success/12 text-success' : 'bg-primary/10 text-primary',
        )}
      >
        {done ? <Icon name="check" size={14} /> : toArabicIndic(n)}
      </span>
      {text}
    </span>
  );
}

/**
 * نمطُ النسبة — T-95.
 *
 * كان قائمةً منسدلة بمصطلحٍ لا يُفهم بلا شرح. فصار ثلاثةَ خياراتٍ بوصفها،
 * **وجملةً حيّة بألفاظ الصفحة نفسها** (`PageStrings`) تُري ما سيُطبع في
 * رأس الصفحة. و«الملقي وحده» و«بلا نسبة» تُطبعان اليوم سطراً واحداً —
 * كلاهما يُسقط اسم الجهة وحده (`summary/partials/attrib`) — فالجملةُ تقول
 * ذلك كما هو ولا تخترع فرقاً.
 */
function Attribution({
  modes, value, onChange, speaker, attribution, error,
}: {
  modes: string[];
  value: string;
  onChange: (mode: string) => void;
  speaker: string;
  attribution: Props['attribution'];
  error?: string;
}) {
  const name = speaker.trim() !== '' ? speaker.trim() : t('lectures.create.attribution.speaker_placeholder');
  const sentence = [
    `${attribution.lecture_by} ${name}`,
    value === 'institution' && attribution.venue !== '' ? `${attribution.lecture_at} ${attribution.venue}` : null,
  ].filter((part): part is string => part !== null).join(' · ');

  return (
    <div role="radiogroup" aria-labelledby="venue-label" className="mt-5">
      <h3 id="venue-label" className="text-[14px] font-medium text-text">
        {t('lectures.create.meeting.venue_mode')}
      </h3>
      <p className="mt-0.5 mb-3 text-[12.5px] text-text-muted">{t('lectures.create.attribution.hint')}</p>

      <div className="grid gap-2 sm:grid-cols-3">
        {modes.map((mode) => (
          <label
            key={mode}
            className={cn(
              'flex cursor-pointer flex-col rounded-lg border p-3 transition-colors',
              value === mode ? 'border-primary bg-primary/5' : 'border-border bg-surface hover:border-border-strong',
            )}
          >
            <span className="flex items-center gap-2">
              <input
                type="radio"
                name="venue_mode"
                value={mode}
                checked={value === mode}
                onChange={() => onChange(mode)}
                className="size-4 shrink-0 accent-[var(--primary)]"
              />
              <span className="text-[14px] font-medium text-text">{t(`lectures.create.meeting.venue.${mode}`)}</span>
            </span>
            <span className="mt-1 ps-6 text-[12.5px] leading-snug text-text-muted">
              {t(`lectures.create.attribution.about.${mode}`)}
            </span>
          </label>
        ))}
      </div>

      <div aria-live="polite" className="mt-3 rounded-md border border-dashed border-border-strong bg-surface-alt px-3.5 py-2.5">
        <p className="text-[12px] text-text-faint">{t('lectures.create.attribution.preview')}</p>
        <p className="mt-0.5 text-[14px] text-text">{sentence}</p>
        {value !== 'institution' ? (
          <p className="mt-1 text-[12.5px] text-text-muted">{t('lectures.create.attribution.no_venue')}</p>
        ) : null}
      </div>

      {error !== undefined ? <p role="alert" className="mt-2 text-[13px] text-danger">{error}</p> : null}
    </div>
  );
}

/**
 * «ملخّص طلبك» — T-95: ما سيُنتج وكم يكلّف قبل الضغط.
 *
 * **والناقصُ يُسمّى ولا يُمنع به الزرّ**: الخادم يتحقّق ويقول ما ينقص عند
 * كلّ حقل، وزرٌّ معطّلٌ بتخمينٍ في المتصفّح قد يمنع ما يقبله الخادم.
 */
function RequestSummary({
  rows, todo, cost, blocked, processing,
}: {
  rows: Array<{ icon: IconName; label: string; value: string | null }>;
  todo: string[];
  cost: string;
  blocked: boolean;
  processing: boolean;
}) {
  return (
    <section className="rounded-lg border border-border bg-surface shadow-card">
      <header className="border-b border-border px-5 py-3">
        <h2 className="text-[16px] font-semibold text-text">{t('lectures.create.summary.title')}</h2>
      </header>

      <dl className="flex flex-col gap-3 px-5 py-4">
        {rows.map((row) => (
          <div key={row.label} className="flex items-start gap-2.5">
            <Icon name={row.icon} size={16} className="mt-1 shrink-0 text-text-faint" />
            <div className="min-w-0">
              <dt className="text-[12px] text-text-faint">{row.label}</dt>
              <dd
                className={cn(
                  'wrap-anywhere text-[14px] leading-snug',
                  row.value !== null && row.value !== '' ? 'text-text' : 'text-text-faint',
                )}
              >
                {row.value !== null && row.value !== '' ? row.value : t('lectures.create.summary.missing')}
              </dd>
            </div>
          </div>
        ))}
      </dl>

      {todo.length > 0 ? (
        <div className="border-t border-border px-5 py-3">
          <p className="text-[12.5px] font-medium text-warning">{t('lectures.create.summary.todo')}</p>
          <ul className="mt-1.5 flex flex-col gap-1">
            {todo.map((item) => (
              <li key={item} className="flex items-center gap-2 text-[13px] text-text-muted">
                <span aria-hidden="true" className="size-1.5 shrink-0 rounded-full bg-warning" />
                {item}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <footer className="flex flex-col gap-3 rounded-b-lg border-t border-border bg-surface-alt px-5 py-4">
        <p className="text-[12.5px] leading-relaxed text-text-muted">{cost}</p>
        {/* الحدّ يمنع البدء هنا، فلا يُصرف موردٌ ثمّ يُقال «تجاوزت». */}
        <div className="[&>button]:w-full">
          <Button type="submit" loading={processing} disabled={blocked}>
            {t('lectures.create.submit')}
          </Button>
        </div>
      </footer>
    </section>
  );
}

/**
 * «عاين المظهر» — T-95: القالبُ الحقيقي على محتوى العيّنة، باللوحة والقالب
 * المختارين لهذا الملخّص، في لوحةٍ جانبية بجهازين.
 */
function AppearanceDrawer({
  open, template, palette, venueMode, onClose,
}: {
  open: boolean;
  template: string;
  palette: string;
  /** نمطُ النسبة — T-126: يُسقط سطر «المكان» عن هذه المعاينة كما يُسقطه عن المنشور. */
  venueMode: string;
  onClose: () => void;
}) {
  const [device, setDevice] = useState<Device>('mobile');
  const frame = useRef<HTMLIFrameElement>(null);
  const close = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    close.current?.focus();

    const escape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', escape);
    return () => document.removeEventListener('keydown', escape);
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  const src = `/panel/lectures/appearance-preview?template=${encodeURIComponent(template)}&palette=${encodeURIComponent(palette)}&venue_mode=${encodeURIComponent(venueMode)}`;

  return (
    <div role="dialog" aria-modal="true" aria-labelledby="appearance-title" className="fixed inset-0 z-50 flex">
      <div aria-hidden="true" onClick={onClose} className="absolute inset-0 bg-black/35" />

      {/* `ms-auto` تدفعه إلى الطرف النهائيّ — اليسار في RTL — كدرجٍ يُسحب من جانب. */}
      <div className="relative ms-auto flex h-full w-full max-w-3xl flex-col bg-surface shadow-lifted">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-3">
          <div className="min-w-0">
            <h2 id="appearance-title" className="text-[17px] font-semibold text-text">
              {t('lectures.create.appearance.preview_title')}
            </h2>
            <p className="text-[12.5px] text-text-muted">{t('lectures.create.appearance.preview_hint')}</p>
          </div>

          <div className="flex items-center gap-2">
            <Segmented
              legend={t('jobs.preview.device.legend')}
              value={device}
              onChange={setDevice}
              options={[
                { key: 'mobile', label: t('jobs.preview.device.mobile'), icon: 'phone' },
                { key: 'desktop', label: t('jobs.preview.device.desktop'), icon: 'desktop' },
              ]}
            />
            <button
              ref={close}
              type="button"
              aria-label={t('lectures.create.appearance.close')}
              onClick={onClose}
              className="rounded-md p-2 text-text-muted transition-colors hover:bg-surface-alt hover:text-text"
            >
              <Icon name="close" size={18} />
            </button>
          </div>
        </header>

        <div className="min-h-0 flex-1 overflow-y-auto">
          <DeviceFrame device={device} src={src} title={t('lectures.create.appearance.preview_title')} frameRef={frame} />
        </div>
      </div>
    </div>
  );
}

/** ثلاث دوائر لونية متراكبة — اللوحةُ بنظرةٍ في رقاقة. */
function Swatches({ colors }: { colors: string[] }) {
  return (
    <span className="flex" aria-hidden="true">
      {colors.map((color, index) => (
        <span
          key={color}
          className={cn('size-3.5 rounded-full ring-2 ring-surface-alt', index > 0 && '-ms-1')}
          style={{ backgroundColor: color }}
        />
      ))}
    </span>
  );
}

/**
 * اختيار المصدر.
 *
 * وكانت ثلاثةَ تبويباتٍ نصّية صغيرة تحت خطٍّ رفيع، فلا يكاد يُرى أنّ ثَمّ
 * ثلاثةَ طرقٍ للبدء. فصارت ثلاثةَ أسطحٍ ظاهرة، لكلٍّ أيقونتُه.
 */
function SourcePicker({ kind, onChange }: { kind: SourceKind; onChange: (kind: SourceKind) => void }) {
  return (
    <div role="tablist" className="grid gap-2 sm:grid-cols-3">
      {SOURCES.map((source) => {
        const selected = kind === source.kind;
        const soon = source.soon === true;

        return (
          <button
            key={source.kind}
            type="button"
            role="tab"
            aria-selected={selected}
            aria-disabled={soon}
            disabled={soon}
            onClick={() => onChange(source.kind)}
            className={cn(
              'flex items-center gap-2.5 rounded-lg border px-3.5 py-3 text-start text-[14px] transition-colors',
              soon
                ? 'cursor-not-allowed border-border bg-surface-alt text-text-muted opacity-75'
                : selected
                  ? 'border-primary bg-primary/8 font-medium text-primary'
                  : 'border-border bg-surface text-text-muted hover:border-border-strong hover:text-text',
            )}
          >
            <Icon name={source.icon} size={18} className="shrink-0" />
            <span className="truncate">{t(`lectures.create.source.tabs.${source.kind}`)}</span>
            {soon ? (
              <span className="ms-auto shrink-0 rounded-full border border-border bg-surface px-2 py-0.5 text-[12px] font-medium text-text-muted">
                {t('lectures.create.source.soon')}
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}

/**
 * مخرَجٌ يُطلب.
 *
 * «تظهران معطَّلتين مع سطر ترقية، لا مخفيّتين» — §3-ب. رؤية ما لا تملكه
 * دافع للترقية، وإخفاؤه يمنع معرفته.
 */
function OutputOption({
  icon, label, note, checked, disabled = false, onChange,
}: {
  icon: IconName;
  label: string;
  note?: string;
  checked: boolean;
  disabled?: boolean;
  onChange?: (next: boolean) => void;
}) {
  return (
    <label
      className={cn(
        'flex items-start gap-3 rounded-lg border p-3.5 transition-colors',
        disabled ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:border-border-strong',
        checked && !disabled ? 'border-primary/45 bg-primary/5' : 'border-border bg-surface',
      )}
    >
      <input
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange?.(event.target.checked)}
        className="mt-0.5 size-4 shrink-0 accent-[var(--primary)]"
      />

      <span className="min-w-0">
        <span className="flex items-center gap-2">
          <Icon name={icon} size={16} className={checked && !disabled ? 'text-primary' : 'text-text-faint'} />
          <span className={cn('text-[14px]', disabled ? 'text-text-faint' : 'text-text')}>{label}</span>
        </span>

        {note !== undefined ? <span className="mt-1 block text-[12px] text-text-muted">{note}</span> : null}
      </span>
    </label>
  );
}

function PreflightPanel({ result, limit }: { result: Preflight; limit: number }) {
  if (!result.ok) {
    return <ErrorState message={result.message ?? t('lectures.create.preflight.unavailable')} />;
  }

  return (
    <div className="rounded-lg border border-border bg-surface-alt p-4" aria-live="polite">
      <h3 className="mb-2.5 flex items-center gap-2 text-[15px] font-semibold text-text">
        <Icon name="check" size={16} className="text-success" />
        {t('lectures.create.preflight.title')}
      </h3>

      <dl className="grid gap-x-4 gap-y-2 text-[14px] sm:grid-cols-[max-content_1fr]">
        <dt className="text-text-muted">{t('lectures.create.preflight.video_title')}</dt>
        <dd className="text-text">{result.title ?? '—'}</dd>

        <dt className="text-text-muted">{t('lectures.create.preflight.duration')}</dt>
        <dd className="text-text">
          {result.duration_minutes
            ? t('lectures.create.preflight.duration_minutes', {
              count: toArabicIndic(result.duration_minutes),
            })
            : '—'}
        </dd>

        <dt className="text-text-muted">{t('lectures.create.preflight.captions')}</dt>
        <dd className="text-text">
          {result.has_arabic_captions
            ? t('lectures.create.preflight.captions_found')
            : t('lectures.create.preflight.captions_missing')}
        </dd>
      </dl>

      {/*
        «وإن تجاوزت المدّة حدّ الاشتراك ظهرت الرسالة **هنا**، لا بعد البدء»
        — §3-أ. والفرق أنّ الثانية تُغضب من انتظر ثمّ عرف.
      */}
      {result.exceeds_limit ? (
        <p className="mt-3 rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-[14px] text-danger">
          {t('lectures.create.preflight.too_long', {
            minutes: toArabicIndic(result.duration_minutes ?? 0),
            limit: toArabicIndic(result.limit_minutes ?? limit),
          })}
        </p>
      ) : null}
    </div>
  );
}

/**
 * حالُ الرفع تحت صندوق الإفلات — كم رُفع، أو «نفحص»، أو «انقطع فأكمِل»، أو المدّة.
 * **ملفُّ نصف غيغابايت يأخذ دقائق، والصمتُ طولَها يُظنّ تعطّلاً.**
 */
function UploadStatus({ upload, onResume, onCancel }: { upload: Upload; onResume: () => void; onCancel: () => void }) {
  if (upload.phase === 'idle') {
    return null;
  }

  if (upload.phase === 'error') {
    return <p role="alert" className="text-[13px] text-danger">{upload.message}</p>;
  }

  if (upload.phase === 'ready') {
    return (
      <p className="flex items-center gap-2 text-[13px] text-success" aria-live="polite">
        <Icon name="check" size={15} />
        {toArabicIndic(t('lectures.create.source.upload_ready', { minutes: Math.max(1, Math.ceil(upload.seconds / 60)) }))}
      </p>
    );
  }

  const percent = upload.file.size === 0 ? 0 : Math.min(100, Math.round((upload.sent / upload.file.size) * 100));

  return (
    <div aria-live="polite" className="flex flex-col gap-1.5">
      <div className="h-1.5 overflow-hidden rounded-full bg-surface-alt">
        <div
          className={cn('h-full transition-[width]', upload.phase === 'paused' ? 'bg-warning' : 'bg-primary')}
          style={{ width: `${percent}%` }}
        />
      </div>

      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-text-muted">
        <span className="min-w-0 flex-1">
          {upload.phase === 'checking'
            ? t('lectures.create.source.upload_checking')
            : toArabicIndic(`${t('lectures.create.source.upload_progress', {
              sent: megabytes(upload.sent),
              total: megabytes(upload.file.size),
            })} · ${percent}%`)}
        </span>

        {upload.phase === 'paused' ? (
          <Button type="button" variant="secondary" onClick={onResume}>
            {t('lectures.create.source.upload_resume')}
          </Button>
        ) : null}

        {upload.phase !== 'checking' ? (
          <button type="button" className="text-text-muted underline hover:text-danger" onClick={onCancel}>
            {t('lectures.create.source.upload_cancel')}
          </button>
        ) : null}
      </div>

      {upload.phase === 'paused' ? <p className="text-[13px] text-warning">{upload.message}</p> : null}
    </div>
  );
}

/** «١٢٫٥ ميغابايت» — بلفظ اللوحة. */
function megabytes(bytes: number): string {
  return t('lectures.create.source.upload_mb', { n: (bytes / (1024 * 1024)).toFixed(1) });
}

/** ما يُقال عن المصدر في «ملخّص طلبك» — ولا يُقال عن مصدرٍ لم يُعطَ شيء. */
function sourceLabel(kind: SourceKind, url: string, text: string, file: File | null, preflight: Preflight | null): string | null {
  if (kind === 'text') {
    return text.trim() !== '' ? t('lectures.create.summary.text_source') : null;
  }

  if (kind === 'upload') {
    return file !== null ? file.name : null;
  }

  if (kind !== 'url' || url.trim() === '') {
    return null;
  }

  if (preflight?.ok === true && preflight.title) {
    return preflight.title;
  }

  try {
    return new URL(url.trim()).host;
  } catch {
    return url.trim();
  }
}

/** اسمُ اليوم بالعربية من تاريخٍ ميلاديّ `YYYY-MM-DD` — «الجمعة». */
function weekdayOf(iso: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    return '';
  }

  const date = new Date(`${iso}T12:00:00`);

  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('ar', { weekday: 'long' });
}

function sameSet(a: string[], b: string[]): boolean {
  return a.length === b.length && a.every((item) => b.includes(item));
}
