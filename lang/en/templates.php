<?php

declare(strict_types=1);

/*
 * Output template names and descriptions — T-45, adapted in T-133.
 *
 * A description says WHO a template suits, not what it looks like. "A serif
 * face and wide margins" does not help a content manager choose; "for long
 * lessons and for people reading on paper" does.
 */

return [

    'label' => 'Page template',
    'hint' => 'How the published page looks. Your colour palette works in every template, so switching does not undo your identity.',
    'current' => 'Currently selected',
    'saved' => 'Template saved.',

    'classic' => [
        'label' => 'Classical',
        'description' => 'Ornamented paper, gold and the Amiri typeface — for sermons and religious lessons.',
    ],

    'modern' => [
        'label' => 'Modern',
        'description' => 'Clean white with light cards — for teaching lessons and general content.',
    ],

    'journal' => [
        'label' => 'Journal',
        'description' => 'Editorial, with numbered sections — for long lessons and for reading on paper.',
    ],

    'lesson' => [
        'label' => 'Teaching lesson',
        'description' => 'Numbered sections, a verse box up front and highlighted tasks — for courses and structured teaching.',
    ],

    'brief' => [
        'label' => 'Brief card',
        'description' => 'No ornament, compact size — read in a minute and shared on a phone.',
    ],

    'research' => [
        'label' => 'Research',
        'description' => 'References up front rather than in a footnote, with a side margin for evidence — for documenting and reviewing.',
    ],
];
