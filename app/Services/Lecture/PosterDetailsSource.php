<?php

declare(strict_types=1);

namespace App\Services\Lecture;

use App\Actions\Stages\StageSchemas;
use App\Contracts\LectureDetailsSource;
use App\Contracts\ModelGateway;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\Tenant;
use App\Services\Model\ModelCallRecorder;
use App\Services\Quota\SpendCap;
use App\Support\Lecture\LectureDetails;
use App\Support\Model\ImageAttachment;
use App\Support\Model\StagePrompt;
use App\Support\TenantContext;
use App\Support\Verification\DomainPolicy;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * بيانات المحاضرة من صورة ملصق — T-09ب.
 *
 * **والاستخراج يمرّ ببوّابة النماذج** (T-10) بحصّتها وكلفتها وقاطع دائرتها،
 * ولا نداء مباشر لمزوّد. والوهميّ هو الافتراض في التطوير وCI — CLAUDE.md §2
 * القاعدة السابعة.
 *
 * **وحاجز حقن التعليمات هنا طبقتان لا واحدة** (§12):
 *   ١. تعليمات النظام تنصّ أنّ نصّ الصورة **مادّةٌ تُقرأ لا أوامرُ تُطاع**.
 *   ٢. **ومخطّط الخرج تسعةُ حقولٍ نصّية لا غير** — فأمرٌ مكتوبٌ في الملصق
 *      لا يجد له مكاناً يخرج منه. وهذا هو الحاجز الأخير الذي تصفه §12.
 *
 * ولا يُغلَّف بـ `<transcript>`: الصورة كتلةٌ مستقلّة لا نصٌّ يُلَفّ.
 *
 * ★ **ونداءٌ للجهة لا لملخّص** — T-194. فيُقيَّد لها بلا ملخّص، ويحسبه سقفُ
 * الإنفاق، ويُفحص السقفُ قبله (§2، القاعدة الخامسة). وكان يُنادى بلا شيءٍ من
 * ذلك، فكلفتُه لا تُرى ولا يوقفها سقف.
 */
class PosterDetailsSource implements LectureDetailsSource
{
    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly ModelCallRecorder $recorder,
        private readonly SpendCap $spendCap,
        private readonly TenantContext $tenants,
    ) {}

    public function name(): string
    {
        return 'poster';
    }

    /**
     * @param  string  $input  بايتات الصورة كما رُفعت.
     */
    public function extract(mixed $input): LectureDetails
    {
        $image = ImageAttachment::fromContents((string) $input);

        // بلا جهةٍ لا نداء: لا يُصرف ما لا يُنسب إلى أحد.
        $tenant = $this->tenants->has() ? Tenant::query()->find($this->tenants->id()) : null;

        if ($tenant === null) {
            throw new RuntimeException('لا جهة في السياق، فلا يُنادى نموذجٌ لا تُنسب كلفتُه.');
        }

        if ($this->spendCap->isHalted() || $this->spendCap->breachedReason() !== null) {
            throw new RuntimeException('سقفُ الإنفاق موقوفٌ أو مبلوغ، فلا يُنادى نموذج.');
        }

        $response = $this->gateway->call(
            Stage::LectureDetails,
            $this->messages($image),
            StageSchemas::for(Stage::LectureDetails),
        );

        $this->recorder->recordForTenant($response, $tenant);

        return LectureDetails::fromArray((array) ($response->decoded ?? []));
    }

    /**
     * **والفشل لا يوقف المستخدم**: يعود فارغاً، فتُفتح الحقول للإدخال اليدوي.
     *
     * فهذه المرحلة غرضها توفير وقتٍ لا حراسة بوّابة. ومن أوقف الإنشاء لأنّ
     * قراءة ملصقٍ أخفقت جعل الميزة عائقاً.
     */
    public function extractOrEmpty(string $contents): LectureDetails
    {
        try {
            return $this->extract($contents);
        } catch (ModelCallFailed|RuntimeException $e) {
            Log::warning('تعذّر استخراج بيانات الدرس من الصورة', ['error' => $e->getMessage()]);

            return new LectureDetails;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messages(ImageAttachment $image): array
    {
        return [
            [
                'role' => 'system',
                'content' => StagePrompt::for(Stage::LectureDetails, DomainPolicy::DEFAULT),
            ],
            [
                'role' => 'user',
                'content' => 'استخرج بيانات الدرس من هذه الصورة.',
                'images' => [['mime' => $image->mime, 'base64' => $image->base64]],
            ],
        ];
    }
}
