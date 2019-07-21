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

// disable direct access to this script
defined('MOODLE_INTERNAL') || die();

// get required files
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');
require_once($CFG->dirroot.'/mod/data/lib.php');

abstract class block_maj_submissions_tool_setupdatabase extends block_maj_submissions_tool_form {

    protected $type = '';
    protected $defaultpreset = '';
    protected $modulename = 'data';
    protected $defaultvalues = array(
        'visible'         => 1,  // course_modules.visible
        'intro'           => '', // see set_defaultintro()
        'introformat'     => FORMAT_HTML, // =1
        'approval'        => 0,
        'manageapproved'  => 0,
        'comments'        => 0,
        'requiredentriestoview' => 0,
        'requiredentries' => 0,
        'maxentries'      => 0,
        'timeavailablefrom' => 0,
        'timeavailableto' => 0,
        'timeviewfrom'    => 0, // start read-only
        'timeviewto'      => 0, // finish tread-only
        'assessed'        => 0

    );
    protected $timefields = array(
        'timestart' => array('timeavailablefrom'),
        'timefinish' => array('timeavailableto')
    );
    protected $permissions = array(
        // "user" is the "Authenticated user" role. A "user" is
        // logged in, but may not be enrolled in the current course.
        // They can view, but not write to, this database activity
        'user' => array('mod/data:viewentry' => CAP_ALLOW)
    );

