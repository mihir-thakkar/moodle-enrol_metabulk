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
 * Event observer for bulk meta enrolment plugin.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/enrol/metabulk/locallib.php');

/**
 * Event observer for enrol_metabulk.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_metabulk_observer {

    /**
     * Triggered via user_enrolment_created event.
     *
     * @param \core\event\user_enrolment_created $event
     * @return bool true on success.
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        if (!enrol_is_enabled('metabulk')) {
            // No more enrolments for disabled plugins.
            return true;
        }

        if ($event->other['enrol'] === 'metabulk') {
            // Prevent circular dependencies - we can not sync meta enrolments recursively.
            return true;
        }

        self::sync_course_instances($event->courseid, $event->relateduserid);
        return true;
    }

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return bool true on success.
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        if (!enrol_is_enabled('metabulk')) {
            // This is slow, let enrol_meta_sync() deal with disabled plugin.
            return true;
        }

        if ($event->other['enrol'] === 'metabulk') {
            // Prevent circular dependencies - we can not sync meta enrolments recursively.
            return true;
        }

        self::sync_course_instances($event->courseid, $event->relateduserid);

        return true;
    }

    /**
     * Triggered via user_enrolment_updated event.
     *
     * @param \core\event\user_enrolment_updated $event
     * @return bool true on success
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        if (!enrol_is_enabled('metabulk')) {
            // No modifications if plugin disabled.
            return true;
        }

        if ($event->other['enrol'] === 'metabulk') {
            // Prevent circular dependencies - we can not sync meta enrolments recursively.
            return true;
        }

        self::sync_course_instances($event->courseid, $event->relateduserid);

        return true;
    }

    /**
     * Triggered via role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return bool true on success.
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        if (!enrol_is_enabled('metabulk')) {
            return true;
        }

        // Prevent circular dependencies - we can not sync meta roles recursively.
        if ($event->other['component'] === 'enrol_metabulk') {
            return true;
        }

        // Only course level roles are interesting.
        if (!$parentcontext = context::instance_by_id($event->contextid, IGNORE_MISSING)) {
            return true;
        }
        if ($parentcontext->contextlevel != CONTEXT_COURSE) {
            return true;
        }

        self::sync_course_instances($parentcontext->instanceid, $event->relateduserid);

        return true;
    }

    /**
     * Triggered via role_unassigned event.
     *
     * @param \core\event\role_unassigned $event
     * @return bool true on success
     */
    public static function role_unassigned(\core\event\role_unassigned $event) {
        if (!enrol_is_enabled('metabulk')) {
            // All roles are removed via cron automatically.
            return true;
        }

        // Prevent circular dependencies - we can not sync meta roles recursively.
        if ($event->other['component'] === 'enrol_metabulk') {
            return true;
        }

        // Only course level roles are interesting.
        if (!$parentcontext = context::instance_by_id($event->contextid, IGNORE_MISSING)) {
            return true;
        }
        if ($parentcontext->contextlevel != CONTEXT_COURSE) {
            return true;
        }

        self::sync_course_instances($parentcontext->instanceid, $event->relateduserid);

        return true;
    }

    /**
     * Triggered via course_deleted event.
     *
     * @param \core\event\course_deleted $event
     * @return bool true on success
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB, $CFG;

        if (!enrol_is_enabled('metabulk')) {
            // This is slow, let enrol_metabulk_sync() deal with disabled plugin.
            return true;
        }

        // Does anything want to sync with this course?
        $courses = $DB->get_fieldset_sql('SELECT DISTINCT e.courseid
                FROM {enrol} e
                JOIN {enrol_metabulk} m ON (m.enrolid = e.id)
                WHERE m.courseid = ?', array($event->objectid));
        if (!$courses) {
            return true;
        }

        require_once("$CFG->dirroot/enrol/metabulk/locallib.php");

        $DB->delete_records('enrol_metabulk', array('courseid' => $event->objectid));
        enrol_metabulk_sync($courses, false);
        return true;
    }

    /**
     * Synchronise meta enrolments of this user in this course
     * @static
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    protected static function sync_course_instances($courseid, $userid) {
        global $DB, $CFG;

        static $preventrecursion = false;

        if ($preventrecursion) {
            return;
        }

        // Does anything want to sync with this parent?
        if (!$enrols = $DB->get_records('enrol_metabulk', array('courseid' => $courseid))) {
            return;
        }

        $courses = $DB->get_fieldset_sql('SELECT DISTINCT e.courseid
                FROM {enrol} e
                JOIN {enrol_metabulk} m ON (m.enrolid = e.id)
                WHERE m.courseid = ?', array($courseid));
        if (!$courses) {
            return;
        }

        $preventrecursion = true;
        try {
            require_once("$CFG->dirroot/enrol/metabulk/locallib.php");
            enrol_metabulk_sync($courses, false);
        } catch (Exception $e) {
            $preventrecursion = false;
            throw $e;
        }

        $preventrecursion = false;
    }

}
