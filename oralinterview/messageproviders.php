<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'assigned' => [
        'capability' => 'mod/oralinterview:evaluate',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED,
            'email' => MESSAGE_PERMITTED,
            'site' => MESSAGE_PERMITTED
        ]
    ],
    'deadline_reminder' => [
        'capability' => 'mod/oralinterview:evaluate',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED,
            'email' => MESSAGE_PERMITTED,
            'site' => MESSAGE_PERMITTED
        ]
    ]
];
