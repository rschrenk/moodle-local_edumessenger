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
 * This page does an automated login and redirects to another site.
 *
 * @package    local_edumessenger
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

header("access-control-allow-origin: *");

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/edumessenger/lib.php');
require_once($CFG->dirroot . '/local/edumessenger/geilo.php');

$userid = optional_param('userid', 0, PARAM_INT);
$edmtoken = optional_param('edmtoken', '', PARAM_TEXT);
// This url should be base64 encoded.
$url = optional_param('url', '', PARAM_TEXT);


if (local_edumessenger_lib::check_token($userid, $edmtoken)) {
    redirect(base64_decode($url));
} else {
    $reply = (object) array(
        'status' => 'error',
        'error' => 'invalid_login',
        'userid' => $userid,
        'edmtoken' => $edmtoken,
        'data' => $url
    );
}

echo json_encode($reply, JSON_NUMERIC_CHECK);