    /**
     * definition
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }

        // --------------------------------------------------------
        $name = 'databaseactivity'; // header
        $label = get_string($name, $this->plugin);
        $mform->addElement('header', $name, $label);
        // --------------------------------------------------------

        $name = 'databaseactivity'; // cmid
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        // --------------------------------------------------------
        $name = 'preset';
        $label = get_string($name, $this->plugin);
        $mform->addElement('header', $name, $label);
        $mform->addHelpButton($name, $name, $this->plugin);
        // --------------------------------------------------------

        $presets = self::get_available_presets($context, $this->plugin, $this->cmid, 'imagegallery');
        if (count($presets)) {

            $name = 'presetfolder';
            $elements = array();

            foreach ($presets as $preset) {
                $label = " $preset->description";
                $value = "$preset->userid/$preset->shortname";
                $elements[] = $mform->createElement('radio', $name, null, $label, $value);
            }

            if (count($elements)) {
                $label = get_string('uploadpreset', $this->plugin);
                $elements[] = $mform->createElement('radio', $name, null, $label, '0/uploadpreset');
                $group_name = 'group_'.$name;
                $label = get_string($name, $this->plugin);
                $mform->addGroup($elements, $group_name, $label, html_writer::empty_tag('br'), false);
                $mform->addHelpButton($group_name, $name, $this->plugin);
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, "0/$this->defaultpreset");
            }
        }

        $name = 'presetfile';
        $label = get_string($name, $this->plugin);
        $mform->addElement('filepicker', $name, $label);
        $mform->addHelpButton($name, $name, $this->plugin);
        $mform->disabledIf($name, 'presetfolder', 'neq', '0/uploadpreset');

        $this->add_action_buttons();
    }

    /**
     * Perform extra validation on form values.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $DB;

        $errors = array();

        $require_activity = true;
        $require_section = false;
        $require_preset = true;

        if ($require_activity) {
            $name = 'databaseactivity';
            $group = 'group_'.$name;
            $num = $name.'num';
            $name = $name.'name';
            if (empty($data[$num])) {
                $errors[$group] = get_string("missing$num", $this->plugin);
            } else if ($data[$num]==self::CREATE_NEW) {
                if (empty($data[$name])) {
                    $errors[$group] = get_string("missing$name", $this->plugin);
                }
                $require_section = true;
            } else if ($data[$num] > 0) {
                $config = $this->instance->config;
                $confignames = get_object_vars($config);
                unset($confignames[$this->type.'cmid']);
                $confignames = array_keys($confignames);
                $confignames = preg_grep('/cmid$/', $confignames);
                foreach ($confignames as $configname) {
                    if ($config->$configname==$data[$num]) {
                        $a = (object)array('databasedescription' => get_string($configname, $this->plugin),
                                           'createnewactivity' => get_string('createnewactivity', $this->plugin));
                        $errors[$group] = get_string('warningoverwritedatabase', $this->plugin, $a);
                    }
                }
                if (empty($errors)) {
                    $modinfo = get_fast_modinfo($this->instance->page->course);
                    if (array_key_exists($data[$num], $modinfo->cms)) {
                        $params = array('dataid' => $modinfo->get_cm($data[$num])->instance);
                        if ($count = $DB->get_field('data_records', 'COUNT(*)', $params)) {
                            // TODO: if you report the warning, add a "force" checkbox so it can be easily overridden
                            // $errors[$group] = get_string('warningrecordsexist', $this->plugin, $count);
                        }
                    }
                }
            }
        }

        if ($require_section) {
            $name = 'coursesection';
            $group = 'group_'.$name;
            $num = $name.'num';
            $name = $name.'name';
            if (empty($data[$num])) {
                $errors[$group] = get_string("missing$num", $this->plugin);
            } else if ($data[$num]==self::CREATE_NEW) {
                if (empty($data[$name])) {
                    $errors[$group] = get_string("missing$name", $this->plugin);
                }
            }
        }

        if ($require_preset) {
            $name = 'presetfolder';
            if (isset($data[$name]) && $data[$name]) {
                $require_preset = false;
            }
            $name = 'presetfile';
            if (isset($data[$name]) && $data[$name]) {
                $require_preset = false;
            }
        }

        if ($require_preset) {
            $errors['group_presetfolder'] = get_string("missingpreset", $this->plugin);
            $errors['presetfile'] = get_string("missingpreset", $this->plugin);
        }

        return $errors;
    }

    /**
     * form_postprocessing
     *
     * @uses $DB
     * @param object $data
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function form_postprocessing() {
        global $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->dirroot.'/lib/xmlize.php');

        $cm = false;
        $msg = array();
        $time = time();

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'databaseactivity');
        }

        if ($cm) {

            if (empty($data->presetfile)) {
                $presetfile = '';
            } else {
                $presetfile = $this->get_new_filename('presetfile');
            }

            if (empty($data->presetfolder)) {
                $presetfolder = '';
            } else {
                $presetfolder = $data->presetfolder;
            }

            // create
            $data = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
            $data->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
            $data->instance   = $cm->instance;

            $importer = null;

            if ($presetfile) {
                $file = $this->save_temp_file('presetfile');
                $importer = new data_preset_upload_importer($this->course, $cm, $data, $file);
                $presetfolder = ''; // ignore any folder that was specified
            }

            if ($presetfolder) {

                // transfer preset to Moodle file storage
                $fs = get_file_storage();
                list($userid, $filepath) = explode('/', $presetfolder, 2);
                $dirpath = "$CFG->dirroot/blocks/maj_submissions/presets/$filepath";
                if (is_dir($dirpath) && is_directory_a_preset($dirpath)) {

                    $contextid = DATA_PRESET_CONTEXT;   // SYSCONTEXTID
                    $component = DATA_PRESET_COMPONENT; // "mod_data"
                    $filearea  = DATA_PRESET_FILEAREA;  // "site_presets"
                    $itemid    = 0;
                    $sortorder = 0;

                    $filenames = scandir($dirpath);
                    foreach ($filenames as $filename) {
                        if (substr($filename, 0, 1)=='.') {
                            continue; // skip hidden items
                        }
                        if (is_dir("$dirpath/$filename")) {
                            continue; // skip sub directories
                        }
                        if ($fs->file_exists($contextid, $component, $filearea, $itemid,  "/$filepath/", $filename)) {
                            continue; // file already exists - unusual !!
                        }
                        if ($sortorder==0) {
                            $fs->create_directory($contextid, $component, $filearea, $itemid, "/$filepath/");
                            $sortorder++;
                        }
                        $filerecord = array(
                            'contextid' => $contextid,
                            'component' => $component,
                            'filearea'  => $filearea,
                            'sortorder' => $sortorder++,
                            'itemid'    => $itemid,
                            'filepath'  => "/$filepath/",
                            'filename'  => $filename
                        );
                        $file = $fs->create_file_from_pathname($filerecord, "$dirpath/$filename");
                    }
                }

                // now, try the import using the standard forms for the database module
                $importer = new data_preset_existing_importer($this->course, $cm, $data, $presetfolder);
            }

            if ($importer) {
                $renderer = $PAGE->get_renderer('mod_data');
                $importform = $renderer->import_setting_mappings($data, $importer);

                // adjust the URL in the import form
                $search = '/(<form method="post" action=")(">)/';
                $replace = new moodle_url('/mod/data/preset.php', array('id' => $cm->id));
                $importform = preg_replace($search, '$1'.$replace.'$2', $importform);

                // on a new database, remove warning about overwriting fields
                if (empty($importer->get_preset_settings()->currentfields)) {
                    $name = 'overwritesettings';
                    $params = array('type' => 'hidden', 'name' => $name, 'value' => 0);
                    $search = '/(\s*<p>.*?<\/p>)?\s*<div class="'.$name.'">.*?<\/div>/s';
                    $replace = html_writer::empty_tag('input', $params);
                    $importform = preg_replace($search, $replace, $importform);
                }

                // send the import form to the browser
                echo $OUTPUT->header();
                echo $OUTPUT->heading(format_string($data->name), 2);
                echo $importform;
                echo $OUTPUT->footer();
                exit(0);
            }

            // Otherwise, something was amiss so redirect to the standard page
            // for importing a preset into the database acitivty.
            $url = new moodle_url('/mod/data/preset.php', array('id' => $cm->id));
            redirect($url);
        }

        return false; // shouldn't happen !!
    }


    /**
     * Specify whether or not database is read only.
     * Databases that collect information from delegates,
     * such as the registration or submissions databases,
     * are NOT read-only, whereas the "rooms" and "events"
     * databases are read-only, so that teachers and admins
     * can add records, but ordinary delegates cannot.
     */
    protected function is_readonly() {
        return false;
    }

