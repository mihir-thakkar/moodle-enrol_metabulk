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
        if (!$enrols = $DB->get_records('enrol_metabulk', array('courseid' => $courseid))) {
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

/**
 * Sync all metabulk course links.
 *
 * @param int $courseid one course, empty mean all
 * @param bool $verbose verbose CLI output
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_metabulk_sync($courseid = NULL, $verbose = false) {
    global $CFG, $DB;
    require_once("{$CFG->dirroot}/group/lib.php");

    // Purge all roles if meta sync disabled, those can be recreated later here in cron.
    if (!enrol_is_enabled('metabulk')) {
        if ($verbose) {
            mtrace('Meta sync plugin is disabled, unassigning all plugin roles and stopping.');
        }
        role_unassign_all(array('component'=>'enrol_metabulk'));
        return 2;
    }

    // Unfortunately this may take a long time, execution can be interrupted safely.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_HUGE);

    if ($verbose) {
        mtrace('Starting user enrolment synchronisation...');
    }

    $instances = array(); // Cache instances.

    $meta = enrol_get_plugin('metabulk');

    $unenrolaction = $meta->get_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
    $skiproles     = $meta->get_config('nosyncroleids', '');
    $skiproles     = empty($skiproles) ? array() : explode(',', $skiproles);
    $syncall       = $meta->get_config('syncall', 1);

    $allroles = get_all_roles();


    // Iterate through all not enrolled yet users.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
    $params['courseid'] = $courseid;
    $sql = "SELECT pue.userid, e.id as enrolid, pue.status
            FROM {user_enrolments} pue
            JOIN {enrol} pe ON (pe.id = pue.enrolid AND pe.enrol <> 'metabulk' AND pe.enrol $enabled)
            JOIN {enrol} e ON (e.enrol = 'metabulk' $onecourse)
            JOIN {enrol_metabulk} m ON (m.enrolid = e.id AND m.courseid = pe.courseid)
            JOIN {user} u ON (u.id = pue.userid AND u.deleted = 0)
        LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = pue.userid)
            WHERE ue.id IS NULL";

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if (!$syncall) {
            // this may be slow if very many users are ignored in sync
            $parentcontext = context_course::instance($instance->courseid);
            list($ignoreroles, $params) = $DB->get_in_or_equal($skiproles, SQL_PARAMS_NAMED, 'ri', false, -1);
            $params['contextid'] = $parentcontext->id;
            $params['userid'] = $ue->userid;
            $params['courselevel'] = CONTEXT_COURSE;
            $params['enrolid'] = $instance->id;

            $select = "SELECT *
                FROM {role_assignments} ra
                JOIN {context} c ON (c.contextlevel=:courselevel AND c.id=ra.contextid)
                JOIN {enrol_metabulk} mb ON (mb.enrolid=:enrolid AND mb.courseid=c.instanceid)
                WHERE ra.userid = :userid AND ra.component <> 'enrol_metabulk' AND ra.roleid $ignoreroles";
            if (!$DB->record_exists_sql($select, $params)) {
                // bad luck, this user does not have any role we want in parent course
                if ($verbose) {
                    mtrace("  skipping enrolling: $ue->userid ==> $instance->courseid (user without role)");
                }
                continue;
            }
        }

        $meta->enrol_user($instance, $ue->userid, null, 0, 0, $ue->status);
        // Todo: Group support.
        /*if ($instance->customint2) {
            groups_add_member($instance->customint2, $ue->userid, 'enrol_meta', $instance->id);
        }*/
        if ($verbose) {
            mtrace("  enrolling: $ue->userid ==> $instance->courseid");
        }
    }
    $rs->close();


    // unenrol as necessary - ignore enabled flag, we want to get rid of existing enrols in any case
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
    $params['courseid'] = $courseid;
    $sql = "SELECT ue.*
                FROM {user_enrolments} ue
                JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
                WHERE NOT EXISTS (SELECT 1
                FROM {enrol_metabulk} m
                JOIN {enrol} xpe ON (xpe.courseid = m.courseid AND xpe.status = 0 AND xpe.enrol <> 'metabulk' AND xpe.enrol $enabled)
                JOIN {user_enrolments} xpue ON xpe.id = xpue.enrolid AND xpue.status = 0
                WHERE xpue.userid = ue.userid AND m.enrolid = ue.enrolid
                )";

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];

        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            $meta->unenrol_user($instance, $ue->userid);
            if ($verbose) {
                mtrace("  unenrolling: $ue->userid ==> $instance->courseid");
            }

        } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND) {
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $meta->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                if ($verbose) {
                    mtrace("  suspending: $ue->userid ==> $instance->courseid");
                }
            }

        } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $meta->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $context = context_course::instance($instance->courseid);
                role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_metabulk', 'itemid'=>$instance->id));
                if ($verbose) {
                    mtrace("  suspending and removing all roles: $ue->userid ==> $instance->courseid");
                }
            }
        }
    }
    $rs->close();


    // update status - meta enrols + start and end dates are ignored, sorry
    // note the trick here is that the active enrolment and instance constants have value 0
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
    $params['courseid'] = $courseid;
    $sql = "SELECT ue.userid, ue.enrolid, pue.pstatus
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
              JOIN {enrol_metabulk} m ON (m.enrolid = ue.enrolid)
              JOIN (SELECT xpue.userid, xpe.courseid, MIN(xpue.status + xpe.status) AS pstatus
                      FROM {user_enrolments} xpue
                      JOIN {enrol} xpe ON (xpe.id = xpue.enrolid AND xpe.enrol <> 'metabulk' AND xpe.enrol $enabled)
                  GROUP BY xpue.userid, xpe.courseid
                   ) pue ON (pue.courseid = m.courseid AND pue.userid = ue.userid)
             WHERE (pue.pstatus = 0 AND ue.status > 0) OR (pue.pstatus > 0 and ue.status = 0)";
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        $ue->pstatus = ($ue->pstatus == ENROL_USER_ACTIVE) ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED;

        if ($ue->pstatus == ENROL_USER_ACTIVE and !$syncall and $unenrolaction != ENROL_EXT_REMOVED_UNENROL) {
            // this may be slow if very many users are ignored in sync
            $parentcontext = context_course::instance($instance->courseid);
            list($ignoreroles, $params) = $DB->get_in_or_equal($skiproles, SQL_PARAMS_NAMED, 'ri', false, -1);
            $params['contextid'] = $parentcontext->id;
            $params['userid'] = $ue->userid;
            $params['courselevel'] = CONTEXT_COURSE;
            $params['enrolid'] = $instance->id;
            $select = "SELECT *
                FROM {role_assignments} ra
                JOIN {context} c ON (c.contextlevel=:courselevel AND c.id=ra.contextid)
                JOIN {enrol_metabulk} mb ON (mb.enrolid=:enrolid AND mb.courseid=c.instanceid)
                WHERE ra.userid = :userid AND ra.component <> 'enrol_metabulk' AND ra.roleid $ignoreroles";
            if (!$DB->record_exists_sql($select, $params)) {
                // bad luck, this user does not have any role we want in parent course
                if ($verbose) {
                    mtrace("  skipping unsuspending: $ue->userid ==> $instance->courseid (user without role)");
                }
                continue;
            }
        }

        $meta->update_user_enrol($instance, $ue->userid, $ue->pstatus);
        if ($verbose) {
            if ($ue->pstatus == ENROL_USER_ACTIVE) {
                mtrace("  unsuspending: $ue->userid ==> $instance->courseid");
            } else {
                mtrace("  suspending: $ue->userid ==> $instance->courseid");
            }
        }
    }
    $rs->close();


    // now assign all necessary roles
    $enabled = explode(',', $CFG->enrol_plugins_enabled);
    foreach($enabled as $k=>$v) {
        if ($v === 'metabulk') {
            continue; // no meta sync of meta roles
        }
        $enabled[$k] = 'enrol_'.$v;
    }
    $enabled[] = ''; // manual assignments are replicated too

    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    list($enabled, $params) = $DB->get_in_or_equal($enabled, SQL_PARAMS_NAMED, 'e');
    $params['coursecontext'] = CONTEXT_COURSE;
    $params['courseid'] = $courseid;
    $params['activeuser'] = ENROL_USER_ACTIVE;
    $params['enabledinstance'] = ENROL_INSTANCE_ENABLED;
    $sql = "SELECT DISTINCT pra.roleid, pra.userid, c.id AS contextid, m.enrolid AS enrolid, e.courseid
              FROM {role_assignments} pra
              JOIN {user} u ON (u.id = pra.userid AND u.deleted = 0)
              JOIN {context} pc ON (pc.id = pra.contextid AND pc.contextlevel = :coursecontext AND pra.component $enabled)
              JOIN {enrol} e ON (e.enrol = 'metabulk' $onecourse AND e.status = :enabledinstance)
              JOIN {enrol_metabulk} m ON (m.enrolid = e.id AND m.courseid = pc.instanceid)
              JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = u.id AND ue.status = :activeuser)
              JOIN {context} c ON (c.contextlevel = pc.contextlevel AND c.instanceid = e.courseid)
         LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.userid = pra.userid AND ra.roleid = pra.roleid AND ra.itemid = m.enrolid AND ra.component = 'enrol_metabulk')
             WHERE ra.id IS NULL";

    if ($ignored = $meta->get_config('nosyncroleids')) {
        list($notignored, $xparams) = $DB->get_in_or_equal(explode(',', $ignored), SQL_PARAMS_NAMED, 'ig', false);
        $params = array_merge($params, $xparams);
        $sql = "$sql AND pra.roleid $notignored";
    }

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ra) {
        role_assign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_metabulk', $ra->enrolid);
        if ($verbose) {
            mtrace("  assigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname);
        }
    }
    $rs->close();


    // remove unwanted roles - include ignored roles and disabled plugins too
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    $params = array();
    $params['coursecontext'] = CONTEXT_COURSE;
    $params['courseid'] = $courseid;
    $params['activeuser'] = ENROL_USER_ACTIVE;
    $params['enabledinstance'] = ENROL_INSTANCE_ENABLED;
    if ($ignored = $meta->get_config('nosyncroleids')) {
        list($notignored, $xparams) = $DB->get_in_or_equal(explode(',', $ignored), SQL_PARAMS_NAMED, 'ig', false);
        $params = array_merge($params, $xparams);
        $notignored = "AND pra.roleid $notignored";
    } else {
        $notignored = "";
    }

    $sql = "SELECT ra.roleid, ra.userid, ra.contextid, ra.itemid, e.courseid
              FROM {role_assignments} ra
              JOIN {enrol} e ON (e.id = ra.itemid AND ra.component = 'enrol_metabulk' AND e.enrol = 'metabulk' $onecourse)
              JOIN {enrol_metabulk} m ON (m.enrolid = ra.itemid)
              JOIN {context} pc ON (pc.instanceid = m.courseid AND pc.contextlevel = :coursecontext)
         LEFT JOIN {role_assignments} pra ON (pra.contextid = pc.id AND pra.userid = ra.userid AND pra.roleid = ra.roleid AND pra.component <> 'enrol_metabulk' $notignored)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = :activeuser)
             GROUP BY ra.roleid, ra.userid, ra.contextid, ra.itemid, e.courseid
             HAVING MAX(pra.id) IS NULL OR MAX(ue.id) IS NULL OR MIN(e.status) > :enabledinstance";
            // WHERE pra.id IS NULL OR ue.id IS NULL OR e.status <> :enabledinstance  --last line replaced
    if ($unenrolaction != ENROL_EXT_REMOVED_SUSPEND) {
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach($rs as $ra) {
            role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_metabulk', $ra->itemid);
            if ($verbose) {
                mtrace("  unassigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname);
            }
        }
        $rs->close();
    }


    // kick out or suspend users without synced roles if syncall disabled
    if (!$syncall) {
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
            $params = array();
            $params['coursecontext'] = CONTEXT_COURSE;
            $params['courseid'] = $courseid;
            $sql = "SELECT ue.userid, ue.enrolid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
                      JOIN {context} c ON (e.courseid = c.instanceid AND c.contextlevel = :coursecontext)
                 LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.itemid = e.id AND ra.userid = ue.userid)
                     WHERE ra.id IS NULL";
            $ues = $DB->get_recordset_sql($sql, $params);
            foreach($ues as $ue) {
                if (!isset($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                $meta->unenrol_user($instance, $ue->userid);
                if ($verbose) {
                    mtrace("  unenrolling: $ue->userid ==> $instance->courseid (user without role)");
                }
            }
            $ues->close();

        } else {
            // just suspend the users
            $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
            $params = array();
            $params['coursecontext'] = CONTEXT_COURSE;
            $params['courseid'] = $courseid;
            $params['active'] = ENROL_USER_ACTIVE;
            $sql = "SELECT ue.userid, ue.enrolid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
                      JOIN {context} c ON (e.courseid = c.instanceid AND c.contextlevel = :coursecontext)
                 LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.itemid = e.id AND ra.userid = ue.userid)
                     WHERE ra.id IS NULL AND ue.status = :active";
            $ues = $DB->get_recordset_sql($sql, $params);
            foreach($ues as $ue) {
                if (!isset($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                $meta->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                if ($verbose) {
                    mtrace("  suspending: $ue->userid ==> $instance->courseid (user without role)");
                }
            }
            $ues->close();
        }
    }

    // Finally sync groups.
    $affectedusers = groups_sync_with_enrolment('meta', $courseid);
    if ($verbose) {
        foreach ($affectedusers['removed'] as $gm) {
            mtrace("removing user from group: $gm->userid ==> $gm->courseid - $gm->groupname", 1);
        }
        foreach ($affectedusers['added'] as $ue) {
            mtrace("adding user to group: $ue->userid ==> $ue->courseid - $ue->groupname", 1);
        }
    }

    if ($verbose) {
        mtrace('...user enrolment synchronisation finished.');
    }

    return 0;
}