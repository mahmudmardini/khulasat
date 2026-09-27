<?php

declare(strict_types=1);

/*
 * Quotas and plans — SCREENS.md screen 9, spec §11. Adapted in T-133.
 *
 * **Counted in summaries, not tokens** — the word "token" never appears on
 * a customer screen (T-23).
 */

return [

    'quota' => [
        'used' => 'You have used :used of :limit this month',
        'remaining' => ':count left',
        'exhausted' => 'This month\'s quota is used up',
        'unlimited' => 'Unlimited',
        'label' => 'This month\'s quota',
    ],

    'plan' => [
        'label' => 'Plan',
        'upgrade' => 'Upgrade plan',
        'locked_feature' => 'This feature is on a higher plan',
    ],

    'suspended' => [
        'title' => 'Summary production is paused',
        'body' => 'Your published pages are working exactly as they were and nothing has been touched. What is paused is creating new summaries.',
    ],

    'page' => [
        'title' => 'Subscription and usage',
        'subtitle' => 'What you have this month, and what has been used of it.',

        'plan_card' => 'Your plan',
        'customised' => 'Your limits are set specially',
        'customised_hint' => 'One or more limits have been raised for you above the plan, and the figures below are what your account actually runs on.',

        'rich_outputs' => 'Carousel and image pack',
        'rich_outputs_on' => 'Included in your plan',
        'rich_outputs_off' => 'On the Institution plan and above',

        'limits' => 'Your subscription limits',
        'usage' => 'This month\'s usage',

        'monthly_quota' => 'Summaries per month',
        'daily_cap' => 'Summaries per day',
        'max_lecture_minutes' => 'Longest lecture',
        'transcription_minutes_quota' => 'Audio transcription minutes',
        'regenerations_per_summary' => 'Regenerations per summary',

        'minutes' => ':count minutes',
        'summaries' => ':count summaries',
        'used_of' => ':used of :limit',

        'ledger' => 'This month\'s record',
        'ledger_empty' => 'Nothing used this month',
        'ledger_empty_body' => 'Every summary you create, and every minute of transcription, appears here with its date.',
        'ledger_when' => 'Date',
        'ledger_what' => 'Event',
        'ledger_summary' => 'Summary',
        'ledger_amount' => 'Counted',

        'events' => [
            'generate' => 'New summary',
            'regenerate' => 'Regeneration',
            'transcribe' => 'Audio transcription',
        ],

        /*
         * **No button that does nothing.** There is no payment gateway at
         * this stage, so upgrading is a conversation with us rather than a
         * click — and a button promising what it cannot do is worse than no
         * button at all.
         */
        'change' => 'Upgrade your plan or adjust your limits',
        'change_body' => 'Get in touch to raise your quota or change your plan.',
    ],
];
