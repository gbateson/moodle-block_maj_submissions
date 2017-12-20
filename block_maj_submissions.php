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

    protected $fixyearchar  = false;
    protected $fixmonthchar = false;
    protected $fixdaychar   = false;
    protected $multilang    = false;

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

            'registerdelegatestimestart'  => 0,
            'registerdelegatestimefinish' => 0,
            'registerdelegatescmid'       => 0,

            'registerpresenterstimestart'  => 0,
            'registerpresenterstimefinish' => 0,
            'registerpresenterscmid'       => 0,

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
                        case 'registerdelegates':
                        case 'registerpresenters':
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
        if (! $dateformat = $this->config->customdatefmt) {
            if (! $dateformat = $this->config->moodledatefmt) {
                $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
            }
            $dateformat = get_string($dateformat);
        }
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
                        case 'registerdelegates':
                        case 'registerpresenters':
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
                            html_writer::empty_tag('br').
                            html_writer::tag('span', $date);
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

        // If necessary, format the export, import and edit icons.
        // These icons will be printed next to the first heading in this block
        $icons = '';
        if ($this->user_can_edit()) {
            if ($countdates) {
                $icons .= ' '.$this->get_exportimport_icon($plugin, 'export', 'content',  'f/html');
                $icons .= ' '.$this->get_exportimport_icon($plugin, 'export', 'settings', 'i/export');
            }
            $icons .= ' '.$this->get_exportimport_icon($plugin, 'import', 'settings', 'i/import');
            if (empty($USER->editing)) {
                $icons .= ' '.$this->get_edit_icon($plugin, $courseid);
            }
        }

        // add quick links, if necessary
        if ($links = implode('', $links)) {
            $heading = $this->get_string('quicklinks', $plugin).$icons;
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'quicklinks'));
            $this->content->text .= html_writer::tag('ul', $links, array('class' => 'quicklinks'));
            $icons = ''; // to ensure we only print the icons once
        }

        // add important dates, if necessary
        if ($dates = implode('', $dates)) {
            $heading = $this->get_string('importantdates', $plugin).$icons;
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'importantdates'));
            $this->content->text .= html_writer::tag('ul', $dates,   array('class' => 'importantdates'));
            $icons = ''; // to ensure we only print the icons once
        }

        // add conference tools, if necessary
        if ($this->user_can_edit()) {
            $heading = $this->get_string('conferencetools', $plugin).$icons;
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'toollinks'));
            $this->content->text .= $this->get_tool_link($plugin, 'setupregistrations');
            $this->content->text .= $this->get_tool_link($plugin, 'setuppresentations');
            $this->content->text .= $this->get_tool_link($plugin, 'setupworkshops');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'data2workshop');
            $this->content->text .= $this->get_tool_link($plugin, 'createusers');
            $this->content->text .= $this->get_tool_link($plugin, 'setupvetting');
            $this->content->text .= html_writer::tag('p', '', array('class' => 'tooldivider'));
            $this->content->text .= $this->get_tool_link($plugin, 'workshop2data');
            $this->content->text .= $this->get_tool_link($plugin, 'setupevents');
            $this->content->text .= $this->get_tool_link($plugin, 'setupschedule');
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
            $removestart  = ($this->config->$timestart && (strftime('%H:%M', $this->config->$timestart)=='00:00'));
            $removefinish = ($this->config->$timefinish && (strftime('%H:%M', $this->config->$timefinish)=='23:55'));
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
            $date = (object)array(
                'open'  => $this->userdate($this->config->$timestart, $dateformat, $removetime, $removeyear),
                'close' => $this->userdate($this->config->$timefinish, $dateformat, $removetime, $removedate)
            );
            $date = $this->get_string('dateopenclose', $plugin, $date);
        } else if ($this->config->$timestart) {
            $date = $this->userdate($this->config->$timestart, $dateformat, $removestart, $removeyear);
            if ($this->config->$timestart < $timenow) {
                $date = $this->get_string('dateopenedon', $plugin, $date);
            } else {
                $date = $this->get_string('dateopenson', $plugin, $date);
            }
        } else if ($this->config->$timefinish) {
            $date = $this->userdate($this->config->$timefinish, $dateformat, $removefinish, $removeyear);
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
     * get_exportimport_icon
     *
     * @param string $plugin
     * @param string $action "import" or "export"
     * @param string $action "content" or "settings"
     * @param string $dir within "pix" dir where icon file is located
     * @return array
     */
    protected function get_exportimport_icon($plugin, $action, $type, $icon) {
        $title = get_string($action.$type, $plugin);
        $params = array('id' => $this->instance->id, 'sesskey' => sesskey());
        $href = new moodle_url("/blocks/maj_submissions/$action.$type.php", $params);
        return $this->get_icon($icon, $title, $href, $action.$type.'icon');
    }

    /**
     * multilang_userdate
     *
     * @param integer $date
     * @param string  $format
     * @param boolean $removetime (optional, default = false)
     * @param boolean $removedate (optional, default = REMOVE_NONE)
     * @return string representation of $date
     */
    public function multilang_userdate($date, $formatstring, $plugin='', $removetime=false, $removedate=self::REMOVE_NONE) {

        if ($this->multilang==false) {
            $format = get_string($formatstring, $plugin);
            return $this->userdate($date, $format, $removetime, $removedate);
        }

        $currentlanguage = current_language();

        // get all langs and locales on this Moodle site
        $locales = $this->get_string('locale', 'langconfig', null, true);

        $dates = array();
        foreach ($locales as $lang => $locale) {
            moodle_setlocale($locale);
            force_current_language($lang);
            $format = get_string($formatstring, $plugin);
            $dates[$lang] = userdate($date, $format);
            //$dates[$lang] = $this->userdate($date, $format, $removetime, $removedate);
        }

        if ($lang = $currentlanguage) {
            $locale = $locales[$lang];
            moodle_setlocale($locale);
            force_current_language($lang);
        }

        return $this->multilang_string($dates);
    }

    /**
     * multilang_userdate
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
            moodle_setlocale($locale);
            force_current_language($lang);
            $times[$lang] = format_time($secs);
        }

        if ($lang = $currentlanguage) {
            $locale = $locales[$lang];
            moodle_setlocale($locale);
            force_current_language($lang);
        }

        return $this->multilang_string($times);
    }

    /**
     * userdate
     *
     * @param integer $date
     * @param string  $format
     * @param boolean $removetime
     * @param boolean $removedate (optional, default = REMOVE_NONE)
     * @return string representation of $date
     */
    protected function userdate($date, $format, $removetime, $removedate=self::REMOVE_NONE) {

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

        // set the $year, $month and $day characters for CJK languages
        list($year, $month, $day) = $this->get_date_chars();

        // add year, month and day characters for CJK languages
        if ($this->fixyearchar || $this->fixmonthchar || $this->fixdaychar) {
            $replace = array();
            if ($this->fixyearchar) {
                $replace['%y'] = '%y'.$year;
                $replace['%Y'] = '%Y'.$year;
            }
            if ($this->fixmonthchar) {
                $replace['%b'] = '%b'.$month;
                $replace['%h'] = '%h'.$month;
            }
            if ($this->fixdaychar) {
                $replace['%d'] = '%d'.$day;
            }
            $format = strtr($format, $replace);
        }

        if ($fixmonth = ($this->config->fixmonth && is_numeric(strpos($format, '%m')))) {
            $format = str_replace('%m', 'MM', $format);
        }
        if ($fixday = ($this->config->fixday && is_numeric(strpos($format, '%d')))) {
            $format = str_replace('%d', 'DD', $format);
        }
        if ($fixhour = ($this->config->fixhour && is_numeric(strpos($format, '%I')))) {
            $format = str_replace('%I', 'II', $format);
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

        return $userdate;
    }

    /**
     * check_date_fixes
     */
    protected function check_date_fixes() {

        if (! $dateformat = $this->config->customdatefmt) {
            if (! $dateformat = $this->config->moodledatefmt) {
                $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
            }
            $dateformat = get_string($dateformat);
        }

        $date = strftime($dateformat, time());

        // set the $year, $month and $day characters for CJK languages
        list($year, $month, $day) = $this->get_date_chars();

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
     * get_date_chars
     *
     * @return array($year, $month, $day)
     */
    protected function get_date_chars() {
        switch (substr(current_language(), 0, 2)) {
            case 'ja': return array('年', '月', '日'); // Japanese
            case 'ko': return array('년', '월', '일'); // Korean
            case 'zh': return array('年', '月', '日'); // Chinese
            default  : return array('',  '',   '');
        }
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
     * @return string, if $this->multilang is set, return the "multilang" verison of the required string;
     *                 i.e. <span lang="xx" class="multilang">...></span><span...>...</span>
     *                 otherwise, return Moodle's standard get_string() output
     */
    public function get_string($identifier, $component='', $a=null, $returnarray=false) {

        if ($this->multilang==false) {
            return get_string($identifier, $component, $a);
        }

        $strman = get_string_manager();
        $langs = $strman->get_list_of_translations();
        $langs = array_keys($langs);

        // sort $langs, so that "en" is first
        // and parent langs appear before child langs
        usort($langs, array($this, 'usort_langs'));

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
                if (array_search($text, $texts)===false) {
                    $texts[$lang] = $text;
                }
            }
        }

        if ($returnarray) {
            return $texts;
        }

        return $this->multilang_string($texts);
    }

    /**
     * multilang_string
     *
     * @param array $items
     * @return multilang string version of $items
     */
    public function multilang_string($items) {

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

        // special case - this item is unique in only one language pack
        if (count($items)==1) {
            return reset($items);
        }

        // format items as multilang $items
        foreach ($items as $lang => $item) {
            $params = array('lang' => $lang, 'class' => 'multilang');
            $items[$lang] = html_writer::tag('span', $item, $params);
        }

        return implode('', $items);
    }

    /**
     * usort_langs
     *
     * sort $langs, so that "en" is first
     * and parent langs (length = 2)
     * appear before child langs (length > 2)
     */
    public function usort_langs($a, $b) {
        if ($a=='en') {
            return -1;
        }
        if ($b=='en') {
            return 1;
        }
        // compare parent langs
        $a_parent = substr($a, 0, 2);
        $b_parent = substr($b, 0, 2);
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
            if (isset($config->registerdelegatescmid)) {
                $cmids[] = $config->registerdelegatescmid;
            }
            if (isset($config->registerpresenterscmid)) {
                $cmids[] = $config->registerpresenterscmid;
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
            array('conference',
                  'workshops',
                  'reception'),
            array('collectpresentations',
                  'collectworkshops',
                  'collectsponsoreds'),
            array('review',
                  'revise',
                  'publish'),
            array('registerpresenters',
                  'registerdelegates',
                  'registerearly')
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
            'kr' => 'k0', // Korean
         'pt_br' => 'pt_br', // Brazilian Portuguese
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
    static public function get_sectionlink($courseid, $sectionnum, $coursedisplay) {
        $url = new moodle_url('/course/view.php', array('id' => $courseid));
        if ($coursedisplay==COURSE_DISPLAY_SINGLEPAGE) {
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
        if ($name = self::filter_text($section->name)) {
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
    static function trim_text($text, $textlength=42, $headlength=16, $taillength=16) {
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
            // use call_user_func() to prevent syntax error in PHP 5.2.x
            $class = context_helper::get_class_for_level($contextlevel);
            return call_user_func(array($class, 'instance'), $instanceid, $strictness);
        } else {
            return self::context($contextlevel, $instanceid);
        }
    }
}
