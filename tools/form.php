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
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * block_maj_submissions_tool_form
 *
 * @copyright 2014 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
abstract class block_maj_submissions_tool_form extends moodleform {

    const CREATE_NEW = -1;
    const TEMPLATE_COUNT = 8;
    const TEXT_FIELD_SIZE = 20;

    /** values passed from tool creating this form */
    protected $plugin = '';
    protected $course = null;
    protected $instance = null;

    /**
     * The "course_module" id of the course activity, if any, to which this form relates
     */
    protected $cmid = 0;

    /**
     * The name of the form field, if any, containing the id of a group of anonymous users
     */
    protected $groupfieldname = '';

    /**
     * The course activity type, if any, to which this form relates
     *
     * We expect one of the following values:
     *  - register(delegates|presenters|events)
     *  - collect(presentations|sponsoreds|workshops)
     *
     * For a complete list, see the "get_timetypes()" method
     * in "blocks/maj_subimssions/block_maj_subimssions.php"
     */
    protected $type = '';

    /**
     * settings used by forms that create a new activity
     */
    protected $modulename = '';
    protected $defaultname = '';
    protected $defaultvalues = array();
    protected $timefields = array(
        'timestart' => array(),
        'timefinish' => array()
    );
    protected $permissions = array();
    protected $restrictions = array();

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {

        // cache $this->plugin, $this->course and $this->instance
        $this->cache_customdata($customdata);

        // call parent constructor, to continue normal setup
        // which includes calling the "definition()" method
        if (method_exists('moodleform', '__construct')) {
            parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
        } else {
            parent::moodleform($action, $customdata, $method, $target, $attributes, $editable);
        }
    }

    /**
     * cache_customdata
     *
     * @param array $customdata
     * @return void, but will update plugin, course and instance properties
     */
    protected function cache_customdata($customdata) {
        // cache the custom data passed from the main script
        $this->plugin  = $customdata['plugin'];
        $this->course  = $customdata['course'];
        $this->instance = $customdata['instance'];

        // convert block instance to "block_maj_submissions" object
        $this->instance = block_instance($this->instance->blockname, $this->instance);

        // set the "multilang" property, because we may need
        // multilang name strings for new activities
        $this->instance->set_multilang(true);

        // setup values that may required to create a new activity
        if ($this->type) {

            // set the "course_module" id, if it is defined and still exists
            $cmid = $this->type.'cmid';
            if (property_exists($this->instance->config, $cmid)) {
                $cmid = $this->instance->config->$cmid;
                if (array_key_exists($cmid, get_fast_modinfo($this->course)->cms)) {
                    $this->cmid = $cmid;
                }
            }

            // set start times, if any, in $defaultvalues
            $time = $this->type.'timestart';
            if (property_exists($this->instance->config, $time)) {
                $time = $this->instance->config->$time;
                foreach ($this->timefields['timestart'] as $timefield) {
                    $this->defaultvalues[$timefield] = $time;
                }
            }

            // set finish times, if any, in $defaultvalues
            $time = $this->type.'timefinish';
            if (property_exists($this->instance->config, $time)) {
                $time = $this->instance->config->$time;
                foreach ($this->timefields['timefinish'] as $timefield) {
                    $this->defaultvalues[$timefield] = $time;
                }
            }
        }
    }

    /**
     * add_field_template
     *
     * create a list of activities of the required type
     * that can be used as a template for creating a new activity
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name
     * @param string  $modulenames
     * @param string  $cmidfield
     * @return void, but will update $mform
     * @todo Finish documenting this function
     */
    protected function add_field_template($mform, $plugin, $name, $modulenames, $cmidfield) {
        global $DB;
        $options = array();
        $select = 'SELECT DISTINCT parentcontextid FROM {block_instances} WHERE blockname = ?';
        $select = "SELECT DISTINCT instanceid FROM {context} ctx WHERE ctx.id IN ($select) AND ctx.contextlevel = ?";
        $select = "id IN ($select)";
        $params = array($this->instance->instance->blockname, CONTEXT_COURSE);
        if ($courses = $DB->get_records_select('course', $select, $params, 'startdate DESC, id DESC')) {
            foreach ($courses as $course) {
                if ($cmids = self::get_cmids($mform, $course, $plugin, $modulenames, '', 0, true)) {
                    $coursename = $course->shortname;
                    $coursename = block_maj_submissions::filter_text($coursename);
                    $coursename = block_maj_submissions::trim_text($coursename);
                    $options[$coursename] = $cmids;
                    if (count($options) >= self::TEMPLATE_COUNT) {
                        break;
                    }
                }
            }
        }
        $this->add_field($mform, $plugin, $name, 'selectgroups', PARAM_INT, $options);
        if ($cmidfield) {
            $mform->disabledIf($name, $cmidfield.'num', 'neq', self::CREATE_NEW);
        }
    }

