<?php

/*
|--------------------------------------------------------------------------
| Staff App
|--------------------------------------------------------------------------
|
| The chrome of the app the floor works from. Sent to the browser as one
| Inertia prop, so keep it to what the staff app actually renders.
|
| `:name` placeholders are filled in the browser by useTranslations(), not
| here, because these strings reach React before they reach a screen.
|
*/

return [

    'home' => [
        'title' => 'Today',
        'empty' => 'Nothing is on the menu yet.',
    ],

    'login' => [
        'title' => 'Sign in',
        'heading' => 'Staff sign in',
        'intro' => 'We will email you a code. There is no password.',
        'code_heading' => 'Enter your code',
        'code_intro' => 'We emailed you a :length-digit code. It expires in :minutes minutes.',
        'email_label' => 'Email address',
        'email_placeholder' => 'you@example.com',
        'send' => 'Email me a code',
        'sending' => 'Sending…',
        'code_label' => ':length-digit code',
        'submit' => 'Sign in',
        'checking' => 'Checking…',
        'different_email' => 'Use a different email',
    ],

    'item' => [
        'sold_out' => 'Sold out',
        'hidden' => 'Hidden',
        'additions' => 'Additions',
        'free' => 'Free',
    ],

    'actions' => [
        'back' => 'Back',
        'sign_out' => 'Sign out',
        'switch_to_dark' => 'Switch to dark',
        'switch_to_light' => 'Switch to light',
        'switch_language' => 'Switch language',
    ],

];
