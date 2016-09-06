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
            'title'             => get_string('blockname', $plugin),

            'currentstate'      => 0, // 0 = unset
                                      // 1 = collect
                                      // 2 = review
                                      // 3 = revise
                                      // 4 = publish
                                      // 5 = register

            // data is collected in a database activity
            'collectcmid'       => 0,
            'collecttimestart'  => 0,
            'collecttimefinish' => 0,
            'collecttimeworkshopstart'   => 0,
            'collecttimeworkshopfinish'  => 0,
            'collecttimesponsoredstart'  => 0,
            'collecttimesponsoredfinish' => 0,

            // fields used to filter data into workshops
            // e.g. submissiontype AND submissionlanguage
            'filterfields'      => array(),

            // data is reviewed,
            // during the start and finish times,
            // in one or more WORKSHOP activities
            'reviewsectionnum'  => 0,
            'reviewcmids'       => array(),
            'reviewtimestart'   => 0,
            'reviewtimefinish'  => 0,

            // data is revised,
            // during the start and finish times,
            // in one or more ASSIGNMENT activities
            'revisesectionnum'  => 0,
            'revisecmids'       => array(),
            'revisetimestart'   => 0,
            'revisetimefinish'  => 0,

            // data is published in a DATABASE activity
            'publishcmid'       => 0,
            'publishtimestart'  => 0,
            'publishtimefinish' => 0,

            // delegates are registered in a DATABASE activity
            'registercmid'       => 0,
            'registertimestart'  => 0,
            'registertimefinish' => 0,

            // date settings
            'moodledatefmt'      => 'strftimerecent', // 11 Nov, 10:12
            'customdatefmt'      => '',
            'fixmonth'           => 1, // 0=no, 1=remove leading "0" from months
            'fixday'             => 1, // 0=no, 1=remove leading "0" from days
            'fixhour'            => 1  // 0=no, 1=remove leading "0" from hours
        );

        if (empty($this->config)) {
            $this->config = new stdClass();
        }

        foreach ($defaults as $name => $items) {
            if (! isset($this->config->$name)) {
                $this->config->$name = $items;
            }
        }

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

        // quick check to filter out students
        if (! has_capability('moodle/course:manageactivities', $this->context)) {
            return $this->content;
        }

        $plugin = 'block_maj_submissions';

        if (! $dateformat = $this->config->customdatefmt) {
            if (! $dateformat = $this->config->moodledatefmt) {
                $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
            }
            $dateformat = get_string($dateformat);
        }
        $time = time();

        $dates = array();
        $options = array();

        $types = array(
            'collect'  => array('', 'workshop', 'sponsored'),
            'review'   => array(''),
            'revise'   => array(''),
            'publish'  => array(''),
            'register' => array('', 'presenter')
        );
        foreach ($types as $type => $times) {

            // set up $url
            $url = '';
            switch ($type) {
                case 'collect':
                case 'publish':
                case 'register':
                    $cmid = $type.'cmid';
                    if (isset($this->config->$cmid)) {
                        $cmid = $this->config->$cmid;
                        if (is_numeric($cmid) && $cmid > 0) {
                            $url = new moodle_url('/mod/data/view.php', array('id' => $cmid));
                        }
                    }
                    break;

                case 'review':
                case 'revise':
                    $sectionnum = $type.'sectionnum';
                    if (isset($this->config->$sectionnum)) {
                        $sectionnum = $this->config->$sectionnum;
                        if (is_numeric($sectionnum) && $sectionnum >= 0) { // 0 is allowed ;-)
                            $params = array('id' => $this->page->course->id, 'section' => $sectionnum);
                            $url = new moodle_url('/course/view.php', $params);
                        }
                    }
                    break;
            }

            foreach ($times as $time) {

                $timestart = $type.$time.'timestart';
                $timefinish = $type.$time.'timefinish';

                $removestart  = (isset($this->config->$timestart) && strftime('%H:%M', $this->config->$timestart)=='00:00');
                $removefinish = (isset($this->config->$timefinish) && strftime('%H:%M', $this->config->$timefinish)=='23:55');
                $removetime   = ($removestart && $removefinish);

                if ($this->config->$timestart && $this->config->$timefinish) {
                    $date = $this->userdate($this->config->$timestart, $dateformat, $removetime).
                            ' - '.
                            $this->userdate($this->config->$timefinish, $dateformat, $removetime);
                } else if ($this->config->$timestart) {
                    $date = $this->userdate($this->config->$timestart, $dateformat, $removestart);
                    if ($this->config->$timestart < $time) {
                        $date = get_string('openedon', $plugin, $date);
                    } else {
                        $date = get_string('openson', $plugin, $date);
                    }
                } else if ($this->config->$timefinish) {
                    $date = $this->userdate($this->config->$timefinish, $dateformat, $removefinish);
                    if ($this->config->$timefinish < $time) {
                        $date = get_string('closedon', $plugin, $date);
                    } else {
                        $date = get_string('closeson', $plugin, $date);
                    }
                } else {
                    $date = '';
                }

                if ($date) {
                    $text = html_writer::tag('b', get_string($type.$time.'time', $plugin));
                    if ($url) {
                        $text = html_writer::tag('a', $text, array('href' => $url));
                    }
                    $date = $text.html_writer::empty_tag('br').$date;

                    $class = 'date';
                    if (($time >= $this->config->$timestart) && ($time <= $this->config->$timefinish)) {
                        $class .= ' open';
                    }
                    if ($this->config->$timestart && ($this->config->$timestart > $time)) {
                        $class .= ' early';
                    }
                    if ($this->config->$timefinish && ($this->config->$timefinish < $time)) {
                        $class .= ' late';
                    }
                    $dates[] = html_writer::tag('li', $date, array('class' => $class));
                }
            }
        }

        if ($dates = implode('', $dates)) {
            $heading = get_string('importantdates', $plugin);
            if ($this->user_can_edit() && $USER->editing==0) {
                $heading .= ' '.$this->get_edit_icon();
            }
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'importantdates'));
            $this->content->text .= html_writer::tag('ul', $dates,   array('class' => 'importantdates'));
        }

        if ($this->user_can_edit()) {
            $this->content->text .= html_writer::tag('h4', get_string('conversiontools', $plugin), array('class' => 'toollinks'));
            $this->content->text .= $this->get_tool_link($plugin, 'data2workshop');
            $this->content->text .= $this->get_tool_link($plugin, 'workshop2assign');
            $this->content->text .= $this->get_tool_link($plugin, 'assign2data');
        }
        return $this->content;
    }

    /**
     * get_tool_link
     *
     * @param string $type
     * @return array
     */
    protected function get_tool_link($plugin, $type) {

        $text = get_string('tool'.$type, $plugin);
        $text = html_writer::tag('b', $text);

        $desc = get_string('tool'.$type.'_desc', $plugin);

        $params = array('id' => $this->page->course->id,
                        'bui' => $this->instance->id);
        $link = new moodle_url("/blocks/maj_submissions/tools/$type.php", $params);

        $link = html_writer::tag('a', $text, array('href' => $link)).
                html_writer::empty_tag('br').
                html_writer::tag('span', $desc);

        return html_writer::tag('p', $link, array('class' => 'toollink'));
    }

    /**
     * get_edit_icon
     *
     * @return array
     */
    protected function get_edit_icon() {
        global $OUTPUT;

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

        // the edit icon image
        $params = array('src' => $OUTPUT->pix_url('t/edit'),
                        'title' => get_string('editsettings'));
        $icon = html_writer::empty_tag('img', $params);

        // return edit icon image
        // linking to edit enable page
        // with redirect to block settings page
        return html_writer::tag('a', $icon, array('href' => $href, 'class' => 'editicon'));
    }

    /**
     * get_state_types
     *
     * @return array
     */
    static public function get_state_types() {
        return array(1 => 'collect',
                     2 => 'review',
                     3 => 'revise',
                     4 => 'publish',
                     5 => 'register');
    }

    /**
     * get_state_name
     *
     * @param string  $plugin name of plugin
     * @param integer $type
     * @return string name of $type in current locale
     */
    static public function get_state_name($plugin, $type) {
        if ($type=='register') {
            return get_string($type.'participation', $plugin);
        } else {
            return get_string($type.'submissions', $plugin);
        }
    }

    /**
     * get_state_names
     *
     * @param string $plugin name of plugin
     * @param array  $prepend (optional, default=null)
     * @param array  $append  (optional, default=null)
     * @return array
     */
    static public function get_state_names($plugin, $prepend=null, $append=null) {
        $types = self::get_state_types();
        foreach ($types as $state => $type) {
            $names[$state] = self::get_state_name($plugin, $type);
        }
        if ($prepend) {
            $names = ($prepend + $names);
        }
        if ($append) {
            $names = ($names + $append);
        }
        return $names;
    }

    /**
     * userdate
     *
     * @param integer $date
     * @param string  $format (optional, default='')
     * @param integer $timezone (optional, default = 99)
     * @return string representation of $date
     */
    protected function userdate($date, $format, $removetime) {

        $current_language = substr(current_language(), 0, 2);

        if ($removetime) {
            // http://php.net/manual/en/function.strftime.php
            $search = '/[ :\.,-]*[\[\{\(]*?%[HkIlMpPrRSTX][\)\}\]]?/';
            $format = preg_replace($search, '', $format);
        }

        if ($current_language=='ja' || $current_language=='zh') {
            $replace = array();
            if (strpos($format, '年')===false) {
                $replace['%Y'] = '%Y年';
                $replace['%y'] = '%y年';
            }
            if (strpos($format, '月')===false) {
                $replace['%m'] = '%m月';
                $replace['%b'] = '%b月';
                $replace['%h'] = '%h月';
            }
            if (strpos($format, '日')===false) {
                $replace['%d'] = '%d日';
            }
            if (count($replace)) {
                $format = strtr($format, $replace);
            }
        } else if ($current_language=='ko') {
            $replace = array();
            if (strpos($format, '년')===false) {
                $replace['%Y'] = '%Y년';
                $replace['%y'] = '%y년';
            }
            if (strpos($format, '월')===false) {
                $replace['%m'] = '%m월';
                $replace['%b'] = '%b월';
                $replace['%h'] = '%h월';
            }
            if (strpos($format, '일')===false) {
                $replace['%d'] = '%d일';
            }
            if (count($replace)) {
                $format = strtr($format, $replace);
            }
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
        $strlen = self::textlib('strlen', $text);
        if ($strlen > $textlength) {
            $head = self::textlib('substr', $text, 0, $headlength);
            $tail = self::textlib('substr', $text, $strlen - $taillength, $taillength);
            $text = $head.' ... '.$tail;
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
