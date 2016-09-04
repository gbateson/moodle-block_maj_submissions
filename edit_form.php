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
 * blocks/maj_submissions/edit_form.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * block_maj_submissions_mod_form
 *
 * @copyright 2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage taskchain
 */
class block_maj_submissions_edit_form extends block_edit_form {

    /**
     * specific_definition
     *
     * @param object $mform
     * @return void, but will update $mform
     */
    protected function specific_definition($mform) {

        $this->set_form_id($mform, get_class($this));

        // cache the plugin name, because
        // it is quite long and we use it a lot
        $plugin = 'block_maj_submissions';

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'title');
        //-----------------------------------------------------------------------------

        $name = 'description';
        $label = get_string($name);
        $text = get_string('blockdescription', $plugin);
        $element = $mform->addElement('static', $name, $label, $text);

        $name = 'title';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('text', $config_name, $label, array('size' => 50));
        $mform->setType($config_name, PARAM_TEXT);
        $mform->setDefault($config_name, $this->get_original_value($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'state');
        //-----------------------------------------------------------------------------

        $name = 'currentstate';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none'),
            1 => get_string('collect', $plugin),
            2 => get_string('review',  $plugin),
            3 => get_string('revise',  $plugin),
            4 => get_string('publish', $plugin),
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->get_original_value($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'collectsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'collect');
        $this->add_cmid($mform, $plugin, 'data', 'collectcmid');
        $this->add_repeat_elements($mform, $plugin, 'filterfields');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'reviewsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'review');
        $this->add_sectionnum($mform, $plugin, 'reviewsectionnum');
        $this->add_repeat_elements($mform, $plugin, 'reviewcmids');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'revisesubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'revise');
        $this->add_sectionnum($mform, $plugin, 'revisesectionnum');
        $this->add_repeat_elements($mform, $plugin, 'revisecmids');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'publishsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'publish');
        $this->add_cmid($mform, $plugin, 'data', 'publishcmid');
    }

    /**
     * set_form_id
     *
     * @param  object $mform
     * @param  string $id
     * @return mixed default value of setting
     */
    protected function set_form_id($mform, $id) {
        $attributes = $mform->getAttributes();
        $attributes['id'] = $id;
        $mform->setAttributes($attributes);
    }

    /**
     * get the value of a block config setting
     * either the NEW value from the incoming form data
     * or the OLD value from the block->config object
     *
     * @param  object $mform of setting
     * @param  string $name of element
     * @param  mixed  $default (optional, default=NULL)
     * @return mixed  the value of the required $mform element
     */
    protected function get_value($mform, $name, $paramtype=PARAM_INT, $default=null) {
        $config_name = 'config_'.$name;
        $value = optional_param($config_name, null, $paramtype);
        if (isset($value)) {
            return $value;
        } else {
            return $this->get_original_value($name, $default);
        }
    }

    /**
     * get original value for a config setting in this block
     *
     * @param  string $name of setting
     * @param  mixed  $default (optional, default=NULL)
     * @return mixed default value of setting
     */
    protected function get_original_value($name, $default=null) {
        if (isset($this->block->config->$name)) {
            return $this->block->config->$name;
        } else {
            return $default;
        }
    }

    /**
     * add_header
     *
     * @param object  $mform
     * @param string  $component
     * @param string  $name of string
     * @param boolean $expanded (optional, default=TRUE)
     * @return void, but will update $mform
     */
    protected function add_header($mform, $component, $name, $expanded=true) {
        $label = get_string($name, $component);
        $mform->addElement('header', $name, $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
        }
    }

    /**
     * add_time_startfinish
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $type
     * @return void, but will update $mform
     */
    protected function add_time_startfinish($mform, $plugin, $type) {

        $name = $type.'timestart';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, $label);
        $mform->setDefault($config_name, $this->get_original_value($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = $type.'timefinish';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, $label);
        $mform->setDefault($config_name, $this->get_original_value($name));
        $mform->addHelpButton($config_name, $name, $plugin);
    }

    /**
     * add_cmid
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $type
     * @param string  $name
     * @return void, but will update $mform
     */
    protected function add_cmid($mform, $plugin, $type, $name) {
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = $this->get_options_cmids($mform, $plugin, 'data');
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->get_original_value($name));
        $mform->addHelpButton($config_name, $name, $plugin);
    }

