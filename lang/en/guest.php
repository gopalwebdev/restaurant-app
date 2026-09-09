<?php

/*
|--------------------------------------------------------------------------
| Guest App
|--------------------------------------------------------------------------
|
| Every word of chrome in the app a diner reads at the table. The dish names,
| sections and tile labels are not here — those are the restaurant's own words
| and live in translated database columns.
|
| The whole file is sent to the browser as one Inertia prop, so keep it to what
| the guest app actually renders.
|
*/

return [

    'home' => [
        'title' => 'Welcome',
        'empty' => 'This restaurant has not set up its home screen yet. Please ask a member of staff.',
    ],

    'menu' => [
        'title' => 'Menu',
        'empty' => 'This menu is not ready yet. Please ask a member of staff.',
        'additions' => 'Add to this',
        'free' => 'Free',
    ],

    'document' => [
        'unavailable' => 'This document could not be opened.',
        'open' => 'Open in a new tab',
    ],

    'status' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],

    'actions' => [
        'back' => 'Back',
        'switch_to_dark' => 'Switch to dark',
        'switch_to_light' => 'Switch to light',
        'switch_language' => 'Switch language',
    ],

];
