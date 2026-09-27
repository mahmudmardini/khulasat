<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Quran\QuranSync;
use Illuminate\Console\Command;

/**
 * المزامنة الأسبوعية مع Quran Foundation — T-161.
 *
 * شروطُهم (تحديث ١٤ أيلول ٢٠٢٦) لا تُجيز حفظ المحتوى أكثر من أسبوع إلّا
 * بمزامنةٍ كلّ سبعة أيام تُطبَّق فيها التغييرات كلُّها. فيُعاد جلبُ المصحف
 * والترجمات كاملةً ويُكتب كلٌّ في معاملته، ويُقيَّد الوقت في {@see QuranSync}.
 *
 * **وإخفاقُها لا يمسّ المنشور ولا التحقّق**: الجدولُ يبقى كما كان، ويقول فحصُ
 * الجاهزية إنّ المزامنة فاتت.
 */
class SyncQuran extends Command
{
    protected $signature = 'khulasah:sync-quran';

    protected $description = 'مزامنة المصحف وترجماته مع Quran Foundation — أسبوعياً بحكم شروطهم';

    public function handle(): int
    {
        $core = $this->call('khulasah:seed-quran', ['--force' => true]);

        // **ولا ترجمات بلا أصلٍ مزامَن**: الترجمة تُربط بمفاتيح المصحف المبذور.
        if ($core !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('khulasah:seed-quran-translations', ['--force' => true]);
    }
}
