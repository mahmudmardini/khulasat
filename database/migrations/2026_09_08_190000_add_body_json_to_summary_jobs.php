<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * متن الملخّص كتلاً — T-43.
 *
 * **ويبقى `body_html` معه لا بدلاً عنه.** فهو ما يُنشر ويُنقّى ويُعاين،
 * وكلّ ما بعد الكتابة مبنيٌّ عليه. والكتل تُحفظ لأنّها أصلُه: منها يُعاد
 * الرسم بلا نداء نموذج، وعليها تُبنى الترجمة في T-38 — ترجمةُ حقولِ نصٍّ
 * أسلمُ من ترجمة HTML.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->jsonb('body_json')->nullable()->after('structure_json');
        });
    }

    public function down(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->dropColumn('body_json');
        });
    }
};
