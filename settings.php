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
 * Displays a color-coded view of roles' capabilities
 *
 * @package    report
 * @subpackage rolescapabilities
 * @author     Daniel Neis <danielneis@gmail.com>
 * @copyright  2011 onwards Daniel Neis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$ADMIN->add('reports',
            new admin_externalpage('reportrolescapabilities',
                                   get_string('rolescapabilities', 'report_rolescapabilities'),
                                   "{$CFG->wwwroot}/report/rolescapabilities/index.php",
                                   'report/rolescapabilities:view'));

$records = $DB->get_records('role',  array(), 'sortorder ASC', 'id,name');
$roles = array();
foreach ($records as $r) {
    $roles[$r->id] = format_string($r->name);
}
$temp = new admin_settingpage('rolescapabilities', get_string('rolescapabilities', 'report_rolescapabilities'));
$temp->add(new admin_setting_configmultiselect('report_rolescapabilities_available_roles', 
                                               get_string('config_available_roles', 'report_rolescapabilities'),
                                               get_string('desc_available_roles', 'report_rolescapabilities'),
                                               null, $roles));
$settings = $temp;