    /**
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        $defaultvalues = parent::get_defaultvalues($data, $time);

        if ($this->is_readonly()) {
            // content will be added by teacher/admin
            $defaultvalues['timeviewfrom'] = $time;
        } else {
            // content will be added by students (=participants)
            $defaultvalues['approval'] = 1;
            $defaultvalues['maxentries'] = 1;
            $defaultvalues['requiredentriestoview'] = 10;
        }

        return $defaultvalues;
    }

    /**
     * get_defaultintro
     *
     * @todo Finish documenting this function
     */
    protected function get_defaultintro() {
        $intro = '';

        // useful urls
        $urls = array(
            'signup' => new moodle_url('/login/signup.php'),
            'login'  => new moodle_url('/login/index.php'),
            'enrol'  => new moodle_url('/enrol/index.php', array('id' => $this->course->id))
        );

        // setup the multiparams for the multilang strings
        $names = array('record' => $this->defaultpreset.'record',
                       'process' => $this->defaultpreset.'process');
        $multilangparams = $this->instance->get_multilang_params($names, $this->plugin);

        // add intro sections
        $howtos = array('switchrole', 'begin', 'login', 'enrol', 'signup', 'add', 'edit' ,'delete');
        foreach ($howtos as $howto) {
            $params = array('class' => "howto $howto");
            $intro .= html_writer::start_tag('div', $params);
            $text = $this->instance->get_string("howto$howto", $this->plugin, $multilangparams);
            switch ($howto) {
                case 'login':
                case 'enrol':
                    $text = str_replace('{$url}', $urls[$howto], $text);
                    break;
                case 'signup':
                    $text .= html_writer::start_tag('ol');
                    foreach ($urls as $name => $url) {
                        $link = $this->instance->get_string("link$name", $this->plugin);
                        if ($name=='signup' || $name=='login') {
                            $params = array('target' => '_blank');
                        } else {
                            $params = array(); // $name=='enrol'
                        }
                        $link = html_writer::link($url, $link, $params);
                        $text .= html_writer::tag('li', $link);
                    }
                    $text .= html_writer::end_tag('ol');
                    break;
            }
            if ($text) {
                $intro .= html_writer::tag('p', $text);
            }
            $intro .= html_writer::end_tag('div');
        }

        if ($intro) {
            $intro = '<script type="text/javascript">'."\n".
                     '//<![CDATA['."\n".
                     '(function(){'."\n".
                     '    var css = ".path-mod-data .howto, .path-mod-data .alert-error { display: none; }";'."\n".
                     '    var style = document.createElement("style");'."\n".
                     '    style.setAttribute("type","text/css");'."\n".
                     '    style.appendChild(document.createTextNode(css));'."\n".
                     '    var head = document.getElementsByTagName("head");'."\n".
                     '    head[0].appendChild(style);'."\n".
                     "})();\n".
                     "//]]>\n".
                     "</script>\n".
                     $intro;
        }

        return $intro;
    }

    /**
     * process_action
     *
     * @uses $DB
     * @param object $course
     * @param string $sectionname
     * @return object
     * @todo Finish documenting this function
     */
    public function process_action() {
        global $DB, $PAGE, $OUTPUT;

        if (! $action = optional_param('action', '', PARAM_ALPHANUM)) {
            return ''; // no action specified
        }

        if (! optional_param('sesskey', false, PARAM_BOOL)) {
            return ''; // no sesskey - unusual !!
        }

        if (! confirm_sesskey()) {
            return ''; // invalid sesskey - unusual !!
        }

        $fullname = optional_param('fullname', '', PARAM_PATH);
        list($userid, $shortname) = explode('/', $fullname, 2);

        $userid    = clean_param($userid, PARAM_INT);
        $shortname = clean_param($shortname, PARAM_TEXT);
        $fullname  = "$userid/$shortname";

        $message = '';

        if ($action=='confirmdelete') {
            $yes = array('fullname' => $fullname,
                         'action' => 'delete',
                         'id' => $PAGE->url->param('id'));
            $yes = new moodle_url($PAGE->url->out_omit_querystring(), $yes);
            $no = array('id' => $PAGE->url->param('id'));
            $no = new moodle_url($PAGE->url->out_omit_querystring(), $no);
            $message = get_string('deletewarning', 'data').
                       html_writer::empty_tag('br').$shortname;
            if ($userid) {
                $message .= ' ('.fullname($DB->get_record('user', array('id' => $userid))).')';
            }
            $message = $OUTPUT->confirm($message, $yes, $no);
        }

        if ($action=='delete') {
            if ($this->cmid) {
                $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            } else {
                $context = $this->course->context;
            }
            $can_delete = false;
            $presets = self::get_available_presets($context, $this->plugin, $this->cmid);
            foreach ($presets as $preset) {
                if ($can_delete==false && $preset->shortname==$shortname) {
                    $can_delete = data_user_can_delete_preset($context, $preset);
                }
            }
            if ($can_delete) {
                data_delete_site_preset($shortname);
                $url = clone($PAGE->url);
                $url->remove_all_params();
                $url->params(array('id' => $PAGE->url->param('id')));
                $message = $shortname.' '.get_string('deleted', 'data');
                $message = $OUTPUT->notification($message, 'notifysuccess').
                           $OUTPUT->continue_button($url);
            }
        }

        return $message;
    }

