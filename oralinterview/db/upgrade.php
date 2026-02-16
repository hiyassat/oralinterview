<?php
function xmldb_oralinterview_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    
    // Add oralinterviewid field to oralint_template table
    if ($oldversion < 2026010101) {
        $table = new xmldb_table('oralint_template');
        
        $field = new xmldb_field('oralinterviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for oralinterviewid
        $index = new xmldb_index('oralint_template_oralinterview', XMLDB_INDEX_NOTUNIQUE, ['oralinterviewid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_mod_savepoint(true, 2026010101, 'oralinterview');
    }
    
    // Add questionid field to oralint_template_q table
    if ($oldversion < 2026010102) {
        $table = new xmldb_table('oralint_template_q');
        
        $field = new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'templateid');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for questionid
        $index = new xmldb_index('oralint_tq_question', XMLDB_INDEX_NOTUNIQUE, ['questionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_mod_savepoint(true, 2026010102, 'oralinterview');
    }
    
    // Add questionid field to oralint_session_q table
    if ($oldversion < 2026010103) {
        $table = new xmldb_table('oralint_session_q');
        
        $field = new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'sessionid');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add index for questionid
        $index = new xmldb_index('oralint_session_q_question', XMLDB_INDEX_NOTUNIQUE, ['questionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_mod_savepoint(true, 2026010103, 'oralinterview');
    }
    
    // Add jobid field to oralint_template table
    if ($oldversion < 2026010106) {
        $table = new xmldb_table('oralint_template');
        
        $field = new xmldb_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'jobtitle');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Add foreign key for jobid
        $key = new xmldb_key('fk_template_job', XMLDB_KEY_FOREIGN, ['jobid'], 'approved_jobs', ['jobid']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }
        
        // Add index for jobid
        $index = new xmldb_index('oralint_template_jobid', XMLDB_INDEX_NOTUNIQUE, ['jobid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
        upgrade_mod_savepoint(true, 2026010106, 'oralinterview');
    }
    
    // Create job_competency_type table
    if ($oldversion < 2026010107) {
        $table = new xmldb_table('job_competency_type');
        
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('competency_id', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('competency_name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('competency_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'both');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_job_competency_job', XMLDB_KEY_FOREIGN, ['jobid'], 'approved_jobs', ['jobid']);
        $table->add_key('fk_job_competency_user', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        
        // Note: Foreign keys automatically create indexes, so we don't need manual ones for the same fields
        $table->add_index('idx_competency', XMLDB_INDEX_NOTUNIQUE, ['competency_id(50)']);
        $table->add_index('idx_type', XMLDB_INDEX_NOTUNIQUE, ['competency_type']);
        
        $dbman->create_table($table);
        
        upgrade_mod_savepoint(true, 2026010107, 'oralinterview');
    }

    // Add jobid field to oralinterview (activity) table.
    if ($oldversion < 2026010108) {
        $table = new xmldb_table('oralinterview');
        $field = new xmldb_field('jobid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'name');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add foreign key for jobid.
        $key = new xmldb_key('fk_oralinterview_job', XMLDB_KEY_FOREIGN, ['jobid'], 'approved_jobs', ['jobid']);
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        // Add index for jobid.
        $index = new xmldb_index('oralinterview_jobid', XMLDB_INDEX_NOTUNIQUE, ['jobid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026010108, 'oralinterview');
    }

    // Session scheduling: oralint_rooms, oralint_schedule_config, oralint_schedule_exceptions, oralint_schedule_assignment.
    if ($oldversion < 2026010110) {
        // oralint_rooms: rooms per interview (Room 1, Room 2, Room 3).
        $table = new xmldb_table('oralint_rooms');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('oralinterviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('oralint_rooms_oi', XMLDB_INDEX_NOTUNIQUE, ['oralinterviewid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // oralint_schedule_config: schedule settings per interview.
        $table = new xmldb_table('oralint_schedule_config');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('oralinterviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('num_rooms', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('start_time', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '09:00');
        $table->add_field('end_time', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '17:00');
        $table->add_field('slot_duration_minutes', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '20');
        $table->add_field('selected_dates', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('oralint_sched_config_oi', XMLDB_INDEX_NOTUNIQUE, ['oralinterviewid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // oralint_schedule_exceptions: exception times (breaks, unavailable).
        $table = new xmldb_table('oralint_schedule_exceptions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('oralinterviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('exception_date', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('start_time', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('end_time', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('oralint_sched_exc_oi', XMLDB_INDEX_NOTUNIQUE, ['oralinterviewid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // oralint_schedule_assignment: candidate assignments to slots.
        $table = new xmldb_table('oralint_schedule_assignment');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('oralinterviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('candidateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('session_date', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('roomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot_start_time', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot_end_time', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('oralint_session_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('oralint_sched_assign_oi', XMLDB_INDEX_NOTUNIQUE, ['oralinterviewid']);
        $table->add_index('oralint_sched_assign_room', XMLDB_INDEX_NOTUNIQUE, ['roomid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add roomid to oralint_session (nullable).
        $sess = new xmldb_table('oralint_session');
        $roomfield = new xmldb_field('roomid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'oralinterviewid');
        if (!$dbman->field_exists($sess, $roomfield)) {
            $dbman->add_field($sess, $roomfield);
        }

        upgrade_mod_savepoint(true, 2026010110, 'oralinterview');
    }

    // Migrate to global oralint_room (3 rooms system-wide, not per-interview).
    if ($oldversion < 2026010111) {
        // Create oralint_room (global) - 3 rows for the whole system.
        $table = new xmldb_table('oralint_room');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            $now = time();
            $DB->insert_record('oralint_room', (object)['name' => 'غرفة 1', 'sortorder' => 1]);
            $DB->insert_record('oralint_room', (object)['name' => 'غرفة 2', 'sortorder' => 2]);
            $DB->insert_record('oralint_room', (object)['name' => 'غرفة 3', 'sortorder' => 3]);
        }

        // Migrate assignments: map old oralint_rooms.id -> oralint_room.id by sortorder.
        if ($dbman->table_exists('oralint_rooms') && $dbman->table_exists('oralint_schedule_assignment')) {
            $assignments = $DB->get_records('oralint_schedule_assignment');
            foreach ($assignments as $a) {
                $old = $DB->get_record('oralint_rooms', ['id' => $a->roomid], 'sortorder');
                if ($old && $old->sortorder >= 1 && $old->sortorder <= 3) {
                    $DB->set_field('oralint_schedule_assignment', 'roomid', $old->sortorder, ['id' => $a->id]);
                }
            }
            $dbman->drop_table(new xmldb_table('oralint_rooms'));
        }

        upgrade_mod_savepoint(true, 2026010111, 'oralinterview');
    }

    return true;
}
