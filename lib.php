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
        $entries = $DB->get_records_sql('SELECT token FROM {external_tokens} WHERE externalserviceid=?', array($this->serviceid));
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
        $payload = array('act' => 'data_auth', 'ctoken' => $this->servicetoken);
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
