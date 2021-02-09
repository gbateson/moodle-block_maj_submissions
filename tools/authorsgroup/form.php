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
 * blocks/maj_submissions/block_maj_submissions.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// disable direct access to this script
defined('MOODLE_INTERNAL') || die();

// get required files
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/data2workshop/form.php');

/**
 * block_maj_submissions_tool_authorsgroup
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_authorsgroup extends block_maj_submissions_tool_data2workshop {

    protected $type = '';
    protected $modulename = '';
    protected $defaultname = '';

    protected $template = null;
    protected $defaultvalues = array();
    protected $timefields = array();

    /**
     * The name of the form field containing
     * the id of a group of anonymous submitters
     */
    protected $groupfieldnames = array('authorsgroup' => 'presenters');

    /**
     * definition
     */
    public function definition() {

        $mform = $this->_form;
        $this->set_form_id($mform);

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }

        $name = 'sourcedatabase';
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'data');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);

        $name = 'filterconditions';
        $label = get_string($name, $this->plugin);

        // create the $elements for a single filter condition
        $elements = array();
        $elements[] = $mform->createElement('select', $name.'field',    null, $this->get_field_options());
        $elements[] = $mform->createElement('select', $name.'operator', null, $this->get_operator_options());
        $elements[] = $mform->createElement('text',   $name.'value',    null, array('size' => self::TEXT_FIELD_SIZE));

        // prepare the parameters to pass to the "repeat_elements()" method
        $elements = array($mform->createElement('group', $name, $label, $elements, ' ', false));
        $repeats = optional_param('count'.$name, 0, PARAM_INT);
        $options = array($name.'field'    => array('type' => PARAM_INT),
                         $name.'operator' => array('type' => PARAM_INT),
                         $name.'value'    => array('type' => PARAM_TEXT),
                         $name => array('helpbutton' => array($name, $this->plugin)));
        $addstring = get_string('add'.$name, $this->plugin, 1);
        $this->repeat_elements($elements, $repeats, $options, 'count'.$name, 'add'.$name, 1, $addstring, true);

        $mform->disabledIf('add'.$name, 'sourcedatabase', 'eq', 0);
        $mform->disabledIf('add'.$name, 'sourcedatabase', 'eq', self::CREATE_NEW);

        $this->add_group_fields($mform);

        $name = 'resetgroup';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', 0);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', self::CREATE_NEW);

        $this->add_action_buttons();
    }

    /**
     * form_postprocessing
     *
     * @uses $DB
     * @param object $data
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function form_postprocessing() {
        global $DB;

        $cm = false;
        $msg = array();
        $config = $this->instance->config;


        // get the incomfing form $data
        if ($data = $this->get_data()) {
            $groupid = $data->authorsgroup;
            $resetgroup = $data->resetgroup;
            $databasenum = $data->sourcedatabase;
        } else {
            $groupid = 0;
            $resetgroup = 0;
            $databasenum = 0;
        }

        if ($databasenum && $groupid) {

            // cache the database id
            $dataid = get_fast_modinfo($this->course)->get_cm($databasenum)->instance;

            // cache the groupname
            $groupname = groups_get_group_name($groupid);
            $groupname = format_string($groupname);

            // reset group, if required
            if ($resetgroup) {
                $msg[] = get_string('groupreset', $this->plugin, $groupname);
                $DB->delete_records('groups_members', array('groupid' => $groupid));
            }

            // cache ids of enrolled users
            $fields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic';
            $enrolledusers = get_enrolled_users($this->course->context, '', 0, $fields);

            // initialize counters
            $countsubmissions = $DB->get_field('data_records', 'COUNT(*)', array('dataid' => $dataid));
            $countselected = 0;
            $countpresenters = 0;
            $countadded = 0;

            // basic SQL to fetch records from database activity
            $select = array('dr.id AS recordid, dr.dataid');
            $from   = array('{data_records} dr');
            $where  = array('dr.dataid = ?');
            $order  = '';
            $params = array($dataid);

            if (empty($data->filterconditionsfield)) {
                $data->filterconditionsfield = array();
            }

            // add SQL to fetch only required records
            $this->add_filter_sql($data, $select, $from, $where, $params);

            // add SQL to fetch presentation content
            $fields = array('presentation_title' => '',
                            'presentation_type' => '',
                            'presentation_language' => '',
                            'presentation_keywords' => '',
                            'presentation_abstract' => '');
            $this->add_content_sql($data, $select, $from, $where, $order, $params, $fields, $dataid);

            $select = implode(', ', $select);
            $from   = implode(' LEFT JOIN ', $from);
            $where  = implode(' AND ', $where);

            if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
                $countselected = count($records);
                list($where, $params) = $DB->get_in_or_equal(array_keys($records));
                $select = 'dc.id, dc.fieldid, dc.recordid, dc.content, '.
                          'df.name AS fieldname';
                $from   = '{data_content} dc, '.
                          '{data_fields} df';
                $where  = "dc.recordid $where ".
                          'AND dc.content IS NOT NULL '.
                          'AND dc.content <> ? '.
                          'AND dc.fieldid = df.id '.
                          'AND ('.$DB->sql_like('df.name', '?').' OR '.$DB->sql_like('df.name', '?').')';
                array_push($params, '', 'name_given%', 'name_surname%');
                $records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params);
            }

            // extract presenter names
            $names = array();
            if ($records) {
                foreach ($records as $record) {

                    // process name field
                    $i = 0;
                    $name = '';
                    $type = '';
                    $lang = 'xx';

                    $rid = $record->recordid;
                    $field = $record->fieldname;
                    $value = $record->content;

                    $parts = explode('_', $field);
                    switch (count($parts)) {
                        case 2:
                            list($name, $type) = $parts;
                            break;
                        case 3:
                            if (is_numeric($parts[2])) {
                                list($name, $type, $i) = $parts;
                            } else {
                                list($name, $type, $lang) = $parts;
                            }
                            break;
                        case 4:
                            if (is_numeric($parts[2])) {
                                list($name, $type, $i, $lang) = $parts;
                            } else {
                                list($name, $type, $lang, $i) = $parts;
                            }
                            break;
                    }

                    if (empty($names[$rid])) {
                        $names[$rid] = array();
                    }
                    if (empty($names[$rid][$i])) {
                        $names[$rid][$i] = array();
                    }
                    if (empty($names[$rid][$i][$lang])) {
                        $names[$rid][$i][$lang] = array();
                    }
                    switch ($type) {
                        case 'given': $type = 'firstname'; break;
                        case 'surname': $type = 'lastname'; break;
                    }
                    $value = block_maj_submissions::textlib('strtoupper', $value);
                    $names[$rid][$i][$lang][$type] = $value;
                }

                $userids = array();
                foreach ($names as $rid => $parts) {
                    foreach ($parts as $i => $langs) {
                        foreach ($langs as $lang => $types) {
                            if (array_key_exists('firstname', $types) && array_key_exists('lastname', $types)) {
                                $select = 'deleted = ? AND ((firstname = ? AND lastname = ?) OR (firstnamephonetic = ? AND lastnamephonetic = ?))';
                                $params = array(0,
                                                $types['firstname'],
                                                $types['lastname'],
                                                $types['firstname'],
                                                $types['lastname']);
                                if ($users = $DB->get_records_select('user', $select, $params, 'id')) {
                                    $userid = 0;
                                    foreach ($users as $user) {
                                        if (array_key_exists($user->id, $enrolledusers)) {
                                            $userid = $user->id;
                                            break;
                                        }
                                    }
                                    if ($userid) {
                                        // add only the enrolled user
                                        $userids[$userid] = 1;
                                    } else {
                                        // Add all users, since none are enrolled.
                                        // They will be listed as "No role" in the group.
                                        foreach ($users as $user) {
                                            $userids[$user->id] = 1;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $countpresenters = count($userids);
                $userids = array_keys($userids);
                sort($userids); // ascending ID

                foreach ($userids as $userid) {
                    if (! $DB->record_exists('groups_members', array('groupid' => $groupid, 'userid' => $userid))) {
                        $user = (object)array(
                            'groupid'   => $groupid,
                            'userid'    => $userid,
                            'timeadded' => $this->time,
                            'component' => '',
                            'itemid'    => 0
                        );
                        $DB->insert_record('groups_members', $user);
                        $countadded++;
                    }
                }

                $a = (object)array('submissions' => $countsubmissions,
                                   'selected'    => $countselected,
                                   'presenters'  => $countpresenters,
                                   'added'       => $countadded,
                                   'group'       => $groupname);
                $msg[] = get_string('presentersadded', $this->plugin, $a);
            }
        }

        return $this->form_postprocessing_msg($msg);
    }
}
