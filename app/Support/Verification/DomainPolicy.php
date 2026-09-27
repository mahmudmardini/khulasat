<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\MatchStatus;
use App\Enums\UnverifiedPolicy;
use RuntimeException;

/**
 * How one domain settles evidence — المواصفة §7-5 وT-02ب.
 *
 * **العتبات وما يمنع النشر خاصّةٌ بالمجال لا عامّة، وهذا فرقٌ جوهري لا
 * تفصيل تنفيذ.** فالمجال الشرعي على تشدّده — المطابق تماماً وحده يمرّ، لأنّ
 * تغيير كلمةٍ في حديث أمرٌ جلل. ولو فُرض هذا على مجالٍ أكاديمي — تُقبل فيه
 * إعادة الصياغة ما دام المرجع صحيحاً — **لتوقّفت كلّ فقرة وصار المنتج عديم
 * النفع**.
 *
 * وهي تُقرأ من الإعدادات لا من الشيفرة، فمجالٌ ثانٍ يُضاف بكتلة إعدادات
 * لا بفرعٍ في `match`.
 *
 * @see khulasah-build-spec.md §7-5
 */
final readonly class DomainPolicy
{
    /** مجال المرحلة الأولى، وهو افتراض عمود `tenants.domain`. */
    public const DEFAULT = 'islamic';

    /**
     * @param  array<string, class-string>  $verifiers  (kind) ← المحقّق.
     * @param  array<string, array{statuses: list<MatchStatus>, grades: ?list<string>}>  $settling
     *                                                                                              ما يُنشر في كلّ وضع — §7-5.
     * @param  array<string, float>  $thresholds
     */
    public function __construct(
        public string $domain,
        public array $verifiers,
        public array $settling,
        public array $thresholds,
    ) {}

    /**
     * @throws RuntimeException مجالٌ لا إعداد له — ولا يُخترع له افتراض.
     */
    public static function for(string $domain): self
    {
        /** @var array<string, mixed>|null $config */
        $config = config("khulasah.domains.{$domain}");

        if (! is_array($config)) {
            // **ولا يُردّ إلى الشرعي صامتاً.** جهةٌ مجالها غير مضبوط تُصنَّف
            // شواهدُها بسياسةٍ ليست سياستها، وذلك أخفى من إخفاقٍ ظاهر.
            throw new RuntimeException("لا سياسة مضبوطة للمجال «{$domain}».");
        }

        $settling = [];

        foreach ($config['settling'] ?? [] as $mode => $rule) {
            $settling[$mode] = [
                'statuses' => array_map(MatchStatus::from(...), $rule['statuses'] ?? []),
                'grades' => $rule['grades'] ?? null,
            ];
        }

        return new self(
            domain: $domain,
            verifiers: $config['verifiers'] ?? [],
            settling: $settling,
            thresholds: $config['thresholds'] ?? [],
        );
    }

    /** أنواع الشواهد التي يعرفها هذا المجال — قائمة سماح لا `enum` في القاعدة. */
    public function knows(string $kind): bool
    {
        return array_key_exists($kind, $this->verifiers);
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return array_keys($this->verifiers);
    }

    /** @return class-string|null */
    public function verifierFor(string $kind): ?string
    {
        return $this->verifiers[$kind] ?? null;
    }

    public function threshold(string $key): float
    {
        if (! isset($this->thresholds[$key])) {
            throw new RuntimeException("لا عتبة «{$key}» في سياسة المجال «{$this->domain}».");
        }

        return (float) $this->thresholds[$key];
    }

    /** أتُقبل هذه الحالة في هذا الوضع؟ */
    public function allowsAutoPass(MatchStatus $status, UnverifiedPolicy $mode = UnverifiedPolicy::Disclose): bool
    {
        return in_array($status, $this->rule($mode)['statuses'], true);
    }

    /**
     * ★ **هل عُرف مصدره؟** — الضمانة الحاكمة في §7-5.
     *
     * > لا يُنشر لفظٌ إلا لفظَ مصدره مقروناً بدرجته. **وما جُهل مصدره لا
     * > يُنشر البتّة.**
     *
     * وشرطان: أن يُطابَق أصلاً، وأن تكون له **درجة منصوصة**. والمحقّق يضع
     * `has_stated_grade`، وغيابُه يعني ألّا درجة تُطلب في هذا النوع — كالآية،
     * فمطابقتها المصحفَ هي نصُّ مصدرها.
     *
     * **وعجزُنا عن التخريج ليس حكماً بالوضع**، فما لم يُعرف لا يُوصَف بشيء:
     * يُحذف صامتاً ويُسجَّل لصاحب الجهة.
     */
    public function hasStatedSource(VerificationResult $result): bool
    {
        return $result->status !== MatchStatus::None
            && ($result->sourceMeta['has_stated_grade'] ?? true) === true;
    }

    /**
     * أيُنشر هذا الشاهد بلا إنسان في هذا الوضع؟
     *
     * `disclose` يقبل التامّ والقريب بأيّ درجة منصوصة — ويُنشر كلٌّ مقروناً
     * ببيانه. و`review` لا يقبل إلا التامّ الصحيح، وما دونه يقف عند
     * `needs_review`.
     *
     * **ويُفترض المرور عند غياب الدرجة** لا يُمنع: الآية لا درجة لها، وحصرُ
     * `review` في `sahih` لو طُبّق عليها لأوقف كلّ آية مطابقة على مراجع.
     */
    public function publishes(VerificationResult $result, UnverifiedPolicy $mode): bool
    {
        $rule = $this->rule($mode);

        if (! in_array($result->status, $rule['statuses'], true)) {
            return false;
        }

        $grades = $rule['grades'];
        $grade = $result->sourceMeta['grade'] ?? null;

        return $grades === null || $grade === null || in_array($grade, $grades, true);
    }

    /** @return array{statuses: list<MatchStatus>, grades: ?list<string>} */
    private function rule(UnverifiedPolicy $mode): array
    {
        if (! isset($this->settling[$mode->value])) {
            throw new RuntimeException(
                "لا سياسة حسمٍ لوضع «{$mode->value}» في المجال «{$this->domain}»."
            );
        }

        return $this->settling[$mode->value];
    }
}
