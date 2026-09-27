<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ترجمةُ ملخّصٍ إلى لغة — T-38.
 *
 * **نقلٌ لا حكم**: لا حالةَ مراجعةٍ هنا ولا درجةَ شاهد. التحقّق جرى على
 * العربيّ مرّةً واحدة، ولا يُعاد لكلّ لغة.
 *
 * ★★ **ولا لفظَ شاهدٍ مترجَماً فيه.** الآيةُ تُقرأ ترجمتُها المعتمدة من
 * {@see QuranTranslation}، والحديثُ يبقى بلفظه العربي في `body_json`
 * ويُضاف إليه معنًى من `meanings` **موسوماً**.
 */
class SummaryTranslation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'body_json' => 'array',
            'meanings' => 'array',
            'requested_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** للّغة متنٌ مترجَمٌ يُعرض — وترجمةٌ بلا متنٍ ليست ترجمة. */
    public function isReady(): bool
    {
        return trim((string) $this->body_html) !== '';
    }

    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }
}
