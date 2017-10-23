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
require_once($CFG->dirroot.'/admin/tool/createusers/classes/form.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/mod/data/lib.php');

/**
 * block_maj_submissions_tool_base
 *
 * @copyright 2014 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
abstract class block_maj_submissions_tool_base extends moodleform {

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
            if ($count = $DB->get_field_sql($sql, array('groupid' => $id))) {
                $a = (object)array('name' => $name, 'count' => $count);
                $groups[$id] = get_string('groupnamecount', $this->plugin, $a);
            }
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

class block_maj_submissions_tool_setupdatabase extends block_maj_submissions_tool_base {

    protected $type = '';
    protected $defaultpreset = '';
    protected $modulename = 'data';
    protected $defaultvalues = array(
        'visible'         => 1,  // course_modules.visible
        'intro'           => '', // see set_defaultintro()
        'introformat'     => FORMAT_HTML, // =1
        'comments'        => 0,
        'timeavailablefrom' => 0,
        'timeavailableto' => 0,
        'requiredentries' => 10,
        'requiredentriestoview' => 10,
        'maxentries'      => 1,
        'approval'        => 1,
        'manageapproved'  => 0,
        'assessed'        => 0
    );
    protected $timefields = array(
        'timestart' => array('timeavailablefrom'),
        'timefinish' => array('timeavailableto')
    );
    protected $permissions = array(
        // "user" is the "Authenticated user" role. A "user" is
        // logged in, but may not be enrolled in the current course.
        // They can view, but not write to, this database activity
        'user' => array('mod/data:viewentry' => CAP_ALLOW)
    );

    /**
     * definition
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }

        // --------------------------------------------------------
        $name = 'databaseactivity';
        $label = get_string($name, $this->plugin);
        $mform->addElement('header', $name, $label);
        // --------------------------------------------------------

        $name = 'databaseactivity';
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        // --------------------------------------------------------
        $name = 'preset';
        $label = get_string($name, $this->plugin);
        $mform->addElement('header', $name, $label);
        $mform->addHelpButton($name, $name, $this->plugin);
        // --------------------------------------------------------

        $presets = self::get_available_presets($context, $this->plugin, $this->cmid, 'imagegallery');
        if (count($presets)) {

            $name = 'presetfolder';
            $elements = array();

            foreach ($presets as $preset) {
                $label = " $preset->description";
                $value = "$preset->userid/$preset->shortname";
                $elements[] = $mform->createElement('radio', $name, null, $label, $value);
            }

            if (count($elements)) {
                $label = get_string('uploadpreset', $this->plugin);
                $elements[] = $mform->createElement('radio', $name, null, $label, '0/uploadpreset');
                $group_name = 'group_'.$name;
                $label = get_string($name, $this->plugin);
                $mform->addGroup($elements, $group_name, $label, html_writer::empty_tag('br'), false);
                $mform->addHelpButton($group_name, $name, $this->plugin);
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, "0/$this->defaultpreset");
            }
        }

        $name = 'presetfile';
        $label = get_string($name, $this->plugin);
        $mform->addElement('filepicker', $name, $label);
        $mform->addHelpButton($name, $name, $this->plugin);
        $mform->disabledIf($name, 'presetfolder', 'neq', '0/uploadpreset');

        $this->add_action_buttons();
    }

    /**
     * Perform extra validation on form values.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = array();

        $require_activity = true;
        $require_section = false;
        $require_preset = true;

        if ($require_activity) {
            $name = 'databaseactivity';
            $group = 'group_'.$name;
            $num = $name.'num';
            $name = $name.'name';
            if (empty($data[$num])) {
                $errors[$group] = get_string("missing$num", $this->plugin);
            } else if ($data[$num]==self::CREATE_NEW) {
                if (empty($data[$name])) {
                    $errors[$group] = get_string("missing$name", $this->plugin);
                }
                $require_section = true;
            }
        }

        if ($require_section) {
            $name = 'coursesection';
            $group = 'group_'.$name;
            $num = $name.'num';
            $name = $name.'name';
            if (empty($data[$num])) {
                $errors[$group] = get_string("missing$num", $this->plugin);
            } else if ($data[$num]==self::CREATE_NEW) {
                if (empty($data[$name])) {
                    $errors[$group] = get_string("missing$name", $this->plugin);
                }
            }
        }

        if ($require_preset) {
            $name = 'presetfolder';
            if (isset($data[$name]) && $data[$name]) {
                $require_preset = false;
            }
            $name = 'presetfile';
            if (isset($data[$name]) && $data[$name]) {
                $require_preset = false;
            }
        }

        if ($require_preset) {
            $errors['group_presetfolder'] = get_string("missingpreset", $this->plugin);
            $errors['presetfile'] = get_string("missingpreset", $this->plugin);
        }

        return $errors;
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
        global $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->dirroot.'/lib/xmlize.php');

        $cm = false;
        $msg = array();
        $time = time();

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'databaseactivity');
        }

        if ($cm) {

            if (empty($data->presetfile)) {
                $presetfile = '';
            } else {
                $presetfile = $this->get_new_filename('presetfile');
            }

            if (empty($data->presetfolder)) {
                $presetfolder = '';
            } else {
                $presetfolder = $data->presetfolder;
            }

            // create
            $data = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
            $data->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
            $data->instance   = $cm->instance;

            $importer = null;

            if ($presetfile) {
                $file = $this->save_temp_file('presetfile');
                $importer = new data_preset_upload_importer($this->course, $cm, $data, $file);
                $presetfolder = ''; // ignore any folder that was specified
            }

            if ($presetfolder) {

                // transfer preset to Moodle file storage
                $fs = get_file_storage();
                list($userid, $filepath) = explode('/', $presetfolder, 2);
                $dirpath = "$CFG->dirroot/blocks/maj_submissions/presets/$filepath";
                if (is_dir($dirpath) && is_directory_a_preset($dirpath)) {

                    $contextid = DATA_PRESET_CONTEXT;   // SYSCONTEXTID
                    $component = DATA_PRESET_COMPONENT; // "mod_data"
                    $filearea  = DATA_PRESET_FILEAREA;  // "site_presets"
                    $itemid    = 0;
                    $sortorder = 0;

                    $filenames = scandir($dirpath);
                    foreach ($filenames as $filename) {
                        if (substr($filename, 0, 1)=='.') {
                            continue; // skip hidden items
                        }
                        if (is_dir("$dirpath/$filename")) {
                            continue; // skip sub directories
                        }
                        if ($fs->file_exists($contextid, $component, $filearea, $itemid,  "/$filepath/", $filename)) {
                            continue; // file already exists - unusual !!
                        }
                        if ($sortorder==0) {
                            $fs->create_directory($contextid, $component, $filearea, $itemid, "/$filepath/");
                            $sortorder++;
                        }
                        $filerecord = array(
                            'contextid' => $contextid,
                            'component' => $component,
                            'filearea'  => $filearea,
                            'sortorder' => $sortorder++,
                            'itemid'    => $itemid,
                            'filepath'  => "/$filepath/",
                            'filename'  => $filename
                        );
                        $file = $fs->create_file_from_pathname($filerecord, "$dirpath/$filename");
                    }
                }

                // now, try the import using the standard forms for the database module
                $importer = new data_preset_existing_importer($this->course, $cm, $data, $presetfolder);
            }

            if ($importer) {
                $renderer = $PAGE->get_renderer('mod_data');
                $importform = $renderer->import_setting_mappings($data, $importer);

                // adjust the URL in the import form
                $search = '/(<form method="post" action=")(">)/';
                $replace = new moodle_url('/mod/data/preset.php', array('id' => $cm->id));
                $importform = preg_replace($search, '$1'.$replace.'$2', $importform);

                // on a new database, remove warning about overwriting fields
                if (empty($importer->get_preset_settings()->currentfields)) {
                    $name = 'overwritesettings';
                    $params = array('type' => 'hidden', 'name' => $name, 'value' => 0);
                    $search = '/(\s*<p>.*?<\/p>)?\s*<div class="'.$name.'">.*?<\/div>/s';
                    $replace = html_writer::empty_tag('input', $params);
                    $importform = preg_replace($search, $replace, $importform);
                }

                // send the import form to the browser
                echo $OUTPUT->header();
                echo $OUTPUT->heading(format_string($data->name), 2);
                echo $importform;
                echo $OUTPUT->footer();
                exit(0);
            }

            // Otherwise, something was amiss so redirect to the standard page
            // for importing a preset into the database acitivty.
            $url = new moodle_url('/mod/data/preset.php', array('id' => $cm->id));
            redirect($url);
        }

        return false; // shouldn't happen !!
    }

    /**
     * get_defaultintro
     *
     * @todo Finish documenting this function
     */
    protected function get_defaultintro() {
        $intro = '';

        // useful urls
        $urls = array(
            'signup' => new moodle_url('/login/signup.php'),
            'login'  => new moodle_url('/login/index.php'),
            'enrol'  => new moodle_url('/enrol/index.php', array('id' => $this->course->id))
        );

        // setup the multiparams for the multilang strings
        $names = array('record' => $this->defaultpreset.'record',
                       'process' => $this->defaultpreset.'process');
        $multilangparams = $this->instance->get_multilang_params($names, $this->plugin);

        // add intro sections
        $howtos = array('switchrole', 'begin', 'login', 'enrol', 'signup', 'add', 'edit' ,'delete');
        foreach ($howtos as $howto) {
            $params = array('class' => "howto $howto");
            $intro .= html_writer::start_tag('div', $params);
            $text = $this->instance->get_string("howto$howto", $this->plugin, $multilangparams);
            switch ($howto) {
                case 'login':
                case 'enrol':
                    $text = str_replace('{$url}', $urls[$howto], $text);
                    break;
                case 'signup':
                    $text .= html_writer::start_tag('ol');
                    foreach ($urls as $name => $url) {
                        $link = $this->instance->get_string("link$name", $this->plugin);
                        if ($name=='signup' || $name=='login') {
                            $params = array('target' => '_blank');
                        } else {
                            $params = array(); // $name=='enrol'
                        }
                        $link = html_writer::link($url, $link, $params);
                        $text .= html_writer::tag('li', $link);
                    }
                    $text .= html_writer::end_tag('ol');
                    break;
            }
            if ($text) {
                $intro .= html_writer::tag('p', $text);
            }
            $intro .= html_writer::end_tag('div');
        }

        if ($intro) {
            $intro = '<script type="text/javascript">'."\n".
                     '//<![CDATA['."\n".
                     '(function(){'."\n".
                     '    var css = ".path-mod-data .howto { display: none; }";'."\n".
                     '    var style = document.createElement("style");'."\n".
                     '    style.setAttribute("type","text/css");'."\n".
                     '    style.appendChild(document.createTextNode(css));'."\n".
                     '    var head = document.getElementsByTagName("head");'."\n".
                     '    head[0].appendChild(style);'."\n".
                     "})();\n".
                     "//]]>\n".
                     "</script>\n".
                     $intro;
        }

        return $intro;
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
        global $DB, $PAGE, $OUTPUT;

        if (! $action = optional_param('action', '', PARAM_ALPHANUM)) {
            return ''; // no action specified
        }

        if (! optional_param('sesskey', false, PARAM_BOOL)) {
            return ''; // no sesskey - unusual !!
        }

        if (! confirm_sesskey()) {
            return ''; // invalid sesskey - unusual !!
        }

        $fullname = optional_param('fullname', '', PARAM_PATH);
        list($userid, $shortname) = explode('/', $fullname, 2);

        $userid    = clean_param($userid, PARAM_INT);
        $shortname = clean_param($shortname, PARAM_TEXT);
        $fullname  = "$userid/$shortname";

        $message = '';

        if ($action=='confirmdelete') {
            $yes = array('fullname' => $fullname,
                         'action' => 'delete',
                         'id' => $PAGE->url->param('id'));
            $yes = new moodle_url($PAGE->url->out_omit_querystring(), $yes);
            $no = array('id' => $PAGE->url->param('id'));
            $no = new moodle_url($PAGE->url->out_omit_querystring(), $no);
            $message = get_string('deletewarning', 'data').
                       html_writer::empty_tag('br').$shortname;
            if ($userid) {
                $message .= ' ('.fullname($DB->get_record('user', array('id' => $userid))).')';
            }
            $message = $OUTPUT->confirm($message, $yes, $no);
        }

        if ($action=='delete') {
            if ($this->cmid) {
                $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            } else {
                $context = $this->course->context;
            }
            $can_delete = false;
            $presets = self::get_available_presets($context, $this->plugin, $this->cmid);
            foreach ($presets as $preset) {
                if ($can_delete==false && $preset->shortname==$shortname) {
                    $can_delete = data_user_can_delete_preset($context, $preset);
                }
            }
            if ($can_delete) {
                data_delete_site_preset($shortname);
                $url = clone($PAGE->url);
                $url->remove_all_params();
                $url->params(array('id' => $PAGE->url->param('id')));
                $message = $shortname.' '.get_string('deleted', 'data');
                $message = $OUTPUT->notification($message, 'notifysuccess').
                           $OUTPUT->continue_button($url);
            }
        }

        return $message;
    }

    /**
     * get_available_presets
     *
     * @uses $DB
     * @uses $OUTPUT
     * @param object $context
     * @return integer $cmid
     */
    static public function get_available_presets($context, $plugin, $cmid, $exclude='') {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $presets = array();

        $strman = get_string_manager();
        $strdelete = get_string('deleted', 'data');

        $dirpath = $CFG->dirroot.'/blocks/maj_submissions/presets';
        if (is_dir($dirpath)) {

            $shortnames = scandir($dirpath);
            foreach ($shortnames as $shortname) {
                if (substr($shortname, 0, 1)=='.') {
                    continue; // skip hidden shortnames
                }
                $path = "$dirpath/$shortname";
                if (! is_dir($path)) {
                    continue; // skip files and links
                }
                if (! is_directory_a_preset($path)) {
                    continue; // not a preset - unusual !!
                }
                if ($strman->string_exists('presetname'.$shortname, $plugin)) {
                    $name = get_string('presetname'.$shortname, $plugin);
                } else {
                    $name = $shortname;
                }
                if (file_exists("$path/screenshot.jpg")) {
                    $screenshot = "$path/screenshot.jpg";
                } else if (file_exists("$path/screenshot.png")) {
                    $screenshot = "$path/screenshot.png";
                } else if (file_exists("$path/screenshot.gif")) {
                    $screenshot = "$path/screenshot.gif";
                } else {
                    $screenshot = ''; // shouldn't happen !!
                }
                $presets[] = (object)array(
                    'userid' => 0,
                    'path' => $path,
                    'name' => $name,
                    'shortname' => $shortname,
                    'screenshot' => $screenshot
                );
            }
        }

        // append mod_data presets, user presets and site wide presets
        $presets = array_merge($presets, data_get_available_presets($context));

        if (empty($exclude)) {
            $exclude = array();
        } else if (is_scalar($exclude)) {
            $exclude = array($exclude);
        }

        if (method_exists($OUTPUT, 'image_url')) {
            $image_url = 'image_url'; // Moodle >= 3.3
        } else {
            $image_url = 'pix_url'; // Moodle <= 3.2
        }

        foreach ($presets as $i => $preset) {

            if (in_array($preset->shortname, $exclude)) {
                unset($presets[$i]);
                continue;
            }

            // ensure each preset is only added once
            $exclude[] = $preset->shortname;

            if (empty($preset->userid)) {
                $preset->userid = 0;
                $preset->description = $preset->name;
            } else {
                $fields = get_all_user_name_fields(true);
                $params = array('id' => $preset->userid);
                $user = $DB->get_record('user', $params, "id, $fields", MUST_EXIST);
                $preset->description = $preset->name.' ('.fullname($user, true).')';
            }

            if (strpos($preset->path, $dirpath)===0) {
                $can_delete = false; // a block preset
            } else {
                $can_delete = data_user_can_delete_preset($context, $preset);
            }

            if ($can_delete) {
                $url = clone($PAGE->url);
                $url->remove_all_params();

                $params = array('id'       => $PAGE->url->param('id'),
                                'action'   => 'confirmdelete',
                                'fullname' => "$preset->userid/$preset->shortname",
                                'sesskey'  => sesskey());
                $url->params($params);

                $icon = $OUTPUT->$image_url('t/delete');
                $params = array('src'   => $icon,
                                'class' => 'iconsmall',
                                'alt'   => "$strdelete $preset->description");
                $icon = html_writer::empty_tag('img', $params);

                $preset->description .= html_writer::link($url, $icon);
            }

            $presets[$i] = $preset;
        }

        return $presets;
    }
}

