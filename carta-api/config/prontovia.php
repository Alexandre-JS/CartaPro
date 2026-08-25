<?php

return [
    /* URLs ficam centralizadas e só são mostradas quando configuradas. */
    'android_url' => env('PRONTOVIA_ANDROID_URL'),
    'web_app_url' => env('PRONTOVIA_WEB_APP_URL'),
    'school_url' => env('PRONTOVIA_SCHOOL_URL'),
    'support_email' => env('PRONTOVIA_SUPPORT_EMAIL'),
    'contact_phone' => env('PRONTOVIA_CONTACT_PHONE'),
    'contact_phone_url' => env('PRONTOVIA_CONTACT_PHONE_URL'),
    'business_hours' => env('PRONTOVIA_BUSINESS_HOURS'),
    'social_image' => env('PRONTOVIA_SOCIAL_IMAGE'),
    'images' => [
        // Caminhos relativos a public/, por exemplo: images/prontovia/home-hero.webp.
        'home_hero' => env('PRONTOVIA_HOME_HERO_IMAGE', 'images/prontovia/pessoa-que.avif'),
        'candidate_hero' => env('PRONTOVIA_CANDIDATE_HERO_IMAGE', 'images/prontovia/pessoa-que.avif'),
        'school_hero' => env('PRONTOVIA_SCHOOL_HERO_IMAGE', 'images/prontovia/backgrouseccao.png'),
        'schools_section' => env('PRONTOVIA_SCHOOLS_SECTION_IMAGE', 'images/prontovia/backgrouseccao.png'),
    ],
    'social' => [
        'facebook' => env('PRONTOVIA_FACEBOOK_URL'),
        'instagram' => env('PRONTOVIA_INSTAGRAM_URL'),
        'linkedin' => env('PRONTOVIA_LINKEDIN_URL'),
    ],
    /* Escolas patrocinadoras. Só aparecem como parceiro real quando nome e URL existem. */
    'partners' => array_values(array_filter([
        ['name' => env('PRONTOVIA_PARTNER_1_NAME'), 'url' => env('PRONTOVIA_PARTNER_1_URL'), 'logo' => env('PRONTOVIA_PARTNER_1_LOGO'), 'location' => env('PRONTOVIA_PARTNER_1_LOCATION')],
        ['name' => env('PRONTOVIA_PARTNER_2_NAME'), 'url' => env('PRONTOVIA_PARTNER_2_URL'), 'logo' => env('PRONTOVIA_PARTNER_2_LOGO'), 'location' => env('PRONTOVIA_PARTNER_2_LOCATION')],
        ['name' => env('PRONTOVIA_PARTNER_3_NAME'), 'url' => env('PRONTOVIA_PARTNER_3_URL'), 'logo' => env('PRONTOVIA_PARTNER_3_LOGO'), 'location' => env('PRONTOVIA_PARTNER_3_LOCATION')],
    ], fn ($partner) => $partner['name'] && $partner['url'])),
];
