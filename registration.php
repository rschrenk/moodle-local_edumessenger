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
 * Links and settings
 * @package    localedumessenger
 * @copyright  2017 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

require_once('registration_form.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/local/edumessenger/lib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/edumessenger/pages/registration.php', array());
$PAGE->set_title('eduMessenger Registration');
$PAGE->set_heading('eduMessenger Registration');

echo $OUTPUT->header();

if (!has_capability('local/edumessenger:manage', context_system::instance())) {
    echo "<p class=\"alert alert-error\">" . get_string('missing_capability', 'local_edumessenger') . "</p>";
    echo $OUTPUT->footer();
    exit;
}

$edm = new local_edumessenger();
$regform = new edumessenger_registration_form();
if ($formdata = $regform->get_data()) {
    if ($regform->store($edm, $formdata)) {
        echo "<p class=\"alert alert-success\">" . get_string('changes_saved', 'local_edumessenger') . "</p>";
    } else {
        echo "<p class=\"alert alert-error\">" . get_string('changes_note_saved', 'local_edumessenger') . "</p>";
    }
}

$auth = $edm->site_auth();

$devmode = optional_param('developermode', -1, PARAM_INT);
if ($devmode > -1) {
    $edm->set('developermode', $devmode);
    echo "<p class=\"alert alert-success\">Developermode " . (($devmode == '0') ? 'disabled' : 'enabled') . "</p>";
}

$createtoken = optional_param('create_token', -1, PARAM_INT);
if ($createtoken == 1) {
    if ($edm->site_register()) {
        echo "<p class=\"alert alert-success\">" . get_string('token:changed', 'local_edumessenger') . "</p>";
        $auth = $edm->site_auth();
    } else {
        echo "<p class=\"alert alert-error\">" . get_string('token:failed', 'local_edumessenger') . "</p>";
    }
}

$communicationtocentral = (@$auth->status == 'ok' && $edm->secret() != '') ? 1 : 0;
$communicationtomoodle = ($edm->servicetoken != '') ? 1 : 0;
$communicationimage = $CFG->wwwroot . '/local/edumessenger/img/' . $communicationtocentral . $communicationtomoodle . ".gif";

?>
<h3><?php echo get_string('step1:head', 'local_edumessenger'); ?></h3>
<p><?php echo get_string('step1:p1', 'local_edumessenger'); ?></p>
<p><?php echo get_string('step1:p2', 'local_edumessenger'); ?></p>
<table border="0" width="100%"  cellpadding="3"
       style="background-color: #f5f5f5; border: 1px solid rgba(0, 0, 0, 0.15);border-radius: 4px;padding: 9.5px;">
    <tr>
        <td align="center"><?php echo get_string('lbl:justclick', 'local_edumessenger'); ?></td>
        <td align="center">&nbsp;</td>
        <td align="center"><?php echo get_string('lbl:createwebservicetoken', 'local_edumessenger'); ?></td>
    </tr>
    <tr>
        <td width="40%" style="margin: 0px; text-align: center"
            class="alert <?php echo ($communicationtocentral) ? 'alert-success' : 'alert-error'; ?>">
            <input type="button"
            value="<?php echo get_string((($communicationtocentral) ? 'renew_token' : 'create_token'), 'local_edumessenger'); ?>"
            onclick="top.location.href='<?php echo $CFG->wwwroot; ?>/local/edumessenger/registration.php?create_token=1'" />
        </td>
        <td><img src="<?php echo $communicationimage; ?>" style="width: 100%;" /></td>
        <td width="40%" style="margin: 0px; text-align: center;"
            class="alert <?php echo ($communicationtomoodle) ? 'alert-success' : 'alert-error'; ?>">
            <input type="button" value="<?php echo get_string('control_token', 'local_edumessenger'); ?>"
                onclick="top.location.href='<?php echo $CFG->wwwroot; ?>/admin/webservice/tokens.php?action=create'"/>
        </td>
    </tr>
</table>

<?php

switch(@$auth->status) {
    case 'not_registered':
        ?>
        <p>
            <?php echo get_string('step1:not_registered', 'local_edumessenger'); ?>  
        </p>
        <?php
    break;
    case 'wrong_pwd':
        ?>
        <p>
            <?php echo get_string('step1:wrong_pwd', 'local_edumessenger'); ?>
        </p>
        <?php
    break;
    case 'ok':
        if ($edm->servicetoken != "") {
        ?>
        <h3><?php echo get_string('step2:head', 'local_edumessenger'); ?></h3>
        <div class="alert alert-<?php echo ($auth->instance->active) ? 'success' : 'error'; ?>">
            <p><?php
            if ($auth->instance->active) {
                echo get_string('step2:p0' . (($auth->instance->active == 1) ? 'active' : 'inactive'), 'local_edumessenger');
            } else {
                echo get_string('step2:p1', 'local_edumessenger');
            }
            ?></p>
            <input type="button" onclick="location.href='<?php echo $edm->regtool(); ?>'"
                   value="<?php echo get_string('step2:ptool', 'local_edumessenger'); ?>" style="width: 100%;" />
        </div>
        <?php
        } // End if edm->servicetoken!="".
        ?>
        <p>
        <?php echo get_string('step2:p2', 'local_edumessenger'); ?>
        </p>
        <img src="<?php echo $edm->url . "/profile/" . $auth->instance->logo . ".png"; ?>"
             alt="Your Logo" style="position: absolute; right: 30px; max-height: 5em;" />
        <table border="0" width="100%">
            <tr>
                <th valign="top"><?php echo get_string('Title', 'local_edumessenger'); ?></th>
                <td valign="top"><?php echo $auth->instance->title; ?></td>
            </tr>
            <tr>
                <th valign="top"><?php echo get_string('Contact', 'local_edumessenger'); ?></th>
                <td valign="top"><?php echo $auth->instance->contact; ?></td>
            </tr>
            <tr>
                <th valign="top"><?php echo get_string('Webauth', 'local_edumessenger'); ?></th>
                <td valign="top">
<?php echo ($auth->instance->webauth) ? get_string('yes', 'local_edumessenger') : get_string('no', 'local_edumessenger'); ?>
                </td>
            </tr>
            <tr>
                <th valign="top"><?php echo get_string('Contingent', 'local_edumessenger'); ?></th>
                <td valign="top">
<?php
echo $auth->instance->contingent . ' '
    . get_string('user' . (($auth->instance->contingent > 0) ? 's' : ''), 'local_edumessenger');
?>
                <sup>1)</sup></td>
            </tr>
            <tr>
                <th valign="top"><?php echo get_string('Description', 'local_edumessenger'); ?></th>
                <td valign="top"><?php echo $auth->instance->description; ?></td>
            </tr>
        </table>
        <p><sup>1) <?php echo get_string('contingent:info', 'local_edumessenger'); ?></sup></p>
        <?php
    break;
}
?>

<p><?php echo get_string('step1:p3', 'local_edumessenger'); ?></p>
<div style="background-color: #f5f5f5; border: 1px solid rgba(0, 0, 0, 0.15);border-radius: 4px;padding: 9.5px;">
    <?php $regform->display(); ?>
</div>
<?php

$edm->messages_show();

echo $OUTPUT->footer();