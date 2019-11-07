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

require_once($CFG->dirroot . "/local/edumessenger/lib.php");
require_once($CFG->dirroot . "/mod/forum/lib.php");

class observer {
    public static function event($event) {
        global $CFG, $DB;

        $allowpush = get_config('local_edumessenger', 'allowpush');
        if (!$allowpush) {
            if (\local_edumessenger_lib::debugging()) error_log("PUSH NOTIFICATIONS USING EDUMESSENGER ARE DISABLED BY ADMIN");
            return;
        }

        //error_log("OBSERVER EVENT: " . print_r($event, 1));
        $entry = (object)$event->get_data();
        if (\local_edumessenger_lib::debugging()) error_log("OBSERVER EVENT ENTRY: " . print_r($entry, 1));

        $pushobject = (object)array(
            'message' => '',
            'subject' => '',
            'targetuserids' => '',
            'wwwroot' => $CFG->wwwroot,
        );

        switch ($entry->eventname) {
            // We will test for discussion_deleted by checkig at event post_deleted if discussion still exists.
            //case "\\mod_forum\\event\\discussion_deleted":
            case "\\mod_forum\\event\\post_deleted":
                $pushobject->postid = $entry->objectid;
                $pushobject->courseid = $entry->courseid;
                $pushobject->discussionid = $entry->other['discussionid'];
                $pushobject->forumid = $entry->other['forumid'];
                $discussion = $DB->get_record("forum_discussions", array("id" => $pushobject->postid));
                if (empty($discussion->firstpost) || $discussion->firstpost == $pushobject->postid) {
                    // The whole discussion was deleted.
                    $pushobject->command = 'delete_discussion';
                    $pushobject->targetuserids = self::users_for_discussion($pushobject->discussionid);
                } else {
                    // A post was deleted.
                    $pushobject->command = 'delete_post';
                    $pushobject->targetuserids = self::users_for_discussion($pushobject->discussionid);
                }
                if ($pushobject->targetuserids[0] == -1) {
                    // We need to fallback and send a silent notification to all users of that course.
                    $forum = $DB->get_record("forum", array("id" => $pushobject->forumid));
                    $context = \context_course::instance($pushobject->courseid);
                    $coursemembers = array_keys(get_enrolled_users($context, '', 0, 'u.id'));
                    $sql = "SELECT DISTINCT(u.id)
                                FROM {user} u, {local_edumessenger_tokens} let
                                WHERE u.id=let.userid
                                    AND u.id IN (" . implode(',', $coursemembers) . ")";
                    //error_log($sql);
                    $pushobject->targetuserids = array_merge($pushobject->targetuserids, array_keys($DB->get_records_sql($sql, array())));
                }

                $qitem = (object) array(
                    'created' => time(),
                    'id' => 0,
                    'json' => json_encode($pushobject),
                );
                //$qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
                if (\local_edumessenger_lib::debugging()) error_log('Stored QItem: ' . print_r($qitem, 1));
            break;
            case "\\mod_forum\\event\\post_created":
            case "\\mod_forum\\event\\post_updated":
            case "\\mod_forum\\event\\discussion_created":
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

                $pushobject->message = $post->message;
                $pushobject->subject = $discussion->name;

                $pushobject->courseid = $forum->course;
                $pushobject->forumid = $forum->id;
                $pushobject->discussionid = $discussion->id;
                $pushobject->postid = $post->id;

                $pushobject->targetuserids = self::users_for_discussion($pushobject->discussionid, $entry->userid);

                $qitem = (object) array(
                    'created' => time(),
                    'id' => 0,
                    'json' => json_encode($pushobject),
                );
                if (count($pushobject->targetuserids) > 0) {
                    $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
                    if (\local_edumessenger_lib::debugging()) error_log('Stored QItem: ' . print_r($qitem, 1));
                } else {
                    if (\local_edumessenger_lib::debugging()) error_log('EMPTY LIST OF RECIPIENTS: ' . print_r($qitem, 1));
                }

                // Now we create a silent push notification for the author of the post.
                self::silent_message($pushobject, $entry->userid);
            break;
            case "\\core\\event\\message_sent":
                $message = $DB->get_record('messages', array('id' => $entry->objectid));
                \local_edumessenger_lib::enhance_message($message);

                $pushobject->messageid = $message->id;
                $pushobject->conversationid = $message->conversationid;
                $pushobject->message = !empty($message->fullmessagehtml) ? $message->fullmessagehtml : $message->fullmessage;
                $pushobject->subject = \mb_strimwidth($pushobject->message, 0, 20);
                $pushobject->targetuserids = self::users_for_conversation($pushobject->conversationid, $entry->userid);
                if (count($pushobject->targetuserids) > 0) {
                    $qitem = (object) array(
                        'created' => time(),
                        'id' => 0,
                        'json' => json_encode($pushobject),
                    );
                    $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
                    if (\local_edumessenger_lib::debugging()) error_log('Stored QItem: ' . print_r($qitem, 1));
                } else {
                    if (\local_edumessenger_lib::debugging()) error_log('EMPTY LIST OF RECIPIENTS: ' . print_r($qitem, 1));
                }
                $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
                // Now we create a silent push notification for the author of the post.
                self::silent_message($pushobject, $entry->userid);
            break;
        }

        //error_log("QUITEM: " . print_r($qitem, 1));

        if (!empty($qitem->id)) {
            // Send item.
            \local_edumessenger_lib::sendQitem($qitem);
        }

        return true;
    }

