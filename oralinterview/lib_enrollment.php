<?php
/**
 * Auto-enrollment: get users from exam session (quiz) and sync as interview candidates.
 * Uses: exam_frame_settings (jobid → quizid, sessionid), test_session_assignments (session_id → userid).
 *
 * @package   mod_oralinterview
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check if a DB table exists (short name, no prefix).
 */
function oralinterview_enrollment_table_exists(string $shortname): bool {
    global $DB;
    return $DB->get_manager()->table_exists($shortname);
}

/**
 * Get quizid and sessionid for a job from exam_frame_settings.
 * Optionally scoped by courseid so the exam frame matches the activity's course.
 *
 * @param int $jobid
 * @param int|null $courseid optional; if set, only return row matching this course
 * @return object|null { quizid, sessionid } or null if table/row missing
 */
function oralinterview_get_exam_frame_for_job(int $jobid, $courseid = null): ?object {
    global $DB;

    if ($jobid <= 0 || !oralinterview_enrollment_table_exists('exam_frame_settings')) {
        return null;
    }

    // Prefer row matching courseid so exam frame matches activity course; fallback to any row for this job.
    if ($courseid !== null && $courseid > 0) {
        $row = $DB->get_record_sql(
            "SELECT quizid, sessionid FROM {exam_frame_settings} WHERE jobid = :jobid AND courseid = :courseid ORDER BY timemodified DESC LIMIT 1",
            ['jobid' => $jobid, 'courseid' => $courseid]
        );
    } else {
        $row = null;
    }
    if (!$row || empty($row->quizid) || empty($row->sessionid)) {
        $row = $DB->get_record_sql(
            "SELECT quizid, sessionid FROM {exam_frame_settings} WHERE jobid = :jobid ORDER BY timemodified DESC LIMIT 1",
            ['jobid' => $jobid]
        );
    }

    if (!$row || empty($row->quizid) || empty($row->sessionid)) {
        return null;
    }

    return $row;
}

/**
 * Get exam frame settings for display (exam_percent, interview_percent, etc.) for a job.
 * Same lookup order as get_exam_frame_for_job (course first, then job only).
 *
 * @param int $jobid
 * @param int|null $courseid optional
 * @return object|null with exam_percent, interview_percent, interview_selection, interview_top_n (or null)
 */
function oralinterview_get_exam_frame_display_for_job(int $jobid, $courseid = null): ?object {
    global $DB;

    if ($jobid <= 0 || !oralinterview_enrollment_table_exists('exam_frame_settings')) {
        return null;
    }

    $cols = $DB->get_columns('exam_frame_settings');
    $select = ['quizid', 'sessionid'];
    if (isset($cols['exam_percent'])) {
        $select[] = 'exam_percent';
    }
    if (isset($cols['interview_percent'])) {
        $select[] = 'interview_percent';
    }
    if (isset($cols['interview_selection'])) {
        $select[] = 'interview_selection';
    }
    if (isset($cols['interview_top_n'])) {
        $select[] = 'interview_top_n';
    }
    $sel = implode(', ', $select);

    // Prefer row for this course if given; fallback to any row for this job (frame may be in another course).
    $row = null;
    if ($courseid !== null && $courseid > 0) {
        $row = $DB->get_record_sql(
            "SELECT $sel FROM {exam_frame_settings} WHERE jobid = :jobid AND courseid = :courseid ORDER BY timemodified DESC LIMIT 1",
            ['jobid' => $jobid, 'courseid' => $courseid]
        );
    }
    if (!$row) {
        $row = $DB->get_record_sql(
            "SELECT $sel FROM {exam_frame_settings} WHERE jobid = :jobid ORDER BY timemodified DESC LIMIT 1",
            ['jobid' => $jobid]
        );
    }

    return $row ?: null;
}

/**
 * Format exam/interview weights for display (avoids theme replacing get_string with [[key]]).
 *
 * @param int $exam_pct
 * @param int $interview_pct
 * @return string
 */
function oralinterview_format_exam_frame_weights(int $exam_pct, int $interview_pct): string {
    $lang = current_language();
    if ($lang === 'ar') {
        return 'الاختبار: ' . $exam_pct . '٪ | المقابلة: ' . $interview_pct . '٪';
    }
    return 'Exam: ' . $exam_pct . '% | Interview: ' . $interview_pct . '%';
}

/**
 * Format interview top-N for display (avoids theme replacing get_string with [[key]]).
 *
 * @param int $n
 * @return string
 */
function oralinterview_format_exam_frame_top_n(int $n): string {
    $lang = current_language();
    if ($lang === 'ar') {
        return 'اختيار المقابلة: أفضل ' . $n;
    }
    return 'Interview selection: top ' . $n;
}

/**
 * Get Moodle user IDs assigned to an exam session (same logic as session_quiz_execute).
 * Table: test_session_assignments. Tries session_id first, then sessionid (column name can vary).
 *
 * @param int $sessionid test_sessions.id (or exam_frame_settings.sessionid)
 * @return int[] user ids
 */
