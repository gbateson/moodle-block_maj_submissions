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

// disable direct access to this block
defined('MOODLE_INTERNAL') || die();

// get required files
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * block_maj_submissions_tool
 *
 * @copyright 2014 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool extends moodleform {

    const CREATE_NEW = -1;
    const TEXT_FIELD_SIZE = 20;

    protected $type = '';
    protected $modulename = 'data';

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {

        // extract the custom data passed from the main script
        $this->course  = $customdata['course'];
        $this->plugin  = $customdata['plugin'];
        $this->instance = $customdata['instance'];

        // convert block instance to "block_maj_submissions" object
        $this->instance = block_instance($this->instance->blockname, $this->instance);

        // set the "course_module" id, if supplied
        $this->cmid = $this->instance->config->{$this->type.'cmid'};

        // call parent constructor, as normal
        if (method_exists('moodleform', '__construct')) {
            parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
        } else {
            parent::moodleform($action, $customdata, $method, $target, $attributes, $editable);
        }
    }

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

        // --------------------------------------------------------
        $name = 'databaseactivity';
        $label = get_string($name, $this->plugin);
        $mform->addElement('header', $name, $label);
        // --------------------------------------------------------

        $this->add_field_cm($mform, $this->course, $this->plugin, 'databaseactivity', $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $sectionnum);

        // --------------------------------------------------------
        $label = get_string('fromfile', 'data');
        $mform->addElement('header', 'uploadpreset', $label);
        // --------------------------------------------------------

        $label = get_string('chooseorupload', 'data');
        $mform->addElement('filepicker', 'uploadfile', $label);

        $presets = self::get_available_presets($context, $this->plugin, $this->cmid);
        if (count($presets)) {

            // --------------------------------------------------------
            $label = get_string('usestandard', 'data');
            $mform->addElement('header', 'presets', $label);
            // --------------------------------------------------------

            foreach ($presets as $preset) {
                $label = ' '.$preset->description;
                $mform->addElement('radio', 'presetname', null, $label, "$preset->userid/$preset->shortname");
            }
        }

        $this->add_action_buttons();
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
        $label = get_string($this->type.'cmid', $this->plugin);
        $numoptions = self::get_cmids($mform, $course, $plugin, $this->modulename);
        $disabledif = array($name.'name' => $name.'num');
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
    protected function add_field_section($mform, $course, $plugin, $name, $numdefault=0, $namedefault='', $label='') {
        $numoptions = self::get_sectionnums($mform, $course, $plugin);
        $disabledif = array($name.'name' => $name.'num', $name.'num' => 'databaseactivitynum');
        $this->add_field_numname($mform, $plugin, $name, $numoptions, array(), $numdefault, $namedefault, $label, $disabledif);
    }

    /**
     * add_field_section
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
     * data_postprocessing
     *
     * @uses $DB
     * @param object $data
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function data_postprocessing(&$data) {
        global $DB;

        $databasenum = $data->databaseactivitynum;
        $databasename = $data->databaseactivityname;
        $sectionnum = $data->coursesectionnum;
        $sectionname = $data->coursesectionname;

        if (empty($databasenum)) {
            $cm = null;
            $section = null;
        } else if ($databasenum==self::CREATE_NEW) {
            if ($sectionnum==self::CREATE_NEW) {
                $section = self::get_section($this->course, $sectionname);
            }
            $cm = self::get_coursemodule($this->course, $section, 'data', $databasename);
        } else {
            $cm = get_fast_modinfo($this->course)->get_cm($databasenum);
            $section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $sectionnum));
        }
        //print_object($cm);
        //print_object($section);
        //print_object($data);
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
    public function get_section($course, $sectionname) {
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
     * @return object
     * @todo Finish documenting this function
     */
    public function get_coursemodule($course, $section, $modulename, $instancename) {
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
        $where  = 'cs.section = ? AND x.name = ? AND cm.visible = ?';
        $params = array($moduleid, $section->section, $instancename, 1);
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
            'groupmode'     => 0,
            'MAX_FILE_SIZE' => 10485760, // 10 GB

        );

        // add default values
        $columns = $DB->get_columns($modulename);
        foreach ($columns as $column) {
            if ($column->not_null) {
                $name = $column->name;
                if ($name=='id') {
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
            $sectionid = course_add_cm_to_section($course->id, $newrecord->coursemodule, $section->section);
        } else {
            // Moodle <= 2.3
            $sectionid = add_mod_to_section($newrecord);
        }
        if (! $sectionid) {
            throw new exception('Could not add the new course module to that section');
        }
        if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $newrecord->coursemodule))) {
            throw new exception('Could not update the course module with the correct section');
        }

        // if the section is hidden, we should also hide the new instance
        if (! isset($newrecord->visible)) {
            $newrecord->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
        }
        set_coursemodule_visible($newrecord->coursemodule, $newrecord->visible);

        // Trigger mod_updated event with information about this module.
        $event = (object)array(
            'courseid'   => $newrecord->course,
            'cmid'       => $newrecord->coursemodule,
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
        rebuild_course_cache($course->id, true);

        return $newrecord;
    }

    /**
     * get_available_presets
     *
     * @uses $DB
     * @uses $OUTPUT
     * @param object $context
     * @return integer $cmid
     */
    static public function get_available_presets($context, $plugin, $cmid) {
        global $CFG, $DB, $OUTPUT;

        $strman = get_string_manager();
        $strdelete = get_string('deleted', 'data');

        require_once($CFG->dirroot.'/mod/data/lib.php');
        $presets = data_get_available_presets($context);

        $dir = $CFG->dirroot.'/blocks/maj_submissions/presets';
        if (is_dir($dir) && ($dh = opendir($dir))) {
            while (($item = readdir($dh)) !== false) {
                if (substr($item, 0, 1)=='.') {
                    continue; // a hidden item
                }
                $diritem = "$dir/$item";
                if (is_dir($diritem) && is_directory_a_preset($diritem)) {
                    if ($strman->string_exists('presetfullname'.$item, $plugin)) {
                        $fullname = get_string('presetfullname'.$item, $plugin);
                    } else {
                        $fullname = $item;
                    }
                    if ($strman->string_exists('presetshortname'.$item, $plugin)) {
                        $shortname = get_string('presetshortname'.$item, $plugin);
                    } else {
                        $shortname = $item;
                    }
                    if (file_exists("$diritem/screenshot.jpg")) {
                        $screenshot = "$diritem/screenshot.jpg";
                    } else if (file_exists("$diritem/screenshot.png")) {
                        $screenshot = "$diritem/screenshot.png";
                    } else if (file_exists("$diritem/screenshot.gif")) {
                        $screenshot = "$diritem/screenshot.gif";
                    } else {
                        $screenshot = ''; // shouldn't happen !!
                    }
                    $presets[] = (object)array(
                        'userid' => 0,
                        'path' => $diritem,
                        'name' => $fullname,
                        'shortname' => $shortname,
                        'screenshot' => $screenshot
                    );
                }
            }
            closedir($dh);
        }

        foreach ($presets as &$preset) {

            $user_can_delete_preset = data_user_can_delete_preset($context, $preset);

            if (empty($preset->userid)) {
                $preset->userid = 0;
                $preset->description = $preset->name;
                if ($preset->name=='Image gallery') {
                    $user_can_delete_preset = false;
                }
            } else {
                $fields = get_all_user_name_fields(true);
                $params = array('id' => $preset->userid);
                $user = $DB->get_record('user', $params, "id, $fields", MUST_EXIST);
                $preset->description = $preset->name.' ('.fullname($user, true).')';
            }

            if ($user_can_delete_preset) {
                $params = array('id'       => $cmid,
                                'action'   => 'confirmdelete',
                                'fullname' => "$preset->userid/$preset->shortname",
                                'sesskey'  => sesskey());
                $url = new moodle_url('/mod/data/preset.php', $params);
                $params = array('src'   => $OUTPUT->pix_url('t/delete'),
                                'class' => 'iconsmall',
                                'alt'   => "$strdelete $preset->description");
                $icon = html_writer::empty_tag('img', $params);
                $preset->description .= html_writer::link($url, $icon);
            }
        }
        return $presets;
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
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
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
     * @param string  $modnames (optional, default="")
     * @param integer $sectionnum (optional, default=0)
     * @return array($cmid => $name) of activities from the specified $sectionnum
     *                               or from the whole course (if $sectionnum==0)
     */
    static public function get_cmids($mform, $course, $plugin, $modnames='', $sectionnum=0) {
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
        return self::format_selectgroups_options($plugin, $options, 'activity');
    }

    /**
     * format_selectgroups_options
     *
     * @param string  $plugin
     * @param array   $options
     * @param string  $type ("field", "activity" or "section")
     * @return array  $option for a select element in $mform
     */
    static public function format_selectgroups_options($plugin, $options, $type) {
        return $options + array('-----' => self::format_select_options($plugin, array(), $type));
    }

    /**
     * format_select_options
     *
     * @param string  $plugin
     * @param array   $options
     * @param string  $type ("field", "activity" or "section")
     * @return array  $option for a select element in $mform
     */
    static public function format_select_options($plugin, $options, $type) {
        if (! array_key_exists(0, $options)) {
            $none = get_string('none');
            $options = array(0 => "($none)") + $options;
        }
        $createnew = get_string('createnew'.$type, $plugin);
        return $options + array(self::CREATE_NEW => "($createnew)");
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

class block_maj_submissions_tool_setupregistrations extends block_maj_submissions_tool {
    protected $type = 'registerdelegates';
}
class block_maj_submissions_tool_setuppresentations extends block_maj_submissions_tool {
    protected $type = 'collectpresentations';
}
class block_maj_submissions_tool_setupworkshops extends block_maj_submissions_tool {
    protected $type = 'collectworkshops';
}