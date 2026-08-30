<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Salon Timezone
    |--------------------------------------------------------------------------
    |
    | All datetimes are STORED in UTC (see config/app.php). This is the timezone
    | the salon physically operates in, and the only timezone that opening hours,
    | staff schedules, and availability slots should ever be interpreted in.
    |
    | Changing this value changes how stored UTC datetimes are presented and how
    | wall-clock schedule rules are resolved. It does not rewrite stored data.
    |
    */

    'timezone' => env('SALON_TIMEZONE', 'Asia/Manila'),

    /*
    |--------------------------------------------------------------------------
    | Booking Rule Defaults
    |--------------------------------------------------------------------------
    |
    | Seed values for the single booking_rules row. Once that row exists it is
    | authoritative and admin-editable; these defaults are only used to create it.
    |
    */

    'booking_rule_defaults' => [
        'min_advance_minutes' => 60,
        'max_advance_days' => 60,
        'cancellation_deadline_hours' => 24,
        'reschedule_deadline_hours' => 24,
        'buffer_minutes' => 10,
        'slot_interval_minutes' => 15,
        'max_duration_minutes' => 480,
    ],

];
