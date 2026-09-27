<?php

declare(strict_types=1);

namespace App\Services\Quran;

use App\Contracts\QuranContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Quran Foundation Content API with a developer account — T-161.
 *
 * **ولماذا حساب لا الواجهة القديمة المفتوحة:** شروط المطوّرين (تحديث ١٤ أيلول
 * ٢٠٢٦) تمنع حفظ المحتوى أكثر من أسبوع إلّا ما جاء من Content Sync بمزامنةٍ
 * كلّ سبعة أيام. فالعثماني والترجمات من **لقطاتها** (`quran_core:1` و
 * `translations:<id>`)، والإملائي من واجهة المحتوى الموثَّقة — ولا لقطةَ له.
 *
 * والمفتاحان في `.env` وحدها (CLAUDE.md §2 القاعدة السادسة)، والرمزُ يُخبَّأ
 * دون عمره بقليل فلا يُطلب لكلّ نداء.
 */
final class QuranFoundationContent implements QuranContent
{
    private const TOKEN_CACHE_KEY = 'quran_foundation.token';

    public function core(): array
    {
        $chapters = [];
        $verses = [];

        foreach ($this->snapshot('quran_core', 1) as $record) {
            match ($record['record_type'] ?? null) {
                'chapter' => $chapters[(int) $record['chapter_number']] = (string) $record['name_arabic'],
                'verse' => $verses[(string) $record['verse_key']] = (string) $record['text_uthmani'],
                default => null,
            };
        }

        return ['chapters' => $chapters, 'verses' => $verses];
    }

    public function imlaei(): array
    {
        $verses = $this->get('/api/v4/quran/verses/imlaei')['verses'] ?? [];

        return collect(is_array($verses) ? $verses : [])
            ->mapWithKeys(static fn (array $v): array => [(string) $v['verse_key'] => (string) $v['text_imlaei']])
            ->all();
    }

    public function translation(int $id): array
    {
        return collect($this->snapshot('translations', $id))
            ->filter(static fn (array $r): bool => isset($r['verse_key']))
            ->mapWithKeys(static fn (array $r): array => [(string) $r['verse_key'] => (string) ($r['text'] ?? '')])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function snapshot(string $group, int $id): array
    {
        $records = $this->get("/api/v4/resources/snapshots/{$group}/{$id}")['records'] ?? [];

        return is_array($records) ? array_values($records) : [];
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return (array) Http::timeout((int) config('khulasah.quran.timeout'))
            ->retry(3, 2000)
            ->withHeaders([
                'x-auth-token' => $this->token(),
                'x-client-id' => $this->clientId(),
            ])
            ->get(rtrim((string) config('khulasah.quran.api_url'), '/').$path)
            ->throw()
            ->json();
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            $token = Http::asForm()
                ->timeout(30)
                ->withBasicAuth($this->clientId(), $this->secret())
                ->post((string) config('khulasah.quran.oauth_url'), [
                    'grant_type' => 'client_credentials',
                    'scope' => 'content',
                ])
                ->throw()
                ->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('لم تُرجع Quran Foundation رمز دخول.');
            }

            return $token;
        });
    }

    private function clientId(): string
    {
        return $this->credential('client_id', 'QURAN_CLIENT_ID');
    }

    private function secret(): string
    {
        return $this->credential('client_secret', 'QURAN_CLIENT_SECRET');
    }

    private function credential(string $key, string $env): string
    {
        $value = trim((string) config("khulasah.quran.{$key}"));

        if ($value === '') {
            throw new RuntimeException("{$env} فارغ في .env — أنشئ تطبيق خادم في Developer Console لدى Quran Foundation وضع مفتاحيه.");
        }

        return $value;
    }
}
