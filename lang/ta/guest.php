<?php

/*
|--------------------------------------------------------------------------
| Guest App — Tamil
|--------------------------------------------------------------------------
|
| Keys mirror lang/en/guest.php exactly. A missing key falls back to English
| rather than showing the key itself, so a half-translated file degrades into
| a readable screen instead of a broken one.
|
*/

return [

    'home' => [
        'title' => 'வரவேற்கிறோம்',
        'empty' => 'இந்த உணவகம் இன்னும் தனது முகப்புத் திரையை அமைக்கவில்லை. பணியாளரிடம் கேட்கவும்.',
    ],

    'menu' => [
        'title' => 'மெனு',
        'empty' => 'இந்த மெனு இன்னும் தயாராகவில்லை. பணியாளரிடம் கேட்கவும்.',
        'featured' => 'சிறப்புப் பரிந்துரை',
        'combos' => 'சேர்க்கைகள்',
        'combo_contains' => 'உங்களுக்குக் கிடைப்பவை',
        'was' => 'முன்பு :price',
        'save' => ':amount சேமியுங்கள்',
        'additions' => 'இத்துடன் சேர்க்க',
        'free' => 'இலவசம்',
        'served_between' => ':from முதல் :until வரை பரிமாறப்படும்',
        'not_being_served' => 'இப்போது பரிமாறப்படவில்லை',
        'tax_included' => 'விலைகளில் :rate GST அடங்கும்.',
        'tax_excluded' => 'விலைகளில் GST அடங்காது; :rate வீதம் வசூலிக்கப்படும்.',
        'service_charge' => 'பில்லில் :rate சேவைக் கட்டணம் சேர்க்கப்படும்.',
        'parcel_charge' => 'பார்சல் ஆர்டர்கள் :amount கட்டணத்தில் பொதியப்படும்.',
    ],

    'document' => [
        'unavailable' => 'இந்த ஆவணத்தைத் திறக்க முடியவில்லை.',
        'open' => 'புதிய தாவலில் திற',
    ],

    'status' => [
        'open' => 'திறந்துள்ளது',
        'closed' => 'மூடியுள்ளது',
    ],

    'actions' => [
        'back' => 'பின்செல்',
        'switch_to_dark' => 'இருண்ட தோற்றத்திற்கு மாற்று',
        'switch_to_light' => 'ஒளிர் தோற்றத்திற்கு மாற்று',
        'switch_language' => 'மொழியை மாற்று',
    ],

];
