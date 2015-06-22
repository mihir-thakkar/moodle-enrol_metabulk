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
     * @param object $eid $availablecourses
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
     * @param object $eid $availablecourses
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
     * Add new instance of enrol plugin and adds multiple courses in one enrol instance.
     * @param object $course $data
     * @param array instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_metabulk_instance($course, $data, array $fields = null) {
        global $DB;

        if ($course->id == SITEID) {
            throw new coding_exception('Invalid request to add enrol instance to frontpage.');
        }

        // Add instance in enrol table.
        $eid = $this->add_instance($course, array('name' => $data->name));

        // Add instances in metabulk table.
        if (!empty($data->unlinks)) {
            foreach ($data->unlinks as $link) {
                $metainstance = new stdClass();
                $metainstance->enrolid        = $eid;
                $metainstance->courseid       = $link;
                $DB->insert_record('enrol_metabulk', $metainstance);
            }
        }
        return $eid;
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
     * Search available courses matching the text in search box.
     * @param object $searchtext $rowlimit
     * @return available courses
     */
    public function search_courses($searchtext, $rowlimit) {

        $courses = get_courses_search(explode(" ", $searchtext), 'shortname ASC', 0, 99999, $rowlimit);
        $availablecourses = $this->get_valid_courses($courses);

        return $availablecourses;
    }

    /**
     * Update an instance of enrol metabulk plugin.
     * @param object $instance
     * @param array instance fields
     * @return int id of updated instance, null if can not be created
     */
    public function update_instance($instance, $data, array $fields = null) { // TODO.
        global $DB, $course;

        $instance->timemodified   = time();

        $fields = (array)$fields;
        foreach ($fields as $field => $value) {
            $instance->$field = $value;
        }

        // Update entries in the table enrol_metabulk.
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
        if (!empty($data->links)) {
            foreach ($data->links as $link) {
                if ($DB->record_exists('enrol_metabulk', array('enrolid' => $instance->id, 'courseid' => $link))) {
                    $DB->delete_records('enrol_metabulk', array('enrolid' => $instance->id, 'courseid' => $link));
                }
            }
        }

        return $DB->update_record('enrol', $instance);
    }
}
