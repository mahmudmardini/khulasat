<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// CLAUDE.md §1: declare(strict_types=1) في كل ملفّ PHP مكتوب يدوياً.
// يُفحص آلياً حتى لا يعتمد على انتباه المراجع.
it('declares strict types in every hand-written PHP file', function (): void {
    $files = Finder::create()
        ->files()
        ->name('*.php')
        ->in([base_path('app'), base_path('bootstrap'), base_path('config'), base_path('database'), base_path('routes'), base_path('tests')])
        ->notPath('cache');

    $missing = [];

    foreach ($files as $file) {
        if (! str_contains($file->getContents(), 'declare(strict_types=1);')) {
            $missing[] = str_replace(base_path().'/', '', $file->getRealPath());
        }
    }

    expect($missing)->toBe([]);
});
