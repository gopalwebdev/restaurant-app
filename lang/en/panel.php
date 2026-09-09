<?php

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
|
| The parts of the restaurant admin panel that follow the language chosen in
| its top bar. Deliberately only the menu and storefront surfaces: those are
| what a restaurant works in daily, and what a Tamil-speaking manager needs to
| read. Roles, permissions and accounts stay in English — they are the product
| team's vocabulary and code refers to them by name.
|
| What a restaurant typed itself — menu, section, dish, addition and tile names
| — is not here. That lives in translated database columns; see
| .ai/rules/models.md.
|
*/

return [

    'language' => [
        'label' => 'Language',
    ],

    'navigation' => [
        'menu' => 'Menu',
        'storefront' => 'Storefront',
    ],

    'shared' => [
        'both_languages' => 'Both languages are edited together. English is required; a guest reading in Tamil sees the English text wherever the Tamil is blank.',
        'order' => 'Order',
        'order_help' => 'Lower numbers come first. Ties fall back to the name.',
        'showing' => 'Showing',
        'not_translated' => 'Not translated',
        'name' => 'Name',
        'description' => 'Description',
    ],

    'menus' => [
        'label' => 'menu',
        'plural' => 'menus',
        'create' => 'New menu',
        'name_section' => 'Name',
        'description_section' => 'Description',
        'description_help' => 'Optional. A line about what this menu is — "Served 12pm to 3pm", say.',
        'storefront_section' => 'On the storefront',
        'is_active' => 'Showing to guests',
        'is_active_help' => 'Turn this off to take the whole menu down — its sections and dishes with it — without deleting anything. Home screen tiles pointing at it stop being shown too.',
        'sections_count' => 'Sections',
        'showing_tooltip' => 'A hidden menu takes its sections and their dishes off the storefront too.',
        'unique' => 'This restaurant already has a menu with that name.',
        'delete_warning' => 'Every section on this menu is deleted with it, and every dish in those sections. Hide it instead to take it off the storefront and keep everything.',
    ],

    'categories' => [
        'label' => 'section',
        'plural' => 'sections',
        'create' => 'New section',
        'section' => 'Section',
        'menu' => 'Menu',
        'menu_help' => 'Which menu this section appears on. Hiding a menu hides everything under it.',
        'on_the_menu' => 'On the menu',
        'position' => 'Order on the menu',
        'is_active' => 'Showing on the menu',
        'is_active_help' => 'Turn this off to hide the whole section, and everything in it, without deleting anything.',
        'items_count' => 'Items',
        'showing_tooltip' => 'A hidden section takes everything in it off the menu too.',
        'unique' => 'This menu already has a section with that name.',
        'delete_warning' => 'Everything on this section of the menu is deleted with it. Hide it instead to take it off the menu and keep the items.',
    ],

    'items' => [
        'label' => 'dish',
        'plural' => 'dishes',
        'create' => 'New dish',
        'dish' => 'Dish',
        'section' => 'Section',
        'section_help' => 'Hiding a section, or the menu it is on, hides everything in it — this dish included.',
        'needs_a_section' => 'Add a menu and a section to it first.',
        'food_type' => 'Food type',
        'food_type_help' => 'Shown to guests as the veg or non-veg mark. Never translated — it is a regulatory mark, and its colours mean a fixed thing.',
        'price_section' => 'Price and availability',
        'price' => 'Price',
        'price_help' => 'What a guest pays, in whole currency. Stored exactly, never as a float.',
        'is_available' => 'Available now',
        'is_available_help' => 'Turn off when it sells out, without taking it off the menu.',
        'position' => 'Order in the section',
        'type' => 'Type',
        'unique' => 'This section already has a dish with that name.',
        'delete_warning' => 'The additions on this dish are deleted with it.',
        'is_featured' => 'Featured',
        'is_featured_help' => 'Featured dishes are shown in a row of their own at the top of the menu, above the sections.',
        'featured_heading' => 'Featured dishes',
        'featured_description' => 'Shown in a row of their own at the top of this menu. Drag them into the order a guest reads them.',
        'featured_add' => 'Feature a dish',
        'featured_pick' => 'Dish',
        'featured_pick_help' => 'Only dishes on this menu that are not featured yet.',
        'featured_remove' => 'Remove from featured',
        'featured_remove_warning' => 'The dish stays on the menu under its own section. It just stops being led with.',
        'featured_empty_heading' => 'Nothing featured yet',
        'featured_empty_description' => 'A menu reads fine without this. Feature a dish when you want guests to see it before they scroll.',
    ],

    'additions' => [
        'section' => 'Additions',
        'section_help' => 'Extras this dish can be ordered with — extra cheese, a large portion, no onions. Leave empty if it has none.',
        'label' => 'Addition',
        'add' => 'Add an addition',
        'price' => 'Extra charge',
        'price_help' => 'Zero is fine — "no onions" costs nothing and is still worth listing.',
        'is_available' => 'Available now',
        'count' => 'Additions',
    ],

    'rows' => [
        'label' => 'home screen row',
        'plural' => 'home screen',
        'heading' => 'Home screen',
        'subheading' => 'What a guest sees after scanning the QR code at their table. Each row is a band of the screen; drag them into the order you want them read.',
        'create' => 'New row',
        'title_section' => 'Heading',
        'title_section_help' => 'Optional. A banner into your menu speaks for itself; a rail of photographs usually wants something over it.',
        'title_field' => 'Heading',
        'layout' => 'Layout',
        'layout_help' => 'How this row draws the tiles inside it.',
        'layout_column' => 'Layout',
        'untitled' => 'No heading',
        'tiles_count' => 'Tiles',
        'placement' => 'In this row',
        'link' => 'Link to open',
        'link_help' => 'The full address, starting with https:// — your Instagram, a WhatsApp link, your own site. It opens in a new tab.',
        'a_link' => 'A link',
        'row' => 'Row',
        'position_help' => 'Lower numbers come first. Drag the rows on the list to set this instead.',
        'is_active' => 'Showing to guests',
        'empty_heading' => 'No rows yet',
        'empty_description' => 'Guests scanning a table\'s QR code see nothing until there is at least one row here. Most restaurants start with a banner row that opens their menu.',
        'delete_warning' => 'Every tile in this row is deleted with it. Anything those tiles pointed at — a menu, say — is left alone.',
        'manage_tiles' => 'Tiles',
        'manage_tiles_help' => 'The tiles in this row, in the order a guest reads them.',
    ],

    'tiles' => [
        'label' => 'home screen tile',
        'plural' => 'home screen',
        'heading' => 'Home screen',
        'subheading' => 'What a guest sees after scanning the QR code at their table, in the order shown here.',
        'create' => 'New tile',
        'label_section' => 'Label',
        'label_section_help' => 'Read out by screen readers, shown on the tile when there is no picture yet, and used as the heading of the page a PDF tile opens.',
        'label_field' => 'Label',
        'picture' => 'Picture',
        'picture_help' => 'The row\'s layout decides the shape it is cropped to. A tile without a picture still works — the guest app draws the label on your brand colour instead.',
        'picture_field_help' => 'JPEG, PNG or WebP, up to 4 MB. Around 1200 by 675 pixels looks right.',
        'picture_column' => 'Picture',
        'no_picture' => 'No picture',
        'destination' => 'Where it goes',
        'destination_help' => 'What happens when a guest taps this tile.',
        'on_tap' => 'On tap',
        'goes_to' => 'Goes to',
        'menu_to_open' => 'Menu to open',
        'menu_to_open_help' => 'Only this restaurant\'s menus. A tile pointing at a hidden menu stops being shown.',
        'document' => 'PDF to show',
        'document_help' => 'Up to 10 MB. Shown inside the app, with a back arrow out of it.',
        'an_uploaded_pdf' => 'An uploaded PDF',
        'no_menu_chosen' => 'No menu chosen',
        'placement' => 'In this row',
        'link' => 'Link to open',
        'link_help' => 'The full address, starting with https:// — your Instagram, a WhatsApp link, your own site. It opens in a new tab.',
        'a_link' => 'A link',
        'row' => 'Row',
        'position_help' => 'Lower numbers come first. Drag the rows on the list to set this instead.',
        'is_active' => 'Showing to guests',
        'empty_heading' => 'No tiles yet',
        'empty_description' => 'Guests scanning a table\'s QR code see nothing until there is at least one tile here. Most restaurants start with one that opens their menu.',
        'delete_warning' => 'The tile is removed from the home screen. Anything it pointed at — a menu, say — is left alone.',
    ],

];
