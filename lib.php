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

/**
 * Class to handle general functions.
 *
 * @package    local_edumessenger
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_edumessenger_lib {
    // This cache helps to load certain objects from database only once.
    public static $cache = array();
    public static $verifieduserid = 0;
    private static $URLCENTRAL = 'https://messenger.dibig.at/v-6/service.php';
    /**
     * Adds a watermark to a text.
     * @param text the text to add the watermark to
     * @param textformat the textformat (0 ... clean, 1 ... html, 2 ... markdown)
     */
    public static function add_watermark(&$text, $textformat = 1) {
        $msg = get_string('watermark', 'local_edumessenger');

        switch ($textformat) {
            case 0:
                $text .= '\n\r\n\r---------------------------------------------------------------------\n\r\n\r' . $msg;
            break;
            case 1:
                $text .= '<div class="watermark"><hr />' . $msg . '</div>';
            break;
            case 2:
                $text .= '\n\r\n\r---------------------------------------------------------------------\n\r\n\r' . $msg;
            break;
        }
    }
    /**
     * Check a token for validity.
     * Sets the verified_userid token is valid.
     * @param userid userid the token belongs to
     * @param token token to check
     * @return true if token is valid.
     */
    public static function check_token($userid, $edmtoken) {
        global $DB, $USER;
        // If there is a logged in user and he differs from userid, log him out.
        if ($USER->id > 0 && !isguestuser($USER) && $USER->id != $userid) {
            require_logout();
            global $PAGE;
            redirect($PAGE->url);
            die();
        }
        $entry = $DB->get_record('local_edumessenger_tokens',
                    array(
                        'userid' => $userid,
                        'edmtoken' => $edmtoken)
                    );
        if (!empty($entry->userid) && $entry->userid == $userid) {
            self::verified_userid($userid);
            $entry->used = time();
            $DB->update_record('local_edumessenger_tokens', $entry);
            self::user_login();
            return true;
        }
        return false;
    }
    /**
     * Creates a user token.
     * @return user token.
     */
    public static function create_token() {
        global $DB, $USER;
        if ($USER->id < 2) {
            // We do not allow guest users to create a token.
            return '';
        }
        $tokenobject = (object) array(
            'userid' => $USER->id,
            'token' => md5(date('Ymdhis') . json_encode($USER)),
            'created' => time(),
            'used' => 0,
        );
        $tokenobject->id = $DB->insert_record('local_edumessenger_tokens', $tokenobject);
        if (!empty($tokenobject->id)) {
            return $tokenobject->edmtoken;
        } else {
            return '';
        }
    }
    /**
     * Get certain item from cache or database.
     * @param type of object
     * @param id of object
     */
    public static function get_cache($type, $id) {
        if (!isset(self::$cache[$type])) {
            self::$cache[$type] = array();
        }
        if (isset(self::$cache[$type][$id])) {
            return self::$cache[$type][$id];
        }
        global $CFG, $DB;
        switch ($type) {
            case 'ctxforums':
                $forum = self::get_cache('forums', $id);
                require_once($CFG->dirroot . '/mod/forum/lib.php');
                $cm = get_coursemodule_from_instance('forum', $id, $forum->course);
                self::$cache[$type][$id] = context_module::instance($cm->id);
            break;
            case 'ctxusers':
                self::$cache[$type][$id] = context_user::instance($id, IGNORE_MISSING);
            break;
            case 'discussions':
                self::$cache[$type][$id] = $DB->get_record('forum_discussions', array('id' => $id));
            break;
            case 'forums':
                self::$cache[$type][$id] = $DB->get_record('forum', array('id' => $id));
            break;
            case 'users':
                self::$cache[$type][$id] = $DB->get_record('user', array('id' => $id));
            break;
        }
        return self::$cache[$type][$id];
    }
    /**
     * Returns the moodle version.
     */
    public static function get_version() {
        return get_config('', 'version');
    }
    /**
     * Enhance a discussion-object
     * @param discussion to attach info to.
     */
    public static function enhance_discussion(&$discussion) {
        global $CFG;
        $discussion->discussionid = $discussion->id;
        $forum = self::get_cache('forums', $discussion->forum);
        $discussion->forumid = $forum->id;
        $discussion->courseid = $forum->course;
        $user = self::get_cache('users', $discussion->userid);
        $context = self::get_cache('ctxusers', $discussion->userid);

        $discussion->userfullname = fullname($user, true);
        if (!empty($context->id)) {
            $discussion->userpictureurl = $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/user/icon';
        }
    }
    /**
     * Enhance a message-object with additional data.
     * @param message object to attach info to.
     */
    public static function enhance_message(&$message) {
        global $CFG, $DB, $USER;
        $message->messageid = $message->id;
        $message->userid = $message->useridfrom;
        $message->enhanced = true;
        // Userfullname.
        $user = self::get_cache('users', $message->userid);
        $message->userfullname = fullname($user, true);
        // Userpictureurl.
        $usercontext = self::get_cache('ctxusers', $message->userid);
        if (!empty($usercontext->id)) {
            $message->userpictureurl = $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id . '/user/icon';
        }
        // Get rid of edm-watermark
        $msg = explode('<div class="watermark"', $message->fullmessagehtml);
        $message->fullmessagehtml = $msg[0];

        // Get rid of system-message
        $msg = explode('---------------------------------------------------------------------', $message->fullmessagehtml);
        $message->fullmessagehtml = $msg[0];
        if (empty($message->fullmessagehtml)) $message->fullmessagehtml = $message->fullmessage;
    }
    /**
     * Enhance a post-object with additional data.
     * @param post object to attach info to.
     */
    public static function enhance_post(&$post) {
        global $CFG, $DB, $USER;
        $post->postid = $post->id;
        $discussion = self::get_cache('discussions', $post->discussion);
        $post->discussionid = $post->discussion;
        $post->forumid = $discussion->forum;
        $forum = self::get_cache('forums', $post->forumid);
        $post->courseid = $forum->course;
        // Userfullname.
        $user = self::get_cache('users', $post->userid);
        $post->userfullname = fullname($user, true);
        // Userpictureurl.
        $usercontext = self::get_cache('ctxusers', $post->userid);
        if (!empty($usercontext->id)) {
            $post->userpictureurl = $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id . '/user/icon';
        }
        // Get rid of edm-watermark
        if (strpos($post->message, '<div class="watermark"') > 0) {
            $post->message = substr($post->message, 0, strpos($post->message, '<div class="watermark"'));
        }
        // Scores.
        require_once($CFG->dirroot . '/rating/lib.php');
        $forum = self::get_cache('forums', $post->forumid);
        $ratingoptions = new stdClass;
        $ratingoptions->context = self::get_cache('ctxforums', $post->forumid);
        $ratingoptions->component = 'mod_forum';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = array($post);
        $ratingoptions->aggregate = $forum->assessed;//the aggregation method
        $ratingoptions->scaleid = $forum->scale;
        $ratingoptions->userid = $USER->id;
        $ratingoptions->assesstimestart = $forum->assesstimestart;
        $ratingoptions->assesstimefinish = $forum->assesstimefinish;
        $rm = new rating_manager();
        $rm->get_ratings($ratingoptions);
    }
    /**
     * Enhance a user-object with additional data.
     * @param user object to attach info to.
     */
    public static function enhance_user(&$user) {
        global $CFG, $DB;
        if (empty($user->userid)) {
            $user->userid = $user->id;
        }
        // Userfullname.
        $_user = self::get_cache('users', $user->userid);
        $_user->userfullname = fullname($_user, true);
        $usercontext = self::get_cache('ctxusers', $user->userid);
        $fields = array('email', 'firstname', 'lastname', 'userfullname');
        foreach($fields AS $field) {
            $user->{$field} = $_user->{$field};
        }
        $user->userpictureurl = !empty($usercontext->id) ? $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id . '/user/icon' : '';
    }
    private static function secretToken() {
        global $CFG;
        $secret = get_config('local_edumessenger', 'secrettoken');
        if (empty($secret)) {
            $oursecrettoken = md5(rand(0, 999) . $CFG->wwwroot . time());
            set_config('oursecrettoken', $oursecrettoken, 'local_edumessenger');
            $data = array(
                'host' => $CFG->wwwroot,
                'act' => 'get_token',
                'oursecrettoken' => $oursecrettoken,
                'release' => $CFG->release,
                'plugin' => get_config('local_edumessenger', 'version')
            );

            error_log("GET TOKEN: " . print_r($data, 1));

            $payload = json_encode($data);
            $ch = curl_init(self::$URLCENTRAL);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            // Return response instead of printing.
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            // Send request.
            $result = curl_exec($ch);
            curl_close($ch);

            $chk = json_decode($result);
            return '';
        } else {
            return $secret;
        }
    }

    /**
     * Send a push notification from a qitem.
     * @param qitem
     */
    public static function sendQitem($qitem) {
        global $CFG, $DB;
        error_log("SEND QUITEM: " . print_r($qitem, 1));
        if (!empty($qitem->subject) && !empty($qitem->message) && !empty($qitem->targetusers)) {
            // Make curl request to eduMessenger-central.
            $secrettoken = self::secretToken();
            if (empty($secrettoken)) {
                // We can not send and abort.
                // As the token has been requested in the background the message will be sent upon next cron.
            } else {
                $chk = false;
                $data = array(
                    'host' => $CFG->wwwroot,
                    'secret' => $secrettoken,
                    'qitem' => $qitem,
                    'release' => $CFG->release,
                    'plugin' => get_config('local_edumessenger', 'version')
                );

                $payload = json_encode($data);
                $ch = curl_init(self::$URLCENTRAL);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
                curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                // Return response instead of printing.
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                // Send request.
                $result = curl_exec($ch);
                curl_close($ch);

                $chk = json_decode($result);

                // Delete queue item if curl was successful.
                if (!empty($chk->stored) && !empty($qitem->id)) {
                    $DB->delete_records('local_edumessenger_queue', array('id' => $qitem->id));
                }
            }
        } elseif (!empty($qitem->id)) {
            // Invalid queue-item - remove it.
            $DB->delete_records('local_edumessenger_queue', array('id' => $qitem->id));
        }
    }

    public static function user_login() {
        global $CFG, $DB, $USER;
        $userid = self::verified_userid();
        $user = $DB->get_record('user', array('id' => $userid));
        if (empty($user->id)) return;
        if (isguestuser($user)) return;
        if (empty($user->confirmed)) return;
        if ($USER->id != $userid) {
            complete_user_login($user);
        }
        return $user;
    }
    public static function verified_userid($userid = '') {
        if (!empty($userid)) {
            self::$verifieduserid = $userid;
        }
        return empty(self::$verifieduserid) ? 0 : self::$verifieduserid;
    }
}


