<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Compliance\RequestAccountClosure;
use App\Http\Controllers\Controller;
use App\Support\Arabic;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * سياسة الخصوصية المعلنة — T-207، بندُ الخصوصية في وثيقة المرجعية.
 *
 * **والمددُ من الإعداد الذي يكنس به الكود**، لا أرقامٌ مكتوبة في النصّ:
 * فلو غُيّرت مدّةُ حذف نصوص «تحقّق» تغيّر ما تقوله الصفحة معها.
 */
class PrivacyController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        $replace = [
            'verify_days' => (int) config('khulasah.verify.retention_days', 7),
            'per_hour' => (int) config('khulasah.verify.per_hour', 5),
            'upload_days' => (int) config('khulasah.transcript.upload.retention_days', 3),
            'grace_days' => RequestAccountClosure::GRACE_DAYS,
        ];

        /** @var list<array{heading: string, items: list<string>}> $sections */
        $sections = trans('privacy.sections');

        return Inertia::render('Public/Privacy', [
            'title' => __('privacy.title'),
            'intro' => __('privacy.intro'),
            'sections' => array_map(static fn (array $section): array => [
                'heading' => $section['heading'],
                'items' => array_map(
                    static fn (string $item): string => strtr($item, array_combine(
                        array_map(static fn (string $k): string => ':'.$k, array_keys($replace)),
                        array_map(Arabic::toArabicIndicDigits(...), $replace),
                    )),
                    $section['items'],
                ),
            ], $sections),
            'contact' => __('privacy.contact'),
            'contactLink' => __('privacy.contact_link'),
            'updated' => __('privacy.updated', ['date' => '٤ أكتوبر ٢٠٢٦']),
        ]);
    }
}
