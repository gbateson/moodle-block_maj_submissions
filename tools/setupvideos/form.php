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
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.filterconditions.php');

/**
 * block_maj_submissions_tool_setupvideos
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_setupvideos extends block_maj_submissions_tool_filterconditions {

    protected $type = 'collectpresentations';
    protected $modulename = '';
    protected $defaultname = '';

    protected $defaultvalues = array(
        'intro' => '',
        'introformat' => 1,
    );

    protected $timefields = array('timestart' => array(),
                                  'timefinish' => array());

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

        $name = 'sourcedatabase';
        $this->add_field_sourcedatabase($mform, $name, $this->cmid);
        $this->add_field_filterconditions($mform, 'filterconditions', $name);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $name = 'restrictroleid';
        $plugins = core_plugin_manager::instance()->get_enabled_plugins('availability');
        if (array_key_exists('role', $plugins)) {
            $options = get_assignable_roles($this->course->context);
            $options = array('' => get_string('none')) + $options;
            $default = $DB->get_field('role', 'id', array('shortname' => 'student'));
            $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options, $default);
        }

        $plugins = core_plugin_manager::instance()->get_enabled_plugins('mod');
        $options = array('bigbluebuttonbn', 'googlemeet', 'webex', 'zoom');
        $options  = array_intersect_key($plugins, array_flip($options));

        $name = 'videomodname';
        foreach (array_keys($options) as $modname) {
            $options[$modname] = get_string('pluginname', 'mod_'.$modname);
        }
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_ALPHANUM, $options);

        $this->add_action_buttons();
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
        global $CFG, $DB;

        $cm = false;
        $msg = array();
        $videomodname = '';
        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, 'sourcedatabase');
            $videomodname = $data->videomodname;
        }

        if ($cm && $videomodname) {

            // get the "lib.php" file for the selected video mod
            require_once($CFG->dirroot."/mod/$videomodname/lib.php");
            require_once($CFG->dirroot."/mod/$videomodname/locallib.php");

            // cache the database id
            $databasenum = $data->sourcedatabase;
            $dataid = get_fast_modinfo($this->course)->get_cm($databasenum)->instance;

            // cache the fieldid of the video field (it may not exist)
            $params = array('dataid' => $dataid, 'name' => 'presentation_video');
            $videofieldid = $DB->get_field('data_fields', 'id', $params);

            // initialize counters
            $counttotal = $DB->get_field('data_records', 'COUNT(*)', array('dataid' => $dataid));
            $countselected = 0;
            $countcreated = 0;

            // specifiy the presentation fields that we want
            $fields = array('presentation_title' => '',
                            'presentation_type' => '',
                            'presentation_language' => '',
                            'presentation_abstract' => '',
                            'presentation_handout_file' => '',
                            'presentation_slides_file' => '',
                            'presentation_url' => '',
                            'schedule_day' => '',
                            'schedule_time' => '',
                            'schedule_duration' => '');

            // get all records matching the filters (may update $data and $fields)
            if ($records = $this->get_filtered_records($dataid, $data, $fields)) {

                $duplicaterecords = array();
                $duplicatevideos = array();

                // sanitize submission titles (and remove duplicates)
                foreach ($records as $id => $record) {
                    if (empty($record->presentation_title)) {
                        $title = get_string('notitle', $this->plugin, $record->recordid);
                    } else {
                        $title = block_maj_submissions::plain_text($record->presentation_title);
                    }
                    if (array_key_exists($title, $duplicaterecords)) {
                        $duplicaterecords[$title]++;
                        unset($records[$id]);
                    } else {
                        $duplicaterecords[$title] = 0;
                        $records[$id]->title = $title;
                    }
                }

                // skip database records that already have video activity
                foreach ($records as $id => $record) {
                    $title = $record->presentation_title;
                    if (array_key_exists($title, $duplicatevideos)) {
                        $duplicatevideos[$title]++;
                        unset($records[$id]);
                    } else if ($DB->record_exists($data->videomodname, array('name' => $title, 'course' => $cm->course))) {
                        $duplicatevideos[$title] = 1;
                        unset($records[$id]);
                    } else {
                        $duplicatevideos[$title] = 0;
                    }
                }

                $dateformat = block_maj_submissions::get_date_format($config);
                if (empty($config->conferencetimestart)) {
                    $date = time();
                } else {
                    $date = $config->conferencetimestart;
                }
                $date = getdate($date);
                $dateyear = $date['year'];

                $removetime = false;
                $removedate = block_maj_submissions::REMOVE_YEAR;

                $multilangsearch = '/<span[^>]*lang="(\w+)"[^>]*>(.*?)<\/span>/ui';
                $datetextsearch = '/([^<>]+)( +)(\d+ *: *\d+)/u';
                $datetextreplace = '<small>$1</small>$2<big>$3</big>';

                $names = array('schedule_day', 'schedule_time', 'schedule_duration');
                foreach ($records as $id => $record) {
                    foreach ($names as $name) {
                        $clean = $name.'_clean';
                        $record->$clean = '';
                        if (empty($record->$name)) {
                            $record->$name = '';
                        } else {
                            if (preg_match_all($multilangsearch, $record->$name, $matches, PREG_OFFSET_CAPTURE)) {
                                $record->$clean = $matches[2][0][0]; // first multilang string
                                $i_max = (count($matches[0]) - 1);
                                for ($i=$i_max; $i >= 0; $i--) {
                                    // use the english date/time if it is available
                                    if ($matches[1][$i][0] == 'en') {
                                        $record->$clean = $matches[2][$i][0];
                                    }
                                }
                            } else {
                                // no multilang strings, so use entire string
                                $record->$clean = $record->$name;
                            }
                        }
                    }

                    if (empty($record->schedule_day_clean) || empty($record->schedule_time_clean)) {
                        $record->schedule_starttime = 0;
                        $record->schedule_starttime_multilang = 0;
                    } else {
                        $record->schedule_day_clean = preg_replace('/ *\(\w+\)/', '', $record->schedule_day_clean);
                        $record->schedule_time_clean = preg_replace('/(\d+) *: *(\d+).*/', '$1:$2', $record->schedule_time_clean);

                        // format full date string, e.g. Feb 19th 2021 10:30
                        // and then convert date string to unix timestamp
                        $record->schedule_starttime = $record->schedule_day_clean.' '.$dateyear.' '.$record->schedule_time_clean;
                        $record->schedule_starttime = strtotime($record->schedule_starttime);

                        // format timestamp as multilang date string
                        $record->schedule_starttime_multilang = $this->instance->multilang_userdate($record->schedule_starttime,
                                                                                                   $dateformat, $this->plugin,
                                                                                                   $removetime, $removedate);
                        $record->schedule_starttime_multilang = preg_replace($datetextsearch, $datetextreplace,
                                                                            $record->schedule_starttime_multilang);
                    }
                    if (empty($record->schedule_duration_clean)) {
                        $record->schedule_finishtime = 0;
                    } else {
                        $record->schedule_finishtime = $record->schedule_starttime;
                        $record->schedule_finishtime += (MINSECS * intval($record->schedule_duration_clean));
                    }
                }

                // sort records by schedule_starttime
                uasort($records, array(get_class($this), 'sort_by_datetime'));

                $duplicaterecords = array_filter($duplicaterecords);
                if ($count = count($duplicaterecords)) {
                    $a = html_writer::alist(array_keys($duplicaterecords));
                    $a = (object)array('count' => $count,'list' => $a);
                    $msg[] = get_string('duplicatevideorecords', $this->plugin, $a);
                }

                $duplicatevideos = array_filter($duplicatevideos);
                if ($count = count($duplicatevideos)) {
                    $a = html_writer::alist(array_keys($duplicatevideos));
                    $a = (object)array('count' => $count,'list' => $a);
                    $msg[] = get_string('duplicatevideos', $this->plugin, $a);
                }

                // create video activity for each submission record
                $datetime = 0;
                $countselected = count($records);
                foreach ($records as $record) {

                    // add label for $record->schedule_starttime
                    if ($datetime < $record->schedule_starttime) {
                        $datetime = $record->schedule_starttime;
                        // create new label for $record->schedule_starttime_multilang
                        $label = (object)array(
                            'course' => $this->course->id,
                            'modname' => 'label',
                            'labelnum' => self::CREATE_NEW,
                            'labelname' => format_text($record->schedule_starttime_multilang),
                            'coursesectionnum' => $data->coursesectionnum,
                            'coursesectionname' => $data->coursesectionname,
                            'intro' => $record->schedule_starttime_multilang,
                            'introformat' => FORMAT_HTML // = 1
                        );
                        $label = $this->get_cm($msg, $label, 'label');
                    }

                    $video = (object)$this->defaultvalues;

                    $video->modname = $videomodname;
                    $video->videonum = self::CREATE_NEW;
                    $video->videoname = $record->title;
                    $video->coursesectionnum = $data->coursesectionnum;
                    $video->coursesectionname = $data->coursesectionname;

                    // workaround for video mods, such as "mod_bigbluebuttonbn",
                    // that do not handle multilang content in the "intro" field
                    $video->intro = '<style>'."\n".
                        '.lang-en .multilang:not([lang=en]),'.
                        '.lang-ja .multilang:not([lang=ja])'.
                        ' { display: none; }'."\n".
                    '</style>'."\n";
                    $video->introformat = 1;

                    // add details of each field to intro
                    foreach ($fields as $name => $field) {
                        if (isset($record->$name)) {
                            if ($name == 'presentation_title') {
                                // do nothing
                            } else if ($name == 'presentation_abstract') {
                                $params = array('style' => 'text-align: justify; '.
                                                           'text-indent: 20px; '.
                                                           'max-width: 960px;');
                                $video->intro .= html_writer::tag('p', block_maj_submissions::plain_text($record->$name), $params)."\n";
                            } else if ($record->$name) {
                                $field = html_writer::tag('b', $field.': ');
                                $video->intro .= html_writer::tag('p', $field.$record->$name)."\n";
                            }
                        }
                    }

                    // add link back to submissions database
                    $field = html_writer::tag('b', get_string('moreinfo').': ');
                    $params = array('d' => $record->dataid,
                                    'rid' => $record->recordid,
                                    'mode' => 'single');
                    $url = new moodle_url('/mod/data/view.php', $params);
                    $video->intro .= html_writer::tag('p', $field.html_writer::link($url, $record->title, array('target' => 'MAJ')))."\n";

                    switch ($videomodname) {
                        case 'bigbluebuttonbn':
                            $video->type = 0; // room with recordings
                            $video->record = 1;
                            $video->openingtime = ($record->schedule_starttime - (MINSECS * 10));
                            $video->closingtime = $record->schedule_finishtime;
                            $participants = array(
                                array('selectiontype' => 'all',
                                      'selectionid' => 'all',
                                      'role' => BIGBLUEBUTTONBN_ROLE_VIEWER),
                                array('selectiontype' => 'user',
                                      'selectionid' => $record->userid,
                                      'role' => BIGBLUEBUTTONBN_ROLE_MODERATOR)
                            );
                            $video->participants = json_encode($participants);
                            $video->moderatorpass = bigbluebuttonbn_random_password(12);
                            $video->viewerpass = bigbluebuttonbn_random_password(12, $video->moderatorpass);
                            $video->meetingid = bigbluebuttonbn_unique_meetingid_seed();
                            $video->recordings_html = 1;
                            $video->recordings_deleted = 1;
                            $video->recordings_imported = 0;
                            $video->recordings_preview = 1;
                            break;
                    }

                    if ($cm = $this->get_cm($msg, $video, 'video')) {

                        if ($videofieldid) {

                            $videourl = new moodle_url("/mod/$videomodname/view.php", array('id' => $cm->id));
                            $videourl = $url->out(); // convert to string

                            $params = array('recordid' => $record->recordid,
                                            'fieldid' => $videofieldid);
                            if ($DB->record_exists('data_content', $params)) {
                                $DB->set_field('data_content', 'content', $videourl, $params);
                            } else {
                                $params['content'] = $videourl;
                                $DB->insert_record('data_content', $params);
                            }
                        }

                        $countcreated++;
                    }
                }

                // TODO: restrict coursesection access to users with "participant" role
                if ($roleid = $data->restrictroleid) {
                        $section = $DB->get_record('course_sections', array('course' => $cm->course,
                                                                            'section' => $cm->section));
                        $restrictions = (object)array(
                            'op' => '|',
                            'c' => array((object)array('type' => 'role', 'id' => (int)$roleid)),
                            'show' => true
                        );
                        self::set_section_restrictions($section, $restrictions);
                }

                $a = (object)array('total' => $counttotal,
                                   'selected' => $countselected,
                                   'created' => $countcreated);
                $msg[] = get_string('videoscreated', $this->plugin, $a);
            }
        }
        return $this->form_postprocessing_msg($msg);
    }

    /**
     * sort_by_datetime
     *
     * @param object $a
     * @param object $b
     * @return integer if ($a < $b) -1; if ($a > $b) 1; Otherwise, 0.
     */
    static public function sort_by_datetime($a, $b) {
        $field = 'schedule_starttime';
        $a_value = (empty($a->$field) ? 0 : $a->$field);
        $b_value = (empty($b->$field) ? 0 : $b->$field);
        if ($a_value < $b_value) {
            return -1;
        }
        if ($a_value > $b_value) {
            return 1;
        }
        return 0;
    }
}
