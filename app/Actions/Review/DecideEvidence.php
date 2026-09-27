<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Actions\Stages\WriteBody;
use App\Enums\ReviewDecision;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\User;
use App\Support\Render\ContentObject;
use RuntimeException;

/**
 * يُثبت قرار المراجع في شاهد — SCREENS.md الشاشة 5.
 *
 * **وما يُنشر هو `matched_text`** — يقرؤه {@see WriteBody}
 * و{@see ContentObject}. فالقرار هنا ليس وسماً إدارياً،
 * بل هو الذي يحسم **أيّ لفظٍ يظهر على صفحة الجهة**.
 */
final class DecideEvidence
{
    public function handle(EvidenceItem $item, ReviewDecision $decision, User $actor): EvidenceItem
    {
        if ($actor->tenant_id !== $item->tenant_id) {
            throw new RuntimeException('لا يُراجع شواهدَ جهةٍ من ليس منها.');
        }

        // §10: `editor` مراجعةٌ ونشر، و`viewer` قراءة.
        if (! $actor->role->canPublish()) {
            throw new RuntimeException('لا يملك دورُك حسمَ الشواهد.');
        }

        if ($decision === ReviewDecision::Source && $item->matched_text === null) {
            throw new RuntimeException('لا لفظ مصدرٍ يُثبَت: هذا الشاهد لم يُطابَق.');
        }

        if ($decision === ReviewDecision::AsQuoted) {
            /*
             * **يُنسخ لفظ الدرس إلى `matched_text` ولا يُترك فارغاً.** فالقارئ
             * لاحقاً `matched_text ?? raw_text`، ولو تُرك لعمل الفرعُ الثاني
             * فبدا الأمر صحيحاً — حتى يأتي قرارٌ آخر يملأ الحقل فينقلب
             * المنشور بلا أن يقرّر أحد.
             */
            $item->matched_text = $item->raw_text;
        }

        $item->review_status = $decision->resultingStatus();
        $item->resolved_by = $actor->id;
        $item->resolved_at = now();

        $item->save();

        return $item;
    }

    /** ما بقي معلّقاً في المهمّة بعد هذا القرار. */
    public function pendingAfter(EvidenceItem $item): int
    {
        return $item->summaryJob()
            ->first()
            ?->evidenceItems()
            ->where('review_status', ReviewStatus::Pending->value)
            ->count() ?? 0;
    }
}
