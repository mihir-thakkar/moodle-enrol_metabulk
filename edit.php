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
 * Adds new instance of enrol_metabulk to specified course.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/metabulk/edit_form.php");
require_once("$CFG->dirroot/group/lib.php");
require_once("$CFG->dirroot/enrol/metabulk/locallib.php");

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);
$message = optional_param('message', null, PARAM_TEXT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/metabulk:config', $context);

$PAGE->set_url('/enrol/metabulk/edit.php', array('courseid' => $course->id, 'id' => $instanceid));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
$pageurl = new moodle_url('/enrol/metabulk/edit.php', array('courseid' => $course->id, 'id' => $instanceid));

if (!enrol_is_enabled('metabulk')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('metabulk');
$rowlimit = $enrol->get_config('addmultiple_rowlimit', 0);

$availablecourses = array();
$existing = $DB->get_records('enrol', array('enrol' => 'metabulk', 'courseid' => $course->id));


if ($instanceid) {
    $instance = $DB->get_record('enrol',
        array('courseid' => $course->id, 'enrol' => 'metabulk', 'id' => $instanceid), '*', MUST_EXIST);
} else {
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
    $instance = new stdClass();
    $instance->id         = null;
    $instance->courseid   = $course->id;
    $instance->enrol      = 'metabulk';
}

// Try and make the manage instances node on the navigation active.
$courseadmin = $PAGE->settingsnav->get('courseadmin');
if ($courseadmin && $courseadmin->get('users') && $courseadmin->get('users')->get('manageinstances')) {
    $courseadmin->get('users')->get('manageinstances')->make_active();
}

$mform = new enrol_metabulk_edit_form(null, array($instance, $course, $availablecourses));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    if (!empty($data->customint2) && $data->customint2 == ENROL_METABULK_CREATE_GROUP) {
         $data->customint2 = enrol_metabulk_create_new_group($course->id, $data->name);
    }
    // Entry already there in enrol table.
    if ($instance->id) {
        $DB->update_record('enrol', array('id' => $instance->id, 'name' => $data->name, 'customint2' => $data->customint2));
        enrol_metabulk_sync(array($course->id));
    } else {
        // Add new instance of enrol_metabulk.
        $eid = $enrol->add_instance($course, array('name' => $data->name));
        enrol_metabulk_sync(array($course->id));
        if (!empty($data->submitbutton)) {
            redirect(new moodle_url('/enrol/metabulk/manage.php', array('courseid' => $courseid, 'id' => $eid)));
        }
    }
    redirect($returnurl);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_metabulk'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
