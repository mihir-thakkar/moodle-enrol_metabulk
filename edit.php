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
 * Adds new instance of enrol_meta_bulk to specified course.
 *
 * @package    enrol_meta_bulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/metabulk/edit_form.php");
require_once("$CFG->dirroot/group/lib.php");

$id = required_param('id', PARAM_INT);
$instanceid = optional_param('instanceid', 0, PARAM_INT);
$message = optional_param('message', null, PARAM_TEXT);
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/metabulk:config', $context);

$PAGE->set_url('/enrol/metabulk/edit.php', array('id' => $course->id, 'instanceid' => $instanceid));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
$pageurl = new moodle_url('/enrol/metabulk/edit.php', array('id' => $course->id, 'instanceid' => $instanceid));

if (!enrol_is_enabled('metabulk')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('metabulk');
$availablecourses = array();

if ($instanceid) {
    require_capability('enrol/metabulk:config', $context);
    $instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'metabulk',
        'id' => $instanceid), '*', MUST_EXIST);

} else {
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
    }
    $instance = null;
}

$instance = new stdClass();
$mform = new enrol_metabulk_edit_form(null, array($instance, $course));


if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // Entry in enrol table.
    $enrol->add_instance($course, array('name' => $data->name));
    if (!empty($data->submitbuttonnext)) {
	       redirect(new moodle_url('/enrol/metabulk/edit.php',
               array('id' => $course->id , 'instanceid' => $instanceid , 'message' => 'added')));
    } else {
        redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
    }
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_metabulk'));

echo $OUTPUT->header();
if ($message === 'added') {
    echo $OUTPUT->notification(get_string('instanceadded', 'enrol'), 'notifysuccess');
}
$mform->display();
echo $OUTPUT->footer();
