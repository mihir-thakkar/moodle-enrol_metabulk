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
 * Create and remove links to courses
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/enrol/metabulk/manage_form.php");
require_once("$CFG->dirroot/group/lib.php");
require_once("$CFG->dirroot/enrol/metabulk/locallib.php");

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/metabulk:config', $context);

$PAGE->set_url('/enrol/metabulk/manage.php', array('courseid' => $course->id, 'id' => $instanceid));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
$pageurl = new moodle_url('/enrol/metabulk/manage.php', array('courseid' => $course->id, 'id' => $instanceid));

if (!enrol_is_enabled('metabulk')) {
    redirect($returnurl);
}

// Get text from the Search box 'link_searchtext'.
$searchtext = optional_param('links_searchtext', '', PARAM_RAW);
// If clear button pressed, redirect & empty the textbox.
if (optional_param('links_clearbutton', 0, PARAM_RAW) && confirm_sesskey()) {
    redirect($pageurl);
}

$enrol = enrol_get_plugin('metabulk');
$rowlimit = $enrol->get_config('addmultiple_rowlimit', 0);

$availablecourses = array();
$existing = $DB->get_records('enrol', array('enrol' => 'metabulk', 'courseid' => $course->id));

if (!empty($searchtext) and confirm_sesskey()) {
    $availablecourses = $enrol->search_courses($searchtext, $rowlimit);
} else {
    $where = '';
    $params = array();
    $existing = $DB->get_records('enrol_metabulk', array('courseid' => $course->id));
    $select = ', ' . context_helper::get_preload_record_columns_sql('ctx');
    $join = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
    $plugin = enrol_get_plugin('metabulk');
    $sortorder = 'c.' . $plugin->get_config('coursesort', 'sortorder') . ' ASC';
    $sql = "SELECT c.id, c.fullname, c.shortname, c.visible $select FROM {course} c $join $where ORDER BY $sortorder";
    $rs = $DB->get_recordset_sql($sql, array('contextlevel' => CONTEXT_COURSE) + $params);

    $availablecourses = $enrol->get_valid_courses($rs);
    $rs->close();
}

if ($instanceid) {
    $instance = $DB->get_record('enrol',
        array('courseid' => $course->id, 'enrol' => 'metabulk', 'id' => $instanceid), '*', MUST_EXIST);
} else {
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }
}

// Try and make the manage instances node on the navigation active.
$courseadmin = $PAGE->settingsnav->get('courseadmin');
if ($courseadmin && $courseadmin->get('users') && $courseadmin->get('users')->get('manageinstances')) {
    $courseadmin->get('users')->get('manageinstances')->make_active();
}

$mform = new enrol_metabulk_manage_form(null, array($instance, $course, $availablecourses));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    if (!empty($data->link_courses)) {
        $enrol->add_links($instance, $data);
    } else if (!empty($data->unlink_courses)) {
        $enrol->remove_links($instance, $data);
    }
    redirect($pageurl);
}

$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_metabulk'));

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
