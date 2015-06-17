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
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Meta course bulk enrolment plugin.
 * @author    Mihir Thakkar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        // Multiple instances supported - multiple parent courses linked.
        if (!empty($CFG->enrol_meta_addmultiple)) {
            return new moodle_url('/enrol/meta/addmultiple.php', array('id'=>$courseid));
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
            $editlink = new moodle_url("/enrol/metabulk/edit.php",
                array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Add new instance of enrol plugin and adds multiple courses in one enrol instance.
     * @param object $course
     * @param array instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_metabulk_instance($course, $eid, array $fields = NULL) {
        global $DB;

        if ($course->id == SITEID) {
            throw new coding_exception('Invalid request to add enrol instance to frontpage.');
        }

        $instance = new stdClass();
        $instance->enrolid        = $eid;
        //$instance->courseid       = $course->id;

        $fields = (array)$fields;
        foreach($fields as $field => $value) {
            $instance->$field = $value;
        }

        return $DB->insert_record('enrol_metabulk', $instance);
    }

    /**
     * Delete metabulk enrol plugin instance, unenrol all users.
     * @param object $instance
     * @return void
     */

    public function delete_instance($instance) {
        global $DB;

        $name = $this->get_name();
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        // First unenrol all users
        $participants = $DB->get_recordset('user_enrolments', array('enrolid' => $instance->id));
        foreach ($participants as $participant) {
            $this->unenrol_user($instance, $participant->userid);
        }
        $participants->close();

        // Now clean up all remainders that were not removed correctly
        $DB->delete_records('groups_members', array('itemid'=>$instance->id, 'component'=>'enrol_'.$name));
        $DB->delete_records('role_assignments', array('itemid'=>$instance->id, 'component'=>'enrol_'.$name));
        $DB->delete_records('user_enrolments', array('enrolid'=>$instance->id));

        // finally drop the enrol row
        $DB->delete_records('enrol', array('id'=>$instance->id));

        // Remove entries of linked courses from enrol_metabulk
        $linkedcourses = $DB->get_recordset('enrol_metabulk', array('enrolid'=>$instance->id));
        foreach ($linkedcourses as $c) {
            $DB->delete_records('enrol_metabulk', array('enrolid' => $instance->id));
        }
        $linkedcourses->close();

        // invalidate all enrol caches
        $context = context_course::instance($instance->courseid);
        $context->mark_dirty();

    }

    /**
     * Update an instance of enrol metabulk plugin.
     * @param object $instance
     * @param array instance fields
     * @return int id of updated instance, null if can not be created
     */
    public function update_instance($instance, array $fields = null) {
        global $DB;

        $instance->timemodified   = time();

        $fields = (array)$fields;
        foreach ($fields as $field => $value) {
            $instance->$field = $value;
        }

        return $DB->update_record('enrol', $instance);
    }

    /**
     * Search available courses matching the text in search box.
     * @param object $searchtext $rowlimit
     * @return available courses
     */
    public function search_courses($searchtext, $rowlimit) {

        $courses = get_courses_search(explode(" ", $searchtext), 'shortname ASC', 0, 99999, $rowlimit);
        $availablecourses = get_valid_courses($courses);

        return $availablecourses;
    }
}
