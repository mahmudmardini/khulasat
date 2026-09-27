<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\BuildCostOverview;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة الكلفة — T-22، مؤجَّلةً حتى رُفع التأجيل بقرار مالك المنتج
 * (11 أيلول 2026) بعد أن صار `MODEL_GATEWAY=real` مُشغَّلاً فعلاً.
 */
class CostController extends Controller
{
    public function __invoke(BuildCostOverview $overview): Response
    {
        return Inertia::render('Admin/Cost/Index', $overview->handle());
    }
}
