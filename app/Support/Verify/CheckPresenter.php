<?php

declare(strict_types=1);

namespace App\Support\Verify;

use App\Models\VerifyCheck;

/**
 * طلبُ «تحقّق» كما يُعرض — T-181: **شكلٌ واحد للصفحة وللواجهة البرمجية**.
 *
 * فما يراه الباحث في الصفحة هو ما يقرؤه المطوّر من JSON، حقلاً بحقل. ولو
 * كُتب لكلٍّ شكلُه لاختلفا بعد أوّل حقلٍ يُضاف إلى أحدهما ويُنسى في الآخر.
 */
final class CheckPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(VerifyCheck $check, bool $withText = true): array
    {
        $purged = $check->isPurged();

        return [
            'id' => $check->id,
            'status' => $check->status->value,
            'settled' => $check->status->isSettled(),
            'purged' => $purged,
            'url' => route('verify.show', $check->id),
            'char_count' => $check->char_count,
            'text' => $withText && ! $purged ? $check->text : null,
            'evidence_count' => $check->evidence_count,
            'counts' => $purged ? null : ($check->report['counts'] ?? null),
            'findings' => $purged ? null : ($check->report['findings'] ?? null),
            'error' => $check->error_code === null ? null : [
                'code' => $check->error_code,
                'message' => (string) __(match ($check->error_code) {
                    'spend_cap' => 'verify.errors.paused',
                    'corpus_unavailable' => 'verify.errors.unavailable',
                    default => 'verify.errors.failed',
                }),
            ],
            'created_at' => $check->created_at->toIso8601String(),
            'completed_at' => $check->completed_at?->toIso8601String(),
            'expires_at' => $check->created_at->copy()->addDays((int) config('khulasah.verify.retention_days'))->toIso8601String(),
        ];
    }
}
