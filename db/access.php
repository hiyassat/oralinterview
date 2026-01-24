<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'mod/oralinterview:addinstance' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:managecandidates' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:evaluate' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW]
    ],
    'mod/oralinterview:viewown' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW]
    ],
    'mod/oralinterview:viewall' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:export' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:lock' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:recalculate' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:override' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
    'mod/oralinterview:reopen' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW]
    ],
];
