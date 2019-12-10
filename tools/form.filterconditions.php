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
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');

abstract class block_maj_submissions_tool_filterconditions extends block_maj_submissions_tool_form {

    const FILTER_NONE           = 0;
    const FILTER_CONTAINS       = 1;
    const FILTER_NOT_CONTAINS   = 2;
    const FILTER_EQUALS         = 3;
    const FILTER_NOT_EQUALS     = 4;
    const FILTER_STARTSWITH     = 5;
    const FILTER_NOT_STARTSWITH = 6;
    const FILTER_ENDSWITH       = 7;
    const FILTER_NOT_ENDSWITH   = 8;
    const FILTER_EMPTY          = 9;
    const FILTER_NOT_EMPTY      = 10;
    const FILTER_IN             = 11;
    const FILTER_NOT_IN         = 12;

    /**
     * add_field_sourcedatabase
     *
     * @param object $mform
     * @param string $name of form field
     * @return void, but will update $mform
     */
    protected function add_field_sourcedatabase($mform, $name) {
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'data');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);
    }

    /**
     * add_field_filterconditions
     *
     * @param object $mform
     * @param string $name of form field
     * @param string name of form field on which this field is dependent
     * @param string comma seprated list of fields to $exclude
     * @param string comma seprated list of fields to $include
     * @return void, but will update $mform
     */
    protected function add_field_filterconditions($mform, $name, $dependentname='', $include='', $exclude='') {
        $label = get_string($name, $this->plugin);

        // create the $elements for a single filter condition
        $elements = array();
        $elements[] = $mform->createElement('select', $name.'field',    null, $this->get_field_options($include, $exclude));
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

        if ($dependentname) {
            $mform->disabledIf('add'.$name, $dependentname, 'eq', 0);
            $mform->disabledIf('add'.$name, $dependentname, 'eq', self::CREATE_NEW);
        }
    }

    /**
     * get_field_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_field_options($include=null, $exclude=null) {
        global $DB;
        if ($dataid = $this->get_dataid('sourcedatabase')) {
            $select = 'dataid = ? AND type NOT IN (?, ?, ?, ?, ?)';
            $params = array($dataid, 'action', 'constant', 'template', 'report', 'file',
                                     '%', '%', '%');
            $fields = 'id,name,description';
            if ($options = $DB->get_records_select('data_fields', $select, $params, 'name', $fields)) {

                if (is_string($exclude)) {
                    $exclude = explode(',', $exclude);
                    $exclude = array_map('trim', $exclude);
                    $exclude = array_filter($exclude);
                }
                if (empty($exclude)) {
                    $exclude = false;
                }

                if (is_string($include)) {
                    $include = explode(',', $include);
                    $include = array_map('trim', $include);
                    $include = array_filter($include);
                }
                if (empty($include)) {
                    $include = false;
                }

                $search = self::bilingual_string();
                if (self::is_low_ascii_language()) {
                    $replace = '$2'; // low-ascii language e.g. English
                } else {
                    $replace = '$1'; // high-ascii/multibyte language
                }

                foreach ($options as $id => $option) {
                    $name = $option->name;
                    $text = $option->description;
                    switch (true) {
                        case ($include):
                            $keep = in_array($name, $include);
                            break;
                        case ($exclude && in_array($name, $exclude)):
                            $keep = false;
                            break;
                        default:
                            switch (true) {
                                case (substr($name, 0, 13)=='presentation_'): $keep = true; break;
                                case (substr($name, 0, 11)=='submission_'): $keep = true; break;
                                case (substr($name, 0, 5)=='peer_'): $keep = true; break;
                                default: $keep = false;
                            }
                            if (preg_match('/_\d+(_[a-z]{2})?$/', $name)) {
                                $keep = false;
                            }
                    }
                    if ($keep==false) {
                        unset($options[$id]);
                    } else {
                        $options[$id] = preg_replace($search, $replace, $text).' ['.$name.']';
                    }
                }
            }
        } else {
            $options = false;
        }
        if ($options==false) {
            $options = array();
        }
        return $this->format_select_options($this->plugin, $options);
    }

    /**
     * get_operator_options
     *
     * see mod/taskchain/form/helper/records.php
     * "get_filter()" method (around line 662)
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_operator_options() {
        return array(self::FILTER_CONTAINS       => get_string('contains',       'filters'),
                     self::FILTER_NOT_CONTAINS   => get_string('doesnotcontain', 'filters'),
                     self::FILTER_EQUALS         => get_string('isequalto',      'filters'),
                     self::FILTER_NOT_EQUALS     => get_string('notisequalto',   $this->plugin),
                     self::FILTER_STARTSWITH     => get_string('startswith',     'filters'),
                     self::FILTER_NOT_STARTSWITH => get_string('notstartswith',  $this->plugin),
                     self::FILTER_ENDSWITH       => get_string('endswith',       'filters'),
                     self::FILTER_NOT_ENDSWITH   => get_string('notendswith',    $this->plugin),
                     self::FILTER_EMPTY          => get_string('isempty',        'filters'),
                     self::FILTER_NOT_EMPTY      => get_string('notisempty',     $this->plugin),
                     self::FILTER_IN             => get_string('isinlist',       $this->plugin),
                     self::FILTER_NOT_IN         => get_string('notisinlist',    $this->plugin));
    }

    /**
     * add_filter_sql
     *
     * @param object $data (passed by reference)
     * @return array of filtered records, and may modify $data and $fields
     */
    protected function get_filtered_records($dataid, &$data, &$fields, $recordids=null) {
        global $DB;

        // basic SQL to fetch records from database activity
        $select = array('dr.id AS recordid, dr.dataid, dr.userid, dr.timemodified');
        $from   = array('{data_records} dr');
        $where  = array('dr.dataid = ?');
        $order  = '';
        $params = array($dataid);

        if ($recordids) {
            if (count($recordids)==1) {
                $where[] = 'dr.id = ?';
                $params[] = reset($recordids);
            } else {
                $where[] = 'dr.id IN ('.implode(',', array_fill(0, count($recordids), '?')).')';
                $params = array_merge($params, $recordids);
            }
        }

        if (empty($data)) {
            $data = new stdClass();
        }

        $defaults = array(
            'sourcedatabase' => optional_param('sourcedatabase', 0, PARAM_INT),
            'countfilterconditions' => optional_param('countfilterconditions', 0, PARAM_INT),
            'filterconditionsfield' => optional_param_array('filterconditionsfield', array(), PARAM_INT),
            'filterconditionsoperator' => optional_param_array('filterconditionsoperator', array(), PARAM_INT),
            'filterconditionsvalue' => optional_param_array('filterconditionsvalue', array(), PARAM_TEXT),
            'sortfield' => optional_param('sortfield', 0, PARAM_INT),
            'sortdirection' => optional_param('sortdirection', '', PARAM_ALPHA),
            'displayperpage' => optional_param('displayperpage', 0, PARAM_INT),
            'submissions' => optional_param_array('submissions', array(), PARAM_INT)
        );
        foreach ($defaults as $name => $value) {
            if (empty($data->$name)) {
                $data->$name = $value;
            }
        }

        // add SQL to fetch only required records
        $this->add_filter_sql($data, $select, $from, $where, $params);

        // add SQL to fetch presentation content
        $this->add_content_sql($data, $select, $from, $where, $order, $params, $fields, $dataid);

        $select = implode(', ', $select);
        $from   = implode(' LEFT JOIN ', $from);
        $where  = implode(' AND ', $where);

        $sql = "SELECT $select FROM $from WHERE $where";
        if ($order) {
            $sql .= " ORDER BY $order";
        }
        if ($data->displayperpage) {
            $sql .= " LIMIT 0,$data->displayperpage";
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * add_filter_sql
     *
     * @param object $data   (passed by reference)
     * @param string $select (passed by reference)
     * @param string $from   (passed by reference)
     * @param string $where  (passed by reference)
     * @param array  $params (passed by reference)
     * @return void, but may modify $data, $select, $from $where, and $params
     */
    protected function add_filter_sql(&$data, &$select, &$from, &$where, &$params) {
        global $DB;

        foreach ($data->filterconditionsfield as $i => $fieldid) {

            // skip empty filters
            if (empty($fieldid)) {
                continue;
            }

            // define an SQL alias for the "data_content" table
            $alias = 'dc'.$i;

            array_push($select, "$alias.recordid AS recordid$i",
                                "$alias.fieldid AS fieldid$i",
                                "$alias.content AS content$i");

            $from[] = '{data_content}'." $alias ON $alias.recordid = dr.id";

            if (isset($data->filterconditionsvalue[$i])) {
                $value = $data->filterconditionsvalue[$i];
            } else {
                $value = null;
            }

            switch ($data->filterconditionsoperator[$i]) {

                case self::FILTER_CONTAINS:
                    $where[] = "$alias.fieldid = ? AND ".$DB->sql_like("$alias.content", '?');
                    array_push($params, $fieldid, '%'.$value.'%');
                    break;

                case self::FILTER_NOT_CONTAINS:
                    $where[] = "$alias.fieldid = ? AND ".$DB->sql_like("$alias.content", '?', false, false, true);
                    array_push($params, $fieldid, '%'.$value.'%');
                    break;
                    break;

                case self::FILTER_EQUALS:
                    $where[] = "$alias.fieldid = ? AND $alias.content = ?";
                    array_push($params, $fieldid, $value);
                    break;

                case self::FILTER_NOT_EQUALS:
                    $where[] = "$alias.fieldid = ? AND $alias.content != ?";
                    array_push($params, $fieldid, $value);
                    break;

                case self::FILTER_STARTSWITH:
                    $where[] = "$alias.fieldid = ? AND ".$DB->sql_like('$alias.content', '?');
                    array_push($params, $fieldid, $value.'%');
                    break;

                case self::FILTER_NOT_STARTSWITH:
                    $where[] = "$alias.fieldid = ? AND ".$DB->sql_like('$alias.content', '?', false, false, true);
                    array_push($params, $fieldid, $value.'%');
                    break;

                case self::FILTER_ENDSWITH:
                    $where[] = "$alias.fieldid = ? AND ".$DB->sql_like('$alias.content', '?');
                    array_push($params, $fieldid, '%'.$value);
                    break;

                case self::FILTER_NOT_ENDSWITH:
                    $where[] = "$alias.fieldid = ? AND ".$DB->sql_like('$alias.content', '?', false, false, true);
                    array_push($params, $fieldid, '%'.$value);
                    break;

                case self::FILTER_EMPTY:
                    $where[] = "($alias.fieldid IS NULL OR ($alias.fieldid = ? AND ($alias.content IS NULL OR $alias.content = ?)))";
                    array_push($params, $fieldid, '');
                    break;

                case self::FILTER_NOT_EMPTY:
                    $where[] = "$alias.fieldid = ? AND $alias.content IS NOT NULL AND $alias.content != ?";
                    array_push($params, $fieldid, '');
                    break;

                case self::FILTER_IN:
                    $value = explode(',', $value);
                    $value = array_map('trim', $value);
                    if (count($value)) {
                        $value = $DB->get_in_or_equal($value);
                        $params[] = $fieldid;
                        $params = array_merge($params, $value[1]);
                        $where[] = "$alias.fieldid = ? AND content ".$value[0];
                    }
                    break;

                case self::FILTER_NOT_IN:
                    $value = explode(',', $value);
                    $value = array_map('trim', $value);
                    if (count($value)) {
                        $value = $DB->get_in_or_equal($value, SQL_PARAMS_QM, 'param', false);
                        $params[] = $fieldid;
                        $params = array_merge($params, $value[1]);
                        $where[] = "$alias.fieldid = ? AND content ".$value[0];
                    }
                    break;
            }
        }
    }

    /**
     * add_content_sql
     *
     * generate SQL to fetch presentation_(title|abstract|type|language|keywords)
     *
     * @param object  $data   (passed by reference)
     * @param string  $select (passed by reference)
     * @param string  $from   (passed by reference)
     * @param string  $where  (passed by reference)
     * @param array   $params (passed by reference)
     * @param array   $fields (passed by reference)
     * @param integer $dataid
     * @return void, but may modify $data, $select, $from $where, and $params
     */
    protected function add_content_sql(&$data, &$select, &$from, &$where, &$order, &$params, &$fields, $dataid) {
        global $DB;

        $fieldnames = array_keys($fields);

        $sortfieldname = '';
        $sortdirection = '';
        if ($sortfield = $data->sortfield) {
            $fieldparams = array('dataid' => $dataid, 'id' => $sortfield);
            if ($sortfieldname = $DB->get_field('data_fields', 'name', $fieldparams)) {
                $sortdirection = ($data->sortdirection=='ASC' ? 'ASC' : 'DESC');
                $order = "$sortfieldname $sortdirection";
                if (in_array($sortfieldname, $fieldnames)===false) {
                    $fieldnames[] = $sortfieldname;
                }
            }
        }

        $i = count($data->filterconditionsfield);
        foreach ($fieldnames as $name) {
            if ($name=='charcount' || $name=='wordcount') {
                continue;
            }
            $fieldparams = array('dataid' => $dataid,
                                 'name' => $name);
            if ($field = $DB->get_record('data_fields', $fieldparams)) {
                if (array_key_exists($name, $fields)) {
                    $fields[$name] = $field->description;
                }

                $alias = 'dc'.$i;
                array_push($select, "$alias.recordid AS recordid$i",
                                    "$alias.fieldid AS fieldid$i",
                                    "$alias.content AS $name");
                $from[] = '{data_content}'." $alias ON $alias.recordid = dr.id";
                $where[] = "$alias.fieldid = ?";
                $params[] = $field->id;
                $i++;
            } else {
                // $name field does not exist in this database
                unset($fields[$name]);
            }
        }
    }
}
