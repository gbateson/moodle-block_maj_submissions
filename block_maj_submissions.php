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

// get parent class, "block_base"
require_once($CFG->dirroot.'/blocks/moodleblock.class.php');

/**
 * block_maj_submissions
 *
 * @copyright 2014 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions extends block_base {

    const REMOVE_NONE  = 0;
    const REMOVE_DAY   = 1;
    const REMOVE_MONTH = 2;
    const REMOVE_YEAR  = 4;

    const MAX_NAME_LENGTH = 30;

    protected $fixyearchar  = false;
    protected $fixmonthchar = false;
    protected $fixdaychar   = false;
    protected $multilang    = false;
    protected $dateformat   = null;

    protected $requiredfields = array();

    /**
     * init
     */
    function init() {
        $this->title = get_string('blockname', 'block_maj_submissions');
        $this->version = 2016090100;
    }

    /**
     * hide_header
     *
     * @return xxx
     */
    function hide_header() {
        return empty($this->config->title);
    }

    /**
     * applicable_formats
     *
     * @return xxx
     */
    function applicable_formats() {
        return array('course' => true);
    }

    /**
     * instance_allow_config
     *
     * @return xxx
     */
    function instance_allow_config() {
        return true;
    }

    /**
     * specialization
     */
    function specialization() {
        $plugin = 'block_maj_submissions';
        $defaults = array(

            'title' => get_string('blockname', $plugin),
            'displaylinks' => 1, // 0=no, 1=yes
            'displaydates' => 1, // 0=no, 1=yes
            'displaystats' => 1, // 0=no, 1=yes
            'displaylangs' => implode(',', self::get_languages()),

            // database CONSTANT fields
            'conferencename'  => '',
            'conferencevenue' => '',
            'conferencedates' => '',
            'dinnername'      => '',
            'dinnervenue'     => '',
            'dinnerdate'      => '',
            'dinnertime'      => '',
            'certificatedate' => '',

            // database CONSTANT (auto-increment) fields
            'badgenumber'               => 0,
            'badgenumberformat'         => '%04d',
            'feereceiptnumber'          => 0,
            'feereceiptnumberformat'    => '%04d',
            'dinnerreceiptnumber'       => 0,
            'dinnerreceiptnumberformat' => '%04d',
            'dinnerticketnumber'        => 0,
            'dinnerticketnumberformat'  => '%04d',
            'certificatenumber'         => 0,
            'certificatenumberformat'   => '%04d',

            // conference events
            'conferencetimestart'  => 0,
            'conferencetimefinish' => 0,
            'conferencecmid'       => 0,

            'workshopstimestart'   => 0,
            'workshopstimefinish'  => 0,
            'workshopscmid'        => 0,

            'receptiontimestart'   => 0,
            'receptiontimefinish'  => 0,
            'receptioncmid'        => 0,

            'registereventscmid'   => 0,

            'collectpresentationstimestart'  => 0,
            'collectpresentationstimefinish' => 0,
            'collectpresentationscmid'       => 0,

            'collectworkshopstimestart'  => 0,
            'collectworkshopstimefinish' => 0,
            'collectworkshopscmid'       => 0,

            'collectsponsoredstimestart'  => 0,
            'collectsponsoredstimefinish' => 0,
            'collectsponsoredscmid'       => 0,

            'reviewtimestart'  => 0,
            'reviewtimefinish' => 0,
            'reviewsectionnum' => 0,

            'revisetimestart'  => 0,
            'revisetimefinish' => 0,
            'revisesectionnum' => 0,

            'publishtimestart'  => 0,
            'publishtimefinish' => 0,
            'publishcmid'       => 0,

            'registerpresenterstimestart'  => 0,
            'registerpresenterstimefinish' => 0,
            'registerpresenterscmid'       => 0,

            'registerdelegatestimestart'  => 0,
            'registerdelegatestimefinish' => 0,
            'registerdelegatescmid'       => 0,

            'registerearlytimestart'  => 0,
            'registerearlytimefinish' => 0,

            // date settings
            'moodledatefmt' => 'strftimerecent', // 11 Nov, 10:12
            'customdatefmt' => '%b %d (%a) %H:%M', // Nov 11th (Wed) 10:12
            'removeyear'    => 0, // 0=no, 1=yes remove current year from dates
            'fixmonth'      => 1, // 0=no, 1=remove leading "0" from months
            'fixday'        => 1, // 0=no, 1=remove leading "0" from days
            'fixhour'       => 1, // 0=no, 1=remove leading "0" from hours
            'manageevents'  => 0  // 0=no, 1=yes (i.e. sync calendar events)
        );

        if (empty($this->config)) {
            $this->config = new stdClass();
        }
        if (get_class($this->config)=='__PHP_Incomplete_Class') {
            $this->config = get_object_vars($this->config);
            $this->config = (object)$this->config;
            unset($this->config->__PHP_Incomplete_Class_Name);
        }
        foreach ($defaults as $name => $value) {
            if (! isset($this->config->$name)) {
                $this->config->$name = $value;
            }
        }

        $this->check_date_fixes();

        // load user-defined title (may be empty)
        $this->title = $this->config->title;
    }

    /**
     * instance_config_save
     *
     * @param xxx $config
     * @param xxx $pinned (optional, default=false)
     * @return xxx
     */
    function instance_config_save($config, $pinned=false) {
        global $DB;

        // do nothing if user hit the "cancel" button
        if (optional_param('cancel', 0, PARAM_INT)) {
            return true;
        }

        $plugin = 'block_maj_submissions';

        /////////////////////////////////////////////////
        // update CONSTANT fields, if required
        /////////////////////////////////////////////////

        $course = $this->page->course;
        $courseid = $course->id;
        $moduleid = $DB->get_field('modules', 'id', array('name' => 'data'));

        // retrieve the files from the filemanager
        $name = 'files';
        $options = self::get_fileoptions();
        file_postupdate_standard_filemanager($config, $name, $options, $this->context, $plugin, $name, 0);

        $dataids = array();
        $names = array_keys(get_object_vars($config));
        $names = preg_grep('/^(collect|register).*cmid$/', $names);
        // we expect the following settings:
        // - collect(presentations|sponsoreds|workshops)cmid
        // - register(delegates|presenters)cmid

        foreach ($names as $name) {
            if (! empty($config->$name)) {
                $params = array('id' => $config->$name, 'course' => $courseid, 'module' => $moduleid);
                $dataids[] = $DB->get_field('course_modules', 'instance', $params);
            }
        }

        $dataids = array_filter($dataids);
        $dataids = array_unique($dataids);

        if (count($dataids)) {

            // cache langs
            if (empty($config->displaylangs)) {
                $langs = self::get_languages();
            } else {
                $langs = self::get_languages($config->displaylangs);
            }

            // constant values (type = 0)
            $constanttype = 0; // constant fields
            $fieldnames = self::get_constant_fieldnames($constanttype);
            foreach ($fieldnames as $fieldname => $name) {
                foreach ($langs as $lang) {
                    if ($lang=='en') {
                        // update fields which have no separate "_$lang" values
                        $this->update_constant_field($plugin, $dataids, $config, $name, $name.$lang, $fieldname, $constanttype);
                    }
                    $this->update_constant_field($plugin, $dataids, $config, $name, $name.$lang, $fieldname."_$lang", $constanttype);
                }
            }

            // autoincrement values (type = 1)
            $constanttype = 1;
            $fieldnames = self::get_constant_fieldnames($constanttype);
            foreach ($fieldnames as $fieldname => $name) {
                $this->update_constant_field($plugin, $dataids, $config, $name, $name, $fieldname, $constanttype);
            }
        }

        /////////////////////////////////////////////////
        // update calendar events, if required
        /////////////////////////////////////////////////

        if ($config->manageevents) {
            $events = array();

            $modinfo = get_fast_modinfo($course);
            foreach (self::get_timetypes() as $types) {
                foreach ($types as $type) {

                    // set up $cmid, $modname, $instance and $visible
                    $cmid        = '';
                    $modname     = '';
                    $instance    =  0;
                    $visible     =  1;
                    $description = '';
                    switch ($type) {
                        case 'conference':
                        case 'workshops':
                        case 'reception':
                        case 'collectpresentations':
                        case 'collectworkshops':
                        case 'collectsponsoreds':
                        case 'publish':
                        case 'registerpresenters':
                        case 'registerdelegates':
                            $cmid = $type.'cmid';
                            break;
                    }
                    if ($cmid && isset($config->$cmid)) {
                        $cmid = $config->$cmid;
                        if (is_numeric($cmid) && $cmid > 0 && isset($modinfo->cms[$cmid])) {
                            $modname  = $modinfo->get_cm($cmid)->modname;
                            $instance = $modinfo->get_cm($cmid)->instance;
                            $visible  = $modinfo->get_cm($cmid)->visible;
                            $description = $modinfo->get_cm($cmid)->name;
                        }
                    }

                    // set start and finish time
                    $timestart = $type.'timestart';
                    $timefinish = $type.'timefinish';

                    // set duration
                    if ($config->$timefinish && $config->$start) {
                        $duration = ($config->$timefinish - $config->$start);
                    } else {
                        $duration = 0;
                    }

                    // add event(s)
                    if ($duration > 0 && $duration < DAYSECS) {
                            $events[] = $this->create_event($name, $description, 'open', $config->$timestart, $duration, $modname, $instance);
                    } else {
                        if ($config->$timestart) {
                            $name = $this->get_string('timestart', $plugin);
                            $name = $this->get_string($type.'time', $plugin)." ($name)";
                            $events[] = $this->create_event($name, $description, 'open', $config->$timestart, 0, $modname, $instance);
                        }
                        if ($config->$timefinish) {
                            $name = $this->get_string('timefinish', $plugin);
                            $name = $this->get_string($type.'time', $plugin)." ($name)";
                            $events[] = $this->create_event($name, $description, 'close', $config->$timefinish, 0, $modname, $instance);
                        }
                    }
                }
            }

            if (count($events)) {
                $this->add_events($events, $course, $plugin);
            }
        }

        /////////////////////////////////////////////////
        //  save config settings as usual
        /////////////////////////////////////////////////

        return parent::instance_config_save($config, $pinned);
    }

    /**
     * Ensure file area is cleared when removing an instance of this block.
     *
     * @return boolean
     */
    function instance_delete() {
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_maj_submisisons');
        return parent::instance_delete();
    }

    /**
     * Copy any block-specific data when copying to a new block instance.
     * @param int $fromid the id number of the block instance to copy from
     * @return boolean
     */
    public function instance_copy($fromid) {
        $fromcontext = self::context(CONTEXT_BLOCK, $fromid);
        $component = 'block_maj_submissions';
        $filearea = 'files';
        $fs = get_file_storage();
        if ($fs->is_area_empty($fromcontext->id, $component, $filearea, 0, false)) {
            // do nothing - saves several SQL queries
        } else {
            $draftitemid = 0;
            $options = self::get_fileoptions();
            file_prepare_draft_area($draftitemid, $fromcontext->id, $component, $filearea, 0, $options);
            file_save_draft_area_files($draftitemid, $this->context->id, $component, $filearea, 0, $options);
        }
        return parent::instance_copy($fromid);
    }

    /**
     * update_constant_field
     *
     * @param string $plugin
     * @param array  $dataids
     * @param object $config values from recently submitted form
     * @param string $name the base name of this config setting, e.g. conferencename
     * @param string $configname the name of the setting in the form, e.g. conferencenameen
     * @param string $fieldname the name of the setting in the database, e.g. conference_name_en
     * @param string $constanttype 0=constant, 1=autoincrement, 2=random
     * @return xxx
     */
    protected function update_constant_field($plugin, $dataids, $config, $name, $configname, $fieldname, $constanttype) {
        global $DB;
        if (isset($config->$configname)) {
            $param1 = $config->$configname;
            $param2 = $constanttype;
            $param3 = ($constanttype==1 ? $config->{$configname.'format'} : '');
            if (substr($configname, -4)=='cmid' && substr($fieldname)=='_url') {
                $is_url = true;
            } else {
                $is_url = false;
            }
            foreach ($dataids as $dataid) {
                if ($is_url) {
                    $param1 = array('id' => $config->$configname);
                    $param1 = new moodle_url('mod/page/view.php', $param1);
                }
                $params = array('dataid' => $dataid,
                                'type' => 'constant',
                                'name' => $fieldname);
                if ($field = $DB->get_record('data_fields', $params)) {
                    $field->param1 = $param1;
                    $field->param2 = $param2;
                    $field->param3 = $param3;
                    $DB->update_record('data_fields', $field);
                } else if ($param1 && self::is_required_constant($config, $dataid, $name)) {
                    $field = (object)array('dataid' => $dataid,
                                           'type'   => 'constant',
                                           'name'   => $fieldname,
                                           'description' => get_string($name, $plugin),
                                           'required' => 0,
                                           'param1' => $param1,
                                           'param2' => $param2,
                                           'param3' => $param3);
                    $field->id = $DB->insert_record('data_fields', $field);
                }
            }
        }
    }

    /**
     * get_content
     *
     * @return xxx
     */
    function get_content() {
        global $FULLME, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = (object)array(
            'text' => '',
            'footer' => ''
        );

        $plugin = 'block_maj_submissions';

        $dates = array();
        $countdates = 0;
        $formatdates = ($this->config->displaydates || $this->multilang);

        $links = array();
        $countlinks = 0;

        // get $dateformat
        $dateformat = self::get_date_format($this->config);
        $timenow = time();

        $course = $this->page->course;
        $courseid = $course->id;

        // cache $coursedisplay (single/multi page)
        if (isset($course->coursedisplay)) {
            $coursedisplay = $course->coursedisplay;
        } else if (function_exists('course_get_format')) {
            $coursedisplay = course_get_format($course);
            $coursedisplay = $coursedisplay->get_format_options();
            $coursedisplay = $coursedisplay['coursedisplay'];
        } else {
            $coursedisplay = COURSE_DISPLAY_SINGLEPAGE; // =0
        }

        // get info about sections and mods in this course
        $modinfo = get_fast_modinfo($course);

        // build menu of quick links to course sections
        if ($this->config->displaylinks) {
            $canviewhidden = 'moodle/course:viewhiddensections';
            $canviewhidden = has_capability($canviewhidden, $this->page->context);
            foreach ($modinfo->get_section_info_all() as $sectionnum => $section) {
                if ($sectionnum && ($section->visible || $canviewhidden)) {
                    if ($sectionname = self::get_sectionname($section)) {
                        $url = self::get_sectionlink($courseid, $sectionnum, $coursedisplay);
                        $sectionname = html_writer::link($url, $sectionname);
                        $linkclass = ($section->visible ? '' : 'dimmed_text');
                        $links[] = html_writer::tag('li', $sectionname, array('class' => $linkclass));
                    }
                }
            }
        }

        // build list of important dates
        foreach (self::get_timetypes() as $types) {

            // skip the divider, if $dates is still empty
            $skipdivider = empty($dates);

            foreach ($types as $type) {

                $timestart = $type.'timestart';
                $timefinish = $type.'timefinish';

                if ($this->config->$timestart || $this->config->$timefinish) {
                    $countdates++;
                }

                if ($formatdates) {

                    // set up $url from $cmid or $sectionnum
                    $url = '';
                    $cmid = '';
                    $sectionnum = '';
                    switch ($type) {
                        case 'conference':
                        case 'workshops':
                        case 'reception':
                        case 'collectpresentations':
                        case 'collectworkshops':
                        case 'collectsponsoreds':
                        case 'publish':
                        case 'registerpresenters':
                        case 'registerdelegates':
                            $cmid = $type.'cmid';
                            break;

                        case 'revise':
                        case 'review':
                            $sectionnum = $type.'sectionnum';
                            break;
                    }

                    if ($cmid && isset($this->config->$cmid)) {
                        $cmid = $this->config->$cmid;
                        if (is_numeric($cmid) && $cmid > 0 && isset($modinfo->cms[$cmid])) {
                            $modname = $modinfo->get_cm($cmid)->modname;
                            $url = new moodle_url("/mod/$modname/view.php", array('id' => $cmid));
                        }
                    }

                    if ($sectionnum && isset($this->config->$sectionnum)) {
                        $sectionnum = $this->config->$sectionnum;
                        if (is_numeric($sectionnum) && $sectionnum >= 0) { // 0 is allowed ;-)
                            $url = self::get_sectionlink($courseid, $sectionnum, $coursedisplay);
                        }
                    }

                    $date = $this->format_date_range($plugin, $dateformat, $timenow, $timestart, $timefinish);
                } else {
                    $date = '';
                }

                if ($date) {
                    $text = $this->get_string($type.'time', $plugin);
                    if ($url) {
                        $text = html_writer::tag('a', $text, array('href' => $url));
                    }
                    $date = html_writer::tag('b', $text).
                            html_writer::empty_tag('br').$date;
                            //html_writer::tag('span', $date);
                    if ($this->user_can_edit()) {
                        if ($stats = $this->format_stats($plugin, $modinfo, $type, $cmid, $sectionnum)) {
                            $date .= html_writer::tag('i', $stats);
                        }
                    }
                    $class = 'date';
                    switch (true) {
                        case ($timenow < $this->config->$timestart):  $class .= ' early'; break;
                        case ($timenow < $this->config->$timefinish): $class .= ' open';  break;
                        case ($this->config->$timefinish):            $class .= ' late';  break;
                    }
                    if ($timenow < $this->config->$timefinish) {
                        $timeremaining = ($this->config->$timefinish - $timenow);
                        switch (true) {
                            case ($timeremaining <= (1 * DAYSECS)): $class .= ' timeremaining1'; break;
                            case ($timeremaining <= (2 * DAYSECS)): $class .= ' timeremaining2'; break;
                            case ($timeremaining <= (3 * DAYSECS)): $class .= ' timeremaining3'; break;
                            case ($timeremaining <= (7 * DAYSECS)): $class .= ' timeremaining7'; break;
                        }
                    }
                    if ($skipdivider==false) {
                        $skipdivider = true;
                        $dates[] = html_writer::tag('li', '', array('class' => 'divider'));
                    }
                    $dates[] = html_writer::tag('li', $date, array('class' => $class));
                }
            }
        }

        // add quick links, if necessary
        if ($links = implode('', $links)) {
            $heading = $this->get_string('quicklinks', $plugin);
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'quicklinks'));
            $this->content->text .= html_writer::tag('ul', $links, array('class' => 'quicklinks'));
        }

        // add important dates, if necessary
        if ($dates = implode('', $dates)) {
            $heading = $this->get_string('importantdates', $plugin);
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'importantdates'));
            $this->content->text .= html_writer::tag('ul', $dates,   array('class' => 'importantdates'));
        }

        // add conference tools, if necessary
        if ($this->user_can_edit()) {
            $heading = $this->get_string('conferencetools', $plugin);
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'toollinks'));
            $this->content->text .= $this->get_tool_link($plugin, 'setupregistrations');
            $this->content->text .= $this->get_tool_link($plugin, 'setuppresentations');
            $this->content->text .= $this->get_tool_link($plugin, 'setupworkshops');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'createusers');
            $this->content->text .= $this->get_tool_link($plugin, 'data2workshop');
            $this->content->text .= $this->get_tool_link($plugin, 'setupvetting');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'workshop2data');
            $this->content->text .= $this->get_tool_link($plugin, 'updatevetting');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'setupevents');
            $this->content->text .= $this->get_tool_link($plugin, 'setuprooms');
            $this->content->text .= $this->get_tool_link($plugin, 'setupschedule');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'setupvideos');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'authorsgroup');
            $this->content->text .= $this->get_tool_link($plugin, 'authorsforum');
            $this->content->text .= $this->get_tool_link($plugin, 'reviewersforum');
            if ($countdates) {
                $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
                $this->content->text .= $this->get_exportimport_link($plugin, 'import','settings', 'i/import'    );
                $this->content->text .= $this->get_exportimport_link($plugin, 'export','settings', 'i/export'  );
                $this->content->text .= $this->get_exportimport_link($plugin, 'export','dates',    'c/event'    );
                $this->content->text .= $this->get_exportimport_link($plugin, 'export','schedule', 'i/calendar' );
                $this->content->text .= $this->get_exportimport_link($plugin, 'export','handbook', 'f/html'     );
            }
        }

        return $this->content;
    }

    /**
     * format_date_range
     *
     * @params string  $dateformat
     * @params integer $timenow
     * @params string  $timestart
     * @params string  $timefinish
     * @return array
     */
    protected function format_date_range($plugin, $dateformat, $timenow, $timestart, $timefinish) {

        $removedate = self::REMOVE_NONE;
        if ($this->config->$timestart && $this->config->$timefinish) {
            if (strftime('%Y', $this->config->$timestart)==strftime('%Y', $this->config->$timefinish)) {
                $removedate |= self::REMOVE_YEAR;
                if (strftime('%m', $this->config->$timestart)==strftime('%m', $this->config->$timefinish)) {
                    $removedate |= self::REMOVE_MONTH;
                    if (strftime('%d', $this->config->$timestart)==strftime('%d', $this->config->$timefinish)) {
                        $removedate |= self::REMOVE_DAY;
                    }
                }
            }
        }

        if ($removedate & self::REMOVE_DAY) {
            $removestart  = false;
            $removefinish = false;
            $removetime   = false;
        } else {
            $removestart  = ($this->config->$timestart && preg_match('/^00:0[01234]$/', strftime('%H:%M', $this->config->$timestart)));
            $removefinish = ($this->config->$timefinish && preg_match('/^23:5[56789]$/', strftime('%H:%M', $this->config->$timefinish)));
            $removetime   = ($removestart && $removefinish);
        }

        // if requested, remove the current year from dates
        $removeyear = self::REMOVE_NONE;
        if ($this->config->removeyear) {
            if ($this->config->$timestart) {
                if (strftime('%Y', $this->config->$timestart)==strftime('%Y')) {
                    $removeyear |= self::REMOVE_YEAR;
                }
            } else if ($this->config->$timefinish) {
                if (strftime('%Y', $this->config->$timefinish)==strftime('%Y')) {
                    $removeyear |= self::REMOVE_YEAR;
                }
            }
        }

        $date = '';
        if ($this->config->$timestart && $this->config->$timefinish) {
            $date = array(
                'open'  => $this->multilang_userdate($this->config->$timestart, $dateformat, $plugin, $removetime, $removeyear, true),
                'close' => $this->multilang_userdate($this->config->$timefinish, $dateformat, $plugin, $removetime, $removedate, true)
            );
            if (is_array($date['open'])) {
                foreach ($date['open'] as $lang => $text) {
                    $date[$lang] = new stdClass();
                    $date[$lang]->open = $text;
                    $text = $date['close'];
                    if (is_array($text)) {
                        $text = $text[$lang];
                    }
                    $date[$lang]->close = $text;
                }
                // unset($date['open'], $date['close']);
            }
            $date = $this->get_string('dateopenclose', $plugin, $date);
        } else if ($this->config->$timestart) {
            $date = $this->multilang_userdate($this->config->$timestart, $dateformat, $plugin, $removestart, $removeyear, true);
            if ($this->config->$timestart < $timenow) {
                $date = $this->get_string('dateopenedon', $plugin, $date);
            } else {
                $date = $this->get_string('dateopenson', $plugin, $date);
            }
        } else if ($this->config->$timefinish) {
            $date = $this->multilang_userdate($this->config->$timefinish, $dateformat, $plugin, $removefinish, $removeyear, true);
            if ($this->config->$timefinish < $timenow) {
                $date = $this->get_string('dateclosedon', $plugin, $date);
            } else {
                $date = $this->get_string('datecloseson', $plugin, $date);
            }
        }
        return $date;
    }

    /**
     * format_stats
     *
     * @params string  $plugin
     * @params string  $type
     * @params integer $cmid
     * @params integer $sectionnum
     * @return array
     */
    protected function format_stats($plugin, $modinfo, $type, $cmid, $sectionnum) {
        global $DB;

        if ($cmid && isset($modinfo->cms[$cmid])) {
            $dataid = $modinfo->get_cm($cmid)->instance;
        } else {
            $dataid = 0;
        }

        $table = '';
        $field = '';
        $select = '';
        $params = array();

        switch ($type) {

            case 'collectpresentations':
                if ($dataid) {
                    $params = array('dataid' => $dataid, 'name' => 'presentation_type');
                    if ($fieldid = $DB->get_field('data_fields', 'id', $params)) {
                        $table = 'data_content';
                        $field = 'COUNT(recordid)';
                        $select  = 'fieldid = ?'.
                                    ' AND '.$DB->sql_like('content', '?', false, false, true). // NOT LIKE
                                    ' AND '.$DB->sql_like('content', '?', false, false, true); // NOT LIKE
                        $params  = array($fieldid, '%orkshop%', '%ponsored%');
                    }
                }
                break;

            case 'collectworkshops':
            case 'collectsponsoreds':
                if ($dataid) {
                    $params = array('dataid' => $dataid, 'name' => 'presentation_type');
                    if ($fieldid = $DB->get_field('data_fields', 'id', $params)) {
                        $table = 'data_content';
                        $field = 'COUNT(recordid)';
                        $select  = 'fieldid = ? AND '.$DB->sql_like('content', '?');
                        $params  = array($fieldid, '%'.substr($type, 8).'%');
                    }
                }
                break;

            case 'review':
            case 'revise':
            case 'publish':
                break;

            case 'registerpresenters':
                if ($dataid) {
                    $params = array('dataid' => $dataid, 'name' => 'presenter');
                    if ($fieldid = $DB->get_field('data_fields', 'id', $params)) {
                        $table = 'data_content';
                        $field = 'COUNT(DISTINCT recordid)';
                        $select  = 'fieldid = ? AND content IS NOT NULL AND content <> ?';
                        $params  = array($fieldid, '');
                    }
                }
                break;

            case 'registerdelegates':
                if ($dataid) {
                    $params = array('dataid' => $dataid, 'name' => 'presenter');
                    if ($fieldid = $DB->get_field('data_fields', 'id', $params)) {
                        $table = 'data_content';
                        $field = 'COUNT(DISTINCT recordid)';
                        $select  = 'fieldid = ? AND (content IS NULL OR content = ?)';
                        $params  = array($fieldid, '');
                    } else {
                        $table = 'data_records';
                        $field = 'COUNT(id)';
                        $select = 'dataid = ?';
                        $params = array($dataid);
                    }
                }
                break;
        }

        if ($select) {
            $count = $DB->get_field_select($table, $field, $select, $params);
            return get_string('countrecords', $plugin, ($count ? $count : '0'));
        }
        return '';
    }

    /**
     * create_event
     *
     * @param string  $description
     * @param string  $type
     * @param integer $time
     * @param integer $duration
     * @param string  $modname
     * @param integer $instance
     * @return xxx
     */
    protected function create_event($name, $description, $type, $time, $duration, $modname, $instance) {
        return (object)array('name'         => $name,
                             'description'  => $this->config->title.': '.($description ? $description : $name),
                             'eventtype'    => $type,
                             'timestart'    => $time,
                             'timeduration' => $duration,
                             'modulename'   => $modname,
                             'instance'     => $instance);
    }

    /**
     * add_events
     *
     * @param array  $events
     * @param object $course
     * @param string $plugin
     * @return xxx
     */
    protected function add_events($events, $course, $plugin) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/calendar/lib.php');

        // check we are allowed to update calendar events,
        if (! has_capability('moodle/calendar:manageentries', $this->page->context)) {
            return false;
        }

        $select = 'courseid = :courseid AND '.$DB->sql_like('description', ':title');
        $params = array('courseid' => $course->id, 'title' => $this->config->title.': %');
        if ($eventids = $DB->get_records_select('event', $select, $params, 'id', 'id,courseid,name')) {
            $eventids = array_keys($eventids);
        } else {
            $eventids = array();
        }

        // don't check calendar capabiltiies when adding/updating events
        $checkcapabilties = false;

        foreach ($events as $event) {
            $event->groupid  = 0;
            $event->userid   = 0;
            $event->courseid = $course->id;
            if (count($eventids)) {
                $event->id = array_shift($eventids);
                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event, $checkcapabilties);
            } else {
                calendar_event::create($event, $checkcapabilties);
            }
        }

        // delete surplus events, if required
        // (no need to check capabilities here)
        while (count($eventids)) {
            $id = array_shift($eventids);
            $event = calendar_event::load($id);
            $event->delete();
        }
    }

    /**
     * get_tool_link
     *
     * @param string $type
     * @return array
     */
    protected function get_tool_link($plugin, $type) {

        $text = $this->get_string('tool'.$type, $plugin);
        $text = html_writer::tag('b', $text);

        $desc = $this->get_string('tool'.$type.'_desc', $plugin);

        $params = array('id' => $this->instance->id);
        $link = new moodle_url("/blocks/maj_submissions/tools/$type/tool.php", $params);

        $link = html_writer::tag('a', $text, array('href' => $link)).
                html_writer::empty_tag('br').
                html_writer::tag('span', $desc);

        return html_writer::tag('p', $link, array('class' => 'toollink'));
    }

    /**
     * get_icon
     *
     * @return array
     */
    protected function get_icon($icon, $title, $href, $class) {
        global $OUTPUT;
        $params = array('href' => $href, 'class' => "icon $class");
        return html_writer::tag('a', $OUTPUT->pix_icon($icon, $title), $params);
    }

    /**
     * get_edit_icon
     *
     * @return array
     */
    protected function get_edit_icon($plugin, $courseid) {

        // the "return" url which leads to the block edit page
        $params = array('id' => $courseid,
                        'sesskey' => sesskey(),
                        'bui_editid' => $this->instance->id);
        $href = new moodle_url('/course/view.php', $params);

        // the URL to enable editing and redirect to the block edit page
        $params = array('id' => $courseid,
                        'edit' => 'on',
                        'sesskey' => sesskey(),
                        'return' => $href->out_as_local_url(false));
        $href = new moodle_url('/course/view.php', $params);

        // return edit icon image
        // linking to edit enable page
        // with redirect to block settings page
        return $this->get_icon('t/edit', get_string('editsettings'), $href, 'editicon');
    }

    /**
     * get_exportimport_link
     *
     * @param string $plugin
     * @param string $action "import" or "export"
     * @param string $type "content" or "settings"
     * @param string $icon within "pix" dir
     * @return array
     */
    protected function get_exportimport_link($plugin, $action, $type, $icon) {
        $title = get_string('tool'.$action.$type, $plugin);
        $params = array('id' => $this->instance->id, 'sesskey' => sesskey());
        $href = new moodle_url("/blocks/maj_submissions/tools/$action$type/tool.php", $params);
        $icon = $this->get_icon($icon, $title, $href, $action.$type.'icon');
        //$title = html_writer::tag('b', $title);
        $title = html_writer::tag('a', $title, array('href' => $href));
        return html_writer::tag('p', $icon.' '.$title, array('class' => 'toollink'));

    }

    /**
     * get_dataids_sql
     *
     * @return`array (string of SQL, array of SQL params)
     */
    public function get_dataids_sql() {
        global $DB;

        $ids = array();
        if ($cmid = $this->config->collectpresentationscmid) {
            $ids[] = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
        }
        if ($cmid = $this->config->collectsponsoredscmid) {
            $ids[] = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
        }
        if ($cmid = $this->config->collectworkshopscmid) {
            $ids[] = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
        }
        $ids = array_filter($ids);
        if (empty($ids)) {
            return array('?', array(0));
        } else {
            return $DB->get_in_or_equal($ids);
        }
    }

    /**
     * get_multilang_fieldnames
     *
     * @param array $fields the basenames (i.e. without lang suffix) of required fields
     * @return`array ($records, $fieldsnames)
     */
    public function get_multilang_fieldnames($fields) {
        global $DB;

        // get SQL to match ids of database activities connected with this block
        list($datawhere, $params) = $this->get_dataids_sql();

        // build SQL to extract field names
        $where = array();
        foreach ($fields as $field) {
            $where[] = $DB->sql_like('name', '?');
            $params[] = $field.'%';
        }

        $where = implode(' OR ', $where);
        $where = "dataid $datawhere AND ($where)";
        return $DB->get_records_select_menu('data_fields', $where, $params, 'name', 'id,name');
    }

    /**
     * get_submission_records
     *
     * @param array $fields ('externalname' => 'internalname')
     * @return`array ($records, $fieldsnames)
     */
    public function get_submission_records($fields) {
        global $DB;

        // get SQL to match ids of database activities connected with this block
        list($datawhere, $dataparams) = $this->get_dataids_sql();

        list($where, $params) = $DB->get_in_or_equal($fields);
        $select = 'dc.id, df.name, dc.recordid, dc.content';
        $from   = '{data_content} dc '.
                  'LEFT JOIN {data_fields} df ON dc.fieldid = df.id';
        $where  = "df.dataid $datawhere AND df.name $where";
        $params = array_merge($dataparams, $params);
        $order  = 'dc.recordid';

        $records = array();
        $fieldnames = array();
        if ($values = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
            foreach ($values as $value) {
                if (empty($value->content)) {
                    continue;
                }
                $rid = $value->recordid;
                $fieldname = $value->name;
                $fieldnames[$fieldname] = true;
                if (empty($records[$rid])) {
                    $records[$rid] = new stdClass();
                }

                // remove HTML comments, script/style blocks, and HTML tags
                // and standardize white space to a single one-byte space
                $value = $value->content;
                $value = preg_replace('/<!-.*?->\s*/us', '', $value);
                $value = preg_replace('/<(script|style)[^>]*>.*?<\/\1>\s*/ius', '', $value);
                $value = preg_replace('/<.*?>\s*/us', ' ', $value);
                $value = preg_replace('/(\x3000|\s)+/us', ' ', $value);
                $records[$rid]->$fieldname = trim($value);
            }
        }
        return array($records, $fieldnames);
    }

    /**
     * multilang_userdate
     *
     * @param integer $date
     * @param string  $formatstring
     * @param boolean $removetime (optional, default = false)
     * @param integer $removedate (optional, default = REMOVE_NONE)
     * @param boolean $returnarray (optional, default = false)
     * @return string representation of $date
     */
    public function multilang_userdate($date, $formatstring, $plugin='', $removetime=false, $removedate=self::REMOVE_NONE, $returnarray=false) {
        global $CFG, $SESSION;

        if ($this->multilang==false) {
            if (strpos($formatstring, '%')===false) {
                $format = get_string($formatstring, $plugin);
            } else {
                $format = $formatstring;
            }
            return $this->userdate($date, $format, $removetime, $removedate);
        }

        // cache the current language
        $currentlanguage = current_language();

        // get all langs and locales on this Moodle site
        $locales = $this->get_string('locale', 'langconfig', null, true);

        $dates = array();
        foreach ($locales as $lang => $locale) {
            // moodle_setlocale($locale);
            force_current_language($lang);
            $this->check_date_fixes();
            if (strpos($formatstring, '%')===false) {
                $format = get_string($formatstring, $plugin);
            } else {
                $format = $formatstring;
            }
            $dates[$lang] = $this->userdate($date, $format, $removetime, $removedate);
        }

        // reset locale for current language, if any
        if ($lang = $currentlanguage) {
            force_current_language($lang);
            $this->check_date_fixes();
        }

        if ($returnarray) {
            return $dates;
        }

        return self::multilang_string($dates);
    }

    /**
     * multilang_format_time
     *
     * @param integer $secs
     * @return string representation of $date
     */
    public function multilang_format_time($secs) {

        if (empty($secs) || $secs==='0') {
            return '';
        }

        if ($this->multilang==false) {
            return format_time($secs);
        }

        $currentlanguage = current_language();

        // get all langs and locales on this Moodle site
        $locales = $this->get_string('locale', 'langconfig', null, true);

        $times = array();
        foreach ($locales as $lang => $locale) {
            force_current_language($lang);
            $times[$lang] = format_time($secs);
        }

        if ($lang = $currentlanguage) {
            $locale = $locales[$lang];
            force_current_language($lang);
        }

        return self::multilang_string($times);
    }

    /**
     * userdate
     *
     * @param integer $date
     * @param string  $format string as used by strftime
     * @param boolean $removetime
     * @param integer $removedate (optional, default = REMOVE_NONE)
     * @return string representation of $date
     */
    public function userdate($date, $format, $removetime, $removedate=self::REMOVE_NONE) {

        $currentlanguage = substr(current_language(), 0, 2);

        if ($removetime) {
            // http://php.net/manual/en/function.strftime.php
            $search = '/[ :,\-\.\/]*[\[\{\(]*?%[HkIlMpPrRSTX][\)\}\]]?/';
            $format = preg_replace($search, '', $format);
        }

        $search = '';
        if ($removedate & self::REMOVE_YEAR) {
            $search .= 'CgGyY';
        }
        if ($removedate & self::REMOVE_MONTH) {
            $search .= 'bBhm';
        }
        if ($removedate & self::REMOVE_DAY) {
            $search .= 'aAdejuw';
        }
        if ($search) {
            // http://php.net/manual/en/function.strftime.php
            $search = '/[ :,\-\.\/]*[\[\{\(]*?%['.$search.'][\)\}\]]?/';
            $format = preg_replace($search, '', $format);
        }

        // replace the unreliable %e, which misbehaves on Windows
        $format = str_replace('%e', '%d', $format);

        // set the $year, $month and $day characters for CJK languages
        list($year, $month, $day) = self::get_date_chars();

        $replace = array();

        // add year, month and day characters for CJK languages
        if ($this->fixyearchar && $year) {
            $replace['%y'] = '%y'.$year;
            $replace['%Y'] = '%Y'.$year;
        }
        if ($this->fixmonthchar && $month) {
            $replace['%b'] = '%m'.$month;
            $replace['%h'] = '%m'.$month;
        }
        if ($this->fixdaychar && $day) {
            $replace['%d'] = '%d'.$day;
        }

        if (is_numeric(strpos($format, '%a'))) {
            $replace['%a'] = self::get_day_name($date, true);
        }
        if (is_numeric(strpos($format, '%A'))) {
            $replace['%A'] = self::get_day_name($date, false);
        }

        if (count($replace)) {
            $format = strtr($format, $replace);
            $replace = array();
        }

        if ($fixmonth = ($this->config->fixmonth && is_numeric(strpos($format, '%m')))) {
            $replace['%m'] = 'MM';
        }
        if ($fixday = ($this->config->fixday && is_numeric(strpos($format, '%d')))) {
            $replace['%d'] = 'DD';
        }
        if ($fixhour = ($this->config->fixhour && is_numeric(strpos($format, '%I')))) {
            $replace['%I'] = 'II';
        }

        if (count($replace)) {
            $format = strtr($format, $replace);
        }

        $userdate = userdate($date, $format, 99, false, false);

        if ($fixmonth || $fixday || $fixhour) {
            $search = array(' 0', ' ');
            $replace = array();
            if ($fixmonth) {
                $month = strftime(' %m', $date);
                $month = str_replace($search, '', $month);
                $replace['MM'] = ltrim($month);
            }
            if ($fixday) {
                if ($currentlanguage=='en') {
                    $day = date(' jS', $date);
                } else {
                    $day = strftime(' %d', $date);
                    $day = str_replace($search, '', $day);
                }
                $replace['DD'] = ltrim($day);
            }
            if ($fixhour) {
                $hour = strftime(' %I', $date);
                $hour = str_replace($search, '', $hour);
                $replace['II'] = ltrim($hour);
            }
            $userdate = strtr($userdate, $replace);
        }

        // remove unnecessary white space
        $userdate = trim($userdate);
        $userdate = preg_replace('/  +/', ' ', $userdate);

        // Note that Chinese dates don't seem to use spaces at all.
        // We could detect this by checking for spaces in "strftimedate":
        // if (substr_count(get_string('strftimedate', 'langconfig'), ' ')) {
        //     $userdate = preg_replace('/  +/', ' ', $userdate);
        // } else {
        //     $userdate = str_replace(' ', '', $userdate);
        // }

        return $userdate;
    }

    /**
     * check_date_fixes
     */
    protected function check_date_fixes() {

        $dateformat = self::get_date_format($this->config);
        $date = strftime($dateformat, time());

        // set the $year, $month and $day characters for CJK languages
        list($year, $month, $day) = self::get_date_chars();

        if ($year && ! preg_match("/[0-9]+$year/", $date)) {
            $this->fixyearchar = true;
        }
        if ($month && ! preg_match("/[0-9]+$month/", $date)) {
            $this->fixmonthchar = true;
        }
        if ($day && ! preg_match("/[0-9]+$day/", $date)) {
            $this->fixdaychar = true;
        }
    }

    /**
     * get_date_format
     */
    static public function get_date_format($config) {
        static $dateformat = null;
        if ($dateformat===null) {
            if (! $dateformat = $config->customdatefmt) {
                if (! $dateformat = $config->moodledatefmt) {
                    $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
                }
                $dateformat = get_string($dateformat);
            }
        }
        return $dateformat;
    }

    /**
     * get_date_chars
     *
     * @return array($year, $month, $day)
     */
    static public function get_date_chars() {
        switch (substr(current_language(), 0, 2)) {
            case 'ja': return array('年', '月', '日'); // Japanese
            case 'ko': return array('년', '월', '일'); // Korean
            case 'zh': return array('年', '月', '日'); // Chinese
            default : return array('', '', '');
        }
    }

    /**
     * get_day_name
     *
     * This function serves as a replacement for %a and %A
     * which do not seem to work reliably across platforms
     * in the "strftime()" function.
     */
    static public function get_day_name($date, $shortname) {
        $w = strftime('%w', $date); // weekday number (0=sun, 6=sat)
        if ($shortname) {
            $names = array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat');
        } else {
            $names = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
        }
        return get_string($names[$w], 'calendar');
    }

    /**
     * set_multilang
     *
     * @return void, but will update "multilang" property
     */
    public function set_multilang($multilang) {
        $this->multilang = $multilang;
    }

    /**
     * get_multilang_params
     *
     * @return array($lang => string params object)
     */
    public function get_multilang_params($names, $plugin, $a=null) {
        $params = array();
        foreach ($names as $paramname => $stringname) {
            $texts = $this->get_string($stringname, $plugin, $a, true);
            foreach ($texts as $lang => $text) {
                if (empty($params[$lang])) {
                    $params[$lang] = new stdClass();
                }
                $params[$lang]->$paramname = $text;
            }
        }
        return $params;
    }

    /**
     * get_string
     *
     * @return string, either string for current language or the "multilang" version of the string
     */
    public function get_string($identifier, $component='', $a=null, $returnarray=false) {
        if ($this->multilang) {
            return self::get_multilang_string($identifier, $component, $a, $returnarray);
        } else {
            return get_string($identifier, $component, $a);
        }
    }

    /**
     * get_multilang_string
     *
     * @return string, return the "multilang" verison of the required string;
     *                 i.e. <span lang="xx" class="multilang">...</span><span...>...</span>
     *                 otherwise, return Moodle's standard get_string() output
     */
    static public function get_multilang_string($identifier, $component='', $a=null, $returnarray=false) {

        $strman = get_string_manager();
        $langs = $strman->get_list_of_translations();
        $langs = array_keys($langs);

        // sort $langs, so that "en" is first
        // and parent langs appear before child langs
        usort($langs, array('block_maj_submissions', 'usort_langs'));

        // initialize $params for get_string
        if ($a_is_multilang = is_array($a)) {
            $params = null; // will be set later
        } else {
            $params = $a; // either scaler or object
        }

        // extract unique strings
        $texts = array();
        foreach ($langs as $lang) {
            $strings = $strman->load_component_strings($component, $lang);
            if (array_key_exists($identifier, $strings)) {
                if ($a_is_multilang) {
                    if (array_key_exists($lang, $a)) {
                        $params = $a[$lang];
                    } else {
                        $params = reset($a);
                    }
                }
                $text = $strman->get_string($identifier, $component, $params, $lang);
                $texts[$lang] = $text;
            }
        }

        if ($returnarray) {
            return $texts;
        }

        return self::multilang_string($texts);
    }

    /**
     * multilang_string
     *
     * @param array $items
     * @return multilang string version of $items
     */
    static public function multilang_string($items) {

        // no items - should not happen !!
        if (empty($items)) {
            return '';
        }

        // remove items that are the same as the default 'en' item
        if (array_key_exists('en', $items)) {
            foreach ($items as $lang => $item) {
                if ($lang=='en') {
                    continue;
                }
                if ($items['en']==$item) {
                    unset($items[$lang]);
                }
            }
        }

        // special case - this item is unique in only one language
        if (count($items)==1) {
            return reset($items);
        }

        // get common $prefix and $suffix, if any
        $prefix = '';
        $suffix = '';
        //list($items, $prefix, $suffix) = self::get_items_prefix_suffix($items);

        // format items as multilang $items
        foreach ($items as $lang => $item) {
            $params = array('lang' => $lang, 'class' => 'multilang');
            $items[$lang] = html_writer::tag('span', $item, $params);
        }

        return $prefix.implode('', $items).$suffix;
    }


    /**
     * Extract common $prefix and $suffix.
     * (this is particularly intended for dates with times)
     *
     * NOTE: this method does seem to be used anywhere but it works
     *
     * @param array $items
     * @return array ($items, $suffix, $prefix)
     */
    static public function get_items_prefix_suffix($items) {

        $strlen = 0;
        $prefix = null;
        $suffix = null;

        foreach ($items as $lang => $item) {
            if ($prefix === null) {
                $prefix = $item;
                $strlen = strlen($prefix);
            } else if ($strlen > 0) {
                $strlen = min($strlen, strlen($item));
                for ($i = 0; $i < $strlen; $i++) {
                    if ($prefix[$i] != $item[$i]) {
                        $strlen = $i; // stop loop
                    }
                }
            }
        }
        if ($strlen) {
            $prefix = substr($prefix, 0, $strlen);
            $items = array_map(function($value) use($strlen) {
                return substr($value, $strlen);
            }, $items);
        } else {
            $prefix = '';
        }

        foreach ($items as $lang => $item) {
            $item = strrev($item);
            if ($suffix === null) {
                $suffix = $item;
                $strlen = strlen($suffix);
            } else if ($strlen > 0) {
                $strlen = min($strlen, strlen($item));
                for ($i = 0; $i < $strlen; $i++) {
                    if ($suffix[$i] != $item[$i]) {
                        $strlen = $i; // stop loop
                    }
                }
            }
        }
        if ($strlen) {
            $suffix = strrev($suffix);
            $suffix = substr($suffix, -$strlen);
            $items = array_map(function($value) use($strlen) {
                return substr($value, 0, -$strlen);
            }, $items);
        } else {
            $suffix = '';
        }

        return array($items, $prefix, $suffix);
    }

    /**
     * reduce_multilang_string
     *
     * NOTE: this method does seem to be used anywhere and can probably be deleted
     *
     * @param string $text possibly containing multilang strings
     * @param string $lang optional(default = "en")
     * @return string
     */
    static public function reduce_multilang_string($text, $lang='en') {
        $search = '/<span[^>]*lang="(\w*)"[^>]*>(.*?)<\/span>/isu';
        if (preg_match_all($search, $text, $matches)) {
            $i_max = count($matches[0]);
            for ($i=0; $i<$i_max; $i++) {
                if ($lang==$matches[1][$i]) {
                    return $matches[2][$i];
                }
            }
            return $matches[2][0];
        } else {
            return $text;
        }
    }

    /**
     * usort_langs
     *
     * sort $langs, so that "en" is first
     * and parent langs (length = 2)
     * appear before child langs (length > 2)
     */
    static public function usort_langs($a, $b) {

        // put "en" first
        if ($a=='en') {
            return -1;
        }
        if ($b=='en') {
            return 1;
        }

        // compare parent langs
        $a_parent = substr($a, 0, 2);
        $b_parent = substr($b, 0, 2);
        if ($a_parent=='en' && $b_parent!='en') {
            return -1;
        }
        if ($b_parent=='en' && $a_parent!='en') {
            return -1;
        }
        if ($a_parent < $b_parent) {
            return -1;
        }
        if ($b_parent < $a_parent) {
            return 1;
        }

        // same parent lang, compare lengths
        $a_len = strlen($a);
        $b_len = strlen($b);
        if ($a_len < $b_len) {
            return -1;
        }
        if ($b_len < $a_len) {
            return 1;
        }

        // sibling langs, compare values
        if ($a < $b) {
            return -1;
        }
        if ($b < $a) {
            return 1;
        }

        return 0; // shouldn't happen !!
    }

    /**
     * get_languages
     *
     * @return array
     */
    static public function get_languages($langs='') {
        if ($langs) {
            $langs = explode(',', $langs);
            $langs = array_map('trim', $langs);
            $langs = array_filter($langs);
            $langs = array_unique($langs);
            return $langs;
        } else {
            $langs = get_string_manager()->get_list_of_translations();
            $langs = array_keys($langs);
            sort($langs);
            return $langs;
        }
    }

    /**
     * is_required_constant
     *
     * @return array(database_field_name => configfieldname)
     */
    static public function is_required_constant($config, $dataid, $name) {
        global $DB;
        static $required = array();

        if (empty($required[$dataid])) {
            $required[$dataid] = array(
                'conferencename',
                'conferencevenue',
                'conferencedates'
            );

            // get cmids of conference registration databases
            $cmids = array();
            if (isset($config->registerpresenterscmid)) {
                $cmids[] = $config->registerpresenterscmid;
            }
            if (isset($config->registerdelegatescmid)) {
                $cmids[] = $config->registerdelegatescmid;
            }

            $cmids = array_filter($cmids);
            $cmids = array_unique($cmids);

            // check if this $dataid is for a registration database
            if (count($cmids)) {
                list($select, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
                $select = "id $select AND module = :moduleid AND instance = :instanceid";
                $params['moduleid'] = $DB->get_field('modules', 'id', array('name' => 'data'));
                $params['instanceid'] = $dataid;
                if ($DB->record_exists_select('course_modules', $select, $params)) {
                    array_push($required[$dataid], 'dinnername',
                                                   'dinnervenue',
                                                   'dinnerdate',
                                                   'dinnertime',
                                                   'certificatedate',
                                                   'badgenumber',
                                                   'feereceiptnumber',
                                                   'dinnerreceiptnumber',
                                                   'dinnerticketnumber',
                                                   'certificatenumber');
                }
            }
        }
        return in_array($name, $required[$dataid]);
    }

    /**
     * get_constant_fieldnames
     *
     * @param integer $type (0, 1 or 2)
     * @return array(database_field_name => configfieldname)
     */
    static public function get_constant_fieldnames($constanttype) {
        if ($constanttype==0) { // constant
            return array('conference_name'  => 'conferencename',
                         'conference_venue' => 'conferencevenue',
                         'conference_dates' => 'conferencedates',
                         'dinner_name'      => 'dinnername',
                         'dinner_venue'     => 'dinnervenue',
                         'dinner_date'      => 'dinnerdate',
                         'dinner_time'      => 'dinnertime',
                         'certificate_date' => 'certificatedate',
                         'payment_info_url' => 'paymentinfocmid',
                         'membership_info_url' => 'membershipinfocmid');
        }
        if ($constanttype==1) { // autoincrement
            return array('badge_number'          => 'badgenumber',
                         'fee_receipt_number'    => 'feereceiptnumber',
                         'dinner_receipt_number' => 'dinnerreceiptnumber',
                         'dinner_ticket_number'  => 'dinnerticketnumber',
                         'certificate_number'    => 'certificatenumber');
        }
        if ($constanttype==2) { // random
            return array('unique_key' => 'uniquekey');
        }
        return array(); // shouldn't happen !!
    }

    /**
     * get_timetypes
     *
     * @return array
     */
    static public function get_timetypes() {
        return array(
            array('workshops',
                  'conference',
                  'reception'),
            array('collectpresentations',
                  'collectworkshops',
                  'collectsponsoreds'),
            array('review',
                  'revise',
                  'publish'),
            array('registerearly',
                  'registerpresenters',
                  'registerdelegates')
        );
    }

    static public function get_fileoptions() {
        return array('subdirs' => 1,
                     'maxbytes' => 0,
                     'maxfiles' => -1,
                     'mainfile' => false,
                     'accepted_types' => '*');
    }

    /**
     * get_php_lang
     *
     * @return array
     */
    static public function get_php_lang() {
        // http://php.net/manual/en/index.php
        $langs = array(
            // Moodle lang code => PHP lang code
            'en' => 'en', // English
            'de' => 'de', // German
            'es' => 'es', // Spanish
            'fr' => 'fr', // French
            'ja' => 'ja', // Japanese
         'pt_br' => 'pt_BR', // Brazilian Portuguese
            'ro' => 'ro', // Romanian
            'ru' => 'ru', // Russian
            'tr' => 'tr', // Turkish
            'zh' => 'zh', // Chinese (Simplified)
        );
        $lang = current_language();
        if (array_key_exists($lang, $langs)) {
            return $langs[$lang];
        } else {
            return 'en'; // default PHP language
        }
    }

    /**
     * get_sectionlink
     *
     * @param integer  $courseid
     * @param integer  $sectionnum
     * @param integer  $coursedisplay (optional, default=null)
     * @return string  name of $section
     */
    static public function get_sectionlink($courseid, $sectionnum, $coursedisplay=null) {
        $url = new moodle_url('/course/view.php', array('id' => $courseid));
        if ($coursedisplay===null || $coursedisplay==COURSE_DISPLAY_SINGLEPAGE) {
            $url->set_anchor("section-$sectionnum");
        } else {
            $url->param('section', $sectionnum);
        }
        return $url;
        //$params = array('id' => $courseid, 'section' => $sectionnum);
        //return new moodle_url('/course/view.php', $params);
    }

    /**
     * get_sectionname
     *
     * names longer than $namelength will be trancated to to HEAD ... TAIL
     * where the number of characters in HEAD is $headlength
     * and the number of characters in TAIL is $taillength
     *
     * @param object   $section
     * @param integer  $namelength of section name (optional, default=28)
     * @param integer  $headlength of head of section name (optional, default=10)
     * @param integer  $taillength of tail of section name (optional, default=10)
     * @return string  name of $section
     */
    static public function get_sectionname($section, $namelength=28, $headlength=10, $taillength=10) {

        // extract section title from section name (strip tags inserted by filters)
        if ($name = trim(strip_tags(self::filter_text($section->name)))) {
            return self::trim_text($name, $namelength, $headlength, $taillength);
        }

        // extract section title from section summary
        if ($name = self::filter_text($section->summary)) {

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
            if ($name = trim(strip_tags($name))) {
                $name = self::trim_text($name, $namelength, $headlength, $taillength);
                return $name;
            }
        }

        return ''; // section name and summary are empty
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
     * filter_text
     *
     * @param string $text
     * @return string
     */
    static public function filter_text($text) {
        global $PAGE;

        $filter = filter_manager::instance();

        if (method_exists($filter, 'setup_page_for_filters')) {
            // Moodle >= 2.3
            $filter->setup_page_for_filters($PAGE, $PAGE->context);
        }

        return $filter->filter_text($text, $PAGE->context);
    }

    /**
     * trim_text
     *
     * @param   string   $text
     * @param   integer  $textlength (optional, default=42)
     * @param   integer  $headlength (optional, default=16)
     * @param   integer  $taillength (optional, default=16)
     * @return  string
     */
    static public function trim_text($text, $textlength=42, $headlength=16, $taillength=16) {
        if ($textlength) {
            $strlen = self::textlib('strlen', $text);
            if ($strlen > $textlength) {
                $head = self::textlib('substr', $text, 0, $headlength);
                $tail = self::textlib('substr', $text, $strlen - $taillength, $taillength);
                $text = $head.' ... '.$tail;
            }
        }
        return $text;
    }

    /**
     * textlib
     *
     * a wrapper method to offer consistent API for textlib class
     * in Moodle 2.0 and 2.1, $textlib is first initiated, then called
     * in Moodle 2.2 - 2.5, we use only static methods of the "textlib" class
     * in Moodle >= 2.6, we use only static methods of the "core_text" class
     *
     * @param string $method
     * @param mixed any extra params that are required by the textlib $method
     * @return result from the textlib $method
     * @todo Finish documenting this function
     */
    static public function textlib() {
        if (class_exists('core_text')) {
            // Moodle >= 2.6
            $textlib = 'core_text';
        } else if (method_exists('textlib', 'textlib')) {
            // Moodle 2.0 - 2.2
            $textlib = textlib_get_instance();
        } else {
            // Moodle 2.3 - 2.5
            $textlib = 'textlib';
        }
        $args = func_get_args();
        $method = array_shift($args);
        $callback = array($textlib, $method);
        return call_user_func_array($callback, $args);
    }

    /**
     * plain_text
     *
     * @param string $text string possibly containing HTML and/or unicode chars
     * @return single-line, plain-text version of $text
     */
    static public function plain_text($text) {
        // remove single-byte spaces before HTML tags
        $search = '/(?: |\t|\r|\n|\x{00A0}|\x{3000}|&nbsp;|(?:<[^>]*>))+/us';
        $text = preg_replace($search, ' ', $text);
        // remove single-byte spaces following double-byte char
        $search = '/(?<=[\x{3001}-\x{3002},\x{FF01}-\x{FF1F}]) +/u';
        $text = preg_replace($search, '', $text);
        // ensure there is no space before punctuation
        // and exactly one space after (but don't touch numbers)
        $search = array('/ +(?=[,.?!:;])/us', '/([,.])(?=[^0-9 ])\s*/us', '/([?!:;])\s*/us');
        $replace = array('', '$1 ', '$1 ');
        $text = preg_replace($search, $replace, $text);
        return trim($text);
    }

    /**
     * context
     *
     * a wrapper method to offer consistent API to get contexts
     * in Moodle 2.0 and 2.1, we use self::context() function
     * in Moodle >= 2.2, we use static context_xxx::instance() method
     *
     * @param integer $contextlevel
     * @param integer $instanceid (optional, default=0)
     * @param int $strictness (optional, default=0 i.e. IGNORE_MISSING)
     * @return required context
     * @todo Finish documenting this function
     */
    static public function context($contextlevel, $instanceid=0, $strictness=0) {
        if (class_exists('context_helper')) {
            // Moodle >= 2.2
            // use call_user_func() to prevent syntax error in PHP 5.2.x
            $class = context_helper::get_class_for_level($contextlevel);
            return call_user_func(array($class, 'instance'), $instanceid, $strictness);
        }
        if (function_exists('get_context_instance')) {
            // Moodle 1.7 - 2.5
            return get_context_instance($contextlevel, $instanceid);
        }
        // Moodle <= 1.6 does not know about contexts
        return null;
    }

    /**
     * get_room_name
     *
     * extract a numeric number of seats from the "schedule_roomseats" field
     *
     * @param integer $recordid
     * @param integer $cmid
     * @return the numeric number of seats extract from the schedule_roomseats
     * @todo Finish documenting this function
     */
    static function get_room_name($cmid, $recordid=0) {
        return self::get_room_info($cmid, $recordid, 'name');
    }

    /**
     * get_room_seats
     *
     * extract a numeric number of seats from the "schedule_roomseats" field
     *
     * @param integer $recordid
     * @param integer $cmid
     * @return the numeric number of seats extract from the schedule_roomseats
     * @todo Finish documenting this function
     */
    static function get_room_seats($cmid, $recordid=0) {
        return self::get_room_info($cmid, $recordid, 'seats');
    }

    /**
     * get_room_info_one
     *
     * extract a numeric number of seats from the "schedule_roomseats" field
     *
     * @param integer $recordid
     * @param integer $cmid
     * @param string $type (optional: default="")
     * @return array [recordid => mixed], either "name", "seats" of array("name" => 99, "seats" => 99)
     * @todo Finish documenting this function
     */
    static function get_room_info($cmid, $recordid=0, $type='') {
        global $DB;

        $select = 'dc.id, dc.recordid, df.name AS fieldname, dc.content';
        $from   = '{course_modules} cm, '.
                  '{data_fields} df, '.
                  '{data_content} dc';
        $where  = 'cm.id = ? '.
                  'AND cm.instance = df.dataid '.
                  'AND (df.name = ? OR df.name = ?) '.
                  'AND df.id = dc.fieldid';
        $params = array($cmid, 'schedule_roomname', 'schedule_roomseats');
        $order = 'dc.recordid ASC, df.name DESC'; // i.e. roomseats FIRST

        if ($recordid) {
            $where .= ' AND dc.recordid = ?';
            $params[] = $recordid;
        }

        $info = array();
        if ($contents = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {

            // regex to match multilang SPANs
            //   $1 : opening multilang SPAN tag
            //   $2 : SPAN content
            //   $3 : closing SPAN tag
            $multilang = '/(<span[^>]*class="multilang"[^>]*>)(.*?)\s*(<\/span>)/us';

            $rid = 0;
            foreach ($contents as $id => $content) {

                // skip empty content
                if ($content->content=='') {
                    continue;
                }

                // cache record id
                $rid = $content->recordid;

                if (empty($info[$rid])) {
                    if ($type) {
                        $info[$rid] = '';
                    } else {
                        $info[$rid] = array('name' => '', 'seats' => '');
                    }
                }

                $name = '';
                $seats = 0;

                $text = $content->content;
                $fieldname = $content->fieldname;

                if (preg_match_all($multilang, $text, $matches)) {
                    $i_max = count($matches[0]);
                    for ($i=0; $i<$i_max; $i++) {
                        $room = self::extract_room_from_text($matches[2][$i], $fieldname);
                        $matches[0][$i] = $matches[1][$i].$room['name'].$matches[3][$i];
                        if ($seats < $room['seats']) {
                            $seats = $room['seats'];
                        }
                    }
                    $name = implode('', $matches[0]);
                } else {
                    $room = self::extract_room_from_text($text, $fieldname);
                    $name = $room['name'];
                    $seats = $room['seats'];
                }

                $name = format_string($name);
                if ($type) {
                    if ($info[$rid]==='') {
                        if ('type'=='name') {
                            $info[$rid] = $name;
                        } else {
                            $info[$rid] = $seats;
                        }
                    }
                } else {
                    if ($info[$rid]['name']==='') {
                        $info[$rid]['name'] = $name;
                    }
                    if ($info[$rid]['seats']==='') {
                        $info[$rid]['seats'] = $seats;
                    }
                }
            }
        }

        if ($recordid==0) {
            return $info;
        }

        if (array_key_exists($recordid, $info)) {
            return $info[$recordid];
        }

        return ($type=='name' ? '' : 0);
    }

    /**
     * extract_room_from_text
     *
     * extract a numeric number of seats from the "schedule_roomseats" field
     *
     * @param string $text
     * @param string $fieldname
     * @return the numeric seats of seats extract from the schedule_roomseats
     * @todo Finish documenting this function
     */
    static function extract_room_from_text($text, $fieldname) {

        // "schedule_roomname" value may contain seat capacity in parentheses
        if (preg_match('/^(.*?)\s*\x{FF08}(.*?)\x{FF09}/us', $text, $match)) {
            $name = $match[1];
            $seats = $match[2];
        } else if (preg_match('/^(.*?)\s*\((.*?)\)/us', $text, $match)) {
            $name = $match[1];
            $seats = $match[2];
        } else if ($fieldname=='schedule_roomname') {
            $name = $text;
            $seats = '';
        } else {
            $name = '';
            $seats = $text;
        }

        // search for double-byte seats
        if (preg_match('/[\x{FF10}-\x{FF19}]+/u', $seats, $match)) {
            $seats = strtr($match[0], array("\u{FF10}" => 0, "\u{FF11}" => 1,
                                            "\u{FF12}" => 2, "\u{FF13}" => 3,
                                            "\u{FF14}" => 4, "\u{FF15}" => 5,
                                            "\u{FF16}" => 6, "\u{FF17}" => 7,
                                            "\u{FF18}" => 8, "\u{FF19}" => 9));
        }
        if (preg_match('/[0-9]+/', $seats, $match)) {
            $seats = intval($match[0]);
        } else {
            $seats = 0; // number of seats not specified
        }

        return array('name' => $name, 'seats' => $seats);
    }

    /**
     * get_seats_info
     *
     * @param array $info ()
     * @return array [recordid => string]
     * @todo Finish documenting this function
     */
    static public function get_seats_info($info, $is_manager) {
        global $DB;
        if (empty($info)) {
            return $info;
        }
        list($sql, $params) = $DB->get_in_or_equal(array_keys($info));
        $select = 'SELECT recordid, SUM(attend) AS attendance '.
                  'FROM {block_maj_submissions} '.
                  "WHERE recordid $sql ".
                  'GROUP BY recordid';
        $attend = $DB->get_records_sql_menu($select, $params);
        if ($attend===false) {
            $attend = array();
        }

        $string = 'emptyseatsx';
        $plugin = 'block_maj_submissions';
        foreach ($info as $rid => $seats) {
            if (array_key_exists($rid, $attend)) {
                $attend[$rid] = intval($attend[$rid]);
            } else {
                $attend[$rid] = 0;
            }
            if ($seats == 0) {
                // unlimited number of seats (e.g. online)
                if ($is_manager) {
                    $info[$rid] = get_string('usedseatsx', $plugin, $attend[$rid]);
                } else {
                    $info[$rid] = get_string('seatsavailable', $plugin);
                }
            } else {
                // limited number of seats (e.g. face-to-face presentation)
                $info[$rid] = get_string('emptyseatsx', $plugin, max(0, $seats - $attend[$rid]));
            }
        }
        return $info;
    }

    /**
     * Format a submission record as HTML suitable for adding to the conference schedule
     * This method is used by the setupschedule tool in this block_base
     * and also by the "setup_schedule" subplugin in datafield_action
     *
     * @param object $instance a block instance object
     * @param integer $recordid
     * @param array $item
     * @return string of HTML to represent this submission in the conference schedule
     */
    static public function format_item($instance, $recordid, $item, $formatcontainer=true) {

        // cache for CSS classes
        static $classes = array();

        $html = '';

        $name = 'submission_status';
        if (array_key_exists($name, $item) && preg_match('/Cancelled|(Not accepted)/', $item[$name])) {
            return $html;
        }

        // search/replace strings to extract CSS class from field param1
        $multilangsearch = array('/<(span|lang)\b[^>]*>([ -~]*?)<\/\1>/u',
                                 '/<(span|lang)\b[^>]*>.*?<\/\1>/u');
        $multilangreplace = array('$2', '');

        $firstwordsearch = array('/[^a-zA-Z0-9 ]/u', '/ .*$/u');
        $firstwordreplace = array('', '');

        $durationsearch = array('/(^.*\()|(\).*$)/u', '/[^0-9]/', '/^.*$/');
        $durationreplace = array('', '', 'duration$0');

        $sessionclass = 'session';

        if (isset($item['event_name'])) {
            $sessionclass .= ' event';
        }

        // extract category
        //     個人の発表 Individual presentation
        //     スポンサー提供の発表 Sponsored presentation
        //     日本ムードル協会の補助金報告 MAJ R&D grant report
        if (empty($item['presentation_category'])) {
            $presentationcategory = '';
        } else {
            $presentationcategory = $item['presentation_category'];
            if (empty($classes['category'][$presentationcategory])) {
                $class = $presentationcategory;
                if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                    $class = preg_replace($multilangsearch, $multilangreplace, $class);
                }
                $class = preg_replace($firstwordsearch, $firstwordreplace, $class);
                $classes['category'][$presentationcategory] = strtolower(trim($class));
            }
            $sessionclass .= ' '.$classes['category'][$presentationcategory];
        }

        // extract type
        //     ライトニング・トーク（１０分） Lightning talk (10 mins)
        //     ケース・スタディー（２０分） Case study (20 mins)
        //     プレゼンテーション（２０分） Presentation (20 mins)
        //     プレゼンテーション（４０分） Presentation (40 mins)
        //     プレゼンテーション（９０分） Presentation (90 mins)
        //     ショーケース（９０分） Showcase (90 mins)
        //     商用ライトニング・トーク（１０分） Commercial lightning talk (10 mins)
        //     商用プレゼンテーション（４０分） Commercial presentation (40 mins)
        //     商用プレゼンテーション（９０分） Commercial presentation (90 mins)
        switch (true) {
            case isset($item['event_type']):
                $presentationtype = trim($item['event_type']);
                break;
            case isset($item['presentation_type']):
                $presentationtype = trim($item['presentation_type']);
                break;
            default:
                $presentationtype = '';
        }
        if ($presentationtype) {
            if (empty($classes['type'][$presentationtype])) {
                $class = $presentationtype;
                if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                    $class = preg_replace($multilangsearch, $multilangreplace, $class);
                }
                $class = preg_replace($firstwordsearch, $firstwordreplace, $class);
                $classes['type'][$presentationtype] = strtolower(trim($class));
            }
            $sessionclass .= ' '.$classes['type'][$presentationtype];
        }

        switch (true) {
            case isset($item['event_topic']):
                $presentationtopic = trim($item['event_topic']);
                break;
            case isset($item['presentation_topic']):
                $presentationtopic = trim($item['presentation_topic']);
                break;
            default:
                $presentationtopic = '';
        }

        // extract duration CSS class e.g. duration40mins
        if (empty($item['schedule_duration'])) {
            $scheduleduration = $presentationtype;
        } else {
            $scheduleduration = trim($item['schedule_duration']);
        }
        if ($scheduleduration) {
            if (isset($classes['duration'][$scheduleduration])) {
                $class = $classes['duration'][$scheduleduration];
            } else {
                $class = $scheduleduration;
                if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                    $class = preg_replace($multilangsearch, $multilangreplace, $class);
                }
                $class = preg_replace($durationsearch, $durationreplace, $class);
                if (preg_match('/^\s*duration\d+\s*$/i', $class)) {
                    $class = strtolower(trim($class));
                } else {
                    $class = ''; // no duration specfied
                }
                $classes['duration'][$scheduleduration] = $class;
            }
            if ($class) {
                $sessionclass .= ' '.$class;
            }
        }

        // extract duration
        if (empty($item['schedule_duration'])) {
            $duration = $item['presentation_type'];
            $duration = preg_match('/[^0-9]/', '', $duration);
            $duration = $instance->multilang_format_time($duration);
        } else {
            $duration = $item['schedule_duration'];
        }

        if ($formatcontainer) {
            // start session DIV
            $html .= html_writer::start_tag('div', array('id' => 'id_recordid_'.$recordid,
                                                         'class' => $sessionclass,
                                                         'style' => 'display: inline-block;'));
        }
        // time and duration
        $html .= html_writer::start_tag('div', array('class' => 'time'));
        $html .= html_writer::tag('span', $item['schedule_time'], array('class' => 'startfinish'));
        $html .= html_writer::tag('span', $duration, array('class' => 'duration'));
        $html .= html_writer::end_tag('div');

        // room
        $html .= html_writer::start_tag('div', array('class' => 'room'));
        $html .= html_writer::tag('span', $item['schedule_roomname'], array('class' => 'roomname'));
        $html .= html_writer::tag('span', '', array('class' => 'roomseats'));
        $html .= html_writer::tag('span', '', array('class' => 'roomtopic'));
        $html .= html_writer::end_tag('div');

        // title
        $html .= self::format_title($recordid, $item);

        // schedule number and authornames
        $html .= html_writer::start_tag('div', array('class' => 'authors'));
        $html .= html_writer::tag('span', $item['schedule_number'], array('class' => 'schedulenumber'));
        $html .= self::format_authornames($recordid, $item);
        $html .= html_writer::end_tag('div');

        // category, type and topic
        $html .= html_writer::start_tag('div', array('class' => 'categorytypetopic'));
        $html .= html_writer::tag('span', $presentationcategory, array('class' => 'category'));
        $html .= html_writer::tag('span', $presentationtype, array('class' => 'type'));
        $html .= html_writer::tag('span', $presentationtopic, array('class' => 'topic'));
        $html .= html_writer::end_tag('div'); // end categorytypetopic DIV

        // summary (remove all tags and nbsp)
        $html .= self::format_summary($recordid, $item);

        // capacity
        $html .= html_writer::start_tag('div', array('class' => 'capacity'));
        $html .= html_writer::tag('div', '', array('class' => 'emptyseats'));
        $html .= html_writer::start_tag('div', array('class' => 'attendance'));
        $html .= html_writer::empty_tag('input', array('id' => 'id_attend_'.$recordid,
                                                       'name' => 'attend['.$recordid.']',
                                                       'type' => 'checkbox',
                                                       'value' => '1'));
        $text = get_string('notattending', 'block_maj_submissions');
        $html .= html_writer::tag('label', $text, array('for' => 'id_attend_'.$recordid));
        $html .= html_writer::end_tag('div'); // end attendance DIV
        $html .= html_writer::end_tag('div'); // end capacity DIV

        if ($formatcontainer) {
            $html .= html_writer::end_tag('div'); // end session DIV
        }

        return $html;
    }

    /**
     * Format presentation title as HTML suitable for adding to the conference schedule
     *
     * @param array $item
     * @return string of HTML to represent the presentation title in the conference schedule
     */
    static public function format_title($recordid, $item) {
        if (is_string($item)) {
            $text = trim($item);
        } else if (isset($item['event_name'])) {
            $text = trim($item['event_name']);
        } else if (empty($item['presentation_title'])) {
            $text = '';
        } else {
            $text = trim($item['presentation_title']);
        }
        if ($text=='') {
            $text = get_string('notitle', 'block_maj_submissions', $recordid);
        } else {
            $text = self::plain_text($text);
        }
        return html_writer::tag('div', $text, array('class' => 'title'));
    }

    /**
     * Format presentation abstract as HTML suitable for adding to the conference schedule
     *
     * @param array $item
     * @return string of HTML to represent the presentation abstract in the conference schedule
     */
    static public function format_summary($recordid, $item) {
        if (is_string($item)) {
            $text = trim($item);
        } else if (isset($item['event_description'])) {
            $text = trim($item['event_description']);
        } else if (empty($item['presentation_abstract'])) {
            $text = '';
        } else {
            $text = trim($item['presentation_abstract']);
        }
        if ($text=='') {
            $text = get_string('noabstract', 'block_maj_submissions', $recordid);
        } else {
            $text = self::plain_text($text);
        }
        return html_writer::tag('div', $text, array('class' => 'summary'));
    }

    /**
     * Format presentation abstract as HTML suitable for adding to the conference schedule
     *
     * @param array $item
     * @return string of HTML to represent the presentation abstract in the conference schedule
     */
    static public function format_sessiontypes($recordid, $item) {
        if (is_string($item)) {
            $type = trim($item);
        } else if (isset($item['event_type'])) {
            $type = trim($item['event_type']);
        } else if (isset($item['presentation_type'])) {
            $type = trim($item['presentation_type']);
        } else {
            $type = '';
        }
        return html_writer::tag('span', $text, array('class' => 'type'));
    }

    /**
     * Format the authornames as HTML suitable for adding to the conference schedule
     *
     * @param array $item
     * @return string of HTML to represent the authornames in the conference schedule
     */
    static public function format_authornames($recordid, $item) {

        static $nametemplates = null;
        if ($nametemplates===null) {
            $nametemplates = self::get_multilang_string('fullnamedisplay', '', null, true);
            foreach ($nametemplates as $lang => $nametemplate) {
                $nametemplate = preg_replace('/(\x3000|\s)+/us', ' ', $nametemplate);
                $nametemplate = preg_replace('/\{\$a->(\w+)\}/', '$1', $nametemplate);
                $nametemplates[$lang] = $nametemplate;
            }
        }

        // the "name_order" field allow users to override the English name order
        if (empty($item['name_order'])) {
            $nametemplate = reset($nametemplates);
        } else {
            $nametemplate = $item['name_order'];
            $pairs = array('Given name' => 'firstname',
                           'SURNAME' => 'lastname');
            $nametemplate = strtr($nametemplate, $pairs);
        }
        $defaultnametemplate = $nametemplate;

        $authornames = array();
        $fields = preg_grep('/^name_(given|surname)(.*)$/', array_keys($item));
        foreach ($fields as $field) {
            if (empty($item[$field])) {
                continue;
            }
            if (trim($item[$field])=='') {
                continue;
            }
            $i = 0;
            $name = '';
            $type = '';
            $lang = 'xx';
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
            if (empty($authornames[$i])) {
                $authornames[$i] = array();
            }
            if (empty($authornames[$i][$lang])) {
                $authornames[$i][$lang] = array();
            }
            $authornames[$i][$lang][$type] = self::textlib('strtotitle', $item[$field]);
        }

        // the full length of the English names
        $namelength = 0;

        ksort($authornames);
        foreach ($authornames as $i => $langs) {
            // remove names with no surname
            foreach ($langs as $lang => $name) {
                if (empty($name['surname'])) {
                    unset($langs[$lang]);
                }
            }

            // format names as multilang if necessary
            $count = count($langs);
            if ($count==0) {
                $authornames[$i] = '';
                continue;
            }
            if ($count==1) {
                $lang = key($langs);
                $name = reset($langs);
                if ($lang == 'en' || empty($nametemplates[$lang])) {
                    $nametemplate = $defaultnametemplate;
                } else {
                    $nametemplate = $nametemplates[$lang];
                }
                $pairs = array('firstname' => $name['given'],
                               'lastname' => $name['surname']);
                $name = strtr($nametemplate, $pairs);
                $namelength += self::textlib('strlen', $name);
                $authornames[$i] = $name;
                continue;
            }

            foreach ($langs as $lang => $name) {
                if ($lang == 'en' || empty($nametemplates[$lang])) {
                    $nametemplate = $defaultnametemplate;
                } else {
                    $nametemplate = $nametemplates[$lang];
                }
                $pairs = array('firstname' => $name['given'],
                               'lastname' => $name['surname']);
                $name = strtr($nametemplate, $pairs);
                if ($lang == 'en') {
                    if ($namelength) {
                        $namelength += 2; // comma and space
                    }
                    $namelength += self::textlib('strlen', $name);
                }
                $params = array('class' => 'multilang', 'lang' => $lang);
                $authornames[$i][$lang] = html_writer::tag('span', $name, $params);
            }
            $authornames[$i] = implode('', $authornames[$i]);
        }

        // for commercial presentations, we append the affiliation too
        if (empty($item['presentation_category'])) {
            $category = '';
        } else {
            $category = $item['presentation_category'];
        }

        $affiliation = array();
        if (strpos($category, 'Sponsored')) {

            $fields = preg_grep('/^affiliation(.*)$/', array_keys($item));
            foreach ($fields as $field) {
                if (empty($item[$field])) {
                    continue;
                }
                if (trim($item[$field])=='') {
                    continue;
                }
                $parts = explode('_', $field);
                if (count($parts) > 1) {
                    $lang = end($parts);
                } else {
                    $lang = 'xx';
                }
                if (strlen($lang)==2 && empty($affiliation[$lang])) {
                    $affiliation[$lang] = $item[$field];
                }
            }
        }

        if ($name = reset($affiliation)) {
            $namelength += self::textlib('strlen', $name);
        }

        if ($namelength > self::MAX_NAME_LENGTH) {
            $authornames = reset($authornames).' '.self::get_multilang_string('etal', 'block_maj_submissions');
        } else {
            $authornames = array_map('trim', $authornames);
            $authornames = array_filter($authornames);
            $authornames = implode(', ', $authornames);
        }

        if (isset($item['event_facilitator'])) {
            $authornames = trim($item['event_facilitator']);
        } else if ($authornames=='') {
            $authornames = get_string('noauthors', 'block_maj_submissions', $recordid);
        }

        if ($affiliation = self::multilang_string($affiliation)) {
            $authornames .= " ($affiliation)";
        }

        return html_writer::tag('span', $authornames, array('class' => 'authornames'));
    }
}
