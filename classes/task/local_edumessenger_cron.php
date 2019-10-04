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

namespace local_edumessenger\task;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot."/local/edumessenger/classes/task/taskhelper.php");

class local_edumessenger_cron extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('cron:title', 'local_edumessenger');
    }

    public function execute() {
        global $DB;
        $items = $DB->get_records('local_edumessenger_queue', array());
        foreach ($items AS $item) {
            $pushobject = json_decode($item);
            // Now send it.
        }
        /*
        $helper = new local_edumessenger_taskhelper();
        $helper->cron();
        */
    }
}
