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

class observer {
    public static function event($event) {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/local/edumessenger/lib.php");

        //error_log("OBSERVER EVENT: " . print_r($event, 1));
        $entry = (object)$event->get_data();
        error_log("OBSERVER EVENT ENTRY: " . print_r($entry, 1));

        $pushobject = (object)array(
            'message' => '',
            'subject' => '',
            'targetuserids' => '',
            'wwwroot' => $CFG->wwwroot,
        );

        switch ($entry->eventname) {
            case "\\mod_forum\\event\\post_created":
            case "\\mod_forum\\event\\post_deleted":
            case "\\mod_forum\\event\\post_updated":
            case "\\mod_forum\\event\\discussion_created":
            case "\\mod_forum\\event\\discussion_deleted":
                if (substr($entry->eventname, 0, strlen("\\mod_forum\\event\\post_")) == "\\mod_forum\\event\\post_") {
                    $post = $DB->get_record("forum_posts", array("id" => $entry->objectid));
                    $discussion = $DB->get_record("forum_discussions", array("id" => $post->discussion));
                } else {
                    $discussion = $DB->get_record("forum_discussions", array("id" => $entry->objectid));
                    $post = $DB->get_record("forum_posts", array("discussion" => $discussion->id, "parent" => 0));
                }
                $forum = $DB->get_record("forum", array("id" => $discussion->forum));
                //error_log("DISCUSSION: " . print_r($discussion, 1));
                //error_log("POST: " . print_r($post, 1));

                \local_edumessenger_lib::enhance_discussion($discussion);
                \local_edumessenger_lib::enhance_post($post);

                // We only attach the text if it is not deleted!
                if (empty($post->deleted)) {
                    $pushobject->message = $post->message;
                    $pushobject->subject = $discussion->name;
                }

                $pushobject->courseid = $forum->course;
                $pushobject->forumid = $forum->id;
                $pushobject->discussionid = $discussion->id;
                $pushobject->postid = $post->id;

                $context = \context_course::instance($discussion->course);
                $coursemembers = array_keys(get_enrolled_users($context, '', 0, 'u.id'));
                //error_log("COURSEMEMBERS: " . print_r($coursemembers, 1));
                $sql = "SELECT DISTINCT(u.id)
                            FROM {user} u, {local_edumessenger_tokens} let
                            WHERE u.id=let.userid
                                AND u.id IN (" . implode(',', $coursemembers) . ")
                                AND u.id<>?";
                //error_log($sql);
                $targetuserids = array_keys($DB->get_records_sql($sql, array($post->userid)));
                $pushobject->targetuserids = array();

                $forum = $DB->get_record('forum', array('id' => $discussion->forum));
                $course = get_course($forum->course);
                $cm = get_fast_modinfo($course)->instances['forum'][$forum->id];
                $contextmodule = \context_module::instance($cm->id);
                foreach($targetuserids AS $tuid) {
                    if ($tuid == $post->userid) continue;
                    $user = \core_user::get_user($tuid);
                    if(forum_user_can_see_discussion($forum, $discussion, $contextmodule, $user)) {
                        $pushobject->targetuserids[] = $tuid;
                    };
                }

                $qitem = (object) array(
                    'created' => time(),
                    'id' => 0,
                    'json' => json_encode($pushobject),
                );
                $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
                error_log('Stored QItem: ' . print_r($qitem, 1));
            break;
            case "\\core\\event\\message_sent":
                $message = $DB->get_record('messages', array('id' => $entry->objectid));
                \local_edumessenger_lib::enhance_message($message);

                $pushobject->messageid = $message->id;
                $pushobject->conversationid = $message->conversationid;
                $pushobject->message = !empty($message->fullmessagehtml) ? $message->fullmessagehtml : $message->fullmessage;
                $pushobject->subject = \mb_strimwidth($pushobject->message, 0, 20);
                $targetuserids = $DB->get_records('message_conversation_members', array('conversationid' => $message->conversationid));
                $pushobject->targetuserids = array();
                foreach ($targetuserids AS $targetuserid) {
                    if ($targetuserid == $entry->userid) continue;
                    $pushobject->targetuserids[] = $targetuserid->userid;
                }
                $qitem = (object) array(
                    'created' => time(),
                    'id' => 0,
                    'json' => json_encode($pushobject),
                );
                $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
            break;
        }

        //error_log("QUITEM: " . print_r($qitem, 1));

        if (!empty($qitem->id)) {
            // Send item.
            \local_edumessenger_lib::sendQitem($qitem);
        }

        return true;
    }

}
