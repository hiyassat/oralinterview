<?php
namespace mod_oralinterview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements metadata_provider {
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table('oralint_eval', [
            'sessionid' => 'privacy:metadata:oralint_eval:sessionid',
            'userid' => 'privacy:metadata:oralint_eval:userid',
            'status' => 'privacy:metadata:oralint_eval:status'
        ], 'privacy:metadata:oralint_eval');
        $collection->add_database_table('oralint_score', [
            'evaluationid' => 'privacy:metadata:oralint_score:evaluationid',
            'score' => 'privacy:metadata:oralint_score:score',
            'comment' => 'privacy:metadata:oralint_score:comment'
        ], 'privacy:metadata:oralint_score');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid) : approved_contextlist {
        global $DB;
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'oralinterview']);
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = ?
                  JOIN {oralint_session} s ON s.oralinterviewid = cm.id
                  JOIN {oralint_eval} e ON e.sessionid = s.id
                  WHERE cm.module = ? AND e.userid = ?";
        $contextids = $DB->get_fieldset_sql($sql, [CONTEXT_MODULE, $moduleid, $userid]);
        $contextlist = new approved_contextlist();
        foreach ($contextids as $contextid) {
            $contextlist->add_context(\context::instance_by_id($contextid));
        }
        return $contextlist;
    }

    public static function export_user_data(contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('oralinterview', $context->instanceid, 0, false, MUST_EXIST);
            $sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->id]);
            foreach ($sessions as $session) {
                $evaluation = $DB->get_record('oralint_eval', ['sessionid' => $session->id, 'userid' => $contextlist->get_userid()]);
                if (!$evaluation) {
                    continue;
                }
                $data = [
                    'sessionid' => $session->id,
                    'status' => $evaluation->status,
                    'finalscore' => $session->finalscore
                ];
                writer::with_context($context)->export_data([$session->id], $data);
                $scores = $DB->get_records('oralint_score', ['evaluationid' => $evaluation->id]);
                if ($scores) {
                    $scoredata = [];
                    foreach ($scores as $score) {
                        $scoredata[] = [
                            'question' => $score->sessionqid,
                            'score' => $score->score,
                            'comment' => $score->comment
                        ];
                    }
                    writer::with_context($context)->export_related_data([$session->id], 'oralint_score', $scoredata);
                }
            }
        }
    }

    public static function delete_data_for_user(contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('oralinterview', $context->instanceid, 0, false, MUST_EXIST);
            $userid = $contextlist->get_userid();
            $sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->id]);
            foreach ($sessions as $session) {
                $evaluation = $DB->get_record('oralint_eval', ['sessionid' => $session->id, 'userid' => $userid]);
                if (!$evaluation) {
                    continue;
                }
                $DB->delete_records('oralint_score', ['evaluationid' => $evaluation->id]);
                $DB->delete_records('oralint_eval', ['id' => $evaluation->id]);
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('oralinterview', $context->instanceid, 0, false, MUST_EXIST);
        $sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $cm->id]);
        foreach ($sessions as $session) {
            $evaluations = $DB->get_records('oralint_eval', ['sessionid' => $session->id]);
            foreach ($evaluations as $evaluation) {
                $DB->delete_records('oralint_score', ['evaluationid' => $evaluation->id]);
            }
            $DB->delete_records('oralint_eval', ['sessionid' => $session->id]);
        }
    }
}
