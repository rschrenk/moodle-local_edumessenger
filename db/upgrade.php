<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_edumessenger
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_local_edumessenger_upgrade($oldversion=0) {
    global $DB;
    $dbman = $DB->get_manager();

    $checkversion = 2018110201;
    error_log('local_edumessenger versionupgrade from ' . $oldversion . ' to ' . $checkversion);
    if ($oldversion < $checkversion) {
        $table = new xmldb_table('edumessenger_userid_enabled');

        // Adding fields to table edumessenger_userid_enabled.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table edumessenger_userid_enabled.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for edumessenger_userid_enabled.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Edumessenger savepoint reached.
        upgrade_plugin_savepoint(true, $checkversion, 'local', 'edumessenger');
    }
    return true;
}
