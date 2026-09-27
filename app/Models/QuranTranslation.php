<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use Illuminate\Database\Eloquent\Model;

/**
 * One approved translation of one ayah — T-38.
 *
 * **لا يحمل BelongsToTenant**: الترجمات المعتمدة واحدةٌ لكل الجهات كالمصحف،
 * وليست بيانات مستأجر.
 *
 * ★★ **ولا يكتب فيه نموذج.** يُملأ من `khulasah:seed-quran-translations`
 * وحده، من ترجماتٍ معتمدةٍ منشورة. وقراءةُ الآية من هنا تجعل ترجمتها
 * **حتميّةً كمطابقتها** — لا تتبدّل بين تشغيلين ولا يمسّها اختيارُ نموذج.
 */
class QuranTranslation extends Model
{
    protected $table = 'quran_translations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'surah' => 'integer',
            'ayah' => 'integer',
            'translation_id' => 'integer',
            'locale' => Locale::class,
        ];
    }

    /**
     * ترجمةُ آيةٍ بعينها، أو `null`.
     *
     * **و`null` جوابٌ صحيح** لا إخفاق: لغةُ المصدر لا ترجمة لها، ولغةٌ لم
     * تُبذَر بعدُ كذلك. ومن استدعى هذه فليَعرض العربيَّ وحده عند الغياب،
     * ولا يخترع نصّاً.
     */
    public static function lookup(Locale $locale, int $surah, int $ayah): ?self
    {
        if ($locale->isSource()) {
            return null;
        }

        return self::query()
            ->where('locale', $locale->value)
            ->where('surah', $surah)
            ->where('ayah', $ayah)
            ->first();
    }
}
