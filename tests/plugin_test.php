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
 * Bulk meta enrolment sync functional test.
 *
 * @package    enrol_metabulk
 * @category   phpunit
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

class enrol_meta_plugin_testcase extends advanced_testcase {

    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['metabulk'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['metabulk']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    protected function is_meta_enrolled($user, $enrol, $role = null) {
        global $DB;

        if (!$DB->record_exists('user_enrolments', array('enrolid' => $enrol->id, 'userid' => $user->id))) {
            return false;
        }

        if ($role === null) {
            return true;
        }

        return $this->has_role($user, $enrol, $role);
    }

    protected function has_role($user, $enrol, $role) {
        global $DB;

        $context = context_course::instance($enrol->courseid);

        if ($role === false) {
            if ($DB->record_exists('role_assignments',
                array('contextid' => $context->id, 'userid' => $user->id, 'component' => 'enrol_metabulk', 'itemid' => $enrol->id))) {
                return false;
            }
        } else if (!$DB->record_exists('role_assignments',
            array('contextid' => $context->id, 'userid' => $user->id, 'roleid' => $role->id, 'component' => 'enrol_metabulk', 'itemid' => $enrol->id))) {
            return false;
        }

        return true;
    }
}