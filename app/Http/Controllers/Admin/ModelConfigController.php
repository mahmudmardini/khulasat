<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\Stage;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ModelConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * النماذج — SCREENS.md §د من لوحة المشرف، والمهمّة T-21.
 *
 * «التغيير يسري فوراً **بلا نشر**» — فالجدول يُقرأ في كلّ نداء، ولا قيمةَ
 * منه في `.env` ولا في حزمةٍ مبنيّة.
 *
 * **وكلّ تعديل يُقيَّد**: تغييرُ نموذجٍ يغيّر جودة المنتج وكلفته معاً، وهذان
 * أخطر ما في المنتج. فتبديلٌ يمرّ بلا أثر يُسأل عنه بعد شهرٍ ولا جواب.
 */
class ModelConfigController extends Controller
{
    /** ما يُحرَّر ويُقيَّد. و`stage` ليست منها: المرحلة هوية الصفّ لا خاصّيته. */
    private const EDITABLE = [
        'provider', 'model_id', 'max_tokens', 'thinking_level',
        'fallback_provider', 'fallback_model_id',
        'timeout_seconds', 'max_retries', 'on_exhausted',
        'input_price_per_m', 'output_price_per_m', 'is_active',
    ];

    public function __construct(private readonly RecordAudit $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Models/Index', [
            'configs' => ModelConfig::query()
                ->orderBy('stage')
                ->orderByDesc('id')
                ->get()
                ->map(static fn (ModelConfig $config): array => [
                    'id' => $config->id,
                    'stage' => $config->stage->value,
                    ...$config->only(self::EDITABLE),
                    'updated_at' => $config->updated_at?->toDateTimeString(),
                ])
                ->all(),
            'stages' => array_map(static fn (Stage $s): string => $s->value, Stage::cases()),
            'audit' => AuditEvent::query()
                ->where('action', AuditAction::ModelConfigUpdated->value)
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(static fn (AuditEvent $event): array => [
                    'id' => $event->id,
                    'admin_name' => $event->admin_name,
                    'subject_label' => $event->subject_label,
                    'changes' => $event->changes,
                    'occurred_at' => $event->occurred_at?->toDateTimeString(),
                ])
                ->all(),
        ]);
    }

    public function update(Request $request, ModelConfig $config): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
            'model_id' => ['required', 'string', 'max:128'],
            'max_tokens' => ['required', 'integer', 'min:256', 'max:200000'],
            'thinking_level' => ['required', 'string', 'max:32'],
            'fallback_provider' => ['nullable', 'string', 'max:64'],
            'fallback_model_id' => ['nullable', 'string', 'max:128'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:900'],
            'max_retries' => ['required', 'integer', 'min:0', 'max:5'],
            'on_exhausted' => ['required', Rule::in(['fail', 'fallback', 'queue'])],
            'input_price_per_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'output_price_per_m' => ['required', 'numeric', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $config->fill(array_intersect_key($data, array_flip(self::EDITABLE)));

        $changes = $this->audit->diff($config, self::EDITABLE);

        if ($changes === []) {
            return back();
        }

        $config->save();

        $this->audit->handle(
            AuditAction::ModelConfigUpdated,
            $config,
            $changes,
            $data['note'] ?? null,
        );

        return back()->with('message', trans('admin.models.saved'));
    }
}
