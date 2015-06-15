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

class enrol_metabulk_edit_form extends moodleform {
    protected $course;

    public function definition() {
        global $CFG, $DB;

        $mform  = $this->_form;
        list($instance, $course, $availablecourses) = $this->_customdata;
        $this->course = $course;
        $coursecontext = context_course::instance($course->id);
        $enrol = enrol_get_plugin('metabulk');

        $mform->addElement('header' , 'general' , get_string('pluginname' , 'enrol_metabulk'));
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        // Multi select form element.
        $select = $mform->addElement('select', 'link', get_string('linkbulk', 'enrol_metabulk'), $availablecourses,
            array('size' => 10, 'multiple' => true));
        $select->setMultiple(true);

        // Search box.
        $searchgroup = array();
        $searchgroup[] = &$mform->createElement('text', 'links_searchtext');
        $mform->setType('links_searchtext', PARAM_RAW);
        $searchgroup[] = &$mform->createElement('submit', 'links_searchbutton', get_string('search'));
        $mform->registerNoSubmitButton('links_searchbutton');
        $searchgroup[] = &$mform->createElement('submit', 'links_clearbutton', get_string('clear'));
        $mform->registerNoSubmitButton('links_clearbutton');
        $mform->addGroup($searchgroup, 'searchgroup', get_string('search') , array(' '), false);

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        if ($instance->id) {
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
        $buttonarray[0] = $mform->createElement('submit', 'submitbutton', get_string('addinstance', 'enrol'));
        $buttonarray[1] = $mform->createElement('submit', 'submitbuttonnext', get_string('addinstanceanother', 'enrol'));
        $buttonarray[2] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    public function validation($data, $files) {
        // Validation data.
    }
}