// DEPRECATED WITH EDM6
class local_edumessenger {
    public function __construct() {
        global $DB;
        $this->messages = array();
        $this->load_settings();
        $this->serviceid = 0;
        $this->servicetoken = '';
        $entries = $DB->get_records_sql('SELECT id FROM {external_services} WHERE component=?', array('local_edumessenger'));
        foreach ($entries as $entry) {
            $this->serviceid = $entry->id;
        }
        $validuntil = time();
        $entries = $DB->get_records_sql('SELECT token FROM {external_tokens} WHERE externalserviceid=? AND (validuntil>? OR validuntil=0)', array($this->serviceid, $validuntil));
        foreach ($entries as $entry) {
            $this->servicetoken = $entry->token;
        }
    }
    public function load_settings() {
        $this->debugmode = get_config('local_edumessenger', 'debugmode');
        $this->developermode = get_config('local_edumessenger', 'developermode');
        $this->url = ($this->developermode) ? 'http://localhost/eduMessenger' : 'https://messenger.dibig.at';
    }
    public function site_register() {
        global $CFG;
        $payload = array(
            'act' => 'data_register',
            'release' => $CFG->release
        );
        $inst = json_decode($this->curl($payload));
        if (isset($inst->instance) && isset($inst->instance->pwd)) {
            $this->set('secret', $inst->instance->pwd);
            return true;
        } else {
            return false;
        }
    }
    public function site_auth() {
        global $CFG,$DB;

        // Get amount of users from database.
        $entries = $DB->get_records_sql('SELECT COUNT(id) AS amount FROM {user} WHERE confirmed=1 AND deleted=0 AND suspended=0', array());
        $k = array_keys($entries);
        // Make payload and send
        $payload = array(
            'act' => 'data_auth',
            'ctoken' => $this->servicetoken,
            'users' => $entries[$k[0]]->amount,
            'release' => $CFG->release,
            'plugin' => get_config('local_edumessenger', 'version')
        );
        return json_decode($this->curl($payload));
    }
    public function site_data($data=false) {
        if ($data) {
            $payload = (array)$data;
            $payload['act'] = 'data_store';
        } else {
            $payload = array('act' => 'data_fetch');
        }
        return $this->curl($payload, false);
    }
    public function regtool() {
        global $CFG;
        $url = $this->url . '/manage/index.php';
        $url .= "?host=" . rawurlencode($CFG->wwwroot);
        $url .= "&pwd=" . $this->secret();
        return $url;
    }

