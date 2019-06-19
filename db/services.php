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

// We define the web service functions to install.
$functions = array(
    // THIS FUNCTION IS DEPRECATED FOR EDUMESSENGER 6
    'local_edumessenger_amount' => array(
        'classname'   => 'local_edumessenger_external',
        'methodname'  => 'amount',
        'classpath'   => 'local/edumessenger/externallib.php',
        'description' => 'Returns the amount of active users in this moodle.',
        'type'        => 'read',
    ),
    // THIS FUNCTION IS DEPRECATED FOR EDUMESSENGER 6
    'local_edumessenger_enableuser' => array(
        'classname'   => 'local_edumessenger_external',
        'methodname'  => 'enableuser',
        'classpath'   => 'local/edumessenger/externallib.php',
        'description' => 'Enables or disables a user for the edumessenger service.',
        'type'        => 'write',
    ),
    // THIS FUNCTION IS DEPRECATED FOR EDUMESSENGER 6
    'local_edumessenger_ping' => array(
        'classname'   => 'local_edumessenger_external',
        'methodname'  => 'ping',
        'classpath'   => 'local/edumessenger/externallib.php',
        'description' => 'Calls edumessenger-Cron to assure faster push-notifications',
        'type'        => 'read',
    ),
);
