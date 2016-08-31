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

    public $modnames = null;
    public $langnames = null;

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

        $element = $mform->addElement('static', 'description', get_string('description'), get_string('blockdescription', $plugin));

        $name = 'title';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('text', $config_name, $label, array('size' => 50));
        $mform->setType($config_name, PARAM_TEXT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
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
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'collectsubmissions');
        //-----------------------------------------------------------------------------

        $name = 'collectcmid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'filterfields';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'collecttimestart';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'collecttimefinish';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'reviewsubmissions');
        //-----------------------------------------------------------------------------

        $name = 'reviewsectionid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'reviewcmids';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'reviewtimestart';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'reviewtimefinish';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'revisesubmissions');
        //-----------------------------------------------------------------------------

        $name = 'revisesectionid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'revisecmids';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'revisetimestart';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'revisetimefinish';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);


        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'publishsubmissions');
        //-----------------------------------------------------------------------------

        $name = 'publishcmid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'publishtimestart';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = 'publishtimefinish';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);
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
     * get default value for a setting in this block
     *
     * @param  string $name of setting
     * @return mixed default value of setting
     */
    protected function defaultvalue($name) {
        if (isset($this->block->config->$name)) {
            return $this->block->config->$name;
        } else {
            return null;
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
     * set_langs_and_modnames
     *
     * @param object  $mform
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function set_langnames_and_modnames($plugin) {
        global $COURSE, $USER;

        if ($this->modnames===null && $this->langnames===null) {
            $this->modnames = array();
            $this->langnames = array('' => get_string('default'));

            // pick out mod names and languages used in this course
            $modinfo = get_fast_modinfo($COURSE, $USER->id);
            foreach ($modinfo->cms as $cmid => $cm) {
                if ($cm->modname=='label') {
                    continue; // ignore labels
                }
                if (empty($this->modnames[$cm->modname])) {
                    switch ($cm->modname) {
                        case 'assign'     : $modname = get_string('showassigns',     $plugin); break;
                        case 'assignment' : $modname = get_string('showassignments', $plugin); break;
                        case 'attendance' : $modname = get_string('showattendances', $plugin); break;
                        case 'database'   : $modname = get_string('showdatabases',   $plugin); break;
                        case 'forum'      : $modname = get_string('showforums',      $plugin); break;
                        case 'glossary'   : $modname = get_string('showglossaries',  $plugin); break;
                        case 'lesson'     : $modname = get_string('showlessons',     $plugin); break;
                        case 'quiz'       : $modname = get_string('showquizzes',     $plugin); break;
                        case 'workshop'   : $modname = get_string('showworkshops',   $plugin); break;
                        default           : $modname = ''; // get_string('modulenameplural', $cm->modname)
                    }
                    $this->modnames[$cm->modname] = $modname;
                }

                // get language, if any
                if (preg_match_all('/<span[^>]*class="multilang"[^>]*>/', $cm->name, $matches)) {
                    foreach ($matches[0] as $match) {
                        if (preg_match('/lang="(\w+)"/', $match, $lang)) {
                            $lang = substr($lang[1], 0, 2);
                            $this->langnames[$lang] = '';
                        }
                    }
                }
            }

            // get localized lang names
            $langs = get_string_manager()->get_list_of_translations();
            foreach ($langs as $lang => $text) {
                $lang = substr($lang, 0, 2);
                if (isset($this->langnames[$lang])) {
                    $this->langnames[$lang] = $text;
                }
            }

            // remove languages that are not available on this site
            $this->langnames = array_filter($this->langnames);
            ksort($this->langnames);

            // remove modnames that we cannot handle
            $this->modnames = array_filter($this->modnames);
            asort($this->modnames);
        }
    }

    /**
     * add_field_currentstate
     *
     * @param object  $mform
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function add_field_currentstate($mform, $plugin) {

        // get localized mod/lang names
        $this->set_langnames_and_modnames($plugin);

        $name = 'showactivities';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $elements_name = 'elements_'.$name;
        $label = get_string($name, $plugin);

        if (empty($this->modnames)) {
            $mform->addElement('static', $name, $label, get_string('noactivities', $plugin));
            $mform->addHelpButton($name, $name, $plugin);
        } else {
            $elements = array();
            foreach ($this->modnames as $modname => $text) {
                $elements[] = $mform->createElement('checkbox', $config_name.'['.$modname.']', '', $text);
            }

            $mform->addGroup($elements, $elements_name, $label, html_writer::empty_tag('br'), false);
            $mform->addHelpButton($elements_name, $name, $plugin);

            $defaultvalue = $this->defaultvalue($name);
            $defaultvalue = explode(',', $defaultvalue);

            foreach ($this->modnames as $modname => $text) {
                $mform->setType($config_name.'['.$modname.']', PARAM_INT);
                $mform->setDefault($config_name.'['.$modname.']', in_array($modname, $defaultvalue));
            }
        }
    }

    /**
     * add_field_languages
     *
     * @param object  $mform
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function add_field_languages($mform, $plugin) {

        // get localized mod/lang names
        $this->set_langnames_and_modnames($plugin);

        // cache some useful strings and textbox params
        $total = html_writer::tag('small', get_string('total', $plugin).': ');
        $head  = html_writer::tag('small', get_string('head',  $plugin).': ');
        $tail  = html_writer::tag('small', get_string('tail',  $plugin).': ');
        $params = array('size' => 2);

        $elements = array();
        foreach ($this->langnames as $lang => $text) {

            $lang = substr($lang, 0, 2);
            $namelength = 'config_namelength'.$lang;
            $headlength = 'config_headlength'.$lang;
            $taillength = 'config_taillength'.$lang;

            // add line break (except before the first language, the default, which has $lang=='')
            if ($lang) {
                $elements[] = $mform->createElement('static', '', '', html_writer::empty_tag('br'));
            }

            // add length fields for this language
            $elements[] = $mform->createElement('static', '', '', $total);
            $elements[] = $mform->createElement('text', $namelength, '', $params);
            $elements[] = $mform->createElement('static', '', '', $head);
            $elements[] = $mform->createElement('text', $headlength, '', $params);
            $elements[] = $mform->createElement('static', '', '', $tail);
            $elements[] = $mform->createElement('text', $taillength, '', $params);
            $elements[] = $mform->createElement('static', '', '', html_writer::tag('small', $text));
        }

        $name = 'textlength';
        $elements_name = 'elements_'.$name;
        $mform->addGroup($elements, $elements_name, get_string($name, $plugin), ' ', false);
        $mform->addHelpButton($elements_name, $name, $plugin);

        foreach ($elements as $element) {
            if ($element->getType()=='text') {
                $mform->setType($element->getName(), PARAM_INT);
            }
        }
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
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $elements_name = 'elements_'.$name;

        $time = time();
        $fixdaymonth = $this->block->config->fixdaymonth;
        $switch_plus = $PAGE->theme->pix_url('t/switch_plus', 'core')->out();

        $string = array();
        include($CFG->dirroot.'/lang/en/langconfig.php');
        $options = array_flip(preg_grep('/^strftime(da|re)/', array_keys($string)));

        // add examples of each date format string
        $elements = array();
        foreach (array_keys($options) as $i => $option) {
            $fmt = get_string($option);
            $text = userdate($time, $fmt, 99, $fixdaymonth, $fixdaymonth);

            $params = array('src' => $switch_plus, 'onclick' => 'toggledateformat(this, '.$i.')');
            $text .= ' '.html_writer::empty_tag('img', $params);

            $params = array('id' => 'dateformat'.$i, 'class' => 'dateformat', 'style' => 'display: none;');
            $text .= html_writer::tag('div', $option.': '.$fmt, $params);

            $elements[] = $mform->createElement('radio', $config_name, '', $text, $option);
        }

        usort($elements, array($this, 'sort_by_text'));

        $js = '';
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "function toggledateformat(img, i) {\n";
        $js .= "    var obj = document.getElementById('dateformat' + i);\n";
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

        $mform->addGroup($elements, $elements_name, get_string($name, $plugin), html_writer::empty_tag('br'), false);
        $mform->addHelpButton($elements_name, $name, $plugin);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
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
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $elements_name = 'elements_'.$name;

        $help = 'http://php.net/manual/'.substr(current_language(), 0, 2).'/function.strftime.php';
        $help = html_writer::tag('a', get_string('help'), array('href' => $help, 'target' => '_blank'));
        $help = html_writer::tag('small', $help);

        $elements = array(
            $mform->createElement('text', $config_name, $label, array('size' => 30)),
            $mform->createElement('static', '', '', $help)
        );

        $mform->addGroup($elements, $elements_name, get_string($name, $plugin), ' ', false);
        $mform->addHelpButton($elements_name, $name, $plugin);
        $mform->setType($config_name, PARAM_TEXT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
    }

    /**
     * get_mycourses
     *
     * @return mixed, either an array(coursecontextid) of accessible courses with similar block, or FALSE
     */
    protected function get_mycourses() {
        global $COURSE, $DB;

        $mycourses = array();

        $select = 'bi.id, ctx.id AS contextid, c.id AS courseid, c.shortname';
        $from   = '{block_instances} bi '.
                  'JOIN {context} ctx ON bi.parentcontextid = ctx.id '.
                  'JOIN {course} c ON ctx.instanceid = c.id';
        $where  = 'bi.blockname = ? AND bi.pagetypepattern = ? AND ctx.contextlevel = ? AND ctx.instanceid <> ? AND ctx.instanceid <> ?';
        $order  = 'c.sortorder ASC';
        $params = array('maj_submissions', 'course-view-*', CONTEXT_COURSE, SITEID, $COURSE->id);

        if ($instances = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
            $capability = 'block/maj_submissions:addinstance';
            if (class_exists('context_course')) {
                $context = context_course::instance(SITEID);
            } else {
                $context = get_context_instance(COURSE_CONTEXT, SITEID);
            }
            $has_site_capability = has_capability($capability, $context);
            foreach ($instances as $instance) {
                if ($has_site_capability) {
                    $has_course_capability = true;
                } else {
                    if (class_exists('context')) {
                        $context = context::instance_by_id($instance->contextid);
                    } else {
                        $context = get_context_instance_by_id($instance->contextid);
                    }
                    $has_course_capability = has_capability($capability, $context);
                }
                if ($has_course_capability) {
                    $mycourses[$instance->contextid] = $instance->shortname;
                }
            }
        }

        if (empty($mycourses)) {
            return false;
        } else {
            return $mycourses;
        }
    }

    /**
     * add_importexport
     *
     * @param object  $mform
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function add_importexport($mform, $plugin) {
        global $CFG, $OUTPUT;

        $str = (object)array(
            'all'        => addslashes_js(get_string('all')),
            'apply'      => addslashes_js(get_string('apply', $plugin)),
            'export'     => addslashes_js(get_string('exportsettings', $plugin)),
            'exporthelp' => addslashes_js($OUTPUT->help_icon('exportsettings', $plugin)),
            'exportlink' => addslashes_js($CFG->wwwroot.'/blocks/maj_submissions/export.php?id='.$this->block->instance->id),
            'import'     => addslashes_js(get_string('importsettings', $plugin)),
            'importhelp' => addslashes_js($OUTPUT->help_icon('importsettings', $plugin)),
            'importlink' => addslashes_js($CFG->wwwroot.'/blocks/maj_submissions/import.php?id='.$this->block->instance->id),
            'none'       => addslashes_js(get_string('none')),
            'select'     => addslashes_js(get_string('selectallnone', $plugin)),
            'selecthelp' => addslashes_js($OUTPUT->help_icon('selectallnone', $plugin))
        );

        $js = '';
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "function add_importexport() {\n";
        $js .= "    var obj = document.getElementsByTagName('DIV');\n";
        $js .= "    if (obj) {\n";
        $js .= "        var fbuttons = new RegExp('\\\\bfitem_actionbuttons\\\\b');\n";
        $js .= "        var fcontainer = new RegExp('\\\\bfcontainer\\\\b');\n";
        $js .= "        var fempty = new RegExp('\\\\bfemptylabel\\\\b');\n";
        $js .= "        var fitem = new RegExp('\\\\bfitem\\\\b');\n";
        $js .= "        var fid = new RegExp('^f[a-z]+_id_(elements_)?(config_)?(.*)');\n";
        $js .= "        var i_max = obj.length;\n";
        $js .= "        var addSelect = true;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";
        $js .= "            if (obj[i].className.match(fbuttons)) {\n";
        $js .= "                continue;\n";
        $js .= "            }\n";
        $js .= "            if (obj[i].className.match(fempty)) {\n";
        $js .= "                continue;\n";
        $js .= "            }\n";
        $js .= "            if (obj[i].className.match(fitem)) {\n";

        $js .= "                if (addSelect && obj[i].id=='') {\n";
        $js .= "                    addSelect = false;\n";

        $js .= "                    var elm = document.createElement('SPAN');\n";
        $js .= "                    elm.style.margin = '6px auto';\n";

/**
        $js .= "                    var lnk = document.createElement('A');\n";
        $js .= "                    lnk.appendChild(document.createTextNode('$str->import'));\n";
        $js .= "                    lnk.href = '$str->importlink';\n";
        $js .= "                    elm.appendChild(lnk);\n";
        $js .= "                    elm.innerHTML += '$str->importhelp';\n";
        $js .= "                    elm.appendChild(document.createElement('BR'));\n";

        $js .= "                    var lnk = document.createElement('A');\n";
        $js .= "                    lnk.appendChild(document.createTextNode('$str->export'));\n";
        $js .= "                    lnk.href = '$str->exportlink';\n";
        $js .= "                    elm.appendChild(lnk);\n";
        $js .= "                    elm.innerHTML += '$str->exporthelp';\n";
**/

        $js .= "                    var elm = document.createElement('SPAN');\n";
        $js .= "                    elm.style.margin = '6px auto';\n";

        $js .= "                    elm.appendChild(document.createTextNode('$str->select'));\n";
        $js .= "                    elm.innerHTML += '$str->selecthelp';\n";
        $js .= "                    elm.appendChild(document.createElement('BR'));\n";

        $js .= "                    var lnk = document.createElement('A');\n";
        $js .= "                    lnk.appendChild(document.createTextNode('$str->all'));\n";
        $js .= "                    lnk.href = \"javascript:select_all_in('DIV','itemselect',null);\";\n";
        $js .= "                    elm.appendChild(lnk);\n";

        $js .= "                    elm.appendChild(document.createTextNode(' / '));\n";

        $js .= "                    var lnk = document.createElement('A');\n";
        $js .= "                    lnk.appendChild(document.createTextNode('$str->none'));\n";
        $js .= "                    lnk.href = \"javascript:deselect_all_in('DIV','itemselect',null);\";\n";
        $js .= "                    elm.appendChild(lnk);\n";

        $js .= "                } else {\n";
        $js .= "                    var elm = document.createElement('INPUT');\n";
        $js .= "                    elm.style.margin = '6px auto';\n";

        $js .= "                    if (obj[i].id=='fitem_id_config_mycourses') {\n";
        $js .= "                        elm.type = 'submit';\n";
        $js .= "                        elm.value = '$str->apply';\n";
        $js .= "                    } else {\n";
        $js .= "                        elm.type = 'checkbox';\n";
        $js .= "                        elm.value = 1;\n";
        $js .= "                        elm.name = 'select_' + obj[i].id.replace(fid, '\$3');\n";
        $js .= "                        elm.id = 'id_select_' + obj[i].id.replace(fid, '\$3');\n";
        $js .= "                    }\n";
        $js .= "                }\n";

        $js .= "                var div = document.createElement('DIV');\n";
        $js .= "                div.appendChild(elm);\n";
        $js .= "                div.className = 'itemselect';\n";
        $js .= "                div.style.marginRight = (obj[i].offsetWidth - 720) + 'px';\n";

        $js .= "                obj[i].insertBefore(div, obj[i].firstChild);\n";
        $js .= "                div.style.height = obj[i].offsetHeight + 'px';\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "}\n";
        $js .= "if (window.addEventListener) {\n";
        $js .= "    window.addEventListener('load', add_importexport, false);\n";
        $js .= "} else if (window.attachEvent) {\n";
        $js .= "    window.attachEvent('onload', add_importexport);\n";
        $js .= "} else {\n";
        $js .= "    window.onload = add_importexport;\n";
        $js .= "}\n";
        $js .= "//]]>\n";
        $js .= "</script>\n";
        $mform->addElement('static', '', '', $js);
    }
}
