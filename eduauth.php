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

defined('MOODLE_INTERNAL') || die;

/**
 * @package    local_edumessenger
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/local/edumessenger/lib.php');

class local_edumessenger_eduauth {
    public static function callforward($data, &$reply) {
        global $CFG, $DB, $USER;
        require_login();
        if (empty($data->act)) {
            return $reply;
        }
        switch ($data->act) {
            case 'create_discussion':
                require_once($CFG->dirroot . '/mod/forum/lib.php');
                $forum = $DB->get_record('forum', array('id' => $data->forumid), '*', MUST_EXIST);
                $group = null;
                if (!empty($data->groupd)) $group = $DB->get_record('groups', array('id' => $data->groupid), '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course);
                $context = context_module::instance($cm->id);
                if (forum_user_can_post_discussion($forum, $group, -1, $cm, $context)) {
                    local_edumessenger_lib::add_watermark($data->message, 1);
                    $discussion = (object) array(
                        'groupid' => !empty($group->id) ? $group->id : -1,
                        'userid' => $USER->id,
                        'created' => time(),
                        'modified' => time(),
                        'subject' => $data->topic, // required for post
                        'name' => $data->topic, // required for discussion
                        'message' => $data->message,
                        'messageformat' => 1, // We force HTML-format
                        'messagetrust' => true, // we do not know what this is! - there is no documentation on this!
                        'attachments' => null, // @todo add attachments filearea here
                        'forum' => $forum->id,
                        'course' => $forum->course,
                        'mailnow' => 0,
                    );
                    $reply->discussionid = forum_add_discussion($discussion);
                    if (!empty($reply->discussionid)) {
                        $reply->discussion = $DB->get_record('forum_discussions', array('id' => $reply->discussionid));
                        local_edumessenger_lib::enhance_discussion($reply->discussion);

                        // If we have potential users check who can access this discussion.
                        if (!empty($data->potentialusers) && count($data->potentialusers) > 0) {
                            $reply->discussion->accessusers = array();
                            $course = get_course($forum->course);
                            $cm = get_fast_modinfo($course)->instances['forum'][$forum->id];
                            $context = \context_module::instance($cm->id);
                            foreach ($data->potentialusers AS $potentialuserid) {
                                $user = \core_user::get_user($potentialuserid);
                                if (forum_user_can_see_discussion($forum, $reply->discussion, $context, $user)) {
                                    $reply->discussion->accessusers[] = $potentialuserid;
                                }
                            }
                        }
                    }
                }
            break;
            case 'create_message':
                $ismember = $DB->get_record('message_conversation_members', array('userid' => $USER->id, 'conversationid' => $data->conversationid));
                if (!empty($ismember->id)) {
                    local_edumessenger_lib::add_watermark($data->message, $data->messageformat);
                    if (local_edumessenger_lib::get_version() > 2018120300) { // Moodle 3.6
                        require_once($CFG->dirroot . '/message/lib.php');
                        require_once($CFG->dirroot . '/message/classes/api.php');
                        // Seems to be API in Moodle 3.7, not 3.5
                        $createdmessage = (object)core_message\api::send_message_to_conversation($USER->id, $data->conversationid, $data->message, $data->messageformat);
                        $reply->message = $createdmessage;
                    } elseif(local_edumessenger_lib::get_version() > 2016052300) { // Moodle 3.2
                        require_once($CFG->dirroot . "/message/lib.php");
                        $touser = $DB->get_record('user', array('id' => $data->touserid), '*', IGNORE_MISSING);
                        $messageid = 0;
                        try {
                            $messageid = message_post_message($USER, $touser, $data->message, $data->messageformat);
                        } catch(Exception $e) {
                            $reply->exception = $e;
                        }

                        if ($messageid > 0) {
                            $reply->message = $DB->get_record('messages', array('id' => $messageid));
                        } else {
                            $reply->error = 'message could not be sent';
                        }
                    } else {
                        $reply->error = get_string('incompatible_moodle_version', 'local_edumessenger') . ': ' . local_edumessenger_lib::get_version();
                    }
                    if (!empty($reply->message->id)) {
                        local_edumessenger_lib::enhance_message($reply->message);
                    }
                } else {
                    $reply->error = 'Not member of conversation';
                }
            break;
            case 'create_post':
                // forum_user_can_post() --> line 182 in forum/post.php
                require_once($CFG->dirroot . '/mod/forum/lib.php');
                $discussion = $DB->get_record('forum_discussions', array('id' => $data->discussionid), '*', MUST_EXIST);
                $reply->discussion = $discussion;
                $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
                $reply->forum = $forum;

                $course = get_course($forum->course);
                $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
                $context = context_module::instance($cm->id);

                global $USER;
                if (forum_user_can_post($forum, $discussion, $USER, $cm, $course)) {
                    $origmessage = $data->message;
                    local_edumessenger_lib::add_watermark($data->message, 1);
                    $post = (object) array(
                        'userid' => $USER->id,
                        'created' => time(),
                        'modified' => time(),
                        'subject' => mb_strimwidth(strip_tags($origmessage), 0, 30, "..."), // required for post
                        'message' => $data->message,
                        'messageformat' => 1, // We force HTML-format
                        'messagetrust' => true, // we do not know what this is! - there is no documentation on this!
                        'attachments' => null, // @todo add attachments filearea here
                        'discussion' => $discussion->id,
                        'forum' => $forum->id,
                        'course' => $forum->course,
                        'mailnow' => 0,
                    );
                    $reply->postid = forum_add_new_post($post, array());
                    if (!empty($reply->postid)) {
                        $reply->post = $DB->get_record('forum_posts', array('id' => $reply->postid));
                        local_edumessenger_lib::enhance_post($reply->post);
                        /*
                        $reply->post->courseid = $forum->course;
                        $reply->post->forumid = $forum->id;
                        $reply->post->discussionid = $discussion->id;
                        */
                    }
                }
            break;
            case 'get_conversation_messages':
                $ismember = $DB->get_record('message_conversation_members', array('userid' => $USER->id, 'conversationid' => $data->conversationid));
                if (!empty($ismember->id)) {
                    if (local_edumessenger_lib::get_version() > 2018120300) { // Moodle 3.6
                        require_once($CFG->dirroot . '/message/lib.php');
                        require_once($CFG->dirroot . '/message/classes/api.php');
                        $reply->messages = \core_message\api::get_conversation_messages($USER->id, $data->conversationid, null, null, null, $data->lastmodified);
                    } elseif(local_edumessenger_lib::get_version() > 2016052300) { // Moodle 3.2
                        $reply->messages = $DB->get_records_sql('SELECT * FROM {messages} WHERE conversationid=? AND timecreated>?', array($data->conversationid, $data->timemodified));
                        //$reply->messages = $DB->get_records_sql('SELECT * FROM {message} WHERE useridto=? OR useridfrom=?', array($USER->id, $USER->id));
                    } else {
                        $reply->error = get_string('incompatible_moodle_version', 'local_edumessenger') . ': ' . local_edumessenger_lib::get_version();
                    }
                    foreach ($reply->messages AS &$message) {
                        local_edumessenger_lib::enhance_message($message);
                    }
                } else {
                    $reply->ismember = $ismember;
                    $reply->data = $data;
                    $reply->error = 'Not member of conversation';
                }
            break;
            case 'get_conversations':
                $reply->conversations = $DB->get_records('message_conversation_members', array('userid' => $USER->id));
                foreach ($reply->conversations AS &$conversation) {
                    $conversation->members = $DB->get_records('message_conversation_members', array('conversationid' => $conversation->conversationid));
                    if (local_edumessenger_lib::get_version() > 2018120300) { // Moodle 3.6
                        require_once($CFG->dirroot . '/message/lib.php');
                        require_once($CFG->dirroot . '/message/classes/api.php');
                        $conversation->messages = \core_message\api::get_conversation_messages($USER->id, $conversation->conversationid);
                    } elseif(local_edumessenger_lib::get_version() > 2016052300) { // Moodle 3.2
                        $conversation->messages = $DB->get_records('messages', array('conversationid' => $conversation->conversationid));
                    } else {
                        $reply->error = get_string('incompatible_moodle_version', 'local_edumessenger') . ': ' . local_edumessenger_lib::get_version();
                    }
                    foreach ($conversation->messages AS &$message) {
                        local_edumessenger_lib::enhance_message($message);
                    }
                    foreach ($conversation->members AS &$member) {
                        local_edumessenger_lib::enhance_user($member);
                    }
                }
            break;
            // forum/lib.php
            // forum_get_all_discussion_posts($discussionid, $sort, $tracking=false)
            // forum_get_discussions($cm, $forumsort="", $fullpost=true, $unused=-1, $limit=-1, $userlastmodified=false, $page=-1, $perpage=0, $groupid = -1, $updatedsince = 0)
            case 'get_course_structure':
                $course = get_course($data->courseid);
                $modinfo = get_fast_modinfo($course);
                $cms = $modinfo->get_cms();
                $reply->structure = $DB->get_records('course_sections', array('course' => $course->id));
                foreach($reply->structure AS &$section) {
                    //$section->modules = $DB->get_records_sql('SELECT * FROM {course_modules} WHERE id IN (?)', array($section->sequence));
                    $modids = explode(',', $section->sequence);
                    $section->modules = array();
                    foreach($modids AS $modid) {
                        $cm = $cms[$modid];
                        if (!empty($cm)) {
                            $section->modules[$modid] = array(
                                'iconurl' => '' . $cm->get_icon_url(),
                                'intro' => $cm->content,
                                'modname' => $cm->modname,
                                'name' => $cm->name,
                                'url' => '' . $cm->url,
                            );
                        }
                    }
                }
            break;
            case 'get_courses':
                require_once($CFG->dirroot . '/mod/forum/lib.php');
                $reply->courses = enrol_get_all_users_courses($USER->id, true);
                foreach ($reply->courses AS &$course) {
                    // Load forums.
                    $course->forums = $DB->get_records('forum', array('course' => $course->id)); //forum_get_readable_forums($USER->id, $course->id);
                    // Load groups, forums that I can access.
                    foreach ($course->forums AS &$forum) {
                        $forum->cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
                        // Perhaps this forum does not exist anymore?
                        if (empty($forum->cm)) continue;
                        $context = context_module::instance($forum->cm->id);
                        if ($forum->cm->groupmode > 0) {
                            $groups = groups_get_activity_allowed_groups($forum->cm, $USER->id);
                            $agroups = array();
                            foreach ($groups AS $group) {
                                $group->cancreatepost = forum_user_can_post_discussion($forum, $group->id, -1, $forum->cm, $context);
                                $agroups[$group->name] = $group;
                            }
                            $forum->groups = array_values($agroups);
                        }
                        $forum->cancreatepost = forum_user_can_post_discussion($forum, null, -1, $forum->cm, $context);
                        $context = context_module::instance($forum->cm->id);
                        $forum->cancreatediscussion = has_capability('mod/forum:startdiscussion', $context);
                        $forum->canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
                    }
                }
            break;
            case 'get_discussions':
                require_once($CFG->dirroot . '/mod/forum/lib.php');
                $cm = get_coursemodule_from_instance('forum', $data->forumid, $data->courseid);
                $reply->discussions = forum_get_discussions($cm, '', true, -1, -1, false, -1, 0, -1, $data->updatedsince);
                $contexts = array(); $users = array();
                foreach ($reply->discussions AS &$discussion) {
                    $discussion->forumid = $data->forumid;
                    local_edumessenger_lib::enhance_discussion($discussion);
                }
            break;
            case 'get_posts':
                require_once($CFG->dirroot . '/mod/forum/lib.php');
                $forum = local_edumessenger_lib::get_cache('forums', $data->forumid);
                $cm = get_coursemodule_from_instance('forum', $data->forumid, $forum->course);
                $context = local_edumessenger_lib::get_cache('ctxforums', $data->forumid);
                $forum = local_edumessenger_lib::get_cache('forums', $data->forumid);

                // Attention, moodle has a weird idea how to use 'id', 'discussionid' and 'parent' with posts and discussions.
                // We have to translate our 'discussionid' to the 'parent-postid' of our discussion.
                $firstpost = $DB->get_record('forum_posts', array('discussion' => $data->discussionid, 'parent' => 0));
                $contexts = array(); $users = array();
                if (!empty($firstpost->id)) {
                    $discussions = forum_get_discussions($cm, '', true, -1, -1, false, -1, 0, -1, 0);
                    if (isset($discussions[$firstpost->id])) {
                        $discussions[$firstpost->id]->forumid = $data->forumid;
                        $reply->discussion = $discussions[$firstpost->id];
                        local_edumessenger_lib::enhance_discussion($reply->discussion);

                        $reply->posts = $DB->get_records_sql('SELECT * FROM {forum_posts} WHERE discussion=? AND modified>?', array($data->discussionid, $data->updatedsince));
                        foreach ($reply->posts AS &$post) {
                            local_edumessenger_lib::enhance_post($post);
                        }
                    } else {
                        $reply->error = 'No_access_to_such_discussion';
                    }
                } else {
                    $reply->error = 'No_access_to_such_discussion';
                    $reply->error_ext = 'post missing';
                }
            break;
            case 'get_stream':
                // Attention - be carefull with the param-checks here. We want to avoid SQL-Injection!
                // Only vars parsed by intval, or a list from an in_array-check are allowed!
                $lastknownmodified = !empty($data->lastknownmodified) ? intval($data->lastknownmodified) : 0;
                $offset = !empty($data->offset) ? intval($data->offset) : 0;
                $ordering = in_array($data->ordering, array('ASC', 'DESC')) ? $data->ordering : 'ASC';
                $limit = !empty($data->limit) ? intval($data->limit) : 1000;
                $priorto = !empty($data->priorto) ? intval($data->priorto) : -1;
                $userscourses = enrol_get_all_users_courses($USER->id, true);
                $userscourseids = array_keys($userscourses);
                if ($priorto > -1) {
                    // We want to go back in time! Automatically set ordering to DESC and such.
                    $sql = "SELECT p.*
                                FROM {forum_posts} p, {forum_discussions} d
                                WHERE p.discussion=d.id
                                    AND d.course IN (" . implode(',', $userscourseids) . ")
                                    AND p.modified<" . $priorto . "
                                ORDER BY p.modified DESC
                                LIMIT " . $offset . ", " . $limit;
                } else {
                    $sql = "SELECT p.*
                                FROM {forum_posts} p, {forum_discussions} d
                                WHERE p.discussion=d.id
                                    AND d.course IN (" . implode(',', $userscourseids) . ")
                                    AND p.modified>" . $lastknownmodified . "
                                ORDER BY p.modified " . $ordering . "
                                LIMIT " . $offset . ", " . $limit;
                }

                $reply->offset = $offset;
                $reply->ordering = $ordering;
                $reply->lastknownmodified = $lastknownmodified;
                $reply->limit = $limit;
                $reply->posts = $DB->get_records_sql($sql, array());
                $reply->discussions = array();
                foreach($reply->posts AS &$post) {
                    local_edumessenger_lib::enhance_post($post);
                    if (!isset($reply->discussions[$post->discussionid])) {
                        $reply->discussions[$post->discussionid] = $DB->get_record('forum_discussions', array('id' => $post->discussionid));
                        $reply->discussions[$post->discussionid]->discussion = $post->discussion;
                        $reply->discussions[$post->discussionid]->forumid = $reply->discussions[$post->discussionid]->forum;
                        local_edumessenger_lib::enhance_discussion($reply->discussions[$post->discussionid]);
                    }
                }
            break;
            case 'myData':
                $context = context_user::instance($USER->id);
                $reply->user = array(
                    'email' => $USER->email,
                    'firstname' => $USER->firstname,
                    'lastname' => $USER->lastname,
                    'pictureurl' => $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/user/icon',
                    'userid' => $USER->id,
                    'username' => $USER->username,
                );
                $preferences = $DB->get_records_sql('SELECT * FROM {user_preferences} WHERE userid=? AND name LIKE "message_provider_%"', array($USER->id));
                $reply->preferences = array(
                    'forum_mail' => 0,
                    'forum_push' => 0,
                    'message_mail' => 0,
                    'message_push' => 0,
                );
                foreach ($preferences AS $preference) {
                    switch ($preference->name) {
                        case 'message_provider_mod_forum_posts_loggedoff':
                            if (strpos($preference->value, 'email') > -1) {
                                $reply->preferences['forum_mail'] = 1;
                            }
                        break;
                        case 'message_provider_moodle_instantmessage_loggedoff':
                            if (strpos($preference->value, 'email') > -1) {
                                $reply->preferences['message_mail'] = 1;
                            }
                        break;
                        case 'message_provider_edumessenger_forum':
                            if (strpos($preference->value, 'push') > -1) {
                                $reply->preferences['forum_push'] = 1;
                            }
                        break;
                        case 'message_provider_edumessenger_message':
                            if (strpos($preference->value, 'push') > -1) {
                                $reply->preferences['message_push'] = 1;
                            }
                        break;
                    }
                }
            break;
            case 'removeMe':
                $DB->delete_records('local_edumessenger_tokens', array('userid' => $USER->id, 'edmtoken' => optional_param('edmtoken', '', PARAM_TEXT)));
                $reply->status = 'ok';
            break;
            case 'setPreference':
                $allowedprefs = array(
                    'forum_mail' => 'message_provider_mod_forum_posts_loggedoff',
                    'forum_push' => 'message_provider_edumessenger_forum',
                    'message_mail' => 'message_provider_moodle_instantmessage_loggedoff',
                    'message_push' => 'message_provider_edumessenger_message',
                );
                if (in_array($data->preference, array_keys($allowedprefs))) {
                    $field = $allowedprefs[$data->preference];
                    $rec = $DB->get_record('user_preferences', array('userid' => $USER->id, 'name' => $field));
                    if (empty($rec->id)) {
                        $rec = array('userid' => $USER->id, 'name' => $field, 'value' => 'none');
                    }
                    if (in_array($data->preference, array('forum_mail', 'message_mail'))) {
                        $targvalue = ($data->value == 'on') ? 'email' : 'none';
                        switch ($targvalue) {
                            case 'none':
                                // If popup is contained, keep it.
                                if (strpos($rec->value, 'popup') > -1) $rec->value = 'popup';
                                else $rec->value = 'none';
                            break;
                            case 'email':
                                // If popup is contained, keep it.
                                if (strpos($rec->value, 'popup') > -1) $rec->value = 'popup,email';
                                else $rec->value = 'email';
                            break;
                        }
                        if (!empty($rec->id)) {
                            $DB->update_record('user_preferences', $rec);
                        } else {
                            $DB->insert_record('user_preferences', $rec);
                        }
                    } else {
                        $rec->value = ($data->value == 'on') ? 'push' : 'none';
                        if (!empty($rec->id)) {
                            $DB->update_record('user_preferences', $rec);
                        } else {
                            $DB->insert_record('user_preferences', $rec);
                        }
                    }
                } else {
                    $reply->error = 'invalid preference key';
                }
            break;
            case 'vieweddiscussion':
                // Trigger discussion viewed event.
                forum_discussion_view($modcontext, $forum, $discussion); // line 198 mod/forum/discuss.php
            break;
            case 'wstoken':
                $serviceshortname = 'moodle_mobile_app';
                if (!empty($USER->confirmed)) {
                    require_once($CFG->dirroot . '/lib/externallib.php');
                    $service = $DB->get_record('external_services', array('shortname' => $serviceshortname, 'enabled' => 1));
                    $token = external_generate_token_for_current_user($service);
                    $reply->wstoken = $token->token;
                }
            break;
        }
        return $reply;
    }
}
