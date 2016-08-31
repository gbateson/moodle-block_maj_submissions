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
            'reviewsectionid'   => 0,
            'reviewcmids'       => array(),
            'reviewtimestart'   => 0,
            'reviewtimefinish'  => 0,

            // data is revised,
            // during the start and finish times,
            // in one or more ASSIGNMENT activities
            'revisesectionid'   => 0,
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
        global $CFG, $COURSE, $DB, $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = (object)array(
            'text' => '',
            'footer' => ''
        );

        if (empty($this->instance)) {
            return $this->content; // shouldn't happen !!
        }

        if (empty($COURSE)) {
            return $this->content; // shouldn't happen !!
        }

        if (empty($COURSE->context)) {
            $COURSE->context = self::context(CONTEXT_COURSE, $COURSE->id);
        }

        // quick check to filter out students
        if (! has_capability('moodle/grade:viewall', $COURSE->context)) {
            return $this->content;
        }

        $plugin = 'block_maj_submissions';

        $this->content = 'MAJ Submissions content';
        return $this->content;
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
}
