<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'mod_oralinterview\\task\\reminder',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '*/6',
        'day' => '*',
        'dayofmonth' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ],
    [
        'classname' => 'mod_oralinterview\\task\\retention',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '*/4',
        'day' => '*',
        'dayofmonth' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ]
];
