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
 * @copyright  2017 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");

/**
** add function for get_capatility moodle/site:accessallgroups in course
**/
class local_edumessenger_external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function amount_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * @return int amount of active users in this moodle.
     */
    public static function amount() {
        global $DB;
        $entries = $DB->get_records_sql('SELECT COUNT(id) AS amount FROM {user} WHERE confirmed=1 AND deleted=0 AND suspended=0', array());
        $k = array_keys($entries);
        return $entries[$k[0]]->amount;
    }
    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function amount_returns() {
        return new external_value(PARAM_INT, 'Returns the amount of active users in your moodle instance.');
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function enableuser_parameters() {
        return new external_function_parameters(
            array(
                'entries' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'userid' => new external_value(PARAM_INT, 'id of user'),
                            'enabled' => new external_value(PARAM_INT, '1 to enable, all other values to disable user'),
                        )
                    )
                )
            )
        );
    }

    /**
     * @return array With all user operations.
     */
    public static function enableuser($entries) {
        global $DB;
        $params = self::validate_parameters(self::enableuser_parameters(), array('entries' => $entries));
        $reply = array();
        foreach($params['entries'] AS $entry) {
            $entry = (object) $entry;
            if (!isset($entry->userid) || $entry->userid == 0) continue;
            $entry->enabled = (isset($entry->enabled) && $entry->enabled == 1) ? 1 : 0;
            $hasrecord = $DB->record_exists('edumessenger_userid_enabled', array('userid' => $entry->userid));
            if ($entry->enabled == 1 && !$hasrecord) {
                $DB->insert_record('edumessenger_userid_enabled', (object)$entry);
            } elseif($entry->enabled == 0 && $hasrecord) {
                $DB->update_record('edumessenger_userid_enabled', (object)$entry);
            }
            $status = $DB->get_record('edumessenger_userid_enabled', array('userid' => $entry->userid));
            $reply[$status->userid] = array('userid' => $status->userid, 'enabled' => $status->enabled);
        }
        return $reply;
    }
    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function enableuser_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'userid' => new external_value(PARAM_INT, 'id of user', VALUE_REQUIRED),
                    'enabled' => new external_value(PARAM_INT, '1 to enable, all other values to disable user', VALUE_REQUIRED),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function ping_parameters() {
        return new external_function_parameters(array());
    }

    /**
     * Calls the cron-function in object taskhelper.
     * @return boolean true if cron was called.
     */
    public static function ping() {
        global $CFG;
        require_once($CFG->dirroot . "/local/edumessenger/classes/task/taskhelper.php");
        $task = new \local_edumessenger\task\local_edumessenger_taskhelper();
        // We have to prohibit debugmode as it would break our return value!
        $task->debugmode = false;
        return $task->cron();
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function ping_returns() {
        return new external_value(PARAM_BOOL, 'Returns the result of taskhelper=>cron, normally true');
    }
}
