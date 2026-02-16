<?php
/**
 * Session scheduling helpers – rooms, exceptions, auto-assignment.
 *
 * @package   mod_oralinterview
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Ensure global rooms exist (Room 1, Room 2, Room 3). System-wide, not per-interview.
 *
 * @param int $oralinterviewid unused; kept for API compatibility
 * @return array room records from oralint_room
 */
function oralinterview_schedule_ensure_rooms(int $oralinterviewid): array {
    global $DB;
    if (!$DB->get_manager()->table_exists('oralint_room')) {
        return [];
    }
    $existing = $DB->get_records('oralint_room', null, 'sortorder ASC');
    if (count($existing) >= 3) {
        return array_values($existing);
    }
    $names = ['غرفة 1', 'غرفة 2', 'غرفة 3'];
    for ($i = 0; $i < 3; $i++) {
        $ord = $i + 1;
        if (!$DB->record_exists('oralint_room', ['sortorder' => $ord])) {
            $DB->insert_record('oralint_room', (object)['name' => $names[$i], 'sortorder' => $ord]);
        }
    }
    return array_values($DB->get_records('oralint_room', null, 'sortorder ASC'));
}

/**
 * Get rooms (global oralint_room). num_rooms (1, 2, or 3) limits how many are used.
 *
 * @param int $oralinterviewid unused; kept for API compatibility
 * @param int $limit 1, 2, or 3 – how many rooms to return
 * @return array
 */
function oralinterview_schedule_get_rooms(int $oralinterviewid, int $limit = 3): array {
    global $DB;
    oralinterview_schedule_ensure_rooms($oralinterviewid);
    $all = $DB->get_records('oralint_room', null, 'sortorder ASC');
    return array_slice(array_values($all), 0, max(1, min(3, $limit)));
}

/**
 * Count candidates for an interview (those in the scheduling pool).
 * These are the candidates auto-synced from the approved job when the interview was created
 * (one oralint_session per candidate). Same pool shown in "المرشحون" / candidates tab.
 *
 * @param int $oralinterviewid oralinterview instance id
 * @return int
 */
function oralinterview_schedule_count_candidates(int $oralinterviewid): int {
    global $DB;
    return $DB->count_records('oralint_session', ['oralinterviewid' => $oralinterviewid]);
}

/**
 * Check if a time slot overlaps with any exception on the given date.
 *
 * @param int $oralinterviewid
 * @param string $date Y-m-d
 * @param string $start HH:mm
 * @param string $end HH:mm
 * @return bool true if overlapping
 */
function oralinterview_schedule_slot_in_exception(int $oralinterviewid, string $date, string $start, string $end): bool {
    global $DB;
    $date = trim($date);
    if ($date === '') {
        return false;
    }
    $exceptions = $DB->get_records('oralint_schedule_exceptions', [
        'oralinterviewid' => $oralinterviewid,
    ]);
    foreach ($exceptions as $ex) {
        $ex_date = trim((string) ($ex->exception_date ?? ''));
        if ($ex_date !== $date) {
            continue;
        }
        $ex_start = trim((string) ($ex->start_time ?? ''));
        $ex_end = trim((string) ($ex->end_time ?? ''));
        if ($ex_start === '' || $ex_end === '' || !preg_match('/^\d{1,2}:\d{2}$/', $ex_start) || !preg_match('/^\d{1,2}:\d{2}$/', $ex_end)) {
            continue;
        }
        if (_time_ranges_overlap($start, $end, $ex_start, $ex_end)) {
            return true;
        }
    }
    return false;
}

/**
 * Internal: check if two time ranges overlap.
 */
function _time_ranges_overlap(string $a1, string $a2, string $b1, string $b2): bool {
    $amin = _time_to_minutes($a1);
    $amax = _time_to_minutes($a2);
    $bmin = _time_to_minutes($b1);
    $bmax = _time_to_minutes($b2);
    return $amin < $bmax && $bmin < $amax;
}

/**
 * Internal: HH:mm or H:mm to minutes since midnight.
 */
function _time_to_minutes(string $t): int {
    $t = trim($t);
    $p = explode(':', $t);
    $h = (int) ($p[0] ?? 0);
    $m = (int) (isset($p[1]) ? trim($p[1]) : 0);
    $h = max(0, min(23, $h));
    $m = max(0, min(59, $m));
    return $h * 60 + $m;
}