    /**
     * get_available_presets
     *
     * @uses $DB
     * @uses $OUTPUT
     * @param object $context
     * @return integer $cmid
     */
    static public function get_available_presets($context, $plugin, $cmid, $exclude='') {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $presets = array();

        $strman = get_string_manager();
        $strdelete = get_string('deleted', 'data');

        $dirpath = $CFG->dirroot.'/blocks/maj_submissions/presets';
        if (is_dir($dirpath)) {

            $shortnames = scandir($dirpath);
            foreach ($shortnames as $shortname) {
                if (substr($shortname, 0, 1)=='.') {
                    continue; // skip hidden shortnames
                }
                $path = "$dirpath/$shortname";
                if (! is_dir($path)) {
                    continue; // skip files and links
                }
                if (! is_directory_a_preset($path)) {
                    continue; // not a preset - unusual !!
                }
                if ($strman->string_exists('presetname'.$shortname, $plugin)) {
                    $name = get_string('presetname'.$shortname, $plugin);
                } else {
                    $name = $shortname;
                }
                if (file_exists("$path/screenshot.jpg")) {
                    $screenshot = "$path/screenshot.jpg";
                } else if (file_exists("$path/screenshot.png")) {
                    $screenshot = "$path/screenshot.png";
                } else if (file_exists("$path/screenshot.gif")) {
                    $screenshot = "$path/screenshot.gif";
                } else {
                    $screenshot = ''; // shouldn't happen !!
                }
                $presets[] = (object)array(
                    'userid' => 0,
                    'path' => $path,
                    'name' => $name,
                    'shortname' => $shortname,
                    'screenshot' => $screenshot
                );
            }
        }

        // append mod_data presets, user presets and site wide presets
        $presets = array_merge($presets, data_get_available_presets($context));

        if (empty($exclude)) {
            $exclude = array();
        } else if (is_scalar($exclude)) {
            $exclude = array($exclude);
        }

        if (method_exists($OUTPUT, 'image_url')) {
            $image_url = 'image_url'; // Moodle >= 3.3
        } else {
            $image_url = 'pix_url'; // Moodle <= 3.2
        }

        foreach ($presets as $i => $preset) {

            if (in_array($preset->shortname, $exclude)) {
                unset($presets[$i]);
                continue;
            }

            // ensure each preset is only added once
            $exclude[] = $preset->shortname;

            if (empty($preset->userid)) {
                $preset->userid = 0;
                $preset->description = $preset->name;
            } else {
                $fields = get_all_user_name_fields(true);
                $params = array('id' => $preset->userid);
                $user = $DB->get_record('user', $params, "id, $fields", MUST_EXIST);
                $preset->description = $preset->name.' ('.fullname($user, true).')';
            }

            if (strpos($preset->path, $dirpath)===0) {
                $can_delete = false; // a block preset
            } else {
                $can_delete = data_user_can_delete_preset($context, $preset);
            }

            if ($can_delete) {
                $url = clone($PAGE->url);
                $url->remove_all_params();

                $params = array('id'       => $PAGE->url->param('id'),
                                'action'   => 'confirmdelete',
                                'fullname' => "$preset->userid/$preset->shortname",
                                'sesskey'  => sesskey());
                $url->params($params);

                $icon = $OUTPUT->$image_url('t/delete');
                $params = array('src'   => $icon,
                                'class' => 'iconsmall',
                                'alt'   => "$strdelete $preset->description");
                $icon = html_writer::empty_tag('img', $params);

                $preset->description .= html_writer::link($url, $icon);
            }

            $presets[$i] = $preset;
        }

        return $presets;
    }
}
