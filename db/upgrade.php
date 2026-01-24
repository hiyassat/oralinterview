<?php
function xmldb_oralinterview_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    return true;
}
