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

/**
 * block_maj_submissions_tool_setupschedule
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_setupschedule extends block_maj_submissions_tool_form {

    protected $type = 'publish';
    protected $modulename = 'page';
    protected $defaultname = 'conferenceschedule';

    // default values for a new "page" resource
    protected $defaultvalues = array(
        'intro' => '',
        'introformat' => FORMAT_HTML, // =1
        'content' => '',
        'contentformat' => FORMAT_HTML, // =1,
        'display' => 0, // = RESOURCELIB_DISPLAY_AUTO
        'displayoptions' => array('printheading' => 0, 'printintro' => 0)
    );

    // caches for the menu items
    protected $schedule_day      = null;
    protected $schedule_time     = null;
    protected $schedule_duration = null;
    protected $schedule_room     = null;
    protected $schedule_audience = null;
    protected $schedule_event    = array();

    const TEMPLATE_NONE     = 0;
    const TEMPLATE_ACTIVITY = 1;
    const TEMPLATE_FILENAME = 2;
    const TEMPLATE_UPLOAD   = 3;
    const TEMPLATE_GENERATE = 4;

    /**
     * definition
     */
    public function definition() {
        global $PAGE;

        if (method_exists($PAGE->requires, 'jquery')) {
            $PAGE->requires->jquery();
            $PAGE->requires->jquery_plugin('ui');
        } else {
            // get JQuery some other way
        }
        $PAGE->requires->js('/blocks/maj_submissions/tools/setupschedule/jquery.js', true);

        $mform = $this->_form;
        $config = $this->instance->config;

        $start = array(
            $config->workshopstimestart,
            $config->conferencetimestart,
        );
        $start = min(array_filter($start));
        $finish = array(
            $config->workshopstimefinish,
            $config->conferencetimefinish,
        );
        $finish = max(array_filter($finish));

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }

        if (empty($this->cmid)) {

            $name = 'publishcmid';
            $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
            $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

            // --------------------------------------------------------
            $name = 'template';
            $label = get_string($name, $this->plugin);
            $mform->addElement('header', $name, $label);
            // --------------------------------------------------------

            $name = 'templatetype';
            $options = array(self::TEMPLATE_NONE     => '',
                             self::TEMPLATE_ACTIVITY => get_string('selecttemplateactivity', $this->plugin),
                             self::TEMPLATE_FILENAME => get_string('selecttemplatefilename', $this->plugin),
                             self::TEMPLATE_UPLOAD   => get_string('uploadtemplatefile',     $this->plugin),
                             self::TEMPLATE_GENERATE => get_string('generatesampletemplate', $this->plugin));
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_FILE, $options);
            $mform->disabledIf($name, 'publishcmidnum', 'neq', self::CREATE_NEW);

            $name = 'templateactivity';
            $this->add_field_template($mform, $this->plugin, $name, $this->modulename, 'publishcmid');
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_ACTIVITY);

            $name = 'templatefilename';
            $this->add_field_templatefilename($mform, $this->plugin, $name);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_FILENAME);

            $name = 'templateupload';
            $label = get_string($name, $this->plugin);
            $mform->addElement('filepicker', $name, $label);
            $mform->addHelpButton($name, $name, $this->plugin);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_UPLOAD);

            // --------------------------------------------------------
            $name = 'generatesampletemplate';
            $label = get_string($name, $this->plugin);
            $mform->addElement('header', $name, $label);
            // --------------------------------------------------------

            $name = 'numberofdays';
            $default = max(0, $finish - $start);
            $default = ceil($default / DAYSECS);
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, range(0, 10), $default);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'numberofrooms';
            $default = 6; // calculate from data available in DB ?
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, range(0, 10), $default);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'numberofslots';
            $default = 6; // calculate from data available in DB ?
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, range(0, 10), $default);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'firstslottime';
            $default = mktime(9, 0, 0, date('m', $start), date('d', $start), date('Y', $start));
            $this->add_field($mform, $this->plugin, $name, 'date_time_selector', PARAM_INT, null, $default);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'slotduration';
            $default = (20 * MINSECS);
            $this->add_field($mform, $this->plugin, $name, 'duration', PARAM_INT, null, $default);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'slotinterval';
            $default = (10 * MINSECS);
            $this->add_field($mform, $this->plugin, $name, 'duration', PARAM_INT, null, $default);
            $mform->disabledIf($name, 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'registration';
            $this->add_time_duration($mform, $this->plugin, $name, mktime(8, 30, 0), mktime(9, 0, 0));
            $mform->disabledIf($name.'timestarthour',    'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timestartminute',  'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timefinishhour',   'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timefinishminute', 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'lunch';
            $this->add_time_duration($mform, $this->plugin, $name, mktime(12, 0, 0), mktime(13, 0, 0));
            $mform->disabledIf($name.'timestarthour',    'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timestartminute',  'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timefinishhour',   'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timefinishminute', 'templatetype', 'neq', self::TEMPLATE_GENERATE);

            $name = 'dinner';
            $this->add_time_duration($mform, $this->plugin, $name, mktime(18, 0, 0), mktime(20, 0, 0));
            $mform->disabledIf($name.'timestarthour',    'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timestartminute',  'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timefinishhour',   'templatetype', 'neq', self::TEMPLATE_GENERATE);
            $mform->disabledIf($name.'timefinishminute', 'templatetype', 'neq', self::TEMPLATE_GENERATE);

        } else {

            $name = 'publishcmid';
            $options = self::get_cmids($mform, $this->course, $this->plugin, $this->modulename, 'activity', 0, true);
            $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, $this->cmid);

            // --------------------------------------------------------
            $name = 'sessioninfo';
            $label = get_string($name, $this->plugin);
            $mform->addElement('header', $name, $label);
            // --------------------------------------------------------

            $this->set_schedule_menus();

            $name = 'schedule_event';
            $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $this->$name);

            $name = 'schedule_day';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_time';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_duration';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_room';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);

            $name = 'schedule_audience';
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $this->$name);
        }

        $this->add_action_buttons();
    }

    /**
     * add_field_templatefilename
     *
     * @param object $mform
     * @param string $plugin
     * @param string $name
     */
    static public function add_field_templatefilename($mform, $plugin, $name) {
        global $CFG;

        $elements = array();
        $default = '';
        $strman = get_string_manager();

        $dirpath = $CFG->dirroot.'/blocks/maj_submissions/templates';
        if (is_dir($dirpath)) {

            $filenames = scandir($dirpath);
            foreach ($filenames as $filename) {
                if (substr($filename, 0, 1)=='.') {
                    continue; // skip hidden shortnames
                }
                if (substr($filename, -5)=='.html') {
                    $label = substr($filename, 0, -5);
                } else if (substr($filename, -4)=='.htm') {
                    $label = substr($filename, 0, -4);
                } else {
                    $label = '';
                }
                if ($label) {
                    $label = 'template'.$label;
                    if ($strman->string_exists($label, $plugin)) {
                        $label = get_string($label, $plugin);
                    } else {
                        $label = $filename;
                    }
                    $elements[] = $mform->createElement('radio', $name, null, $label, $filename);
                    if ($default=='') {
                        $default = $filename;
                    }
                }
            }
        }

        if (count($elements)) {
            $group_name = 'group_'.$name;
            $label = get_string($name, $plugin);
            $mform->addGroup($elements, $group_name, $label, html_writer::empty_tag('br'), false);
            $mform->addHelpButton($group_name, $name, $plugin);
            $mform->setType($name, PARAM_TEXT);
            $mform->setDefault($name, $default);
        }
    }

    /**
     * set_schedule_menus
     *
     * @param boolean $shorten_texts (optional, default=FALSE)
     * @return void, but will update "schedule_xxx" properties
     */
    public function set_schedule_menus($shorten_texts=false) {
        global $DB;

        $config = $this->instance->config;
        $modinfo = get_fast_modinfo($this->course);

        // the database types
        $types = array('presentation',
                       'workshop',
                       'sponsored',
                       'event');

        // database field types
        $names = array('schedule_day',
                       'schedule_time',
                       'schedule_duration',
                       'schedule_room',
                       'schedule_audience');

        foreach ($types as $type) {
            if ($type=='event') {
                $types_cmid = $type.'scmid';
            } else {
                $types_cmid = 'collect'.$type.'scmid';
            }
            if (empty($config->$types_cmid)) {
                continue;
            }
            $cmid = $config->$types_cmid;
            if (! array_key_exists($cmid, $modinfo->cms)) {
                continue;
            }
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname != 'data') {
                continue;
            }
            $dataid = $cm->instance;
            foreach ($names as $name) {
                if ($this->$name) {
                    continue;
                }
                $params = array('dataid' => $dataid, 'name' => $name);
                if (! $field = $DB->get_record('data_fields', $params)) {
                    continue;
                }
                if (! $field->param1) {
                    continue;
                }
                if (self::is_menu_field($field)) {
                    $array = preg_split('/[\\r\\n]+/', $field->param1);
                    $array = array_filter($array);
                    $array = array_combine($array, $array);
                    foreach ($array as $key => $value) {
                        $value = self::convert_to_multilang($value, $config);
                        $value = block_maj_submissions::filter_text($value);
                        $array[$key] = $value;
                    }
                    array_unshift($array, '');
                    $this->$name = $array;
                }
            }

            $params = array('dataid' => $dataid, 'name' => $type.'_title');
            if ($field = $DB->get_record('data_fields', $params)) {
                $params = array('fieldid' => $field->id);
                if ($menu = $DB->get_records_menu('data_content', $params, 'content', 'id,content')) {
                    $name = get_string($types_cmid, $this->plugin);
                    $this->schedule_event[$name] = array_filter($menu);
                }
            }
        }
    }

    /**
     * is_menu_field
     *
     * @param object $field a record from the "data_fields" table
     * @return boolean TRUE if this is a "menu" field; otherwise FALSE
     */
    static public function is_menu_field($field) {
        if ($field->type=='admin') {
            return ($field->param10=='menu');
        } else {
            return ($field->type=='menu');
        }
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
        global $DB;

        $cm = false;
        $time = time();
        $msg = array();

        // check we have some form data
        if ($data = $this->get_data()) {

            $cm = $this->get_cm($msg, $data, $time, 'publishcmid');

            if ($cm) {
                $this->set_schedule_menus();

                $recordid = 0;
                $fields = array('schedule_event',    'schedule_day',  'schedule_time',
                                'schedule_duration', 'schedule_room', 'schedule_audience');

                $fields = array_flip($fields);
                foreach (array_keys($fields) as $name) {

                    $content = null;
                    if (isset($data->$name)) {
                        if ($name=='schedule_event') {
                            foreach (array_keys($this->$name) as $activityname) {
                                if (array_key_exists($data->$name, $this->$name[$activityname])) {
                                    $content = $this->$name[$activityname][$data->$name];
                                    $params = array('id' => $data->$name);
                                    $recordid = $DB->get_field('data_content', 'recordid', $params);
                                }
                            }
                        } else if (array_key_exists($data->$name, $this->$name)) {
                            $content = $this->$name[$data->$name];
                        }
                    }

                    if ($content===null) {
                        unset($fields[$name]);
                    } else {
                        $fields[$name] = $content;
                    }
                }

                if ($recordid) {

                    $params = array('id' => $recordid);
                    $dataid = $DB->get_field('data_records', 'dataid', $params);

                    foreach ($fields as $name => $content) {

                        if ($name=='schedule_event') {
                            // format feedback message and link to db record
                            $params = array('d' => $dataid, 'rid' => $recordid);
                            $link = new moodle_url('/mod/data/view.php', $params);
                            $params = array('href' => $link, 'target' => '_blank');
                            $link = html_writer::tag('a', $content, $params);
                            $msg[] = get_string('scheduleupdated', $this->plugin, $link);
                        } else {
                            // update field $content for this $recordid
                            $params = array('dataid' => $dataid, 'name' => $name);
                            if ($fieldid = $DB->get_field('data_fields', 'id', $params)) {

                                $params = array('fieldid' => $fieldid, 'recordid' => $recordid);
                                $DB->set_field('data_content', 'content', $content, $params);
                            }
                        }
                    }
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }

    /**
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        global $CFG;

        $defaultvalues = parent::get_defaultvalues($data, $time);

        $name = 'displayoptions';
        if (is_array($defaultvalues[$name])) {
            $defaultvalues[$name] = serialize($defaultvalues[$name]);
        }

        // search string to detect leading and trailing <body> tags in HTML file/snippet
        $search = '/(^.*?<body[^>]*>\s*)|(\s*<\/body>.*?)$/su';

        switch ($data->templatetype) {

            case self::TEMPLATE_ACTIVITY:
                $template = $DB->get_record('course_modules', array('id' => $data->templateactivity));
                $template = $DB->get_record($this->modulename, array('id' => $template->instance));
                break;

            case self::TEMPLATE_FILENAME:
                $filename = $CFG->dirroot.'/blocks/maj_submissions/templates/'.$data->templatefilename;
                if (is_file($filename)) {
                    $template = new stdClass();
                    $template->content = file_get_contents($filename);
                    $template->content = preg_replace($search, '', $template->content);
                }
                break;

            case self::TEMPLATE_UPLOAD:
                $file = $this->save_temp_file('templateupload');
                if (is_file($file)) {
                    $template = new stdClass();
                    $template->content = file_get_contents($file);
                    $template->content = preg_replace($search, '', $template->content);
                    fulldelete($file);
                }
                break;

            case self::TEMPLATE_GENERATE:
                $template = new stdClass();
                $template->content = $this->get_defaulttemplate($data);
                break;
        }

        if ($template) {
            foreach ($template as $name => $value) {
                if ($name=='id' || $name=='name') {
                    continue; // skip these fields
                }
                if (is_scalar($value)) {
                    $defaultvalues[$name] = $value;
                }
            }
        }

        return $defaultvalues;
    }

    /**
     * get_defaulttemplate
     *
     * @param array $data recently submitted from HTML form
     * @todo Finish documenting this function
     */
    protected function get_defaulttemplate($data) {

        $instance = $this->instance;
        $config = $instance->config;

        $numberofdays  = $data->numberofdays;
        $numberofrooms = $data->numberofrooms;
        $numberofslots = $data->numberofslots;

        $firstslottime = $data->firstslottime;
        $slotduration  = $data->slotduration;
        $slotinterval  = $data->slotinterval;

        $times = array('registration' => new stdClass(),
                       'lunch'        => new stdClass(),
                       'dinner'       => new stdClass());
        foreach (array_keys($times) as $time) {
            $times[$time]->start = mktime($data->{$time.'timestarthour'},
                                          $data->{$time.'timestartminute'});
            $times[$time]->finish = mktime($data->{$time.'timefinishhour'},
                                           $data->{$time.'timefinishminute'});
            $times[$time]->duration = ($times[$time]->finish - $times[$time]->start);
        }

        // get multilang title from config settings, if possible
        $title = array();
        if (empty($config->displaylangs)) {
            $langs = '';
        } else {
            $langs = $config->displaylangs;
        }
        $langs = block_maj_submissions::get_languages($langs);
        foreach ($langs as $lang) {
            $name = 'conferencename'.$lang;
            if (isset($config->$name) && $config->$name) {
                $title[] = html_writer::tag('span', $config->$name, array('class' => 'multilang', 'lang' => $lang));
            }
        }
        if (count($title)) {
            $title = implode('', $title);
        } else if (isset($config->conferencename)) {
            $title = $config->conferencename;
        } else {
            $title = '';
        }
        if ($title=='') {
            $title = $instance->get_string('conferenceschedule', $this->plugin);
        }

        // set array of common surnames to use as authors
        $authors = array('Chan',   'Doe',    'Garcia',
                         'Honda',  'Jones',  'Khan',
                         'Lee',    'Mensah', 'Nguyen',
                         'Nomo',   'Novak',  'Patel',
                         'Petrov', 'Rossi',  'Singh',
                         'Suzuki', 'Smith',  'Wang');

        // set array of letters from which to generate random words
        $letters = range(97, 122); // ascii a-z
        $letters = array_map('chr', $letters);

        $rooms = array();
        for ($i=0; $i<=$numberofrooms; $i++) {
            if ($i==0) {
                $name = $instance->get_string('roomname0', $this->plugin);
                $seats = $instance->get_string('totalseatsx', $this->plugin, 100);
                $topic = '';
            } else {
                $name = $instance->get_string('roomnamex', $this->plugin, $i);
                $seats = 20 + (5 * ($numberofrooms - $i));
                $seats = $instance->get_string('totalseatsx', $this->plugin, $seats);
                $topic = 'roomtopic'.(($i % 6) + 1);
                $topic = $instance->get_string($topic, $this->plugin);
            }
            $rooms[$i] = (object)array('name' => $name,
                                       'seats' => $seats,
                                       'topic' => $topic);
        }


        $days  = array();
        for ($i=1; $i<=$numberofdays; $i++) {
            $date = ($firstslottime + (($i - 1) * DAYSECS));
            $tabtext = $instance->multilang_userdate($date, 'scheduledatetabtext', $this->plugin);
            $fulltext = $instance->multilang_userdate($date, 'scheduledatefulltext', $this->plugin);
            $days[$i] = (object)array('tabtext' =>  $tabtext,
                                      'fulltext' => $fulltext);
        }

        $slots = array();
        foreach (array_keys($times) as $time) {

            if ($times[$time]->duration==0) {
                continue;
            }

            $start = $times[$time]->start;
            $start = $instance->multilang_userdate($start, 'schedulesessiontime', $this->plugin);

            $finish = $times[$time]->finish;
            $finish = $instance->multilang_userdate($finish, 'schedulesessiontime', $this->plugin);

            $duration = $times[$time]->duration;
            $duration = $instance->multilang_format_time($duration);

            $slots[] = (object)array('time' => "$start - $finish",
                                     'duration' => $duration,
                                     'allrooms' => true);
        }

        // cache the formatted slot duration (e.g. 20 mins)
        $duration = $instance->multilang_format_time($slotduration);

        $countslots = 0;
        $slottime = $firstslottime;
        while ($countslots < $numberofslots) {

            $start = $slottime;
            $slottime += $slotduration;
            $finish = $slottime;
            $slottime += $slotinterval;

            // skip slots during registration, lunch or dinner
            $skip = false;
            foreach (array_keys($times) as $time) {
                if ($start > $times[$time]->start && $start < $times[$time]->finish) {
                    $skip = true;
                    break;
                }
                if ($finish > $times[$time]->start && $finish < $times[$time]->finish) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // add this slot
            $start = $instance->multilang_userdate($start, 'schedulesessiontime', $this->plugin);
            $finish = $instance->multilang_userdate($finish, 'schedulesessiontime', $this->plugin);
            $slots[] = (object)array('time' => "$start - $finish",
                                     'duration' => $duration,
                                     'allrooms' => false);

            // increment counter
            $countslots++;
        }

        usort($slots, array(get_class($this), 'sort_slots'));

        $countrooms = max(array_keys($rooms));
        $countcols = ($countrooms + 1);

        $content = '';
        $content .= html_writer::start_tag('table', array('class' => 'schedule'));

        // COLGROUP (after CAPTION, before THEAD)
        $content .= html_writer::start_tag('colgroup');
        $content .= html_writer::start_tag('col', array('class' => 'room0 timeheading'));
        foreach ($rooms as $r => $room) {
            if ($r==0) {
                continue;
            }
            $content .= html_writer::start_tag('col', array('class' => "room$r"));
        }
        $content .= html_writer::end_tag('colgroup');

        // THEAD
        $content .= html_writer::start_tag('thead');

        // title
        $content .= html_writer::start_tag('tr', array('class' => 'scheduletitle'));
        $content .= html_writer::tag('td', $title, array('colspan' => $countcols));
        $content .= html_writer::end_tag('tr');

        // tabs (one DIV for each day)
        $content .= html_writer::start_tag('tr', array('class' => 'tabs'));
        $content .= html_writer::start_tag('td', array('colspan' => $countcols));
        foreach ($days as $d => $day) {
            $content .= html_writer::tag('div', $day->tabtext, array('class' => "tab day$d".($d==2 ? ' active' : '')));
        }
        $content .= html_writer::end_tag('td');
        $content .= html_writer::end_tag('tr');

        $content .= html_writer::end_tag('thead');

        // TBODY (one TBODY for each day)
        foreach ($days as $d => $day) {
            $content .= html_writer::start_tag('tbody', array('class' => "day day$d".($d==2 ? ' active' : '')));

            // date (one full-width cell)
            $content .= html_writer::start_tag('tr', array('class' => 'date'));
            $content .= html_writer::tag('td', $day->fulltext, array('colspan' => $countcols));
            $content .= html_writer::end_tag('tr');

            // slots
            $addroomheadings = true;
            foreach ($slots as $s => $slot) {

                // add room headings, if necessary
                if (empty($slot->allrooms)) {
                    if ($addroomheadings) {
                        $content .= html_writer::start_tag('tr', array('class' => 'roomheadings'));
                        $content .= html_writer::tag('th', '', array('class' => 'timeheading'));
                        foreach ($rooms as $r => $room) {
                            if ($r==0) {
                                continue;
                            }
                            $text = html_writer::tag('span', $room->name,  array('class' => 'roomname')).
                                    html_writer::tag('span', $room->seats, array('class' => 'totalseats')).
                                    html_writer::tag('div',  $room->topic, array('class' => 'roomtopic'));
                            $content .= html_writer::tag('th', $text, array('class' => "roomheading room$r"));
                        }
                        $content .= html_writer::end_tag('tr');
                        $addroomheadings = false;
                    }
                } else {
                    $addroomheadings = true;
                }

                $content .= html_writer::start_tag('tr', array('class' => 'slot'));

                $time = $slot->time.
                        html_writer::tag('span', $slot->duration, array('class' => 'duration'));
                $content .= html_writer::tag('td', $time, array('class' => 'timeheading'));

                $attending = rand(1, $countrooms);
                foreach ($rooms as $r => $room) {
                    $session = '';

                    if (empty($slot->allrooms)) {
                        $slot->allrooms = false;
                    }

                    if ($r==0 && $slot->allrooms==false) {
                        continue;
                    }
                    if ($r && $slot->allrooms==true) {
                        continue;
                    }
                    if ($r==0) {
                        $attending = $r;
                    }

                    // randomly skip some sessions
                    if ($attending==$r || rand(0, 2)) {

                        // time
                        $session .= html_writer::tag('div', $time, array('class' => 'time'));

                        // room
                        $text = html_writer::tag('span', $room->name,  array('class' => 'roomname')).
                                html_writer::tag('span', $room->seats, array('class' => 'totalseats')).
                                html_writer::tag('div',  $room->topic, array('class' => 'roomtopic'));
                        $session .= html_writer::tag('div',  $text, array('class' => 'room'));

                        // title
                        $text = $instance->get_string('sessiontitlex', $this->plugin, "$d.$s.$r");
                        $session .= html_writer::tag('div', $text, array('class' => 'title'));

                        // [schedulenumber]  + list of authors
                        $keys = array_rand($authors, rand(1, 2));
                        if (is_array($keys)) {
                            sort($keys);
                            $text = array();
                            foreach ($keys as $key) {
                                $text[] = $authors[$key];
                            }
                            $text = implode(', ', $text);
                        } else {
                            $text = $authors[$keys];
                        }
                        $text = html_writer::tag('span', "$d$s$r-P", array('class' => 'schedulenumber')).$text;
                        $session .= html_writer::tag('div', $text, array('class' => 'authors'));

                        //  summary
                        $summary = array();
                        for ($i=0; $i<40; $i++) {
                            $keys = array_rand($letters, rand(3, 7));
                            sort($keys);
                            $word = '';
                            foreach ($keys as $key) {
                                $word .= $letters[$key];
                            }
                            $summary[] = $word;
                        }
                        $summary = implode(' ', $summary);
                        $session .= html_writer::tag('div', $summary, array('class' => 'summary'));

                        // capacity
                        if ($r==$attending) {
                            $capacity = html_writer::empty_tag('input', array('type' => 'checkbox', 'value' => 1, 'checked' => 'checked')).
                                        html_writer::tag('span', 'Attending', array('class' => 'text'));
                        } else {
                            $capacity = html_writer::empty_tag('input', array('type' => 'checkbox', 'value' => 1)).
                                        html_writer::tag('span', 'Not attending', array('class' => 'text'));
                        }
                        $capacity = html_writer::tag('div', "$room->seats left", array('class' => 'emptyseats')).
                                    html_writer::tag('div', $capacity, array('class' => 'attendance'));
                        $session .= html_writer::tag('div', $capacity, array('class' => 'capacity'));
                    }

                    $class = 'session';
                    if ($slot->allrooms) {
                        $class .= ' allrooms';
                    }
                    if ($r==$attending) {
                        $class .= ' attending';
                    }
                    if ($session=='') {
                        $class .= ' emptysession';
                    }
                    $params = array('class' => $class);
                    if ($slot->allrooms) {
                        $params['colspan'] = $countrooms;
                    }
                    $content .= html_writer::tag('td', $session, $params);
                }

                $content .= html_writer::end_tag('tr');
            }

            $content .= html_writer::end_tag('tbody');
        }
        $content .= html_writer::end_tag('table');

        // we use JS to add the CSS file because <style> tags are removed by Moodle
        $src = new moodle_url('/blocks/maj_submissions/templates/template');
        $content .= html_writer::start_tag('script', array('type' => 'text/javascript'))."\n";
        $content .= "//<![CDATA[\n";
        $content .= '(function(){'."\n";
        $content .= '    var wwwroot = location.href.replace(new RegExp("^(.*?)/mod/.*$"), "$1");'."\n";
        $content .= '    var src = "'.$src->out_as_local_url().'";'."\n";
        $content .= '    var css = "@import url(" + wwwroot + src + ".css);";'."\n";
        $content .= '    var style = document.createElement("style");'."\n";
        $content .= '    style.setAttribute("type","text/css");'."\n";
        $content .= '    style.appendChild(document.createTextNode(css));'."\n";
        $content .= '    var script = document.createElement("script");'."\n";
        $content .= '    script.setAttribute("type","text/javascript");'."\n";
        $content .= '    script.setAttribute("src", wwwroot + src + ".js");'."\n";
        $content .= '    var head = document.getElementsByTagName("head");'."\n";
        $content .= '    head[0].appendChild(style);'."\n";
        $content .= '    head[0].appendChild(script);'."\n";
        $content .= '})();'."\n";
        $content .= "//]]>\n";
        $content .= html_writer::end_tag('script');

        return $content;
    }

    /**
     * sort_slots
     *
     * @param object $a
     * @param object $b
     * @return integer if ($a < $b) -1; if ($a > $b) 1; Otherwise, 0.
     */
    static public function sort_slots($a, $b) {

        $atime = explode(' - ', $a->time);
        $atime = explode(':', $atime[0]);

        $btime = explode(' - ', $b->time);
        $btime = explode(':', $btime[0]);

        if ($atime[0] < $btime[0]) {
            return -1;
        }
        if ($atime[0] > $btime[0]) {
            return 1;
        }
        if ($atime[1] < $btime[1]) {
            return -1;
        }
        if ($atime[1] > $btime[1]) {
            return 1;
        }
        return 0; // shouldn't happen !!
    }
}