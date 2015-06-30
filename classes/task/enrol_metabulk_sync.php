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
 * Scheduled tasks for enrol_metabulk.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_metabulk\task;
defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled tasks for metabulk enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */

class enrol_metabulk_sync extends \core\task\scheduled_task {

    public function get_name() {
        // Shown in admin screens.
        return get_string('pluginname', 'enrol_metabulk');
    }

    public function execute() { // TODO.
    }
}