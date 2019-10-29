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
 * @package    local_edumessenger
 * @copyright  2017 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if (isset($settings)) {
    // @TODO use this value to disable any communication to edumessenger-central
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edumessenger/allowpush',
            get_string('settings:allowpush', 'local_edumessenger'),
            get_string('settings:allowpush:description', 'local_edumessenger'),
            1,
            PARAM_INT
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edumessenger/debug',
            get_string('settings:debug', 'local_edumessenger'),
            get_string('settings:debug:description', 'local_edumessenger'),
            0,
            PARAM_INT
        )
    );
}
