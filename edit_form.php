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
 * Create/Edit instance form
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * enrol_metabulk edit instance form.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_metabulk_edit_form extends moodleform {

    /**
     * Form definition for manage courses.
     */
    public function definition() {
        global $CFG, $DB;

        $mform  = $this->_form;
        list($instance, $course, $availablecourses) = $this->_customdata;
        $this->course = $course;
        $coursecontext = context_course::instance($course->id);
        $enrol = enrol_get_plugin('metabulk');

        $groups = array(0 => get_string('none'));
        if (has_capability('moodle/course:managegroups', context_course::instance($course->id))) {
            $groups[ENROL_METABULK_CREATE_GROUP] = get_string('creategroup', 'enrol_metabulk');
        }
        foreach (groups_get_all_groups($course->id) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context' => context_course::instance($course->id)));
        }

        $mform->addElement('header' , 'general' , get_string('pluginname' , 'enrol_metabulk'));
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('select', 'customint2', get_string('addgroup', 'enrol_metabulk'), $groups);

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        if ($instance->id) {
            $linkcontent = '<a href="manage.php?courseid='
            . (string)$course->id . '&id='
            . (string)$instance->id . '">Manage courses</a>';
            $mform->addElement('static', '', '', $linkcontent);
            $this->add_action_buttons(true);
        } else {
            $this->add_add_buttons();
        }
        $this->set_data($instance);
    }

    /**
     * Adds buttons on create new method form
     */
    protected function add_add_buttons() {
        $mform = $this->_form;
        $buttonarray = array();
        $buttonarray[0] = $mform->createElement('submit', 'submitbutton', get_string('next', 'enrol_metabulk'));
        $buttonarray[1] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}