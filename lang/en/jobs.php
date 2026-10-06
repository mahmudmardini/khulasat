<?php

declare(strict_types=1);

/*
 * Generation job states and their strings — SCREENS.md §states and colours,
 * spec §5. Adapted in T-133.
 *
 * **No "token", no model name, no error code** — the governing principle at
 * the head of SCREENS.md. Stages are therefore named in terms a content
 * manager understands, not in terms of the code.
 */

return [

    'status' => [
        'queued' => 'In preparation',
        'transcribing' => 'In preparation',
        'cleaning' => 'In preparation',
        'structuring' => 'In preparation',
        'extracting' => 'In preparation',
        'verifying' => 'In preparation',
        'writing' => 'In preparation',
        'rendering' => 'In preparation',
        'needs_review' => 'Awaiting your review',
        'published' => 'Published',
        'failed' => 'Stopped',
        'unpublished' => 'Not published',
    ],

    /*
     * "Every long operation has a definite state — no vague spinner and no
     * invented percentage" — §general rules.
     */
    'steps_label' => 'Summary preparation stages',

    'steps' => [
        'transcribing' => 'Extracting the lecture text',
        'cleaning' => 'Cleaning up the text',
        'structuring' => 'Extracting the structure',
        'extracting' => 'Extracting the evidence',
        'verifying' => 'Verifying the evidence',
        // Review is a stage in the display even though it is no model call —
        // dropping it would hide that the wait is with the user, not with us.
        'review' => 'Your review of the evidence',
        'writing' => 'Writing the body',
        'quiz' => 'Building the quiz',
        'rendering' => 'Producing the page',
    ],

    'step_state' => [
        'done' => 'Complete',
        'active' => 'Running now',
        // **Waiting on you** — SCREENS §4. Not the same as "running": that
        // moves by itself, this does not move until the user acts.
        'awaiting' => 'Waiting on you',
        'pending' => 'Not started',
        'failed' => 'Stopped',
    ],

    'outputs' => [
        'page' => 'The page',
        'carousel' => 'The carousel',
        'images' => 'Image pack',
        'not_produced' => 'Not produced',
    ],

    'follow' => [
        'retry_action' => 'The summary is prepared again from its source after the failure.',
        'title' => 'Following preparation',
        'live' => 'This page updates itself.',
        'review_cta' => 'Open the review gate',
        'view_cta' => 'Open the summary',
        'change_source' => 'Change the source',
        'failed_title' => 'Preparation stopped',
        'failed_fallback' => 'Preparation stopped before it finished. Try again, and if it keeps happening, change the lecture source.',
        'cancelled' => 'Preparation of this summary was cancelled.',
        'needs_review_note' => 'Preparation stopped at evidence that needs your decision. Nothing is published until you settle it.',
        'published_note' => 'Preparation finished and the summary is published.',
        'pending_count' => ':count citations still unsettled.',

        'pending_one' => 'One citation still unsettled.',
        'pending_two' => 'Two citations still unsettled.',
        'pending_few' => ':count citations still unsettled.',
        'pending_many' => ':count citations still unsettled.',
        'pending_other' => ':count citations still unsettled.',

        'running_note' => 'Preparation is running.',
        'running_body' => 'You can leave this page; the work finishes by itself.',

        'cancel' => 'Cancel preparation',
        'cancel_hint' => 'Preparation stops now and will not resume. What has been spent from your quota on this summary is not returned.',
        'cancel_failed' => 'Preparation finished before the cancellation arrived, so there is nothing to cancel.',

        /*
         * The takedown notice — T-28.
         *
         * **It never says "fault".** The page was removed by a decision, and
         * the institution deserves to know that and why — rather than seeing
         * a link that used to work stop working and assume the fault is ours.
         */
        'takedown' => 'This page has been taken down',
        'takedown_body' => 'Its link now returns a "this summary has been removed" page, not an error. The body and evidence are kept with you as they were.',
        'takedown_reason' => 'Reason',
        'takedown_at' => 'Date removed',

        'started' => 'Started',
        'finished' => 'Finished',
        'elapsed' => 'Took',

        'minutes_one' => 'One minute',
        'minutes_two' => 'Two minutes',
        'minutes_few' => ':count minutes',
        'minutes_many' => ':count minutes',
        'minutes_other' => ':count minutes',
        'hours_one' => 'One hour',
        'hours_two' => 'Two hours',
        'hours_few' => ':count hours',
        'hours_many' => ':count hours',
        'hours_other' => ':count hours',
    ],

    /*
     * The live follow screen — T-83.
     *
     * ★ **No percentage anywhere in it** — SCREENS.md §4. The three broad
     * phases group the eight real stages and invent nothing beyond them.
     */
    'live' => [
        'phases' => [
            'listen' => 'Listening',
            'understand' => 'Understanding and verifying',
            'write' => 'Writing and producing',
        ],
        'elapsed' => ':time elapsed',

        'wizard' => [
            'position' => 'Step :current of :total · :name',
            'queued' => 'In the queue; it starts in a moment.',
            'awaiting' => 'Preparation has stopped for your review of the evidence.',
            'all_done' => 'All eight stages are complete.',
            'stopped_at' => 'Preparation stopped at ":name".',
            'running_since' => 'for :time',
            'just_started' => 'Just started',
            'took' => 'Took :time',
            'instant' => 'Under a second',
            // A review where every citation matched its source automatically,
            // so you were never needed — it has no duration.
            'skipped' => 'Did not need your decision',
            'decided' => 'Decided by you',
            'seconds_one' => 'One second',
            'seconds_two' => 'Two seconds',
            'seconds_few' => ':count seconds',
            'seconds_many' => ':count seconds',
            'seconds_other' => ':count seconds',

            'about' => [
                'transcribing' => 'We extract the lecture text from its source.',
                'cleaning' => 'We clean the text and fix transcription errors.',
                'structuring' => 'We build the lecture\'s sections and its governing idea.',
                'extracting' => 'We pick out the verses and hadith the speaker cited.',
                'verifying' => 'We match each citation against its approved source — a textual match, with no guessing.',
                'review' => 'Whatever did not match its source stops with you to settle.',
                'writing' => 'We write the body of the summary in readable prose.',
                'quiz' => 'We build the comprehension quiz you asked for from the summary and its evidence.',
                'rendering' => 'We produce the page in your identity and publishing languages.',
            ],

            'tally' => [
                'running' => 'So far',
                'done' => 'Preparation tally',
                'time' => 'Time',
                'words' => 'Lecture text',
                'evidence' => 'Evidence',
                'locales' => 'Output languages',
                'source' => 'Lecture length',
                'source_length' => ':hours and :minutes',
                'not_started' => 'Not started yet',
                'words_pending' => 'Known once the text is extracted',
                'evidence_pending' => 'Known once the evidence is extracted',
                'span' => 'from :from to :to',
                'span_without_review' => 'from :from to :to, excluding your review',
                'now' => 'now',
            ],
        ],

        'words_one' => 'One word',
        'words_two' => 'Two words',
        'words_few' => ':count words',
        'words_many' => ':count words',
        'words_other' => ':count words',

        'nuggets_title' => 'Glimpses of the lecture',
        'nuggets_hint' => 'From the lecture\'s structure as extracted, before the body is written.',
        'nuggets_waiting' => 'Glimpses of the lecture appear here once its structure has been extracted.',
        'nugget_kinds' => [
            'concept' => 'Governing idea',
            'diagnosis' => 'Diagnosis',
            'axis' => 'Section',
        ],
        'nugget_position' => ':current of :total',
        'previous' => 'Previous glimpse',
        'next' => 'Next glimpse',
        'pause' => 'Stop cycling',
        'resume' => 'Resume cycling',

        'evidence_none' => 'No evidence was extracted from this lecture',
        'evidence_one' => 'One citation found',
        'evidence_two' => 'Two citations found',
        'evidence_few' => ':count citations found',
        'evidence_many' => ':count citations found',
        'evidence_other' => ':count citations found',
        'locales' => 'Output languages: :list',

        /*
         * Word of completion. **It never happens unasked**: the button is the
         * gesture the browser requires for sound, and the visible reason for
         * requesting notification permission.
         */
        'notify' => [
            'enable' => 'Tell me when it is done',
            'enabled' => 'We will tell you when it is done',
            'blocked' => 'Browser notifications are blocked, so we will alert you with a tone and a marker in the tab title.',
            'published' => '":title" is ready',
            'published_body' => 'The summary is ready to preview.',
            'marker_published' => 'Done',
            'review' => '":title" is waiting for your review',
            'review_body' => 'Preparation stopped at evidence that needs your decision.',
            'marker_review' => 'Waiting on you',
            'failed' => 'Preparation of ":title" stopped',
            'failed_body' => 'Open the page to see the reason and what to do.',
            'marker_failed' => 'Stopped',
        ],
    ],

    /*
     * The carousel — SCREENS.md §6, task T-19.
     *
     * The difference that must be obvious is the difference in cost: drawing
     * is free, and only new text is charged for.
     */
    'carousel' => [
        'build_action' => 'The slide texts are written from the summary, then drawn in your identity.',
        'recondense_action' => 'The current slide texts are replaced with a new wording.',
        'title' => 'Instagram slides',
        'subtitle' => 'Six to ten slides from the same summary, in your institution\'s identity.',

        'build' => 'Build the slides',
        'build_hint' => 'The lecture structure is condensed into slides, and verses and hadith are carried over in full in their source wording.',

        'rebuild' => 'Redraw',
        'rebuild_hint' => 'Redrawn in the current identity from the same text, at no cost and without counting against your quota.',

        'recondense' => 'Ask for new text',
        'recondense_hint' => 'The text is condensed again from scratch, producing slides in different wording. This is the only part that costs anything.',
        'recondense_confirm' => 'The current slide text is replaced with new wording. Go ahead?',

        'preview' => 'Preview',
        'texts' => 'Slide text',
        'copy' => 'Copy the slide text',
        'copy_all' => 'Copy all',
        'copied' => 'Copied',
        'open' => 'Open in a tab',
        'public_url' => 'Published link',
        'count' => ':count slides',

        'anchored' => 'In the source wording',
        'anchored_hint' => 'This slide\'s body is carried over in full from the verified citation and is never trimmed.',

        'empty' => 'The slides have not been built yet',
        'empty_body' => 'Slides are built from the finished summary and re-run nothing of it.',

        /*
         * A lower plan sees the feature disabled with an upgrade line —
         * SCREENS.md §3-b. "Seeing what you do not have is a reason to
         * upgrade; hiding it stops anyone knowing about it."
         */
        'locked' => 'Slides are on the Institution plan and above',
        'locked_body' => 'The content is ready, and slides are drawn from it at no extra cost. Get in touch to raise your plan and they will be opened for you.',
        'locked_cta' => 'See your subscription',

        'blocked' => 'Slides are built once the evidence is settled',
        'blocked_body' => 'No output is drawn while a citation is still unsettled.',

        'rejected' => 'The slides were not accepted: :reason Different wording is being requested.',
        'failed' => 'The slides could not be built right now. Try again shortly.',
    ],

    /*
     * Preview and publishing — SCREENS.md §6, task T-30.
     *
     * ★ **The difference that must be obvious is the difference in cost**:
     * adding a new output does not count against the quota, because it is
     * drawing from content that already exists. Only regeneration counts, and
     * it shows what remains before it runs.
     */
    'preview' => [
        'regenerate_action' => 'The summary is created again from the start. The current one stays as it is until the new one is ready.',
        'title' => 'Summary preview',
        'subtitle' => 'What you see here is what gets published, letter for letter.',

        'tabs' => [
            'page' => 'The page',
            'carousel' => 'The slides',
            'images' => 'Image pack',
            'quiz' => 'The quiz',
        ],

        'device' => [
            'legend' => 'Preview size',
            'desktop' => 'Desktop',
            'tablet' => 'Tablet',
            'mobile' => 'Phone',
        ],

        'locale' => [
            'legend' => 'Preview language',
            'failed' => 'Translation failed',
            'failed_note' => 'This language could not be translated, so the page is shown in Arabic. Try again from “Add a language” above.',
        ],

        'quick' => [
            'legend' => 'Quick actions',
            'copy_link' => 'Copy the link',
            'copied' => 'Link copied',
            'share' => 'Share',
            'pdf' => 'Export PDF',
            'pdf_hint' => 'The print dialog opens with the page as published: choose "Save as PDF".',
            'html' => 'Download HTML',
            'open' => 'Open in a tab',
            'needs_publish' => 'Publish first, so this language has a link to copy and share.',
        ],

        'publish' => 'Publish',
        'republish' => 'Update published',
        'publish_hint' => 'The file is uploaded to its public link. It does not count against your quota.',
        'live' => 'Published now',
        'not_live' => 'Not published yet',
        'open_public' => 'Open the public link',
        'manage' => 'Manage publishing',

        'download' => 'Download',
        'download_page' => 'Download the page',
        'download_carousel' => 'Download the slide text',
        'download_hint' => 'A self-contained file: it opens from your device without a network, and you can upload it to your own site if you like.',

        'add_output' => 'Add an output',
        'add_carousel' => 'Build the slides',
        'free_hint' => 'Drawn from the same content: no cost, and it does not count against your quota.',

        'regenerate' => 'Regenerate',
        'regenerate_hint' => 'The summary is rebuilt from its source, and this is the only thing that counts against your quota.',
        'regenerate_left' => 'You have :count regenerations left for this summary.',
        'regenerate_none' => 'You have used up the regenerations allowed for this summary.',
        'regenerate_confirm' => 'The summary is rebuilt from scratch, and one regeneration counts against your quota. The current summary stays as it is until the new one is finished.',

        'blocked' => 'The preview appears once the evidence is settled',
        'blocked_body' => 'No output is drawn while a citation is still unsettled.',

        'not_ready' => 'The body is not finished yet',
        'not_ready_body' => 'The preview is drawn from the written body, and it has not been written yet.',

        'more_title' => 'Download, publish, regenerate',
        'open_carousel_page' => 'Slides page',
        'images_soon' => 'The image pack has not been built yet',
        'images_soon_body' => 'Once it is, it will be drawn from the same slides, at no cost and with no regeneration.',

        'carousel_empty' => 'No slides have been built for this summary',
        'carousel_pending' => 'The slides are being built',
        'carousel_pending_body' => 'They appear here when they are ready, in a minute or two, without reloading the page.',
        'carousel_pending_short' => 'Building',
        'slides' => 'Instagram slides',
        'slide_texts' => 'Slide text',
    ],

    'published' => [
        'title' => 'The published summary',
        'link' => 'Public link',
        'link_missing' => 'No link yet — it is created on first publish.',
        'copy_link' => 'Copy the link',
        'outputs' => 'Outputs',
        'output_missing' => 'Not produced',
        'rendered_at' => 'Last drawn',
        'published_at' => 'Published on',
        'unpublished_at' => 'Removed on',

        /*
         * The visit counter — SCREENS.md §7, wired in T-31.
         *
         * **The name says exactly what is counted.** What is counted is page
         * opens, not visitors: no cookie and no fingerprint, so there is no
         * way to tell one reader from another — **and we do not want one**.
         */
        'visits' => 'Visits',
        'visits_none' => 'Not opened yet',
        'visits_recent' => ':count in the last thirty days',
        'visits_hint' => 'These are page opens, not a count of readers: no cookie, no browser fingerprint, and nothing is kept about whoever opened it. Your own previews are not counted.',

        'publish' => 'Publish',
        'republish' => 'Publish again',
        'refresh' => 'Update published',
        'refresh_hint' => 'The page is redrawn with the current identity and content, overwriting the same file. The link does not change.',
        'published_now' => 'The summary has been published.',
        'publish_failed' => 'Publishing failed just now. Try again, and write to us if it keeps happening.',
        'not_publishable' => 'This summary cannot be published as it stands: either its body is unfinished, or it contains an unsettled citation.',

        'unpublish' => 'Unpublish',
        'unpublish_hint' => 'The page is removed from the web, and the body and evidence stay with you so it can be republished with one press.',
        'unpublish_confirm' => 'The page is removed from the web now, and its link returns a "this summary has been removed" page to anyone who opens it. The body and evidence stay with you, so you can republish whenever you like at no cost.',
        'unpublished_now' => 'The summary has been removed from publication.',
        'unpublished_note' => 'This summary is not published right now',
        'unpublished_body' => 'Its link returns a "this summary has been removed" page to anyone who opens it, not an error page. The body and evidence are with you as they were, so republish whenever you like at no cost.',

        'delete' => 'Delete permanently',
        'delete_hint' => 'The body, evidence and outputs are erased from your account. This cannot be undone.',
        'delete_confirm' => 'This summary\'s body, evidence and outputs are erased beyond recovery. Its link keeps returning a "this summary has been removed" page to anyone who opens it — not an error page — because whoever shared the link deserves to know it existed and was taken down. The quota spent creating it is not returned.',
        'deleted_now' => 'The summary has been permanently deleted.',

        'not_published_yet' => 'This summary has not been published yet',
        'not_published_body' => 'Preview it first, then publish it from here once you are happy with it.',
        'preview_cta' => 'Open the preview',
    ],

    'empty' => [
        'title' => 'You have not created a summary yet',
        'body' => 'Give us the lecture link, and we will hand back a typeset page whose evidence is traced and graded, ready to publish and share.',
    ],
    /*
     * "Add a language" to an existing summary — T-166. One translation call
     * from what is stored: not a new summary, and not counted against quota.
     */
    'add_locale' => [
        'confirm' => 'Translate',
        'confirm_action' => 'The summary is translated into :locale from what is saved, without rerunning the stages.',
        'legend' => 'Add a language',
        'hint' => 'Translated from this same summary without re-running the creation stages, and not counted against your quota.',
        'published_hint' => 'The summary is published, so the new language is published on its own once its translation is done.',
        'translating' => 'Translating :locale…',
        'translating_short' => 'Translating',
        'translating_note' => 'This language is being translated now and will appear here within a minute or two. You can leave this screen.',
        'failed' => 'Could not translate :locale',
        'retry' => 'Try again',
        'queued' => 'Translation into :locale has started and will appear within a minute or two.',
        'refused' => [
            'not_ready' => 'A language can only be added once the summary is complete and all its evidence is settled.',
            'translating' => 'This language is being translated right now.',
            'unavailable' => 'This language is already part of the summary, or cannot be added to it.',
        ],
    ],

    // حزمةُ صور الكاروسيل — T-173.
    'images' => [
        'create' => 'Create images',
        'recreate' => 'Recreate images',
        'download' => 'Download the pack',
        'empty' => 'No slide images yet',
        'empty_body' => 'Each slide is captured as an Instagram-sized image (1080×1350) and packed with the post text. Free, and not counted against your quota.',
        'rendering' => 'Creating images…',
        'rendering_body' => 'Slides are captured in batches, which takes a few seconds. The previous images stay until the new ones replace them, and the page updates on its own.',
        'free_hint' => 'Creating and recreating the images, with any template, is free and does not count against your quota.',
        'stale' => 'The slides changed after these images were made (a new wording, another template, or a changed summary link on the last slide). Recreate them to match; it costs nothing.',
        'design_scope' => 'The template applies to this summary\'s slides only, and their images are drawn with it.',
        'ready_hint' => '1080×1350 images numbered in order, with the post text in caption.txt.',
        'progress' => ':done of :total captured',
        'stalled' => 'Image creation stopped before it finished. Try again.',
        'failed_title' => 'The images could not be created',
        'needs_carousel' => 'Build the slides first',
        'needs_carousel_body' => 'Images are captured from the slides, so the slides come first.',
        'disabled' => 'Image capture is not enabled on this server.',
        'no_carousel' => 'Build the slides first; the images are captured from them.',
        'pending' => 'Images are not created while :count evidence items are unresolved.',
        'overflow' => 'The text of slide :slides runs past its edges with this template and would be cut off in the image. Try another template, or rebuild the slides.',
        'capture_failed' => 'Slide :slide could not be captured. Try again shortly.',
        'failed' => 'The images could not be created. Try again shortly.',
        'slide_alt' => 'Slide :slide',
    ],
];
