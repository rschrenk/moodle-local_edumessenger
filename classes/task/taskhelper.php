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


// @TODO this file is obsolete once edm6 is published.


/**
 * @package    local_edumessenger
 * @copyright  2017 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edumessenger\task;

defined('MOODLE_INTERNAL') || die;

class local_edumessenger_taskhelper {
    public function __construct() {
        global $DB;
        $this->messages = array();
        $this->load_settings();
    }
    public function load_settings() {
        $this->debugmode = get_config('local_edumessenger', 'debugmode');
        $this->developermode = get_config('local_edumessenger', 'developermode');
        $this->url = ($this->developermode) ? 'http://localhost/eduMessenger' : 'https://messenger.dibig.at';
    }

    public function secret() {
        $secret = get_config('local_edumessenger', 'secret');
        return $secret;
    }

    public function cron() {
        global $DB;

        $logid = get_config('local_edumessenger', 'logid');
        if ($logid == "") {
            $entries = $DB->get_records_sql('SELECT MAX(id) AS id FROM {logstore_standard_log}', array());
            foreach ($entries as $entry) {
                $logid = $entry->id;
            }
        }
        $this->message('Loading logstore from id ' . $logid);

        $filter = $this->get_filter();

        $lastid = 0;
        $events = array();
        $entries = $DB->get_records_sql('SELECT * FROM {logstore_standard_log} WHERE id>? LIMIT 0,50000', array($logid));

        $this->message("Analyzing " . count($entries) . " Events since logid " . $logid);
        foreach ($entries as $entry) {
            if (strlen(print_r($events, 1)) > 1024*1024) continue;
            $lastid = $entry->id;
            $entry = $this->enhanceEntry($entry, $filter);
            if ($entry != null) {
                $events[] = $entry;
            }
        }

        $payload = array(
            "act" => "log",
            "events" => $events
        );

        if (count($events) > 0) {
            $this->message("Sending ".count($events)." events");
            $this->message(json_encode($payload));
            $this->curl($payload);
        } else {
            $this->message("No new events to send!");
            if ($lastid > 0) {
                $this->message("Set latest logid to " . $lastid);
                set_config('logid', $lastid, 'local_edumessenger');
            }
        }
        return true;
    }
    public function enhanceEntry($entry, $filter=array()) {
        global $DB;
        // We are not interested in many events.
        if (count($filter) > 0 && !in_array($entry->eventname, $filter)) {
            return null;
        }
        // We are not interested in messages that have been set by system user or via cli.
        if (($entry->eventname == '\core\event\message_sent' || $entry->eventname == '\core\event\message_deleted')
            &&
            ($entry->userid == 0 || (isset($entry->origin) && $entry->origin == "cli"))) {
            return null;
        }
        // We are not interested if a user sends himself a message or so.
        if ($entry->relateduserid > 0 && $entry->userid == $entry->relateduserid) {
            return null;
        }
        if (is_array($entry->other)) $entry->other = (object) $entry->other;
        else $entry->other = (object)unserialize($entry->other);

        if ($entry->userid > 0) {
            $entry->user = $DB->get_record("user", array("id" => $entry->userid));
        }
        if ($entry->relateduserid > 0) {
            $entry->relateduser = $DB->get_record("user", array("id" => $entry->relateduserid));
        }
        if ($entry->eventname == '\core\event\message_sent') {
            $entry->msg = $DB->get_record("messages", array("id" => $entry->objectid));
            // If this message does not exist in the unread messages table try in read messages table.
            if (!isset($entry->msg->fullmessage) || empty($entry->msg->fullmessage)) {
                $entry->msg = $DB->get_record("message_read", array("id" => $entry->objectid));
            }
            // If this message is still empty continue.
            if (!isset($entry->msg->fullmessage)) {
                return null;
            }

            // Check if one of the users is using edumessenger.
            error_log('SELECT COUNT(id) FROM {edumessenger_userid_enabled} WHERE userid IN (' . implode(',', array($entry->userid, $entry->relateduserid)) . ') AND enabled=1');
            $using = $DB->count_records_sql('SELECT COUNT(id) FROM {edumessenger_userid_enabled} WHERE userid IN (' . implode(',', array($entry->userid, $entry->relateduserid)) . ') AND enabled=1', array());
            if ($using == 0) { return null; }
        }
        if ($entry->eventname == '\mod_forum\event\discussion_created') {
            $entry->discussion = $DB->get_record("forum_discussions", array("id" => $entry->objectid));
            $entry->post = $DB->get_record("forum_posts", array("discussion" => $entry->objectid, "parent" => 0));
        }
        if ($entry->eventname == '\mod_forum\event\post_created') {
            $entry->post = $DB->get_record("forum_posts", array("id" => $entry->objectid));
            $entry->discussion = $DB->get_record("forum_discussions", array("id" => $entry->post->discussion));
        }

        if (isset($entry->courseid)) {
            // Check if any enrolled users are using edumessenger.
            //error_log('SELECT COUNT(eue.id) FROM {edumessenger_userid_enabled} AS eue, {user_enrolments} AS ue, {enrol} AS e WHERE eue.userid=ue.userid AND ue.enrolid=e.id AND e.courseid=' . $entry->courseid);
            $using = $DB->count_records_sql('SELECT COUNT(eue.id) FROM {edumessenger_userid_enabled} AS eue, {user_enrolments} AS ue, {enrol} AS e WHERE eue.userid=ue.userid AND ue.enrolid=e.id AND e.courseid=?', array($entry->courseid));
            if ($using == 0) { return null; }
        }
        if (count($filter) > 0) {
            return $entry;
        } else {
            $payload = array(
                "act" => "log",
                "byobserver" => true,
                "events" => array($entry)
            );

            $this->message("Sending 1 events from observer");
            $this->message(json_encode($payload));
            $this->curl($payload);
        }
    }
    public function curl($payload, $debug=false) {
        global $CFG;

        $data = array(
            'host' => $CFG->wwwroot,
            'secret' => $this->secret(),
            'debugmode' => $this->debugmode,
            'payload' => $payload,
            'release' => $CFG->release,
            'plugin' => get_config('local_edumessenger', 'version')
        );

        $this->message('URL: ' . $this->url . '/services/service.php');

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
        if (isset($chk->logid)) {
            $logid = get_config('local_edumessenger', 'logid');
            if ($chk->logid > $logid) {
                $this->message('Set latest logid to ' . $chk->logid);
                set_config('logid', $chk->logid, 'local_edumessenger');
            } else {
                $this->message('Latest logid (' . $logid . ') already higher than ' . $chk->logid);
            }
        } else {
            $this->message('Got no latest logid: ');
            $this->message(json_encode($result));
        }

        if ($this->debugmode) {
            $this->messages_show();
        }
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
        echo "===== Debug =====<br />\n";
        echo "-- " . implode("\n<br />-- ", $this->messages) . "\n\n";
    }
    public function get_filter() {
        return array(
            '\core\event\course_created',
            '\core\event\course_deleted',
            '\core\event\group_created',
            '\core\event\group_deleted',
            '\core\event\group_member_added',
            '\core\event\group_member_removed',
            '\core\event\course_module_created',
            '\core\event\course_module_deleted',
            '\mod_forum\event\discussion_created',
            '\mod_forum\event\discussion_deleted',
            // '\mod_forum\event\assessable_uploaded',
            '\mod_forum\event\post_updated',
            '\mod_forum\event\post_created',
            '\mod_forum\event\post_deleted',
            '\core\event\message_sent',
            '\core\event\message_deleted',
        );
    }
}
