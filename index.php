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

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/roles/lib.php');

require_login(get_site());

$url = new moodle_url('/report/rolescapabilities/index.php');
$PAGE->set_url($url);
$PAGE->requires->css('/report/rolescapabilities/styles.css');
$PAGE->set_title(get_string('rolescapabilities', 'report_rolescapabilities'));
$PAGE->set_heading(get_string('rolescapabilities', 'report_rolescapabilities'));
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rolescapabilities', 'report_rolescapabilities'));

echo '<div id="topcontainer">';
echo '<div id="legendcontainer">',
       '<h3>', get_string('legend_title', 'report_rolescapabilities'), '</h3>',
       '<dl id="legend">',
         '<dt><span class="notset">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('notset', 'role'), '</dd>',

         '<dt><span class="notsetdef">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('notset', 'role'), ' ' , get_string('allowed_authenticated_user', 'report_rolescapabilities'), '</dd>',

         '<dt><span class="allow">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('allow', 'role'), '</dd>',

         '<dt><span class="allowdef">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('allow', 'role'), ' ', get_string('duplicated_authenticated_user', 'report_rolescapabilities')  , '</dd>',

         '<dt><span class="prevent">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('prevent', 'role'), '</dd>',

         '<dt><span class="deny">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('prohibit', 'role'), '</dd>',
       '</dl>',
     '</div>';

$available_role_ids = explode(',', get_config('report_rolescapabilities', 'available_roles'));
$available_roles = array();
$roles = role_get_names();
foreach ($roles as $r) {
    if (in_array($r->id, $available_role_ids)) {
        $available_roles[$r->id] = $r->localname;
    }
}

if ($data = data_submitted()) {
    $roles_ids = $data->roles_ids;
} else {
    $roles_ids = array();
}

echo '<div id="optionscontainer">',
     '<form action="index.php" method="post">',
     '<select multiple="multiple" name="roles_ids[]" size="10" id="roles_ids">';
foreach ($available_roles as $rid => $r) {
    $selected = '';
    if (!empty($roles_ids)) {
        $selected = in_array($rid, $roles_ids) ? 'selected="selected"' : '';
    }
    echo "<option value=\"{$rid}\" {$selected}>", $r, "</option>";
}
echo '</select>',
     '<p><input type="submit" value="', get_string('show'), '" /></p>',
     '</form>',
     '</div>';
echo '</div>';

if (empty($available_roles)) {
    echo $OUTPUT->heading(get_string('no_roles_available', 'report_rolescapabilities'));
}

class rolescapabilities_table extends core_role_capability_table_base {

    public function __construct($context, $id, $roleids) {
        global $DB, $CFG;

        parent::__construct($context, $id);

        $this->allrisks = get_all_risks();
        $this->risksurl = get_docs_url(s(get_string('risks', 'role')));

        $this->allpermissions = array(
            CAP_INHERIT => 'inherit',
            CAP_ALLOW => 'allow',
            CAP_PREVENT => 'prevent' ,
            CAP_PROHIBIT => 'prohibit',
        );

        $this->strperms = array();
        foreach ($this->allpermissions as $permname) {
            $this->strperms[$permname] =  get_string($permname, 'role');
        }

        $available_role_ids = explode(',', get_config('report_rolescapabilities', 'available_roles'));
        $available_roles = array();
        $roles = role_get_names();
        foreach ($roleids as $r) {
            if (in_array($r, $available_role_ids)) {
                $this->roles[$r] = $roles[$r]->localname;
            }
        }

        $context = context_system::instance();
        $has_cap = has_capability('moodle/role:manage', $context);

        $this->show_edit_link = $has_cap && $DB->record_exists('config_plugins',
                                                               array('plugin' => 'tool_editrolesbycap',
                                                                     'name' => 'version'));
    }

    protected function add_header_cells() {
        $th = '';
        if ($this->show_edit_link) {
            $th .= "<th class='edit'></th>";
        }
        foreach ($this->roles as $rid => $r) {
            $th .= "<th class=\"role\">".$r."</th>";
        }
        $th .= '<th>'.get_string('risks', 'role').'</th>';
        echo $th;
    }

    protected function num_extra_columns() {
        if ($this->show_edit_link) {
            return sizeof($this->roles) + 2;
        } else {
            return sizeof($this->roles) + 1;
        }
    }

    protected function add_row_cells($capability) {
        global $DB, $OUTPUT;

        $authuser_roleid = $DB->get_field('role', 'id', array('shortname'=>'user'));
        $perm_authuser = $DB->get_records_menu('role_capabilities',
                                            array('roleid' => $authuser_roleid,
                                                 'contextid' => $this->context->id,
                                                 'capability' => $capability->name),
                                            '', 'capability,permission');
        $perm_default = $perm_authuser ? 'def' : '';
        if ($this->show_edit_link) {
            echo '<td>',
                 html_writer::link(new moodle_url('/admin/tool/editrolesbycap/index.php?cap=' . urlencode($capability->name)),
                                   html_writer::empty_tag('img', array('class' => 'iconsmall',
                                                                       'alt' => get_string('update'),
                                                                       'src' => $OUTPUT->pix_url('i/edit'))));
                 '</td>';
        }

        foreach ($this->roles as $rid => $role) {
            $permission = $DB->get_records_menu('role_capabilities',
                                                array('roleid' => $rid,
                                                     'contextid' => $this->context->id,
                                                     'capability' => $capability->name),
                                                '', 'capability,permission');
            if ($permission) {
                echo '<td class="role cap', $perm_default, $permission[$capability->name], '">';
                $str = '';
                switch($permission[$capability->name]) {
                    case CAP_ALLOW:
                        $str = get_string('allow', 'role');
                        break;
                    case CAP_PREVENT:
                        $str = get_string('prevent', 'role');
                        break;
                    case CAP_PROHIBIT:
                        $str = get_string('prohibit', 'role');
                        break;
                }
                echo $str;
                echo '</td>';
            } else {
                if($perm_authuser) {
                    echo '<td class="role capnotsetdef"></td>';
                } else {
                    echo '<td class="role capnotset"></td>';
                }
            }
        }
        echo '<td>';
    /// One cell for all possible risks.
        foreach ($this->allrisks as $riskname => $risk) {
            if ($risk & (int)$capability->riskbitmask) {
               echo $this->get_risk_icon($riskname);
            }
        }
        echo '</td>';
    }

    /**
     * Print a risk icon, as a link to the Risks page on Moodle Docs.
     *
     * @param string $type the type of risk, will be one of the keys from the
     *      get_all_risks array. Must start with 'risk'.
     */
    function get_risk_icon($type) {
        global $OUTPUT;
        if (!isset($this->riskicons[$type])) {
            $iconurl = $OUTPUT->pix_url('i/' . str_replace('risk', 'risk_', $type));
            $text = '<img src="' . $iconurl . '" alt="' . get_string($type . 'short', 'admin') . '" />';
            $action = new popup_action('click', $this->risksurl, 'docspopup');
            $this->riskicons[$type] = $OUTPUT->action_link($this->risksurl, $text, $action, array('title'=>get_string($type, 'admin')));
        }
        return $this->riskicons[$type];
    }
}

if (empty($roles_ids)) {
    echo $OUTPUT->heading(get_string('no_roles_selected', 'report_rolescapabilities'));
} else {
    $report = new rolescapabilities_table(context_system::instance(), 0, $roles_ids);
    $report->display();
}
echo $OUTPUT->footer();
