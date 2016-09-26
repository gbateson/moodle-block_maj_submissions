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

    protected $fixyearchar  = false;
    protected $fixmonthchar = false;
    protected $fixdaychar   = false;
    protected $multilang    = false;

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
            'displaydates'          => 1,  // 0=no, 1=yes

            // conference events
            'conferencecmid'       => 0,
            'conferencetimestart'  => 0,
            'conferencetimefinish' => 0,

            'workshopscmid'        => 0,
            'workshopstimestart'   => 0,
            'workshopstimefinish'  => 0,

            'receptioncmid'        => 0,
            'receptiontimestart'   => 0,
            'receptiontimefinish'  => 0,

            // data is collected in a DATABASE activity
            'collectcmid'          => 0,
            'collecttimestart'     => 0,
            'collecttimefinish'    => 0,
            'collectworkshoptimestart'   => 0,
            'collectworkshoptimefinish'  => 0,
            'collectsponsoredtimestart'  => 0,
            'collectsponsoredtimefinish' => 0,

            // fields used to filter data into workshops
            // e.g. submissiontype AND submissionlanguage
            'filterfields'         => array(),

            // data is reviewed,
            // in one or more WORKSHOP activities
            'reviewsectionnum'  => 0,
            'reviewtimestart'   => 0,
            'reviewtimefinish'  => 0,
            'reviewcmids'       => array(),

            // data is revised,
            // in one or more ASSIGNMENT activities
            'revisesectionnum'  => 0,
            'revisetimestart'   => 0,
            'revisetimefinish'  => 0,
            'revisecmids'       => array(),

            // data is published in a DATABASE activity
            'publishcmid'       => 0,
            'publishtimestart'  => 0,
            'publishtimefinish' => 0,

            // delegates are registered in a DATABASE activity
            'registercmid'       => 0,
            'registertimestart'  => 0,
            'registertimefinish' => 0,
            'registerpresentertimestart'  => 0,
            'registerpresentertimefinish' => 0,

            // date settings
            'moodledatefmt'      => 'strftimerecent', // 11 Nov, 10:12
            'customdatefmt'      => '%b %d (%a) %H:%M', // Nov 11th (Wed) 10:12
            'fixmonth'           => 1, // 0=no, 1=remove leading "0" from months
            'fixday'             => 1, // 0=no, 1=remove leading "0" from days
            'fixhour'            => 1, // 0=no, 1=remove leading "0" from hours
            'manageevents'       => 0  // 0=no, 1=yes
        );

        if (empty($this->config)) {
            $this->config = new stdClass();
        }

        foreach ($defaults as $name => $items) {
            if (! isset($this->config->$name)) {
                $this->config->$name = $items;
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

        // update calendar events, if required
        if ($config->manageevents) {
            $events = array();

            $modinfo = get_fast_modinfo($this->page->course);
            foreach ($this->get_timetypes() as $types) {
                foreach ($types as $type) {

                    // set up $cmid, $modname, $instance and $visible
                    $cmid        = '';
                    $modname     = '';
                    $instance    =  0;
                    $visible     =  1;
                    $description = '';
                    switch ($type) {
                        case 'collect':
                        case 'collectworkshop':
                        case 'collectsponsored':
                            $cmid = 'collectid';
                            break;
                        case 'conference':
                        case 'workshops':
                        case 'reception':
                        case 'publish':
                            $cmid = $type.'cmid';
                            break;
                        case 'register':
                        case 'registerpresenter':
                            $cmid = 'registercmid';
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
                $this->add_events($events, $this->page->course, $plugin);
            }
        }

        //  save config settings as usual
        return parent::instance_config_save($config, $pinned);
    }

    /**
     * get_content
     *
     * @return xxx
     */
    function get_content() {
        global $USER;

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

        // get $dateformat
        if (! $dateformat = $this->config->customdatefmt) {
            if (! $dateformat = $this->config->moodledatefmt) {
                $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
            }
            $dateformat = get_string($dateformat);
        }
        $timenow = time();

        // cache $coursedisplay (single/multi page)
        if (isset($this->page->course->coursedisplay)) {
            $coursedisplay = $this->page->course->coursedisplay;
        } else if (function_exists('course_get_format')) {
            $coursedisplay = course_get_format($this->page->course);
            $coursedisplay = $coursedisplay->get_format_options();
            $coursedisplay = $coursedisplay['coursedisplay'];
        } else {
            $coursedisplay = COURSE_DISPLAY_SINGLEPAGE; // =0
        }

        $modinfo = get_fast_modinfo($this->page->course);
        foreach ($this->get_timetypes() as $types) {

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
                        case 'collect':
                        case 'collectworkshop':
                        case 'collectsponsored':
                            $cmid = 'collectcmid';
                            break;

                        case 'conference':
                        case 'workshops':
                        case 'reception':
                        case 'publish':
                            $cmid = $type.'cmid';
                            break;

                        case 'review':
                        case 'revise':
                            $sectionnum = $type.'sectionnum';
                            break;

                        case 'register':
                        case 'registerpresenter':
                            $cmid = 'registercmid';
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
                            $params = array('id' => $this->page->course->id);
                            if ($coursedisplay==COURSE_DISPLAY_MULTIPAGE) {
                                $params['section'] = $sectionnum;
                                $anchor = null;
                            } else {
                                $anchor = "section-$sectionnum";
                            }
                            $url = new moodle_url('/course/view.php', $params, $anchor);
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
                    $text = html_writer::tag('b', $text).html_writer::empty_tag('br');
                    $date = $text.html_writer::tag('span', $date);
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
        // These icons will be printed next to the "Important Dates" heading
        // if there any important dates.
        // Otherwise, they will appear next to the "Conference tools" heading
        $icons = '';
        if ($this->user_can_edit()) {
            if ($countdates) {
                $icons .= ' '.$this->get_exportimport_icon($plugin, 'export', 'content',  'f/html');
                $icons .= ' '.$this->get_exportimport_icon($plugin, 'export', 'settings', 'i/export');
            }
            $icons .= ' '.$this->get_exportimport_icon($plugin, 'import', 'settings', 'i/import');
            if ($USER->editing==0) {
                $icons .= ' '.$this->get_edit_icon($plugin);
            }
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
            $this->content->text .= $this->get_tool_link($plugin, 'setupregistration');
            $this->content->text .= $this->get_tool_link($plugin, 'setupsubmissions');
            $this->content->text .= $this->get_tool_link($plugin, 'data2workshop');
            $this->content->text .= $this->get_tool_link($plugin, 'workshop2assign');
            $this->content->text .= $this->get_tool_link($plugin, 'assign2data');
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

        $removestart  = ($this->config->$timestart && (strftime('%H:%M', $this->config->$timestart)=='00:00'));
        $removefinish = ($this->config->$timefinish && (strftime('%H:%M', $this->config->$timefinish)=='23:55'));
        $removetime   = ($removestart && $removefinish);
        $removedate   = false;

        $date = '';
        if ($this->config->$timestart && $this->config->$timefinish) {
            if (($this->config->$timefinish - $this->config->$timestart) < DAYSECS) {
                // the dates are less than 24 hours apart, so don't remove times ...
                $removetime = false;
                // ... but remove the finish date ;-)
                $removedate = true;
            }
            $date = (object)array(
                'open'  => $this->userdate($this->config->$timestart, $dateformat, $removetime),
                'close' => $this->userdate($this->config->$timefinish, $dateformat, $removetime, $removedate)
            );
            $date = $this->get_string('dateopenclose', $plugin, $date);
        } else if ($this->config->$timestart) {
            $date = $this->userdate($this->config->$timestart, $dateformat, $removestart);
            if ($this->config->$timestart < $timenow) {
                $date = $this->get_string('dateopenedon', $plugin, $date);
            } else {
                $date = $this->get_string('dateopenson', $plugin, $date);
            }
        } else if ($this->config->$timefinish) {
            $date = $this->userdate($this->config->$timefinish, $dateformat, $removefinish);
            if ($this->config->$timefinish < $timenow) {
                $date = $this->get_string('dateclosedon', $plugin, $date);
            } else {
                $date = $this->get_string('datecloseson', $plugin, $date);
            }
        }
        return $date;
    }

    /**
     * get_timetypes
     *
     * @return array
     */
    protected function get_timetypes() {
        return array(
            array('conference', 'workshops',       'reception'),
            array('collect',    'collectworkshop', 'collectsponsored'),
            array('review',     'revise',          'publish'),
            array('register',   'registerpresenter'),
        );
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
        $link = new moodle_url("/blocks/maj_submissions/tools/$type.php", $params);

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
    protected function get_icon($src, $title, $href, $class) {
        global $OUTPUT;
        $params = array('src' => $OUTPUT->pix_url($src), 'title' => $title);
        $icon = html_writer::empty_tag('img', $params);
        return html_writer::tag('a', $icon, array('href' => $href, 'class' => "icon $class"));
    }

    /**
     * get_edit_icon
     *
     * @return array
     */
    protected function get_edit_icon($plugin) {

        // the "return" url which leads to the block edit page
        $params = array('id' => $this->page->course->id,
                        'sesskey' => sesskey(),
                        'bui_editid' => $this->instance->id);
        $href = new moodle_url('/course/view.php', $params);

        // the URL to enable editing and redirect to the block edit page
        $params = array('id' => $this->page->course->id,
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
    protected function get_exportimport_icon($plugin, $action, $type, $path) {
        $title = get_string($action.$type, $plugin);
        $params = array('id' => $this->instance->id, 'sesskey' => sesskey());
        $href = new moodle_url("/blocks/maj_submissions/$action.$type.php", $params);
        return $this->get_icon($path, $title, $href, $action.$type.'icon');
    }

    /**
     * userdate
     *
     * @param integer $date
     * @param string  $format
     * @param boolean $removetime
     * @param boolean $removedate (optional, default = false)
     * @return string representation of $date
     */
    protected function userdate($date, $format, $removetime, $removedate=false) {

        $current_language = substr(current_language(), 0, 2);

        if ($removetime) {
            // http://php.net/manual/en/function.strftime.php
            $search = '/[ :,\-\.\/]*[\[\{\(]*?%[HkIlMpPrRSTX][\)\}\]]?/';
            $format = preg_replace($search, '', $format);
        }

        if ($removedate) {
            // http://php.net/manual/en/function.strftime.php
            $search = '/[ :,\-\.\/]*[\[\{\(]*?%[AadejuwbBhmCgGyY][\)\}\]]?/';
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
                if ($current_language=='en') {
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

        if ($day && ! preg_match("/[0-9]+$year/", $date)) {
            $this->fixyearchar = true;
        }
        if ($day && ! preg_match("/[0-9]+$month/", $date)) {
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
     * get_string
     *
     * @return string, if $this->multilang is set, return the "multilang" verison of the required string;
     *                 otherwise, return Moodle's standard get_string() output
     */
    public function get_string($identifier, $component='', $a=null, $lazyload=false) {
        if ($this->multilang==false) {
            return get_string($identifier, $component, $a, $lazyload);
        }

        $strman = get_string_manager();
        $langs = $strman->get_list_of_translations();
        $langs = array_keys($langs);

        $spans = array();
        foreach ($langs as $lang) {
            $strings = $strman->load_component_strings($component, $lang);
            if ($strings[$identifier]) {
                $string = $strman->get_string($identifier, $component, $a, $lang);
                $params = array('lang' => $lang, 'class' => 'multilang');
                $spans[] = html_writer::tag('span', $string, $params);
            }
        }

        return implode('', $spans);
    }

    /**
     * filter_text
     *
     * @param string $text
     * @return string
     */
    static public function filter_text($text) {
        global $COURSE, $PAGE;

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
