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
    const TEXT_FIELD_SIZE = 20;

    protected $cmid = 0;
    protected $plugin = '';
    protected $course = null;
    protected $instance = null;
    protected $modulename = '';
    protected $defaultvalues = array();
    protected $timefields = array(
        'timestart' => array(),
        'timefinish' => array()
    );
    protected $permissions = array();
    protected $availability = null;

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

        if ($this->type) {
            // set the "course_module" id, if it is defined and still exists
            $cmid = $this->type.'cmid';
            if (property_exists($this->instance->config, $cmid)) {
                $cmid = $this->instance->config->$cmid;
                if (array_key_exists($cmid, get_fast_modinfo($this->course)->cms)) {
                    $this->cmid = $cmid;
                }
            }

            // set start time, if any, in $newdatarecord
            $time = $this->type.'timestart';
            if (property_exists($this->instance->config, $time)) {
                $time = $this->instance->config->$time;
                foreach ($this->timefields['timestart'] as $timefield) {
                    $this->defaultvalues[$timefield] = $time;
                }
            }

            // set finish time, if any, in $newdatarecord
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

        $message = '';

        if ($action=='confirmdelete') {
        }

        if ($action=='delete') {
        }

        return $message;
    }

    /**
     * get_defaultvalues
     *
     * @todo Finish documenting this function
     */
    protected function get_defaultvalues() {
        $this->defaultvalues['intro'] = $this->get_defaultintro();
        return $this->defaultvalues;
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
     * set availability on a newly created $cm
     *
     * @uses $DB
     * @todo Finish documenting this function
     */
    static public function set_cm_availability($cm, $availability) {
        // e.g. restrict "Events database" to admins only
        // see "update_course_module_availability()"
        // in "block_taskchain_navigation/accesscontrol.php"
        if (property_exists($cm, 'availability') && $availability && property_exists($availability, 'c')) {
            $tree = new \core_availability\tree($availability);
        }
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
            if ($name = self::get_sectionname($section)) {
                $options[$sectionnum] = $name;
            } else {
                $options[$sectionnum] = self::get_sectionname_default($course, $sectionnum);
            }
        }
        return self::format_select_options($plugin, $options, 'section');
    }

    /**
     * get_sectionname_default
     *
     * @param object   $course
     * @param object   $section
     * @param string   $dateformat (optional, default='%b %d')
     * @return string  name of $section
     */
    static public function get_sectionname_default($course, $sectionnum, $dateformat='%b %d') {

        // set course section type
        if ($course->format=='weeks') {
            $sectiontype = 'week';
        } else if ($course->format=='topics') {
            $sectiontype = 'topic';
        } else {
            $sectiontype = 'section';
        }

        // "weeks" format
        if ($sectiontype=='week' && $sectionnum > 0) {
            if ($dateformat=='') {
                $dateformat = get_string('strftimedateshort');
            }
            // 604800 : number of seconds in 7 days i.e. WEEKSECS
            // 518400 : number of seconds in 6 days i.e. WEEKSECS - DAYSECS
            $date = $course->startdate + 7200 + (($sectionnum - 1) * 604800);
            return userdate($date, $dateformat).' - '.userdate($date + 518400, $dateformat);
        }

        // get string manager object
        $strman = get_string_manager();

        // specify course format plugin name
        $courseformat = 'format_'.$course->format;

        if ($strman->string_exists('section'.$sectionnum.'name', $courseformat)) {
            return get_string('section'.$sectionnum.'name', $courseformat);
        }

        if ($strman->string_exists('sectionname', $courseformat)) {
            return get_string('sectionname', $courseformat).' '.$sectionnum;
        }

        if ($strman->string_exists($sectiontype, 'moodle')) {
            return get_string($sectiontype).' '.$sectionnum;
        }

        if ($strman->string_exists('sectionname', 'moodle')) {
            return get_string('sectionname').' '.$sectionnum;
        }

        return $sectiontype.' '.$sectionnum;
    }

    /**
     * get_options_cmids
     *
     * @param object  $mform
     * @param string  $course
     * @param string  $plugin
     * @param string  $modnames
     * @param integer $sectionnum (optional, default=0)
     * @return array($cmid => $name) of activities from the specified $sectionnum
     *                               or from the whole course (if $sectionnum==0)
     */
    static public function get_cmids($mform, $course, $plugin, $modnames, $type='', $sectionnum=0) {
        $options = array();

        $modnames = explode(',', $modnames);
        $modnames = array_filter($modnames);
        $count = count($modnames);

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
                        if ($count==0 || in_array($cm->modname, $modnames)) {
                            if ($sectionname=='') {
                                $sectionname = self::get_sectionname($section, 0);
                                $options[$sectionname] = array();
                            }
                            $name = $cm->name;
                            $name = block_maj_submissions::filter_text($name);
                            $name = block_maj_submissions::trim_text($name);
                            $options[$sectionname][$cmid] = $name;
                        }
                    }
                }
            }
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
     * get_sectionname
     *
     * names longer than $namelength will be trancated to to HEAD ... TAIL
     * where the number of characters in HEAD is $headlength
     * and the number of characters in TIAL is $taillength
     *
     * @param object   $section
     * @param integer  $namelength of section name (optional, default=28)
     * @param integer  $headlength of head of section name (optional, default=10)
     * @param integer  $taillength of tail of section name (optional, default=10)
     * @return string  name of $section
     */
    static public function get_sectionname($section, $namelength=28, $headlength=10, $taillength=10) {

        // extract section title from section name
        if ($name = block_maj_submissions::filter_text($section->name)) {
            return block_maj_submissions::trim_text($name, $namelength, $headlength, $taillength);
        }

        // extract section title from section summary
        if ($name = block_maj_submissions::filter_text($section->summary)) {

            // remove script and style blocks
            $select = '/\s*<(script|style)[^>]*>.*?<\/\1>\s*/is';
            $name = preg_replace($select, '', $name);

            // look for HTML H1-5 tags or the first line of text
            $tags = 'h1|h2|h3|h4|h5|h6';
            if (preg_match('/<('.$tags.')\b[^>]*>(.*?)<\/\1>/is', $name, $matches)) {
                $name = $matches[2];
            } else {
                // otherwise, get first line of text
                $name = preg_split('/<br[^>]*>/', $name);
                $name = array_map('strip_tags', $name);
                $name = array_map('trim', $name);
                $name = array_filter($name);
                if (empty($name)) {
                    $name = '';
                } else {
                    $name = reset($name);
                }
            }
            $name = trim(strip_tags($name));
            $name = block_maj_submissions::trim_text($name, $namelength, $headlength, $taillength);
            return $name;
        }

        return ''; // section name and summary are empty
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
     * data_postprocessing
     *
     * @uses $DB
     * @param object $data
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function data_postprocessing() {
        global $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->dirroot.'/lib/xmlize.php');

        // get/create the $cm record and associated $section
        $cm = false;
        if ($data = $this->get_data()) {

            $databasenum  = $data->databaseactivitynum;
            $databasename = $data->databaseactivityname;
            $sectionnum   = $data->coursesectionnum;
            $sectionname  = $data->coursesectionname;

            if ($databasenum) {
                if ($databasenum==self::CREATE_NEW) {
                    if ($sectionnum==self::CREATE_NEW) {
                        $section = self::get_section($this->course, $sectionname);
                    } else {
                        $section = get_fast_modinfo($this->course)->get_section_info($sectionnum);
                    }
                    if ($section) {
                        $defaultvalues = $this->get_defaultvalues();
                        $cm = self::get_coursemodule($this->course, $section, $this->modulename,  $databasename, $defaultvalues);
                    }
                    if ($cm) {
                        self::set_cm_permissions($cm, $this->permissions);
                        self::set_cm_availability($cm, $this->availability);
                        if ($this->cmid==0) {
                            $this->cmid = $cm->id;
                            $cmid = $this->type.'cmid';
                            $this->instance->config->$cmid = $cm->id;
                            $this->instance->instance_config_save($this->instance->config);
                        }
                    }
                } else {
                    $cm = get_fast_modinfo($this->course)->get_cm($databasenum);
                }
            }
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
            $js = array();
            $js[] = '<script type="text/javascript">';
            $js[] = '//<![CDATA[';
            $js[] = '(function(){';
            $js[] = '    var css = ".path-mod-data .howto { display: none; }";';
            $js[] = '    var style = document.createElement("style");';
            $js[] = '    style.setAttribute("type","text/css");';
            $js[] = '    style.appendChild(document.createTextNode(css));';
            $js[] = '    var head = document.getElementsByTagName("head");';
            $js[] = '    head[0].appendChild(style);';
            $js[] = '}());';
            $js[] = '//]]>';
            $js[] = '</script>';
            $intro = implode("\n", $js)."\n".$intro;
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

                $icon = $OUTPUT->pix_url('t/delete');
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
}
class block_maj_submissions_tool_setuppresentations extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'collectpresentations';
    protected $defaultpreset = 'presentations';
}
class block_maj_submissions_tool_setupworkshops extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'collectworkshops';
    protected $defaultpreset = 'workshops';
}
class block_maj_submissions_tool_setupevents extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'registerevents';
    protected $defaultpreset = 'events';
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
    protected $defaultvalues = array(
        'visible'         => 0,
        'submissionstart' => 0,
        'submissionend'   => 0,
        'assessmentstart' => 0,
        'assessmentend'   => 0
    );
    protected $timefields = array(
        'timestart' => array('submissionstart', 'assessmentstart'),
        'timefinish' => array('submissionend', 'assessmentend')
    );

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
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $name = 'resetworkshop';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', 0);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', self::CREATE_NEW);

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
            $options = $DB->get_records_menu('data_fields', array('dataid' => $dataid), null, 'id,name');
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
     * data_postprocessing
     *
     * @uses $DB
     * @param object $data
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function data_postprocessing() {
        global $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->dirroot.'/lib/xmlize.php');

        // get/create the $cm record and associated $section
        $cm = false;
        if ($data = $this->get_data()) {

            $workshopnum  = $data->targetworkshopnum;
            $workshopname = $data->targetworkshopname;
            $sectionnum   = $data->coursesectionnum;
            $sectionname  = $data->coursesectionname;

            if ($workshopnum) {
                if ($workshopnum==self::CREATE_NEW) {
                    if ($sectionnum==self::CREATE_NEW) {
                        $section = self::get_section($this->course, $sectionname);
                    } else {
                        $section = get_fast_modinfo($this->course)->get_section_info($sectionnum);
                    }
                    $cm = self::get_coursemodule($this->course, $section, $this->modulename, $workshopname, $this->defaultvalues);
                    self::set_cm_permissions($cm, $this->permissions);
                    self::set_cm_availability($cm, $this->availability);
                } else {
                    $cm = get_fast_modinfo($this->course)->get_cm($workshopnum);
                }
            }
        }

        if ($cm) {

            // get workshop
            $workshop = $DB->get_record('workshop', array('id' => $cm->instance), '*', MUST_EXIST);
            $workshop->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
            $workshop->instance   = $cm->instance;
        }

        return false; // shouldn't happen !!
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
     * definition
     */
    public function definition() {
        $mform = $this->_form;

        $name = 'targetworkshop';
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'workshop');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);

        $name = 'vettinggroup';
        $options = groups_list_to_menu(groups_get_all_groups($this->course->id));
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);

        $this->add_action_buttons();
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

    protected $schedule_day      = null;
    protected $schedule_time     = null;
    protected $schedule_duration = null;
    protected $schedule_room     = null;
    protected $schedule_audience = null;
    protected $schedule_event    = array();

    /**
     * definition
     */
    public function definition() {
        $mform = $this->_form;

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

        $this->add_action_buttons();
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

        $types = array('presentation',
                       'workshop',
                       'sponsored',
                       'event');

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
                    $this->$name = preg_split('/[\\r\\n]+/', $field->param1);
                    $this->$name = array_filter($this->$name);
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
}
