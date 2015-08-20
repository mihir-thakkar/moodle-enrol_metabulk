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
 * Meta course link/unlink form
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * enrol_metabulk manage courses form.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_metabulk_manage_form extends moodleform {

    /**
     * Form definition for manage courses.
     */
    public function definition() {
        global $CFG, $DB, $PAGE;

        $PAGE->requires->jquery();
        $PAGE->requires->js('/enrol/metabulk/search.js');

        $mform  = $this->_form;
        list($instance, $course, $availablecourses) = $this->_customdata;
        $this->course = $course;
        $coursecontext = context_course::instance($course->id);
        $enrol = enrol_get_plugin('metabulk');

        $mform->addElement('header' , 'general' , get_string('pluginname' , 'enrol_metabulk'));

        $linkedcourses = $enrol->get_linked_courses($instance->id, $availablecourses);
        $unlinkedcourses = $enrol->get_unlinked_courses($instance->id, $availablecourses);

        // Multi select form element.
        $selectgroup = array();
        $selectgroup[] = $mform->createElement('submit', 'unlink_courses', get_string('unlinkbulk', 'enrol_metabulk'));
        if (count($linkedcourses) < COURSE_MAX_COURSES_PER_DROPDOWN + 500) {
            $selectgroup[] = $mform->createElement('select', 'links', get_string('unlinkbulk', 'enrol_metabulk'), $linkedcourses,
                array('size' => 10, 'multiple' => true));
        } else {
            $selectgroup[] = $mform->createElement('select', 'links', get_string('unlinkbulk', 'enrol_metabulk'),
                array((string)count($linkedcourses) . ' Courses', 'Use search'),
                array('size' => 10, 'multiple' => true, 'disabled' => 'disabled'));
        }
        $selectgroup[] = $mform->createElement('submit', 'link_courses', get_string('linkbulk', 'enrol_metabulk'));
        if (count($unlinkedcourses) < COURSE_MAX_COURSES_PER_DROPDOWN + 500) {
            $selectgroup[] = $mform->createElement('select', 'unlinks', get_string('linkbulk', 'enrol_metabulk'), $unlinkedcourses,
                array('size' => 10, 'multiple' => true));
        } else {
            $selectgroup[] = $mform->createElement('select', 'unlinks', get_string('linkbulk', 'enrol_metabulk'),
                array((string)count($unlinkedcourses) . ' Courses', 'Use search'),
                array('size' => 10, 'multiple' => true, 'disabled' => 'disabled'));
        }
        $mform->addGroup($selectgroup, 'selectgroup', get_string('linkcourses', 'enrol_metabulk'), array(' '), false);

        $searchgroup = array();
        $searchgroup[] = &$mform->createElement('text', 'links_searchtext');
        $mform->setType('links_searchtext', PARAM_RAW);
        $searchgroup[] = &$mform->createElement('submit', 'links_searchbutton', get_string('search'));
        $mform->registerNoSubmitButton('links_searchbutton');
        $searchgroup[] = &$mform->createElement('submit', 'links_clearbutton', get_string('clear'));
        $mform->registerNoSubmitButton('links_clearbutton');
        $mform->addGroup($searchgroup, 'searchgroup', get_string('search') , array(' '), false);

        $linkcontent = '<a href="edit.php?courseid='
            . (string)$course->id . '&id='
            . (string)$instance->id . '">Edit instance</a>';
        $mform->addElement('static', '', '', $linkcontent);

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        $this->set_data($instance);
    }
}