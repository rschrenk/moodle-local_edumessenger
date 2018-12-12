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

// We define the web service functions to install.
$functions = array(
    'local_edumessenger_amount' => array(
        'classname'   => 'local_edumessenger_external',
        'methodname'  => 'amount',
        'classpath'   => 'local/edumessenger/externallib.php',
        'description' => 'Returns the amount of active users in this moodle.',
        'type'        => 'read',
    ),
    'local_edumessenger_enableuser' => array(
        'classname'   => 'local_edumessenger_external',
        'methodname'  => 'enableuser',
        'classpath'   => 'local/edumessenger/externallib.php',
        'description' => 'Enables or disables a user for the edumessenger service.',
        'type'        => 'write',
    ),
    'local_edumessenger_ping' => array(
        'classname'   => 'local_edumessenger_external',
        'methodname'  => 'ping',
        'classpath'   => 'local/edumessenger/externallib.php',
        'description' => 'Calls edumessenger-Cron to assure faster push-notifications',
        'type'        => 'read',
    ),
);


// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
    'eduMessenger' => array(
        'functions' => array (
            'local_edumessenger_amount',
            'local_edumessenger_enableuser',
            'local_edumessenger_logout',
            'local_edumessenger_ping',
            'core_course_delete_courses',
            'core_course_duplicate_course',
            'core_course_get_categories',
            'core_course_get_courses',
            'core_course_import_course',
            'core_group_get_activity_allowed_groups',
            'core_user_create_users',
            'core_user_get_users',
            'core_user_get_users_by_field',
            'core_user_update_users',
            'core_webservice_get_site_info',
            'enrol_manual_enrol_users',
            'enrol_manual_unenrol_users',
            'mod_forum_get_forum_discussion_posts',
            'mod_forum_get_forum_discussions_paginated',
            'tool_mobile_get_public_config',
        ),
        'restrictedusers' => 0,
        'enabled' => 1,
    )
);
