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
 * Meta bulk enrolment plugin settings and presets.
 *
 * @package    enrol_metabulk
 * @copyright  2015 Mihir Thakkar, 2013 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // General settings.
    $settings->add(new admin_setting_heading('enrol_metabulk_settings', '', get_string('pluginname_desc', 'enrol_metabulk')));

    if (!during_initial_install()) {
        $allroles = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT, true);
        $settings->add(new admin_setting_configmultiselect('enrol_metabulk/nosyncroleids',
            get_string('nosyncroleids', 'enrol_metabulk'),
            get_string('nosyncroleids_desc', 'enrol_metabulk'), array(), $allroles));

        $settings->add(new admin_setting_configcheckbox('enrol_metabulk/syncall',
            get_string('syncall', 'enrol_metabulk'), get_string('syncall_desc', 'enrol_metabulk'), 1));

        $options = array(
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'core_enrol'),
            ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'core_enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'core_enrol'),
        );
        $settings->add(new admin_setting_configselect('enrol_metabulk/unenrolaction',
            get_string('extremovedaction', 'enrol'),
            get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

        $sortoptions = array(
            'sortorder' => new lang_string('sort_sortorder', 'admin'),
            'fullname' => new lang_string('sort_fullname', 'admin'),
            'shortname' => new lang_string('sort_shortname', 'admin'),
            'idnumber' => new lang_string('sort_idnumber', 'admin'),
        );
        $settings->add(new admin_setting_configselect(
            'enrol_metabulk/coursesort',
            new lang_string('coursesort', 'enrol_metabulk'),
            new lang_string('coursesort_help', 'enrol_metabulk'),
            'sortorder',
            $sortoptions
        ));
    }
}
