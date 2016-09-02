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

            // data is collected in a database activity
            'collectcmid'       => 0,
            'collecttimestart'  => 0,
            'collecttimefinish' => 0,

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
            'publishtimefinish' => 0
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

        $dateformat = get_string('strftimerecent');

        $options = array();
        foreach (self::get_states() as $state) {
            $timestart = $state.'timestart';
            $timefinish = $state.'timefinish';
            if ($this->config->$timefinish - $this->config->$timestart < HOURSECS) {
                $name = get_string($state.'submissions', $plugin);
                switch ($state) {
                    case 'collect':
                    case 'publish':
                        $cmid = $state.'cmid';
                        if (isset($this->config->$cmid)) {
                            $cmid = $this->config->$cmid;
                            if (is_numeric($cmid) && $cmid > 0) {
                                $href = new moodle_url('/mod/data/view.php', array('id' => $cmid));
                                $name = html_writer::tag('a', $name, array('href' => $href));
                            }
                        }
                        break;
                        
                    case 'review':
                    case 'revise':
                        $sectionnum = $state.'sectionnum';
                        if (isset($this->config->$sectionnum)) {
                            $sectionnum = $this->config->$sectionnum;
                            if (is_numeric($sectionnum) && $sectionnum >= 0) { // 0 is allowed ;-)
                                $params = array('id' => $this->page->course->id, 'section' => $sectionnum);
                                $href = new moodle_url('/course/view.php', $params);
                                $name = html_writer::tag('a', $name, array('href' => $href));
                            }
                        }
                        break;
                }

                $date = userdate($this->config->$timestart, $dateformat).' - '.
                        userdate($this->config->$timefinish, $dateformat);

                $option = html_writer::tag('b', $name).
                          html_writer::empty_tag('br').
                          html_writer::tag('span', $date);

                $options[] = html_writer::tag('li', $option, array('class' => 'date'));
            }

        }
        if ($options = implode('', $options)) {
            $heading = get_string('importantdates', $plugin);
            $this->content->text .= html_writer::tag('h4', $heading, array('class' => 'importantdates'));
            $this->content->text .= html_writer::tag('ul', $options, array('class' => 'dates'));
        }

        return $this->content;
    }

    /**
     * get_states
     *
     * @return array
     */
    static public function get_states() {
        return array('collect', 'review', 'revise', 'publish');
    }

    /**
     * get_lang_code
     *
     * @return string
     */
    function get_lang_code() {
        static $lang = null;

        if (isset($lang)) {
            return $lang;
        }

        $lang = substr(current_language(), 0, 2);

        $namelength = 'namelength'.$lang;
        if (isset($this->config->$namelength)) {
            return $lang;
        }

        $lang = 'en';

        $namelength = 'namelength'.$lang;
        if (isset($this->config->$namelength)) {
            return $lang;
        }

        $lang = '';
        return $lang;
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
     * @param   integer  $textlength (optional, default=28)
     * @param   integer  $headlength (optional, default=10)
     * @param   integer  $taillength (optional, default=10)
     * @return  string
     */
    static function trim_text($text, $textlength=28, $headlength=10, $taillength=10) {
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
