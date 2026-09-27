<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Contracts\EvidenceVerifier;
use App\Contracts\VerifierRegistry;
use App\Support\Verification\DomainPolicy;

/**
 * The registry, read from the domain configuration — المواصفة §7-4 وT-02ب.
 *
 * يُسجَّل فيه المجال الشرعي وحده الآن: `ayah` ← {@see QuranVerifier}،
 * و`hadith` و`athar` ← {@see HadithVerifier}. **ولا مجال ثانٍ** — غرض هذه
 * البنية منعُ قفل المنتج على مجاله، لا التنبّؤ بالمجال القادم.
 *
 * والمحقّق يُبنى **بسياسة مجاله**، فتُقرأ عتباته منها لا من إعدادٍ عامّ.
 * وهذا ما يجعل «`exact` أو يقف» حكمَ الشرعي وحده لا حكمَ النظام.
 */
class ConfiguredVerifierRegistry implements VerifierRegistry
{
    /**
     * المحقّقون المبنيّون، بمفتاح المجال والصنف.
     *
     * فمحقّق الحديث يُسأل مرّتين في المهمّة الواحدة (`hadith` و`athar`)،
     * وبناؤه في كلّ مرّة يعيد بناء مزوّديه بلا داعٍ.
     *
     * @var array<string, EvidenceVerifier>
     */
    private array $resolved = [];

    public function for(string $domain, string $kind): ?EvidenceVerifier
    {
        // مجالٌ لا إعداد له يرمي من هنا — {@see DomainPolicy::for}.
        $policy = DomainPolicy::for($domain);
        $class = $policy->verifierFor($kind);

        if ($class === null) {
            // نوعٌ لا محقّق له. **يعود `null` ولا يُقرَّب إلى أقرب محقّق**،
            // فيُصنَّف شاهده `none` ويُرفع للمراجعة — §7-4.
            return null;
        }

        /** @var EvidenceVerifier */
        return $this->resolved["{$domain}:{$class}"] ??= app()->makeWith($class, [
            'policy' => $policy,
        ]);
    }
}