class block_maj_submissions_tool_setupregistrations extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'registerdelegates';
    protected $defaultpreset = 'registrations';
    protected $defaultname = 'registerdelegatesname';
}
class block_maj_submissions_tool_setuppresentations extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'collectpresentations';
    protected $defaultpreset = 'presentations';
    protected $defaultname = 'collectpresentationsname';
}
class block_maj_submissions_tool_setupworkshops extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'collectworkshops';
    protected $defaultpreset = 'workshops';
    protected $defaultname = 'collectworkshopsname';
}
class block_maj_submissions_tool_setupevents extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'registerevents';
    protected $defaultpreset = 'events';
    protected $defaultname = 'conferenceevents';
    protected $permissions = array();

    /**
     * get_defaultintro
     *
     * @return string
     */
    protected function get_defaultintro() {
        $howto = 'setupevents';
        if ($intro = $this->instance->get_string("howto$howto", $this->plugin)) {
            $intro = html_writer::tag('p', $intro);
            $intro = html_writer::tag('div', $intro, array('class' => "howto $howto"));
        }
        return $intro;
    }
}

/**
 * block_maj_submissions_tool_createusers
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_createusers extends tool_createusers_form {

    // properties to hold customdata
    protected $plugin = '';
    protected $course = null;
    protected $instance = null;

    // should we allow student/teacher enolments
    protected $allow_student_enrolments = true;
    protected $allow_teacher_enrolments = false;

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {

        // extract the custom data passed from the main script
        $this->plugin  = $customdata['plugin'];
        $this->course  = $customdata['course'];
        $this->instance = $customdata['instance'];

        // restrict the list of student-enrollable courses to the current course
        $this->forcecourseid = $this->course->id;

        // convert block instance to "block_maj_submissions" object
        $this->instance = block_instance($this->instance->blockname, $this->instance);

        // call parent constructor, as normal
        if (method_exists('moodleform', '__construct')) {
            parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
        } else {
            parent::moodleform($action, $customdata, $method, $target, $attributes, $editable);
        }
    }
}

/**
 * block_maj_submissions_tool_data2workshop
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_data2workshop extends block_maj_submissions_tool_base {

    protected $type = '';
    protected $modulename = 'workshop';
    protected $defaultname = 'reviewsubmissions';

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

        if ($data->templateactivity) {
            $cm = $DB->get_record('course_modules', array('id' => $data->templateactivity));
            $instance = $DB->get_record($this->modulename, array('id' => $cm->instance));
            $course = $DB->get_record('course', array('id' => $instance->course));
            $template = new workshop($instance, $cm, $course);
        }

        if ($template) {
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
                    if ($template) {
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
                                if ($name=='title') {
                                    // skip this field
                                } else if ($name=='abstract') {
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

/**
 * block_maj_submissions_tool_workshop2data
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_workshop2data extends block_maj_submissions_tool_base {

    const NOT_GRADED = -1;

    protected $type = '';
    protected $modulename = 'data';
    protected $defaultvalues = array(
        'visible'         => 1,  // course_modules.visible
        'intro'           => '', // see set_defaultintro()
        'introformat'     => FORMAT_HTML, // =1
        'comments'        => 0,
        'timeavailablefrom' => 0,
        'timeavailableto' => 0,
        'requiredentries' => 10,
        'requiredentriestoview' => 10,
        'maxentries'      => 1,
        'approval'        => 1,
        'manageapproved'  => 0,
        'assessed'        => 0
    );
    protected $timefields = array(
        'timestart' => array('timeavailablefrom'),
        'timefinish' => array('timeavailableto')
    );

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

        $name = 'sourceworkshop';
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'workshop');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);

        $name = 'statusfilter';
        $label = get_string($name, $this->plugin);

        // create the $elements for a single filter condition
        $elements = array();
        $elements[] = $mform->createElement('static', '', '', get_string($name.'1', $this->plugin));
        $elements[] = $mform->createElement('select', $name.'grade', null, $this->get_statuslimit_options());
        $elements[] = $mform->createElement('static', '', '', get_string($name.'2', $this->plugin));
        $elements[] = $mform->createElement('select', $name.'status', null, $this->get_statuslevel_options());
        $elements[] = $mform->createElement('static', '', '', get_string($name.'3', $this->plugin));

        // prepare the parameters to pass to the "repeat_elements()" method
        $elements = array($mform->createElement('group', $name, $label, $elements, ' ', false));
        $repeats = optional_param('count'.$name, 0, PARAM_INT);
        $options = array($name.'level' => array('type' => PARAM_TEXT),
                         $name.'limit' => array('type' => PARAM_INT),
                         $name => array('helpbutton' => array($name, $this->plugin)));
        $addstring = get_string('add'.$name, $this->plugin, 1);
        $this->repeat_elements($elements, $repeats, $options, 'count'.$name, 'add'.$name, 1, $addstring, true);

        $mform->disabledIf('add'.$name, 'targetdatabase', 'eq', 0);

        $name = 'targetdatabase';
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $this->add_action_buttons();
    }

    /**
     * get_statuslevel_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_statuslevel_options() {
        global $DB;
        $options = array();
        if ($cmid = optional_param('targetdatabasenum', null, PARAM_INT)) {
            $dataid = get_fast_modinfo($this->course)->get_cm($cmid)->instance;
            $params = array('dataid' => $dataid, 'name' => 'submission_status');
            if ($record = $DB->get_record('data_fields', $params)) {
                $search = self::bilingual_string();
                if (self::is_low_ascii_language()) {
                    $replace = '$2'; // low-ascii language e.g. English
                } else {
                    $replace = '$1'; // high-ascii/multibyte language
                }
                $options = preg_split('/[\r\n]+/', $record->param1);
                $options = array_filter($options);
                $options = array_flip($options);
                foreach (array_keys($options) as $option) {
                    $options[$option] = preg_replace($search, $replace, $option);
                }
            }
        }
        return $options;
    }

    /**
     * get_statuslimit_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_statuslimit_options() {
        $options = array(
            self::NOT_GRADED => get_string('notgraded', 'question')
        );
        foreach (range(0, 100) as $i) {
            $options[$i] = ">= $i%";
        }
        return $options;
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
        $msg = array();
        $time = time();

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'targetdatabase');
        }

        if ($cm) {

            // get database
            $database = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
            $database->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
            $database->instance   = $cm->instance;

            // get workshop
            $cm = $DB->get_record('course_modules', array('id' => $data->sourceworkshop));
            $instance = $DB->get_record('workshop', array('id' => $cm->instance));
            $course = $DB->get_record('course', array('id' => $instance->course));
            $workshop = new workshop($instance, $cm, $course);

            // get ids of peer_review_fields
            $reviewfields = array(
                'submission_status'   => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'submission_status')),
                'peer_review_score'   => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'peer_review_score')),
                'peer_review_details' => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'peer_review_details')),
                'peer_review_notes'   => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'peer_review_notes')),
            );

            // get formatted deadline for revisions
            if (! $dateformat = $this->instance->config->customdatefmt) {
                if (! $dateformat = $this->instance->config->moodledatefmt) {
                    $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
                }
                $dateformat = get_string($dateformat);
            }
            $revisetimefinish = $this->instance->config->revisetimefinish;
            $revisetimefinish = userdate($revisetimefinish, $dateformat);

            $registrationlink = '';
            if (! empty($this->instance->config->registerdelegatescmid)) {
                $registrationlink = 'registerdelegatescmid';
            }
            if (! empty($this->instance->config->registerpresenterscmid)) {
                $registrationlink = 'registerpresenterscmid';
            }
            if ($registrationlink) {
                $params = array('id' => $this->instance->config->$registrationlink);
                $registrationlink = html_writer::link(new moodle_url('/mod/data/view.php', $params),
                                                      get_string($registrationlink, $this->plugin),
                                                      array('target' => '_blank'));
            }

            if (empty($this->instance->config->conferencetimestart)) {
                $conferencemonth = '';
            } else {
                $conferencemonth = $this->instance->config->conferencetimestart;
                $conferencemonth = userdate($conferencemonth, '%B');
            }

            // get workshop submissions
            $submissions = $DB->get_records('workshop_submissions', array('workshopid' => $workshop->id));
            if ($submissions===false) {
                $submissions = array();
            }

            // setup $statusfilters which maps a submission grade to a data record status
            $statusfilters = array(self::NOT_GRADED => null); // may get overwritten
            if (isset($data->statusfiltergrade) && isset($data->statusfilterstatus)) {
                foreach ($data->statusfiltergrade as $i => $grade) {
                    if (array_key_exists($i ,$data->statusfilterstatus)) {
                        $statusfilters[intval($grade)] = $data->statusfilterstatus[$i];
                    }
                }
                // sort from highest grade to lowest grade
                krsort($statusfilters);
            }

            // ids of data records that get updated
            $maxgrade = 0;
            $countselected = 0;
            $counttransferred = 0;
            $datarecordids = array();

            // get info about criteria (=dimensions) and levels
            $params = array('workshopid' => $workshop->id);
            if ($criteria = $DB->get_records('workshopform_rubric', $params, 'sort')) {
                foreach (array_keys($criteria) as $id) {
                    $criteria[$id]->levels = array();
                }
                list($select, $params) = $DB->get_in_or_equal(array_keys($criteria));
                if ($levels = $DB->get_records_select('workshopform_rubric_levels', "dimensionid $select", $params, 'grade')) {
                    while ($level = array_pop($levels)) {
                        $id = $level->dimensionid;
                        $grade = $level->grade;
                        $level = format_string($level->definition, $level->definitionformat);
                        $criteria[$id]->levels[$grade] = $level;
                    }
                }
                unset($levels);
                foreach (array_keys($criteria) as $id) {
                    asort($criteria[$id]->levels);
                    $grades = array_keys($criteria[$id]->levels);
                    $criteria[$id]->maxgrade = intval(max($grades));
                    $maxgrade += $criteria[$id]->maxgrade;

                    // format the rubric criteria description, assuming the following structure:
                    // <p><b>title</b><br />explanation ...</p>
                    $text = format_text($criteria[$id]->description, $criteria[$id]->descriptionformat);
                    $text = preg_replace('/^\\s*<(h1|h2|h3|h4|h5|h6|p)\\b[^>]*>(.*?)<\\/\\1>.*$/u', '$2', $text);
                    $text = preg_replace('/^(.*?)<br\\b[^>]*>.*$/u', '$1', $text);
                    $text = preg_replace('/<[^>]*>/u', '', $text); // strip tags
                    $text = preg_replace('/[[:punct:]]+$/u', '', $text);
                    $criteria[$id]->description = $text;
                }
            } else {
                $criteria = array();
            }

            foreach ($submissions as $sid => $submission) {

                // get database records that link to this submission
                if ($records = self::get_database_records($workshop, $sid, $database->id)) {

                    // we only expect one record
                    $record = reset($records);

                    // initialie the status - it should be reset from $statusfilters
                    $status = '';

                    // format and transfer each of the peer review fields
                    foreach ($reviewfields as $name => $fieldid) {
                        if (empty($fieldid)) {
                            continue; // shouldn't happen !!
                        }
                        $content = '';
                        switch ($name) {
                            case 'submission_status';
                                if (is_numeric($submission->grade)) {
                                    $content = round($submission->grade, 0);
                                    foreach ($statusfilters as $grade => $status) {
                                        if ($content >= $grade) {
                                            $content = $status;
                                            break;
                                        }
                                    }
                                } else {
                                    $content = $statusfilters[self::NOT_GRADED];
                                }
                                break;

                            case 'peer_review_score';
                                if (is_numeric($submission->grade)) {
                                    $content = round($submission->grade, 0);
                                }
                                break;

                            case 'peer_review_details';
                                $assessments = $DB->get_records('workshop_assessments', array('submissionid' => $sid));
                                if ($assessments===false) {
                                    $assessments = array();
                                }
                                $i = 1; // peer review number
                                foreach ($assessments as $aid => $assessment) {
                                    if ($grades = $DB->get_records('workshop_grades', array('assessmentid' => $aid))) {

                                        $content .= html_writer::tag('h4', get_string('peerreviewnumber', $this->plugin, $i++))."\n";

                                        $content .= html_writer::start_tag('table');
                                        $content .= html_writer::start_tag('tbody')."\n";

                                        $content .= html_writer::start_tag('tr');
                                        $content .= html_writer::tag('th', get_string('criteria', 'workshopform_rubric'));
                                        $content .= html_writer::tag('th', get_string('assessment', 'workshop'), array('style' => 'text-align:center;'));
                                        $content .= html_writer::end_tag('tr')."\n";

                                        // CSS class for criteria grades
                                        $params = array('class' => 'criteriagrade');

                                        foreach ($grades as $grade) {
                                            $id = $grade->dimensionid;
                                            $content .= html_writer::start_tag('tr');
                                            $content .= html_writer::tag('td', $criteria[$id]->description);
                                            $content .= html_writer::tag('td', intval($grade->grade).' / '.$criteria[$id]->maxgrade, $params);
                                            $content .= html_writer::end_tag('tr')."\n";
                                        }

                                        // CSS class for submission grade
                                        $params = array('class' => 'submissiongrade');

                                        $content .= html_writer::start_tag('tr');
                                        $content .= html_writer::tag('td', ' ');
                                        $content .= html_writer::tag('td', intval($submission->grade).' / '.$maxgrade, $params);
                                        $content .= html_writer::end_tag('tr')."\n";

                                        $content .= html_writer::end_tag('tbody');
                                        $content .= html_writer::end_tag('table')."\n";
                                    }

                                    // CSS class for feedback
                                    $params = array('class' => 'feedback');
                                    if ($feedback = self::plain_text($assessment->feedbackauthor)) {
                                        $feedback = html_writer::tag('b', get_string('feedback')).' '.$feedback;
                                        $feedback = html_writer::tag('p', $feedback, $params);
                                        $content .= $feedback;
                                    }
                                }
                                break;

                            case 'peer_review_notes';
                                $params = array('class' => 'thanks');
                                $content .= html_writer::tag('p', get_string('peerreviewgreeting', $this->plugin), $params)."\n";

                                switch (true) {
                                    case strpos($status, 'Conditionally accepted'):
                                        $content .= html_writer::tag('p', get_string('conditionallyaccepted', $this->plugin), array('class' => 'status'))."\n";
                                        $advice = array(
                                            get_string('pleasemakechanges', $this->plugin, $revisetimefinish),
                                            get_string('youwillbenotified', $this->plugin)
                                        );
                                        $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                                        break;

                                    case strpos($status, 'Not accepted'):
                                        $content .= html_writer::tag('p', get_string('notaccepted', $this->plugin), array('class' => 'status'))."\n";
                                        break;

                                    case strpos($status, 'Accepted'):
                                        $content .= html_writer::tag('p', get_string('accepted', $this->plugin), array('class' => 'status'))."\n";
                                        if ($registrationlink) {
                                            $advice = array(
                                                get_string('pleaseregisteryourself', $this->plugin, $registrationlink),
                                                get_string('pleaseregistercopresenters', $this->plugin)
                                            );
                                            $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                                        }
                                        $content .= html_writer::tag('p', get_string('acceptedfarewell', $this->plugin, $conferencemonth), array('class' => 'farewell'));
                                        break;

                                    case strpos($status, 'Waiting for review'):
                                        $content .= html_writer::tag('p', get_string('waitingforreview', $this->plugin), array('class' => 'status'))."\n";
                                        break;
                                }
                        }

                        $params = array('fieldid'  => $fieldid,
                                        'recordid' => $record->recordid);
                        if ($DB->record_exists('data_content', $params)) {
                            $DB->set_field('data_content', 'content', $content, $params);
                        } else {
                            $content = (object)array(
                                'fieldid'  => $fieldid,
                                'recordid' => $record->recordid,
                                'content'  => $content,
                                'content1' => ($name=='peer_review_score' ? null : FORMAT_HTML)
                            );
                            $content->id = $DB->insert_record('data_content', $content);
                        }

                        $counttransferred++;
                        $datarecordids[] = $record->recordid;
                    }
                }
                unset($records, $record);
            }
        }

        return $this->form_postprocessing_msg($msg);
    }
}

/**
 * block_maj_submissions_tool_setupvetting
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_setupvetting extends block_maj_submissions_tool_base {

    //protected $availability = (object)array(
    //    'op'    => '&',
    //    'c'     => array((object)array('type' => 'group', 'id' => 0)),
    //    'showc' => array(true),
    //    'show'  => false
    //);

    /**
     * The name of the form field containing
     * the id of a group of anonymous assessors
     */
    protected $groupfieldname = 'anonymousreviewers';

    /**
     * definition
     */
    public function definition() {
        $mform = $this->_form;

        $name = 'targetworkshop';
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'workshop');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);

        $name = 'reviewers';
        $options = $this->get_group_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'anonymousreviewers';
        $options = $this->get_group_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'reviewspersubmission';
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, range(0, 10));
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'resetassessments';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

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
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/workshop/locallib.php');

        $cm = false;
        $msg = array();

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            if ($cm = $data->targetworkshop) {
                $cm = get_fast_modinfo($this->course)->get_cm($cm);
            }
            if ($reviewers = $data->reviewers) {
                $params = array('groupid' => $reviewers);
                $reviewers = $DB->get_records_menu('groups_members', $params, null, 'id,userid');
            }
            if ($anonymous = $data->anonymousreviewers) {
                $params = array('groupid' => $anonymous);
                $anonymous = $DB->get_records_menu('groups_members', $params, null, 'id,userid');
            }
            $reviewspersubmission = $data->reviewspersubmission;
        } else {
            $reviewers = false;
            $anonymous = false;
            $reviewspersubmission = 0;
        }
        if ($reviewers===false) {
            $reviewers = array();
        }
        if ($anonymous===false) {
            $anonymous = array();
        }

        $countreviewers = count($reviewers);
        $countanonymous = count($anonymous);
        if ($cm && $countreviewers && $countanonymous) {

            if ($countreviewers > $countanonymous) {
                $a = (object)array(
                    'countanonymous' => $countanonymous,
                    'countreviewers' => $countreviewers
                );
                $msg[] = get_string('toofewreviewers', $this->plugin);
            } else {
                // get workshop object
                $workshop = $DB->get_record('workshop', array('id' => $cm->instance));
                $workshop = new workshop($workshop, $cm, $this->course);

                // select only the required number of anonymous reviewers
                $anonymous = array_slice($anonymous, 0, $countreviewers);
                $countanonymous = $countreviewers;

                if ($anonymous===false) {
                    $anonymous = array(); // shouldn't happen !!
                }

                // shuffle reviewerids so that they are mapped randomly to anonymous users
                shuffle($reviewers);

                // map $anonymous reviewers onto real $reviewers
                $reviewers = array_combine($anonymous, $reviewers);

                // map anonymous reviewers to simple object
                // (realuserid, review and reviewcount)
                foreach ($reviewers as $userid => $realuserid) {
                    $reviewers[$userid] = (object)array(
                        'realuserid'   => $realuserid,
                        'reviews'      => array(),
                        'countreviews' => 0,
                    );
                }

                $submissions = $DB->get_records('workshop_submissions', array('workshopid' => $workshop->id), null, 'id,authorid');
                if ($submissions===false) {
                    $submissions = array();
                }

                $reviewfields = array();

                $reset = (object)array(
                    'submissionids' => array(),
                    'datarecordids' => array()
                );

                foreach ($submissions as $sid => $submission) {
                    $submissions[$sid]->countreviews = 0;
                    $submissions[$sid]->reviews = array();

                    $assessments = $DB->get_records('workshop_assessments', array('submissionid' => $sid));
                    if ($assessments===false) {
                        $assessments = array();
                    }

                    if ($data->resetassessments) {
                        // reset workshop (=remove previous assessments and grades), if necessary
                        foreach (array_keys($assessments) as $aid) {
                            $DB->delete_records('workshop_grades', array('assessmentid' => $aid));
                        }
                        $DB->delete_records('workshop_assessments', array('submissionid' => $sid));
                        $DB->set_field('workshop_submissions', 'grade', null, array('id' => $sid));
                        $reset->submissionids[] = $sid;

                    } else {
                        foreach ($assessments as $aid => $assessment) {
                            $rid = $assessment->reviewerid;
                            if (empty($reviewers[$rid])) {
                                // user is not in the anonymous reviewer group
                                // probably left over from a previous vetting
                            } else {
                                $reviewers[$rid]->countreviews++;
                                $reviewers[$rid]->reviews[$sid] = $aid;
                            }
                            $submissions[$sid]->countreviews++;
                            $submissions[$sid]->reviews[$rid] = $aid;
                        }
                    }
                    unset($assessments);

                    // initialize real authorid for this submission
                    $submissions[$sid]->realauthorid = 0;

                    // get database records that link to this submission
                    if ($records = self::get_database_records($workshop, $sid)) {

                        // cache real authorid if record user is also a reviewer
                        $record = reset($records);
                        if (self::is_reviewer($record->userid, $reviewers)) {
                            $submissions[$sid]->realauthorid = $record->userid;
                        }

                        if ($data->resetassessments) {
                            foreach ($records as $record) {

                                // get peer_review fields for this dataid
                                if (! array_key_exists($record->dataid, $reviewfields)) {
                                    $select = 'dataid = ? AND name IN (?, ?, ?)';
                                    $params = array($record->dataid, 'peer_review_score',
                                                                     'peer_review_details',
                                                                     'peer_review_notes');
                                    if ($reviewfields[$record->dataid] = $DB->get_records_select_menu('data_fields', $select, $params, null, 'name,id')) {
                                        $reviewfields[$record->dataid] = $DB->get_in_or_equal($reviewfields[$record->dataid]);
                                    }
                                }

                                // reset content for peer_review fields with this recordid
                                if ($reviewfields[$record->dataid]) {
                                    list($select, $params) = $reviewfields[$record->dataid];
                                    $select = "fieldid $select AND recordid = ?";
                                    $params[] = $record->recordid;
                                    $DB->set_field_select('data_content', 'content', '', $select, $params);
                                    $reset->datarecordids[] = $record->recordid;
                                }
                            }
                        }
                    }
                    unset($records, $record);
                }

                if ($count = count($reset->submissionids)) {
                    sort($reset->submissionids);
                    $a = (object)array(
                        'count' => $count,
                        'ids' => implode(', ', $reset->submissionids)
                    );
                    $msg[] = get_string('submissiongradesreset', $this->plugin, $a);
                }

                if ($count = count($reset->datarecordids)) {
                    sort($reset->datarecordids);
                    $a = (object)array(
                        'count' => $count,
                        'ids' => implode(', ', $reset->datarecordids)
                    );
                    $msg[] = get_string('datarecordsreset', $this->plugin, $a);
                }

                // switch workshop to EVALUATION phase
                $workshop->switch_phase(workshop::PHASE_EVALUATION);

                if (empty($data->reviewspersubmission)) {
                    $countreviews = $countreviewers;
                } else {
                    $countreviews = $data->reviewspersubmission;
                }

                // Allocate reviewers to submission as necessary.
                foreach (array_keys($submissions) as $sid) {
                    $new = array();
                    while ($submissions[$sid]->countreviews < $countreviews) {
                        if (! $this->add_reviewer($workshop, $sid, $submissions, $reviewers, $new)) {
                            break; // could not add reviewer for some reason
                        }
                    }
                    if ($count = count($new)) {
                        sort($new);
                        $a = (object)array(
                            'sid'   => $sid,
                            'count' => $count,
                            'ids'   => implode(', ', $new)
                        );
                        $msg[] = get_string('reviewersadded', $this->plugin, $a);
                    }
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }

    /**
     * add_reviewer, while observing the following limitations:
     * (1) reviewers should be assigned an equal number of submissions
     * (2) reviewers should not be assigned more than once to the same submission
     * (3) real reviewers should not be not assigned to their own submissions
     *
     * @param integer submission id
     * @param object  (passed by reference) $submission
     * @param array   (passed by reference) ids of $anonymous reviewers
     * @param array   (passed by reference) map submision authors to $realathours
     * @param array   (passed by reference) $msg strings
     * @return boolean,  and may also update the $submissions and $reviewers arrays
     */
    protected function add_reviewer($workshop, $sid, &$submissions, &$reviewers, &$new) {
        uasort($reviewers, array(get_class($this), 'sort_reviewers'));
        foreach ($reviewers as $rid => $reviewer) {
            if (array_key_exists($rid, $submissions[$sid]->reviews)) {
                continue; // reviewer is already reviewing this submission
            }
            if ($submissions[$sid]->realauthorid==$reviewer->realuserid) {
                continue; // reviewers cannot review their own submissions
            }
            $aid = $workshop->add_allocation($submissions[$sid], $rid);
            if ($aid===false || $aid==workshop::ALLOCATION_EXISTS) {
                continue; // unusual - could not add new allocation
            }
            $new[] = $rid;
            $reviewers[$rid]->countreviews++;
            $reviewers[$rid]->reviews[$sid] = $aid;
            $submissions[$sid]->countreviews++;
            $submissions[$sid]->reviews[$rid] = $aid;
            return true;
        }
        return false;
    }

    /**
     * sort_reviewers
     *
     * @param object $a
     * @param object $b
     * @return integer if ($a < $b) -1; if ($a > $b) 1; Otherwise, 0.
     */
    static public function sort_reviewers($a, $b) {
        if ($a->countreviews < $b->countreviews) {
            return -1;
        }
        if ($a->countreviews > $b->countreviews) {
            return 1;
        }
        return mt_rand(-1, 1); // random shuffle
    }

    /**
     * is_reviewer
     *
     * @param integer $userid
     * @param array   $reviewers
     * @return boolean TRUE if $authorid is a
     */
    static public function is_reviewer($userid, $reviewers) {
        foreach ($reviewers as $reviewer) {
            if ($reviewer->realuserid==$userid) {
                return true;
            }
        }
        return false;
    }
}

/**
 * block_maj_submissions_tool_setupschedule
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_setupschedule extends block_maj_submissions_tool_base {

    protected $type = 'publish';
    protected $modulename = 'page';
    protected $defaultname = 'conferenceschedule';

    // default values for a new "page" resource
    protected $defaultvalues = array(
        'intro' => '',
        'introformat' => FORMAT_HTML, // =1
        'content' => '',
        'contentformat' => FORMAT_HTML, // =1,
        'display' => 0, // = RESOURCELIB_DISPLAY_AUTO
        'displayoptions' => array('printheading' => 0, 'printintro' => 0)
    );

    // caches for the menu items
    protected $schedule_day      = null;
    protected $schedule_time     = null;
    protected $schedule_duration = null;
    protected $schedule_room     = null;
    protected $schedule_audience = null;
    protected $schedule_event    = array();

    const TEMPLATE_NONE     = 0;
    const TEMPLATE_ACTIVITY = 1;
    const TEMPLATE_FILENAME = 2;
    const TEMPLATE_UPLOAD   = 3;
    const TEMPLATE_GENERATE = 4;

    /**
     * definition
     */
    public function definition() {
        global $PAGE;

        if (method_exists($PAGE->requires, 'jquery')) {
            $PAGE->requires->jquery();
            $PAGE->requires->jquery_plugin('ui');
        } else {
            // get JQuery some other way
        }
        $PAGE->requires->js('/blocks/maj_submissions/tools/setupschedule.js', true);

        $mform = $this->_form;

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }


        if (empty($this->cmid)) {

            $name = 'publishcmid';
            $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
            $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

            // --------------------------------------------------------
            $name = 'template';
            $label = get_string($name, $this->plugin);
            $mform->addElement('header', $name, $label);
            // --------------------------------------------------------

            $name = 'templatetype';
            $options = array(self::TEMPLATE_NONE     => '',
                             self::TEMPLATE_ACTIVITY => get_string('selecttemplateactivity', $this->plugin),
                             self::TEMPLATE_FILENAME => get_string('selecttemplatefilename', $this->plugin),
                             self::TEMPLATE_UPLOAD   => get_string('uploadtemplatefile',     $this->plugin),
                             self::TEMPLATE_GENERATE => get_string('generatesampletemplate', $this->plugin));
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_FILE, $options);
            $mform->disabledIf($name, 'publishcmidnum', 'neq', self::CREATE_NEW);

            $name = 'templateactivity';
            $this->add_field_template($mform, $this->plugin, $name, $this->modulename, 'publishcmid');
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_ACTIVITY);

            $name = 'templatefilename';
            $this->add_field_templatefilename($mform, $this->plugin, $name);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_FILENAME);

            $name = 'templateupload';
            $label = get_string($name, $this->plugin);
            $mform->addElement('filepicker', $name, $label);
            $mform->addHelpButton($name, $name, $this->plugin);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_UPLOAD);

        } else {

            $name = 'publishcmid';
            $options = self::get_cmids($mform, $this->course, $this->plugin, $this->modulename, 'activity', 0, true);
            $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, $this->cmid);

            // --------------------------------------------------------
            $name = 'sessioninfo';
            $label = get_string($name, $this->plugin);
            $mform->addElement('header', $name, $label);
            // --------------------------------------------------------

            $this->set_schedule_menus();

            $name = 'schedule_event';
            $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $this->$name);

            $name = 'schedule_day';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_time';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_duration';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_room';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_audience';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);
        }

        $this->add_action_buttons();
    }

    /**
     * add_field_templatefilename
     *
     * @param object $mform
     * @param string $plugin
     * @param string $name
     */
    static public function add_field_templatefilename($mform, $plugin, $name) {
        global $CFG;

        $elements = array();
        $default = '';
        $strman = get_string_manager();

        $dirpath = $CFG->dirroot.'/blocks/maj_submissions/templates';
        if (is_dir($dirpath)) {

            $filenames = scandir($dirpath);
            foreach ($filenames as $filename) {
                if (substr($filename, 0, 1)=='.') {
                    continue; // skip hidden shortnames
                }
                if (substr($filename, -5)=='.html') {
                    $label = substr($filename, 0, -5);
                } else if (substr($filename, -4)=='.htm') {
                    $label = substr($filename, 0, -4);
                } else {
                    $label = '';
                }
                if ($label) {
                    $label = 'template'.$label;
                    if ($strman->string_exists($label, $plugin)) {
                        $label = get_string($label, $plugin);
                    } else {
                        $label = $filename;
                    }
                    $elements[] = $mform->createElement('radio', $name, null, $label, $filename);
                    if ($default=='') {
                        $default = $filename;
                    }
                }
            }
        }

        if (count($elements)) {
            $group_name = 'group_'.$name;
            $label = get_string($name, $plugin);
            $mform->addGroup($elements, $group_name, $label, html_writer::empty_tag('br'), false);
            $mform->addHelpButton($group_name, $name, $plugin);
            $mform->setType($name, PARAM_TEXT);
            $mform->setDefault($name, $default);
        }
    }

    /**
     * set_schedule_menus
     *
     * @param boolean $shorten_texts (optional, default=FALSE)
     * @return void, but will update "schedule_xxx" properties
     */
    public function set_schedule_menus($shorten_texts=false) {
        global $DB;

        $config = $this->instance->config;
        $modinfo = get_fast_modinfo($this->course);

        // the database types
        $types = array('presentation',
                       'workshop',
                       'sponsored',
                       'event');

        // database field types
        $names = array('schedule_day',
                       'schedule_time',
                       'schedule_duration',
                       'schedule_room',
                       'schedule_audience');

        foreach ($types as $type) {
            if ($type=='event') {
                $types_cmid = $type.'scmid';
            } else {
                $types_cmid = 'collect'.$type.'scmid';
            }
            if (empty($config->$types_cmid)) {
                continue;
            }
            $cmid = $config->$types_cmid;
            if (! array_key_exists($cmid, $modinfo->cms)) {
                continue;
            }
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname != 'data') {
                continue;
            }
            $dataid = $cm->instance;
            foreach ($names as $name) {
                if ($this->$name) {
                    continue;
                }
                $params = array('dataid' => $dataid, 'name' => $name);
                if (! $field = $DB->get_record('data_fields', $params)) {
                    continue;
                }
                if (! $field->param1) {
                    continue;
                }
                if (self::is_menu_field($field)) {
                    $array = preg_split('/[\\r\\n]+/', $field->param1);
                    $array = array_filter($array);
                    $array = array_combine($array, $array);
                    foreach ($array as $key => $value) {
                        $value = self::convert_to_multilang($value, $config);
                        $value = block_maj_submissions::filter_text($value);
                        $array[$key] = $value;
                    }
                    array_unshift($array, '');
                    $this->$name = $array;
                }
            }

            $params = array('dataid' => $dataid, 'name' => $type.'_title');
            if ($field = $DB->get_record('data_fields', $params)) {
                $params = array('fieldid' => $field->id);
                if ($menu = $DB->get_records_menu('data_content', $params, 'content', 'id,content')) {
                    $name = get_string($types_cmid, $this->plugin);
                    $this->schedule_event[$name] = array_filter($menu);
                }
            }
        }
    }

    /**
     * is_menu_field
     *
     * @param object $field a record from the "data_fields" table
     * @return boolean TRUE if this is a "menu" field; otherwise FALSE
     */
    static public function is_menu_field($field) {
        if ($field->type=='admin') {
            return ($field->param10=='menu');
        } else {
            return ($field->type=='menu');
        }
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
        $time = time();
        $msg = array();

        // check we have some form data
        if ($data = $this->get_data()) {

            $cm = $this->get_cm($msg, $data, $time, 'publishcmid');

            if ($cm) {
                $this->set_schedule_menus();

                $recordid = 0;
                $fields = array('schedule_event',    'schedule_day',  'schedule_time',
                                'schedule_duration', 'schedule_room', 'schedule_audience');

                $fields = array_flip($fields);
                foreach (array_keys($fields) as $name) {

                    $content = null;
                    if (isset($data->$name)) {
                        if ($name=='schedule_event') {
                            foreach (array_keys($this->$name) as $activityname) {
                                if (array_key_exists($data->$name, $this->$name[$activityname])) {
                                    $content = $this->$name[$activityname][$data->$name];
                                    $params = array('id' => $data->$name);
                                    $recordid = $DB->get_field('data_content', 'recordid', $params);
                                }
                            }
                        } else if (array_key_exists($data->$name, $this->$name)) {
                            $content = $this->$name[$data->$name];
                        }
                    }

                    if ($content===null) {
                        unset($fields[$name]);
                    } else {
                        $fields[$name] = $content;
                    }
                }

                if ($recordid) {

                    $params = array('id' => $recordid);
                    $dataid = $DB->get_field('data_records', 'dataid', $params);

                    foreach ($fields as $name => $content) {

                        if ($name=='schedule_event') {
                            // format feedback message and link to db record
                            $params = array('d' => $dataid, 'rid' => $recordid);
                            $link = new moodle_url('/mod/data/view.php', $params);
                            $params = array('href' => $link, 'target' => '_blank');
                            $link = html_writer::tag('a', $content, $params);
                            $msg[] = get_string('scheduleupdated', $this->plugin, $link);
                        } else {
                            // update field $content for this $recordid
                            $params = array('dataid' => $dataid, 'name' => $name);
                            if ($fieldid = $DB->get_field('data_fields', 'id', $params)) {

                                $params = array('fieldid' => $fieldid, 'recordid' => $recordid);
                                $DB->set_field('data_content', 'content', $content, $params);
                            }
                        }
                    }
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }

    /**
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        global $CFG;

        $defaultvalues = parent::get_defaultvalues($data, $time);

        $name = 'displayoptions';
        if (is_array($defaultvalues[$name])) {
            $defaultvalues[$name] = serialize($defaultvalues[$name]);
        }

        // search string to detect leading and trailing <body> tags in HTML file/snippet
        $search = '/(^.*?<body[^>]*>\s*)|(\s*<\/body>.*?)$/su';

        switch ($data->templatetype) {

            case self::TEMPLATE_ACTIVITY:
                $template = $DB->get_record('course_modules', array('id' => $data->templateactivity));
                $template = $DB->get_record($this->modulename, array('id' => $template->instance));
                break;

            case self::TEMPLATE_FILENAME:
                $filename = $CFG->dirroot.'/blocks/maj_submissions/templates/'.$data->templatefilename;
                if (is_file($filename)) {
                    $template = new stdClass();
                    $template->content = file_get_contents($filename);
                    $template->content = preg_replace($search, '', $template->content);
                }
                break;

            case self::TEMPLATE_UPLOAD:
                $file = $this->save_temp_file('templateupload');
                if (is_file($file)) {
                    $template = new stdClass();
                    $template->content = file_get_contents($file);
                    $template->content = preg_replace($search, '', $template->content);
                    fulldelete($file);
                }
                break;

            case self::TEMPLATE_GENERATE:
                $template = new stdClass();
                $template->content = $this->get_defaulttemplate();
                break;
        }

        if ($template) {
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
     * get_defaultcontent
     *
     * @todo Finish documenting this function
     */
    protected function get_defaulttemplate() {

        $config = $this->instance->config;

        // get multilang title from config settings, if possible
        $title = array();
        $config = $this->instance->config;
        if (empty($config->displaylangs)) {
            $langs = '';
        } else {
            $langs = $config->displaylangs;
        }
        $langs = block_maj_submissions::get_languages($langs);
        foreach ($langs as $lang) {
            $name = 'conferencename'.$lang;
            if (isset($config->$name) && $config->$name) {
                $title[] = html_writer::tag('span', $config->$name, array('class' => 'multilang', 'lang' => $lang));
            }
        }
        if (count($title)) {
            $title = implode('', $title);
        } else if (isset($config->conferencename)) {
            $title = $config->conferencename;
        } else {
            $title = '';
        }
        if ($title=='') {
            $title = $this->instance->get_string('conferenceschedule', $this->plugin);
        }

        // set array of common surnames to use as authors
        $authors = array('Chan',   'Doe',    'Garcia',
                         'Honda',  'Jones',  'Khan',
                         'Lee',    'Mensah', 'Nguyen',
                         'Nomo',   'Novak',  'Patel',
                         'Petrov', 'Rossi',  'Singh',
                         'Suzuki', 'Smith',  'Wang');

        // set array of letters from which to generate random words
        $letters = range(97, 122); // ascii a-z
        $letters = array_map('chr', $letters);

        $rooms = array(
            0 => (object)array(
                    'name' => $this->instance->get_string('roomname0', $this->plugin),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 100),
                    'topic' => '',
                 ),
            1 => (object)array(
                    'name' => $this->instance->get_string('roomnamex', $this->plugin, 1),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 50),
                    'topic' => $this->instance->get_string('roomtopic1', $this->plugin),
                 ),
            2 => (object)array(
                    'name' => $this->instance->get_string('roomnamex', $this->plugin, 2),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 40),
                    'topic' => $this->instance->get_string('roomtopic2', $this->plugin),
                 ),
            3 => (object)array(
                    'name' => $this->instance->get_string('roomnamex', $this->plugin, 3),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 35),
                    'topic' => $this->instance->get_string('roomtopic3', $this->plugin),
                 ),
            4 => (object)array(
                    'name' => $this->instance->get_string('roomnamex', $this->plugin, 4),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 30),
                    'topic' => $this->instance->get_string('roomtopic4', $this->plugin),
                 ),
            5 => (object)array(
                    'name' => $this->instance->get_string('roomnamex', $this->plugin, 5),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 25),
                    'topic' => $this->instance->get_string('roomtopic5', $this->plugin),
                 ),
            6 => (object)array(
                    'name' => $this->instance->get_string('roomnamex', $this->plugin, 6),
                    'seats' => $this->instance->get_string('totalseatsx', $this->plugin, 20),
                    'topic' => $this->instance->get_string('roomtopic6', $this->plugin),
                 ),
        );

        $days  = array(
            1 => (object)array(
                    'tabtext' =>  'Feb 21st<br />(Wed)',
                    'fulltext' => 'Feb 21st (Wed)'
                 ),
            2 => (object)array(
                    'tabtext' =>  'Feb 22nd<br />(Thu)',
                    'fulltext' => 'Feb 22nd (Thu)'
                 ),
            3 => (object)array(
                    'tabtext' =>  'Feb 23rd<br />(Fri)',
                    'fulltext' => 'Feb 23rd (Fri)'
                 ),
        );

        $slots = array(
            0 => (object)array(
                    'time' =>  '8:30 - 9:00',
                    'duration' => '30 mins',
                    'allrooms' => true
                 ),
            1 => (object)array(
                    'time' =>  '9:00 - 9:40',
                    'duration' => '40 mins'
                 ),
            2 => (object)array(
                    'time' =>  '9:50 - 10:30',
                    'duration' => '40 mins'
                 ),
            3 => (object)array(
                    'time' =>  '10:40 - 10:50',
                    'duration' => '10 mins'
                 ),
            4 => (object)array(
                    'time' =>  '11:00 - 12:00',
                    'duration' => '60 mins'
                 ),
        );

        $countrooms = max(array_keys($rooms));
        $countcols = ($countrooms + 1);

        $content = '';
        $content .= html_writer::start_tag('table', array('class' => 'schedule'));

        // COLGROUP (after CAPTION, before THEAD)
        $content .= html_writer::start_tag('colgroup');
        $content .= html_writer::start_tag('col', array('class' => 'room0 timeheading'));
        foreach ($rooms as $r => $room) {
            if ($r==0) {
                continue;
            }
            $content .= html_writer::start_tag('col', array('class' => "room$r"));
        }
        $content .= html_writer::end_tag('colgroup');

        // THEAD
        $content .= html_writer::start_tag('thead');

        // title
        $content .= html_writer::start_tag('tr', array('class' => 'scheduletitle'));
        $content .= html_writer::tag('td', $title, array('colspan' => $countcols));
        $content .= html_writer::end_tag('tr');

        // tabs (one DIV for each day)
        $content .= html_writer::start_tag('tr', array('class' => 'tabs'));
        $content .= html_writer::start_tag('td', array('colspan' => $countcols));
        foreach ($days as $d => $day) {
            $content .= html_writer::tag('div', $day->tabtext, array('class' => "tab day$d".($d==2 ? ' active' : '')));
        }
        $content .= html_writer::end_tag('td');
        $content .= html_writer::end_tag('tr');

        $content .= html_writer::end_tag('thead');

        // TBODY (one TBODY for each day)
        foreach ($days as $d => $day) {
            $content .= html_writer::start_tag('tbody', array('class' => "day day$d".($d==2 ? ' active' : '')));

            // date (one full-width cell)
            $content .= html_writer::start_tag('tr', array('class' => 'date'));
            $content .= html_writer::tag('td', $day->fulltext, array('colspan' => $countcols));
            $content .= html_writer::end_tag('tr');

            // room headings
            $content .= html_writer::start_tag('tr', array('class' => 'roomheadings'));
            $content .= html_writer::tag('th', '', array('class' => 'timeheading'));
            foreach ($rooms as $r => $room) {
                if ($r==0) {
                    continue;
                }
                $text = html_writer::tag('span', $room->name,  array('class' => 'roomname')).
                        html_writer::tag('span', $room->seats, array('class' => 'totalseats')).
                        html_writer::tag('div',  $room->topic, array('class' => 'roomtopic'));
                $content .= html_writer::tag('th', $text, array('class' => "roomheading room$r"));
            }
            $content .= html_writer::end_tag('tr');

            // slots
            foreach ($slots as $s => $slot) {
                $content .= html_writer::start_tag('tr', array('class' => 'slot'));

                $time = $slot->time.
                        html_writer::tag('span', $slot->duration, array('class' => 'duration'));
                $content .= html_writer::tag('td', $time, array('class' => 'timeheading'));

                $attending = rand(1, $countrooms);
                foreach ($rooms as $r => $room) {
                    $session = '';

                    if (empty($slot->allrooms)) {
                        $slot->allrooms = false;
                    }

                    if ($r==0 && $slot->allrooms==false) {
                        continue;
                    }
                    if ($r && $slot->allrooms==true) {
                        continue;
                    }
                    if ($r==0) {
                        $attending = $r;
                    }

                    // randomly skip some sessions
                    if ($attending==$r || rand(0, 2)) {

                        // time
                        $session .= html_writer::tag('div', $time, array('class' => 'time'));

                        // room
                        $text = html_writer::tag('span', $room->name,  array('class' => 'roomname')).
                                html_writer::tag('span', $room->seats, array('class' => 'totalseats')).
                                html_writer::tag('div',  $room->topic, array('class' => 'roomtopic'));
                        $session .= html_writer::tag('div',  $text, array('class' => 'room'));

                        // title
                        $text = $this->instance->get_string('sessiontitlex', $this->plugin, "$d.$s.$r");
                        $session .= html_writer::tag('div', $text, array('class' => 'title'));

                        // [schedulenumber]  + list of authors
                        $keys = array_rand($authors, rand(1, 2));
                        if (is_array($keys)) {
                            sort($keys);
                            $text = array();
                            foreach ($keys as $key) {
                                $text[] = $authors[$key];
                            }
                            $text = implode(', ', $text);
                        } else {
                            $text = $authors[$keys];
                        }
                        $text = html_writer::tag('span', "$d$s$r-P", array('class' => 'schedulenumber')).$text;
                        $session .= html_writer::tag('div', $text, array('class' => 'authors'));

                        //  summary
                        $summary = array();
                        for ($i=0; $i<40; $i++) {
                            $keys = array_rand($letters, rand(3, 7));
                            sort($keys);
                            $word = '';
                            foreach ($keys as $key) {
                                $word .= $letters[$key];
                            }
                            $summary[] = $word;
                        }
                        $summary = implode(' ', $summary);
                        $session .= html_writer::tag('div', $summary, array('class' => 'summary'));

                        // capacity
                        if ($r==$attending) {
                            $capacity = html_writer::empty_tag('input', array('type' => 'checkbox', 'value' => 1, 'checked' => 'checked')).
                                        html_writer::tag('span', 'Attending', array('class' => 'text'));
                        } else {
                            $capacity = html_writer::empty_tag('input', array('type' => 'checkbox', 'value' => 1)).
                                        html_writer::tag('span', 'Not attending', array('class' => 'text'));
                        }
                        $capacity = html_writer::tag('div', "$room->seats left", array('class' => 'emptyseats')).
                                    html_writer::tag('div', $capacity, array('class' => 'attendance'));
                        $session .= html_writer::tag('div', $capacity, array('class' => 'capacity'));
                    }

                    $class = 'session';
                    if ($slot->allrooms) {
                        $class .= ' allrooms';
                    }
                    if ($r==$attending) {
                        $class .= ' attending';
                    }
                    if ($session=='') {
                        $class .= ' emptysession';
                    }
                    $params = array('class' => $class);
                    if ($slot->allrooms) {
                        $params['colspan'] = $countrooms;
                    }
                    $content .= html_writer::tag('td', $session, $params);
                }

                $content .= html_writer::end_tag('tr');
            }

            $content .= html_writer::end_tag('tbody');
        }
        $content .= html_writer::end_tag('table');

        // we use JS to add the CSS file because <style> tags are removed by Moodle
        $src = new moodle_url('/blocks/maj_submissions/templates/template');
        $content .= html_writer::start_tag('script', array('type' => 'text/javascript'))."\n";
        $content .= "//<![CDATA[\n";
        $content .= '(function(){'."\n";
        $content .= '    var wwwroot = location.href.replace(new RegExp("^(.*?)/mod/.*$"), "$1");'."\n";
        $content .= '    var src = "'.$src->out_as_local_url().'";'."\n";
        $content .= '    var css = "@import url(" + wwwroot + src + ".css);";'."\n";
        $content .= '    var style = document.createElement("style");'."\n";
        $content .= '    style.setAttribute("type","text/css");'."\n";
        $content .= '    style.appendChild(document.createTextNode(css));'."\n";
        $content .= '    var script = document.createElement("script");'."\n";
        $content .= '    script.setAttribute("type","text/javascript");'."\n";
        $content .= '    script.setAttribute("src", wwwroot + src + ".js");'."\n";
        $content .= '    var head = document.getElementsByTagName("head");'."\n";
        $content .= '    head[0].appendChild(style);'."\n";
        $content .= '    head[0].appendChild(script);'."\n";
        $content .= '})();'."\n";
        $content .= "//]]>\n";
        $content .= html_writer::end_tag('script');

        return $content;
    }
}