    /**
     * add_sectionnum
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name
     * @return void, but will update $mform
     */
    protected function add_sectionnum($mform, $plugin, $name) {
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = $this->get_options_sectionnum($mform, $plugin);
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->get_original_value($name));
        $mform->addHelpButton($config_name, $name, $plugin);
    }

    /**
     * add_repeat_elements
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name
     * @return void, but will update $mform
     */
    protected function add_repeat_elements($mform, $plugin, $name) {
        global $OUTPUT;
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $get_options = 'get_options_'.$name;
        $options = $this->$get_options($mform, $plugin);
        $elements = array($mform->createElement('select', $config_name, $label, $options));
        $repeats = count($this->block->config->$name);
        $options = array($config_name => array('type' => PARAM_INT, 'helpbutton' => array($name, $plugin)));
        $buttontext = get_string('add'.$name, $plugin, 1);
        $this->repeat_elements($elements, $repeats, $options, 'count'.$name, 'add'.$name, 1, $buttontext);
    }

    /**
     * get_options_sectionnum
     *
     * @param object $mform
     * @param string $plugin
     * @return array($sectionnum => $sectionname) of sections in this course
     */
    protected function get_options_sectionnum($mform, $plugin) {
        $options = array();
        $course = $this->get_course();
        $sections = get_fast_modinfo($course)->get_section_info_all();
        foreach ($sections as $sectionnum => $section) {
            if ($name = $this->get_sectionname($course, $section)) {
                $options[$sectionnum] = $name;
            } else {
                $options[$sectionnum] = $this->get_sectionname_default($course, $sectionnum);
            }
        }
        return $this->format_select_options($plugin, $options, 'section');
    }

    /**
     * get_sectionname
     *
     * names longer than $namelength will be trancated to to HEAD ... TAIL
     * where the number of characters in HEAD is $headlength
     * and the number of characters in TIAL is $taillength
     *
     * @param object   $course
     * @param object   $section
     * @param integer  $namelength of section name (optional, default=28)
     * @param integer  $headlength of head of section name (optional, default=10)
     * @param integer  $taillength of tail of section name (optional, default=10)
     * @return string  name of $section
     */
    protected function get_sectionname($course, $section, $namelength=28, $headlength=10, $taillength=10) {

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
            return block_maj_submissions::trim_text($name, $namelength, $headlength, $taillength);
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
    protected function get_sectionname_default($course, $sectionnum, $dateformat='%b %d') {

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
     * get_options_filterfields
     *
     * @param object $mform
     * @param string $plugin
     * @return array($fieldid => $fieldname) of fields from the collectcmid for this block
     */
    protected function get_options_filterfields($mform, $plugin) {
        global $DB;
        if ($cmid = $this->get_value($mform, 'collectcmid')) {
            $dataid = $this->get_course_modinfo()->get_cm($cmid)->instance;
            $options = $DB->get_records_menu('data_fields', array('dataid' => $dataid), null, 'id,name');
        } else {
            $options = false;
        }
        if ($options==false) {
            $options = array();
        }
        return $this->format_select_options($plugin, $options, 'field');
    }

    /**
     * get_options_reviewcmids
     *
     * @param object $mform
     * @param string $plugin
     * @return array($cmid => $cmname) of fields from the reviewsectionnum for this block
     *                                 or from the whole course (if reviewsectionnum==0)
     */
    protected function get_options_reviewcmids($mform, $plugin) {
        $sectionnum = $this->get_value($mform, 'reviewsectionnum');
        return $this->get_options_cmids($mform, $plugin, 'workshop', $sectionnum);
    }

    /**
     * get_options_revisecmids
     *
     * @param object $mform
     * @param string $plugin
     * @return array($cmid => $cmname) of fields from the revisesectionnum for this block
     *                                 or from the whole course (if revisesectionnum==0)
     */
    protected function get_options_revisecmids($mform, $plugin) {
        $sectionnum = $this->get_value($mform, 'revisesectionnum');
        return $this->get_options_cmids($mform, $plugin, 'assign', $sectionnum);
    }

    /**
     * get_options_revisecmids
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $modname (optional, default="")
     * @param integer $sectionnum (optional, default=0)
     * @return array($cmid => $name) of activities from the specified $sectionnum
     *                               or from the whole course (if $sectionnum==0)
     */
    protected function get_options_cmids($mform, $plugin, $modname='', $sectionnum=0) {
        $options = array();
        $modinfo = $this->get_course_modinfo();
        $sections = $modinfo->get_section_info_all();
        foreach ($sections as $section) {
            if ($sectionnum==0 || $sectionnum==$section->section) {
                $cmids = explode(',', $section->sequence);
                $cmids = array_filter($cmids);
                foreach ($cmids as $cmid) {
                    if (array_key_exists($cmid, $modinfo->cms)) {
                        $cm = $modinfo->get_cm($cmid);
                        if ($modname=='' || $modname==$cm->modname) {
                            $name = $cm->name;
                            $name = block_maj_submissions::filter_text($name);
                            $name = block_maj_submissions::trim_text($name);
                            $options[$cmid] = $name;
                        }
                    }
                }
            }
        }
        return $this->format_select_options($plugin, $options, 'activity');
    }

    /**
     * format_select_options
     *
     * @param string  $plugin
     * @param array   $options
     * @param string  $type ("", "" or "")
     * @return array  $option for a select element in $mform
     */
    protected function format_select_options($plugin, $options, $type) {
        $createnew = get_string('createnew'.$type, $plugin);
        if (! array_key_exists(0, $options)) {
            $options = array(0 => '') + $options;
        }
        return $options + array(-1 => "($createnew)");
    }

    /**
     * get_course
     *
     * @return course record for this block
     */
    protected function get_course_modinfo() {
        $course = $this->get_course();
        return get_fast_modinfo($course);
    }

    /**
     * get_course
     *
     * @return course record for this block
     */
    protected function get_course() {
        if (isset($this->block->course)) {
            return $this->block->course;
        } else {
            return $this->block->page->course;
        }
    }
}