    public function set($p, $v) {
        $this->message("Set $p to $v");
        set_config($p, $v, 'local_edumessenger');
        switch($p) {
            case "secret":
                $this->secret = $v;
            break;
            case "debugmode":
                $this->debugmode = $v;
            break;
        }
    }
    public function secret() {
        $secret = get_config('local_edumessenger', 'secret');
        return $secret;
    }
    public function curl($payload, $debug=false) {
        global $CFG;
        $data = array(
            'host' => $CFG->wwwroot,
            'secret' => $this->secret(),
            'payload' => $payload,
        );
        $this->message('Using Central: ' . $this->url . '/services/service.php');
        $this->message('Request: ' . json_encode($data));

        $payload = json_encode($data);
        $ch = curl_init($this->url . '/services/service.php');
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        // Return response instead of printing.
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        // Send request.
        $result = curl_exec($ch);
        curl_close($ch);
        $chk = json_decode($result);

        $this->message('Result: ' . json_encode(($chk != "") ? $chk : $result));
        return utf8_decode($result);
    }
    public function asutf8($str) {
        if (preg_match('!!u', $str)) {
            return $str;
        } else {
            return utf8_encode($str);
        }
    }
    public function message($str) {
        $this->messages[] = $str;
    }
    public function messages_show() {
        if ($this->debugmode) {
            echo "<h3>Debug</h3>\n";
            echo "<ul><li><pre>" . implode("</pre></li><li><pre>", $this->messages) . "</li></ul>";
        }
    }
}
