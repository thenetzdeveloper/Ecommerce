<?php
declare(strict_types=1);

/**
 * 4K STORE - Page Content & Note Properties Configuration
 * Maps all 214 bilingual keys to step-by-step pages, logical section cards,
 * human-readable titles, contextual placement hints, and form input controls.
 */

if (!function_exists('getPageContentSteps')) {
    function getPageContentSteps(): array
    {
        return [
            'step1_home' => [
                'step_num' => 1,
                'id' => 'step1_home',
                'icon' => 'fa-house',
                'title_km' => 'ទំព័រដើម & Hero',
                'title_en' => 'Homepage & Hero',
                'badge' => 'ទំព័រដើម (Step 1)',
                'desc' => 'គ្រប់គ្រងចំណងជើង Hero Banner, ស្ថិតិសង្ខេប 100% / KHQR, កាតាឡុកទំនិញ, ប្រអប់ស្វែងរក និងចំណុចធានា 4 យ៉ាង។',
                'preview_url' => '../home/index.php',
                'sections' => [
                    [
                        'title_km' => 'ផ្ទាំង Hero ខាងលើ & ស្ថិតិសង្ខេប',
                        'title_en' => 'Hero Banner Headline, CTA & Stat Highlights',
                        'icon' => 'fa-wand-magic-sparkles',
                        'desc' => 'បង្ហាញនៅផ្នែកខាងលើគេបង្អស់នៃទំព័រដើម (Hero Section)',
                        'keys' => [
                            'home_hero_title' => ['title' => 'ចំណងជើងធំ Hero (Main Headline)', 'hint' => 'ចំណងជើងធំលេចធ្លោលើ Hero Banner', 'type' => 'input'],
                            'home_hero_desc' => ['title' => 'កថាខណ្ឌពន្យល់ Hero (Lead Description)', 'hint' => 'អត្ថបទពណ៌នាខាងក្រោមចំណងជើងធំ Hero', 'type' => 'textarea'],
                            'hero_curated' => ['title' => 'ផ្លាកសញ្ញាខាងលើ (Hero Pill Badge)', 'hint' => 'ស្លាកតូចខាងលើចំណងជើង Hero', 'type' => 'input'],
                            'hero_cta' => ['title' => 'ប៊ូតុងទិញទំនិញ (Shop CTA Button)', 'hint' => 'ប៊ូតុងសកម្មភាពចម្បងលើ Hero Banner', 'type' => 'input'],
                            'stat_quality_title' => ['title' => 'ស្ថិតិទី ១: ចំនួន/ភាគរយ (Stat 1 Number)', 'hint' => 'បង្ហាញលេខ 100% លើកាតស្ថិតិទី ១', 'type' => 'input'],
                            'stat_quality_desc' => ['title' => 'ស្ថិតិទី ១: អត្ថបទពន្យល់ (Stat 1 Text)', 'hint' => 'អត្ថបទ "គុណភាពធានា" ខាងក្រោមលេខ 100%', 'type' => 'input'],
                            'stat_shipping_title' => ['title' => 'ស្ថិតិទី ២: ចំណងជើង (Stat 2 Title)', 'hint' => 'បង្ហាញពាក្យ "រហ័ស" លើកាតស្ថិតិទី ២', 'type' => 'input'],
                            'stat_shipping_desc' => ['title' => 'ស្ថិតិទី ២: អត្ថបទពន្យល់ (Stat 2 Text)', 'hint' => 'អត្ថបទ "ដឹកជញ្ជូនរហ័ស" ខាងក្រោម', 'type' => 'input'],
                            'stat_khqr_title' => ['title' => 'ស្ថិតិទី ៣: ចំណងជើង (Stat 3 Title)', 'hint' => 'បង្ហាញពាក្យ "KHQR" លើកាតស្ថិតិទី ៣', 'type' => 'input'],
                            'stat_khqr_desc' => ['title' => 'ស្ថិតិទី ៣: អត្ថបទពន្យល់ (Stat 3 Text)', 'hint' => 'អត្ថបទ "ទូទាត់ងាយស្រួល" ខាងក្រោម KHQR', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'កាតាឡុកទំនិញ & ផ្ទាំងស្វែងរក',
                        'title_en' => 'Catalog Header, Search Bar & Category Filters',
                        'icon' => 'fa-magnifying-glass',
                        'desc' => 'បង្ហាញនៅផ្នែកកាតាឡុកទំនិញ និងរបារស្វែងរកផលិតផល',
                        'keys' => [
                            'catalog_kicker' => ['title' => 'ពាក្យខាងលើកាតាឡុក (Catalog Kicker)', 'hint' => 'អក្សរតូច THE 4K EDIT ខាងលើចំណងជើងកាតាឡុក', 'type' => 'input'],
                            'catalog_title' => ['title' => 'ចំណងជើងកាតាឡុក (Catalog Title)', 'hint' => 'ចំណងជើងធំ "ស្វែងរកផលិតផលដែលអ្នកពេញចិត្ត"', 'type' => 'input'],
                            'catalog_subtitle' => ['title' => 'កថាខណ្ឌរងកាតាឡុក (Catalog Subtitle)', 'hint' => 'អត្ថបទពណ៌នាខាងក្រោមចំណងជើងកាតាឡុក', 'type' => 'input'],
                            'search_placeholder' => ['title' => 'អត្ថបទប្រអប់ស្វែងរក (Search Placeholder)', 'hint' => 'Placeholder ក្នុងប្រអប់ស្វែងរកផលិតផល', 'type' => 'input'],
                            'cat_all' => ['title' => 'ប៊ូតុងប្រភេទ: ទាំងអស់ (All Filter Pill)', 'hint' => 'ប៊ូតុងជ្រើសរើសទំនិញទាំងអស់', 'type' => 'input'],
                            'cat_men' => ['title' => 'ប៊ូតុងប្រភេទ: បុរស (Men Filter Pill)', 'hint' => 'ប៊ូតុងជ្រើសរើសទំនិញបុរស', 'type' => 'input'],
                            'cat_women' => ['title' => 'ប៊ូតុងប្រភេទ: នារី (Women Filter Pill)', 'hint' => 'ប៊ូតុងជ្រើសរើសទំនិញនារី', 'type' => 'input'],
                            'cat_accessories' => ['title' => 'ប៊ូតុងប្រភេទ: គ្រឿងតុបតែង (Accessories Pill)', 'hint' => 'ប៊ូតុងជ្រើសរើសគ្រឿងតុបតែង', 'type' => 'input'],
                            'cat_digital' => ['title' => 'ប៊ូតុងប្រភេទ: ឌីជីថល (Digital Pill)', 'hint' => 'ប៊ូតុងជ្រើសរើសទំនិញឌីជីថល', 'type' => 'input'],
                            'sort_featured' => ['title' => 'តម្រៀប: ពេញនិយម (Sort Featured)', 'hint' => 'ជម្រើសតម្រៀបទំនិញពេញនិយម', 'type' => 'input'],
                            'sort_discount_desc' => ['title' => 'តម្រៀប: បញ្ចុះតម្លៃច្រើន (Sort SALE)', 'hint' => 'ជម្រើសតម្រៀបទំនិញបញ្ចុះតម្លៃច្រើនជាងគេ', 'type' => 'input'],
                            'sort_price_asc' => ['title' => 'តម្រៀប: តម្លៃទាបទៅខ្ពស់ (Price: Low to High)', 'hint' => 'ជម្រើសតម្រៀបតម្លៃទាបទៅខ្ពស់', 'type' => 'input'],
                            'sort_price_desc' => ['title' => 'តម្រៀប: តម្លៃខ្ពស់ទៅទាប (Price: High to Low)', 'hint' => 'ជម្រើសតម្រៀបតម្លៃខ្ពស់ទៅទាប', 'type' => 'input'],
                            'sort_newest' => ['title' => 'តម្រៀប: ទើបមកដល់ (New Arrivals)', 'hint' => 'ជម្រើសតម្រៀបទំនិញទើបមកដល់ថ្មី', 'type' => 'input'],
                            'showing_products' => ['title' => 'ពាក្យរាប់: បង្ហាញ (Showing Counter)', 'hint' => 'អក្សរខាងមុខចំនួនផលិតផល (ឧ. បង្ហាញ 53)', 'type' => 'input'],
                            'products_unit' => ['title' => 'ឯកតារាប់: ផលិតផល (Products Unit)', 'hint' => 'ពាក្យខាងក្រោយចំនួនផលិតផល (ឧ. 53 ផលិតផល)', 'type' => 'input'],
                            'empty_search_title' => ['title' => 'ចំណងជើងពេលរកមិនឃើញ (Search Empty Title)', 'hint' => 'បង្ហាញនៅពេលស្វែងរកមិនមានផលិតផលត្រូវ', 'type' => 'input'],
                            'empty_search_desc' => ['title' => 'ការពន្យល់ពេលរកមិនឃើញ (Search Empty Desc)', 'hint' => 'ការណែនាំឱ្យសាកល្បងពាក្យគន្លឹះផ្សេងទៀត', 'type' => 'textarea'],
                            'reset_filter_btn' => ['title' => 'ប៊ូតុងកំណត់តម្រងឡើងវិញ (Reset Filters Button)', 'hint' => 'ប៊ូតុងបង្ហាញផលិតផលទាំងអស់ឡើងវិញ', 'type' => 'input'],
                            'catalog_no_products' => ['title' => 'អត្ថបទពេលគ្មានផលិតផលក្នុងហាង (No Products)', 'hint' => 'បង្ហាញពេលហាងមិនទាន់មានទំនិញលក់', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ចំណុចធានា & ទំនុកចិត្ត ៤ យ៉ាង',
                        'title_en' => '4 Trust & Quality Assurance Cards',
                        'icon' => 'fa-shield-halved',
                        'desc' => 'កាតទំនុកចិត្ត 4 ខាងក្រោមនៃទំព័រដើម (Shipping, Quality, KHQR, Support)',
                        'keys' => [
                            'trust_shipping_title' => ['title' => 'កាតធានា ១: ចំណងជើង (Shipping Title)', 'hint' => 'ចំណងជើង "ដឹកជញ្ជូនរហ័សទូទាំងប្រទេស"', 'type' => 'input'],
                            'trust_shipping_desc' => ['title' => 'កាតធានា ១: អត្ថបទពន្យល់ (Shipping Subtitle)', 'hint' => 'អត្ថបទ "ដឹកជញ្ជូនរហ័សដល់ដៃ"', 'type' => 'input'],
                            'trust_quality_title' => ['title' => 'កាតធានា ២: ចំណងជើង (Quality Title)', 'hint' => 'ចំណងជើង "ធានាសុវត្ថិភាព 100%"', 'type' => 'input'],
                            'trust_quality_desc' => ['title' => 'កាតធានា ២: អត្ថបទពន្យល់ (Quality Subtitle)', 'hint' => 'អត្ថបទ "គុណភាពពិតប្រាកដ"', 'type' => 'input'],
                            'trust_payment_title' => ['title' => 'កាតធានា ៣: ចំណងជើង (Payment Title)', 'hint' => 'ចំណងជើង "ទូទាត់រហ័ស KHQR"', 'type' => 'input'],
                            'trust_payment_desc' => ['title' => 'កាតធានា ៣: អត្ថបទពន្យល់ (Payment Subtitle)', 'hint' => 'អត្ថបទ "Bakong & គ្រប់ធនាគារ"', 'type' => 'input'],
                            'trust_support_title' => ['title' => 'កាតធានា ៤: ចំណងជើង (Support Title)', 'hint' => 'ចំណងជើង "ជំនួយអតិថិជន 24/7"', 'type' => 'input'],
                            'trust_support_desc' => ['title' => 'កាតធានា ៤: អត្ថបទពន្យល់ (Support Subtitle)', 'hint' => 'អត្ថបទ "Telegram Support Desk"', 'type' => 'input'],
                        ]
                    ]
                ]
            ],
            'step2_about' => [
                'step_num' => 2,
                'id' => 'step2_about',
                'icon' => 'fa-circle-info',
                'title_km' => 'ទំព័រអំពីយើង',
                'title_en' => 'About Us Page',
                'badge' => 'អំពីយើង (Step 2)',
                'desc' => 'គ្រប់គ្រងខ្លឹមសារទំព័រ About Us: Hero Headline, ប្រអប់ស្ថិតិ 3, រឿងរ៉ាវ & បេសកកម្ម, តម្លៃស្នូល 3, ជំហានទិញទំនិញ 4 ជំហាន, និងប្រអប់ជំនួយ CTA។',
                'preview_url' => '../home/index.php?page=about',
                'sections' => [
                    [
                        'title_km' => 'ផ្ទាំង Hero ខាងលើ & ប៊ូតុងសកម្មភាព',
                        'title_en' => 'Hero Headline, Eyebrow & Action Buttons',
                        'icon' => 'fa-crown',
                        'desc' => 'បង្ហាញនៅផ្នែកខាងលើនៃទំព័រ About Us',
                        'keys' => [
                            'about_hero_eyebrow' => ['title' => 'ស្លាកតូចខាងលើ (Eyebrow Badge)', 'hint' => 'ស្លាក "ស្តង់ដារ និងគុណភាពខ្ពស់"', 'type' => 'input'],
                            'about_hero_title' => ['title' => 'ចំណងជើងធំ (About Hero Headline)', 'hint' => 'ចំណងជើងធំ "ស្វែងយល់អំពី 4K STORE និងការប្តេជ្ញាចិត្តរបស់យើង"', 'type' => 'input'],
                            'about_hero_lead' => ['title' => 'កថាខណ្ឌពន្យល់ធំ (Hero Lead Paragraph)', 'hint' => 'អត្ថបទពណ៌នាលម្អិតអំពីហាង និងទំនុកចិត្ត 100%', 'type' => 'textarea'],
                            'about_btn_browse' => ['title' => 'ប៊ូតុងរកមើលផលិតផល (Browse Button)', 'hint' => 'ប៊ូតុង "ស្វែងរកផលិតផល" ពណ៌បៃតងចាស់', 'type' => 'input'],
                            'about_btn_support' => ['title' => 'ប៊ូតុងទាក់ទងជំនួយ (Support Button)', 'hint' => 'ប៊ូតុង "ទាក់ទងផ្នែកបម្រើអតិថិជន"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ប្រអប់ស្ថិតិ & ចំណុចលេចធ្លោ ៣',
                        'title_en' => '3 Stat Cards (100% Quality, 24/7 Service, KHQR)',
                        'icon' => 'fa-chart-simple',
                        'desc' => 'កាតស្ថិតិ 3 (100%, 24/7, KHQR) នៅក្រោម Hero Lead',
                        'keys' => [
                            'about_val1_desc_short' => ['title' => 'ស្ថិតិ 100%: អត្ថបទពន្យល់ (Quality Fact Note)', 'hint' => 'អត្ថបទ "គុណភាពធានា និងផលិតផលពិត" ក្រោម 100%', 'type' => 'input'],
                            'about_service_short' => ['title' => 'ស្ថិតិ 24/7: អត្ថបទពន្យល់ (Service Fact Note)', 'hint' => 'អត្ថបទ "សេវាកម្មរហ័ស និងការឆ្លើយតប" ក្រោម 24/7', 'type' => 'input'],
                            'about_payment_short' => ['title' => 'ស្ថិតិ KHQR: អត្ថបទពន្យល់ (Payment Fact Note)', 'hint' => 'អត្ថបទ "ទូទាត់ប្រាក់ងាយស្រួល និងសុវត្ថិភាព" ក្រោម KHQR', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'រឿងរ៉ាវ & បេសកកម្មរបស់យើង',
                        'title_en' => 'Our Story & Mission Cards',
                        'icon' => 'fa-book-open',
                        'desc' => 'ផ្នែករឿងរ៉ាវប្រវត្តិបង្កើតឡើង និងបេសកកម្មចម្បង',
                        'keys' => [
                            'about_story_heading_kicker' => ['title' => 'ពាក្យខាងលើផ្នែក (Story Kicker)', 'hint' => 'អក្សរតូច "រឿងរ៉ាវ & បេសកកម្ម"', 'type' => 'input'],
                            'about_story_heading_title' => ['title' => 'ចំណងជើងផ្នែក (Story Section Title)', 'hint' => 'ចំណងជើង "រឿងរ៉ាវ និងបេសកកម្មរបស់យើង"', 'type' => 'input'],
                            'about_story_heading_desc' => ['title' => 'កថាខណ្ឌពន្យល់ផ្នែក (Story Subtitle)', 'hint' => 'អត្ថបទពណ៌នាអំពីប្រវត្តិ និងគោលបំណងចម្បង', 'type' => 'input'],
                            'about_who_title' => ['title' => 'កាតទី ១: ចំណងជើង "យើងជានរណា?"', 'hint' => 'ចំណងជើងកាត Who We Are', 'type' => 'input'],
                            'about_who_desc' => ['title' => 'កាតទី ១: អត្ថបទពន្យល់ (Who We Are Story)', 'hint' => 'ពណ៌នាអំពីការបង្កើតឡើងដោយក្តីស្រឡាញ់លើម៉ូដ', 'type' => 'textarea'],
                            'about_mission_title' => ['title' => 'កាតទី ២: ចំណងជើង "បេសកកម្មរបស់យើង"', 'hint' => 'ចំណងជើងកាត Our Mission', 'type' => 'input'],
                            'about_mission_desc' => ['title' => 'កាតទី ២: អត្ថបទពន្យល់ (Our Mission Desc)', 'hint' => 'ពណ៌នាអំពីបេសកកម្មផ្តល់បទពិសោធន៍ទិញទំនិញល្អឥតខ្ចោះ', 'type' => 'textarea'],
                        ]
                    ],
                    [
                        'title_km' => 'តម្លៃស្នូល ៣ យ៉ាងដែលយើងប្រកាន់ខ្ជាប់',
                        'title_en' => 'Core Values We Live By',
                        'icon' => 'fa-gem',
                        'desc' => 'គោលការណ៍តម្លៃស្នូល 3 (គុណភាព, ទំនុកចិត្ត, ការទទួលខុសត្រូវ)',
                        'keys' => [
                            'about_values_heading_kicker' => ['title' => 'ពាក្យខាងលើផ្នែកតម្លៃស្នូល (Values Kicker)', 'hint' => 'អក្សរតូច "តម្លៃស្នូលរបស់យើង"', 'type' => 'input'],
                            'about_values_heading_title' => ['title' => 'ចំណងជើងផ្នែក (Values Section Title)', 'hint' => 'ចំណងជើង "តម្លៃស្នូលដែលយើងប្រកាន់ខ្ជាប់"', 'type' => 'input'],
                            'about_values_heading_desc' => ['title' => 'កថាខណ្ឌពន្យល់ផ្នែក (Values Subtitle)', 'hint' => 'អត្ថបទពណ៌នាគោលការណ៍ណែនាំការងារ', 'type' => 'input'],
                            'about_val1_title' => ['title' => 'តម្លៃស្នូល ១: ចំណងជើង (Quality First)', 'hint' => 'ចំណងជើង "គុណភាពជាចម្បង"', 'type' => 'input'],
                            'about_val1_desc' => ['title' => 'តម្លៃស្នូល ១: ការពន្យល់ (Quality First Desc)', 'hint' => 'ពណ៌នាអំពីការត្រួតពិនិត្យផលិតផលយ៉ាងហ្មត់ចត់', 'type' => 'textarea'],
                            'about_val2_title' => ['title' => 'តម្លៃស្នូល ២: ចំណងជើង (Trust & Transparency)', 'hint' => 'ចំណងជើង "ទំនុកចិត្ត និងតម្លាភាព"', 'type' => 'input'],
                            'about_val2_desc' => ['title' => 'តម្លៃស្នូល ២: ការពន្យល់ (Trust Desc)', 'hint' => 'ពណ៌នាអំពីតម្លៃពិត រូបភាពពិត គ្មានលាក់បាំង', 'type' => 'textarea'],
                            'about_val3_title' => ['title' => 'តម្លៃស្នូល ៣: ចំណងជើង (Accountability)', 'hint' => 'ចំណងជើង "ការទទួលខុសត្រូវខ្ពស់"', 'type' => 'input'],
                            'about_val3_desc' => ['title' => 'តម្លៃស្នូល ៣: ការពន្យល់ (Accountability Desc)', 'hint' => 'ពណ៌នាអំពីការយកចិត្តទុកដាក់លើការពេញចិត្តរបស់អតិថិជន', 'type' => 'textarea'],
                        ]
                    ],
                    [
                        'title_km' => 'របៀបទិញទំនិញងាយៗ ៤ ជំហាន',
                        'title_en' => '4 Easy Steps to Shop',
                        'icon' => 'fa-list-ol',
                        'desc' => 'ការណែនាំ 4 ជំហាន (រើសទំនិញ, ពិនិត្យ, ស្កេន KHQR, ផ្ញើវិក្កយបត្រ)',
                        'keys' => [
                            'about_steps_heading_kicker' => ['title' => 'ពាក្យខាងលើផ្នែកជំហាន (Steps Kicker)', 'hint' => 'អក្សរតូច "របៀបទិញទំនិញ"', 'type' => 'input'],
                            'about_steps_heading_title' => ['title' => 'ចំណងជើងផ្នែក (Steps Section Title)', 'hint' => 'ចំណងជើង "របៀបទិញទំនិញងាយៗ ៤ ជំហាន"', 'type' => 'input'],
                            'about_steps_heading_desc' => ['title' => 'កថាខណ្ឌពន្យល់ផ្នែក (Steps Subtitle)', 'hint' => 'អត្ថបទ "បទពិសោធន៍ទិញទំនិញដ៏រហ័ស និងសុវត្ថិភាពបំផុត"', 'type' => 'input'],
                            'about_step1_title' => ['title' => 'ជំហាន ១: ចំណងជើង (Step 1 Title)', 'hint' => 'ចំណងជើង "ជ្រើសរើសផលិតផល"', 'type' => 'input'],
                            'about_step1_desc' => ['title' => 'ជំហាន ១: ការពន្យល់ (Step 1 Note)', 'hint' => 'ការណែនាំរើសមើលសម្លៀកបំពាក់ក្នុង Storefront', 'type' => 'textarea'],
                            'about_step2_title' => ['title' => 'ជំហាន ២: ចំណងជើង (Step 2 Title)', 'hint' => 'ចំណងជើង "ពិនិត្យ និងបញ្ជាទិញ"', 'type' => 'input'],
                            'about_step2_desc' => ['title' => 'ជំហាន ២: ការពន្យល់ (Step 2 Note)', 'hint' => 'ការណែនាំពិនិត្យព័ត៌មានលម្អិត បញ្ចូលក្នុងកន្ត្រក ឬទិញភ្លាម', 'type' => 'textarea'],
                            'about_step3_title' => ['title' => 'ជំហាន ៣: ចំណងជើង (Step 3 Title)', 'hint' => 'ចំណងជើង "ស្កេនទូទាត់ KHQR"', 'type' => 'input'],
                            'about_step3_desc' => ['title' => 'ជំហាន ៣: ការពន្យល់ (Step 3 Note)', 'hint' => 'ការណែនាំស្កេន Bakong KHQR គ្រប់ធនាគារ', 'type' => 'textarea'],
                            'about_step4_title' => ['title' => 'ជំហាន ៤: ចំណងជើង (Step 4 Title)', 'hint' => 'ចំណងជើង "ផ្ញើវិក្កយបត្រ & រង់ចាំ"', 'type' => 'input'],
                            'about_step4_desc' => ['title' => 'ជំហាន ៤: ការពន្យល់ (Step 4 Note)', 'hint' => 'ការណែនាំភ្ជាប់រូបវិក្កយបត្រ រួចរង់ចាំទទួលទំនិញដឹកដល់ផ្ទះ', 'type' => 'textarea'],
                        ]
                    ],
                    [
                        'title_km' => 'ប្រអប់ជំនួយ & ទំនាក់ទំនង (Support CTA)',
                        'title_en' => 'Support CTA Box & Buttons',
                        'icon' => 'fa-comments',
                        'desc' => 'ផ្ទាំងទំនាក់ទំនងខាងក្រោមបង្អស់នៃទំព័រ About Us',
                        'keys' => [
                            'about_help_eyebrow' => ['title' => 'ស្លាកខាងលើ CTA (CTA Eyebrow)', 'hint' => 'ស្លាក "យើងនៅទីនេះដើម្បីជួយអ្នក"', 'type' => 'input'],
                            'about_cta_title' => ['title' => 'ចំណងជើង CTA (CTA Heading)', 'hint' => 'ចំណងជើង "មានសំណួរអំពីផលិតផល ឬការបញ្ជាទិញ?"', 'type' => 'input'],
                            'about_cta_desc' => ['title' => 'កថាខណ្ឌពន្យល់ CTA (CTA Description)', 'hint' => 'អត្ថបទពណ៌នាអំពីការទាក់ទងតាម Telegram ឬទំព័រជំនួយ', 'type' => 'textarea'],
                            'about_cta_telegram' => ['title' => 'ប៊ូតុង Telegram CTA (Telegram Button)', 'hint' => 'ប៊ូតុង "ទាក់ទងតាម Telegram"', 'type' => 'input'],
                            'about_cta_form' => ['title' => 'ប៊ូតុងទម្រង់ជំនួយ (Support Page Button)', 'hint' => 'ប៊ូតុង "ផ្ញើសារក្នុងទំព័រជំនួយ"', 'type' => 'input'],
                        ]
                    ]
                ]
            ],
            'step3_support' => [
                'step_num' => 3,
                'id' => 'step3_support',
                'icon' => 'fa-headset',
                'title_km' => 'ទំព័រជំនួយការ',
                'title_en' => 'Customer Support',
                'badge' => 'ជំនួយការ (Step 3)',
                'desc' => 'គ្រប់គ្រងខ្លឹមសារទំព័រ Support: ផ្ទាំងជំនួយឆ្វេង, ឆានែល Telegram (កំណត់សម្គាល់ឆ្លើយតប 10 នាទី), ម៉ោងបម្រើសេវា, ការធានា និងទម្រង់ផ្ញើសារ។',
                'preview_url' => '../customer-service/index.php',
                'sections' => [
                    [
                        'title_km' => 'ផ្ទាំងព័ត៌មានខាងឆ្វេង & បណ្តាញទាក់ទងផ្ទាល់',
                        'title_en' => 'Support Channels, Hours & Response Guarantees',
                        'icon' => 'fa-phone-volume',
                        'desc' => 'ព័ត៌មានជំនួយ 24/7, Telegram, ម៉ោងដំណើរការ, ទីតាំង និងទំនុកចិត្ត',
                        'keys' => [
                            'support_title' => ['title' => 'ចំណងជើងទំព័រជំនួយ (Breadcrumb / Title)', 'hint' => 'ចំណងជើង "ជំនួយ & ទំនាក់ទំនងអតិថិជន"', 'type' => 'input'],
                            'support_badge_pill' => ['title' => 'ផ្លាកសញ្ញាខាងលើ (24/7 Pill Badge)', 'hint' => 'ស្លាកតូច "សេវាកម្មអតិថិជន 24/7"', 'type' => 'input'],
                            'support_hero_title' => ['title' => 'ចំណងជើងធំ (Support Hero Headline)', 'hint' => 'ចំណងជើង "យើងនៅទីនេះដើម្បីជួយអ្នក"', 'type' => 'input'],
                            'support_lead' => ['title' => 'កថាខណ្ឌពន្យល់ (Support Lead Paragraph)', 'hint' => 'អត្ថបទពណ៌នាអំពីសំណួរផលិតផល KHQR ឬការដឹកជញ្ជូន', 'type' => 'textarea'],
                            'support_tg_title' => ['title' => 'ឆានែល Telegram: ចំណងជើង', 'hint' => 'ចំណងជើង "Telegram Support"', 'type' => 'input'],
                            'support_tg_desc' => ['title' => 'ឆានែល Telegram: កំណត់សម្គាល់ឆ្លើយតប (Note)', 'hint' => 'កំណត់សម្គាល់ "ឆ្លើយតបរហ័សទាន់ចិត្ត ក្រោម 10 នាទី"', 'type' => 'input'],
                            'support_phone_title' => ['title' => 'លេខទូរស័ព្ទ: ចំណងជើង (Phone Title)', 'hint' => 'ចំណងជើង "លេខទូរស័ព្ទផ្ទាល់"', 'type' => 'input'],
                            'support_phone_desc' => ['title' => 'លេខទូរស័ព្ទ: កំណត់សម្គាល់ (Phone Note)', 'hint' => 'កំណត់សម្គាល់ "ទំនាក់ទំនងផ្ទាល់ក្នុងម៉ោងបម្រើសេវា"', 'type' => 'input'],
                            'support_hours_title' => ['title' => 'ម៉ោងបម្រើសេវា: ចំណងជើង (Hours Title)', 'hint' => 'ចំណងជើង "ម៉ោងបម្រើសេវាកម្ម"', 'type' => 'input'],
                            'support_hours_desc' => ['title' => 'ម៉ោងបម្រើសេវា: ពេលវេលា (Hours Details)', 'hint' => 'កំណត់សម្គាល់ "ច័ន្ទ - អាទិត្យ: 8:00 AM - 10:00 PM"', 'type' => 'input'],
                            'support_hours_badge' => ['title' => 'ម៉ោងបម្រើសេវា: ផ្លាកសញ្ញា (Active Badge)', 'hint' => 'ស្លាកពណ៌បៃតង "កំពុងដំណើរការរាល់ថ្ងៃ"', 'type' => 'input'],
                            'support_trust_title' => ['title' => 'ការធានា: ចំណងជើង (Trust Title)', 'hint' => 'ចំណងជើង "ការធានា និងទំនុកចិត្ត"', 'type' => 'input'],
                            'support_trust_desc' => ['title' => 'ការធានា: កំណត់សម្គាល់ (Trust Note)', 'hint' => 'កំណត់សម្គាល់ "100% យកចិត្តទុកដាក់ ដោះស្រាយបញ្ហារហ័ស"', 'type' => 'input'],
                            'support_trust_badge' => ['title' => 'ការធានា: ផ្លាកសញ្ញា (Guarantee Badge)', 'hint' => 'ស្លាក "ធានាការឆ្លើយតប"', 'type' => 'input'],
                            'support_location' => ['title' => 'ទីតាំងហាង (Location Address Text)', 'hint' => 'អាសយដ្ឋាន "រាជធានីភ្នំពេញ ព្រះរាជាណាចក្រកម្ពុជា"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ទម្រង់ផ្ញើសារ & ការជូនដំណឹង (Contact Form & Alerts)',
                        'title_en' => 'Contact Form Labels, Placeholders & Toast Alerts',
                        'icon' => 'fa-paper-plane',
                        'desc' => 'ទម្រង់ផ្ញើសារខាងស្តាំដៃ ផ្លាកសញ្ញា និងសារជូនដំណឹងជោគជ័យ/បរាជ័យ',
                        'keys' => [
                            'support_form_kicker' => ['title' => 'ស្លាកតូចលើទម្រង់ (Form Kicker)', 'hint' => 'ស្លាក "ផ្ញើសារមកកាន់យើង"', 'type' => 'input'],
                            'support_form_title' => ['title' => 'ចំណងជើងទម្រង់ (Form Title)', 'hint' => 'ចំណងជើង "ទម្រង់ទំនាក់ទំនង"', 'type' => 'input'],
                            'support_form_lead' => ['title' => 'កថាខណ្ឌពន្យល់ទម្រង់ (Form Lead Note)', 'hint' => 'ការណែនាំបំពេញព័ត៌មានដើម្បីក្រុមការងារទាក់ទងត្រឡប់', 'type' => 'input'],
                            'support_name_label' => ['title' => 'ប្រអប់ឈ្មោះ: ស្លាក (Name Label)', 'hint' => 'ស្លាក "ឈ្មោះពេញរបស់អ្នក *"', 'type' => 'input'],
                            'support_name_ph' => ['title' => 'ប្រអប់ឈ្មោះ: គំរូ (Name Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: សុខា"', 'type' => 'input'],
                            'support_contact_label' => ['title' => 'ប្រអប់ទំនាក់ទំនង: ស្លាក (Contact Label)', 'hint' => 'ស្លាក "អ៊ីម៉ែល ឬ Telegram ID ឬលេខទូរស័ព្ទ *"', 'type' => 'input'],
                            'support_contact_ph' => ['title' => 'ប្រអប់ទំនាក់ទំនង: គំរូ (Contact Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: sokha@gmail.com ឬ @sokha_kh"', 'type' => 'input'],
                            'support_msg_label' => ['title' => 'ប្រអប់សារ: ស្លាក (Message Label)', 'hint' => 'ស្លាក "សារ ឬសំណួររបស់អ្នក *"', 'type' => 'input'],
                            'support_msg_ph' => ['title' => 'ប្រអប់សារ: គំរូ (Message Placeholder)', 'hint' => 'គំរូ "សូមសរសេរសារ ឬសំណួរអំពីផលិតផល..."', 'type' => 'textarea'],
                            'support_btn_send' => ['title' => 'ប៊ូតុងផ្ញើសារ (Send Message Button)', 'hint' => 'ប៊ូតុង "បញ្ជូនសារ"', 'type' => 'input'],
                            'support_alert_success' => ['title' => 'សារជោគជ័យ (Success Alert)', 'hint' => 'បង្ហាញពេលផ្ញើសារជោគជ័យ', 'type' => 'input'],
                            'support_alert_error' => ['title' => 'សារបរាជ័យ (Error Alert)', 'hint' => 'បង្ហាញពេលមានបញ្ហាក្នុងការផ្ញើសារ', 'type' => 'input'],
                        ]
                    ]
                ]
            ],
            'step4_checkout' => [
                'step_num' => 4,
                'id' => 'step4_checkout',
                'icon' => 'fa-credit-card',
                'title_km' => 'ទំព័រទូទាត់ប្រាក់',
                'title_en' => 'Checkout & Payment',
                'badge' => 'ការទូទាត់ (Step 4)',
                'desc' => 'គ្រប់គ្រងខ្លឹមសារទំព័រ Checkout: ព័ត៌មានអតិថិជន & អាសយដ្ឋាន, ការណែនាំស្កេន Bakong KHQR, ការភ្ជាប់រូបវិក្កយបត្រ, គូប៉ុងបញ្ចុះតម្លៃ, តម្លៃសរុប និងផ្ទាំងបញ្ជាទិញជោគជ័យ។',
                'preview_url' => '../home/checkout.php',
                'sections' => [
                    [
                        'title_km' => 'ព័ត៌មានអតិថិជន & អាសយដ្ឋានដឹកជញ្ជូន',
                        'title_en' => 'Customer & Delivery Address Fields',
                        'icon' => 'fa-truck-fast',
                        'desc' => 'ទម្រង់បំពេញព័ត៌មានអតិថិជន លេខទូរស័ព្ទ និងកំណត់សម្គាល់ដឹកជញ្ជូន',
                        'keys' => [
                            'checkout_page_title' => ['title' => 'ចំណងជើងទំព័រ (Page Title)', 'hint' => 'បង្ហាញលើ Head & Tab Browser', 'type' => 'input'],
                            'checkout_bc_home' => ['title' => 'Breadcrumb: ទំព័រដើម (Breadcrumb Home)', 'hint' => 'តំណភ្ជាប់ Breadcrumb ទៅទំព័រដើម', 'type' => 'input'],
                            'checkout_bc_curr' => ['title' => 'Breadcrumb: ទូទាត់ប្រាក់ (Breadcrumb Current)', 'hint' => 'Breadcrumb បច្ចុប្បន្ន "ទូទាត់ប្រាក់"', 'type' => 'input'],
                            'checkout_box_customer' => ['title' => 'ចំណងជើងប្រអប់ព័ត៌មាន (Customer Box Title)', 'hint' => 'ចំណងជើង "ព័ត៌មានអតិថិជន & ដឹកជញ្ជូន"', 'type' => 'input'],
                            'checkout_guest_prompt' => ['title' => 'កំណត់សម្គាល់ភ្ញៀវចូលគណនី (Guest Prompt Note)', 'hint' => 'ការណែនាំភ្ញៀវឱ្យ Login ដើម្បីបំពេញទិន្នន័យស្វ័យប្រវត្តិ', 'type' => 'input'],
                            'checkout_logged_in_as' => ['title' => 'ចំណាំពេលចូលគណនីរួច (Logged In Badge)', 'hint' => 'អត្ថបទ "បានចូលគណនីជា [ឈ្មោះ]"', 'type' => 'input'],
                            'checkout_name_label' => ['title' => 'ឈ្មោះពេញ: ស្លាក (Name Label)', 'hint' => 'ស្លាក "ឈ្មោះពេញរបស់អ្នក (Full Name) *"', 'type' => 'input'],
                            'checkout_name_ph' => ['title' => 'ឈ្មោះពេញ: គំរូ (Name Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: សុខា (Sokha)"', 'type' => 'input'],
                            'checkout_phone_label' => ['title' => 'លេខទូរស័ព្ទ: ស្លាក (Phone Label)', 'hint' => 'ស្លាក "លេខទូរស័ព្ទ / Telegram *"', 'type' => 'input'],
                            'checkout_phone_ph' => ['title' => 'លេខទូរស័ព្ទ: គំរូ (Phone Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: 012 345 678 ឬ @sokha_tg"', 'type' => 'input'],
                            'checkout_address_label' => ['title' => 'អាសយដ្ឋាន: ស្លាក (Address Label)', 'hint' => 'ស្លាក "អាសយដ្ឋានដឹកជញ្ជូន (Delivery Address) *"', 'type' => 'input'],
                            'checkout_address_ph' => ['title' => 'អាសយដ្ឋាន: គំរូ (Address Placeholder)', 'hint' => 'គំរូ "ផ្ទះលេខ, ផ្លូវលេខ, សង្កាត់, ខណ្ឌ..."', 'type' => 'textarea'],
                            'checkout_note_label' => ['title' => 'កំណត់សម្គាល់បន្ថែម: ស្លាក (Delivery Notes Label)', 'hint' => 'ស្លាក "ចំណាំបន្ថែម (Delivery Notes)"', 'type' => 'input'],
                            'checkout_note_ph' => ['title' => 'កំណត់សម្គាល់បន្ថែម: គំរូ (Delivery Notes Placeholder)', 'hint' => 'គំរូ "បញ្ជាក់ព័ត៌មានបន្ថែមសម្រាប់ការដឹកជញ្ជូន (បើមាន)"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ប្រអប់ទូទាត់ប្រាក់ & KHQR (Bakong KHQR Payment & Scan Guide)',
                        'title_en' => 'Bakong KHQR Scan Instructions & Amount Note',
                        'icon' => 'fa-qrcode',
                        'desc' => 'ការណែនាំស្កេន KHQR និងទឹកប្រាក់ត្រូវទូទាត់',
                        'keys' => [
                            'checkout_box_payment' => ['title' => 'ចំណងជើងប្រអប់ KHQR (KHQR Box Heading)', 'hint' => 'ចំណងជើង "ទូទាត់តាមប្រព័ន្ធ Bakong KHQR"', 'type' => 'input'],
                            'checkout_qr_scan_note' => ['title' => 'ការណែនាំស្កេន QR (QR Scan Instructions Note)', 'hint' => 'ចំណាំក្រោម QR Code: "ស្កេន QR កូដខាងលើជាមួយ App ធនាគារណាមួយក៏បាន..."', 'type' => 'textarea'],
                            'checkout_amount_to_pay' => ['title' => 'អត្ថបទទឹកប្រាក់ត្រូវទូទាត់ (Amount to Pay Note)', 'hint' => 'អត្ថបទ "ទឹកប្រាក់ត្រូវទូទាត់:"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ការភ្ជាប់រូបភាពវិក្កយបត្រ (Payment Receipt Upload)',
                        'title_en' => 'Payment Receipt Dropzone & Allowed Formats',
                        'icon' => 'fa-receipt',
                        'desc' => 'ប្រអប់អូសទម្លាក់រូបវិក្កយបត្រ និងទម្រង់ដែលអនុញ្ញាត',
                        'keys' => [
                            'checkout_box_receipt' => ['title' => 'ចំណងជើងប្រអប់វិក្កយបត្រ (Receipt Box Title)', 'hint' => 'ចំណងជើង "ភ្ជាប់រូបភាពវិក្កយបត្រ (Payment Receipt) *"', 'type' => 'input'],
                            'checkout_receipt_drop_text' => ['title' => 'អត្ថបទប្រអប់ទម្លាក់រូប (Receipt Dropzone Text)', 'hint' => 'ការណែនាំ "ចុចទីនេះដើម្បីជ្រើសរើសរូបវិក្កយបត្រ ឬអូសទម្លាក់រូបចូល"', 'type' => 'textarea'],
                            'checkout_receipt_formats' => ['title' => 'ទម្រង់ដែលអនុញ្ញាត (Allowed Formats Note)', 'hint' => 'កំណត់សម្គាល់ "JPG, PNG, WEBP (អតិបរមា 10MB)"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'សង្ខេបការបញ្ជាទិញ & គូប៉ុង (Order Summary, Coupon & Totals)',
                        'title_en' => 'Order Summary, Coupon Input & Grand Total',
                        'icon' => 'fa-calculator',
                        'desc' => 'សង្ខេបទំនិញក្នុងកន្ត្រក ប្រអប់គូប៉ុងបញ្ចុះតម្លៃ ថ្លៃដឹក និងប៊ូតុងបញ្ជាក់',
                        'keys' => [
                            'checkout_box_summary' => ['title' => 'ចំណងជើងប្រអប់សង្ខេប (Summary Box Title)', 'hint' => 'ចំណងជើង "សង្ខេបការបញ្ជាទិញ"', 'type' => 'input'],
                            'checkout_coupon_label' => ['title' => 'ប្រអប់គូប៉ុង: ស្លាក (Coupon Label)', 'hint' => 'ស្លាក "លេខកូដបញ្ចុះតម្លៃ (Coupon Code)"', 'type' => 'input'],
                            'checkout_coupon_ph' => ['title' => 'ប្រអប់គូប៉ុង: គំរូ (Coupon Placeholder)', 'hint' => 'គំរូ "បញ្ចូលកូដបញ្ចុះតម្លៃ..."', 'type' => 'input'],
                            'checkout_coupon_btn' => ['title' => 'ប្រអប់គូប៉ុង: ប៊ូតុង (Coupon Button)', 'hint' => 'ប៊ូតុង "អនុវត្ត"', 'type' => 'input'],
                            'checkout_subtotal_label' => ['title' => 'ស្លាកតម្លៃសរុប (Subtotal Label)', 'hint' => 'ស្លាក "តម្លៃទំនិញសរុប (Subtotal):"', 'type' => 'input'],
                            'checkout_discount_label' => ['title' => 'ស្លាកការបញ្ចុះតម្លៃ (Discount Label)', 'hint' => 'ស្លាក "ការបញ្ចុះតម្លៃ (Discount):"', 'type' => 'input'],
                            'checkout_shipping_label' => ['title' => 'ស្លាកថ្លៃដឹកជញ្ជូន (Shipping Label)', 'hint' => 'ស្លាក "ថ្លៃសេវាដឹកជញ្ជូន (Shipping):"', 'type' => 'input'],
                            'checkout_shipping_free' => ['title' => 'អត្ថបទដឹកជញ្ជូនឥតគិតថ្លៃ (Free Shipping Note)', 'hint' => 'អត្ថបទ "ឥតគិតថ្លៃ (Free)"', 'type' => 'input'],
                            'checkout_total_label' => ['title' => 'ស្លាកទឹកប្រាក់សរុប (Grand Total Label)', 'hint' => 'ស្លាក "ទឹកប្រាក់សរុប (Total):"', 'type' => 'input'],
                            'checkout_btn_submit' => ['title' => 'ប៊ូតុងបញ្ជាក់ការបញ្ជាទិញ (Place Order Button)', 'hint' => 'ប៊ូតុងធំ "បញ្ជាក់ការបញ្ជាទិញ (Place Order)"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ការបញ្ជាក់ជោគជ័យ & ការធានា (Order Success Modal)',
                        'title_en' => 'Success Confirmation Modal & Trust Badges',
                        'icon' => 'fa-circle-check',
                        'desc' => 'ផ្ទាំង Modal លេចឡើងពេលបញ្ជាទិញជោគជ័យ និងផ្លាកសញ្ញាធានា',
                        'keys' => [
                            'checkout_trust_security' => ['title' => 'ផ្លាកធានា ១ (Security Trust Badge)', 'hint' => 'ស្លាក "100% សុវត្ថិភាព"', 'type' => 'input'],
                            'checkout_trust_quality' => ['title' => 'ផ្លាកធានា ២ (Quality Trust Badge)', 'hint' => 'ស្លាក "ធានាគុណភាព"', 'type' => 'input'],
                            'checkout_success_title' => ['title' => 'ចំណងជើងជោគជ័យ (Order Success Title)', 'hint' => 'ចំណងជើង "ការបញ្ជាទិញជោគជ័យ!"', 'type' => 'input'],
                            'checkout_success_desc' => ['title' => 'ការពន្យល់ជោគជ័យ (Order Success Note)', 'hint' => 'កំណត់សម្គាល់ "ព័ត៌មាន និងវិក្កយបត្ររបស់អ្នកត្រូវបានផ្ញើទៅកាន់ Telegram Admin..."', 'type' => 'textarea'],
                            'checkout_order_ref_label' => ['title' => 'ស្លាកលេខកូដបញ្ជាទិញ (Order Ref Label)', 'hint' => 'ស្លាក "លេខកូដបញ្ជាទិញ:"', 'type' => 'input'],
                            'checkout_btn_continue' => ['title' => 'ប៊ូតុងបន្តទិញទំនិញ (Continue Shopping Button)', 'hint' => 'ប៊ូតុង "ត្រឡប់ទៅកាន់ទំព័រដើម (Continue Shopping)"', 'type' => 'input'],
                        ]
                    ]
                ]
            ],
            'step5_products' => [
                'step_num' => 5,
                'id' => 'step5_products',
                'icon' => 'fa-bag-shopping',
                'title_km' => 'ផលិតផល & កន្ត្រក',
                'title_en' => 'Products & Cart Drawer',
                'badge' => 'ផលិតផល & កន្ត្រក (Step 5)',
                'desc' => 'គ្រប់គ្រងផ្លាកសញ្ញាលើកាតផលិតផល (In Stock, SALE បញ្ចុះតម្លៃ, ធានាគុណភាព 100%), ផ្ទាំង Quick View Modal, និងផ្ទាំងកន្ត្រកទំនិញ (Cart Drawer)។',
                'preview_url' => '../home/index.php#catalog-scroll',
                'sections' => [
                    [
                        'title_km' => 'ផ្លាកសញ្ញាលើកាតផលិតផល (Product Card Badges & Buttons)',
                        'title_en' => 'Card Badges, Savings Note & Action Buttons',
                        'icon' => 'fa-tags',
                        'desc' => 'បង្ហាញលើកាតផលិតផលនីមួយៗក្នុងទំព័រដើម',
                        'keys' => [
                            'badge_in_stock' => ['title' => 'ផ្លាកសញ្ញាមានស្តុក (In Stock Badge)', 'hint' => 'ស្លាកពណ៌បៃតង "មានស្តុក (50)"', 'type' => 'input'],
                            'badge_discount' => ['title' => 'ផ្លាកសញ្ញាបញ្ចុះតម្លៃ (SALE Badge)', 'hint' => 'ស្លាកពណ៌ក្រហម "បញ្ចុះតម្លៃ" / "SALE"', 'type' => 'input'],
                            'discount_save_text' => ['title' => 'កំណត់សម្គាល់សន្សំបាន (Save Note)', 'hint' => 'អត្ថបទ "សន្សំបាន $X" / "Save $X"', 'type' => 'input'],
                            'badge_guarantee' => ['title' => 'កំណត់សម្គាល់ធានាគុណភាព (Guarantee Note)', 'hint' => 'អត្ថបទ "ធានាគុណភាព 100%" លើកាត', 'type' => 'input'],
                            'price_per_unit' => ['title' => 'ឯកតាតម្លៃ (Price Per Unit)', 'hint' => 'អត្ថបទ "/ មួយ" ឬ "/ item" បន្ទាប់ពីតម្លៃ', 'type' => 'input'],
                            'btn_quickview' => ['title' => 'Tooltip ប៊ូតុងពិនិត្យរហ័ស (Quick View Tooltip)', 'hint' => 'អត្ថបទ "មើលព័ត៌មានលម្អិត"', 'type' => 'input'],
                            'btn_add_cart' => ['title' => 'ប៊ូតុងកន្ត្រក (Add to Cart Button)', 'hint' => 'អត្ថបទលើប៊ូតុងកន្ត្រក "កន្ត្រក"', 'type' => 'input'],
                            'btn_buy_now' => ['title' => 'ប៊ូតុងទិញភ្លាម (Buy Now Button)', 'hint' => 'អត្ថបទលើប៊ូតុងទិញភ្លាម "ទិញភ្លាម"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ផ្ទាំងពិនិត្យរហ័ស & សារដំណឹង (Quick View Modal & Toast)',
                        'title_en' => 'Quick View Specs, Stock Status & Toast Alerts',
                        'icon' => 'fa-eye',
                        'desc' => 'ផ្ទាំង Modal មើលព័ត៌មានលម្អិត និងសារលោតពេលដាក់ទំនិញចូលកន្ត្រក',
                        'keys' => [
                            'qv_stock_notice' => ['title' => 'ស្ថានភាពស្តុក (QV Stock Notice)', 'hint' => 'អត្ថបទ "មានក្នុងស្តុក"', 'type' => 'input'],
                            'qv_qty_label' => ['title' => 'ស្លាកចំនួន (QV Quantity Label)', 'hint' => 'ស្លាក "ចំនួន:"', 'type' => 'input'],
                            'qv_btn_add_cart' => ['title' => 'ប៊ូតុងដាក់កន្ត្រក (QV Add to Cart Button)', 'hint' => 'ប៊ូតុង "ដាក់ក្នុងកន្ត្រក"', 'type' => 'input'],
                            'qv_btn_buy_now' => ['title' => 'ប៊ូតុងទិញឥឡូវ (QV Buy Now Button)', 'hint' => 'ប៊ូតុង "ទិញឥឡូវ"', 'type' => 'input'],
                            'toast_cart_added' => ['title' => 'សារដាក់កន្ត្រកជោគជ័យ (Cart Added Toast)', 'hint' => 'សារលោត "បានដាក់ចូលកន្ត្រកជោគជ័យ!"', 'type' => 'input'],
                            'toast_cart_empty' => ['title' => 'សារកន្ត្រកទទេ (Cart Empty Warning Toast)', 'hint' => 'សារលោត "កន្ត្រករបស់អ្នកទទេ! សូមជ្រើសរើសទំនិញជាមុនសិន"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ផ្ទាំងកន្ត្រកទំនិញ (Cart Drawer)',
                        'title_en' => 'Cart Drawer Labels & Shipping Guarantee Note',
                        'icon' => 'fa-cart-shopping',
                        'desc' => 'ផ្ទាំងកន្ត្រករុញចេញពីស្តាំដៃ និងកំណត់សម្គាល់ធានាដឹកជញ្ជូន',
                        'keys' => [
                            'cart_drawer_title' => ['title' => 'ចំណងជើងកន្ត្រក (Cart Drawer Title)', 'hint' => 'ចំណងជើង "កន្ត្រកទំនិញរបស់អ្នក"', 'type' => 'input'],
                            'cart_empty_text' => ['title' => 'អត្ថបទកន្ត្រកទទេ (Cart Empty Description)', 'hint' => 'អត្ថបទ "កន្ត្រករបស់អ្នកមិនទាន់មានទំនិញនៅឡើយទេ"', 'type' => 'input'],
                            'cart_subtotal_label' => ['title' => 'ស្លាកតម្លៃសរុប (Cart Subtotal Label)', 'hint' => 'ស្លាក "តម្លៃសរុប (Subtotal):"', 'type' => 'input'],
                            'cart_btn_checkout' => ['title' => 'ប៊ូតុងបន្តទៅទូទាត់ (Checkout Button)', 'hint' => 'ប៊ូតុង "បន្តទៅកាន់ការទូទាត់ (Proceed to Checkout)"', 'type' => 'input'],
                            'cart_btn_shop_now' => ['title' => 'ប៊ូតុងទៅទិញទំនិញ (Shop Now Button)', 'hint' => 'ប៊ូតុងពេលកន្ត្រកទទេ "ទៅកាន់កាតាឡុកទំនិញ"', 'type' => 'input'],
                            'cart_shipping_badge' => ['title' => 'កំណត់សម្គាល់ធានាដឹកជញ្ជូន (Shipping Guarantee Note)', 'hint' => 'ចំណាំបាតកន្ត្រក "គាំទ្រការដឹកជញ្ជូនរហ័ស និងទូទាត់តាម KHQR"', 'type' => 'input'],
                        ]
                    ]
                ]
            ],
            'step6_auth' => [
                'step_num' => 6,
                'id' => 'step6_auth',
                'icon' => 'fa-user-shield',
                'title_km' => 'គណនី & ប្រវត្តិរូប',
                'title_en' => 'Auth & Customer Profile',
                'badge' => 'គណនី (Step 6)',
                'desc' => 'គ្រប់គ្រងខ្លឹមសារផ្ទាំង Login & Register, ប៊ូតុង Google, ទម្រង់បំពេញព័ត៌មាន, និងផ្ទាំងប្រវត្តិរូបអតិថិជន (Profile, Addresses, Orders & Password)។',
                'preview_url' => '../home/profile.php',
                'sections' => [
                    [
                        'title_km' => 'ផ្ទាំងចូលគណនី & ចុះឈ្មោះ (Sign In & Registration)',
                        'title_en' => 'Login & Register Titles, Google Button & Form Inputs',
                        'icon' => 'fa-right-to-bracket',
                        'desc' => 'ទម្រង់ផ្ទាំងចូលប្រើប្រាស់គណនី និងចុះឈ្មោះថ្មី',
                        'keys' => [
                            'auth_login_title' => ['title' => 'ចំណងជើងផ្ទាំង Login (Login Title)', 'hint' => 'ចំណងជើង "ចូលប្រើប្រាស់គណនី"', 'type' => 'input'],
                            'auth_login_subtitle' => ['title' => 'កថាខណ្ឌពន្យល់ Login (Login Subtitle Note)', 'hint' => 'កំណត់សម្គាល់ "ចូលគណនីរបស់អ្នកដើម្បីពិនិត្យការបញ្ជាទិញ..."', 'type' => 'textarea'],
                            'auth_register_title' => ['title' => 'ចំណងជើងផ្ទាំងចុះឈ្មោះ (Register Title)', 'hint' => 'ចំណងជើង "បង្កើតគណនីថ្មី"', 'type' => 'input'],
                            'auth_register_subtitle' => ['title' => 'កថាខណ្ឌពន្យល់ចុះឈ្មោះ (Register Subtitle Note)', 'hint' => 'កំណត់សម្គាល់ "ចុះឈ្មោះដើម្បីទទួលបានបទពិសោធន៍ទិញទំនិញ..."', 'type' => 'textarea'],
                            'auth_google_btn' => ['title' => 'ប៊ូតុង Google (Google Sign-In Button)', 'hint' => 'ប៊ូតុង "ចូលគណនីជាមួយ Google"', 'type' => 'input'],
                            'auth_or_divider' => ['title' => 'បន្ទាត់ខណ្ឌ (Divider Note)', 'hint' => 'អត្ថបទ "ឬប្រើអ៊ីម៉ែល" / "or with email"', 'type' => 'input'],
                            'auth_name_label' => ['title' => 'ប្រអប់ឈ្មោះ: ស្លាក (Name Label)', 'hint' => 'ស្លាក "ឈ្មោះពេញរបស់អ្នក *"', 'type' => 'input'],
                            'auth_name_ph' => ['title' => 'ប្រអប់ឈ្មោះ: គំរូ (Name Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: សុខា"', 'type' => 'input'],
                            'auth_email_label' => ['title' => 'ប្រអប់អ៊ីម៉ែល: ស្លាក (Email Label)', 'hint' => 'ស្លាក "អាសយដ្ឋានអ៊ីម៉ែល (Email / Gmail) *"', 'type' => 'input'],
                            'auth_email_ph' => ['title' => 'ប្រអប់អ៊ីម៉ែល: គំរូ (Email Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: name@gmail.com"', 'type' => 'input'],
                            'auth_phone_label' => ['title' => 'ប្រអប់លេខទូរស័ព្ទ: ស្លាក (Phone Label)', 'hint' => 'ស្លាក "លេខទូរស័ព្ទ / Telegram"', 'type' => 'input'],
                            'auth_phone_ph' => ['title' => 'ប្រអប់លេខទូរស័ព្ទ: គំរូ (Phone Placeholder)', 'hint' => 'គំរូ "ឧទាហរណ៍: 012 345 678"', 'type' => 'input'],
                            'auth_pass_label' => ['title' => 'ប្រអប់ពាក្យសម្ងាត់: ស្លាក (Password Label)', 'hint' => 'ស្លាក "ពាក្យសម្ងាត់ (Password) *"', 'type' => 'input'],
                            'auth_pass_ph' => ['title' => 'ប្រអប់ពាក្យសម្ងាត់: គំរូ (Password Placeholder Note)', 'hint' => 'កំណត់សម្គាល់ "យ៉ាងហោចណាស់ ៦ តួអក្សរ"', 'type' => 'input'],
                            'auth_remember_me' => ['title' => 'ចងចាំគណនី (Remember Me Checkbox)', 'hint' => 'ស្លាក "ចងចាំគណនីខ្ញុំ"', 'type' => 'input'],
                            'auth_btn_login' => ['title' => 'ប៊ូតុង Login (Sign In Button)', 'hint' => 'ប៊ូតុង "ចូលគណនី"', 'type' => 'input'],
                            'auth_btn_register' => ['title' => 'ប៊ូតុងចុះឈ្មោះ (Register Button)', 'hint' => 'ប៊ូតុង "បង្កើតគណនី"', 'type' => 'input'],
                            'auth_no_account' => ['title' => 'សំណួរគ្មានគណនី (No Account Prompt)', 'hint' => 'សំណួរ "មិនទាន់មានគណនីមែនទេ?"', 'type' => 'input'],
                            'auth_have_account' => ['title' => 'សំណួរមានគណនី (Have Account Prompt)', 'hint' => 'សំណួរ "មានគណនីរួចហើយ?"', 'type' => 'input'],
                            'auth_switch_register' => ['title' => 'តំណទៅចុះឈ្មោះ (Register Link)', 'hint' => 'តំណ "ចុះឈ្មោះឥឡូវនេះ"', 'type' => 'input'],
                            'auth_switch_login' => ['title' => 'តំណទៅ Login (Sign In Link)', 'hint' => 'តំណ "ចូលគណនីឥឡូវនេះ"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'ផ្ទាំងប្រវត្តិរូប & ការបញ្ជាទិញ (Customer Profile & Orders)',
                        'title_en' => 'Profile Tabs, Saved Address & Order History Statuses',
                        'icon' => 'fa-id-card',
                        'desc' => 'ទំព័រគ្រប់គ្រងគណនីផ្ទាល់ខ្លួនរបស់អតិថិជន និងប្រវត្តិទិញទំនិញ',
                        'keys' => [
                            'profile_title' => ['title' => 'ចំណងជើងទំព័រ (Profile Page Title)', 'hint' => 'ចំណងជើង "គណនី និងប្រវត្តិបញ្ជាទិញ"', 'type' => 'input'],
                            'profile_tab_info' => ['title' => 'Tab ព័ត៌មានផ្ទាល់ខ្លួន (Personal Info Tab)', 'hint' => 'ឈ្មោះ Tab "ព័ត៌មានផ្ទាល់ខ្លួន"', 'type' => 'input'],
                            'profile_tab_orders' => ['title' => 'Tab ប្រវត្តិបញ្ជាទិញ (Order History Tab)', 'hint' => 'ឈ្មោះ Tab "ប្រវត្តិបញ្ជាទិញ"', 'type' => 'input'],
                            'profile_tab_security' => ['title' => 'Tab សុវត្ថិភាព (Security Tab)', 'hint' => 'ឈ្មោះ Tab "សុវត្ថិភាព & ពាក្យសម្ងាត់"', 'type' => 'input'],
                            'profile_address_label' => ['title' => 'អាសយដ្ឋានលំនាំដើម: ស្លាក (Address Label)', 'hint' => 'ស្លាក "អាសយដ្ឋានដឹកជញ្ជូនលំនាំដើម"', 'type' => 'input'],
                            'profile_address_ph' => ['title' => 'អាសយដ្ឋានលំនាំដើម: គំរូ (Address Placeholder)', 'hint' => 'គំរូ "ផ្ទះលេខ, ផ្លូវ, សង្កាត់, ខណ្ឌ..."', 'type' => 'textarea'],
                            'profile_btn_save' => ['title' => 'ប៊ូតុងរក្សាទុកព័ត៌មាន (Save Profile Button)', 'hint' => 'ប៊ូតុង "រក្សាទុកព័ត៌មាន"', 'type' => 'input'],
                            'profile_no_orders' => ['title' => 'អត្ថបទពេលគ្មានការបញ្ជាទិញ (No Orders Note)', 'hint' => 'កំណត់សម្គាល់ "លោកអ្នកមិនទាន់មានការបញ្ជាទិញនៅឡើយទេ"', 'type' => 'input'],
                            'profile_order_ref' => ['title' => 'ក្បាលតារាង: លេខកូដបញ្ជាទិញ (Order Ref Column)', 'hint' => 'ក្បាលតារាង "លេខកូដបញ្ជាទិញ"', 'type' => 'input'],
                            'profile_order_date' => ['title' => 'ក្បាលតារាង: កាលបរិច្ឆេទ (Date Column)', 'hint' => 'ក្បាលតារាង "កាលបរិច្ឆេទ"', 'type' => 'input'],
                            'profile_order_total' => ['title' => 'ក្បាលតារាង: ទឹកប្រាក់សរុប (Total Column)', 'hint' => 'ក្បាលតារាង "ទឹកប្រាក់សរុប"', 'type' => 'input'],
                            'profile_order_status' => ['title' => 'ក្បាលតារាង: ស្ថានភាព (Status Column)', 'hint' => 'ក្បាលតារាង "ស្ថានភាព"', 'type' => 'input'],
                            'profile_receipt_view' => ['title' => 'តំណមើលវិក្កយបត្រ (View Receipt Link)', 'hint' => 'តំណ "មើលវិក្កយបត្រ"', 'type' => 'input'],
                            'profile_btn_shop_now' => ['title' => 'ប៊ូតុងទៅទិញទំនិញ (Start Shopping Button)', 'hint' => 'ប៊ូតុង "ទៅកាន់កាតាឡុកទំនិញ"', 'type' => 'input'],
                            'profile_change_pass' => ['title' => 'ចំណងជើងប្តូរពាក្យសម្ងាត់ (Change Password Title)', 'hint' => 'ចំណងជើង "ផ្លាស់ប្តូរពាក្យសម្ងាត់"', 'type' => 'input'],
                            'profile_old_pass' => ['title' => 'ពាក្យសម្ងាត់បច្ចុប្បន្ន (Current Password Label)', 'hint' => 'ស្លាក "ពាក្យសម្ងាត់បច្ចុប្បន្ន"', 'type' => 'input'],
                            'profile_new_pass' => ['title' => 'ពាក្យសម្ងាត់ថ្មី (New Password Label)', 'hint' => 'ស្លាក "ពាក្យសម្ងាត់ថ្មី"', 'type' => 'input'],
                        ]
                    ]
                ]
            ],
            'step7_nav' => [
                'step_num' => 7,
                'id' => 'step7_nav',
                'icon' => 'fa-compass',
                'title_km' => 'ការរុករក & បាតទំព័រ',
                'title_en' => 'Navigation & Footer',
                'badge' => 'រុករក & Footer (Step 7)',
                'desc' => 'គ្រប់គ្រងម៉ឺនុយរុករកខាងលើ (Navbar Links, Brand Tagline), Dropdown គណនី និងបាតទំព័រ (Footer Payment Partners & Copyright)។',
                'preview_url' => '../home/index.php',
                'sections' => [
                    [
                        'title_km' => 'ម៉ឺនុយរុករកខាងលើ (Top Navigation Bar & Account Dropdown)',
                        'title_en' => 'Header Nav Links, Brand Tagline & User Menu',
                        'icon' => 'fa-bars',
                        'desc' => 'បង្ហាញនៅរបាររុករកខាងលើគេនៃគ្រប់ទំព័រ (Global Navbar)',
                        'keys' => [
                            'nav_brand_tagline' => ['title' => 'ពាក្យស្លោកក្រោម Logo (Brand Tagline)', 'hint' => 'អត្ថបទក្រោម Logo "ហាងសម្លៀកបំពាក់ និងម៉ូដ"', 'type' => 'input'],
                            'nav_home' => ['title' => 'តំណ: ទំព័រដើម (Home Nav Link)', 'hint' => 'តំណភ្ជាប់ "ទំព័រដើម"', 'type' => 'input'],
                            'nav_about' => ['title' => 'តំណ: អំពីយើង (About Us Nav Link)', 'hint' => 'តំណភ្ជាប់ "អំពីយើង"', 'type' => 'input'],
                            'nav_support' => ['title' => 'តំណ: ជំនួយការ (Support Nav Link)', 'hint' => 'តំណភ្ជាប់ "ជំនួយការ"', 'type' => 'input'],
                            'nav_payments' => ['title' => 'តំណ: ការទូទាត់ (Payments Nav Link)', 'hint' => 'តំណភ្ជាប់ "ការទូទាត់"', 'type' => 'input'],
                            'nav_cart' => ['title' => 'ប៊ូតុងកន្ត្រក Navbar (Cart Nav Button)', 'hint' => 'អត្ថបទលើប៊ូតុង "កន្ត្រក"', 'type' => 'input'],
                            'nav_telegram' => ['title' => 'ប៊ូតុង Telegram Navbar (Telegram Nav Button)', 'hint' => 'អត្ថបទលើប៊ូតុង "Telegram"', 'type' => 'input'],
                            'nav_login' => ['title' => 'ប៊ូតុងចូលគណនី (Sign In Nav Button)', 'hint' => 'ប៊ូតុង "ចូលគណនី"', 'type' => 'input'],
                            'nav_register' => ['title' => 'ប៊ូតុងចុះឈ្មោះ (Register Nav Button)', 'hint' => 'ប៊ូតុង "ចុះឈ្មោះ"', 'type' => 'input'],
                            'nav_my_profile' => ['title' => 'Dropdown: គណនីរបស់ខ្ញុំ (My Profile Link)', 'hint' => 'តំណក្នុង Dropdown "គណនីរបស់ខ្ញុំ"', 'type' => 'input'],
                            'nav_my_orders' => ['title' => 'Dropdown: ប្រវត្តិបញ្ជាទិញ (My Orders Link)', 'hint' => 'តំណក្នុង Dropdown "ប្រវត្តិបញ្ជាទិញ"', 'type' => 'input'],
                            'nav_logout' => ['title' => 'Dropdown: ចាកចេញ (Logout Link)', 'hint' => 'តំណក្នុង Dropdown "ចាកចេញ"', 'type' => 'input'],
                        ]
                    ],
                    [
                        'title_km' => 'បាតទំព័រ & ដៃគូទូទាត់ (Footer & Payment Partners)',
                        'title_en' => 'Footer Banking Partners & Copyright Notice',
                        'icon' => 'fa-shoe-prints',
                        'desc' => 'បង្ហាញនៅបាតទំព័រខាងក្រោមគេបង្អស់នៃគ្រប់ទំព័រ (Global Footer)',
                        'keys' => [
                            'footer_payments_title' => ['title' => 'ចំណងជើងដៃគូទូទាត់ (Footer Payments Title)', 'hint' => 'ចំណងជើង "វិធីទូទាត់ប្រាក់ និងដៃគូធនាគារ"', 'type' => 'input'],
                            'footer_payments_desc' => ['title' => 'កថាខណ្ឌពន្យល់ដៃគូទូទាត់ (Payments Subtitle)', 'hint' => 'កំណត់សម្គាល់ "ទទួលការទូទាត់យ៉ាងរហ័សតាមរយៈប្រព័ន្ធ KHQR..."', 'type' => 'textarea'],
                            'footer_copyright' => ['title' => 'កំណត់សម្គាល់រក្សាសិទ្ធិ (Copyright Notice)', 'hint' => 'អត្ថបទ "រក្សាសិទ្ធិគ្រប់យ៉ាង" / "All rights reserved."', 'type' => 'input'],
                        ]
                    ]
                ]
            ]
        ];
    }
}

