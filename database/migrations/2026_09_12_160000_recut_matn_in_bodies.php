<?php

declare(strict_types=1);

use App\Actions\Stages\GuardEvidenceText;
use App\Support\Hadith\MatnExtractor;
use App\Support\Render\BodyBlocks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * قطعُ الإسناد من متون الصفحات المكتوبة — T-116، تتمّةُ إصلاح الشواهد.
 *
 * **ولفظُ الشاهد في المتن نسخةٌ ثانية لا استعلام.** فـ{@see GuardEvidenceText}
 * يُثبّت لفظَ المصدر في كتلة الشاهد **وقتَ الكتابة** (T-73)، ويُخبَز الناتج
 * في `body_html`. فإصلاحُ `evidence_items` وحده يُصلح قائمة التخريج
 * **ويترك المتن بإسناده** — وهو الذي رآه مالك المنتج في لقطاته.
 *
 * ★ **والقطعُ هو القطعُ نفسُه**: نصُّ الكتلة هو `matched_text` حرفاً (يضمنه
 * حارسُ T-73)، فإجراءُ المستخرِج عليه يُخرج ما أخرجه على الشاهد سواءً بسواء.
 * **ولا نموذجَ يُنادى**: إعادةُ المرحلة الخامسة تصرف مالاً وتُخرج صياغةً
 * أخرى، وهذا قطعٌ نصّيٌّ حتميّ لا كتابةَ فيه.
 *
 * **والترجماتُ مثلُها**: الشاهد عربيٌّ في كلّ اللغات (T-38)، فنسخةُ المتن
 * في `summary_translations` تحمل الإسناد نفسَه.
 */
return new class extends Migration
{
    /** كتلةُ الحديث كما يبنيها {@see BodyBlocks}. */
    private const HADITH_BLOCK = '/(<p class="text hadith" lang="ar" dir="rtl">)(.*?)(<\/p>)/su';

    public function up(): void
    {
        foreach (['summary_jobs', 'summary_translations'] as $table) {
            DB::table($table)
                ->whereNotNull('body_html')
                ->where('body_html', 'like', '%text hadith%')
                ->select('id', 'body_html')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        $body = $this->recut((string) $row->body_html);

                        if ($body !== (string) $row->body_html) {
                            DB::table($table)->where('id', $row->id)->update(['body_html' => $body]);
                        }
                    }
                });
        }
    }

    /**
     * **ولا يُمسّ من الكتلة إلّا نصُّها**: الوسمُ وصنفُه وما حوله كما هو.
     */
    private function recut(string $body): string
    {
        return (string) preg_replace_callback(self::HADITH_BLOCK, static function (array $match): string {
            $text = html_entity_decode($match[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $matn = MatnExtractor::extract($text);

            // وما لم يُقطع منه شيء يُعاد كما كان بترميزه الأصليّ.
            if ($matn === $text || trim($matn) === '') {
                return $match[0];
            }

            return $match[1].htmlspecialchars($matn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$match[3];
        }, $body);
    }

    /** **ولا رجعةَ لها** — الإسنادُ المقطوع لا يُستعاد من المتن. */
    public function down(): void {}
};
