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

/**
 * block_maj_submissions_tool_data2workshop
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_data2workshop extends block_maj_submissions_tool_form {

    protected $type = '';
    protected $modulename = 'workshop';
    protected $defaultname = 'reviewsubmissions';

    protected $template = null;

    protected $defaultvalues = array(
        'visible'         => 1,
        'submissionstart' => 0,
        'submissionend'   => 0,
        'assessmentstart' => 0,
        'assessmentend'   => 0,
        'phase'           => 10, // 10=setup, 20=submission, 30=assessment, 40=evaluation, 50=closed
        'grade'           => 100.0,
        'gradinggrade'    => 0.0,
        'strategy'        => 'rubric',
        'evaluation'      => 'best',
        'latesubmissions' => 1,
        'maxbytes'        => 0,
        'usepeerassessment' => 1,
        'overallfeedbackmaxbytes' => 0
    );

    protected $timefields = array(
        'timestart' => array('submissionstart', 'assessmentstart'),
        'timefinish' => array('submissionend', 'assessmentend')
    );

    /**
     * The name of the form field containing
     * the id of a group of anonymous submitters
     */
    protected $groupfieldname = 'anonymousauthors';

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
     * definition
     */
    public function definition() {
        $mform = $this->_form;

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

        $name = 'targetworkshop';
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_template($mform, $this->plugin, 'templateactivity', $this->modulename, $name);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $name = 'resetsubmissions';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', 0);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', self::CREATE_NEW);

        $name = $this->groupfieldname;
        $options = $this->get_group_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);

        $this->add_action_buttons();
    }

    /**
     * get_field_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_field_options() {
        global $DB;
        if ($cmid = optional_param('sourcedatabase', null, PARAM_INT)) {
            $dataid = get_fast_modinfo($this->course)->get_cm($cmid)->instance;
            $select = 'dataid = ? AND type NOT IN (?, ?, ?, ?, ?, ?)';
            $params = array($dataid, 'action', 'admin', 'constant', 'template', 'report', 'file');
            if ($options = $DB->get_records_select('data_fields', $select, $params, null, 'id,name,description')) {
                $search = self::bilingual_string();
                if (self::is_low_ascii_language()) {
                    $replace = '$2'; // low-ascii language e.g. English
                } else {
                    $replace = '$1'; // high-ascii/multibyte language
                }
                foreach ($options as $id => $option) {
                    if (preg_match('/_\d+(_[a-z]{2})?$/', $option->name)) {
                        unset($options[$id]);
                    } else {
                        $option->description = preg_replace($search, $replace, $option->description);
                        $options[$id] = $option->description.' ['.$option->name.']';
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
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        $defaultvalues = parent::get_defaultvalues($data, $time);

        if ($template = $this->get_template($data)) {
            foreach ($template as $name => $value) {
                if ($name=='id' || $name=='name') {
                    continue; // skip these fields
                }
                if (is_scalar($value)) {
                    $defaultvalues[$name] = $value;
                }
            }
        }

        return $defaultvalues;
    }

    /**
     * get_template
     */
    protected function get_template($data, $name='templateactivity') {
        global $DB;
        if ($this->template===null) {
            $this->template = false;
            if (empty($data->$name)) {
                return $this->template;
            }
            if (! $cm = $DB->get_record('course_modules', array('id' => $data->$name))) {
                return $this->template;
            }
            if (! $instance = $DB->get_record($this->modulename, array('id' => $cm->instance))) {
                return $this->template;
            }
            if (! $course = $DB->get_record('course', array('id' => $instance->course))) {
                return $this->template;
            }
            $this->template = new workshop($instance, $cm, $course);
        }
        return $this->template;
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
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/workshop/locallib.php');

        $cm = false;
        $time = time();
        $msg = array();
        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'targetworkshop');
        }

        if ($cm) {

            // cache the database id
            $databasenum = $data->sourcedatabase;
            $dataid = get_fast_modinfo($this->course)->get_cm($databasenum)->instance;

            // cache the workshop cmid
            $workshopnum  = $data->targetworkshopnum;

            // initialize counters
            $counttotal = $DB->get_field('data_records', 'COUNT(*)', array('dataid' => $dataid));
            $countselected = 0;
            $counttransferred = 0;

            // get workshop object
            $workshop = $DB->get_record('workshop', array('id' => $cm->instance));
            $workshop = new workshop($workshop, $cm, $this->course);

            // get ids of anonymous authors
            $name = $this->groupfieldname;
            $params = array('groupid' => $data->$name);
            $anonymous = $DB->get_records_menu('groups_members', $params, null, 'id,userid');

            // basic SQL to fetch records from database activity
            $select = array('dr.id AS recordid, dr.dataid');
            $from   = array('{data_records} dr');
            $where  = array('dr.dataid = ?');
            $params = array($dataid);

            if (empty($data->filterconditionsfield)) {
                $data->filterconditionsfield = array();
            }

            // add SQL to fetch only required records
            $this->add_filter_sql($data, $select, $from, $where, $params);

            // add SQL to fetch presentation content
            $fields = array('title' => '',
                            'type' => '',
                            'language' => '',
                            'keywords' => '',
                            'charcount' => 0,
                            'wordcount' => 0,
                            'abstract' => '');
            $this->add_content_sql($data, $select, $from, $where, $params, $fields, $dataid);

            $select = implode(', ', $select);
            $from   = implode(' LEFT JOIN ', $from);
            $where  = implode(' AND ', $where);

            if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {

                $countanonymous = count($anonymous);
                $countselected = count($records);
                if ($countanonymous < $countselected) {
                    $a = (object)array('countanonymous' => $countanonymous,
                                       'countselected' => $countselected);
                    $msg[] = get_string('toofewauthors', $this->plugin, $a);
                } else {

                    // select only the required number of authors and shuffle them randomly
                    $anonymous = array_slice($anonymous, 0, $countselected);
                    shuffle($anonymous);

                    // get/create id of "peer_review_link" field
                    $peer_review_link_fieldid = self::peer_review_link_fieldid($this->plugin, $dataid);

                    // do we want to overwrite previous peer_review_links ?
                    if ($workshopnum==self::CREATE_NEW) {
                        $overwrite_peer_review_links = true;
                    } else if ($data->resetsubmissions) {
                        $overwrite_peer_review_links = true;
                    } else {
                        $overwrite_peer_review_links = false;
                    }

                    // transfer settings from $template to $workshop
                    if ($template = $this->get_template($data)) {
                        // transfer grading strategy (e.g. rubric)
                        $strategy = $template->grading_strategy_instance();
                        $formdata = $this->get_strategy_formdata($strategy);
                        $formdata->workshopid = $workshop->id;
                        $strategy = $workshop->grading_strategy_instance();
                        $strategy->save_edit_strategy_form($formdata);
                    }

                    // reset workshop (=remove previous submissions), if necessary
                    if (isset($data->resetsubmissions) && $data->resetsubmissions) {
                        if ($count = $workshop->count_submissions()) {
                            $reset = (object)array(
                                // mimic settings from course reset form
                                'reset_workshop_assessments' => 1,
                                'reset_workshop_submissions' => 1,
                                'reset_workshop_phase' => 1
                            );
                            $workshop->reset_userdata($reset);
                            $msg[] = get_string('submissionsdeleted', $this->plugin, $count);
                        }
                    }

                    // switch workshop to ASSESSMENT phase
                    $workshop->switch_phase(workshop::PHASE_ASSESSMENT);

                    // transfer submission records from database to workshop
                    foreach ($records as $record) {

                        // sanitize submission title
                        $name = 'title';
                        if (empty($record->$name)) {
                            $record->$name = get_string('notitle', $this->plugin);
                        } else {
                            $record->$name = self::plain_text($record->$name);
                        }

                        // sanitize submission abstract
                        $name = 'abstract';
                        if (empty($record->$name)) {
                            $record->$name = get_string('noabstract', $this->plugin);
                        } else {
                            $record->$name = self::plain_text($record->$name);
                            if (substr_count($record->abstract, ' ') > 2) {
                                $record->wordcount = str_word_count($record->abstract);
                            }
                            $record->charcount = block_maj_submissions::textlib('strlen', $record->abstract);
                        }

                        // create content for this submission
                        $content = '';
                        foreach ($fields as $name => $field) {
                            if (isset($record->$name)) {
                                if ($name=='abstract') {
                                    $params = array('style' => 'text-align: justify; '.
                                                               'text-indent: 20px;');
                                    $content .= html_writer::tag('p', $record->$name, $params);
                                } else if ($name=='charcount' || $name=='wordcount' && $record->$name) {
                                    if ($record->$name > 0) {
                                        $fieldname = $this->instance->get_string($name, $this->plugin);
                                        $fieldname = html_writer::tag('b', $fieldname.': ');
                                        $content .= html_writer::tag('p', $fieldname.$record->$name);
                                    }
                                } else {
                                    $fieldname = self::convert_to_multilang($field, $config);
                                    $fieldname = html_writer::tag('b', $fieldname.': ');
                                    $fieldvalue = self::plain_text($record->$name);
                                    $fieldvalue = self::convert_to_multilang($fieldvalue, $config);
                                    $content .= html_writer::tag('p', $fieldname.$fieldvalue);
                                }
                            }
                        }

                        // create new submission record
                        $submission = (object)array(
                            'workshopid' => $cm->instance,
                            'authorid' => array_shift($anonymous),
                            'timecreated' => $time,
                            'timemodified' => $time,
                            'title' => $record->title,
                            'content' => $content,
                            'contentformat' => 0,
                            'contenttrust' => 0
                        );

                        // add submission to workshop
                        $params = array('workshopid' => $cm->instance,
                                        'title' => $submission->title);
                        if ($DB->record_exists('workshop_submissions', $params)) {
                            // Oops - this submission appears to be a duplicate
                            $msg[] = get_string('duplicatesubmission', $this->plugin, $submission->title);

                        } else if ($submission->id = $DB->insert_record('workshop_submissions', $submission)) {

                            // add reference to this submission from the database record
                            $link = $workshop->submission_url($submission->id)->out(false);

                            $params = array('fieldid'  => $peer_review_link_fieldid,
                                            'recordid' => $record->recordid);
                            if ($content = $DB->get_record('data_content', $params)) {
                                if (empty($content->content) || $overwrite_peer_review_links) {
                                    $content->content = $link;
                                    $DB->update_record('data_content', $content);
                                }
                            } else {
                                $content = (object)array(
                                    'fieldid'  => $peer_review_link_fieldid,
                                    'recordid' => $record->recordid,
                                    'content'  => $link
                                );
                                $content->id = $DB->insert_record('data_content', $content);
                            }
                            $counttransferred++;
                        }
                    }
                    $a = (object)array('total' => $counttotal,
                                       'selected' => $countselected,
                                       'transferred' => $counttransferred);
                    $msg[] = get_string('submissionstranferred', $this->plugin, $a);
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
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
    protected function add_content_sql(&$data, &$select, &$from, &$where, &$params, &$fields, $dataid) {
        global $DB;

        $i = count($data->filterconditionsfield);
        foreach (array_keys($fields) as $name) {
            if ($name=='charcount' || $name=='wordcount') {
                continue;
            }
            $fieldparams = array('dataid' => $dataid,
                                 'name' => "presentation_$name");
            if ($field = $DB->get_record('data_fields', $fieldparams)) {
                $fields[$name] = $field->description;

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

    /**
     * get_strategy_formdata
     *
     * @param object $strategy
     */
    protected function get_strategy_formdata($strategy) {
        $mform = $strategy->get_edit_strategy_form();
        $mform = $mform->_form;

        // initialize form $data object
        $data = new stdClass();

        // add hidden fields
        $names = array('workshopid',
                       'strategy',
                       'norepeats',
                       'sesskey',
                       '_qf__workshop_edit_rubric_strategy_form');
        foreach ($names as $name) {
            $data->$name = $mform->getElement($name)->getValue();
        }

        // add criteria
        $x = 0;
        while ($mform->elementExists("dimension$x")) {

            // set the dimensionid to 0, to force creation of a new record
            // $data->$name = $mform->getElement($name)->getValue();
            $name = "dimensionid__idx_{$x}";
            $data->$name = 0;

            // fetch criterion description
            $name = "description__idx_{$x}_editor";
            $element = $mform->getElement($name);
            $data->$name = $element->getValue();

            // fetch criterion levels
            $y = 0;
            while ($mform->elementExists("levelid__idx_{$x}__idy_{$y}")) {

                // set the levelid to 0, to force creation of a new record
                // $data->$name = $mform->getElement($name)->getValue();
                $name = "levelid__idx_{$x}__idy_{$y}";
                $data->$name = 0;

                // get grade and definition(format) for each level
                $group = $mform->getElement("level__idx_{$x}__idy_{$y}");
                foreach ($group->getElements() as $element) {
                    $name = $element->getName();
                    $value = $element->getValue();
                    if (is_array($value)) {
                        $value = reset($value);
                    }
                    $data->$name = $value;
                }
                $y++;
            }

            $name = "numoflevels__idx_{$x}";
            $data->$name = $y;

            $x++;
        }

        $name = 'config_layout';
        $group = $mform->getElement('layoutgrp');
        foreach ($group->getElements() as $element) {
            if ($name==$element->getName() && $element->getChecked()) {
                $data->$name = $element->getValue();
            }
        }

        // add submit button
        $group = $mform->getElement('buttonar');
        foreach ($group->getElements() as $element) {
            $name = $element->getName();
            if ($name=='saveandclose') {
                $data->$name = $element->getValue();
            }
        }

        return $data;
    }
}
