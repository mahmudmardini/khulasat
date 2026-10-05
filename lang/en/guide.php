<?php

declare(strict_types=1);

return [
    'title' => 'User guide',
    'kicker' => 'Khulasat guide',
    'intro' => 'A plain-language walkthrough of everything in Khulasat: the screens, the buttons, what the system can do and what it cannot. Pick the guide that matches your role.',
    'choose' => 'Choose your guide',
    'open' => 'Open the guide',
    'not_sure' => 'Not sure of your role? Open your panel and click your name at the top of the screen; your role is shown under it. If you have never used an institution panel, start with the Institution owner guide: it is the most complete of the three.',

    'roles' => [
        'owner' => [
            'title' => 'Institution owner guide',
            'who' => 'For the person responsible for a mosque or centre on Khulasat.',
            'covers' => 'Creating, reviewing and publishing summaries, slides, comprehension quizzes, the institution identity, the subscription and the team.',
        ],
        'editor' => [
            'title' => 'Editor guide',
            'who' => 'For a team member who prepares and reviews summaries.',
            'covers' => 'Creating summaries, reviewing verses and hadiths, publishing, slides, comprehension quizzes, and what your role does not allow.',
        ],
        'admin' => [
            'title' => 'Platform administrator guide',
            'who' => 'For whoever runs the whole Khulasat platform.',
            'covers' => 'Institutions and their limits, jobs, complaints, incoming requests, AI models, costs and the spending cap.',
        ],
    ],

    'switch_role' => 'Guide',
    'language' => 'Guide language',
    'contents' => 'Contents',
    'open_contents' => 'Open contents',
    'close_contents' => 'Close contents',
    'chapter' => 'Chapter :n',
    'to_panel' => 'Sign in to the panel',
    'all_guides' => 'All guides',
    'print' => 'Print the guide',
    'back_to_top' => 'Back to top',

    'search' => [
        'label' => 'Search the guide',
        'placeholder' => 'Search the guide…',
        'shortcut' => 'Press / to search',
        'empty' => 'Nothing found for “:q”. Try another or shorter word.',
        'count' => ':count results',
        'clear' => 'Clear search',
    ],

    'copy_link' => 'Copy a link to this section',
    'copied' => 'Link to the section copied. Paste it anywhere to share it.',
    'copy_failed' => 'Could not copy. Copy the link from the address bar instead.',
    'share' => 'Share',

    'callouts' => [
        'note' => 'Note',
        'tip' => 'Tip',
        'warning' => 'Careful',
        'limit' => 'What the system does not do',
    ],
];
