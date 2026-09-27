<?php

declare(strict_types=1);

/*
 * The review gate — SCREENS.md screen 5, spec §7-5. Adapted in T-133.
 *
 * These are the most exacting strings in the product: this is where what
 * gets published under an institution's name is settled. The wording says
 * **what we found**, never what we rule — the difference between an honest
 * tool and one that puts words in the sources' mouths.
 */

return [

    'title' => 'Review the evidence',

    'match' => [
        'exact' => 'Matches the source',
        'partial' => 'Close to the source wording',
        'none' => 'We found no source for it',
    ],

    /*
     * "We found no source for it", never "fabricated". Our failure to trace
     * something is not a ruling that it is false — product owner decision,
     * 7 September 2026, T-06b.
     */
    'none_hint' => 'We did not find it in the sources we hold. This is not a ruling on it — only that we cannot trace it, so it will not be published.',

    'grade' => [
        'sahih' => 'Sound (sahih)',
        'hasan' => 'Good (hasan)',
        'daif' => 'Weak (da\'if)',
        'mawdu' => 'Fabricated (mawdu\')',
        'unknown' => 'No stated grading',
    ],

    'labels' => [
        'quoted' => 'Wording as said in the lecture',
        'narrator' => 'Narrator',
        'source' => 'Source wording',
        'takhrij' => 'Reference',
        'ruling' => 'Grading',
        'ayah' => 'Verse',
        'surah' => 'Surah',
        'diff_legend' => 'The highlighted part on each side is what differs from the other',
    ],

    'decision' => [
        'publish_with_source' => 'Publish with the source wording',
        'drop' => 'Remove from the summary',
        'pending' => 'Awaiting your decision',
    ],

    'gate' => [
        'blocked' => 'Nothing can be published until every citation is settled.',
        'remaining' => ':count still unsettled',
    ],

    'counter' => 'Citation :current of :total',
    'context' => 'Context',
    'context_missing' => 'We could not find the passage it appeared in within the transcript.',
    'no_source' => 'There is no source wording to compare against.',

    /*
     * Three actions — SCREENS.md §5. **Three and no more**, ordered by
     * preference: the source wording first, because it is the original.
     */
    'actions' => [
        'source' => 'Use the source wording',
        'as_quoted' => 'Keep it as said',
        'remove' => 'Remove the citation',
        'resume' => 'Continue processing',
    ],

    /*
     * "Keep it as said" **warns, it does not block** — the decision belongs
     * to a person, not the system. The wording states exactly what will
     * happen; it does not ask "are you sure?".
     */
    'confirm' => [
        'as_quoted_title' => 'Keeping the lecture wording',
        'as_quoted' => 'The wording as the speaker said it will be published, not the source wording — and you are the one signing it under your institution\'s name.',
        'remove_title' => 'Removing the citation',
        'remove' => 'This citation is removed from the summary so it does not appear in it, and its record stays with you.',
    ],

    'decided' => [
        'approved' => 'Kept with the source wording',
        'corrected' => 'Kept as said in the lecture',
        'removed' => 'Removed from the summary',
        'auto_passed' => 'Passed automatically',
    ],

    'all_settled' => 'Every citation is settled. Continue, and processing will resume.',
    'empty' => 'No citations in this lecture',
    'empty_body' => 'A lecture with no citation is a perfectly sound lecture — the speaker quoted neither a verse nor a hadith, so there is nothing to review.',
    'keyboard' => '1, 2 and 3 for the actions; the arrow keys move between citations.',
];
