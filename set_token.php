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
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
header("access-control-allow-origin: *");

require_once("../../config.php");

$oursecrettoken = get_config('local_edumessenger', 'oursecrettoken');
$proveoursecrettoken = required_param('oursecrettoken', PARAM_TEXT);
$secrettoken = required_param('secrettoken', PARAM_TEXT);

if (!empty($oursecrettoken) && $oursecrettoken == $proveoursecrettoken) {
    set_config('secrettoken', $secrettoken, 'local_edumessenger');
    $DB->delete_records('config_plugins', array('plugin' => 'local_edumessenger', 'name' => 'outsecrettoken'));
    echo "1";
} else {
    echo "0";
}
