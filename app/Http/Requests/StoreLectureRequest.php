<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Enums\TranscriptErrorCode;
use App\Enums\VenueMode;
use App\Http\Controllers\LectureController;
use App\Models\Lecture;
use App\Models\MediaUpload;
use App\Services\Transcript\ChunkedUploads;
use App\Support\Arabic;
use App\Support\Render\Palette;
use App\Support\Transcript\SourceKey;
use App\Support\Transcript\SourceUrlGuard;
use App\Support\Transcript\TranscriptResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * The new-summary form — SCREENS.md §3، والمهمّة T-16.
 *
 * **والرابط يُفحص هنا بحارس SSRF نفسه لا بقاعدة `url` وحدها** (§12 المخطر
 * الثالث). فقاعدة Laravel تقبل `http://169.254.169.254` وهي عنوان بيانات
 * السحابة، والحارس يردّه — والمدخل من المستخدم لا يُوثَق به.
 */
class StoreLectureRequest extends FormRequest
{
    private ?MediaUpload $mediaUpload = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_kind' => ['required', Rule::in(['url', 'upload', 'text'])],
            'source_url' => ['nullable', 'string', 'max:2048'],
            // رفعٌ اكتمل أجزاءً وفُحص — §5-أ-4-ب، و{@see \App\Http\Controllers\UploadController}.
            'upload_id' => ['nullable', 'string', 'uuid'],
            // إقرارُ المستخدم بأنّ المصدر مكرَّرٌ عن قصد — T-65.
            'confirm_duplicate' => ['nullable', 'boolean'],
            'transcript_text' => ['nullable', 'string', 'max:2000000'],

            'title_ar' => ['required', 'string', 'max:255'],
            'subtitle_ar' => ['nullable', 'string', 'max:255'],
            'speaker_name' => ['required', 'string', 'max:255'],
            'speaker_title' => ['nullable', 'string', 'max:255'],

            'gregorian_date' => ['nullable', 'date'],
            'hijri_date' => ['nullable', 'string', 'max:64'],
            'weekday' => ['nullable', 'string', 'max:32'],
            'time_note' => ['nullable', 'string', 'max:64'],

            'venue_mode' => ['required', Rule::enum(VenueMode::class)],

            // المخرجات المطلوبة — SCREENS.md §3-ب. والصفحة دائماً، فلا خانة
            // لها. وحزمة الصور بلا حقل: عارضها مؤجَّل (T-20).
            'want_carousel' => ['sometimes', 'boolean'],
            // اختبارُ الفهم — T-195. لكلّ الشرائح، ولا يُحتسب من الحصّة.
            'want_quiz' => ['sometimes', 'boolean'],

