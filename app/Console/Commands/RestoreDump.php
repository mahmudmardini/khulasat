<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PDO;
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

    /**
     * ★ **القاعدةُ تُنشأ إن لم توجد**، بمستخدم PostgreSQL الذي في `.env`. فمن
     * ثبّت PostgreSQL عنده مستخدمُه، يكتبه في `.env`، ولا يُنشئ قاعدةً بيده.
     *
     * @param  array<string, mixed>  $config
     */
    private function createDatabaseIfMissing(array $config): void
    {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=postgres', $config['host'], $config['port']),
            (string) $config['username'],
            (string) ($config['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $name = (string) $config['database'];
        $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $exists->execute([$name]);

        if ($exists->fetchColumn() === false) {
            $pdo->exec('CREATE DATABASE "'.str_replace('"', '""', $name).'"');
            $this->info("أُنشئت القاعدة {$name}.");
            DB::purge();
        }
    }

    public function handle(): int
    {
        $dump = database_path(self::DUMP);

        if (! is_file($dump)) {
            $this->error("لا ملفّ للقاعدة في {$dump}.");

            return self::FAILURE;
        }

        $config = config('database.connections.'.config('database.default'));

        try {
            $this->createDatabaseIfMissing($config);
            $hasTables = Schema::hasTable('migrations');
        } catch (Throwable $e) {
            $this->error('تعذّر الاتّصال بقاعدة البيانات: '.$e->getMessage());
            $this->line('تحقّق من مستخدم PostgreSQL وكلمة مروره في DB_USERNAME وDB_PASSWORD في ملفّ .env.');

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