    /**
     * add_field_cm
     *
     * @param object  $mform
     * @param object  $course
     * @param string  $plugin
     * @param string  $name
     * @param integer $numdefault (optional, default=0)
     * @param string  $namedefault (optional, default="")
     * @param string  $label (optional, default="")
     * @return void, but will update $mform
     * @todo Finish documenting this function
     */
    protected function add_field_cm($mform, $course, $plugin, $name, $numdefault=0, $namedefault='') {
        if (empty($this->type)) {
            $label = get_string($name, $plugin);
        } else {
            $label = get_string($this->type.'cmid', $plugin);
        }
        if ($numdefault==0 && $namedefault=='') {
            $namedefault = $this->instance->get_string($this->type.'name', $plugin);
        }
        $numoptions = self::get_cmids($mform, $course, $plugin, $this->modulename, 'activity');
        $disabledif = array($name.'name' => $name.'num');
        if ($numdefault==0 && is_scalar(current($numoptions))) {
            $numdefault = self::CREATE_NEW;
        }
        $this->add_field_numname($mform, $plugin, $name, $numoptions, array(), $numdefault, $namedefault, $label, $disabledif);
    }

    /**
     * add_field_section
     *
     * @param object  $mform
     * @param object  $course
     * @param string  $plugin
     * @param string  $name
     * @param integer $numdefault (optional, default=0)
     * @param string  $namedefault (optional, default="")
     * @return void, but will update $mform
     * @todo Finish documenting this function
     */
    protected function add_field_section($mform, $course, $plugin, $name, $cmidfield, $numdefault=0, $namedefault='', $label='') {
        $numoptions = self::get_sectionnums($mform, $course, $plugin);
        $disabledif = array($name.'name' => $name.'num', $name.'num' => $cmidfield.'num');
        $this->add_field_numname($mform, $plugin, $name, $numoptions, array(), $numdefault, $namedefault, $label, $disabledif);
    }

    /**
     * add_field_numname
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name
     * @param array   $numoptions
     * @param array   $nameoptions
     * @param integer $numdefault
     * @param string  $namedefault
     * @param string  $label
     * @param array   $disabledif
     * @return void, but will update $mform
     * @todo Finish documenting this function
     */
    protected function add_field_numname($mform, $plugin, $name, $numoptions, $nameoptions=array(), $numdefault=0, $namedefault='', $label='', $disabledif=array()) {
        $group_name = 'group_'.$name;
        if ($label=='') {
            $label = get_string($name, $plugin);
        }
        if (is_array(current($numoptions))) {
            $select = 'selectgroups';
        } else {
            $select = 'select';
        }
        if (empty($nameoptions)) {
            $nameoptions = array('size' => self::TEXT_FIELD_SIZE);
        }
        $elements = array(
            $mform->createElement($select, $name.'num', '', $numoptions),
            $mform->createElement('text', $name.'name', '', $nameoptions)
        );
        $mform->addGroup($elements, $group_name, $label, ' ', false);
        $mform->addHelpButton($group_name, $name, $plugin);
        $mform->setType($name.'num', PARAM_INT);
        $mform->setType($name.'name', PARAM_TEXT);
        $mform->setDefault($name.'num', $numdefault);
        $mform->setDefault($name.'name', $namedefault);
        foreach ($disabledif as $element1 => $element2) {
            $mform->disabledIf($element1, $element2, 'neq', self::CREATE_NEW);
        }
    }

    /**
     * add_time_duration
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $type e.g. conference, workshops, reception,
     *                collectcollectpresentations, collectworkshops, collectsponsoreds
     *                review, revise, publish, registerdelegates, registerpresenters
     * @return void, but will update $mform
     */
    protected function add_time_duration($mform, $plugin, $type, $start=0, $finish=0) {
        $name = $type.'time';
        $groupname = 'group_'.$name;
        $label = get_string($name, $plugin);

        if (empty($options['step'])) {
            $options['step'] = 1;
        }

        $hours = array();
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf('%02d', $i);
        }
        $minutes = array();
        for ($i = 0; $i < 60; $i += $options['step']) {
            $minutes[$i] = sprintf('%02d', $i);
        }

        $dateoptions = array('optional' => true);
        $timestart = html_writer::tag('b', get_string('timestart', $plugin).' ');
        $timefinish = html_writer::tag('b', get_string('timefinish', $plugin).' ');

