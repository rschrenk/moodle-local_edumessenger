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

$observers = array();
$events = array(
    "\\mod_forum\\event\\discussion_created",
    "\\mod_forum\\event\\discussion_deleted",
    "\\mod_forum\\event\\post_created",
    "\\mod_forum\\event\\post_deleted",
    "\\mod_forum\\event\\post_updated",
    "\\core\\event\\message_sent",
    "\\core\\event\\message_deleted",
);
foreach ($events AS $event) {
    $observers[] = array(
            'eventname' => $event,
            'callback' => '\local_edumessenger\observer::event',
            'priority' => 9999,
        );
}

// Old observer.

$events = array(
    "\\core\\event\\course_created",
    "\\core\\event\\course_deleted",
    "\\core\\event\\group_created",
    "\\core\\event\\group_deleted'",
    "\\core\\event\\group_member_added",
    "\\core\\event\\group_member_removed",
    "\\core\\event\\course_module_created",
    "\\core\\event\\course_module_deleted",
    "\\mod_forum\\event\\discussion_created",
    "\\mod_forum\\event\\discussion_deleted",
    // "\\mod_forum\\event\\assessable_uploaded",
    "\\mod_forum\\event\\post_updated",
    "\\mod_forum\\event\\post_created",
    "\\mod_forum\\event\\post_deleted",
    "\\core\\event\\message_sent",
    "\\core\\event\\message_deleted",
);

foreach ($events AS $event) {
    $observers[] = array(
            'eventname' => $event,
            'callback' => '\local_edumessenger\edumessenger_observer::event',
            'priority' => 9999,
        );
}
