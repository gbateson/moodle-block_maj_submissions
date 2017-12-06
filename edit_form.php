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
     * set_data
     *
     * @param object $defaults
     * @return void, but will update $mform
     */
    function set_data($data) {
        $name = 'files';
        $field = 'config_'.$name;
        $plugin = 'block_maj_submissions';
        $options = block_maj_submissions::get_fileoptions();
        file_prepare_standard_filemanager($data, $field, $options, $this->block->context, $plugin, $name, 0);
        parent::set_data($data);
    }

    /**
     * specific_definition
     *
     * @param object $mform
     * @return void, but will update $mform
     */
    protected function specific_definition($mform) {

        // cache the plugin name, because
        // it is quite long and we use it a lot
        $plugin = 'block_maj_submissions';

        $this->set_form_id($mform, get_class($this));

        //-----------------------------------------------------------------------------
        $this->add_header($mform, 'form', 'display');
        //-----------------------------------------------------------------------------

        $this->add_field_description($mform, $plugin, 'description');
        $this->add_field($mform, $plugin, 'title', 'text', PARAM_TEXT, array('size' => 50));
        $this->add_field($mform, $plugin, 'displaylinks', 'selectyesno', PARAM_INT);
        $this->add_field($mform, $plugin, 'displaydates', 'selectyesno', PARAM_INT);
        $this->add_field($mform, $plugin, 'displaystats', 'selectyesno', PARAM_INT);
        $this->add_field($mform, $plugin, 'displaylangs', 'text', PARAM_TEXT);
        $mform->disabledIf('config_displaystats', 'config_displaydates', 'eq', '0');
        $this->add_field_filemanager($mform, $plugin, 'files');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'conferencestrings', false, true);
        //-----------------------------------------------------------------------------

        $this->add_multilang_strings($mform, $plugin, false);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'autoincrementsettings', false, true);
        //-----------------------------------------------------------------------------

        $this->add_multilang_strings($mform, $plugin, true);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'conferenceevents');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'conference');
        $this->add_cmid($mform, $plugin, 'resource,file,page,url', 'conferencecmid');
        $this->add_time_startfinish($mform, $plugin, 'workshops');
        $this->add_cmid($mform, $plugin, 'resource,file,page,url', 'workshopscmid');
        $this->add_time_startfinish($mform, $plugin, 'reception');
        $this->add_cmid($mform, $plugin, 'resource,file,page,url', 'receptioncmid');

        $this->add_cmid($mform, $plugin, 'data', 'registereventscmid');
        //$this->add_repeat_elements($mform, $plugin, 'events', 'text', true);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'collectsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'collectpresentations');
        $this->add_cmid($mform, $plugin, 'data,page,resource', 'collectpresentationscmid');

        $this->add_time_startfinish($mform, $plugin, 'collectworkshops');
        $this->add_cmid($mform, $plugin, 'data,page,resource', 'collectworkshopscmid');

        $this->add_time_startfinish($mform, $plugin, 'collectsponsoreds');
        $this->add_cmid($mform, $plugin, 'data,page,resource', 'collectsponsoredscmid');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'reviewsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'review');
        $this->add_sectionnum($mform, $plugin, 'reviewsectionnum');
        //$this->add_repeat_elements($mform, $plugin, 'reviewcmids', 'selectgroups', true);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'revisesubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'revise');
        $this->add_sectionnum($mform, $plugin, 'revisesectionnum');
        //$this->add_repeat_elements($mform, $plugin, 'revisecmids', 'selectgroups', true);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'publishsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'publish');
        $this->add_cmid($mform, $plugin, 'data,resource,file,page,url', 'publishcmid');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'registerparticipation');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'registerdelegates');
        $this->add_cmid($mform, $plugin, 'data,page,resource', 'registerdelegatescmid');

        $this->add_time_startfinish($mform, $plugin, 'registerpresenters');
        $this->add_cmid($mform, $plugin, 'data,page,resource', 'registerpresenterscmid');

        $this->add_cmid($mform, $plugin, 'page,resource', 'membershipinfocmid');
        $this->add_cmid($mform, $plugin, 'page,resource', 'paymentinfocmid');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'dateformats');
        //-----------------------------------------------------------------------------

        $this->add_field_moodledatefmt($mform, $plugin);
        $this->add_field_customdatefmt($mform, $plugin);
        $this->add_field_fixdates($mform, $plugin);
        $this->add_field($mform, $plugin, 'removeyear', 'selectyesno', PARAM_INT);
        $this->add_field($mform, $plugin, 'manageevents', 'selectyesno', PARAM_INT);
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
     * get the value of a block setting
     * either the NEW value from the incoming form data
     * or the OLD value from the block->config object
     *
     * @param  object $mform of setting
     * @param  string $name of element
     * @param  mixed  $default (optional, default=NULL)
     * @return mixed  the value of the required $mform element
     */
    protected function get_value($mform, $name, $paramtype=PARAM_INT, $default=null) {
        $configname = 'config_'.$name;
        $value = optional_param($configname, null, $paramtype);
        if (isset($value)) {
            return $value;
        } else {
            return $this->get_original_value($name, $default);
        }
    }

    /**
     * get value of a constant field
     *
     * @param  integer $dataid an "id" from the "data" table
     * @param  string  $name of setting
     * @param  mixed   $paramname to return (e.g. "param1")
     * @param  string  $lang code (optional, default = "")
     * @return mixed value of constant field
     */
    protected function get_constant_value($dataid, $name, $paramname, $lang='') {
        global $DB;

        if ($dataid) {
            $record = false;

            if ($lang) {
                $params = array('dataid' => $dataid,
                                'type'   => 'constant',
                                'name'   => $name."_$lang");
                $record = $DB->get_record('data_fields', $params);
            }

            if ($record===false && ($lang=='' || $lang=='en')) {
                $params = array('dataid' => $dataid,
                                'type'   => 'constant',
                                'name'   => $name);
                $record = $DB->get_record('data_fields', $params);
            }

            if ($record) {
                return $record->$paramname;
            }
        }

        return $this->get_original_value($name);
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
     * set the value of a config setting in this block
     *
     * @param  string $name of setting
     * @param  mixed  $value
     * @return void, but may update $this->block->config
     */
    protected function set_original_value($name, $value) {
        if (isset($this->block->config) && $value) {
            $this->block->config->$name = $value;
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
    protected function add_header($mform, $component, $name, $expanded=true, $addhelpbutton=false) {
        $label = get_string($name, $component);
        $mform->addElement('header', $name, $label);
        if ($addhelpbutton) {
            $mform->addHelpButton($name, $name, $component);
        }
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
        }
    }

    /**
     * add_field_description
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name of field
     * @return void, but will update $mform
     */
    protected function add_field_description($mform, $plugin, $name) {
        global $OUTPUT;

        $label = get_string($name);
        $text = get_string('block'.$name, $plugin);

        if (isset($this->block->instance)) {
            $blockname = $this->block->instance->blockname;
        } else {
            // strip "block_" prefix and "_edit_form" suffix
            $blockname = substr(get_class($this), 6, -10);
        }

        $params = array('id' => $this->block->instance->id);
        $params = array('href' => new moodle_url('/blocks/'.$blockname.'/export.settings.php', $params));

        $text .= ' '.html_writer::tag('a', get_string('exportsettings', $plugin), $params);
        $text .= $OUTPUT->help_icon('exportsettings', $plugin);

        $params = array('id' => $this->block->instance->id);
        $params = array('href' => new moodle_url('/blocks/'.$blockname.'/import.settings.php', $params));

        $text .= ' '.html_writer::tag('a', get_string('importsettings', $plugin), $params);
        $text .= $OUTPUT->help_icon('importsettings', $plugin);

        $text = html_writer::tag('div', $text, array('id' => 'id_description_text'));
        $mform->addElement('static', $name, $label, $text);
    }

    /**
     * add_field_filemanager
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name of field
     * @return void, but will update $mform
     */
    protected function add_field_filemanager($mform, $plugin, $name) {
        global $CFG;
        require_once($CFG->dirroot.'/blocks/maj_submissions/lib.php');

        $configname = 'config_'.$name;
        $groupname = 'group_'.$name;

        $elements = array();

        $filemanager = $configname.'_filemanager';
        $options = block_maj_submissions::get_fileoptions();
        $elements[] = $mform->createElement('filemanager', $filemanager, null, $options);

        $text = get_string($name.'link', $plugin);
        $elements[] = $mform->createElement('static', '', '', $text);

        $url = block_maj_submissions_pluginfile_baseurl($this->get_course(),
                                                        $this->block->context);
        $url = html_writer::tag('small', html_writer::tag('b', $url));
        $elements[] = $mform->createElement('static', '', '', $url);

        $label = get_string($name, $plugin);
        $join = html_writer::empty_tag('br');
        $mform->addGroup($elements, $groupname, $label, $join, false);

        $mform->addHelpButton($groupname, $name, $plugin);
    }

    /**
     * add_multilang_strings
     *
     * @param object  $mform
     * @param string  $plugin
     * @param boolean $autoincrement
     * @return void, but will update $mform
     */
    protected function add_multilang_strings($mform, $plugin, $autoincrement) {
        global $DB;

        if ($cmid = $this->get_original_value('registerdelegatescmid', 0)) {
            $params = array('name' => 'data');
            $moduleid = $DB->get_field('modules', 'id', $params);
            $params = array('id' => $cmid, 'module' => $moduleid);
            $dataid = $DB->get_field('course_modules', 'instance', $params);
        } else {
            $cmid = 0;
            $dataid = 0;
            $moduleid = 0;
        }

        $fieldnames = block_maj_submissions::get_constant_fieldnames($autoincrement);

        if ($autoincrement) {

            // link to help on formatting string for the PHP's sprintf function
            $help = 'http://php.net/manual/'.block_maj_submissions::get_php_lang().'/function.sprintf.php';
            $help = html_writer::tag('a', get_string('help'), array('href' => $help, 'target' => '_blank'));
            $help = html_writer::tag('small', $help);

            $textoptions = array('size' => 10);
            foreach ($fieldnames as $fieldname => $name) {

                $label = get_string($name, $plugin);
                $configname = 'config_'.$name;
                $groupname = 'group_'.$name;

                $defaultvalue = $this->get_constant_value($dataid, $fieldname, 'param1');
                $this->set_original_value($name, $defaultvalue);

                $defaultformat = $this->get_constant_value($dataid, $fieldname, 'param3');
                $this->set_original_value($name.'format', $defaultformat);

                $elements = array();
                $elements[] = $mform->createElement('static', '', '', get_string('typevalue', 'grades'));
                $elements[] = $mform->createElement('text', $configname, '', $textoptions);
                $elements[] = $mform->createElement('static', '', '', get_string('format'));
                $elements[] = $mform->createElement('text', $configname.'format', '', $textoptions);
                $elements[] = $mform->createElement('static', '', '', $help);

                $mform->addGroup($elements, $groupname, $label, ' ', false);
                $mform->addHelpButton($groupname, $name, $plugin);

                $mform->setType($configname, PARAM_INT);
                $mform->setType($configname.'format', PARAM_TEXT);

                $mform->setDefault($configname, $this->get_original_value($name, $defaultvalue));
                $mform->setDefault($configname.'format', $this->get_original_value($name, $defaultformat));
            }
        } else {
            $strman = get_string_manager();
            $langs = $this->get_original_value('displaylangs', '');
            $langs = block_maj_submissions::get_languages($langs);

            $multilang = (count($langs) > 1);

            $string = array();
            foreach ($langs as $lang) {
                if ($strman->translation_exists($lang)) {
                    // language is installed
                    $string[$lang] = $strman->load_component_strings('langconfig', $lang);
                } else {
                    // language is NOT installed - create dummy lang pack
                    $list = $strman->get_list_of_languages($lang);
                    if (array_key_exists($lang, $list)) {
                        $string[$lang] = array('thislanguage' => $list[$lang]);
                    } else {
                        // unknown language code - shouldn't happen !!
                        $string[$lang] = array('thislanguage' => get_string('unknownlanguage', $plugin));
                    }
                }
            }

            $textoptions = array('size' => 30);
            foreach ($fieldnames as $fieldname => $name) {
                if ($multilang) {
                    // multiple languages
                    $elements = array();
                    foreach ($langs as $lang) {
                        $configname = 'config_'.$name.$lang;
                        $label = $string[$lang]['thislanguage']." ($lang) ";
                        $elements[] = $mform->createElement('static', '', '', $label);
                        $elements[] = $mform->createElement('text', $configname, '', $textoptions);
                        $elements[] = $mform->createElement('static', '', '', html_writer::empty_tag('br'));
                    }
                    $groupname = 'group_'.$name;
                    $label = get_string($name, $plugin);
                    $mform->addGroup($elements, $groupname, $label, '', false);
                    $mform->addHelpButton($groupname, $name, $plugin);
                    foreach ($langs as $lang) {
                        $configname = 'config_'.$name.$lang;
                        $default = $this->get_constant_value($dataid, $fieldname, 'param1', $lang);
                        $mform->setDefault($configname, $default);
                        $mform->setType($configname, PARAM_TEXT);
                    }
                } else {
                    // single language
                    $lang = reset($langs);
                    $label = get_string($name, $plugin);
                    $configname = 'config_'.$name.$lang;
                    $defaultvalue = $this->get_constant_value($dataid, $fieldname, 'param1', $lang);
                    $mform->addElement('text', $configname, $label, $textoptions);
                    $mform->setType($configname, PARAM_TEXT);
                    $mform->setDefault($configname, $defaultvalue);
                    $mform->addHelpButton($configname, $name, $plugin);
                }
            }
        }
    }

    /**
     * add_time_startfinish
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $type e.g. conference, workshops, reception,
     *                collectcollectpresentations, collectworkshops, collectsponsoreds
     *                review, revise, publish, registerdelegates, registerpresenters
     * @return void, but will update $mform
     */
    protected function add_time_startfinish($mform, $plugin, $type) {
        $name = $type.'time';
        $configname = 'config_'.$name;
        $groupname = 'group_'.$name;
        $label = get_string($name, $plugin);

        $dateoptions = array('optional' => true);
        $timestart = html_writer::tag('b', get_string('timestart', $plugin).' ');
        $timefinish = html_writer::tag('b', get_string('timefinish', $plugin).' ');

        $elements = array(
            $mform->createElement('static', '', '', $timestart),
            $mform->createElement('date_time_selector', $configname.'start', '', $dateoptions),
            $mform->createElement('static', '', '', html_writer::empty_tag('br')),
            $mform->createElement('static', '', '', $timefinish),
            $mform->createElement('date_time_selector', $configname.'finish', '', $dateoptions)
        );

        $mform->addGroup($elements, $groupname, $label, '', false);
        $mform->setDefault($configname.'start', $this->get_original_value($name.'start'));
        $mform->setDefault($configname.'finish', $this->get_original_value($name.'finish'));
        $mform->addHelpButton($groupname, $name, $plugin);
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
        $options = $this->get_options_cmids($mform, $plugin, $type);
        $this->add_field($mform, $plugin, $name, 'selectgroups', PARAM_INT, $options);
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
        $options = $this->get_options_sectionnum($mform, $plugin);
        $this->add_field($mform, $plugin, $name, 'select', PARAM_ALPHANUM, $options);
    }

    /**
     * add_repeat_elements
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name
     * @param string  $elementtype
     * @param boolean $addbuttoninside (optional, default=false)
     * @return void, but will update $mform
     */
    protected function add_repeat_elements($mform, $plugin, $name, $elementtype, $addbuttoninside=false) {
        $configname = 'config_'.$name;
        $label = get_string($name, $plugin);
        $method = 'get_options_'.$name;
        $options = $this->$method($mform, $plugin);
        $elements = array($mform->createElement($elementtype, $configname, $label, $options));
        $repeats = count($this->block->config->$name);
        $options = array($configname => array('type' => PARAM_INT, 'helpbutton' => array($name, $plugin)));
        $addstring = get_string('add'.$name, $plugin, 1);
        $this->repeat_elements($elements, $repeats, $options, 'count'.$name, 'add'.$name, 1, $addstring, $addbuttoninside);
    }

    /**
     * get_options_events
     *
     * @param object $mform
     * @param string $plugin
     * @return array() of sections in this course
     */
    protected function get_options_events($mform, $plugin) {
        return array('size' => 40);
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
            if ($name = block_maj_submissions::get_sectionname($section)) {
                $options["$sectionnum"] = $name;
            } else {
                $options["$sectionnum"] = block_maj_submissions::get_sectionname_default($course, $sectionnum);
            }
        }
        return $this->format_select_options($plugin, $options, 'section', '');
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
        return $this->get_options_cmids($mform, $plugin, 'data', $sectionnum);
    }

    /**
     * get_options_cmids
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $modnames (optional, default="")
     * @param integer $sectionnum (optional, default=0)
     * @return array($cmid => $name) of activities from the specified $sectionnum
     *                               or from the whole course (if $sectionnum==0)
     */
    protected function get_options_cmids($mform, $plugin, $modnames='', $sectionnum=0) {
        $options = array();

        $modnames = explode(',', $modnames);
        $modnames = array_filter($modnames);
        $count = count($modnames);

        $modinfo = $this->get_course_modinfo();
        $sections = $modinfo->get_section_info_all();
        foreach ($sections as $sectionnum => $section) {

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
                                $sectionname = block_maj_submissions::get_sectionname($section, 0);
                                if ($sectionname=='') {
                                    $course = $this->get_course();
                                    $sectionname = block_maj_submissions::get_sectionname_default($course, $sectionnum);
                                }
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
        return $this->format_selectgroups_options($plugin, $options, 'activity');
    }

    /**
     * format_select_options
     *
     * @param string  $plugin
     * @param array   $options
     * @param string  $type ("field", "activity" or "section")
     * @return array  $option for a select element in $mform
     */
    protected function format_select_options($plugin, $options, $type, $nonevalue=0, $newvalue=-1) {
        if (! array_key_exists($nonevalue, $options)) {
            $text = get_string('none');
            $options = array($nonevalue => "($text)") + $options;
        }
        $text = get_string('createnew'.$type, $plugin);
        return $options + array($newvalue => "($text)");
    }

    /**
     * format_selectgroups_options
     *
     * @param string  $plugin
     * @param array   $options
     * @param string  $type ("field", "activity" or "section")
     * @return array  $option for a select element in $mform
     */
    protected function format_selectgroups_options($plugin, $options, $type) {
        return $options + array('-----' => $this->format_select_options($plugin, array(), $type));
    }

    /**
     * add_field_moodledatefmt
     *
     * @param object $mform
     * @param string $plugin
     * @return void, but will modify $mform
     */
    protected function add_field_moodledatefmt($mform, $plugin) {
        global $CFG, $PAGE;

        $name = 'moodledatefmt';
        $configname = 'config_'.$name;
        $groupname = 'group_'.$name;

        $time = time();
        if (method_exists($PAGE->theme, 'image_url')) {
            $image_url = 'image_url'; // Moodle >= 3.3
        } else {
            $image_url = 'pix_url'; // Moodle <= 3.2
        }
        $switch_plus = $PAGE->theme->$image_url('t/switch_plus', 'core')->out();

        $string = array();
        include($CFG->dirroot.'/lang/en/langconfig.php');
        $options = array_flip(preg_grep('/^strftime(da|re)/', array_keys($string)));

        // add examples of each date format string
        $elements = array();
        foreach (array_keys($options) as $i => $option) {
            $fmt = get_string($option);
            $text = userdate($time, $fmt);

            $params = array('src' => $switch_plus, 'onclick' => 'toggledateformat(this, '.$i.')');
            $text .= ' '.html_writer::empty_tag('img', $params);

            $params = array('id' => 'id_dateformat_'.$i, 'class' => 'dateformat', 'style' => 'display: none;');
            $text .= html_writer::tag('div', $option.': '.$fmt, $params);

            $elements[] = $mform->createElement('radio', $configname, '', $text, $option);
        }

        usort($elements, array($this, 'sort_by_text'));

        $js = '';
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "function toggledateformat(img, i) {\n";
        $js .= "    var obj = document.getElementById('id_dateformat_' + i);\n";
        $js .= "    if (obj) {\n";
        $js .= "        if (obj.style.display=='none') {\n";
        $js .= "            obj.style.display = '';\n";
        $js .= "            img.src = img.src.replace('plus','minus');\n";
        $js .= "        } else {\n";
        $js .= "            obj.style.display = 'none';\n";
        $js .= "            img.src = img.src.replace('minus','plus');;\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "    return false;\n";
        $js .= "}\n";
        $js .= "//]]>\n";
        $js .= "</script>\n";
        $elements[] = $mform->createElement('static', '', '', $js);

        $label = get_string($name, $plugin);
        $mform->addGroup($elements, $groupname, $label, '<br />', false);
        $mform->addHelpButton($groupname, $name, $plugin);
        $mform->setType($configname, PARAM_ALPHANUM);
        $mform->setDefault($configname, $this->get_original_value($name));
    }

    /**
     * sort_by_text
     *
     * @param object $a
     * @param string $b
     * @return integer
     */
    protected function sort_by_text($a, $b) {
        if ($a->_text < $b->_text) {
            return -1;
        }
        if ($a->_text > $b->_text) {
            return 1;
        }
        return 0;
    }

    /**
     * add_field_customdatefmt
     *
     * @param object $mform
     * @param string $plugin
     * @return void, but will modify $mform
     */
    protected function add_field_customdatefmt($mform, $plugin) {
        $name = 'customdatefmt';
        $configname = 'config_'.$name;
        $groupname = 'group_'.$name;
        $label = get_string($name, $plugin);

        $help = 'http://php.net/manual/'.block_maj_submissions::get_php_lang().'/function.strftime.php';
        $help = html_writer::tag('a', get_string('help'), array('href' => $help, 'target' => '_blank'));
        $help = html_writer::tag('small', $help);

        $elements = array(
            $mform->createElement('text', $configname, '', array('size' => 30)),
            $mform->createElement('static', '', '', $help)
        );

        $mform->addGroup($elements, $groupname, $label, ' ', false);
        $mform->addHelpButton($groupname, $name, $plugin);
        $mform->setType($configname, PARAM_TEXT);
        $mform->setDefault($configname, $this->get_original_value($name));
    }

    /**
     * add_field_fixdates
     *
     * @param object $mform
     * @param string $plugin
     * @return void, but will modify $mform
     */
    protected function add_field_fixdates($mform, $plugin) {
        $elements = array();

        $types = array('month', 'day', 'hour');
        foreach ($types as $type) {
            $name = 'fix'.$type;
            $configname = 'config_'.$name;
            $label = get_string($type, 'form');
            $label = html_writer::tag('b', $label.' :');
            if (count($elements)) {
                $elements[] = $mform->createElement('static', '', '', html_writer::empty_tag('br'));
            }
            $elements[] = $mform->createElement('static', '', '', $label);
            $elements[] = $mform->createElement('selectyesno', $configname);
        }

        $name = 'fixdates';
        $label = get_string($name, $plugin);
        $groupname = 'group_'.$name;

        $mform->addGroup($elements, $groupname, $label, ' ', false);
        $mform->addHelpButton($groupname, $name, $plugin);
        $mform->setType($configname, PARAM_TEXT);
        $mform->setDefault($configname, $this->get_original_value($name));

        foreach ($types as $type) {
            $name = 'fix'.$type;
            $configname = 'config_'.$name;
            $mform->setType($configname, PARAM_INT);
            $mform->setDefault($configname, $this->get_original_value($name));
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
        $configname = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement($elementtype, $configname, $label, $options);
        $mform->setType($configname, $paramtype);
        $mform->setDefault($configname, $this->get_original_value($name, $default));
        $mform->addHelpButton($configname, $name, $plugin);
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
