<?php

declare(strict_types=1);

use App\Contracts\PublishStore;
use App\Enums\OutputType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * الشرائح لا تُنشر — T-204، بقرار مالك المنتج.
 *
 * كانت تُرفع صفحةً على `{slug}/carousel` مع كلّ نشر، ولا يصلها قارئ: صفحةُ
 * الملخّص لا تُشير إليها. والمسارُ لا يخدمها بعد اليوم، **فما رُفع منها يُحذف**
 * ولا يبقى ملفّاً عامّاً بلا رابط، ويُمسح رابطُه من الصفّ. والصفُّ نفسُه يبقى:
 * فيه نصوصُ الشرائح وقالبُها، ومنه تُنشأ الصور.
 *
 * ولا رجوعَ له: ملفٌّ حُذف لا يُستعاد بمسح عمود.
 */
return new class extends Migration
{
    public function up(): void
    {
        $store = app(PublishStore::class);

        DB::table('outputs')
            ->where('type', OutputType::Carousel->value)
            ->where(fn ($query) => $query->whereNotNull('storage_path')->orWhereNotNull('public_url'))
            ->select(['id', 'storage_path'])
            ->lazyById()
            ->each(function (object $row) use ($store): void {
                if ($row->storage_path !== null) {
                    // **وتعذّرُ الحذف لا يوقف الترحيل**: المسارُ لا يخدم الملفَّ على أيّ حال.
                    try {
                        $store->delete((string) $row->storage_path);
                    } catch (Throwable $failure) {
                        report($failure);
                    }
                }

                DB::table('outputs')->where('id', $row->id)->update(['storage_path' => null, 'public_url' => null]);
            });
    }

    public function down(): void
    {
        //
    }
};
