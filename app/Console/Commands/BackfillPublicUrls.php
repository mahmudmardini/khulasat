<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\PublishStore;
use App\Models\Output;
use Illuminate\Console\Command;

/**
 * تصحيحُ `outputs.public_url` القديم — T-128.
 *
 * **T-127 يُصلح الحساب عند النشر التالي وحده.** الصفوف المنشورة قبله —
 * أو المستوردة يدوياً بصفّ SQL خام كما في `MIGRATE-TENANTS-1-6.md` — تبقى
 * حاملةً `/storage/…/index.html` حتى يُعاد حسابها صراحةً. وهذا الأمر يفعل
 * ذلك وحده: **لا رسمَ ولا رفعَ ولا نداءَ نموذج**، تحويلُ نصٍّ صرفٌ على
 * `storage_path` القائم أصلاً — فيصلح صفّ الكاروسيل أيضاً رغم أنّ
 * `khulasah:refresh-pages` لا يعيد رسمه (مرحلةٌ تصرف مالاً).
 *
 * **صالحٌ لإعادة التشغيل بأمان**: لا يكتب صفّاً لم يتغيَّر رابطه.
 */
final class BackfillPublicUrls extends Command
{
    protected $signature = 'khulasah:backfill-public-urls {--dry-run : يُحصي ولا يكتب}';

    protected $description = 'تصحيح public_url القديم على صفوف outputs القائمة — بلا رسمٍ ولا نداء نموذج';

    public function handle(PublishStore $store): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;

        Output::query()
            ->whereNotNull('storage_path')
            ->whereNotNull('public_url')
            ->orderBy('id')
            ->chunkById(100, function ($outputs) use ($store, $dry, &$changed): void {
                /** @var Output $output */
                foreach ($outputs as $output) {
                    $fresh = $store->url((string) $output->storage_path);

                    if ($fresh === $output->public_url) {
                        continue;
                    }

                    $this->line("#{$output->id}: {$output->public_url} → {$fresh}");
                    $changed++;

                    if (! $dry) {
                        $output->forceFill(['public_url' => $fresh])->save();
                    }
                }
            });

        $this->newLine();
        $this->line($dry ? "سيتغيّر {$changed} صفّاً." : "تغيَّر {$changed} صفّاً.");

        return self::SUCCESS;
    }
}
