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
     * A cache of the current time
     */
    protected $time = 0;

    /**
     * The names of the form field, if any, containing the id of a group of anonymous users
     */
    protected $groupfieldnames = '';

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
    protected $restrictions = null;

    /**
     * current language code and text
     */
    protected $langcode = '';
    protected $langtext = '';

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {

        // cache $this->plugin, $this->course and $this->instance
        $this->cache_customdata($customdata);

        // cache the current time
        $this->time = time();

        // convert groupfieldnames to an array
        if (is_string($this->groupfieldnames)) {
            $this->groupfieldnames = explode(',', $this->groupfieldnames);
            $this->groupfieldnames = array_map('trim', $this->groupfieldnames);
            $this->groupfieldnames = array_filter($this->groupfieldnames);
            $this->groupfieldnames = array_combine($this->groupfieldnames, $this->groupfieldnames);
        }

        // extract current language code and text (required for group menus)
        $this->langcode = current_language();
        $langs = get_string_manager()->get_list_of_translations();
        if (array_key_exists($this->langcode, $langs)) {
            $this->langtext = $langs[$this->langcode];
            // remove the suffix of the langtext, which contains 
            // the langcode, brackets and unicode "left-to-right" chars
            // https://en.wikipedia.org/wiki/Left-to-right_mark
            // The unicode LTR char is inserted by get_list_of_translations() 
            // (see line 518 in "lib/classes/string_manager_standard.php")
            $this->langtext = substr($this->langtext, 0, strrpos($this->langtext, ' '));
        }

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

            $modinfo = get_fast_modinfo($this->course);

            // set the "course_module" id, if it is defined and still exists
            $cmid = $this->type.'cmid';
            if (property_exists($this->instance->config, $cmid)) {
                $cmid = $this->instance->config->$cmid;
                if (array_key_exists($cmid, $modinfo->cms)) {
                    $cm = $modinfo->get_cm($cmid);
                    if (property_exists($cm, 'deletioninprogress') && $cm->deletioninprogress) {
                        // Moodle >= 3.2: activity is waiting to be deleted
                        $cmid = 0;
                    }
                } else {
                    // $cmid is not in current course. This can happen
                    // after restoring course or importing block settings.
                    $cmid = 0;
                }
                $this->cmid = $cmid;
            }

            if (is_array($this->timefields)) {

                // set start times, if any, in $defaultvalues
                if (array_key_exists('timestart', $this->timefields)) {
                    $time = $this->type.'timestart';
                    if (property_exists($this->instance->config, $time)) {
                        $time = $this->instance->config->$time;
                        foreach ($this->timefields['timestart'] as $timefield) {
                            $this->defaultvalues[$timefield] = $time;
                        }
                    }
                }

                // set finish times, if any, in $defaultvalues
                if (array_key_exists('timestart', $this->timefields)) {
                    $time = $this->type.'timefinish';
                    if (property_exists($this->instance->config, $time)) {
                        $time = $this->instance->config->$time;
                        foreach ($this->timefields['timefinish'] as $timefield) {
                            $this->defaultvalues[$timefield] = $time;
                        }
                    }
                }
            }
        }
    }

    /**
     * set_form_id
     *
     * @param  object $mform
     * @param  string form $id
     * @return void, but will update specified form attribute 
     */
    protected function set_form_id($mform, $id='') {
        $attributes = $mform->getAttributes();
        $attributes['id'] = ($id ? $id : get_class($this));
        $mform->setAttributes($attributes);
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
    protected function add_field($mform, $plugin, $name, $elementtype, $paramtype='', $options=null, $default=null) {
        if ($elementtype=='selectgroups') {
            if (is_array($options) && is_scalar(current($options))) {
                $elementtype = 'select'; // prevent error in PEAR library
            }
        }
        $label = get_string($name, $plugin);
        $mform->addElement($elementtype, $name, $label, $options);
        $mform->setType($name, $paramtype);
        $mform->setDefault($name, $default);
        $mform->addHelpButton($name, $name, $plugin);
    }

    /**
     * add_group_fields
     *
     * @param object $mform
     * @param string $plugin
     * @return void, but will modify $mform
     */
    protected function add_group_fields($mform) {
        foreach ($this->groupfieldnames as $fieldname => $defaultname) {
            list($options, $default) = $this->get_group_options($fieldname, $defaultname);
            $this->add_field($mform, $this->plugin, $fieldname, 'select', PARAM_INT, $options, $default);
        }
    }

    /**
     * get_group_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_group_options($fieldname, $defaultname='') {
        global $DB;

        $defaultgroupid = '';
        $defaultgroupname = ($defaultname ? $defaultname : $fieldname);

        // Cache strings that are used when looping through groups.
        $multilangsearch = '/<span[^>]*lang="(\w+)"[^>]*>(.*?)<\/span>/i';
        $membercountsql = 'SELECT COUNT(*) FROM {groups_members} WHERE groupid = ?';

        // Reduce multilang spans, and get the default item.
        $groups = groups_get_all_groups($this->course->id);
        foreach ($groups as $id => $group) {

            $groupname = $group->name;
            $englishname = $groupname;

            if (preg_match_all($multilangsearch, $groupname, $matches, PREG_OFFSET_CAPTURE)) {
                $i_max = (count($matches[0]) - 1);
                for ($i=$i_max; $i >= 0; $i--) {
                    list($match, $start) = $matches[0][$i];
                    if ($matches[1][$i][0] == 'en') {
                        $englishname = $matches[2][$i][0];
                    }
                    if ($this->langcode == $matches[1][$i][0]) {
                        $replace = $matches[2][$i][0];
                    } else {
                        $replace = '';
                    }
                    $groupname = substr_replace($groupname, $replace, $start, strlen($match));
                }
            }

            if ($englishname = trim($englishname)) {
                $englishname = strtolower(strip_tags($englishname));
                $englishname = preg_replace('/[^a-zA-Z0-9]/', '', $englishname);
                if (strpos($englishname, $defaultgroupname) === 0) {
                    if (block_maj_submissions::textlib('strpos', $groupname, $this->langtext)) {
                        // if this is the current language, we override previous value
                        $defaultgroupid = $id;
                    } else if ($defaultgroupid == '') {
                        $defaultgroupid = $id;
                    }
                }
            }

            $count = $DB->get_field_sql($membercountsql, array('groupid' => $id));
            $a = (object)array('name' => $groupname, 'count' => $count);
            $groups[$id] = get_string('groupnamecount', $this->plugin, $a);
        }
        return array($groups, $defaultgroupid);
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
     */
    protected function get_defaultvalues($data) {
        $defaultvalues = $this->defaultvalues;

        $defaultvalues['intro'] = $this->get_defaultintro();

        foreach ($this->timefields['timestart'] as $name) {
            $defaultvalues[$name] = $this->time;
        }

        foreach ($this->timefields['timefinish'] as $name) {
            $defaultvalues[$name] = 0;
        }

        // "timemodified" is required by most mod types
        // including "data", "page" and "workshop"
        $defaultvalues['timemodified'] = $this->time;

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
        if (isset($this->restrictions)) {
            $restrictions = $this->restrictions;
        } else {
            $restrictions = (object)array(
                'op' => '|',
                'c' => array(),
                'show' => true
            );
        }
        foreach ($this->groupfieldnames as $fieldname => $defaultname) {
            if (isset($data->$fieldname) && is_numeric($data->$fieldname)) {
                $restrictions->c[] = $this->get_group_restriction($data->$fieldname);
                if (isset($restrictions->showc)) {
                    // hide if condition not satisfied
                    $restrictions->showc[] = false;
                }
            }
        }
        return $restrictions;
    }

    /**
     * get_restrictions for a new activity created with this form
     *
     * @param object containing newly submitted form $data
     * @return array of stdClass()
     */
    public function get_group_restriction($groupid) {
        return (object)array(
            'type' => 'group',
            'id' => intval($groupid)
        );
    }

    /**
     * get_cm
     *
     * @param array  $msg
     * @param object $data
     * @param string $name
     * @param mixed  $a, arguments for get_string(), if needed
     * @return object newly added $cm object; otherwise false
     */
    public function get_cm(&$msg, $data, $name, $a=null) {
        global $DB;

        $cm = false;

        if (empty($data->$name)) {
            $activitynum  = $name.'num';
            $activitynum = (empty($data->$activitynum) ? 0 : $data->$activitynum);
            $activityname = $name.'name';
            $activityname = (empty($data->$activityname) ? '' : $data->$activityname);
        } else {
            $activitynum = $data->$name;
        }
        $sectionnum   = (empty($data->coursesectionnum) ? 0 : $data->coursesectionnum);
        $sectionname  = (empty($data->coursesectionname) ? '' : $data->coursesectionname);

        if ($activitynum) {

            if ($activitynum==self::CREATE_NEW) {

                // get modname and default values
                if (isset($data->modname)) {
                    $modname = $data->modname;
                    $defaultvalues = $data;
                } else {
                    $modname = $this->modulename;
                    $defaultvalues = $this->get_defaultvalues($data);
                }
                $modnametext = get_string('pluginname', $modname);

                // if activityname is empty, try to set it from the template (data2workshop)
                if ($activityname=='' && method_exists($this, 'get_template')) {
                    if ($template = $this->get_template($data)) {
                        $activityname = $template->name;
                    }
                }

                // if activityname is empty, set it to the default
                if ($activityname=='') {
                    if ($this->defaultname=='') {
                        $activityname = $this->instance->get_string('pluginname', $this->modulename);
                    } else {
                        // get default name without empty brackets (single byte, or double byte)
                        $activityname = $this->instance->get_string($this->defaultname, $this->plugin, $a);
                        $activityname = preg_replace('/\s*((\(\))|(\x{FF08}\x{FF09}))/u', '', $activityname);
                    }
                }

                // if we cannot reuse names, ensure that name is unique within this course
                if (empty($data->reusename)) {
                    $i = 1;
                    $sql = 'SELECT cm.id, x.name '.
                           'FROM {course_modules} cm, '.
                                '{modules} m, '.
                                '{'.$modname.'} x '.
                           'WHERE cm.course = :courseid AND '.
                                 'cm.module = m.id AND '.
                                 'm.name = :modname AND '.
                                 'cm.instance = x.id AND '.
                                 'x.name = :activityname';
                    $params = array('courseid' => $this->course->id,
                                    'modname' => $modname,
                                    'activityname' => $activityname);
                    if (property_exists('cm_info', 'deletioninprogress')) {
                        // Moodle >= 3.2
                        $sql .= ' AND cm.deletioninprogress = :deletioninprogress';
                        $params['deletioninprogress'] = 0;
                    }
                    while ($DB->record_exists_sql($sql, $params)) {
                        $i++;
                        $params['activityname'] = "$activityname ($i)";
                    }
                    $activityname = $params['activityname'];
                }

                $activitynametext = block_maj_submissions::filter_text($activityname);
                $activitynametext = strip_tags($activitynametext);

                // create new section, if required
                if ($sectionnum==self::CREATE_NEW) {
                    $section = self::get_section($msg, $this->plugin, $this->course, $sectionname);
                } else {
                    $section = get_fast_modinfo($this->course)->get_section_info($sectionnum);
                }

                if ($section) {
                    if (empty($data->aftermod)) {
                        $aftermod = null;
                    } else {
                        $aftermod = $data->aftermod;
                    }
                    $cm = self::get_coursemodule($this->course, $section, $modname, $activityname, $defaultvalues, $aftermod);

                    // setup parameters for "get_string()"
                    $a = (object)array('type' => $modnametext,
                                       'name' => $activitynametext);
                    if ($cm) {
                        if ($this->type) {
                            if ($this->cmid==0) {
                                $this->cmid = $cm->id;
                                $cmid = $this->type.'cmid';
                                $this->instance->config->$cmid = $cm->id;
                                $this->instance->instance_config_save($this->instance->config);
                            }
                        }
                        // create link to new module
                        if ($modname != 'label') {
                            $url = new moodle_url("/mod/$modname/view.php", array('id' => $cm->id));
                            $a->name = html_writer::link($url, $a->name, array('target' => 'MAJ'));
                        }
                        $msg[] = get_string('newmodcreated', $this->plugin, $a);
                    } else {
                        $msg[] = get_string('newmodfailed', $this->plugin, $a);
                    }
                }
            } else {
                $cm = get_fast_modinfo($this->course)->get_cm($activitynum);
            }

            if ($cm) {
                $permissions = $this->get_permissions($data);
                self::set_cm_permissions($cm, $permissions);

                $restrictions = $this->get_restrictions($data);
                self::set_cm_restrictions($cm, $restrictions);
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
     * get_dataid
     *
     * @param $name of field in database activity
     * @return integer id of database activity
     */
    protected function get_dataid($name, $formonly=false) {
        if ($name) {
            $cmid = optional_param($name, 0, PARAM_INT);
        } else {
            $cmid = 0;
        }
        if ($cmid==0 && $formonly==false) {
            $cmid = $this->instance->config->collectpresentationscmid;
        }
        if ($cmid==0) {
            return 0;
        }
        return get_fast_modinfo($this->course)->get_cm($cmid)->instance;
    }

    /**
     * profile_link
     */
    protected function profile_link($userid) {
        $url = new moodle_url('/user/profile.php', array('id' => $userid));
        return html_writer::link($url, $this->fullname($userid), array('target' => 'MAJ'));
    }

    /**
     * get_fullname
     */
    protected function fullname($userid) {
        if (class_exists('core_user')) {
            // Moodle >= 2.6
            $user = core_user::get_user($userid);
        } else {
            // Moodle <= 2.5
            $user = $DB->get_record('user', array('id' => $userid));
        }
        if (empty($user)) {
            if (get_string_manager()->string_exists('invaliduserid', 'notes')) {
                // Moodle >= 3.0 (Invalid user id)
                return get_string('invaliduserid', 'error').": $userid";
            }
            // Moodle >= 2.0 (No such user!)
            return get_string('nousers', 'error')." id=$userid";
        }
        return fullname($user);
    }

    /**
     * get_menufield_options
     *
     * @uses $DB
     * @param $dataid id of database activity instance
     * @param $name of field in database activity
     * @return array of database fieldnames
     */
    public function get_menufield_options($dataid, $name, $numerickeys=false) {
        global $DB;
        $options = array();
        $params = array('dataid' => $dataid, 'name' => $name);
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
                if (strpos($option, 'multilang')===false) {
                    // no multilang spans, so extract double/single byte string
                    $options[$option] = preg_replace($search, $replace, $option);
                } else {
                    // reduce multilang spans to a single string
                    $options[$option] = format_string($option);
                }
            }
        }
        if ($numerickeys) {
           $options = array_keys($options);
        }
        return $options;
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
     * If string contains multilang spans, return TRUE; otherwise, return FALSE.
     */
    static public function has_multilang_spans($str) {
        return preg_match('/<span[^>]*class="multilang"[^>]*>/is', $str);
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
        return '/^([^'.$chars.']*[^'.$ascii.']) *(['.$ascii.']+?)$/mu';
    }

    /**
     * Return a regexp string to match string made up of
     * non-ascii chars at the start and ascii chars at the end.
     */
    static public function convert_to_multilang($text, $config) {
        if (empty($config->displaylangs)) {
            $langs = '';
        } else {
            $langs = $config->displaylangs;
        }
        $langs = block_maj_submissions::get_languages($langs);
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
     * @param array $msg
     * @param string $plugin
     * @param object $course
     * @param string $sectionname
     * @return object
     * @todo Finish documenting this function
     */
    static public function get_section(&$msg, $plugin, $course, $sectionname) {
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
                'timemodified' => time(),
            );
            if ($section->id = $DB->insert_record('course_sections', $section, $sectionname)) {
                $url = block_maj_submissions::get_sectionlink($course->id, $sectionnum);
                $msg[] = get_string('newsectioncreated', $plugin, html_writer::link($url, $sectionname));
            }
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
     * @param integer $aftermod
     * @return object
     * @todo Finish documenting this function
     */
    static public function get_coursemodule($course, $section, $modulename, $instancename, $defaultvalues, $aftermod) {
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
        $params = array($moduleid, $course->id, $section->section, $instancename, 0);
        if (property_exists('cm_info', 'deletioninprogress')) {
            // Moodle >= 3.2
            $where .= ' AND deletioninprogress = ?';
            $params[] = 0;
        }
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
            'modname'       => $modulename,
            'modulename'    => $modulename,
            'add'           => $modulename,
            'visible'       => 1,
            'visibleonpage' => 1,
            'visibleonold'  => 1,
            'update'        => 0,
            'return'        => 0,
            'cmidnumber'    => '',
            'groupmode'     => 0, // no groups
            'groupingid'    => 0, // no grouping
            'MAX_FILE_SIZE' => 10485760, // 10 GB
        );

        foreach ($defaultvalues as $column => $value) {
            $newrecord->$column = $value;
        }

        // If the section is hidden, we should also hide the new instance
        // but we retain the visibility in the "visibleold" field,
        // so that it can be restored when the section is unhidden.
        if (empty($newrecord->visible)) {
            $newrecord->visible = 0;
            $newrecord->visibleold = 0;
        } else {
            $newrecord->visibleold = $newrecord->visible;
        }
        if (empty($section->visible)) {
            $section->visible = 0;
            $newrecord->visible = 0;
        } else {
            $newrecord->visible = $section->visible;
        }

        // add default values
        $columns = $DB->get_columns($modulename);
        foreach ($columns as $column) {
            $name = $column->name;
            if (isset($newrecord->$name) || $name=='id') {
                // do nothing
            } else if ($column->not_null) {
                if (isset($column->default_value)) {
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

        $beforemod = null;
        if ($aftermod) {
            if (is_object($aftermod)) {
                $aftermod = $aftermod->id;
            }
            $sequence = explode(',', $section->sequence);
            $sequence = array_filter($sequence);
            $i = array_search($aftermod, $sequence);
            if (is_numeric($i) && array_key_exists($i + 1, $sequence)) {
                $beforemod = $sequence[$i + 1];
            }
        }

        $newrecord->id = $newrecord->coursemodule;
        if (function_exists('course_add_cm_to_section')) {
            // Moodle >= 2.4
            $sectionid = course_add_cm_to_section($newrecord->course, $newrecord->id, $section->section, $beforemod);
        } else {
            // Moodle <= 2.3
            $sectionid = add_mod_to_section($newrecord, $beforemod);
        }
        if (! $sectionid) {
            throw new exception('Could not add the new course module to section: '.$newrecord->section);
        }
        if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $newrecord->id))) {
            throw new exception('Could not update the course module with the correct section');
        }

        if (class_exists('\\core\\event\\course_module_created')) {
            // Moodle >= 2.6
            \core\event\course_module_created::create_from_cm($newrecord)->trigger();
        } else {
            // Trigger mod_updated event with information about this module.
            $event = (object)array(
                'courseid'   => $newrecord->course,
                'cmid'       => $newrecord->id,
                'modulename' => $newrecord->modulename,
                'name'       => $newrecord->name,
                'userid'     => $USER->id
            );
            if (function_exists('events_trigger_legacy')) {
                // Moodle 2.6 - 3.0 ... so not used here anymore
                events_trigger_legacy('mod_created', $event);
            } else {
                // Moodle <= 2.5
                events_trigger('mod_created', $event);
            }
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
     * @param course module object
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
            $table = 'course_modules';

            if (is_numeric($cm)) {
                $cm = $DB->get_record($table, array('id' => $cm));
            }
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

            $structure = self::merge_restrictions($cm, $structure, $restrictions);

            // encode availability $structure
            if (empty($structure->c)) {
                $availability = null;
            } else {
                $availability = json_encode($structure);
            }

            // update availability in database
            if ($cm->availability==$availability) {
                // do nothing
            } else {
                $DB->set_field($table, 'availability', $availability, array('id' => $cm->id));
                rebuild_course_cache($cm->course);
            }
        }
    }

    /**
     * set access restrctions (=availability) on a newly created $section
     *
     * @param course module object
     * @param array of stdClass $availability (decoded from JSON)
     * @todo Finish documenting this function
     */
    static public function set_section_restrictions($section, $restrictions) {
        global $DB;

        if (class_exists('\\core_availability\\info_section')) {
            // Moodle >= 2.7
            $table = 'course_sections';

            if (is_numeric($section)) {
                $section = $DB->get_record($table, array('id' => $section));
            }
            if ($section instanceof stdClass) {
                $modinfo = get_fast_modinfo($section->course);
                $section = new section_info($section, $section->section, null, null, $modinfo, null);
            }

            // get current availability structure for this $section
            if (empty($section->availability)) {
                $structure = null;
            } else {
                $info = new \core_availability\info_section($section);
                $tree = $info->get_availability_tree();
                $structure = $tree->save();
            }

            // merge restrictions
            $structure = self::merge_restrictions($section, $structure, $restrictions);

            // encode availability $structure
            if (empty($structure->c)) {
                $availability = null;
            } else {
                $availability = json_encode($structure);
            }

            // update availability in database
            if ($section->availability == $availability) {
                // do nothing
            } else {
                $DB->set_field($table, 'availability', $availability, array('id' => $section->id));
                rebuild_course_cache($section->course);
            }
        }
    }

    /**
     * merge_restrictions
     *
     * @param object $object 
     * @param object $structure from the $section or $cm
     * @param array of stdClass() $restrictions
     */
    static public function merge_restrictions($object, $structure, $restrictions) {
        global $DB;

        if (empty($object)) {
            return false;
        }

        if (empty($structure)) {
            $structure = new stdClass();
        }

        // $restrictions->op takes preference over $structure->op
        if (isset($restrictions) && isset($restrictions->op)) {
            $structure->op = $restrictions->op;
        }

        if (! isset($structure->op)) {
            $structure->op = '|';
        }
        if (! isset($structure->c)) {
            $structure->c = array();
        }
        if ($structure->op == '|' || $structure->op == '!&') {
            if (! isset($structure->show)) {
                $structure->show = false;
            }
            unset($structure->showc);
        }
        if ($structure->op == '&' || $structure->op == '!|') {
            if (! isset($structure->showc)) {
                $structure->showc = array_fill(0, count($structure->c), false);
            }
            unset($structure->show);
        }

        // remove conditions in $structure that refer to groups,
        // groupings, activities or grade items in another course
        for ($i = (count($structure->c) - 1); $i >= 0; $i--) {
            $old = $structure->c[$i];
            if (isset($old->type)) {
                switch ($old->type) {
                    case 'completion':
                        $table = 'course_modules';
                        $params = array('id' => $old->cm, 'course' => $object->course);
                        break;
                    case 'grade':
                        $table = 'grade_items';
                        $params = array('id' => $old->id, 'courseid' => $object->course);
                        break;
                    case 'group':
                        $table = 'groups';
                        $params = array('id' => $old->id, 'courseid' => $object->course);
                        break;
                    case 'grouping':
                        $table = 'groupings';
                        $params = array('id' => $old->id, 'courseid' => $object->course);
                        break;
                    case 'role': // 3rd-party plugin
                        $table = 'role';
                        $params = array('id' => $old->id);
                        break;
                    default:
                        $table = '';
                        $params = array();
                }
                if ($table == '' || $DB->record_exists($table, $params)) {
                    // $params are valid - do nothing
                } else {
                    // remove this condition
                    array_splice($structure->c, $i, 1);
                    if (isset($structure->showc)) {
                        array_splice($structure->showc, $i, 1);
                    }
                }
            } else if (isset($old->op) && isset($old->c)) {
                // a subset of restrictions
            }
        }

        // add new $restrictions if they do not exist in $structure
        foreach ($restrictions->c as $i => $new) {
            $missing = true;
            foreach ($structure->c as $old) {
                $params = false;
                if ($old->type == $new->type) {
                    switch ($old->type) {
                        case 'completion': $params = array('cm', 'e');          break;
                        case 'date':       $params = array('d',  't');          break;
                        case 'grade':      $params = array('id', 'min', 'max'); break;
                        case 'group':      $params = array('id');               break;
                        case 'grouping':   $params = array('id');               break;
                        case 'profile':    $params = array('sf', 'op', 'v');    break;
                        case 'role':       $params = array('id');               break;
                    }
                }
                if ($params) {
                    $missing = false;
                    foreach ($params as $param) {
                        if (isset($old->$param) && isset($new->$param) && $old->$param==$new->$param) {
                            // do nothing
                        } else {
                            $missing = true; // $param doesn't match on $old and $new condition
                        }
                    }
                }
                if (! $missing) {
                    break; // this restriction already exists
                }
            }
            if ($missing) {
                array_push($structure->c, $new);
                if (isset($structure->showc)) {
                    if (isset($restrictions->showc)) {
                        $structure->showc[] = $restrictions->showc[$i];
                    } else if (isset($restrictions->show)) {
                        $structure->showc[] = $restrictions->show;;
                    } else {
                        $structure->showc[] = false;
                    }
                }
            }
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
                        if (property_exists($cm, 'deletioninprogress') && $cm->deletioninprogress) {
                            // Moodle >= 3.2: activity is waiting to be deleted
                        } else if ($modcount==0 || in_array($cm->modname, $modnames)) {
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
