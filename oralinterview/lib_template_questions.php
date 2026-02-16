<?php
/**
 * Interview template questions – data and helpers using mdl_exam_interview_competencies.
 *
 * @package   mod_oralinterview
 * @copyright (c) 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get competency/indicator rows for a job from exam_interview_competencies.
 * Returns array of objects with competencyid, indicatorid (and optionally courseid, sessionid).
 *
 * @param int $jobid
 * @param int|null $courseid optional filter
 * @param int|null $sessionid optional filter
 * @return array
 */
function oralinterview_get_job_competency_indicators(int $jobid, $courseid = null, $sessionid = null): array {
    global $DB;

    if (!oralinterview_table_exists('exam_interview_competencies')) {
        return [];
    }

    $params = ['jobid' => $jobid];
    $where = 'jobid = :jobid AND selected_for = :sel';
    $params['sel'] = 'interview';

    if ($courseid !== null && $courseid > 0) {
        $where .= ' AND courseid = :courseid';
        $params['courseid'] = $courseid;
    }
    if ($sessionid !== null && $sessionid > 0) {
        $where .= ' AND sessionid = :sessionid';
        $params['sessionid'] = $sessionid;
    }

    // Use a unique first column so get_records_sql returns all rows (it keys by first column).
    $sql = "SELECT (competencyid * 10000 + indicatorid) AS rowkey, competencyid, indicatorid
            FROM {exam_interview_competencies}
            WHERE $where
            ORDER BY competencyid, indicatorid";
    $rows = $DB->get_records_sql($sql, $params);
    return $rows ? array_values($rows) : [];
}

/**
 * Build tree: competencies (with names) → indicators (with names).
 * Uses question_categories for names. Keys: competencyid, name, indicators (array of {id, name}).
 *
 * @param int $jobid
 * @param int|null $courseid
 * @param int|null $sessionid
 * @return array
 */
function oralinterview_get_competency_indicator_tree(int $jobid, $courseid = null, $sessionid = null): array {
    global $DB;

    $rows = oralinterview_get_job_competency_indicators($jobid, $courseid, $sessionid);
    if (empty($rows)) {
        return [];
    }

    $by_comp = [];
    foreach ($rows as $r) {
        $cid = (int) $r->competencyid;
        $iid = (int) $r->indicatorid;
        if (!isset($by_comp[$cid])) {
            $by_comp[$cid] = ['competencyid' => $cid, 'indicators' => []];
        }
        $by_comp[$cid]['indicators'][$iid] = $iid;
    }

    $tree = [];
    foreach ($by_comp as $cid => $data) {
        $comp = $DB->get_record('question_categories', ['id' => $cid], 'id, name', IGNORE_MISSING);
        $name = $comp ? $comp->name : get_string('competency', 'oralinterview') . ' #' . $cid;
        $indicators = [];
        foreach ($data['indicators'] as $iid) {
            $ind = $DB->get_record('question_categories', ['id' => $iid], 'id, name', IGNORE_MISSING);
            $indicators[] = [
                'id'   => $iid,
                'name' => $ind ? $ind->name : get_string('indicator', 'oralinterview') . ' #' . $iid,
            ];
        }
        $tree[] = [
            'competencyid' => $cid,
            'name'         => $name,
            'indicators'   => $indicators,
        ];
    }

    return $tree;
}

/**
 * Get interview/both questions linked to an indicator (question_type_mapping.competency_category_id = indicator).
 *
 * @param int $indicatorid question_categories id (leaf/indicator)
 * @return array of question objects with id, name, questiontext
 */
function oralinterview_get_questions_for_indicator(int $indicatorid): array {
    global $DB;

    $sql = "SELECT q.id, q.name, q.questiontext
            FROM {question} q
            JOIN {question_type_mapping} qtm ON qtm.question_id = q.id
            WHERE qtm.competency_category_id = :indid
              AND qtm.question_type IN ('interview', 'both')
            ORDER BY q.name ASC";
    return $DB->get_records_sql($sql, ['indid' => $indicatorid]);
}

/**
 * Get all template question IDs for a template.
 *
 * @param int $templateid
 * @return int[]
 */
function oralinterview_get_template_question_ids(int $templateid): array {
    global $DB;
    $recs = $DB->get_records('oralint_template_q', ['templateid' => $templateid], 'sortorder ASC', 'questionid');
    return array_map('intval', array_column($recs, 'questionid'));
}

