<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Restore the ready database shipped with the repository.
 *
 * ★ **بدلُ خطوات التحميل كلّها على جهاز لجنة التحكيم.** في `database/dump/`
 * قاعدةٌ جاهزة: الجداول، والمصحف وترجماته، والحديث بكتبه التسعة، وإعداد
 * النماذج، وجهة التجربة وحساباتها الثلاثة، وملخّصٌ تجريبيٌّ منشور. فلا يلزم
 * المحكّمَ حسابٌ لدى Quran Foundation، ولا أوامر التحميل واحداً واحداً.
 *
 * **ولا يكتب فوق قاعدةٍ فيها جداول** إلّا بـ`--force`، فلا تُمحى بيانات
 * أحدٍ بأمرٍ أُعيد سهواً. ثمّ يعيد رسم الصفحات المنشورة من القاعدة، لأنّ
 * ملفّاتها على القرص لا في القاعدة، بلا نداء نموذج.
 */
class RestoreDump extends Command
{
    protected $signature = 'khulasah:restore-dump
                            {--force : يمحو ما في القاعدة ثمّ يستعيد}';

    protected $description = 'يستعيد القاعدة الجاهزة من database/dump: المصحف والحديث وحسابات اللجنة وملخّصاً تجريبياً';

    private const DUMP = 'dump/khulasat.sql.gz';

    public function handle(): int
    {
        $dump = database_path(self::DUMP);

        if (! is_file($dump)) {
            $this->error("لا ملفّ للقاعدة في {$dump}.");

            return self::FAILURE;
        }

        try {
            $hasTables = Schema::hasTable('migrations');
        } catch (Throwable $e) {
            $this->error('تعذّر الاتّصال بقاعدة البيانات: '.$e->getMessage());
            $this->line('أنشئ القاعدة والمستخدم كما في الـREADME، وتحقّق من قيم DB_* في .env.');

            return self::FAILURE;
        }

        if ($hasTables && ! $this->option('force')) {
            $this->warn('في القاعدة جداولُ من قبل، فلم يُستعد شيء. وللمحو ثمّ الاستعادة: php artisan khulasah:restore-dump --force');

            return self::FAILURE;
        }

        if ($hasTables) {
            DB::statement('DROP SCHEMA public CASCADE');
            DB::statement('CREATE SCHEMA public');
        }

        $config = config('database.connections.'.config('database.default'));

        $this->info('تُستعاد القاعدة… (دقيقةٌ أو اثنتان)');

        $result = Process::forever()
            ->env(['PGPASSWORD' => (string) ($config['password'] ?? '')])
            ->run(sprintf(
                'gunzip -c %s | psql -q -v ON_ERROR_STOP=1 -h %s -p %s -U %s -d %s',
                escapeshellarg($dump),
                escapeshellarg((string) $config['host']),
                escapeshellarg((string) $config['port']),
                escapeshellarg((string) $config['username']),
                escapeshellarg((string) $config['database']),
            ));

        if (! $result->successful()) {
            $this->error('تعذّرت الاستعادة: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->call('khulasah:refresh-pages');

        $this->info('تمّت. شغّل المنصّة (composer run start) وافتح http://localhost:8000/panel/login');

        return self::SUCCESS;
    }
}
