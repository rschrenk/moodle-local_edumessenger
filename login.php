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
 * This page handles the login for eduvidualapp.
 *
 * @package    local_eduvidualapp
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Allow Access of AJAX-Queries.
header('Access-Control-Allow-Origin: *');

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$edmtoken = required_param('edmtoken', PARAM_TEXT);
$act = optional_param('act', 'login', PARAM_TEXT);

$context = context_system::instance();

$PAGE->set_url('/local/edumessenger/login.php?edmtoken=' . $edmtoken . '&act=' . $act);
$PAGE->set_context($context);

switch ($act) {
    case 'getuser':
        $o = array();
        $chk = $DB->get_record('local_edumessenger_tokens', array('edmtoken' => $edmtoken, 'redeemed' => 0));
        if (!empty($chk->userid)) {
            $user = $DB->get_record('user', array('id' => $chk->userid));
            // We can reveal the edmtoken.
            if (!empty($user->id)) {
                $chk->redeemed++;
                $context = context_user::instance($user->id);
                $DB->update_record('local_edumessenger_tokens', $chk);
                $site = get_site();
                $o = array(
                    'edmtoken' => $edmtoken,
                    'sitename' => $site->fullname,
                    'userid' => $user->id,
                    'wwwroot' => $CFG->wwwroot,
                );
            } else {
                $o = array('error' => 'user_does_not_exist', 'userid' => $chk->userid);
            }
        } else {
            $o = array('error' => 'no_data_for_edmtoken');
        }
        die(json_encode($o, JSON_NUMERIC_CHECK));
    break;
    case 'login':
        if ($USER->id == 0 || isguestuser($USER)) {
            $SESSION->wantsurl = $PAGE->url;
            redirect(get_login_url());
            echo $OUTPUT->header();
            $params = array(
                'content' => get_string('login:required_login', 'local_edumessenger'),
                'script' => 'location.href = "' . get_login_url() . '";',
                'type' => 'success',
                'url' => get_login_url(),
            );
            echo $OUTPUT->render_from_template('local_eduvidualapp/alert', $params);
            echo $OUTPUT->footer();
        } else {
            $o = $DB->get_record('local_edumessenger_tokens', array('userid' => $USER->id, 'edmtoken' => $edmtoken));
            if (empty($o->userid)) {
                $o = array('userid' => $USER->id, 'edmtoken' => $edmtoken, 'redeemed' => 0, 'created' => time());
                $DB->insert_record('local_edumessenger_tokens', (object)$o);
            }
            echo $OUTPUT->header();
            $params = array(
                'content' => get_string('login:successful', 'local_edumessenger'), // . $edmtoken,
                'script' => 'window.top.close(); /* If we are in a webapp, close popup. */',
                'type' => 'success',
                'url' => 'javascript:window.top.close();',
            );
            echo $OUTPUT->render_from_template('local_eduvidualapp/alert', $params);
            echo $OUTPUT->footer();
            $authsequence = get_enabled_auth_plugins(); // auths, in sequence
            foreach($authsequence as $authname) {
                $authplugin = get_auth_plugin($authname);
                $authplugin->logoutpage_hook();
            }
            require_logout();
        }
    break;
}
