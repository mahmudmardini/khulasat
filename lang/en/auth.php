<?php

declare(strict_types=1);

/*
 * Sign-in — SCREENS.md §1. Adapted from `lang/ar/auth.php` (T-133).
 *
 * ★ The two deliberately-vague messages below (`failed`, `reset.sent`) keep
 * their vagueness in every language: telling a stranger whether an address
 * is registered turns the form into a reconnaissance tool.
 */

return [

    'tagline' => 'A lecture is delivered, and comes back a typeset page whose evidence is traced and graded, ready to publish under your name.',

    'title' => 'Sign in',
    'subtitle' => 'Enter your institution\'s dashboard.',

    'email' => 'Email address',
    'password' => 'Password',
    'show_password' => 'Show password',
    'hide_password' => 'Hide password',
    'remember' => 'Keep me signed in',
    'submit' => 'Sign in',
    'logout' => 'Sign out',

    'failed' => 'That email or password is not correct.',
    'throttle' => 'Too many attempts. Try again in :seconds seconds.',

    'no_signup' => 'Accounts are created by invitation from your institution. If you cannot sign in, ask whoever set up your account.',

    'reset' => [
        'title' => 'Reset your password',
        'subtitle' => 'Enter your email and we will send you a link to set a new password.',
        'submit' => 'Send the link',
        'back_to_login' => 'Back to sign in',
        'link' => 'Forgotten your password?',

        'sent' => 'If that address is registered with us, the reset link has just been sent to it. Check your spam folder if it does not arrive.',

        'new_title' => 'New password',
        'new_subtitle' => 'Enter your new password twice.',
        'password' => 'New password',
        'confirm' => 'Enter it again',
        'save' => 'Change password',
        'rules' => 'At least eight characters, and not a common password that has been leaked before.',

        'done' => 'Your password has been changed. Sign in with it now.',
        'invalid' => 'This link has expired or has already been used. Request a new one.',

        'mail_subject' => 'Reset your password',
        'mail_greeting' => 'Hello :name,',
        'mail_greeting_plain' => 'Hello,',
        'mail_intro' => 'We received a request to reset the password on your account. Press the button below to set a new one.',
        'mail_action' => 'Change password',
        'mail_expiry' => 'This link works for :minutes minutes, then expires.',
        'mail_ignore' => 'If you did not ask for this, there is nothing to do: your current password still stands, and nobody can change it without this link.',
        'mail_fallback' => 'If the button does not work, copy this link into your browser:',
    ],
];