/**
 * Get the latest end time (in minutes) of any exception overlapping the given slot on the date.
 * Used to skip to the end of the exception so the next slot starts at 09:45 not 10:00.
 *
 * @param int $oralinterviewid
 * @param string $date Y-m-d
 * @param string $slot_start HH:mm
 * @param string $slot_end HH:mm
 * @return int minutes since midnight, or 0 if no overlap
 */
function _schedule_exception_overlap_end(int $oralinterviewid, string $date, string $slot_start, string $slot_end): int {
    global $DB;
    $date = trim($date);
    if ($date === '') {
        return 0;
    }
    $exceptions = $DB->get_records('oralint_schedule_exceptions', ['oralinterviewid' => $oralinterviewid]);
    $max_end = 0;
    foreach ($exceptions as $ex) {
        $ex_date = trim((string) ($ex->exception_date ?? ''));
        if ($ex_date !== $date) {
            continue;
        }
        $ex_start = trim((string) ($ex->start_time ?? ''));
        $ex_end = trim((string) ($ex->end_time ?? ''));
        if ($ex_start === '' || $ex_end === '' || !preg_match('/^\d{1,2}:\d{2}$/', $ex_start) || !preg_match('/^\d{1,2}:\d{2}$/', $ex_end)) {
            continue;
        }
        if (_time_ranges_overlap($slot_start, $slot_end, $ex_start, $ex_end)) {
            $max_end = max($max_end, _time_to_minutes($ex_end));
        }
    }
    return $max_end;
}

/**
 * Generate available slots for a day: from start_time to end_time, slot_duration each.
 * Excludes slots overlapping exceptions. When a slot overlaps an exception, the next slot
 * starts at the exception end (e.g. 09:45) so we don't waste time.
 *
 * @param int $oralinterviewid
 * @param string $date Y-m-d
 * @param string $start HH:mm
 * @param string $end HH:mm
 * @param int $slot_minutes
 * @return array [['start'=>'09:00','end'=>'09:20'], ...]
 */
function oralinterview_schedule_generate_slots(
    int $oralinterviewid,
    string $date,
    string $start,
    string $end,
    int $slot_minutes
): array {
    $slots = [];
    $s = _time_to_minutes($start);
    $e = _time_to_minutes($end);
    if ($s >= $e || $slot_minutes <= 0) {
        return [];
    }
    $current = $s;
    while ($current + $slot_minutes <= $e) {
        $slot_start = sprintf('%02d:%02d', (int) floor($current / 60), $current % 60);
        $slot_end   = sprintf('%02d:%02d', (int) floor(($current + $slot_minutes) / 60), ($current + $slot_minutes) % 60);
        if (oralinterview_schedule_slot_in_exception($oralinterviewid, $date, $slot_start, $slot_end)) {
            $skip_to = _schedule_exception_overlap_end($oralinterviewid, $date, $slot_start, $slot_end);
            if ($skip_to > $current) {
                $current = $skip_to;
            } else {
                $current += $slot_minutes;
            }
            continue;
        }
        $slots[] = ['start' => $slot_start, 'end' => $slot_end];
        $current += $slot_minutes;
    }
    return $slots;
}

/**
 * Auto-assign candidates to slots across days and rooms.
 * Returns { assignments: [...], errors: [...], total_slots: int, total_candidates: int }.
 * Same start/end times and exception times apply to all rooms (1, 2, or 3).
 *
 * @param int $oralinterviewid
 * @param int $num_rooms 1, 2, or 3
 * @param string[] $selected_dates ['Y-m-d', ...]
 * @param string $start_time HH:mm
 * @param string $end_time HH:mm
 * @param int $slot_minutes
 * @return array
 */
