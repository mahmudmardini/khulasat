<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * أين يذهب مَن معه جلسة؟ — T-129.
 *
 * ★ **الحارسان لا حارسٌ واحد.** المشرف العامّ على حارس `admin` المنفصل
 * (المواصفة §10)، فـ`auth()->check()` — وهي على الافتراضي `web` — تراه
 * زائراً. وكلُّ موضعٍ يسأل «أدخلَ هذا أم لا؟» ثمّ يسأل الحارس الافتراضي
 * وحده يُخطئ في المشرف حتماً.
 *
 * وهي في فعلٍ واحد لأنّ السائلَين اثنان — جذرُ الموقع وحارسُ `guest` —
 * **وتفرُّقُهما هو الذي صنع الدورة**: الحارس يصرف المشرف إلى `/`،
 * و`/` لا تعرفه فتردّه إلى صفحة التعريف التي جاء منها.
 */
class ResolveLandingDestination
{
    /**
     * رابطُ لوحةِ صاحبِ الجلسة، أو `null` للزائر الذي لا جلسة له.
     */
    public function handle(): ?string
    {
        if (Auth::guard('admin')->check()) {
            return route('admin.home');
        }

        if (Auth::guard('web')->check()) {
            return route('lectures.index');
        }

        return null;
    }
}