        $elements = array(
            $mform->createElement('select', $name.'starthour', '', $hours),
            $mform->createElement('static', '', '', ':'),
            $mform->createElement('select', $name.'startminute', '', $minutes),
            $mform->createElement('static', '', '', ' - '),
            $mform->createElement('select', $name.'finishhour', '', $hours),
            $mform->createElement('static', '', '', ':'),
            $mform->createElement('select', $name.'finishminute', '', $minutes),
        );

        $mform->addGroup($elements, $groupname, $label, '', false);
        $mform->setDefault($name.'starthour',    date('H', $start));
        $mform->setDefault($name.'startminute',  date('i', $start));
        $mform->setDefault($name.'finishhour',   date('H', $finish));
        $mform->setDefault($name.'finishminute', date('i', $finish));
        $mform->addHelpButton($groupname, $name, $plugin);
    }

    /**
     * add_field
     *
     * @param object $mform
     * @param string $plugin
     * @param string $type e.g. month, day, hour
     * @return void, but will modify $mform
     */
    protected function add_field($mform, $plugin, $name, $elementtype, $paramtype, $options=null, $default=null) {
        if ($elementtype=='selectgroups' && is_scalar(current($options))) {
            $elementtype = 'select'; // prevent error in PEAR library
        }
        $label = get_string($name, $plugin);
        $mform->addElement($elementtype, $name, $label, $options);
        $mform->setType($name, $paramtype);
        $mform->setDefault($name, $default);
        $mform->addHelpButton($name, $name, $plugin);
    }

    /**
     * get_field_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_group_options() {
        global $DB;
        $groups = groups_list_to_menu(groups_get_all_groups($this->course->id));
        $sql = 'SELECT COUNT(*) FROM {groups_members} WHERE groupid = ?';
        foreach ($groups as $id => $name) {
            $count = $DB->get_field_sql($sql, array('groupid' => $id));
            $a = (object)array('name' => $name, 'count' => $count);
            $groups[$id] = get_string('groupnamecount', $this->plugin, $a);
        }
        return $groups;
    }
    /**
     * process_action
     *
     * @uses $DB
     * @param object $course
     * @param string $sectionname
     * @return object
     * @todo Finish documenting this function
     */
    public function process_action() {
        return '';
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
    }

    /**
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        $defaultvalues = $this->defaultvalues;

        $defaultvalues['intro'] = $this->get_defaultintro();

        foreach ($this->timefields['timestart'] as $name) {
            $defaultvalues[$name] = $time;
        }

        foreach ($this->timefields['timefinish'] as $name) {
            $defaultvalues[$name] = 0;
        }

        return $defaultvalues;
    }

    /**
     * get_defaultintro
     *
     * @todo Finish documenting this function
     */
    protected function get_defaultintro() {
        return '';
    }

    /**
     * get_permissions for a new activity created with this form
     *
     * @param object containing newly submitted form $data
     * @todo array of stdClass()
     */
    public function get_permissions($data) {
        return $this->permissions;
    }

    /**
     * get_restrictions for a new activity created with this form
     *
     * @param object containing newly submitted form $data
     * @return array of stdClass()
     */
    public function get_restrictions($data) {
        $restrictions = $this->restrictions;
        if ($name = $this->groupfieldname) {
            if (isset($data->$name) && is_numeric($data->$name)) {
                $restrictions[] = (object)array(
                    'type' => 'group',
                    'id' => intval($data->$name)
                );
            }
        }
        return $restrictions;
    }

    /**
     * get_cm
     *
     * @param array   $msg
     * @param object  $data
     * @param integer $time
     * @param string  $name
     * @return object newly added $cm object; otherwise false
     */
    public function get_cm(&$msg, $data, $time, $name) {

        $cm = false;

        $activitynum  = $name.'num';
        $activitynum  = $data->$activitynum;

        $activityname = $name.'name';
        $activityname = $data->$activityname;

        if ($activityname=='') {
            if ($this->defaultname) {
                $activityname = get_string($this->defaultname, $this->plugin);
            } else {
                $activityname = get_string('pluginname', $this->modulename);
            }
        }

        $sectionnum   = $data->coursesectionnum;
        $sectionname  = $data->coursesectionname;

        if ($activitynum) {
            if ($activitynum==self::CREATE_NEW) {

                if ($sectionnum==self::CREATE_NEW) {
                    $section = self::get_section($this->course, $sectionname);
                } else {
                    $section = get_fast_modinfo($this->course)->get_section_info($sectionnum);
                }

                if ($section) {
                    $defaultvalues = $this->get_defaultvalues($data, $time);
                    $cm = self::get_coursemodule($this->course, $section, $this->modulename, $activityname, $defaultvalues);

                    if ($cm) {
                        $permissions = $this->get_permissions($data);
                        self::set_cm_permissions($cm, $permissions);

                        $restrictions = $this->get_restrictions($data);
                        self::set_cm_restrictions($cm, $restrictions);

                        if ($this->type) {
                            if ($this->cmid==0) {
                                $this->cmid = $cm->id;
                                $cmid = $this->type.'cmid';
                                $this->instance->config->$cmid = $cm->id;
                                $this->instance->instance_config_save($this->instance->config);
                            }
                        }

                        // create link to new module
                        $link = "/mod/$this->modulename/view.php";
                        $link = new moodle_url($link, array('id' => $cm->id));
                        $link = html_writer::tag('a', $activityname, array('href' => "$link"));

                        $msg[] = get_string('newactivitycreated', $this->plugin, $link);
                    } else {
                        $msg[] = get_string('newactivityskipped', $this->plugin, $activityname);
                    }
                }
            } else {
                $cm = get_fast_modinfo($this->course)->get_cm($activitynum);
            }
        }

        return $cm;
    }

    /**
     * form_postprocessing_msg
     *
     * @param array of string $msg
     * @return string
     */
    public function form_postprocessing_msg($msg) {
        if (empty($msg)) {
            return '';
        }
        if (count($msg)==1) {
            return reset($msg);
        }
        return html_writer::alist($msg, array('class' => 'toolmessagelist'));
    }

    /**
     * peer_review_link_fieldid
     *
     * @param string  $plugin
     * @param integer $dataid
     * @param string  $name of the field (optional, default="peer_review_link")
     * @return integer an id from "data_fields" table
     */
    static public function peer_review_link_fieldid($plugin, $dataid, $name='peer_review_link') {
        global $DB;

        $table = 'data_fields';
        $params = array('dataid' => $dataid,
                        'name' => $name);
        if ($field = $DB->get_record($table, $params)) {
            return $field->id;
        }

        $field = (object)array(
            'dataid'      => $dataid,
            'type'        => 'admin',
            'name'        => $name,
            'description' => get_string($name, $plugin),
            'param9'      => '0',
            'param10'     => 'text'
        );
        return $DB->insert_record($table, $field);
    }

    /**
     * plain_text
     *
     * @param string $text string possibly containing HTML and/or unicode chars
     * @return single-line, plain-text version of $text
     */
    static public function plain_text($text) {
        $search = '/(?: |\t|\r|\n|\x{00A0}|\x{3000}|&nbsp;|(?:<[^>]*>))+/us';
        return preg_replace($search, ' ', $text);
    }

    /**
     * Return a regexp sub-string to match a sequence of low ascii chars.
     */
    static public function low_ascii_substring() {
        // 0000 - 001F Control characters e.g. tab
        // 0020 - 007F ASCII basic e.g. abc
        // 0080 - 009F Control characters
        // 00A0 - 00FF ASCII extended (1) e.g. àáâãäå
        // 0100 - 017F ASCII extended (2) e.g. āăą
        return '\\x{0000}-\\x{007F}';
    }

    /**
     * is_low_ascii_language
     *
     * @param string $lang (optional, defaults to name current language)
     * @return boolean TRUE if $lang appears to be ascii language e.g. English; otherwise, FALSE
     */
    static public function is_low_ascii_language($lang='') {
        if ($lang=='') {
            $lang = get_string('thislanguage', 'langconfig');
        }
        $ascii = self::low_ascii_substring();
        return preg_match('/^['.$ascii.']+$/u', $lang);
    }

    /**
     * Return a regexp string to match string made up of
     * non-ascii chars at the start and ascii chars at the end.
     */
    static public function bilingual_string() {
        $ascii = self::low_ascii_substring();
        // ascii chars excluding numbers: 0-9 (=hex 30-39)
        $chars = '\\x{0000}-\\x{0029}\\x{0040}-\\x{007F}';
        return '/^([^'.$chars.']*[^'.$ascii.']) *(['.$ascii.']+?)$/u';
    }

    /**
     * Return a regexp string to match string made up of
     * non-ascii chars at the start and ascii chars at the end.
     */
    static public function convert_to_multilang($text, $config) {
        $langs = block_maj_submissions::get_languages($config->displaylangs);
        if (count($langs) > 1) {
            $search = self::bilingual_string();
            $replace = '<span class="multilang" lang="'.$langs[0].'">$2</span>'.
                       '<span class="multilang" lang="'.$langs[1].'">$1</span>';
            $text = preg_replace($search, $replace, $text);
        }
        return $text;
    }

    /**
     * get_section
     *
     * @uses $DB
     * @param object $course
     * @param string $sectionname
     * @return object
     * @todo Finish documenting this function
     */
    static public function get_section($course, $sectionname) {
        global $DB;

        // some DBs (e.g. MSSQL) cannot compare TEXT fields
        // so we must CAST them to something else (e.g. CHAR)
        $summary = $DB->sql_compare_text('summary');
        $sequence = $DB->sql_compare_text('sequence');

        $select = 'course = ? AND (name = ? OR '.$summary.' = ?)';
        $params = array($course->id, $sectionname, $sectionname);
        if ($section = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
            $section = reset($section);
        }

        // reuse an empty section, if possible
        if (empty($section)) {
            $select = 'course = ? AND section > ?'.
                      ' AND (name IS NULL OR name = ?)'.
                      ' AND (summary IS NULL OR '.$summary.' = ?)'.
                      ' AND (sequence IS NULL OR '.$sequence.' = ?)';
            $params = array($course->id, 0, '', '', '');
            if ($section = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
                $section = reset($section);
                $section->name = $sectionname;
                $DB->update_record('course_sections', $section);
            }
        }

        // create a new section, if necessary
        if (empty($section)) {
            $sql = 'SELECT MAX(section) FROM {course_sections} WHERE course = ?';
            if ($sectionnum = $DB->get_field_sql($sql, array($course->id))) {
                $sectionnum ++;
            } else {
                $sectionnum = 1;
            }
            $section = (object)array(
                'course'        => $course->id,
                'section'       => $sectionnum,
                'name'          => $sectionname,
                'summary'       => '',
                'summaryformat' => FORMAT_HTML,
            );
            $section->id = $DB->insert_record('course_sections', $section);
        }

        if ($section) {
            if ($section->section > self::get_numsections($course)) {
                self::set_numsections($course, $section->section);
            }
        }

        return $section;
    }

    /**
     * get_coursemodule
     *
     * @uses $DB
     * @uses $USER
     * @param object $course
     * @param object $section
     * @param string $modulename
     * @param string $instancename
     * @param array  $defaultvalues
     * @return object
     * @todo Finish documenting this function
     */
    static public function get_coursemodule($course, $section, $modulename, $instancename, $defaultvalues) {
        global $DB, $USER;

        // cache the module id
        $moduleid = $DB->get_field('modules', 'id', array('name' => $modulename));

        if (empty($moduleid)) {
            return null; // shouldn't happen !!
        }

        // if possible, return the most recent visible version of this course_module
        $select = 'cm.*';
        $from   = '{course_modules} cm '.
                  'JOIN {course_sections} cs ON cm.section = cs.id '.
                  'JOIN {'.$modulename.'} x ON cm.module = ? AND cm.instance = x.id';
        $where  = 'cs.course = ? AND cs.section = ? AND x.name = ?';
        $params = array($moduleid, $course->id, $section->section, $instancename);
        $order  = 'cm.visible DESC, cm.added DESC'; // newest, visible cm first
        if ($cm = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params, 0, 1)) {
            return reset($cm);
        }

        // initialize $newrecord
        $newrecord = (object)array(
            'name'          => $instancename,
            // standard fields for adding a new cm
            'course'        => $course->id,
            'section'       => $section->section,
            'module'        => $moduleid,
            'modulename'    => $modulename,
            'add'           => $modulename,
            'update'        => 0,
            'return'        => 0,
            'cmidnumber'    => '',
            'groupmode'     => 0, // no groups
            'MAX_FILE_SIZE' => 10485760, // 10 GB
        );

        foreach ($defaultvalues as $column => $value) {
            $newrecord->$column = $value;
        }

        // add default values
        $columns = $DB->get_columns($modulename);
        foreach ($columns as $column) {
            if ($column->not_null) {
                $name = $column->name;
                if ($name=='id' || isset($newrecord->$name)) {
                    // do nothing
                } else if (isset($column->default_value)) {
                    $newrecord->$name = $column->default_value;
                } else {
                    // see: lib/dml/database_column_info.php
                    // R - counter (integer primary key)
                    // I - integers
                    // N - numbers (floats)
                    // T - timestamp - unsupported
                    // D - date - unsupported
                    // L - boolean (1 bit)
                    // C - characters and strings
                    // X - texts
                    // B - binary blobs
                    if (preg_match('/^[RINTDL]$/', $column->meta_type)) {
                        $newrecord->$name = 0;
                    } else {
                        $newrecord->$name = '';
                    }
                }
            }
        }

        // set the instanceid i.e. the "id" in the $modulename table
        if (! $newrecord->instance = $DB->insert_record($modulename, $newrecord)) {
            return false;
        }

        // set the coursemoduleid i.e. the "id" in the "course_modules" table
        if (! $newrecord->coursemodule = add_course_module($newrecord) ) {
            throw new exception('Could not add a new course module');
        }

        $newrecord->id = $newrecord->coursemodule;
        if (function_exists('course_add_cm_to_section')) {
            // Moodle >= 2.4
            $sectionid = course_add_cm_to_section($newrecord->course, $newrecord->id, $section->section);
        } else {
            // Moodle <= 2.3
            $sectionid = add_mod_to_section($newrecord);
        }
        if (! $sectionid) {
            throw new exception('Could not add the new course module to that section');
        }
        if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $newrecord->id))) {
            throw new exception('Could not update the course module with the correct section');
        }

        // if the section is hidden, we should also hide the new instance
        if (! isset($newrecord->visible)) {
            $newrecord->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
        }
        set_coursemodule_visible($newrecord->id, $newrecord->visible);

        // Trigger mod_updated event with information about this module.
        $event = (object)array(
            'courseid'   => $newrecord->course,
            'cmid'       => $newrecord->id,
            'modulename' => $newrecord->modulename,
            'name'       => $newrecord->name,
            'userid'     => $USER->id
        );
        if (function_exists('events_trigger_legacy')) {
            events_trigger_legacy('mod_updated', $event);
        } else {
            events_trigger('mod_updated', $event);
        }

        // rebuild_course_cache
        rebuild_course_cache($course->id);
        course_modinfo::clear_instance_cache($course);

        return $newrecord;
    }

    /**
     * set permissions on a newly created $cm
     *
     * @uses $DB
     * @todo Finish documenting this function
     */
    static public function set_cm_permissions($cm, $permissions) {
        global $DB;
        $context = block_maj_submissions::context(CONTEXT_MODULE, $cm->id);
        foreach ($permissions as $roleshortname => $capabilities) {
            if ($roleid = $DB->get_field('role', 'id', array('shortname' => $roleshortname))) {
                foreach ($capabilities as $capability => $permission) {
                    assign_capability($capability, $permission, $roleid, $context->id);
                }
            }
        }
    }

    /**
     * set access restrctions (=availability) on a newly created $cm
     *
     * @param array of stdClass $availability (decoded from JSON)
     * @todo Finish documenting this function
     */
    static public function set_cm_restrictions($cm, $restrictions) {
        global $DB;

        // e.g. restrict "Events database" to admins only
        // see "update_course_module_availability()"
        // in "blocks/taskchain_navigation/accesscontrol.php"

        if (class_exists('\core_availability\info_module')) {
            // Moodle >= 2.7

            if ($cm instanceof stdClass) {
                $cm = cm_info::create($cm);
            }

            // get current availability structure for this $cm
            if (empty($cm->availability)) {
                $structure = null;
            } else {
                $info = new \core_availability\info_module($cm);
                $tree = $info->get_availability_tree();
                $structure = $tree->save();
            }

            $structure = self::fix_cm_restrictions($cm, $structure, $restrictions);

            // encode availability $structure
            if (empty($structure->c)) {
                $availability = null;
            } else {
                $availability = json_encode($structure);
                //if (preg_match_all('/(?<="showc":\[).*?(?=\])/', $availability, $matches, PREG_OFFSET_CAPTURE)) {
                //    $replace = array('0' => 'false',
                //                     '1' => 'true');
                //    $i_max = (count($matches[0]) - 1);
                //    for ($i=$i_max; $i>=0; $i--) {
                //        list($match, $start) = $matches[0][$i];
                //        $availability = substr_replace($availability, strtr($match, $replace), $start, strlen($match));
                //    }
                //}
            }

            // update availability in database
            if ($cm->availability==$availability) {
                // do nothing
            } else {
                $DB->set_field('course_modules', 'availability', $availability, array('id' => $cm->id));
                rebuild_course_cache($cm->course);
            }
        }

    }

    /**
     * fix_cm_restrictions
     *
     * @param object $cm
     * @param object $structure
     * @param array of stdClass() $restrictions
     */
    static public function fix_cm_restrictions($cm, $structure, $restrictions) {
        global $DB;

        if (empty($structure)) {
            $structure = new stdClass();
        }
        if (! isset($structure->op)) {
            $structure->op = '|';
        }
        if (! isset($structure->c)) {
            $structure->c = array();
        }
        if (! isset($structure->showc)) {
            $structure->showc = array();
        }
        if (! isset($structure->show)) {
            $structure->show = true;
        }

        // remove conditions in $structure that refer to groups,
        // gropuings, activities or grade items in another course
        for ($i = (count($structure->c) - 1); $i >= 0; $i--) {
            $old = $structure->c[$i];
            if (isset($old->type)) {
                switch ($old->type) {
                    case 'completion':
                        $table = 'course_modules';
                        $params = array('id' => $old->cm, 'course' => $cm->course);
                        break;
                    case 'grade':
                        $table = 'grade_items';
                        $params = array('id' => $old->id, 'courseid' => $cm->course);
                        break;
                    case 'group':
                        $table = 'groups';
                        $params = array('id' => $old->id, 'courseid' => $cm->course);
                        break;
                    case 'grouping':
                        $table = 'groupings';
                        $params = array('id' => $old->id, 'courseid' => $cm->course);
                        break;
                    default:
                        $table = '';
                        $params = array();
                }
                if ($table=='' || $DB->record_exists($table, $params)) {
                    // do nothing
                } else {
                    array_splice($structure->c, $i, 1);
                }
            } else if (isset($old->op) && isset($old->c)) {
                // a subset of restrictions
            }
        }

        // add new $restrictions if they do not exists in $structure
        foreach ($restrictions as $i => $new) {
            $found = false;
            foreach ($structure->c as $old) {
                $params = false;
                if ($old->type==$new->type) {
                    switch ($old->type) {
                        case 'completion': $params = array('cm', 'e');          break;
                        case 'date':       $params = array('d',  't');          break;
                        case 'grade':      $params = array('id', 'min', 'max'); break;
                        case 'group':      $params = array('id');               break;
                        case 'grouping':   $params = array('id');               break;
                        case 'profile':    $params = array('sf', 'op', 'v');    break;
                    }
                }
                if ($params) {
                    $found = true;
                    foreach ($params as $param) {
                        if (isset($old->$param) && isset($new->$param) && $old->$param==$new->$param) {
                            // do nothing
                        } else {
                            $found = false;
                        }
                    }
                }
                if ($found) {
                    break;
                }
            }
            if ($found==false) {
                array_push($structure->c, $new);
            }
        }

        if (empty($structure->showc)) {
            unset($structure->showc);
        }

        return $structure;
    }

    /**
     * get_options_sectionnum
     *
     * @param object $mform
     * @param object $course
     * @param string $plugin
     * @return array($sectionnum => $sectionname) of sections in this course
     */
    static public function get_sectionnums($mform, $course, $plugin) {
        $options = array();
        $sections = get_fast_modinfo($course)->get_section_info_all();
        foreach ($sections as $sectionnum => $section) {
            if ($name = block_maj_submissions::get_sectionname($section)) {
                $options[$sectionnum] = $name;
            } else {
                $options[$sectionnum] = block_maj_submissions::get_sectionname_default($course, $sectionnum);
            }
        }
        return self::format_select_options($plugin, $options, 'section');
    }

    /**
     * get_options_cmids
     *
     * @param object  $mform
     * @param string  $course
     * @param string  $plugin
     * @param string  $modnames
     * @param string  $type (optional, default="")
     * @param integer $sectionnum (optional, default=0)
     * @param boolean $simplelist (optional, default=false)
     * @return array($cmid => $name) of activities from the specified $sectionnum
     *                               or from the whole course (if $sectionnum==0)
     */
    static public function get_cmids($mform, $course, $plugin, $modnames, $type='', $sectionnum=0, $simplelist=false) {
        global $DB;
        $options = array();

        $modnames = explode(',', $modnames);
        $modnames = array_filter($modnames);
        $modcount = count($modnames);

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        foreach ($sections as $section) {

            $sectionname = '';
            if ($sectionnum==0 || $sectionnum==$section->section) {
                $cmids = $section->sequence;
                $cmids = explode(',', $cmids);
                $cmids = array_filter($cmids);
                foreach ($cmids as $cmid) {
                    if (array_key_exists($cmid, $modinfo->cms)) {
                        $cm = $modinfo->get_cm($cmid);
                        if ($modcount==0 || in_array($cm->modname, $modnames)) {
                            if ($sectionname=='' && $simplelist==false) {
                                $sectionname = block_maj_submissions::get_sectionname($section, 0);
                                if ($sectionname=='') {
                                    $sectionname = block_maj_submissions::get_sectionname_default($course, $sectionnum);
                                }
                                $options[$sectionname] = array();
                            }
                            $name = $cm->name;
                            $name = block_maj_submissions::filter_text($name);
                            $name = block_maj_submissions::trim_text($name);
                            if ($cm->modname=='data') {
                                $params = array('dataid' => $cm->instance);
                                if ($count = $DB->get_field('data_records', 'COUNT(*)', $params)) {
                                    $a = (object)array('name' => $name, 'count' => $count);
                                    $name = get_string('databasenamecount', $plugin, $a);
                                }
                            }
                            if ($cm->modname=='workshop') {
                                $params = array('workshopid' => $cm->instance);
                                if ($count = $DB->get_field('workshop_submissions', 'COUNT(*)', $params)) {
                                    $a = (object)array('name' => $name, 'count' => $count);
                                    $name = get_string('workshopnamecount', $plugin, $a);
                                }
                            }
                            if ($simplelist) {
                                $options[$cmid] = $name;
                            } else {
                                $options[$sectionname][$cmid] = $name;
                            }
                        }
                    }
                }
            }
        }
        if ($simplelist) {
            return $options;
        }
        return self::format_selectgroups_options($plugin, $options, $type);
    }

    /**
     * format_selectgroups_options
     *
     * @param string   $plugin
     * @param array    $options
     * @param string   $type ("field", "activity" or "section")
     * @return array  $option for a select element in $mform
     */
    static public function format_selectgroups_options($plugin, $options, $type) {
        if (empty($options)) {
            return self::format_select_options($plugin, array(), $type);
        } else {
            return $options + array('-----' => self::format_select_options($plugin, array(), $type));
        }
    }

    /**
     * format_select_options
     *
     * @param string   $plugin
     * @param array    $options
     * @param string   $type (optional, default="") "field", "activity" or "section"
     * @return array  $option for a select element in $mform
     */
    static public function format_select_options($plugin, $options, $type='') {
        if (! array_key_exists(0, $options)) {
            $label = get_string('none');
            $options = array(0 => $label) + $options;
        }
        if ($type) {
            $label = get_string('createnew'.$type, $plugin);
            $options += array(self::CREATE_NEW => $label);
        }
        return $options;
    }

    /**
     * get_numsections
     *
     * a wrapper method to offer consistent API for $course->numsections
     * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
     * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
     *
     * @uses $DB
     * @param object $course
     * @return integer $numsections
     */
    static public function get_numsections($course) {
        global $DB;
        if (is_numeric($course)) {
            $course = $DB->get_record('course', array('id' => $course));
        }
        if ($course && isset($course->id)) {
            if (isset($course->numsections)) {
                // Moodle <= 2.3
                return $course->numsections;
            }
            if (isset($course->format)) {
                 // Moodle >= 2.4
                $params = array('courseid' => $course->id,
                                'format' => $course->format,
                                'name' => 'numsections');
                return $DB->get_field('course_format_options', 'value', $params);
            }
        }
        return 0; // shouldn't happen !!
    }

    /**
     * set_numsections
     *
     * a wrapper method to offer consistent API for $course->numsections
     * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
     * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
     *
     * @uses $DB
     * @param object $course
     * @param integer $numsections
     * @return void, but may update "course" or "course_format_options" table
     */
    static public function set_numsections($course, $numsections) {
        global $DB;
        if (is_numeric($course)) {
            $course = $DB->get_record('course', array('id' => $course));
        }
        if (empty($course) || empty($course->id)) {
            return false;
        }
        if (isset($course->numsections)) {
            // Moodle <= 2.3
            $params = array('id' => $course->id);
            return $DB->set_field('course', 'numsections', $numsections, $params);
        } else {
            // Moodle >= 2.4
            $params = array('courseid' => $course->id, 'format' => $course->format);
            return $DB->set_field('course_format_options', 'value', $numsections, $params);
        }
    }

    /**
     * get_database_records
     *
     * get records from database activites that link to
     * the specified submission in the specified workshop
     *
     * @uses $DB
     * @param object  $workshop
     * @param integer $sid (submission id)
     * @return mixed, either an array of database records that link to the specified workshop submission,
     *                or FALSE if no such records exists
     */
    static public function get_database_records($workshop, $sid, $dataid=0) {
        global $DB;
        $select = 'dc.id, dr.dataid, dr.userid, dc.recordid';
        $from   = '{data_content} dc '.
                  'JOIN {data_fields} df ON dc.fieldid = df.id '.
                  'JOIN {data_records} dr ON dc.recordid = dr.id '.
                  'JOIN {data} d ON dr.dataid = d.id';
        $where  = $DB->sql_like('dc.content', ':content').' '.
                  'AND df.name = :fieldname '.
                  'AND d.course = :courseid';
        $order  = 'dr.id DESC';
        $params = array('content' => '%'.$workshop->submission_url($sid)->out_as_local_url(false),
                        'fieldname' => 'peer_review_link',
                        'courseid' => $workshop->course->id);
        if ($dataid) {
            $where .= ' AND d.id = :dataid';
            $params['dataid'] = $dataid;
        }
        $records = "SELECT $select FROM $from WHERE $where ORDER BY $order";
        return $DB->get_records_sql($records, $params);
    }
}