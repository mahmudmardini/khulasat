<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حذفُ الشعارات المحفوظة من هويّات الجهات كلّها — T-99، بطلب مالك المنتج
 * الصريح في ١١ أيلول ٢٠٢٦.
 *
 * **ولا يُسترجع**: `down()` لا يعيد ما حُذف، فالشعارُ لم يُنسخ إلى موضعٍ آخر.
 * ومن أراد شعاره رفعه ثانيةً من «هوية الجهة»، فيظهر في ملخّصاته الجديدة.
 *
 * ويُمسّ مفتاحُ الشعار وحده: اللوحةُ والقالبُ واللغاتُ والروابط تبقى كما هي.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->select(['id', 'brand_kit'])->lazyById()->each(static function (object $row): void {
            $kit = is_string($row->brand_kit) ? json_decode($row->brand_kit, true) : null;

            if (! is_array($kit) || ! array_key_exists('logo_data_uri', $kit)) {
                return;
            }

            unset($kit['logo_data_uri']);

            DB::table('tenants')->where('id', $row->id)->update([
                'brand_kit' => json_encode($kit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        });
    }

    public function down(): void
    {
        // لا رجعة: الشعارات لم تُحفظ في غير هذا الموضع.
    }
};
