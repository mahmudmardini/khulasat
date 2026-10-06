<?php

declare(strict_types=1);

use App\Actions\Stages\RenderAndPublish;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ما بعد النشر ما زال يُبنى — T-228.
 *
 * ★ **وعلّتُها أنّ المهمّة تبلغ `published` قبل أن ينتهي عملُها.**
 *
 * فـ{@see RenderAndPublish} ينشر اللغةَ الأولى — وبها تصير المهمّةُ منشورة —
 * ثمّ يترجم اللغاتِ التالية ويبني الشرائح في العامل نفسه، دقيقةً أو اثنتين.
 * **وبلا حالٍ محفوظة لا تفرق الشاشةُ بين «تُبنى الآن» و«سقطت»**: كانت تُري
 * الإنجليزيةَ «تعذّرت ترجمتُها» والشرائحَ «لم تُنشأ» وهما في الطريق، ولا
 * تتحدّث حتى يُحدِّث المستخدمُ الصفحة.
 *
 * `finishing_at` — بدأ بناءُ ما بعد اللغة الأولى ولم ينتهِ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->timestamp('finishing_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->dropColumn('finishing_at');
        });
    }
};