/**
 * Get competency weight (اوزان) from competency_areas by matching name.
 * Prefers job-specific row; falls back to generic (job_id IS NULL).
 * Returns weight_percent if set, else weight, else null.
 *
 * @param int $jobid Job ID (approved_jobs.jobid; competency_areas.job_id references jobs.id)
 * @param string $competencyname Competency display name (question_categories.name)
 * @return float|null Weight as percentage (0–100) or null
 */
function oralinterview_get_competency_weight_for_job(int $jobid, string $competencyname): ?float {
    global $DB;

    if (!oralinterview_table_exists('competency_areas')) {
        return null;
    }

    $name = trim($competencyname);
    if ($name === '') {
        return null;
    }

    $cols = $DB->get_columns('competency_areas');
    $has_weight_percent = isset($cols['weight_percent']);
    $has_weight = isset($cols['weight']);
    $has_job_id = isset($cols['job_id']);

    if (!$has_weight_percent && !$has_weight) {
        return null;
    }

    $select = $has_weight_percent ? 'weight_percent' : 'weight';
    $params = ['name' => $name];
    $where = 'name_ar = :name';

    if ($has_job_id && $jobid > 0) {
        $where .= ' AND (job_id = :jobid OR job_id IS NULL)';
        $params['jobid'] = $jobid;
        $order = 'job_id DESC'; // Prefer job-specific.
    } else {
        $order = 'id ASC';
    }

    $sql = "SELECT $select FROM {competency_areas} WHERE $where ORDER BY $order LIMIT 1";
    $val = $DB->get_field_sql($sql, $params);

    if ($val === false || $val === null || $val === '') {
        return null;
    }

    return (float) $val;
}

/**
 * Build competency tree with weights from competency_areas.
 * Each competency gets a 'weight' key (float 0–100 or null).
 *
 * @param int $jobid
 * @param int|null $courseid
 * @param int|null $sessionid
 * @return array Same structure as oralinterview_get_competency_indicator_tree + weight
 */
function oralinterview_get_competency_tree_with_weights(int $jobid, $courseid = null, $sessionid = null): array {
    $tree = oralinterview_get_competency_indicator_tree($jobid, $courseid, $sessionid);

    foreach ($tree as &$comp) {
        $comp['weight'] = oralinterview_get_competency_weight_for_job($jobid, $comp['name']);
    }

    return $tree;
}

/**
 * Generate template questions by competency weights.
 * Same logic as process_exam / create_exam_frame: distribute questions and marks by weight.
 * Full mark = interview_percent from exam_frame_settings; each competency gets (weight/sum)*full_mark,
 * divided equally among its questions.
 *
 * @param int $templateid
 * @param int $jobid
 * @param int $totalquestions
 * @param int $userid
 * @param array $formweights Optional. competencyid => weight from form. When provided, used instead of competency_areas.
 * @param int $courseid Optional. Used to get interview_percent from exam_frame_settings as full mark.
 * @return array { added: int, skipped: int, errors: string[] }
 */