function oralinterview_get_userids_for_exam_session(int $sessionid): array {
    global $DB;

    if ($sessionid <= 0 || !oralinterview_enrollment_table_exists('test_session_assignments')) {
        return [];
    }

    // Try session_id first (as in session_quiz_execute.php).
    $records = $DB->get_records(
        'test_session_assignments',
        ['session_id' => $sessionid],
        '',
        'userid'
    );
    if (empty($records)) {
        // Some DBs use sessionid (no underscore) as column name.
        $cols = $DB->get_columns('test_session_assignments');
        if (isset($cols['sessionid'])) {
            $records = $DB->get_records(
                'test_session_assignments',
                ['sessionid' => $sessionid],
                '',
                'userid'
            );
        }
    }

    return array_values(array_unique(array_map('intval', array_column($records, 'userid'))));
}

/**
 * Get user IDs enrolled in the exam for the given job (via exam_frame_settings + test_session_assignments).
 *
 * @param int $jobid
 * @param int|null $courseid optional
 * @return int[] user ids
 */
function oralinterview_get_exam_userids_for_job(int $jobid, $courseid = null): array {
    $frame = oralinterview_get_exam_frame_for_job($jobid, $courseid);
    if (!$frame) {
        return [];
    }
    return oralinterview_get_userids_for_exam_session((int) $frame->sessionid);
}

/**
 * Ensure an oralint_candidate exists for this Moodle user; return candidate id.
 *
 * @param int $userid Moodle user id
 * @return int oralint_candidate.id
 */
function oralinterview_ensure_candidate_for_user(int $userid): int {
    global $DB, $USER;

    $existing = $DB->get_record('oralint_candidate', ['linkeduserid' => $userid], 'id', IGNORE_MISSING);
    if ($existing) {
        return (int) $existing->id;
    }

    $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email', MUST_EXIST);
    $record = (object) [
        'fullname'      => fullname($user),
        'email'         => $user->email ?? '',
        'phone'         => '',
        'nationalid'    => '',
        'linkeduserid'   => $userid,
        'status'        => 'active',
        'timecreated'   => time(),
        'timemodified'  => time(),
        'usermodified'  => $USER->id,
    ];
    return (int) $DB->insert_record('oralint_candidate', $record);
}

/**
 * Sync candidates from the exam session (test_session_assignments) into this oral interview.
 * Creates oralint_candidate (by linkeduserid) and oralint_session (draft) for each user
 * that is not already assigned. Uses the activity's default template (first template or question set).
 *
 * @param int $oralinterviewid oralinterview instance id (cm->instance)
 * @param int $courseid course id
 * @param int $jobid job id (from activity)
 * @param int $cmid course module id (for template lookup; oralint_template.oralinterviewid = cmid)
 * @return array { synced: int, skipped: int, errors: string[] }
 */
function oralinterview_sync_candidates_from_exam(int $oralinterviewid, int $courseid, int $jobid, int $cmid): array {
    global $DB, $USER;

    $result = ['synced' => 0, 'skipped' => 0, 'errors' => []];

    $frame = oralinterview_get_exam_frame_for_job($jobid, $courseid);
    if (!$frame) {
        $result['errors'][] = get_string('sync_candidates_no_frame', 'oralinterview');
        return $result;
    }

    $userids = oralinterview_get_userids_for_exam_session((int) $frame->sessionid);
    if (empty($userids)) {
        $result['errors'][] = get_string('sync_candidates_no_users', 'oralinterview');
        return $result;
    }

    $template = oralinterview_get_default_template_for_cm($cmid);
    if (!$template) {
        $result['errors'][] = get_string('no_template_questions', 'oralinterview');
        return $result;
    }

    $existing_sessions = $DB->get_records_sql(
        "SELECT DISTINCT c.linkeduserid
           FROM {oralint_session} s
           JOIN {oralint_candidate} c ON c.id = s.candidateid
          WHERE s.oralinterviewid = ?
            AND c.linkeduserid IS NOT NULL
            AND c.linkeduserid <> 0",
        [$oralinterviewid]
    );
    $already_userids = array_fill_keys(array_map('intval', array_column($existing_sessions, 'linkeduserid')), true);

    foreach ($userids as $userid) {
        $userid = (int) $userid;
        if (isset($already_userids[$userid])) {
            $result['skipped']++;
            continue;
        }

        try {
            $candidateid = oralinterview_ensure_candidate_for_user($userid);
            $DB->insert_record('oralint_session', (object) [
                'courseid'         => $courseid,
                'oralinterviewid'  => $oralinterviewid,
                'candidateid'      => $candidateid,
                'templateid'       => (int) $template->id,
                'interviewdate'    => 0,
                'deadline'         => 0,
                'requireall'       => 1,
                'status'           => 'active',
                'timecreated'      => time(),
                'timemodified'     => time(),
                'usermodified'     => $USER->id,
            ]);
            $result['synced']++;
            $already_userids[$userid] = true;
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();
        }
    }

    return $result;
}

/**
 * Get the default template (first by timecreated) for this oral interview.
 * Templates are keyed by course module id (oralinterviewid = cm->id in this codebase).
 *
 * @param int $cmid course module id
 * @return object|null oralint_template
 */
function oralinterview_get_default_template_for_cm(int $cmid): ?object {
    global $DB;
    $recs = $DB->get_records('oralint_template', ['oralinterviewid' => $cmid], 'timecreated ASC', '*', 0, 1);
    return $recs ? reset($recs) : null;
}
