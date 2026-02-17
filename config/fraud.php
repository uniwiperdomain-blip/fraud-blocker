<?php

return [
    'enabled' => env('FRAUD_DETECTION_ENABLED', true),

    // Default thresholds (used when no FraudSetting exists for a tenant)
    'defaults' => [
        'block_threshold' => 100,
        'score_window_hours' => 24,
        'rapid_clicks_points' => 30,
        'rapid_clicks_count' => 3,
        'rapid_clicks_window_seconds' => 60,
        'bot_detection_points' => 50,
        'low_engagement_points' => 20,
        'low_engagement_min_time_seconds' => 2,
        'low_engagement_min_scroll_depth' => 1,
        'datacenter_ip_points' => 40,
    ],

    // IP intelligence service
    'ip_intelligence' => [
        'provider' => env('IP_INTELLIGENCE_PROVIDER', 'ipinfo'), // 'ipinfo', 'none'
        'cache_hours' => 24,
    ],

    // Fraud log retention (days)
    'log_retention_days' => 90,

    // Known bot user agent patterns
    'bot_patterns' => [
        'HeadlessChrome',
        'PhantomJS',
        'Selenium',
        'puppeteer',
        'SlimerJS',
        'CasperJS',
        'Nightmare',
        'Playwright',
        'Headless',
        'python-requests',
        'Go-http-client',
        'Java/',
        'libwww-perl',
        'wget',
        'curl/',
        'HTTrack',
        'scrapy',
    ],

    // Legitimate bot patterns to IGNORE (don't score these as fraud)
    'legitimate_bots' => [
        'Googlebot',
        'bingbot',
        'Baiduspider',
        'YandexBot',
        'DuckDuckBot',
        'facebookexternalhit',
        'Twitterbot',
        'LinkedInBot',
        'Slackbot',
        'WhatsApp',
        'Applebot',
    ],
];