            /*
             * إعداداتُ المخرَج لهذه المحاضرة — طلبُ مالك المنتج، ٩ أيلول.
             * **ولا `required`**: من لم يفتح «إعدادات متقدّمة» يبقى على
             * افتراض الجهة.
             */
            'template' => ['sometimes', 'nullable', 'string', Rule::in(array_column(SummaryTemplate::cases(), 'value'))],
            'locales' => ['sometimes', 'nullable', 'array'],
            'locales.*' => ['string', Rule::in(array_column(Locale::cases(), 'value'))],
            'palette' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Palette::all()))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $kind = $this->string('source_kind')->toString();

            if ($kind === 'url') {
                $this->validateUrl($validator);
                $this->validateNotDuplicate($validator);
            }

            if ($kind === 'upload' && ! $validator->errors()->has('upload_id')) {
                $this->validateUpload($validator);
            }

            // ★ **النصُّ الملصوق القصير يُردّ هنا، قبل أن تُنشأ المهمّة** — T-221.
            //   كان يُقبل ويُحتسب من الحصّة، ثمّ يقف الخطّ عند ٥٠٠ كلمة برسالةٍ
            //   عن «الترجمة المرافقة» تقول إنّ الحصّة لم تُمسّ.
            if ($kind === 'text') {
                $words = TranscriptResult::countWords((string) $this->input('transcript_text'));

                if ($words < TranscriptErrorCode::MINIMUM_WORDS) {
                    // والأرقامُ هنديةٌ في العربية كما يكتبها العدّاد تحت الحقل.
                    $count = app()->getLocale() === 'ar' ? Arabic::toArabicIndicDigits($words) : (string) $words;

                    $validator->errors()->add('transcript_text', trans('lectures.create.source.text_too_short', [
                        'count' => $count,
                        'min' => TranscriptErrorCode::minimumWordsLabel(),
                    ]));
                }
            }
        });
    }

    /** الرفعُ الذي يُربط بالمهمّة — مكتملٌ ومفحوص. */
    public function mediaUpload(): ?MediaUpload
    {
        return $this->mediaUpload;
    }

    /**
     * الملفّ المرفوع — §5-أ-4-ب. **فُحص عند اكتمال رفعه** (المحتوى، ووجود
     * الصوت، والمدّة) في {@see ChunkedUploads::complete()}،
     * فالمستخدم عرف عيبَه قبل أن يملأ بقيّة النموذج. ويبقى هنا أن يكون الرفعُ
     * لهذه الجهة، مكتملاً، لم يُكنس — وأن تتّسع له حدودُ الاشتراك الآن.
     */
    private function validateUpload(Validator $validator): void
    {
        $id = (string) $this->input('upload_id');

        if ($id === '') {
            $validator->errors()->add('upload_id', trans('lectures.create.source.upload_required'));

            return;
        }

        // تحت نطاق الجهة: رفعُ جهةٍ أخرى لا يوجد هنا.
        $upload = MediaUpload::query()->find($id);

        if ($upload === null || ! $upload->isReady()) {
            $validator->errors()->add('upload_id', trans('lectures.create.source.upload_expired'));

            return;
        }

        $limit = (int) ($this->user()?->tenant?->max_lecture_minutes ?? 0);
        $seconds = (int) $upload->duration_seconds;

        // والحدُّ يُفحص ثانيةً: قد يتبدّل الاشتراك بين الرفع والإرسال.
        if ($limit > 0 && $seconds > $limit * 60) {
            $validator->errors()->add('upload_id', trans('lectures.create.preflight.too_long', [
                'minutes' => (int) ceil($seconds / 60),
                'limit' => $limit,
            ]));

            return;
        }

        $this->mediaUpload = $upload;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'source_url' => trans('lectures.create.source.url_label'),
            'upload_id' => trans('lectures.create.source.upload_label'),
            'transcript_text' => trans('lectures.create.source.text_label'),
            'title_ar' => trans('lectures.create.meeting.title'),
            'speaker_name' => trans('lectures.create.meeting.speaker'),
            'venue_mode' => trans('lectures.create.meeting.venue_mode'),
        ];
    }

    /**
     * المصدرُ نفسُه أُدخل قبلُ — T-65.
     *
     * **ولا يمنع، بل يقف ويسأل.** فإعادةُ التوليد من الفيديو نفسه بلغاتٍ
     * أخرى أو بقالبٍ آخر حاجةٌ مشروعة، **والتكرارُ الصامت ليس كذلك**: وقع
     * فعلاً ثلاثَ مرّات على فيديو واحد، فجرى الخطُّ ثلاثاً وصُرف ثمنُه
     * ثلاثاً ولم يعلم صاحبُه إلّا حين فتح قائمة المهامّ.
     *
     * ★ **وفي الخادم لا في الشاشة وحدها**: الحقلُ يصل من طلبٍ مصنوعٍ باليد
     * كذلك — نظير حارس الشريحة في {@see LectureController}.
     */
    private function validateNotDuplicate(Validator $validator): void
    {
        if ($this->boolean('confirm_duplicate')) {
            return;
        }

        $key = SourceKey::for($this->input('source_url'));
        $tenantId = $this->user()?->tenant_id;

        if ($key === null || $tenantId === null) {
            return;
        }

        // **العزلُ بالجهة**: مصدرُ جهةٍ لا يحجب جهةً أخرى.
        $existing = Lecture::query()
            ->where('tenant_id', $tenantId)
            ->where('source_key', $key)
            ->latest('id')
            ->first();

        if ($existing === null) {
            return;
        }

        /*
         * **على مفتاحه الخاصّ لا على `source_url`** — فالشاشة تعرض إقرارَ
         * التكرار عند هذا الخطأ وحده، **ولا تعرضه عند خطأ الحارس الأمنيّ**.
         * ولو جُمعا في مفتاحٍ واحد لاحتاجت الشاشةُ أن تتحسّس نصَّ الرسالة
         * لتفرّق بينهما — وذلك يتكسّر بأوّل تعديلٍ في الصياغة.
         */
        $validator->errors()->add('confirm_duplicate', trans('lectures.create.source.duplicate', [
            'title' => (string) $existing->title_ar,
        ]));
    }

    private function validateUrl(Validator $validator): void
    {
        $url = (string) $this->input('source_url');

        try {
            SourceUrlGuard::assertAllowed($url);
        } catch (Throwable) {
            // **ولا يُسرَّب سبب الرفض التقني.** «المضيف غير مسموح» و«العنوان
            // داخليّ» يكشفان للمهاجم شكل الحاجز، والمستخدم لا ينتفع بهما.
            $validator->errors()->add('source_url', trans('errors.transcript.host_not_allowed'));
        }
    }
}