    /**
     * Test who can access a conversation.
     * @param conversationid
     * @param exceptuserid userid we will never include.
     */
    private static function users_for_conversation($conversationid, $exceptuserid) {
        global $DB;
        $mcms = $DB->get_records('message_conversation_members', array('conversationid' => $conversationid));
        foreach ($mcms AS $id => $mcm) {
            if ($mcm->userid == $exceptuserid) continue;
            $conversationmembers[] = $mcm->userid;
        }

        if (\local_edumessenger_lib::debugging()) error_log("CONVERSATIONMEMBERS: " . print_r($conversationmembers, 1));

        $sql = "SELECT DISTINCT(up.userid) AS userid
                    FROM {user_preferences} up
                    WHERE name LIKE 'message_provider_edumessenger_message'
                        AND value LIKE 'push'
                        AND up.userid IN (" . implode(',', $conversationmembers) . ")";
        if (\local_edumessenger_lib::debugging()) error_log($sql);
        return array_keys($DB->get_records_sql($sql, array()));
    }
    /**
     * Test who can access a discussion.
     * @param discussionid
     * @param exceptuserid userid we will never include.
     */
    private static function users_for_discussion($discussionid, $exceptuserid = 0) {
        global $DB;
        $discussion = $DB->get_record("forum_discussions", array("id" => $discussionid));
        if (empty($discussion->id)) return array(-1);

        $forum = $DB->get_record("forum", array("id" => $discussion->forum));
        $context = \context_course::instance($discussion->course);
        $coursemembers = array_keys(get_enrolled_users($context, '', 0, 'u.id'));
        if (\local_edumessenger_lib::debugging()) error_log("COURSEMEMBERS: " . print_r($coursemembers, 1));

        $sql = "SELECT DISTINCT(up.userid) AS userid
                    FROM {user_preferences} up
                    WHERE name LIKE 'message_provider_edumessenger_forum'
                        AND value LIKE 'push'
                        AND up.userid IN (" . implode(',', $coursemembers) . ")";
        if (\local_edumessenger_lib::debugging()) error_log($sql);
        $_targetuserids = array_keys($DB->get_records_sql($sql, array()));
        if (\local_edumessenger_lib::debugging()) error_log("COURSEMEMBERS using Messenger: " . print_r($_targetuserids, 1));
        if ($cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course)) {
            $contextmodule = \context_module::instance($cm->id);
            $targetuserids = array();
            foreach($_targetuserids AS $tuid) {
                if ($tuid == $exceptuserid) continue;
                $user = \core_user::get_user($tuid);
                if(\forum_user_can_see_discussion($forum, $discussion, $contextmodule, $user)) {
                    if (\local_edumessenger_lib::debugging()) error_log("User " . $tuid . " can access discussion");
                    $targetuserids[] = $tuid;
                } else {
                    if (\local_edumessenger_lib::debugging()) error_log("User " . $tuid . " can NOT access discussion");
                }
            }
            //if (\local_edumessenger_lib::debugging()) error_log("Want to send notification to " . print_r($targetuserids, 1));
            return $targetuserids;
        } else {
            return array();
        }
    }

    /**
     * Send a silent message.
     * @param pushobject
     * @param userid
     **/
    private static function silent_message($pushobject, $userid) {
        global $DB;
        $pref = $DB->get_record('user_preferences', array('name' => 'edumessenger_last_contact', 'userid' => $userid));
        if (!empty($pref->id) && $pref->value > (time() - 60*60*24*365)) {
            $pushobject->targetuserids = array($userid);
            $qitem = (object) array(
                'created' => time(),
                'id' => 0,
                'silent' => 1,
                'json' => json_encode($pushobject),
            );
            $qitem->id = $DB->insert_record('local_edumessenger_queue', $qitem, 1);
            if (\local_edumessenger_lib::debugging()) error_log('Stored QItem for event-userid: ' . print_r($qitem, 1));
        } else {
            if (\local_edumessenger_lib::debugging()) error_log('No silent message for userid: ' . $userid);
        }
    }

}
