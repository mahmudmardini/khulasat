<?php

declare(strict_types=1);

/*
 * The index and create screens — SCREENS.md screens 2 and 3, task T-16.
 * Adapted in T-133.
 *
 * "Every string comes from lang/. No string written inside a component, not
 * even (Save)." No emoji, no word "token", no model name, no error code.
 */

return [

    'index' => [
        'title' => 'Summaries',
        'subtitle' => 'Everything you have prepared, and what is waiting on your decision first.',
        'search' => 'Search titles and speakers',

        'quick' => [
            'title' => 'Start from a link',
            'hint' => 'Paste the YouTube link to the lecture, and we will check it for you before spending anything.',
            'cta' => 'Continue',
        ],

        /*
         * The call about what is awaiting a decision. The count covers the
         * whole institution, not the visible page — otherwise it would say
         * "two citations" when the truth is twenty further down.
         */
        'awaiting' => [
            'title' => 'You have :count summaries waiting for your review',
            'body' => 'None of them is published until you settle their evidence.',
            'cta' => 'Show them',
        ],

        'filters' => [
            'status' => 'Status',
            'month' => 'Month',
            'speaker' => 'Speaker',
            'all' => 'All',
        ],

        'sort' => [
            'label' => 'Order',
            'smart' => 'What is waiting on you first',
            'newest' => 'Newest',
            'oldest' => 'Oldest',
            'title' => 'By title',
        ],
        'view' => [
            'label' => 'View',
            'grid' => 'Cards',
            'table' => 'Table',
        ],
        'columns' => [
            'title' => 'Title',
            'speaker' => 'Speaker',
            'date' => 'Date',
            'status' => 'Status',
            'outputs' => 'Outputs',
            'action' => 'Action',
        ],
        'open' => 'Open',
        'review' => 'Review now',
        /*
         * "No filter results" is not "no summaries at all": the first is
         * fixed by clearing the filter, the second by creating a summary.
         * One message for both misleads whichever it is not written for.
         */
        'no_matches' => [
            'title' => 'No summary matches the filter',
            'body' => 'Widen your search or clear the filter to see every summary.',
        ],
    ],

    'create' => [
        'title' => 'New summary',
        'subtitle' => 'Give us the source, the lecture title and its speaker; the rest is on us.',
        'submit' => 'Start preparing',
        'submit_hint' => 'One summary counts against this month\'s quota.',

        /*
         * Optional fields are folded away — T-27. Eight fields that are none
         * of them required, spread among the three that are, hide the fact
         * that only three are asked for.
         */
        'details' => [
            'show' => 'Optional gathering details',
            'hide' => 'Hide optional details',
        ],

        /*
         * Appearance — T-95. **Folded by default** — product owner decision,
         * 9 September 2026 — **but it hides nothing that will be applied**:
         * the actual values show as chips while it is folded. Languages moved
         * out of it into "What we produce": language is a content decision,
         * not an appearance one.
         */
        'appearance' => [
            'legend' => 'Appearance',
            'hint' => 'The page template and its colour palette for this summary alone; neither changes your institution\'s default in Settings.',
            'template' => 'Page template',
            'palette' => 'Colour palette',
            'change' => 'Change',
            'done' => 'Done',
            'default' => 'Your institution\'s default',
            'custom' => 'Custom for this summary',
            'reset' => 'Back to the institution default',
            'preview' => 'Preview the appearance',
            'preview_title' => 'Appearance preview',
            'preview_hint' => 'In the real template on sample content, in this summary\'s colours.',
            'close' => 'Close the preview',
        ],

        /*
         * ★★ **The languages text says each language gets its own summary** —
         * product owner request, 9 September 2026. Someone choosing two
         * languages should expect two pages, not one page in two languages.
         */
        'produce' => [
            'legend' => 'What we produce',
            'page' => 'The summary — always produced',
            'page_hint' => 'A published summary with a link to share, and each language gets its own.',
            'languages' => 'Publishing languages',
            'languages_hint' => 'Each language you choose gets its own published summary. Evidence is verified against the Arabic original either way.',
            'languages_min' => 'Choose at least one language.',
            'quran' => ':language — verses in the approved :name translation',
        ],

        'attribution' => [
            'hint' => 'How the lecture is attributed in the page header and the gathering card.',
            'about' => [
                'institution' => 'The institution\'s name and place alongside the speaker.',
                'speaker_only' => 'The speaker alone, without the institution\'s name.',
                'publisher_only' => 'No attribution to the institution — for a lecture carried from an outside source.',
            ],
            'preview' => 'This is how it appears on the page',
            'no_venue' => 'The institution\'s name appears neither in the page header nor in the gathering card.',
            'speaker_placeholder' => 'Speaker name',
        ],

        'groups' => [
            'speaker' => 'Speaker',
            'when' => 'When',
        ],
        'weekday_hint' => 'Filled in from the Gregorian date, and editable if needed.',

        'summary' => [
            'title' => 'Your request',
            'source' => 'Source',
            'lesson' => 'Lecture',
            'outputs' => 'Outputs',
            'languages' => 'Languages',
            'appearance' => 'Appearance',
            'missing' => 'Not set yet',
            'text_source' => 'Pasted transcript',
            'upload_source' => 'Audio or video file',
            'page' => 'The summary',
            'carousel' => 'Instagram slides',
            'left' => 'You have :left of :limit left this month.',
            'todo' => 'Before you start',
            'todo_source' => 'Lecture source',
            'todo_title' => 'Lecture title',
            'todo_speaker' => 'Speaker name',
        ],

        'source' => [
            'legend' => 'Lecture source',
            'tabs' => [
                'url' => 'YouTube link',
                'upload' => 'Audio or video file',
                'text' => 'Text transcript',
            ],
            'url_label' => 'Link to the lecture on YouTube',
            'url_hint' => 'The link is checked before anything is spent, so you see the title and length first.',
            'upload_hint' => 'Up to 500 MB. Audio or video.',
            'soon' => 'Soon',
            'text_label' => 'Paste the transcript',
            'text_hint' => 'Or upload an srt or vtt captions file, or plain text.',
            'text_placeholder' => 'Paste the full lecture text here…',

            /*
             * Duplicates — T-65. **It does not block; it stops and asks**:
             * regenerating from the same video in another language is a fair
             * need, and a silent duplicate is not.
             */
            'duplicate' => 'This link was entered before, in ":title". '
                .'If you want a second summary from it — in another language or another template — confirm that and submit again.',
            'duplicate_confirm' => 'I know it is a duplicate, and I want a second summary from it.',
        ],

        'preflight' => [
            'run' => 'Check the link',
            'running' => 'Checking the link…',
            'title' => 'What we found at the link',
            'video_title' => 'Title',
            'duration' => 'Length',
            'duration_minutes' => ':count minutes',
            'captions' => 'Arabic captions',
            'captions_found' => 'Present — the text will be read from them, with no audio transcription',
            'captions_missing' => 'Not present — the audio will be transcribed, which is slower',
            'too_long' => 'This lecture is :minutes minutes long, and your subscription\'s limit is :limit minutes. Choose a shorter lecture, or raise your limit from the subscription page.',
            'unavailable' => 'We could not read this link\'s details. Check that it is a link to a lecture on YouTube and not a playlist, or upload the audio file.',
            'session_expired' => 'Your session expired because the page was left open too long. Reload the page and try again.',
        ],

        'outputs' => [
            'legend' => 'Requested outputs',
            'page' => 'The page',
            'page_always' => 'Always produced',
            'carousel' => 'Instagram carousel',
            'images' => 'Image pack',
            'locked' => 'On the Institution plan and above.',
            /*
             * **The image pack is disabled for everyone** until its renderer
             * is built (T-20). A checkbox that ticks and produces nothing is
             * a promise not kept — worse than one that says "not open yet".
             */
            'soon' => 'Coming soon.',
        ],

        'meeting' => [
            'legend' => 'Gathering details',
            'title' => 'Lecture title',
            'subtitle' => 'Subtitle',
            'speaker' => 'Speaker name',
            'gregorian' => 'Gregorian date',
            'hijri' => 'Hijri date',
            'hijri_hint' => 'Written as it is displayed, for example "12 Rajab 1447".',
            'weekday' => 'Day',
            'time_note' => 'Time of delivery',
            'venue_mode' => 'Attribution style',
            'venue' => [
                'institution' => 'In the institution\'s name',
                'speaker_only' => 'In the speaker\'s name alone',
                'publisher_only' => 'No attribution',
            ],
        ],
    ],
];
