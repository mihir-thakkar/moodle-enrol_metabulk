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
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Bulk meta course enrolment tests.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_metabulk_plugin_testcase extends advanced_testcase {

    /**
     * Enable metabulk enrolment plugin.
     */
    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['metabulk'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Disable metabulk enrolment plugin.
     */
    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['metabulk']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Check if user is enrolled in a metabulk enrol instance.
     *
     * @param int $user
     * @param int $enrol
     * @param int $role
     * @return bool true if user is enrolled.
     */
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

    /**
     * Check if user enrolled in a metabulk enrol instance has a role.
     *
     * @param int $user
     * @param int $enrol
     * @param int $role
     * @return bool true if user has a role.
     */
    protected function has_role($user, $enrol, $role) {
        global $DB;

        $context = context_course::instance($enrol->courseid);

        if ($role === false) {
            if ($DB->record_exists('role_assignments',
                array('contextid' => $context->id,
                    'userid' => $user->id,
                    'component' => 'enrol_metabulk',
                    'itemid' => $enrol->id))) {
                return false;
            }
        } else if (!$DB->record_exists('role_assignments',
            array('contextid' => $context->id,
                'userid' => $user->id,
                'roleid' => $role->id,
                'component' => 'enrol_metabulk',
                'itemid' => $enrol->id))) {
            return false;
        }

        return true;
    }

    /**
     * Get all enrolled users in an enrol instance.
     *
     * @param int $enrol
     * @param int $status
     * @return array of enroled users.
     */
    protected function get_enroled_users($enrol, $status) {
        global $DB;
        return $DB->get_fieldset_sql("SELECT userid "
                . "FROM {user_enrolments} "
                . "WHERE enrolid = ? AND status = ? "
                . "ORDER BY userid", array($enrol->id, $status));
    }

    /**
     * Get roles of all enrolled users in a course.
     *
     * @param int $course
     * @return array of enroled users with their roles.
     */
    protected function get_role_assignments($course) {
        global $DB;
        $context = context_course::instance($course->id);
        $rs = $DB->get_recordset_sql("SELECT userid, roleid "
                . "FROM {role_assignments} "
                . "WHERE contextid = ? "
                . "ORDER BY userid, roleid", array($context->id));
        $rv = array();
        foreach ($rs as $record) {
            $rv[] = (array)$record;
        }
        $rs->close();
        return $rv;
    }

    /**
     * Bulk meta enrolment synchronisation tests.
     *
     */
    public function test_sync() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $metalplugin = enrol_get_plugin('metabulk');
        $manplugin = enrol_get_plugin('manual');

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();
        $user6 = $this->getDataGenerator()->create_user();
        $user7 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = $this->getDataGenerator()->create_course();
        $course5 = $this->getDataGenerator()->create_course();
        $manual1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $manual2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $manual3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $manual4 = $DB->get_record('enrol', array('courseid' => $course4->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $manual5 = $DB->get_record('enrol', array('courseid' => $course5->id, 'enrol' => 'manual'), '*', MUST_EXIST);

        $student = $DB->get_record('role', array('shortname' => 'student'));
        $teacher = $DB->get_record('role', array('shortname' => 'teacher'));
        $manager = $DB->get_record('role', array('shortname' => 'manager'));

        $this->disable_plugin();

        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $student->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id, $student->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course1->id, 0);
        $this->getDataGenerator()->enrol_user($user4->id, $course1->id, $teacher->id);
        $this->getDataGenerator()->enrol_user($user5->id, $course1->id, $manager->id);

        $this->getDataGenerator()->enrol_user($user1->id, $course2->id, $student->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, $teacher->id);

        $this->getDataGenerator()->enrol_user($user5->id, $course3->id, $student->id);
        $this->getDataGenerator()->enrol_user($user6->id, $course3->id, $teacher->id);
        $this->getDataGenerator()->enrol_user($user7->id, $course3->id, $manager->id);

        $this->getDataGenerator()->enrol_user($user1->id, $course5->id, $teacher->id);
        $this->getDataGenerator()->enrol_user($user6->id, $course5->id, $student->id);

        $this->assertEquals(12, $DB->count_records('user_enrolments'));
        $this->assertEquals(11, $DB->count_records('role_assignments'));

        set_config('syncall', 0, 'enrol_metabulk');
        set_config('nosyncroleids', $manager->id, 'enrol_metabulk');

        require_once($CFG->dirroot.'/enrol/metabulk/locallib.php');

        enrol_metabulk_sync(null, false);
        $this->assertEquals(12, $DB->count_records('user_enrolments'));
        $this->assertEquals(11, $DB->count_records('role_assignments'));

        $this->enable_plugin();
        enrol_metabulk_sync(null, false);
        $this->assertEquals(12, $DB->count_records('user_enrolments'));
        $this->assertEquals(11, $DB->count_records('role_assignments'));

        // Create Bulk meta course link instance in course3.
        $e3 = $metalplugin->add_instance($course3, array('name' => 'C3 enrolinstance'));
        // Create Bulk meta course link instance in course4.
        $e4 = $metalplugin->add_instance($course4, array('name' => 'C4 enrolinstance'));
        // Create Bulk meta course link instance in course5.
        $e5 = $metalplugin->add_instance($course5, array('name' => 'C5 enrolinstance'));

        $enrol3 = $DB->get_record('enrol', array('id' => $e3, 'name' => 'C3 enrolinstance'));
        $enrol4 = $DB->get_record('enrol', array('id' => $e4, 'name' => 'C4 enrolinstance'));
        $enrol5 = $DB->get_record('enrol', array('id' => $e5, 'name' => 'C5 enrolinstance'));

        // Link course2 to course4.
        $data = new stdClass();
        $data->unlinks = array($course2->id);
        $metalplugin->add_links($enrol4, $data);

        // Users of course2 enrolled in course4, all active, no suspended.
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // Roles of all active users in course4 assigned as per course2.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // Link course1 and course2 to course3.
        $data = new stdClass();
        $data->unlinks = array($course1->id, $course2->id);
        $metalplugin->add_links($enrol3, $data);

        enrol_metabulk_sync(null, false);
        // Users of course1 and course2 enrolled in course3 excluding managers and users with no roles.
        $this->assertEquals(array($user1->id, $user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // Roles of all active users in course3 assigned as per course 1 and course2.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Enable syncall.
        set_config('syncall', 1, 'enrol_metabulk');
        enrol_metabulk_sync(array($course3->id, $course4->id), false);
        // Users with no roles are enrolled as suspended and managers enrolled as active.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));

        // Unenroll user1 from course1 and course2 after disabling the plugin.
        $this->disable_plugin();
        $manplugin->unenrol_user($manual1, $user1->id);
        $manplugin->unenrol_user($manual2, $user1->id);

        // No change in user enrolments of course3 and course4 yet.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // No change in roles assignment of course3 and course4 yet.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // Enable enrol_metabulk plugin and run cron for course4.
        $this->enable_plugin();
        // Set config - unenrol users who are removed from parent course.
        set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND, 'enrol_metabulk');
        enrol_metabulk_sync(array($course4->id), false);

        // Deleted user (user1) suspended in course4.
        $this->assertEquals(array($user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // User is suspended. No changes to role assignments.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // User1 is still enrolled in course4 in suspended state but with roles.
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol4, $student));
        $this->assertTrue($DB->record_exists('user_enrolments',
            array('enrolid' => $enrol4->id, 'status' => ENROL_USER_SUSPENDED, 'userid' => $user1->id)));

        // Run cron for all courses.
        enrol_metabulk_sync(null, false);
        // User1 is suspended in both course3 and course4.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array($user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // User suspended from all courses. No changes to role assignments.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // User1 is still enrolled in course3 and course4 in suspended state but with roles.
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol3, $student));
        $this->assertTrue($DB->record_exists('user_enrolments',
            array('enrolid' => $enrol3->id, 'status' => ENROL_USER_SUSPENDED, 'userid' => $user1->id)));
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol4, $student));
        $this->assertTrue($DB->record_exists('user_enrolments',
            array('enrolid' => $enrol4->id, 'status' => ENROL_USER_SUSPENDED, 'userid' => $user1->id)));

        // Set config - unenrol users who are removed from parent course and also remove role assignments.
        set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES, 'enrol_metabulk');
        // Run cron for course4.
        enrol_metabulk_sync(array($course4->id), false);
        // User1 enrolled with suspended state in course4.
        $this->assertEquals(array($user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // Roles of suspended user (user1) removed from course4.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // User1 still enrolled but is suspended and with no roles.
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol4, false));
        $this->assertTrue($DB->record_exists('user_enrolments',
            array('enrolid' => $enrol4->id, 'status' => ENROL_USER_SUSPENDED, 'userid' => $user1->id)));

        // Run cron for all courses.
        enrol_metabulk_sync(null, false);
        // User1 enrolled with suspended state in course3 and course4.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array($user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));

        // Roles of suspended user (user1) removed from course3 and course4.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // User1 is still enrolled in course3 and course4 in suspended state and with NO roles.
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol3, false));
        $this->assertTrue($DB->record_exists('user_enrolments',
            array('enrolid' => $enrol3->id, 'status' => ENROL_USER_SUSPENDED, 'userid' => $user1->id)));
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol4, false));
        $this->assertTrue($DB->record_exists('user_enrolments',
            array('enrolid' => $enrol4->id, 'status' => ENROL_USER_SUSPENDED, 'userid' => $user1->id)));

        // Set config - remove users who are removed from parent course.
        set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL, 'enrol_metabulk');
        // Run cron for course4.
        enrol_metabulk_sync(array($course4->id), false);
        // Suspended user (user1) removed from course4.
        $this->assertEquals(array($user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // Unassign all roles of suspended users(user1) from course4.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // Suspended users removed from course4.
        $this->assertFalse($this->is_meta_enrolled($user1, $enrol4));

        // Run cron for all courses.
        enrol_metabulk_sync(null, false);
        // Suspended user (user1) removed from course3 and course4.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array($user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        // Unassign all roles of suspended users(user1) from course3 and course4.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // Suspended users removed from course3 as well.
        $this->assertFalse($this->is_meta_enrolled($user1, $enrol3));

        // Now try sync triggered by events.
        set_config('syncall', 1, 'enrol_metabulk');

        // Event : enrol user1 in course1. Config : syncall = 1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $student->id);
        // All users from course1 synchronized.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // All users got their roles too!
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Run cron.
        enrol_metabulk_sync(null, false);

        // User1 still enrolled in course3 after cron.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // User1 should have role student.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : unenrol user1 from course1. Config : syncall = 1.
        $manplugin->unenrol_user($manual1, $user1->id);

        // User1 removed from course3 as well, on event of unenrollment in course1.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // No roles for user1 now.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Run cron.
        enrol_metabulk_sync(null, false);

        // User1 not there in course3 after cron. All synced!
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // No roles for user1.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : enrol user1 in course1 with no roles. Config : syncall = 1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 0);
        // User1 enrolled in course3 because syncall is enabled.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // No roles for user1.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Run cron.
        enrol_metabulk_sync(null, false);
        // Same state of course3.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // No roles for user1.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : unenrol user1 from course1. Config : syncall = 1.
        $manplugin->unenrol_user($manual1, $user1->id);
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Run cron.
        enrol_metabulk_sync(null, false);
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Config 'syncall' disabled now.
        set_config('syncall', 0, 'enrol_metabulk');
        enrol_metabulk_sync(null, false);

        // Users with no roles and with roles not allowed (i.e., managers) are removed.
        $this->assertEquals(array($user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : enrol user1 in course1 with no roles. Config : syncall = 0.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, 0);
        // User1 not enrolled in course3 because syncall is disabled and user1 has no roles.
        $this->assertEquals(array($user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Run cron.
        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertFalse($this->is_meta_enrolled($user1, $enrol3, $student));

        // Event : Assign teacher role to user1 in course1. Config : syncall = 0.
        role_assign($teacher->id, $user1->id, context_course::instance($course1->id)->id);
        // User1 is given teacher role so user1 is enrolled in course3.
        $this->assertEquals(array($user1->id, $user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // User1 has role of teacher.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Run cron.
        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user1->id, $user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : Unassign teacher role to user1 in course1. Config : syncall = 0.
        role_unassign($teacher->id, $user1->id, context_course::instance($course1->id)->id);
        // Users with no roles (i.e., user1) are not synchronized.
        $this->assertEquals(array($user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // No roles for user1.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // No roles for user1.
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : unenrol user1 from course1. Config : syncall = 0.
        $manplugin->unenrol_user($manual1, $user1->id);
        $this->assertEquals(array($user2->id, $user4->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Enable syncall. Config : syncall = 1.
        set_config('syncall', 1, 'enrol_metabulk');
        set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND, 'enrol_metabulk');
        // Run cron.
        enrol_metabulk_sync(null, false);
        // All users with no roles and not allowed roles (i.e., managers) are enrolled in course3.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : enrol user1 in course1 as student. Config : syncall = 1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $student->id);
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // User1 has role of student.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        // User1 has role of student.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : update user1 status to ENROL_USER_SUSPENDED in course1. Config : syncall = 1.
        $manplugin->update_user_enrol($manual1, $user1->id, ENROL_USER_SUSPENDED);
        // User1 is suspended.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol3, $student));

        // Event : unenrol user1 from course1. Config : syncall = 1.
        $manplugin->unenrol_user($manual1, $user1->id);
        // User1 was already suspended.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol3, $student));

        // Event : enrol user1 in course1 as student. Config : syncall = 1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $student->id);
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertTrue($this->is_meta_enrolled($user1, $enrol3, $student));

        // Enable syncall. Unenrolaction is to suspend and unassign roles for users removed externally users.
        set_config('syncall', 1, 'enrol_metabulk');
        set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES, 'enrol_metabulk');
        enrol_metabulk_sync(null, false);
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : enrol user1 in course1 as student. Config : syncall = 1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $student->id);
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));

        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : unenrol user1 from course1.
        $manplugin->unenrol_user($manual1, $user1->id);
        // User1 is unenrolled with no roles in course3.
        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array($user1->id),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : enrol user in course1 as student. Config : syncall = 1.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $student->id);
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Event : enrol user1 is course2.
        $this->getDataGenerator()->enrol_user($user1->id, $course2->id, $student->id);
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // Event : unenrol user1 from course1.
        $manplugin->unenrol_user($manual1, $user1->id);
        // User1 should be there in course3.
        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol4, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));

        // Event - create mutual link between two courses.
        // Let's check course5. No enrolments yet!
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol5, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol5, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $teacher->id),
            array('userid' => $user6->id, 'roleid' => $student->id),
        ), $this->get_role_assignments($course5));

        // Link course3 to course5.
        $data = new stdClass();
        $data->unlinks = array($course3->id);
        $metalplugin->add_links($enrol5, $data);

        // Link course5 to course3.
        $data = new stdClass();
        $data->unlinks = array($course5->id);
        $metalplugin->add_links($enrol3, $data);

        $this->assertEquals(array($user1->id, $user2->id, $user3->id, $user4->id, $user5->id, $user6->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $teacher->id),
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user2->id, 'roleid' => $student->id),
            array('userid' => $user4->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user6->id, 'roleid' => $student->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol4, ENROL_USER_ACTIVE));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
        ), $this->get_role_assignments($course4));
        $this->assertEquals(array($user5->id, $user6->id, $user7->id),
                $this->get_enroled_users($enrol5, ENROL_USER_ACTIVE));
        // No roles for managers.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user6->id, 'roleid' => $student->id),
        ), $this->get_role_assignments($course5));

        enrol_metabulk_sync(null, false);

        $this->assertEquals(array($user5->id, $user6->id, $user7->id),
                $this->get_enroled_users($enrol5, ENROL_USER_ACTIVE));
        // No roles for managers.
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user6->id, 'roleid' => $student->id),
        ), $this->get_role_assignments($course5));

        // Unenrolaction is to unenrol users which are externally removed users.
        set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL, 'enrol_metabulk');
        enrol_metabulk_sync(null, false);

        delete_course($course1, false);
        delete_course($course5, false);

        // Both user1, user2 still there in course2.
        $this->assertEquals(array($user1->id, $user2->id),
                $this->get_enroled_users($enrol3, ENROL_USER_ACTIVE));
        $this->assertEquals(array(),
                $this->get_enroled_users($enrol3, ENROL_USER_SUSPENDED));
        $this->assertEquals(array(
            array('userid' => $user1->id, 'roleid' => $student->id),
            array('userid' => $user2->id, 'roleid' => $teacher->id),
            array('userid' => $user5->id, 'roleid' => $student->id),
            array('userid' => $user6->id, 'roleid' => $teacher->id),
            array('userid' => $user7->id, 'roleid' => $manager->id),
        ), $this->get_role_assignments($course3));

        // Only 3 manually enrolled users left in course3 now after deleting course1.
        enrol_metabulk_sync(null, false);

        $this->assertEquals(9, $DB->count_records('user_enrolments'));
        $this->assertEquals(9, $DB->count_records('role_assignments'));
        $this->assertEquals(9, $DB->count_records('user_enrolments', array('status' => ENROL_USER_ACTIVE)));

        // Deleting course2.
        delete_course($course2, false);
        // No users left now.
        $this->assertEquals(3, $DB->count_records('user_enrolments'));
        $this->assertEquals(3, $DB->count_records('role_assignments'));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array('status' => ENROL_USER_ACTIVE)));
        enrol_metabulk_sync(null, false);
        $this->assertEquals(3, $DB->count_records('user_enrolments'));
        $this->assertEquals(3, $DB->count_records('role_assignments'));
        $this->assertEquals(3, $DB->count_records('user_enrolments', array('status' => ENROL_USER_ACTIVE)));

        delete_course($course3, false);
        delete_course($course4, false);

    }

    /**
     * Test user_enrolment_created event.
     */
    public function test_user_enrolment_created_event() {
        global $DB;

        $this->resetAfterTest();

        $metaplugin = enrol_get_plugin('metabulk');
        $user1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $student = $DB->get_record('role', array('shortname' => 'student'));

        $e1 = $metaplugin->add_instance($course2, array('name' => 'test user instance 1'));

        $enrol1 = $DB->get_record('enrol', array('id' => $e1, 'name' => 'test user instance 1'));

        $data = new stdClass();
        $data->unlinks = array($course1->id);
        $metaplugin->add_links($enrol1, $data);

        // Enrol user and capture event.
        $sink = $this->redirectEvents();

        $metaplugin->enrol_user($enrol1, $user1->id, $student->id);
        $events = $sink->get_events();
        $sink->close();
        $event = array_shift($events);

        // Test Event.
        $dbuserenrolled = $DB->get_record('user_enrolments', array('userid' => $user1->id));
        $this->assertInstanceOf('\core\event\user_enrolment_created', $event);
        $this->assertEquals($dbuserenrolled->id, $event->objectid);
        $this->assertEquals('user_enrolled', $event->get_legacy_eventname());
        $expectedlegacyeventdata = $dbuserenrolled;
        $expectedlegacyeventdata->enrol = 'metabulk';
        $expectedlegacyeventdata->courseid = $course2->id;
        $this->assertEventLegacyData($expectedlegacyeventdata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test user_enrolment_deleted event.
     */
    public function test_user_enrolment_deleted_event() {
        global $DB;

        $this->resetAfterTest(true);

        $metalplugin = enrol_get_plugin('metabulk');
        $user1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $student = $DB->get_record('role', array('shortname' => 'student'));

        $e1 = $metalplugin->add_instance($course2, array('name' => 'test user instance 2'));

        $enrol1 = $DB->get_record('enrol', array('id' => $e1, 'name' => 'test user instance 2'));

        $data = new stdClass();
        $data->unlinks = array($course1->id);
        $metalplugin->add_links($enrol1, $data);

        // Enrol user.
        $metalplugin->enrol_user($enrol1, $user1->id, $student->id);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));

        // Unenrol user and capture event.
        $sink = $this->redirectEvents();
        $metalplugin->unenrol_user($enrol1, $user1->id);
        $events = $sink->get_events();
        $sink->close();
        $event = array_pop($events);

        $this->assertEquals(0, $DB->count_records('user_enrolments'));
        $this->assertInstanceOf('\core\event\user_enrolment_deleted', $event);
        $this->assertEquals('user_unenrolled', $event->get_legacy_eventname());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test user_enrolment_updated event.
     */
    public function test_user_enrolment_updated_event() {
        global $DB;

        $this->resetAfterTest(true);

        $metalplugin = enrol_get_plugin('metabulk');
        $user1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $student = $DB->get_record('role', array('shortname' => 'student'));

        $e1 = $metalplugin->add_instance($course2, array('name' => 'test user instance 3'));

        $enrol1 = $DB->get_record('enrol', array('id' => $e1, 'name' => 'test user instance 3'));

        $data = new stdClass();
        $data->unlinks = array($course1->id);
        $metalplugin->add_links($enrol1, $data);

        // Enrol user.
        $metalplugin->enrol_user($enrol1, $user1->id, $student->id);
        $this->assertEquals(1, $DB->count_records('user_enrolments'));

        // Updated enrolment for user and capture event.
        $sink = $this->redirectEvents();
        $metalplugin->update_user_enrol($enrol1, $user1->id, ENROL_USER_SUSPENDED, null, time());
        $events = $sink->get_events();
        $sink->close();
        $event = array_shift($events);

        // Test Event.
        $dbuserenrolled = $DB->get_record('user_enrolments', array('userid' => $user1->id));
        $this->assertInstanceOf('\core\event\user_enrolment_updated', $event);
        $this->assertEquals($dbuserenrolled->id, $event->objectid);
        $this->assertEquals('user_enrol_modified', $event->get_legacy_eventname());
        $expectedlegacyeventdata = $dbuserenrolled;
        $expectedlegacyeventdata->enrol = 'metabulk';
        $expectedlegacyeventdata->courseid = $course2->id;
        $url = new \moodle_url('/enrol/editenrolment.php', array('ue' => $event->objectid));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventLegacyData($expectedlegacyeventdata, $event);
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test course_deleted event.
     */
    public function test_course_deleted_event() { // TODO.
        global $DB;

        $this->resetAfterTest(true);

        $metalplugin = enrol_get_plugin('metabulk');
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
    }
}
