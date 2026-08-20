<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ticket Timers Configuration
    |--------------------------------------------------------------------------
    */

    'timers' => [
        'reminder_to_agents' => (int) env('BOT_REMINDER_TIMEOUT', 60),
        'auto_close_ticket' => (int) env('BOT_AUTO_CLOSE_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Chat Configuration
    |--------------------------------------------------------------------------
    */

    'service_chat' => [
        'chat_id' => env('TELEGRAM_SERVICE_CHAT_ID'),
        'topic_colors' => [
            'new' => 0x6FB9F0,
            'answered' => 0x7EE0A3,
            'pending' => 0xFFD67E,
            'closed' => 0xCB86DB,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Whitelist Configuration
    |--------------------------------------------------------------------------
    */

    'agents' => [
        // Enable whitelist checking
        'whitelist_enabled' => env('BOT_AGENT_WHITELIST_ENABLED', false),

        // List of allowed agent telegram IDs
        'whitelist' => array_filter(
            explode(',', env('BOT_AGENT_WHITELIST', '')),
            fn ($id) => ! empty($id)
        ),

        // Auto-promote users in service chat to agents
        'auto_promote_in_service_chat' => env('BOT_AUTO_PROMOTE_AGENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Employee List Configuration
    |--------------------------------------------------------------------------
    */

    'employees' => array_map(
        'intval',
        array_filter(
            explode(',', env('BOT_EMPLOYEE_LIST', '')),
            fn ($id) => ! empty($id)
        )
    ),

    /*
    |--------------------------------------------------------------------------
    | Rating Configuration
    |--------------------------------------------------------------------------
    */

    'rating' => [
        'enabled' => true,
        'min_stars' => 1,
        'max_stars' => 5,
        'allow_comments' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reaction for messages forwarded to client
    |--------------------------------------------------------------------------
    */

    'forwarded_to_client_reaction' => env('FORWARDED_TO_CLIENT_REACTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => true,
        'log_messages' => true,
        'log_commands' => true,
        'log_callbacks' => true,
        'log_agent_actions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Work Schedule Configuration
    |--------------------------------------------------------------------------
    */

    'work_schedule' => [
        'timezone' => env('APP_TIMEZONE', 'UTC'),
    ],
];
