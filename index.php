<?php

require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/admin/roles/lib.php');

$roles_ids = optional_param('roles_ids');
$repeat_each = optional_param('repeat_each', 20, PARAM_INT);

admin_externalpage_setup('reportrolescapabilities');

$PAGE->requires->css('/admin/report/rolescapabilities/styles.css');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rolescapabilities', 'report_rolescapabilities'));

echo '<div id="legend_container">',
       '<h3>', get_string('legend_title', 'report_rolescapabilities'), '</h3>',
       '<dl id="legend">',
         '<dt><span class="not_set">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('notset', 'role'), '</dd>',

         '<dt><span class="allow">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('allow', 'role'), '</dd>',

         '<dt><span class="prevent">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('prevent', 'role'), '</dd>',

         '<dt><span class="deny">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></dt>',
         '<dd>', get_string('prohibit', 'role'), '</dd>',
       '</dl>',
     '</div>';

class rolescapabilities_table extends capability_table_base {

    public function __construct($context, $id, $roleids, $repeat_each) {
        global $DB;

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

        $this->repeat_each = $repeat_each;

        $roles_list = implode(',', $roleids);
        $sql = "SELECT id,shortname, name
                  FROM {$CFG->prefix}role
                 WHERE id IN ({$roles_list})
              ORDER BY sortorder";
        $this->roles = $DB->get_records_sql($sql);

    }

    protected function add_header_cells() {
        $th = '<th>' . get_string('allowed', 'role') . '</th>';
        foreach ($this->roles as $rid => $r) {
            $th .= "<th class=\"role\">{$r->name}</th>";
        }
        $th .= '<th class="name" align="left" scope="col">'.get_string('capability','role').'</th>';
        echo $th;
    }

    protected function num_extra_columns() {
        return sizeof($this->roles) + 1;
    }

    protected function add_row_cells($capability) {
        $capabilities = array_chunk(get_moodle_capabilities($this->roles), $this->repeat_each);
        $this->add_header_cells();
        foreach ($capabilities as $chunk) {

            foreach ($chunk as $capability) {

                $cap_string = get_cap_string($capability);
                echo '<tr>', $cap_string;
                foreach ($roles as $role) {
                    if (isset($capability[$role->shortname])) {
                        echo '<td class="role cap_', $capability[$role->shortname] , '">';
                    } else {
                        echo '<td class="role cap_not_set">';
                    }
                    echo '</td>';
                }   
                echo $cap_string, '</tr>';
            }
        }
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



$sql = "SELECT id, name 
          FROM {$CFG->prefix}role
         WHERE id IN ({$CFG->report_rolescapabilities_available_roles})
      ORDER BY sortorder ASC";
$available_roles = $DB->get_records_sql($sql);

if (empty($available_roles)) {
    echo $OUTPUT->heading(get_string('no_roles_available', 'report_rolescapabilities'));
}

echo '<div id="options_container">',
     '<form action="index.php" method="post">',
     '<select multiple="multiple" name="roles_ids[]" size="10" id="roles_ids">';

foreach ($available_roles as $rid => $r) {
    $selected = '';
    if (!empty($roles_ids)) {
        $selected = in_array($rid, $roles_ids) ? 'selected="selected"' : '';
    }
    echo "<option value=\"{$rid}\" {$selected}>{$r->name}</option>";
}

echo '</select>',
     '<p>',
         '<label for="repeat_each">', get_string('repeat_each', 'report_rolescapabilities'), '</label>',
         '<input type="text" id="repeat_each" name="repeat_each" value="', $repeat_each, '" size="2" />',
     '</p>',
     '<input type="submit" value="', get_string('show'), '" />',
     '</form>',
     '</div>';

if (empty($roles_ids)) {
    echo $OUTPUT->heading(get_string('no_roles_selected', 'report_rolescapabilities'));
} else {

    $report = new rolescapabilities_table(get_context_instance(CONTEXT_SYSTEM), 0, $roles_ids, $repeat_each);

    $report->display();

    echo '</table>';
}

echo $OUTPUT->footer();

function get_moodle_capabilities($roles) {
    global $CFG, $DB;

    $sql = "SELECT id, name, component, contextlevel, riskbitmask
              FROM {$CFG->prefix}capabilities
             WHERE name NOT LIKE 'moodle/legacy%'
          ORDER BY contextlevel, name";

    // first, all capabilities
    $records = $DB->get_records_sql($sql);
    $capabilities = array();
    foreach ($records as $cap) {
        $capabilities[$cap->name] = array('component' => $cap->component,
                                          'contextlevel' => $cap->contextlevel,
                                          'riskbitmask' => $cap->riskbitmask,
                                          'name' => $cap->name);
    }

    // now, the permissions by role
    foreach ($roles as $role) {

        $sql = "SELECT rc.capability, rc.permission
                  FROM {$CFG->prefix}role_capabilities rc
                  JOIN {$CFG->prefix}capabilities c
                    ON c.name = rc.capability
                 WHERE rc.contextid = 1
                   AND rc.roleid = {$role->id}
                   AND rc.capability NOT LIKE 'moodle/legacy%'
              ORDER BY c.contextlevel,c.name";

        $records = $DB->get_records_sql($sql);

        foreach ($records as $capability) {
            $capabilities[$capability->capability][$role->shortname] = $capability->permission;
        }
    }
    return $capabilities;
}

?>
