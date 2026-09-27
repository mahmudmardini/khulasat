<?php

declare(strict_types=1);

/*
 * Team — SCREENS.md §10, task T-33. Adapted in T-133.
 *
 * **The tone stays calm — no marketing, no celebration** — and the wording
 * names the effect rather than the action: "remove access" is clearer than
 * "delete member".
 */

return [

    'title' => 'Team',
    'subtitle' => 'Who can sign in to :tenant, and with what permissions.',

    'members' => 'Members',
    'you' => 'You',

    'roles' => [
        'owner' => 'Owner',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ],

    'role_hints' => [
        'owner' => 'Everything inside the institution: summaries, publishing, identity, subscription and team.',
        'editor' => 'Creates summaries, reviews their evidence and publishes them. Does not touch identity, subscription or team.',
        'viewer' => 'Can look, but not create or publish.',
    ],

    'change_role' => 'Permissions',
    'role_changed' => 'Permissions changed.',

    'remove' => 'Remove access',
    'member_removed' => 'The member\'s access has been removed.',
    'remove_confirm' => ':name loses access to your dashboard from now on and cannot sign in again. The summaries they created stay exactly as they are — they belong to the institution, not to them.',

    'invite' => 'Invite a member',
    'invite_email' => 'Invitee\'s email',
    'invite_role' => 'Permissions',
    'invite_submit' => 'Create the invitation link',

    'pending' => 'Invitations not yet accepted',
    'pending_empty' => 'No pending invitations.',
    'expires' => 'Expires on :date',
    'expired' => 'Expired',
    'invite_revoke' => 'Revoke the invitation',
    'invite_revoked' => 'The invitation has been revoked.',
    'invite_revoke_confirm' => 'The invitation link sent to :email stops working and opens nothing after this. You can invite them again whenever you like.',

    /*
     * ★ **The link is copied and sent by hand.**
     *
     * Email does not always arrive — spam filters, a mistyped address, a
     * sender that was never configured. **These institutions use messaging
     * apps more than email.** And it is said plainly that the link is shown
     * once, so nobody closes the screen before copying it.
     */
    'link_ready' => 'The invitation link is ready',
    'link_once' => 'Copy it now and send it to them. It will not be shown again — we keep only its hash, not the link itself. If you close this before copying, just issue the invitation again.',
    'link_copy' => 'Copy the link',
    'link_copied' => 'Copied',
    'link_next' => 'They open the link, set their name and password, and enter your dashboard with the permissions you chose. The link works for a week, then expires.',

    'invite_cancel' => 'Cancel',
    'awaiting' => 'Awaiting acceptance',
    'role_change' => 'Change permissions',
    'role_change_confirm' => ':name\'s permissions change from ":from" to ":to" from now on. :hint',

    'roles_table' => 'What each role can do',
    'ability' => 'Permission',
    'abilities' => [
        'view' => 'View summaries',
        'create' => 'Create summaries',
        'review' => 'Review evidence',
        'publish' => 'Publish summaries',
        'brand' => 'Manage the institution\'s identity',
        'billing' => 'Manage the subscription',
        'team' => 'Manage the team',
    ],
    'yes' => 'Yes',
    'no' => 'No',

    'accept' => [
        'title' => 'Join the team',
        'subtitle' => 'You have been invited to the :tenant dashboard as :role.',
        'name' => 'Your name',
        'password' => 'Password',
        'confirm' => 'Enter it again',
        'rules' => 'At least eight characters, and not a common password that has been leaked before.',
        'submit' => 'Enter the dashboard',

        'invalid' => 'This link has expired or has already been used',
        'invalid_body' => 'Invitation links work for a week, then expire. Ask whoever invited you to create a new one.',
    ],

    'welcome' => 'Welcome. This is your institution\'s dashboard.',

    'errors' => [
        'already_registered' => 'This email already has an account with us. Someone who belongs to one institution cannot be invited to another.',
        'invalid_invitation' => 'This link has expired or has already been used. Ask whoever invited you for a new one.',
        /*
         * **An institution is never left without an owner.** Removing the
         * last owner — or demoting them — locks it against itself: no team,
         * no identity, no subscription, and no way back in except through us.
         */
        'last_owner' => 'This is the institution\'s last owner. Promote another member to Owner first, then try again.',
    ],
];