function oralinterview_schedule_auto_assign(
    int $oralinterviewid,
    int $num_rooms,
    array $selected_dates,
    string $start_time,
    string $end_time,
    int $slot_minutes
): array {
    global $DB, $USER;

    $result = [
        'assignments'      => [],
        'errors'           => [],
        'total_slots'      => 0,
        'total_candidates' => 0,
    ];

    if ($num_rooms < 1 || $num_rooms > 3) {
        $result['errors'][] = 'عدد الغرف يجب أن يكون 1 أو 2 أو 3.';
        return $result;
    }
    if (empty($selected_dates)) {
        $result['errors'][] = 'يجب اختيار تاريخ واحد على الأقل.';
        return $result;
    }
    $start_min = _time_to_minutes($start_time);
    $end_min   = _time_to_minutes($end_time);
    if ($start_min >= $end_min) {
        $result['errors'][] = 'وقت البداية يجب أن يكون قبل وقت النهاية.';
        return $result;
    }
    if ($slot_minutes <= 0) {
        $result['errors'][] = 'مدة المقابلة يجب أن تكون أكبر من صفر.';
        return $result;
    }

    $rooms = oralinterview_schedule_get_rooms($oralinterviewid, $num_rooms);
    if (count($rooms) < $num_rooms) {
        $result['errors'][] = 'لم يتم العثور على الغرف المطلوبة.';
        return $result;
    }

    // Get candidates (distinct from oralint_session).
    $sessions = $DB->get_records('oralint_session', ['oralinterviewid' => $oralinterviewid], 'id ASC', 'id,candidateid');
    $candidates = [];
    foreach ($sessions as $s) {
        if ($s->candidateid > 0 && !isset($candidates[$s->candidateid])) {
            $candidates[$s->candidateid] = (int) $s->candidateid;
        }
    }
    $candidate_ids = array_values($candidates);
    shuffle($candidate_ids);
    $result['total_candidates'] = count($candidate_ids);

    // One timeline for all rooms (in sync): capacity = slots_per_day × days, not × rooms.
    $all_slots = [];
    foreach ($selected_dates as $date) {
        $day_slots = oralinterview_schedule_generate_slots(
            $oralinterviewid, $date, $start_time, $end_time, $slot_minutes
        );
        foreach ($day_slots as $slot) {
            $all_slots[] = [
                'date'   => $date,
                'start'  => $slot['start'],
                'end'    => $slot['end'],
            ];
        }
    }
    $result['total_slots'] = count($all_slots);

    if ($result['total_slots'] === 0) {
        $result['errors'][] = 'لا توجد مواعيد متاحة بعد استبعاد الأوقات المستثنية. تأكد من أن وقت البداية والنهاية يترك فترات كافية خارج الاستراحة.';
        return $result;
    }
    if ($result['total_slots'] < $result['total_candidates']) {
        $result['errors'][] = 'لا توجد ساعات كافية في الأيام المحددة. عدد المرشحين (' . $result['total_candidates'] . ') أكبر من عدد المواعيد المتاحة (' . $result['total_slots'] . '). يرجى اختيار يوم إضافي أو تقليل مدة المقابلة أو تقليل الأوقات المستثنية.';
        return $result;
    }

    // Assign room to each slot in round-robin so all rooms share the same start/end times.
    $room_ids = array_map(function ($r) { return (int) $r->id; }, $rooms);
    $num_rooms = count($room_ids);
    for ($i = 0; $i < count($all_slots); $i++) {
        $all_slots[$i]['roomid'] = $room_ids[$i % $num_rooms];
    }

    $now = time();
    for ($i = 0; $i < count($candidate_ids); $i++) {
        $slot = $all_slots[$i];
        $rec = (object)[
            'oralinterviewid'   => $oralinterviewid,
            'candidateid'       => $candidate_ids[$i],
            'session_date'      => $slot['date'],
            'roomid'            => $slot['roomid'],
            'slot_start_time'   => $slot['start'],
            'slot_end_time'     => $slot['end'],
            'oralint_session_id' => 0,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        $DB->insert_record('oralint_schedule_assignment', $rec);
        $result['assignments'][] = $rec;
    }

    // Save config for display.
    $config = $DB->get_record('oralint_schedule_config', ['oralinterviewid' => $oralinterviewid]);
    if (!$config) {
        $config = (object)[
            'oralinterviewid'      => $oralinterviewid,
            'num_rooms'            => $num_rooms,
            'start_time'           => $start_time,
            'end_time'             => $end_time,
            'slot_duration_minutes' => $slot_minutes,
            'selected_dates'       => json_encode($selected_dates),
            'timecreated'          => $now,
            'timemodified'         => $now,
            'usermodified'         => $USER->id,
        ];
        $config->id = $DB->insert_record('oralint_schedule_config', $config);
    } else {
        $config->num_rooms = $num_rooms;
        $config->start_time = $start_time;
        $config->end_time = $end_time;
        $config->slot_duration_minutes = $slot_minutes;
        $config->selected_dates = json_encode($selected_dates);
        $config->timemodified = $now;
        $config->usermodified = $USER->id;
        $DB->update_record('oralint_schedule_config', $config);
    }

    return $result;
}
