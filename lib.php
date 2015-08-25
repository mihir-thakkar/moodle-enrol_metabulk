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
 * Meta course bulk enrolment plugin.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
/**
 * ENROL_METABULK_CREATE_GROUP constant for automatically creating a group for a meta course.
 */
define('ENROL_METABULK_CREATE_GROUP', -1);

/**
 * Meta course bulk enrolment plugin.
 * @author     Mihir Thakkar
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_metabulk_plugin extends enrol_plugin {

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        global $CFG;
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/metabulk:config', $context)) {
            return null;
        }
        return new moodle_url('/enrol/metabulk/edit.php', array('courseid' => $courseid) );
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/metabulk:config', $context);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/metabulk:config', $context);
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, 
     *                false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/metabulk:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url,
                array('class' => 'unenrollink', 'rel' => $ue->id));
        }
        return $actions;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'metabulk') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();
        if (has_capability('enrol/metabulk:config', $context)) {
            $manage = new moodle_url("/enrol/metabulk/manage.php",
                array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($manage, new pix_icon('i/courseevent', get_string('manage', 'enrol_metabulk'), 'core',
                array('class' => 'iconsmall')));
        }
        if (has_capability('enrol/metabulk:config', $context)) {
            $editlink = new moodle_url("/enrol/metabulk/edit.php",
                array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Returns valid courses by checking capabilities.
     * @param object $courses
     * @return array of valid courses
     */
    public function get_valid_courses($courses) {
        global $course, $DB;
        $validcourses = array();
        foreach ($courses as $c) {
            if ($c->id == SITEID or $c->id == $course->id or isset($existing[$c->id])) {
                continue;
            }
            $coursecontext = context_course::instance($c->id);
            if (!$c->visible and !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
                continue;
            }
            if (!has_capability('enrol/metabulk:selectaslinked', $coursecontext)) {
                continue;
            }
            $validcourses[$c->id] = $DB->get_record('course', array('id' => $c->id), '*');
        }
        return $validcourses;
    }

    /**
     * Returns all the linked courses.
     * @param object $eid
     * @param array $availablecourses
     * @return array of linked courses
     */
    public function get_linked_courses($eid, $availablecourses) {
        global $DB;
        $linkedcourses = array();
        foreach ($availablecourses as $c) {
            if ($DB->record_exists('enrol_metabulk', array('enrolid' => $eid, 'courseid' => $c->id))) {
                $linkedcourses[$c->id] = get_course_display_name_for_list($c);
            }
        }
        return $linkedcourses;
    }

    /**
     * Returns all the unlinked courses.
     * @param object $eid
     * @param array $availablecourses
     * @return array of unlinked courses
     */
    public function get_unlinked_courses($eid, $availablecourses) {
        global $DB;
        $unlinkedcourses = array();
        foreach ($availablecourses as $c) {
            if (!$DB->record_exists('enrol_metabulk', array('enrolid' => $eid, 'courseid' => $c->id))) {
                $unlinkedcourses[$c->id] = get_course_display_name_for_list($c);
            }
        }
        return $unlinkedcourses;
    }

    /**
     * Add links to courses.
     * @param object $instance
     * @param object $data
     * @return int id of enrol instance
     */
    public function add_links($instance, $data) { // Todo.
        global $DB, $CFG;

        if (!empty($data->unlinks)) {
            foreach ($data->unlinks as $unlink) {
                if ($DB->record_exists('enrol_metabulk', array('enrolid' => $instance->id, 'courseid' => $unlink))) {
                    continue;
                } else {
                    $metainstance = new stdClass();
                    $metainstance->enrolid        = $instance->id;
                    $metainstance->courseid       = $unlink;
                    $DB->insert_record('enrol_metabulk', $metainstance);
                }
            }
        }
        require_once("$CFG->dirroot/enrol/metabulk/locallib.php");
        enrol_metabulk_sync(array($instance->courseid));
        return $instance->id;
    }

    /**
     * Remove links to courses.
     * @param object $instance
     * @param object $data
     * @return int id of enrol instance
     */
    public function remove_links($instance, $data) { // Todo.
        global $DB, $CFG;

        $name = $this->get_name();
        if (!empty($data->links)) {
            foreach ($data->links as $link) {
                if ($DB->record_exists('enrol_metabulk', array('enrolid' => $instance->id, 'courseid' => $link))) {

                    // Drop the enrol metabulk row.
                    $DB->delete_records('enrol_metabulk', array('enrolid' => $instance->id, 'courseid' => $link));

                    // Invalidate all enrol caches.
                    $context = context_course::instance($instance->courseid);
                    $context->mark_dirty();
                }
            }
        }
        require_once("$CFG->dirroot/enrol/metabulk/locallib.php");
        enrol_metabulk_sync(array($instance->courseid));
        return $instance->id;
    }

    /**
     * Delete metabulk enrol plugin instance, unenrol all users.
     *
     * @param object $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;

        $name = $this->get_name();
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        // First unenrol all users.
        $participants = $DB->get_recordset('user_enrolments', array('enrolid' => $instance->id));
        foreach ($participants as $participant) {
            $this->unenrol_user($instance, $participant->userid);
        }
        $participants->close();

        // Now clean up all remainders that were not removed correctly.
        $DB->delete_records('groups_members', array('itemid' => $instance->id, 'component' => 'enrol_'.$name));
        $DB->delete_records('role_assignments', array('itemid' => $instance->id, 'component' => 'enrol_'.$name));
        $DB->delete_records('user_enrolments', array('enrolid' => $instance->id));

        // Finally drop the enrol row.
        $DB->delete_records('enrol', array('id' => $instance->id));

        // Remove entries of linked courses from enrol_metabulk.
        $linkedcourses = $DB->get_recordset('enrol_metabulk', array('enrolid' => $instance->id));
        $DB->delete_records('enrol_metabulk', array('enrolid' => $instance->id));
        $linkedcourses->close();

        // Invalidate all enrol caches.
        $context = context_course::instance($instance->courseid);
        $context->mark_dirty();

    }

    /**
     * Update instance status
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     */
    public function update_status($instance, $newstatus) {
        global $CFG;

        parent::update_status($instance, $newstatus);

        require_once("$CFG->dirroot/enrol/metabulk/locallib.php");
        enrol_metabulk_sync($instance->courseid);
    }

    /**
     * Search available courses matching the text in search box.
     * @param object $searchtext
     * @param int $rowlimit
     * @return available courses
     */
    public function search_courses($searchtext, $rowlimit) {

        $courses = get_courses_search(explode(" ", $searchtext), 'shortname ASC', 0, 99999, $rowlimit);
        $availablecourses = $this->get_valid_courses($courses);

        return $availablecourses;
    }
}
