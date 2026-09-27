<?php

declare(strict_types=1);

/*
 * Output languages — T-38, {@see App\Enums\Locale}, adapted in T-133.
 *
 * ★ These name the languages a SUMMARY is published in — not the language
 * of the dashboard, which each user now picks for themselves (T-133).
 */

return [

    'label' => 'Output languages',

    'hint' => 'Each language you choose gets its own published summary, and this is your institution\'s default — '
        .'it can be changed per summary from the create screen. Evidence is verified against the Arabic '
        .'original either way.',

    'source_note' => 'Source language — evidence is verified against it.',

    'min' => 'Choose at least one language.',

    'saved' => 'Output languages saved.',

    /*
     * ★ The meaning-translation tag — an acceptance criterion in T-38:
     * "someone reading the translation alone would take it for the hadith".
     */
    'meaning_only' => 'Meaning',

    'quran_credit' => 'Translation of the meanings of the Qur\'an',
];