function oralinterview_generate_template_questions_by_weights(
    int $templateid,
    int $jobid,
    int $totalquestions,
    int $userid,
    array $formweights = [],
    int $courseid = 0
): array {
    global $DB;

    $result = ['added' => 0, 'skipped' => 0, 'errors' => []];

    if ($totalquestions <= 0) {
        $result['errors'][] = get_string('invalid_total_questions', 'oralinterview');
        return $result;
    }

    $tree = oralinterview_get_competency_tree_with_weights($jobid, null, null);
    if (empty($tree)) {
        $result['errors'][] = get_string('no_competencies_for_job', 'oralinterview');
        return $result;
    }

    $weights = [];
    $sum = 0.0;

    foreach ($tree as $comp) {
        $cid = $comp['competencyid'];
        $w = 0.0;
        if (!empty($formweights) && isset($formweights[$cid])) {
            $w = (float) $formweights[$cid];
        } elseif (isset($comp['weight']) && $comp['weight'] !== null && $comp['weight'] > 0) {
            $w = (float) $comp['weight'];
        }
        if ($w > 0) {
            $weights[$cid] = $w;
            $sum += $w;
        }
    }

    if ($sum <= 0) {
        $equal = 100.0 / count($tree);
        foreach ($tree as $comp) {
            $weights[$comp['competencyid']] = $equal;
        }
        $sum = 100.0;
    }

    // Full mark = interview_percent from exam_frame_settings (same idea as exam grade in create_exam_frame).
    $fullmark = 100.0;
    if ($courseid > 0) {
        require_once(__DIR__ . '/lib_enrollment.php');
        $frame = oralinterview_get_exam_frame_display_for_job($jobid, $courseid);
        if ($frame && isset($frame->interview_percent) && $frame->interview_percent > 0) {
            $fullmark = (float) $frame->interview_percent;
        }
    }

    $existing = oralinterview_get_template_question_ids($templateid);
    $existing_set = array_fill_keys($existing, true);

    // to_add: [{ qid, maxscore }]
    $to_add = [];
    $sortorder = 0;

    foreach ($tree as $comp) {
        $cid = $comp['competencyid'];
        $w = $weights[$cid] ?? 0;
        if ($w <= 0) {
            continue;
        }

        $qcount = (int) round(($w / $sum) * $totalquestions);
        if ($qcount <= 0) {
            continue;
        }

        $marks_for_competency = ($w / $sum) * $fullmark;
        $maxscore_per_q = $qcount > 0 ? $marks_for_competency / $qcount : 0;
        $maxscore_int = max(1, (int) round($maxscore_per_q));

        $pool = [];
        foreach ($comp['indicators'] as $ind) {
            $questions = oralinterview_get_questions_for_indicator($ind['id']);
            foreach ($questions as $q) {
                $pool[] = (int) $q->id;
            }
        }

        $pool = array_unique($pool);
        $available = [];
        foreach ($pool as $qid) {
            if (!isset($existing_set[$qid])) {
                $available[] = $qid;
            }
        }

        shuffle($available);
        $take = min($qcount, count($available));
        for ($i = 0; $i < $take; $i++) {
            $to_add[] = ['qid' => $available[$i], 'maxscore' => $maxscore_int];
            $existing_set[$available[$i]] = true;
        }
    }

    $now = time();
    $sortorder = 0;
    foreach ($to_add as $item) {
        $qid = $item['qid'];
        $maxscore = $item['maxscore'];
        $q = $DB->get_record('question', ['id' => $qid], 'id, questiontext', IGNORE_MISSING);
        if (!$q) {
            continue;
        }
        $DB->insert_record('oralint_template_q', (object)[
            'templateid'   => $templateid,
            'questionid'   => $qid,
            'questiontext' => $q->questiontext ?? '',
            'rubric'       => '',
            'maxscore'     => $maxscore,
            'sortorder'    => $sortorder++,
            'timecreated'  => $now,
            'timemodified' => $now,
            'usermodified' => $userid,
        ]);
        $result['added']++;
    }

    return $result;
}

/**
 * Check if a DB table exists (without mdl_ prefix).
 *
 * @param string $shortname e.g. exam_interview_competencies
 * @return bool
 */
function oralinterview_table_exists(string $shortname): bool {
    global $DB;
    return $DB->get_manager()->table_exists($shortname);
}

/**
 * Get display name for a job (from planning_ready_jobs.job_title or fallback to "Job #id").
 *
 * @param int $jobid
 * @return string
 */
function oralinterview_get_job_display_name(int $jobid): string {
    global $DB;
    if ($jobid <= 0) {
        return '';
    }
    $title = $DB->get_field('planning_ready_jobs', 'job_title', ['jobid' => $jobid], IGNORE_MISSING);
    $title = trim((string)$title);
    return $title !== '' ? $title : ('Job #' . $jobid);
}

/**
 * Auto-generated template name: "[Job title] [date]".
 *
 * @param int $jobid
 * @param int|null $date optional timestamp; default time()
 * @return string
 */
function oralinterview_template_auto_name(int $jobid, $date = null): string {
    $name = oralinterview_get_job_display_name($jobid);
    $ts = $date ?? time();
    $datestr = date('Y-m-d', $ts);
    return $name === '' ? $datestr : ($name . ' ' . $datestr);
}

/**
 * Get approved jobs for job-selection step.
 *
 * @return array
 */
