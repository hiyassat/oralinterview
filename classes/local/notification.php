<?php
namespace mod_oralinterview\local;

use core_user;
use core_message\message;

defined('MOODLE_INTERNAL') || die();

class notification {
    protected static function create_message(int $userid, int $cmid, string $name, string $subject, string $fullmessage, string $shortmessage) {
        $context = \context_module::instance($cmid);
        $message = new message();
        $message->component = 'mod_oralinterview';
        $message->name = $name;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $userid;
        $message->subject = $subject;
        $message->fullmessage = $fullmessage;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml = format_text($fullmessage, FORMAT_MARKDOWN);
        $message->smallmessage = $shortmessage;
        $message->contexturl = new \moodle_url('/mod/oralinterview/my.php', ['id' => $cmid]);
        $message->contexturlname = get_string('myinterviews', 'oralinterview');
        $message->courseid = $context->get_course_context()->instanceid;
        $message->context = $context;
        return $message;
    }

    public static function send_assignment(int $sessionid, int $userid) {
        global $DB;
        $session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);
        $subject = get_string('message_subject_assigned', 'oralinterview');
        $body = get_string('message_body_assigned', 'oralinterview', (object)[
            'sessionid' => $session->id,
            'deadline' => userdate($session->deadline)
        ]);
        $message = self::create_message($userid, $session->oralinterviewid, 'assigned', $subject, $body, $subject);
        message_send($message);
    }

    public static function send_deadline_reminder(int $sessionid, int $userid) {
        global $DB;
        $session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);
        $subject = get_string('message_subject_reminder', 'oralinterview');
        $body = get_string('message_body_reminder', 'oralinterview', (object)[
            'sessionid' => $session->id,
            'deadline' => userdate($session->deadline)
        ]);
        $message = self::create_message($userid, $session->oralinterviewid, 'deadline_reminder', $subject, $body, $subject);
        message_send($message);
    }
}
