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
 * @package   localedumessenger
 * @copyright 2017 Digital Education Society (http://www.dibig.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'eduMessenger';
$string['data:manage'] = 'eduMessenger Registration';
$string['cron:title'] = 'eduMessenger Cron';
$string['missing_capability'] = 'Missing required Capability';
$string['submit'] = 'Submit';
$string['secret_token'] = 'Secret Token';
$string['create_token'] = 'Create Token';
$string['renew_token'] = 'Renew Token';
$string['control_token'] = 'Your Token';
$string['developermode'] = 'Developer Mode';
$string['debugmode'] = 'Debug Mode';
$string['register'] = 'Register this Instance';
$string['changes_saved'] = 'Changes saved';
$string['changes_not_saved'] = 'Changes not saved';

$string['token:changed'] = 'Token created/renewed';
$string['token:failed'] = 'Connection error';

$string['lbl:justclick'] = 'Just click the following button!';
$string['lbl:createwebservicetoken'] = 'Go to <a href="'.$CFG->wwwroot.'/admin/webservice/tokens.php?action=create">Webservice Tokens</a>, create one and make sure you select a user with administrative privileges and the Webservice \'eduMessenger\'!';

$string['step1:head'] = 'Step 1 - Secure Communication';
$string['step1:p1'] = 'You need two secret tokens to setup the communication between your Moodle and eduMessenger. One token authenticates you against eduMessenger Central, and the other one allows eduMessenger to interact with your Moodle.';
$string['step1:p2'] = 'Click the following button to create your first token, or to change your existing one!';
$string['step1:p3'] = 'If you recovered from a data-loss and want to enter your token you can edit it manually. <strong>Normally you do not have to do this!</strong>';

$string['step1:not_registered'] = 'You are not registered - please click the button "Create Token" to setup communication with eduMessenger Central.';
$string['step1:wrong_pwd'] = 'Sorry, your secret is not setup correctly. If you can not recover your secret please contact <a href="mailto:office@dibig.at">office@dibig.at</a> for further instructions.';

$string['step2:head'] = 'Step 2 - Registration at eduMessenger Central';
$string['step2:p0active'] = 'Your Moodle is available within eduMessenger';
$string['step2:p0inactive'] = 'Your Moodle is NOT available within eduMessenger';
$string['step2:p1'] = 'In order to activate your Instance in eduMessenger you need to open the following Management Console:';
$string['step2:ptool'] = 'Management Console';
$string['step2:p2'] = 'Your current Data is as follows:';

$string['error:wrong_token'] = 'This action can not be done without the correct token';

$string['Title'] = 'Title';
$string['Contact'] = 'Contact';
$string['Webauth'] = 'Webauth';
$string['Contingent'] = 'Contingent';
$string['Description'] = 'Description';
$string['yes'] = 'Yes';
$string['no'] = 'No';
$string['user'] = 'user';
$string['users'] = 'users';

$string['contingent:info'] = 'This is the amount of users that can connect their moodle accounts to eduMessenger and use it for 1 year.';