<?php

declare(strict_types=1);

/*
 * Shared interface strings — SCREENS.md §general rules: "every string comes
 * from lang/. No string written inside a component, not even (Save)".
 *
 * No emoji anywhere, and the tone stays calm — no marketing, no celebration.
 * That is the second principle at the head of SCREENS.md, and it survives
 * translation (T-133).
 */

return [

    'actions' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'close' => 'Close',
        'back' => 'Back',
        'next' => 'Next',
        'retry' => 'Try again',
        'upload' => 'Upload a file',
        'browse' => 'Choose from your device',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'search' => 'Search',
        'filter' => 'Filter',
        'clear_filters' => 'Clear filters',
    ],

    'nav' => [
        'index' => 'Summaries',
        'create' => 'New summary',
        'verify' => 'Verify tool',
        'brand' => 'Institution identity',
        'billing' => 'Subscription',
        'team' => 'Team',
        'expand' => 'Expand the menu',
        'collapse' => 'Collapse the menu',
        'open_menu' => 'Open the menu',
        'close_menu' => 'Close the menu',
        'skip_to_content' => 'Skip to content',

        'section_work' => 'Work',
        'section_settings' => 'Settings',
    ],

    'locale' => [
        'label' => 'Dashboard language',
    ],

    /*
     * Views split by page language — T-140, read in both panels
     * (the tenant screen and the admin panel), so it lives in the shared file.
     */
    'views' => [
        'by_locale' => 'Views by language',
        /*
         * **"Unattributed", not "Arabic"**: rows recorded before the split
         * hold every language at once, and pinning them on one is a guess
         * shown as a number.
         */
        'unattributed' => 'Unattributed',
        'unattributed_hint' => 'Views recorded before languages were counted apart, or from a file not yet re-rendered.',
        'recent_of_total' => ':recent in 30 days · :total since the start',
    ],

    'roles' => [
        'owner' => 'Owner',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ],

    'table' => [
        'empty' => 'No matching results',
        'loading' => 'Loading',
        'sort_asc' => 'Sort ascending',
        'sort_desc' => 'Sort descending',
        'of' => 'of',
    ],

    'state' => [
        'loading' => 'Loading',
        'saving' => 'Saving',
        'required' => 'Required field',
        'optional' => 'Optional',
        'suggested' => 'Suggested',
    ],

    /*
     * A destructive dialog states **exactly what will be lost** — §general
     * rules. "Are you sure?" is not a confirmation, because it does not say
     * what goes.
     */
    'confirm' => [
        'title' => 'Confirm this action',
        'irreversible' => 'This cannot be undone.',
    ],

    'dropzone' => [
        'prompt' => 'Drop the file here, or choose it from your device',
        'drop_now' => 'Drop it now',
        'max_size' => 'Maximum :size',
        'wrong_type' => 'That file type is not accepted. Accepted: :types',
        'too_large' => 'The file is larger than the limit (:size).',
        'remove' => 'Remove the file',
    ],

    'complaint' => [
        'title' => 'Report an error',
        'intro' => 'If you have seen an error in one of our summaries — in a citation\'s reference or its grading — or you are the speaker and object to words being attributed to you, tell us. No account is needed.',
        'kind' => 'Type of objection',
        'url' => 'Page link',
        'contact' => 'How to reach you',
        'contact_hint' => 'An email or a number, so we can tell you what came of it.',
        'detail' => 'Details',
        'submit' => 'Send',
        'sla' => 'We answer this type within :hours hours.',
        'sent' => 'We have your report, and we will reply within :hours hours.',
    ],

    /*
     * The product itself — **not the `brand` below it**: that is the identity
     * of the institution publishing, this is the identity of the tool doing
     * the preparing.
     */
    'product' => [
        'name' => 'Khulasat',
        'rights' => '© :year',
        'slogan' => 'Summaries, traced to their sources',
    ],

    'brand' => [
        'title' => 'Institution identity',
        'subtitle' => 'What you set here appears on every page your institution publishes.',
        'saved' => 'Institution identity saved.',
        'preview' => 'Preview',
        'section_identity' => 'Name',
        'section_palette' => 'Colour palette',
        'section_logo' => 'Logo',
        'section_links' => 'Links and disclaimer',
        'name_ar' => 'Name in Arabic',
        'name_ar_full' => 'Full name',
        'name_ar_full_hint' => 'Appears in the gathering card at the foot of the summary.',
        'name_latin' => 'Name in Latin script',
        'logo_max' => '500 KB',
        'youtube_url' => 'YouTube channel link',
        'social_url' => 'Social page link',
        'disclaimer' => 'Disclaimer text',
        'disclaimer_hint' => 'Appears at the foot of every summary. Leave it empty to use the default text.',

        'preview_live' => 'Follows every change before you save',
        'preview_updating' => 'Updating the preview…',
        'preview_failed' => 'The preview could not be updated.',
        'unsaved' => 'Unsaved changes',
        'logo_current' => 'Current logo',
        'logo_chosen' => 'Chosen logo — not saved yet. It appears on new summaries after you press Save.',

        'logo_optional' => 'Optional',
        'logo_remove' => 'Remove the logo',
        'logo_removing' => 'The logo is removed when you press Save, and will no longer appear on pages or slides.',
        'logo_keep' => 'Keep it',

        'logo_transparent' => 'Make the logo background transparent',
        'logo_transparent_hint' => 'With no plate behind it. Choose this for a logo that is already light and needs no protection against the dark header.',

        'scope_note' => 'These are the defaults for new summaries. Published ones do not change until you press "Update published" in their preview.',
        'nav_label' => 'Identity sections',
        'section_appearance' => 'Appearance',
        'appearance_hint' => 'The page template and its colour palette. Both can be changed for one particular summary from the create screen.',
        'section_locales' => 'Default publishing languages',
        'reset' => 'Undo',
        'leave_confirm' => 'The institution identity has unsaved changes, and they will be lost if you leave. Leave anyway?',
    ],

    'palette' => [
        'legend' => 'Institution colour palette',
        'selected' => 'Selected palette',
    ],

    // قوالبُ كاروسيل الجهة — T-173.
    'carousel_designs' => [
        'generate_action' => 'The system reads your identity and proposes three new templates. Your approved templates stay as they are.',
        'title' => 'Carousel templates',
        'hint' => 'Templates for your slide images in your identity: the system reads your name, palette and logo, proposes three templates, and you approve the ones that fit. The first one you approve becomes the default for every carousel after it.',
        'cost_hint' => 'Generating templates calls the model but does not count against your summary quota. Approving them and creating images with them is free.',
        'generate' => 'Generate templates for my identity',
        'regenerate' => 'Generate new templates',
        'generating' => 'Generating templates… this can take a minute; the page updates on its own.',
        'failed' => 'The templates could not be generated. Try again shortly.',
        'capped' => 'The platform has reached today\'s spending cap; nothing is generated until it is raised.',
        'none_valid' => 'None of the proposed templates were valid. Try again.',
        'cannot_approve' => 'No more than :max templates can be approved. Remove one before approving another.',
        'candidates_title' => 'Proposed — not approved yet',
        'approved_title' => 'Approved',
        'default_badge' => 'Default',
        'approve' => 'Approve',
        'discard' => 'Dismiss',
        'make_default' => 'Make default',
        'remove' => 'Remove',
        'empty' => 'No templates yet. Slides use the original template until you approve one.',
        'unnamed' => 'Template',
        'preview_title' => 'Preview of template “:name”',
        'prompt_title' => 'Template generation instructions for this tenant',
        'prompt_hint' => 'Replaces the default instructions for this tenant only. Leave empty to return to the default.',
        'prompt_default' => 'Default instructions',
        'prompt_copy_default' => 'Start from the default',
        'prompt_saved' => 'Instructions saved.',
        'prompt_custom' => 'Custom instructions',
        'prompt_using_default' => 'Using the default',
        'choose' => 'Template',
        'default_original' => 'Original template',
    ],

    // إقرارُ الكلفة — T-203.
    'cost' => [
        'title' => 'Cost',
        'regeneration' => 'One of this summary\'s regenerations is used: :left of :limit left after it.',
        'regeneration_none' => 'This summary\'s regenerations are used up (:limit of :limit).',
        'monthly' => 'One summary from your monthly quota is used: :left of :limit left after it.',
        'monthly_none' => 'You have reached your monthly quota (:limit of :limit).',
        'free' => 'Not counted against your quota or regenerations.',
        'model' => 'Calls an AI model.',
    ],
];
