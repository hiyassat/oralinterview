<?php
namespace mod_oralinterview\local;

use mod_oralinterview\event\session_completed;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class scoring {
    public static function compute_final_score(int $sessionid, ?int $userid = null, bool $force = false) {
        global $DB, $USER;

        $session = $DB->get_record('oralint_session', ['id' => $sessionid], '*', MUST_EXIST);
        $submitted = $DB->count_records_select('oralint_eval', 'sessionid = ? AND status = ?', [$sessionid, 'submitted']);

        // Committee count: if not configured yet, treat as 1 (or as many as already submitted) so recalculation works.
        $committeecount = (int)($session->committeecount ?: $DB->count_records('oralint_committee', ['sessionid' => $sessionid]));
        if ($committeecount <= 0) {
            $committeecount = max(1, (int)$submitted);
        }

        // Ensure session questions exist (snapshot). Recalculate needs them even if nobody opened evaluate yet.
        $questioncount = (int)$DB->count_records('oralint_session_q', ['sessionid' => $sessionid]);
        if ($questioncount <= 0) {
            $template_questions = $DB->get_records('oralint_template_q', ['templateid' => $session->templateid], 'sortorder ASC');
            foreach ($template_questions as $tq) {
                $sq = new stdClass();
                $sq->sessionid = $sessionid;
                $sq->questionid = $tq->questionid;
                $sq->questiontext = $tq->questiontext;
                $sq->rubric = $tq->rubric;
                $sq->maxscore = $tq->maxscore;
                $sq->sortorder = $tq->sortorder;
                $sq->timecreated = time();
                $sq->timemodified = time();
                $sq->usermodified = $userid ?? ($USER->id ?? 0);
                $DB->insert_record('oralint_session_q', $sq);
            }
            $questioncount = (int)$DB->count_records('oralint_session_q', ['sessionid' => $sessionid]);
        }
        if ($questioncount <= 0) {
            return null;
        }
        $ready = !$session->requireall || $submitted >= $committeecount || $force;
        if ($session->requireall && $submitted < $committeecount && !$force) {
            return null;
        }

        $sum = (float)$DB->get_field_sql(
            'SELECT COALESCE(SUM(score), 0) FROM {oralint_score} sc JOIN {oralint_eval} e ON sc.evaluationid = e.id WHERE e.sessionid = ? AND e.status = ?',
            [$sessionid, 'submitted']
        );

        $denominator = $committeecount * $questioncount;
        if ($denominator === 0) {
            return null;
        }

        $finalscore = $sum / $denominator;
        $finalscore = min(max($finalscore, 0), 10);
        $finalpercent = ($finalscore / 10) * 100;

        $record = new stdClass();
        $record->id = $sessionid;
        $record->finalscore = $finalscore;
        $record->finalpercent = $finalpercent;
        $record->formula_version = 'v1';
        $record->committeecount = $committeecount;
        $record->questioncount = $questioncount;
        $record->timemodified = time();
        $record->usermodified = $userid ?? ($USER->id ?? 0);
        if ($ready) {
            $record->status = $session->status === 'locked' ? 'locked' : 'completed';
        }

        $DB->update_record('oralint_session', $record);

        if ($ready) {
            // $session->oralinterviewid stores the oralinterview *instance* id in this codebase.
            // Convert to course module id for context creation.
            $cm = get_coursemodule_from_instance('oralinterview', $session->oralinterviewid, $session->courseid, false, IGNORE_MISSING);
            $context = $cm ? \context_module::instance($cm->id) : \context_course::instance($session->courseid);
            $event = session_completed::create([
                'objectid' => $session->id,
                'context' => $context,
                'other' => ['finalscore' => $finalscore, 'sessionid' => $session->id]
            ]);
            $event->trigger();
        }

        return (object)[
            'finalscore' => $finalscore,
            'finalpercent' => $finalpercent,
            'ready' => !$session->requireall || $submitted >= $committeecount,
            'submitted' => $submitted,
            'committeecount' => $committeecount,
        ];
    }
}
