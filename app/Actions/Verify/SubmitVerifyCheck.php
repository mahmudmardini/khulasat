<?php

declare(strict_types=1);

namespace App\Actions\Verify;

use App\Enums\VerifyCheckStatus;
use App\Exceptions\VerifyRefused;
use App\Jobs\RunVerifyCheckJob;
use App\Models\VerifyCheck;
use App\Services\Quota\SpendCap;
use Illuminate\Support\Facades\RateLimiter;

/**
 * قبولُ طلبٍ في أداة «تحقّق» ووضعُه في الطابور — T-181.
 *
 * **واحدٌ للصفحة وللواجهة البرمجية**، فلا يُحرس بابٌ ويُترك الآخر: الحدّان
 * وسقفُ الإنفاق يُفحصان هنا قبل أن يُنشأ صفّ أو يُنادى نموذج (القاعدة الخامسة).
 *
 * **والمعدّلُ يُعدّ بالقبول لا بالمحاولة**: نصٌّ رُدّ لطوله لم يُكلّف شيئاً،
 * فلا يأكل من حصّة صاحبه.
 */
final class SubmitVerifyCheck
{
    public function __construct(private readonly SpendCap $spendCap) {}

    /** @throws VerifyRefused */
    public function handle(string $text, string $ip): VerifyCheck
    {
        if ($this->spendCap->isHalted()) {
            throw VerifyRefused::paused();
        }

        $fingerprint = VerifyCheck::fingerprint($ip);
        $key = 'verify:'.$fingerprint;
        $perHour = max(1, (int) config('khulasah.verify.per_hour'));

        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw VerifyRefused::rateLimited(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 3600);

        $check = VerifyCheck::query()->create([
            'status' => VerifyCheckStatus::Queued,
            'text' => $text,
            'char_count' => mb_strlen($text),
            'ip_hash' => $fingerprint,
            'created_at' => now(),
        ]);

        RunVerifyCheckJob::dispatch($check);

        return $check->refresh();
    }

    /** النصّ كما يُفحص: بلا فراغٍ في طرفيه، وبأسطرٍ موحّدة. */
    public static function clean(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
