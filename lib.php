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
 * @package    enrol_meta_bulk
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
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/metabulk:config', $context)) {
            return null;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/metabulk/edit.php', array('id' => $courseid) );
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
        return has_capability('enrol/meta:config', $context);
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
                array('id' => $instance->courseid, 'instanceid' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return $icons;
    }
}