function oralinterview_get_approved_jobs(): array {
    global $DB;
    return $DB->get_records_sql("
        SELECT aj.* FROM {approved_jobs} aj
        WHERE aj.status = 'approved'
        ORDER BY aj.jobid ASC
    ");
}

/**
 * Return URL for the template questions step (step 2) with current job.
 *
 * @param int $id cm id
 * @param int $templateid
 * @param int $jobid
 * @param bool $spacui
 * @return moodle_url
 */
function oralinterview_template_questions_return_url(int $id, int $templateid, int $jobid, bool $spacui): moodle_url {
    $params = ['id' => $id, 'templateid' => $templateid, 'step' => 2, 'jobid' => $jobid];
    if ($spacui) {
        $params['spacui'] = 1;
    }
    return new moodle_url('/mod/oralinterview/template_questions_advanced.php', $params);
}

/**
 * Step 1: render job selection (approved jobs only). No queries – pass jobs from oralinterview_get_approved_jobs().
 *
 * @param int $id cm id
 * @param int $templateid
 * @param bool $spacui
 * @param array $jobs from oralinterview_get_approved_jobs()
 * @return string HTML
 */
function oralinterview_template_questions_render_step1_jobs(int $id, int $templateid, bool $spacui, array $jobs): string {
    global $DB;
    $out = '<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Step 1: Select approved job</h5></div><div class="card-body">';
    if (!empty($jobs)) {
        $out .= '<form method="get" action="template_questions_advanced.php">';
        $out .= '<input type="hidden" name="id" value="' . $id . '">';
        $out .= '<input type="hidden" name="templateid" value="' . $templateid . '">';
        $out .= '<input type="hidden" name="step" value="2">';
        if ($spacui) {
            $out .= '<input type="hidden" name="spacui" value="1">';
        }
        $out .= '<div class="row">';
        foreach ($jobs as $job) {
            $approver = $DB->get_record('user', ['id' => $job->approved_by], 'id', IGNORE_MISSING);
            $out .= '<div class="col-md-4 mb-3">';
            $out .= '<div class="card"><div class="card-body">';
            $out .= '<h6 class="card-title">Job ID: ' . (int)$job->jobid . '</h6>';
            $out .= '<p class="card-text small text-muted">';
            $out .= 'Approved: ' . userdate($job->approved_date) . '<br>';
            $out .= 'By: ' . ($approver ? fullname($approver) : '-') . '</p>';
            $out .= '<button type="submit" name="jobid" value="' . (int)$job->jobid . '" class="btn btn-primary btn-sm">Select job</button>';
            $out .= '</div></div></div>';
        }
        $out .= '</div></form>';
    } else {
        $out .= '<div class="alert alert-warning">No approved jobs found. Please approve jobs first.</div>';
    }
    $out .= '</div></div>';
    return $out;
}

/**
 * Step 2: render competency → indicator → questions (expandable) and template questions column.
 * All data is loaded via lib functions (exam_interview_competencies, template_q, question).
 *
 * @param int $id cm id
 * @param int $templateid
 * @param int $jobid
 * @param object $cm
 * @param bool $spacui
 * @return string HTML
 */
function oralinterview_template_questions_render_step2_questions(int $id, int $templateid, int $jobid, $cm, bool $spacui): string {
    global $DB;

    $tree = oralinterview_get_competency_tree_with_weights($jobid, null, null);
    $template_question_ids = oralinterview_get_template_question_ids($templateid);
    $returnurl = oralinterview_template_questions_return_url($id, $templateid, $jobid, $spacui)->out(false);

    $out = '<div class="card mb-4"><div class="card-header"><h5 class="mb-0">Step 2: Interview questions</h5></div><div class="card-body">';
    $out .= '<p class="text-muted">Job ID: ' . (int)$jobid . '</p>';

    if (empty($tree)) {
        $out .= '<div class="alert alert-warning">';
        $out .= get_string('no_competencies_for_job', 'oralinterview');
        $out .= '</div>';
        $out .= '<a href="template_questions_advanced.php?id=' . $id . '&templateid=' . $templateid . '&step=1' . ($spacui ? '&spacui=1' : '') . '" class="btn btn-secondary">Back to job selection</a>';
        $out .= '</div></div>';
        return $out;
    }

    $formaction = (new moodle_url('/mod/oralinterview/template_questions_advanced.php', [
        'id' => $id, 'templateid' => $templateid, 'step' => 2, 'jobid' => $jobid,
    ] + ($spacui ? ['spacui' => 1] : [])))->out(false);
    $out .= '<form method="post" action="' . s($formaction) . '" id="oi-generate-by-weights-form">';
    $out .= '<input type="hidden" name="id" value="' . $id . '">';
    $out .= '<input type="hidden" name="templateid" value="' . $templateid . '">';
    $out .= '<input type="hidden" name="jobid" value="' . (int)$jobid . '">';
    $out .= '<input type="hidden" name="step" value="2">';
    $out .= '<input type="hidden" name="action" value="generate_by_weights">';
    $out .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    if ($spacui) {
        $out .= '<input type="hidden" name="spacui" value="1">';
    }
    $out .= '<div class="card border-primary mb-4"><div class="card-body">';
    $out .= '<h6 class="card-title">توليد حسب الأوزان</h6>';
    $out .= '<p class="small text-muted mb-2">أدخل وزن كل كفاية أدناه (٪) ثم انقر التوليد. لا حاجة لحفظ منفصل.</p>';
    $out .= '<div class="d-flex align-items-center gap-2 flex-wrap mb-3">';
    $out .= '<label class="form-label mb-0">عدد الأسئلة:</label>';
    $out .= '<input type="number" name="total_questions" value="10" min="1" max="100" class="form-control form-control-sm" style="width:80px">';
    $out .= '<button type="submit" name="submit_generate" value="1" class="btn btn-primary">توليد حسب الأوزان</button>';
    $out .= '</div></div></div>';

    $out .= '<div class="row">';
    $out .= '<div class="col-lg-7">';
    $out .= '<h6 class="mb-3">الكفاية ← المؤشر ← الأسئلة</h6>';
    $out .= '<div class="accordion mb-4" id="oi-comp-accordion">';

    $compindex = 0;
    foreach ($tree as $comp) {
        $collapse_id = 'oi-comp-' . $compindex;
        $cid = (int) $comp['competencyid'];
        $default_weight = ($comp['weight'] !== null && $comp['weight'] > 0) ? (int) round($comp['weight']) : 0;
        $weight_input = '<input type="number" name="weights[' . $cid . ']" value="' . $default_weight . '" min="0" max="100" ';
        $weight_input .= 'class="form-control form-control-sm" style="width:60px" title="وزن الكفاية %">';

        $out .= '<div class="accordion-item competency-box" data-comp="' . $compindex . '">';
        $out .= '<h2 class="accordion-header d-flex align-items-center">';
        $out .= '<button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="false" aria-controls="' . $collapse_id . '">';
        $out .= format_string($comp['name']);
        $out .= '</button>';
        $out .= '<span class="d-inline-flex align-items-center gap-1 px-2" onclick="event.stopPropagation();">';
        $out .= '<label class="text-muted mb-0 me-1" style="font-size: 0.8rem; font-weight: normal;">وزن %</label>';
        $out .= $weight_input . '</span></h2>';
        $out .= '<div id="' . $collapse_id . '" class="accordion-collapse collapse" data-bs-parent="#oi-comp-accordion">';
        $out .= '<div class="accordion-body p-2">';

        $indindex = 0;
        foreach ($comp['indicators'] as $ind) {
            $ind_collapse_id = 'oi-ind-' . $compindex . '-' . $indindex;
            $questions = oralinterview_get_questions_for_indicator($ind['id']);
            $out .= '<div class="accordion-item border-0 mb-2">';
            $out .= '<h3 class="accordion-header small">';
            $out .= '<button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#' . $ind_collapse_id . '">';
            $out .= format_string($ind['name']);
            $out .= ' <span class="badge bg-secondary ms-2">' . count($questions) . '</span>';
            $out .= '</button></h3>';
            $out .= '<div id="' . $ind_collapse_id . '" class="accordion-collapse collapse">';
            $out .= '<div class="accordion-body p-2">';
            if (empty($questions)) {
                $out .= '<p class="small text-muted mb-0">No interview questions linked to this indicator.</p>';
            } else {
                $out .= '<ul class="list-group list-group-flush">';
                foreach ($questions as $q) {
                    $in_template = in_array((int)$q->id, $template_question_ids, true);
                    $out .= '<li class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-2">';
                    $out .= '<div class="ms-2 me-auto">';
                    $out .= '<span class="fw-medium">' . format_string($q->name) . '</span>';
                    $out .= '<br><small class="text-muted">' . oralinterview_short_question_text($q->questiontext) . '</small>';
                    $out .= '</div>';
                    $out .= '<div class="btn-group btn-group-sm">';
                    $out .= '<a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="' . (new moodle_url('/mod/oralinterview/question_view.php', [
                        'id' => $cm->id, 'qid' => (int)$q->id, 'jobid' => $jobid, 'returnurl' => $returnurl, 'spacui' => (int)$spacui,
                    ]))->out() . '">' . get_string('viewquestion', 'oralinterview') . '</a>';
                    if ($in_template) {
                        $out .= '<a class="btn btn-danger" href="' . oralinterview_template_question_action_url($id, $templateid, $jobid, $spacui, 'remove', (int)$q->id)->out() . '">−</a>';
                    } else {
                        $out .= '<a class="btn btn-primary" href="' . oralinterview_template_question_action_url($id, $templateid, $jobid, $spacui, 'add', (int)$q->id)->out() . '">+</a>';
                    }
                    $out .= '</div></li>';
                }
                $out .= '</ul>';
            }
            $out .= '</div></div></div>';
            $indindex++;
        }
        $out .= '</div></div></div>';
        $compindex++;
    }
    $out .= '</div></div>';

    $out .= '<div class="col-lg-5">';
    $out .= '<h6 class="mb-3">Template questions</h6>';
    $template_questions = $DB->get_records('oralint_template_q', ['templateid' => $templateid], 'sortorder ASC');
    $out .= '<div class="list-group" style="max-height: 500px; overflow-y: auto;">';
    if (empty($template_questions)) {
        $out .= '<div class="list-group-item text-muted">لم تتم إضافة أي أسئلة إلى هذا القالب بعد.</div>';
    } else {
        $qids = array_column($template_questions, 'questionid');
        $qrecs = $DB->get_records_list('question', 'id', $qids);
        foreach ($template_questions as $tq) {
            $q = $qrecs[$tq->questionid] ?? null;
            if (!$q) {
                continue;
            }
            $out .= '<div class="list-group-item">';
            $out .= '<div class="d-flex justify-content-between align-items-start">';
            $out .= '<div><span class="fw-medium">' . format_string($q->name) . '</span>';
            $out .= '<br><small class="text-muted">' . oralinterview_short_question_text($q->questiontext) . '</small></div>';
            $out .= '<div class="btn-group btn-group-sm">';
            $out .= '<a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="' . (new moodle_url('/mod/oralinterview/question_view.php', [
                'id' => $cm->id, 'qid' => (int)$q->id, 'jobid' => $jobid, 'returnurl' => $returnurl, 'spacui' => (int)$spacui,
            ]))->out() . '">' . get_string('viewquestion', 'oralinterview') . '</a>';
            $out .= '<a class="btn btn-danger btn-sm" href="' . oralinterview_template_question_action_url($id, $templateid, $jobid, $spacui, 'remove', (int)$q->id)->out() . '">×</a>';
            $out .= '</div></div></div>';
        }
    }
    $out .= '</div></div>';
    $out .= '</div>';

    $out .= '</form>';

    $out .= '<div class="mt-3">';
    $out .= '<a href="templates.php?id=' . $id . ($spacui ? '&spacui=1' : '') . '" class="btn btn-success">Finish template setup</a> ';
    $out .= '<a href="template_questions_advanced.php?id=' . $id . '&templateid=' . $templateid . '&step=1' . ($spacui ? '&spacui=1' : '') . '" class="btn btn-secondary">Back to job selection</a>';
    $out .= '</div>';
    $out .= '</div></div>';
    return $out;
}

/**
 * URL for add/remove template question (with sesskey – caller must pass sesskey in page).
 *
 * @param int $id cm id
 * @param int $templateid
 * @param int $jobid
 * @param bool $spacui
 * @param string $action 'add' or 'remove'
 * @param int $questionid
 * @return moodle_url
 */
function oralinterview_template_question_action_url(int $id, int $templateid, int $jobid, bool $spacui, string $action, int $questionid): moodle_url {
    $params = [
        'id' => $id, 'templateid' => $templateid, 'action' => $action, 'questionid' => $questionid,
        'step' => 2, 'jobid' => $jobid, 'sesskey' => sesskey(),
    ];
    if ($spacui) {
        $params['spacui'] = 1;
    }
    return new moodle_url('/mod/oralinterview/template_questions_advanced.php', $params);
}

/**
 * Plain-text snippet of question text (no HTML, max length). For display in lists.
 *
 * @param string $questiontext
 * @param int $max
 * @return string
 */
function oralinterview_short_question_text(string $questiontext, int $max = 80): string {
    $text = strip_tags($questiontext);
    $text = trim($text);
    if ($text === '') {
        return get_string('notapplicable', 'oralinterview');
    }
    if (core_text::strlen($text) <= $max) {
        return $text;
    }
    return core_text::substr($text, 0, $max) . '…';
}
