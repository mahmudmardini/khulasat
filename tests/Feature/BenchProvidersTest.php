<?php

declare(strict_types=1);

use Bench\Providers;

require_once __DIR__.'/../../bench/lib/Providers.php';

/*
 * سببُ التوقّف — T-62. **والمبتورُ ليس نجاحاً**: بلغ `sonnet-5` سقفَه في
 * T-59 فانقطع JSON في منتصف كلمة، وطبع الحارسُ ✓.
 */

it('يعلن البترَ إخفاقاً عند المزوّدين الثلاثة', function (string $provider, string $reason): void {
    expect(Providers::stopError($provider, $reason))->toContain('مبتور');
})->with([
    ['anthropic', 'max_tokens'],
    ['openai', 'length'],
    ['google', 'MAX_TOKENS'],
]);

// و`RECITATION` عند جوجل تحجب نقلَ نصٍّ محفوظٍ حرفاً — وهو عملُ المرحلة ٥.
it('يعلن ما سوى البتر من التوقّف إخفاقاً', function (): void {
    expect(Providers::stopError('google', 'RECITATION'))->toContain('RECITATION')
        ->and(Providers::stopError('anthropic', 'refusal'))->toContain('refusal');
});

it('لا يعدّ التوقّفَ الطبيعيّ ولا المجهولَ إخفاقاً', function (string $provider, ?string $reason): void {
    expect(Providers::stopError($provider, $reason))->toBeNull();
})->with([
    ['anthropic', 'end_turn'],
    ['openai', 'stop'],
    ['google', 'STOP'],
    ['google', null],
]);
