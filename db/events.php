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
 * Bulk meta course enrolment plugin event handler definition.
 *
 * @package enrol_metabulk
 * @category event
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// List of observers.
$observers = array(

    array(
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'enrol_metabulk_observer::user_enrolment_created',
    ),
    array(
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'enrol_metabulk_observer::user_enrolment_deleted',
    ),
    array(
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'enrol_metabulk_observer::user_enrolment_updated',
    ),
    array(
        'eventname'   => '\core\event\role_assigned',
        'callback'    => 'enrol_metabulk_observer::role_assigned',
    ),
    array(
        'eventname'   => '\core\event\role_unassigned',
        'callback'    => 'enrol_metabulk_observer::role_unassigned',
    ),
    array(
        'eventname'   => '\core\event\course_deleted',
        'callback'    => 'enrol_metabulk_observer::course_deleted',
    ),
);
