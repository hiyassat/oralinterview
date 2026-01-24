<?php
namespace mod_oralinterview\tests;

use advanced_testcase;
use mod_oralinterview\local\scoring;
use mod_oralinterview\local\access;
use stdClass;

/**
 * PHPUnit tests for scoring and access control.
 */
class scoring_test extends advanced_testcase {
    public function test_compute_final_score_calculates_normalized_value() {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('oralinterview', ['course' => $course->id]);

        $session = new stdClass();
        $session->courseid = $course->id;
        $session->oralinterviewid = $cm->id;
        $session->candidateid = 0;
        $session->templateid = 0;
        $session->status = 'active';
        $session->requireall = 1;
        $session->timecreated = time();
        $session->timemodified = time();
        $session->usermodified = $course->userid;
        $sessionid = $DB->insert_record('oralint_session', $session);

        $questioncount = 3;
        for ($i = 1; $i <= $questioncount; $i++) {
            $DB->insert_record('oralint_session_q', (object)[
                'sessionid' => $sessionid,
                'questiontext' => "Question {$i}",
                'rubric' => 'rubric',
                'maxscore' => 10,
                'sortorder' => $i,
                'timecreated' => time(),
                'timemodified' => time()
            ]);
        }

        $committeeids = [];
        for ($i = 0; $i < 2; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $committeeids[] = $user->id;
            $DB->insert_record('oralint_committee', (object)[
                'sessionid' => $sessionid,
                'userid' => $user->id,
                'role' => 'member',
                'status' => 'assigned',
                'timecreated' => time(),
                'timemodified' => time()
            ]);
            $evaluationid = $DB->insert_record('oralint_eval', (object)[
                'sessionid' => $sessionid,
                'userid' => $user->id,
                'status' => 'submitted',
                'timecreated' => time(),
                'timemodified' => time(),
                'usermodified' => $user->id
            ]);
            for ($q = 1; $q <= $questioncount; $q++) {
                $score = 8 + $i; // varying score
                $DB->insert_record('oralint_score', (object)[
                    'evaluationid' => $evaluationid,
                    'sessionqid' => $q,
                    'score' => $score,
                    'comment' => 'ok',
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $user->id
                ]);
            }
        }
        $DB->set_field('oralint_session', 'committeecount', count($committeeids), ['id' => $sessionid]);

        $result = scoring::compute_final_score($sessionid, $course->userid, true);
        $this->assertNotNull($result);
        $this->assertEquals(2, $result->committeecount);
        $this->assertEqualsWithDelta(8.5, $result->finalscore, 0.001);
        $this->assertEqualsWithDelta(85.0, $result->finalpercent, 0.1);
    }

    public function test_assert_evaluation_owner_throws_on_mismatch() {
        global $DB;
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $sessionid = 1;
        $evaluationid = $DB->insert_record('oralint_eval', (object)[
            'sessionid' => $sessionid,
            'userid' => $user1->id,
            'status' => 'notstarted',
            'timecreated' => time()
        ]);

        $this->expectException(\moodle_exception::class);
        access::assert_evaluation_owner($evaluationid, $user2->id);
    }
}
