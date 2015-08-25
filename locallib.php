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
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Sync all metabulk course links.
 *
 * @param array $listofcourses
 * @param bool $verbose verbose CLI output
 * @param int $userid one user, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_metabulk_sync($listofcourses = null, $verbose = false, $userid = null) {
    global $CFG, $DB;
    require_once("{$CFG->dirroot}/group/lib.php");

    // Purge all roles if meta sync disabled, those can be recreated later here in cron.
    if (!enrol_is_enabled('metabulk')) {
        if ($verbose) {
            mtrace('Meta sync plugin is disabled, unassigning all plugin roles and stopping.');
        }
        role_unassign_all(array('component' => 'enrol_metabulk'));
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
    list($coursesql, $courseparams) = $DB->get_in_or_equal($listofcourses, SQL_PARAMS_NAMED);
    $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
    list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
    $oneuser = $userid ? "AND pue.userid = :userid" : "";
    $params['userid'] = $userid;
    $params = array_merge($params, $courseparams);
    $sql = "SELECT pue.userid, e.id as enrolid, pue.status
            FROM {user_enrolments} pue
            JOIN {enrol} pe ON (pe.id = pue.enrolid AND pe.enrol <> 'metabulk' AND pe.enrol $enabled)
            JOIN {enrol} e ON (e.enrol = 'metabulk' $onecourse)
            JOIN {enrol_metabulk} m ON (m.enrolid = e.id AND m.courseid = pe.courseid)
            JOIN {user} u ON (u.id = pue.userid AND u.deleted = 0)
        LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = pue.userid)
            WHERE ue.id IS NULL $oneuser";

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if (!$syncall) {
            // This may be slow if very many users are ignored in sync.
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
                // Bad luck, this user does not have any role we want in parent course.
                if ($verbose) {
                    mtrace("  skipping enrolling: $ue->userid ==> $instance->courseid (user without role)");
                }
                continue;
            }
        }

        $meta->enrol_user($instance, $ue->userid, null, 0, 0, $ue->status);
        if ($instance->customint2) {
            groups_add_member($instance->customint2, $ue->userid, 'enrol_metabulk', $instance->id);
        }
        if ($verbose) {
            mtrace("  enrolling: $ue->userid ==> $instance->courseid");
        }
    }
    $rs->close();

    // Unenrol as necessary - ignore enabled flag, we want to get rid of existing enrols in any case.
    $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
    list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
    $oneuser = $userid ? "AND ue.userid = :userid" : "";
    $params['userid'] = $userid;
    $params = $params + $courseparams;
    $sql = "SELECT ue.*
                FROM {user_enrolments} ue
                JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
                WHERE NOT EXISTS (SELECT 1
                FROM {enrol_metabulk} m
                JOIN {enrol} xpe ON
                    (xpe.courseid = m.courseid AND xpe.status = 0 AND xpe.enrol <> 'metabulk' AND xpe.enrol $enabled)
                JOIN {user_enrolments} xpue ON xpe.id = xpue.enrolid AND xpue.status = 0
                WHERE xpue.userid = ue.userid AND m.enrolid = ue.enrolid
                ) $oneuser";

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
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
                role_unassign_all(array(
                    'userid' => $ue->userid,
                    'contextid' => $context->id,
                    'component' => 'enrol_metabulk',
                    'itemid' => $instance->id));
                if ($verbose) {
                    mtrace("  suspending and removing all roles: $ue->userid ==> $instance->courseid");
                }
            }
        }
    }
    $rs->close();

    // Update status - meta enrols + start and end dates are ignored, sorry.
    // Note the trick here is that the active enrolment and instance constants have value 0.
    $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
    list($enabled, $params) = $DB->get_in_or_equal(explode(',', $CFG->enrol_plugins_enabled), SQL_PARAMS_NAMED, 'e');
    $oneuser = $userid ? "AND ue.userid = :userid" : "";
    $params['userid'] = $userid;
    $params = $params + $courseparams;
    $sql = "SELECT ue.userid, ue.enrolid, pue.pstatus
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
              JOIN {enrol_metabulk} m ON (m.enrolid = ue.enrolid)
              JOIN (SELECT xpue.userid, xpe.courseid, MIN(xpue.status + xpe.status) AS pstatus
                      FROM {user_enrolments} xpue
                      JOIN {enrol} xpe ON (xpe.id = xpue.enrolid AND xpe.enrol <> 'metabulk' AND xpe.enrol $enabled)
                  GROUP BY xpue.userid, xpe.courseid
                   ) pue ON (pue.courseid = m.courseid AND pue.userid = ue.userid)
             WHERE ((pue.pstatus = 0 AND ue.status > 0) OR (pue.pstatus > 0 and ue.status = 0)) $oneuser";
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        $ue->pstatus = ($ue->pstatus == ENROL_USER_ACTIVE) ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED;

        if ($ue->pstatus == ENROL_USER_ACTIVE and !$syncall and $unenrolaction != ENROL_EXT_REMOVED_UNENROL) {
            // This may be slow if very many users are ignored in sync.
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
                // Bad luck, this user does not have any role we want in parent course.
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

    // Now assign all necessary roles.
    $enabled = explode(',', $CFG->enrol_plugins_enabled);
    foreach ($enabled as $k => $v) {
        if ($v === 'metabulk') {
            continue; // No metabulk sync of meta roles.
        }
        $enabled[$k] = 'enrol_'.$v;
    }
    $enabled[] = ''; // Manual assignments are replicated too.

    $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
    list($enabled, $params) = $DB->get_in_or_equal($enabled, SQL_PARAMS_NAMED, 'e');
    $params['coursecontext'] = CONTEXT_COURSE;
    $params['activeuser'] = ENROL_USER_ACTIVE;
    $params['enabledinstance'] = ENROL_INSTANCE_ENABLED;
    $oneuser = $userid ? "AND u.id = :userid" : "";
    $params['userid'] = $userid;
    $params = $params + $courseparams;
    $sql = "SELECT DISTINCT pra.roleid, pra.userid, c.id AS contextid, m.enrolid AS enrolid, e.courseid
              FROM {role_assignments} pra
              JOIN {user} u ON (u.id = pra.userid AND u.deleted = 0)
              JOIN {context} pc ON (pc.id = pra.contextid AND pc.contextlevel = :coursecontext AND pra.component $enabled)
              JOIN {enrol} e ON (e.enrol = 'metabulk' $onecourse AND e.status = :enabledinstance)
              JOIN {enrol_metabulk} m ON (m.enrolid = e.id AND m.courseid = pc.instanceid)
              JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = u.id AND ue.status = :activeuser)
              JOIN {context} c ON (c.contextlevel = pc.contextlevel AND c.instanceid = e.courseid)
         LEFT JOIN {role_assignments} ra ON
                (ra.contextid = c.id AND ra.userid = pra.userid AND ra.roleid = pra.roleid
                    AND ra.itemid = m.enrolid AND ra.component = 'enrol_metabulk')
             WHERE ra.id IS NULL $oneuser";

    if ($ignored = $meta->get_config('nosyncroleids')) {
        list($notignored, $xparams) = $DB->get_in_or_equal(explode(',', $ignored), SQL_PARAMS_NAMED, 'ig', false);
        $params = array_merge($params, $xparams);
        $sql = "$sql AND pra.roleid $notignored";
    }

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ra) {
        role_assign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_metabulk', $ra->enrolid);
        if ($verbose) {
            mtrace("  assigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname);
        }
    }
    $rs->close();

    // Remove unwanted roles - include ignored roles and disabled plugins too.
    $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
    $params = array();
    $params['coursecontext'] = CONTEXT_COURSE;
    $oneuser = $userid ? "WHERE ra.userid = :userid" : "";
    $params['userid'] = $userid;
    $params['activeuser'] = ENROL_USER_ACTIVE;
    $params['enabledinstance'] = ENROL_INSTANCE_ENABLED;
    $params = $params + $courseparams;
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
         LEFT JOIN {role_assignments} pra ON
            (pra.contextid = pc.id AND pra.userid = ra.userid AND pra.roleid = ra.roleid
                AND pra.component <> 'enrol_metabulk' $notignored)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = :activeuser)
             $oneuser
             GROUP BY ra.roleid, ra.userid, ra.contextid, ra.itemid, e.courseid
             HAVING MAX(pra.id) IS NULL OR MAX(ue.id) IS NULL OR MIN(e.status) > :enabledinstance";

    if ($unenrolaction != ENROL_EXT_REMOVED_SUSPEND) {
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ra) {
            role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_metabulk', $ra->itemid);
            if ($verbose) {
                mtrace("  unassigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname);
            }
        }
        $rs->close();
    }

    // Kick out or suspend users without synced roles if syncall disabled.
    if (!$syncall) {
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
            $params = array();
            $params['coursecontext'] = CONTEXT_COURSE;
            $oneuser = $userid ? "AND ue.userid = :userid" : "";
            $params['userid'] = $userid;
            $params = $params + $courseparams;
            $sql = "SELECT ue.userid, ue.enrolid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
                      JOIN {context} c ON (e.courseid = c.instanceid AND c.contextlevel = :coursecontext)
                 LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.itemid = e.id AND ra.userid = ue.userid)
                     WHERE ra.id IS NULL $oneuser";
            $ues = $DB->get_recordset_sql($sql, $params);
            foreach ($ues as $ue) {
                if (!isset($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                $meta->unenrol_user($instance, $ue->userid);
                if ($verbose) {
                    mtrace("  unenrolling: $ue->userid ==> $instance->courseid (user without role)");
                }
            }
            $ues->close();

        } else {
            // Just suspend the users.
            list($coursesql, $courseparams) = $DB->get_in_or_equal($listofcourses, SQL_PARAMS_NAMED);
            $onecourse = $listofcourses ? "AND e.courseid " . $coursesql : "";
            $params = array();
            $params['coursecontext'] = CONTEXT_COURSE;
            $params['active'] = ENROL_USER_ACTIVE;
            $oneuser = $userid ? "AND ue.userid = :userid" : "";
            $params['userid'] = $userid;
            $params = $params + $courseparams;
            $sql = "SELECT ue.userid, ue.enrolid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'metabulk' $onecourse)
                      JOIN {context} c ON (e.courseid = c.instanceid AND c.contextlevel = :coursecontext)
                 LEFT JOIN {role_assignments} ra ON (ra.contextid = c.id AND ra.itemid = e.id AND ra.userid = ue.userid)
                     WHERE ra.id IS NULL AND ue.status = :active $oneuser";
            $ues = $DB->get_recordset_sql($sql, $params);
            foreach ($ues as $ue) {
                if (!isset($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
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
    if (!empty($listofcourses)) {
        foreach ($listofcourses as $course) {
            $affectedusers = groups_sync_with_enrolment('metabulk', $course);
            if ($verbose) {
                foreach ($affectedusers['removed'] as $gm) {
                    mtrace("removing user from group: $gm->userid ==> $gm->courseid - $gm->groupname", 1);
                }
                foreach ($affectedusers['added'] as $ue) {
                    mtrace("adding user to group: $ue->userid ==> $ue->courseid - $ue->groupname", 1);
                }
            }
        }
    }

    if ($verbose) {
        mtrace('...user enrolment synchronisation finished.');
    }

    return 0;
}

/**
 * Create a new group with the enrol instance's name.
 *
 * @param int $courseid
 * @param string $coursename
 * @return int $groupid Group ID for this group.
 */
function enrol_metabulk_create_new_group($courseid, $coursename) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/group/lib.php');

    $a = new stdClass();
    $a->name = $coursename;
    $a->increment = '';
    $inc = 1;
    $groupname = trim(get_string('defaultgroupnametext', 'enrol_metabulk', $a));
    // Check to see if the group name already exists in this course. Add an incremented number if it does.
    while ($DB->record_exists('groups', array('name' => $groupname, 'courseid' => $courseid))) {
        $a->increment = '(' . ($inc++) . ')';
        $groupname = trim(get_string('defaultgroupnametext', 'enrol_metabulk', $a));
    }
    // Create a new group for the course meta sync.
    $groupdata = new stdClass();
    $groupdata->courseid = $courseid;
    $groupdata->name = $groupname;
    $groupid = groups_create_group($groupdata);

    return $groupid;
}