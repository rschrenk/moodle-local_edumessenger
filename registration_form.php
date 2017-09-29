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
 * Links and settings
 * @package    localedumessenger
 * @copyright  2017 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/formslib.php");

class edumessenger_registration_form extends moodleform {
    public function definition() {
        global $CFG;
        $mform = $this->_form;
        $mform->addElement('text', 'secret', get_string('secret_token', 'local_edumessenger'));
        $mform->addElement('checkbox', 'debugmode', get_string('debugmode', 'local_edumessenger'));
        $mform->addElement('submit', '', get_string('submit', 'local_edumessenger'));
        $mform->setType('secret', 'text');
        $mform->setDefault('secret', get_config('edumessenger', 'secret'));
        $mform->setDefault('debugmode', get_config('edumessenger', 'debugmode'));
    }
    public function validation($data, $files) {
        return array();
    }
    public function store($edm, $formdata) {
         $edm->set('secret', @$formdata->secret);
         $edm->set('debugmode', @$formdata->debugmode);
         $edm->load_settings();
         return true;
    }
}