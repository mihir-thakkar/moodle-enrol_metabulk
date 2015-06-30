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
 * Local stuff for bulk meta course enrolment plugin.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Event handler for bulk meta enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class enrol_metabulk_handler {

    /**
     * Synchronise meta enrolments of this user in this course
     * @static
     * @param int $courseid
     * @param int $userid
     * @return void
     */
    protected static function sync_course_instances($courseid, $userid) {
        global $DB;

        static $preventrecursion = false;

        // Does anything want to sync with this parent?
        if (!$enrols = $DB->get_records('enrol_metabulk', array('courseid' => $courseid), 'id ASC')) {
            return;
        }

        if ($preventrecursion) {
            return;
        }

        $preventrecursion = true;
        try {
            foreach ($enrols as $enrol) {
                $enrolinstance = $DB->get_record('enrol', array('id' => $enrol->enrolid));
                self::sync_with_parent_course($enrolinstance, $enrol, $userid);
            }
        } catch (Exception $e) {
            $preventrecursion = false;
            throw $e;
        }

        $preventrecursion = false;
    }

    /**
     * Synchronise user enrolments in given instance as fast as possible.
     *
     * All roles are removed if the meta plugin disabled.
     *
     * @static
     * @param stdClass $instance
     * @param int $userid
     * @return void
     */
    protected static function sync_with_parent_course(stdClass $instance, stdClass $metainstance, $userid) {
        global $DB, $CFG;

        $plugin = enrol_get_plugin('metabulk');

        if ($metainstance->courseid == $instance->courseid) {
            // Can not sync with self!!!.
            return;
        }

        $context = context_course::instance($instance->courseid);

        // List of enrolments in parent course (we ignore metabulk enrols in parents completely).
        list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
        $params['userid'] = $userid;
        $params['parentcourse'] = $metainstance->courseid;
        $sql = "SELECT ue.*
                FROM {user_enrolments} ue
                JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol <> 'metabulk' AND e.courseid = :parentcourse AND e.enrol $enabled)
                WHERE ue.userid = :userid";
        $parentues = $DB->get_records_sql($sql, $params);
        // Current enrolments for this instance.
        $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));

        // First deal with users that are not enrolled in parent.
        if (empty($parentues)) {
            self::user_not_supposed_to_be_here($instance, $ue, $context, $plugin);
            return;
        }

        if (!$parentcontext = context_course::instance($metainstance->courseid, IGNORE_MISSING)) {
            // Weird, we should not get here.
            return;
        }

        $skiproles = $plugin->get_config('nosyncroleids', '');
        $skiproles = empty($skiproles) ? array() : explode(',', $skiproles);
        $syncall   = $plugin->get_config('syncall', 1);

        // Roles in parent course (meta enrols must be ignored!)
        $parentroles = array();
        list($ignoreroles, $params) = $DB->get_in_or_equal($skiproles, SQL_PARAMS_NAMED, 'ri', false, -1);
        $params['contextid'] = $parentcontext->id;
        $params['userid'] = $userid;
        $select = "contextid = :contextid AND userid = :userid AND component <> 'enrol_metabulk' AND roleid $ignoreroles";
        foreach ($DB->get_records_select('role_assignments', $select, $params) as $ra) {
            $parentroles[$ra->roleid] = $ra->roleid;
        }

        // Roles from this instance.
        $roles = array();
        $ras = $DB->get_records('role_assignments',
            array('contextid' => $context->id, 'userid' => $userid, 'component' => 'enrol_metabulk', 'itemid' => $instance->id));
        foreach ($ras as $ra) {
            $roles[$ra->roleid] = $ra->roleid;
        }
        unset($ras);

        // Do we want users without roles?
        if (!$syncall and empty($parentroles)) {
            self::user_not_supposed_to_be_here($instance, $ue, $context, $plugin);
            return;
        }

        // Is parent enrol active? (we ignore enrol starts and ends, sorry it would be too complex).
        $parentstatus = ENROL_USER_SUSPENDED;
        foreach ($parentues as $pue) {
            if ($pue->status == ENROL_USER_ACTIVE) {
                $parentstatus = ENROL_USER_ACTIVE;
                break;
            }
        }

        // Enrol user if not enrolled yet or fix status.
        if ($ue) {
            if ($parentstatus != $ue->status) {
                $plugin->update_user_enrol($instance, $userid, $parentstatus);
                $ue->status = $parentstatus;
            }
        } else {
            $plugin->enrol_user($instance, $userid, null, 0, 0, $parentstatus);
            $ue = new stdClass();
            $ue->userid = $userid;
            $ue->enrolid = $instance->id;
            $ue->status = $parentstatus;
            // No group support added yet ( as per July 1 ).
            if ($instance->customint2) {
                groups_add_member($instance->customint2, $userid, 'enrol_metabulk', $instance->id);
            }
        }

        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);

        // Only active users in enabled instances are supposed to have roles (we can reassign the roles any time later).
        if ($ue->status != ENROL_USER_ACTIVE or $instance->status != ENROL_INSTANCE_ENABLED) {
            // Todo. Check enrol_meta.
            if ($roles) {
                role_unassign_all(array('userid' => $userid, 'contextid' => $context->id, 'component' => 'enrol_metabulk', 'itemid' => $instance->id));
            }
            return;
        }

        // Add new roles.
        foreach ($parentroles as $rid) {
            if (!isset($roles[$rid])) {
                role_assign($rid, $userid, $context->id, 'enrol_metabulk', $instance->id);
            }
        }

        if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND) {
            // Always keep the roles.
            return;
        }

        // Remove roles.
        foreach ($roles as $rid) {
            if (!isset($parentroles[$rid])) {
                role_unassign($rid, $userid, $context->id, 'enrol_metabulk', $instance->id);
            }
        }
    }

    /**
     * Deal with users that are not supposed to be enrolled via this instance
     * @static
     * @param stdClass $instance
     * @param stdClass $ue
     * @param context_course $context
     * @param enrol_meta $plugin
     * @return void
     */
    protected static function user_not_supposed_to_be_here($instance, $ue, context_course $context, $plugin) {
        if (!$ue) {
            // Not enrolled yet - simple!
            return;
        }

        $userid = $ue->userid;
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);

        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            // Purges grades, group membership, preferences, etc. - admins were warned!
            $plugin->unenrol_user($instance, $userid);

        } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND) {
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            }

        } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            }
            role_unassign_all(array('userid' => $userid, 'contextid' => $context->id, 'component' => 'enrol_metabulk', 'itemid' => $instance->id));

        } else {
            debugging('Unknown unenrol action '.$unenrolaction);
        }
    }
}