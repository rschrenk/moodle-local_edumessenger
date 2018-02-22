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

namespace local_edumessenger;

defined('MOODLE_INTERNAL') || die;

class edumessenger_observer {
    public static function event($event) {
        global $CFG;
        require_once($CFG->dirroot . "/local/edumessenger/classes/task/taskhelper.php");
        $task = new \local_edumessenger\task\local_edumessenger_taskhelper();
        // We have to prohibit debugmode as it would break our return value!
        $task->debugmode = false;
        $entry = (object)$event->get_data();
        return $task->enhanceEntry($entry);
    }

}
