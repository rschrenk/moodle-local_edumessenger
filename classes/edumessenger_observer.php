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
        global $CFG, $DB;
        require_once($CFG->dirroot . "/local/edumessenger/lib.php");
        require_once($CFG->dirroot . "/local/edumessenger/classes/task/taskhelper.php");
        $task = new \local_edumessenger\task\taskhelper();
        // We have to prohibit debugmode as it would break our return value!
        $task->debugmode = false;
        $entry = (object)$event->get_data();

        $pushobject = array(
            'message' => '',
            'subject' => '',
            'targetuserids' => '',
        );

        switch ($entry->eventname) {
            case "\\mod_forum\\event\\post_created":
            case "\\mod_forum\\event\\discussion_created":
                if ($entry->eventname == "\\mod_forum\\event\\post_created") {
                    $post = $DB->get_record("forum_posts", array("discussion" => $entry->objectid, "parent" => 0));
                    $discussion = $DB->get_record("forum_discussions", array("id" => $post->discussion));
                } else {
                    $discussion = $DB->get_record("forum_discussions", array("id" => $entry->objectid));
                    $post = $DB->get_record("forum_posts", array("discussion" => $discussion->id, "parent" => 0));
                }

                local_edumessenger_lib::enhance_discussion($discussion);
                local_edumessenger_lib::enhance_post($post);
                $pushobject->message = $post->message;
                $pushobject->subject = $discussion->subject;

                $context = context_course::instance($discussion->course);
                $coursemembers = array_keys(get_enrolled_users($context, '', 0, 'u.id'));
                $sql = "SELECT id
                            FROM {user} u, {local_edumessenger_tokens} let
                            WHERE u.id=let.userid
                                AND u.id IN (?)";
                $pushobject->targetuserids = $DB->get_records_sql($sql, array(implode(',', $coursemembers)));
                $qitem = array(
                    'created' => time(),
                    'id' => 0,
                    'json' => json_encode($pushobject),
                );
                $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem);
            break;
            case "\\core\\event\\message_sent":
                $message = $DB->get_record('messages', array('id' => $entry->objectid));
                local_edumessenger_lib::enhance_message($message);

                $pushobject->message = $message->fullmessagehtml;
                $pushobject->subject = \mb_strimwidth($message->fullmessagehtml, 0, 20);
                $pushobject->targetuserids = array_keys($DB->get_records('message_conversation_members', array('conversationid' => $message->conversationid)));
                $qitem = array(
                    'created' => time(),
                    'id' => 0,
                    'json' => json_encode($pushobject),
                );
                $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem);
            break;
        }

        if (!empty($qitem->id)) {
            // Send item.
            local_edumessenger_lib::sendQitem($qitem);
        }

        return true;
    }

}
