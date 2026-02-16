<?php
defined('MOODLE_INTERNAL') || die();

// Settings temporarily disabled to resolve conflicts
if ($hassiteconfig && false) { // Disabled temporarily
    global $ADMIN;
    $settings = new admin_settingpage('mod_oralinterview_settings_2024', get_string('pluginadministration', 'oralinterview'));
    $settings->add(new admin_setting_configtext(
        'oralinterview/retentiondays',
        get_string('setting_retentiondays', 'oralinterview'),
        get_string('setting_retentiondays_desc', 'oralinterview'),
        365,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'oralinterview/messagingreminder',
        get_string('setting_messagingreminder', 'oralinterview'),
        get_string('setting_messagingreminder_desc', 'oralinterview'),
        2,
        PARAM_INT
    ));
    $ADMIN->add('modsettings', $settings);
}
