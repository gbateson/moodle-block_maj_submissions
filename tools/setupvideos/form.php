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

    // The source activity is the submissions database.
    protected $type = 'collectpresentations';
    protected $modulename = '';
    protected $defaultname = '';

    // time fileds will be set from the schedule
    protected $timefields = array('timestart' => array(),
                                  'timefinish' => array());

    /**
     * definition
     */
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        $this->set_form_id($mform);

        // get assignable roleids
        $plugins = core_plugin_manager::instance()->get_enabled_plugins('availability');
        if (array_key_exists('role', $plugins)) {
            $roleids = get_assignable_roles($this->course->context);
            $defaultroleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        } else {
            $roleids = array();
            $defaultroleid = 0;
        }

        // get video mods, if any
        $plugins = core_plugin_manager::instance()->get_enabled_plugins('mod');
        $videomodnames = array('bigbluebuttonbn', 'googlemeet', 'jitsi', 'webex', 'zoom');
        $videomodnames  = array_intersect_key($plugins, array_flip($videomodnames));

        foreach (array_keys($videomodnames) as $videomodname) {
            if (file_exists("$CFG->dirroot/mod/$videomodname")) {
                $videomodnames[$videomodname] = get_string('pluginname', 'mod_'.$videomodname);
            } else {
                // Ignore mods that are marked as "active" but are not available 
                // on the current site. This can sometimes happen after a restore.
                unset($videomodnames[$videomodname]);
            }
        }

        $showform = true;
        if (empty($videomodnames)) {
            $showform = false;
            $name = 'novideomodnames';
            $label = html_writer::tag('b', get_string('error'), array('class' => 'text-danger'));
            $mform->addElement('static', $name, $label, get_string($name, $this->plugin));
        }
        if (empty($roleids)) {
            //$showform = false;
            $name = 'norolecondition';
            $label = html_writer::tag('b', get_string('warning'), array('class' => 'text-warning'));
            $mform->addElement('static', $name, $label, get_string($name, $this->plugin));
        }

        if ($showform) {

            // extract the module context and course section, if possible
            if ($this->cmid) {
                $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
                $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
            } else {
                $context = $this->course->context;
                $sectionnum = 0;
            }

            // Fields to include in the filter conditions.
            $include = array(
                'presentation_category',
                'presentation_language',
                'presentation_method',
                'presentation_title',
                'presentation_type',
                'presentation_video',
                'event_name',
                'event_type',
                'schedule_status',
                'schedule_number',
                'schedule_duration',
                'schedule_day',
                'schedule_time',
                'schedule_roomname',
                'schedule_roomtype',
                'schedule_roomseats'
            );
            // We could also exclude the "peer_review_*" fields, but
            // they will be ignored anyway, if $include is not empty.

            $name = 'sourcedatabase';
            $this->add_field_sourcedatabase($mform, $name, $this->cmid);
            $this->add_field_filterconditions($mform, 'filterconditions', $name, $include);
            $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

            $name = 'separatesections';
            $this->add_field($mform, $this->plugin, $name, 'checkbox', PARAM_INT);
            $mform->disabledIf('coursesectionnum', $name, 'checked');
            $mform->disabledIf('coursesectionname', $name, 'checked');
 
            $name = 'restrictgroupid';
            list($groupids, $defaultgroupid) = $this->get_group_options($name);
            if (count($groupids)) {
                $groupids = array(0 => get_string('none')) + $groupids;
                $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $groupids);
            }

            $name = 'restrictroleid';
            if (count($roleids)) {
                $roleids = array(0 => get_string('none')) + $roleids;
                $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $roleids, $defaultroleid);
            }

            $name = 'videomodname';
            if (count($videomodnames)) {
                $this->add_field($mform, $this->plugin, $name, 'select', PARAM_ALPHANUM, $videomodnames);
            }

            $name = 'resetvideos';
            $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT, null, 1);

            $this->add_action_buttons();
        } else {
            $mform->addElement('cancel');
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
        global $CFG, $DB;

        $cm = false;
        $msg = array();
        $videomodid = 0;
        $videomodname = '';
        $config = $this->instance->config;

        // file storage may be need for BBB
        $fs = null;

        // Cache list of lang codes used in this conference.
        if (empty($config->displaylangs)) {
            $langs = '';
        } else {
            $langs = $config->displaylangs;
        }
        $langs = block_maj_submissions::get_languages($langs);

        if (empty($config->workshopstimestart)) {
            $workshop_startday = 0;
        } else {
            // format workshop start DAY (integer from 1 - 31)
            $workshop_startday = intval(date('j', $config->workshopstimestart));
        }

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, 'sourcedatabase');
            if ($videomodname = $data->videomodname) {
                $videomodid = $DB->get_field('modules', 'id', array('name' => $videomodname));
            }
        }

        if ($cm && $videomodid) {

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

            $select = 'dataid = ? AND ('.$DB->sql_like('name', '?').' OR '.$DB->sql_like('name', '?').')';
            $params = array($dataid, 'name_%', 'affiliation%');
            if ($namefields = $DB->get_records_select_menu('data_fields', $select, $params, 'name', 'id,name')) {
                $namefields = array_combine($namefields, array_fill(0, count($namefields), ''));
            } else {
                $namefields = array(); // shouldn't happen
            }
            $namerecords = $this->get_filtered_records($dataid, $data, $namefields);

            // specifiy the presentation fields that we want
            $fields = array('presentation_title' => '',
                            'presentation_type' => '',
                            'presentation_language' => '',
                            'presentation_abstract' => '',
                            'presentation_handout_file' => '',
                            'presentation_slides_file' => '',
                            'presentation_url' => '',
                            'event_name' => '',
                            'event_type' => '',
                            'event_description' => '',
                            'event_facilitator' => '',
                            'schedule_number' => '',
                            'schedule_day' => '',
                            'schedule_time' => '',
                            'schedule_duration' => '');

            // get all records matching the filters (may update $data and $fields)
            if ($records = $this->get_filtered_records($dataid, $data, $fields, false)) {

                // Prepend "authornames" field to $fields.
                $authornames = array('authornames' => get_string('authornames', $this->plugin));
                $fields = array_merge($authornames, $fields);

                $duplicaterecords = array();
                $duplicatevideos = array();

                // Tidy title and authors and and remove duplicates
                foreach ($records as $id => $record) {

                    // Extract and format authornames from the namefields.
                    $authornames = '';
                    if (property_exists($record, 'event_name')) {
                        $requireauthors = false;
                    } else {
                        $requireauthors = true;
                    }
                    if (array_key_exists($id, $namerecords)) {
                        $authornames = (array)$namerecords[$id];
                        $authornames = block_maj_submissions::format_authornames($id, $authornames, $requireauthors);
                        $authornames = strip_tags($authornames);
                        unset($namerecords[$id]);
                    }

                    // Sanitize submission titles
                    if (isset($record->presentation_title)) {
                        $title = block_maj_submissions::plain_text($record->presentation_title);
                    } else if (isset($record->event_name)) {
                        $title = block_maj_submissions::plain_text($record->event_name);
                    } else {
                        $title = '';
                    }
                    if (trim($title) == '') {
                        $title = get_string('notitle', $this->plugin, $record->recordid);
                        $records[$id]->presentation_title = $title;
                    }

                    if (array_key_exists($title, $duplicaterecords)) {
                        $duplicaterecords[$title]++;
                        unset($records[$id]);
                    } else {
                        $duplicaterecords[$title] = 0;
                        $records[$id]->title = $title;
                        $records[$id]->authornames = $authornames;
                    }
                }

                // skip database records that already have video activity
                if (isset($data->resetvideos) && $data->resetvideos) {
                    $data->resetvideos = true;
                } else {
                    $data->resetvideos = false;
                    foreach ($records as $id => $record) {
                        if ($record->presentation_title) {
                            $title = $record->presentation_title;
                        } else {
                            $title = $record->event_name;
                        }
                        if (array_key_exists($title, $duplicatevideos)) {
                            $duplicatevideos[$title]++;
                            unset($records[$id]);
                        } else {
                            $sql = 'SELECT cm.id, v.id AS vid, v.name '.
                                   'FROM {course_modules} cm, {'.$videomodname.'} v '.
                                   'WHERE cm.module = ? AND cm.course = ? AND cm.instance = v.id AND v.name = ?';
                            $params = array($videomodid, $cm->course, $title);
                            if (property_exists($cm, 'deletioninprogress')) {
                                $sql .= ' AND cm.deletioninprogress = ?';
                                $params[] = 0;
                            }
                            if ($DB->record_exists_sql($sql, $params)) {
                                $duplicatevideos[$title] = 1;
                                unset($records[$id]);
                            } else {
                                $duplicatevideos[$title] = 0;
                            }
                        }
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

                $multilangsearch = '/<span[^>]*lang="(\w+)"[^>]*>(.*?)<\/span>/ui';
                $datetextsearch = '/([^<>]+)( +)(\d+ *: *\d+)/u';
                $datetextreplace = '<small>$1</small>$2<big class="text-info">$3</big>';

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
                                // watch out for multilang dates that have been stripped of tags, e.g.
                                // Feb 19th (Fri)Feb月 19日 (Fri)Feb월 19일 (Fri)Feb月 19日 (Fri)Feb月 19日 (Fri)
                                if ($pos = strpos($record->$clean, ')')) {
                                    $record->$clean = substr($record->$clean, 0, $pos + 1);
                                }
                            }
                        }
                    }

                    if (empty($record->schedule_day_clean) || empty($record->schedule_time_clean)) {
                        $record->schedule_day_clean = '';
                        $record->schedule_time_clean = '';
                        $record->schedule_starttime = 0;
                    } else {
                        $record->schedule_day_clean = preg_replace('/ *\(\w+\)/', '', $record->schedule_day_clean);
                        $record->schedule_time_clean = preg_replace('/(\d+) *: *(\d+).*/', '$1:$2', $record->schedule_time_clean);

                        // format full date string, e.g. Feb 19th 2021 10:30
                        // and then convert date string to unix timestamp
                        $record->schedule_starttime = $record->schedule_day_clean.' '.$dateyear.' '.$record->schedule_time_clean;
                        $record->schedule_starttime = strtotime($record->schedule_starttime);
                        if (empty($record->schedule_starttime)) {
                            $record->schedule_starttime = 0;
                        }
                    }

                    if ($record->schedule_starttime == 0) {
                        $record->schedule_startday = 0;
                        $record->schedule_starttime = 0;
                        $record->schedule_startdate_multilang = block_maj_submissions::get_multilang_string('missingstarttime', $this->plugin);
                    } else {
                        // format start DAY (integer from 1 - 31)
                        $record->schedule_startday = intval(date('j', $record->schedule_starttime));

                        // format start DATE as multilang date string (remove time, remove year)
                        $record->schedule_startdate_multilang = $this->instance->multilang_userdate($record->schedule_starttime,
                                                                                                    $dateformat, $this->plugin,
                                                                                                    true, block_maj_submissions::REMOVE_YEAR);
                        // format start TIME as multilang date string (keep time, remove year)
                        $record->schedule_starttime_multilang = $this->instance->multilang_userdate($record->schedule_starttime,
                                                                                                    $dateformat, $this->plugin,
                                                                                                    false, block_maj_submissions::REMOVE_YEAR);
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
                uasort($records, array(get_class($this), 'sort_by_starttime'));

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
                $newcm = null;
                $startday = -1;
                $starttime = 0;
                $countselected = count($records);
                foreach ($records as $record) {

                    // create new label for $record->schedule_startday_multilang
                    if ($startday != $record->schedule_startday) {
                        $startday = $record->schedule_startday;

                        if ($data->separatesections) {
                            $data->coursesectionnum = 0;
                            $data->coursesectionname = '';

                            $section = null;
                            $sectionname = '';
                            if (empty($record->schedule_startdate_multilang)) {
                                $sectionname = block_maj_submissions::get_multilang_string('missingstarttime', $this->plugin);
                            } else {
                                $sectionname = $record->schedule_startdate_multilang;
                                $sectionname = preg_replace('/<\/?(big|small)[^>]*>/', '', $sectionname);
                            }
                            if ($sectionname) {
                                $strlen = 255;
                                if (strlen($sectionname) > $strlen) {
                                    $search = '/<span[^>]*class="multilang"[^>]*>(.+?)<\/span>/isu';
                                    if (preg_match_all($search, $sectionname, $matches)) {
                                        $sectionname = '';
                                        $matches = $matches[0];
                                        while ($match = array_shift($matches)) {
                                            $strlen -= strlen($match);
                                            if ($sectionname == '') {
                                                $sectionname = $match;
                                            } else if ($strlen >= 0) {
                                                $sectionname .= $match;
                                            }
                                        }
                                    }
                                }
                                if (strlen($sectionname) > 255) {
                                    $sectionname = format_text($record->schedule_startdate_multilang);
                                    $sectionname = shorten_text($sectionname);
                                    // default is 30 chars, which should be no more than 120 bytes.
                                }
                                $section = self::get_section($msg, $this->plugin, $this->course, $sectionname);
                            }
                            if ($section) {
                                // Note that sectionnum is not the "id".
                                $data->coursesectionnum = $section->section;
                                $data->coursesectionname = $sectionname;
                            } else {
                                // The section could not be created for some reason - shouldn't happen !!
                                $sectionname = get_string('cannotcreateorfindstructs', 'error');
                                if (in_array($sectionname, $msg) === false) {
                                    $msg[] = $sectionname;
                                }
                                $data->coursesectionname = $sectionname;
                                $data->coursesectionnum = self::CREATE_NEW;
                                // Maybe a new section can be created later.
                            }
                        } else {
                            $label = (object)array(
                                'course' => $this->course->id,
                                'modname' => 'label',
                                'labelnum' => self::CREATE_NEW,
                                'labelname' => format_text($record->schedule_startdate_multilang),
                                'coursesectionnum' => $data->coursesectionnum,
                                'coursesectionname' => $data->coursesectionname,
                                'intro' => html_writer::tag('h3', $record->schedule_startdate_multilang, array('class' => 'bg-info text-light rounded px-2 py-1')),
                                'introformat' => FORMAT_HTML, // = 1
                                'reusename' => true, // reuse name, if possible
                                'aftermod' => $newcm
                            );
                            $newcm = $this->get_cm($msg, $label, 'label');
                        }
                    }

                    // add label for $record->schedule_starttime
                    if ($starttime < $record->schedule_starttime) {
                        $starttime = $record->schedule_starttime;
                        // create new label for $record->schedule_starttime_multilang
                        $label = (object)array(
                            'course' => $this->course->id,
                            'modname' => 'label',
                            'labelnum' => self::CREATE_NEW,
                            'labelname' => format_text($record->schedule_starttime_multilang),
                            'coursesectionnum' => $data->coursesectionnum,
                            'coursesectionname' => $data->coursesectionname,
                            'intro' => $record->schedule_starttime_multilang,
                            'introformat' => FORMAT_HTML, // = 1
                            'reusename' => true, // reuse name, if possible
                            'aftermod' => $newcm
                        );
                        if ($workshop_startday && $workshop_startday == $record->schedule_startday) {
                            $text = block_maj_submissions::get_multilang_string('workshops', $this->plugin);
                            $params = array('class' => 'bg-light text-info d-inline-block border border-dark ml-2 px-2 py-0');
                            $label->intro .= ' '.html_writer::tag('big', $text, $params);
                        }
                        if (isset($record->presentation_type)) {
                            $type = $record->presentation_type;
                        } else if (isset($record->event_type)) {
                            $type = $record->event_type;
                        } else {
                            $type = '';
                        }
                        if (is_numeric(strpos($type, 'Keynote'))) {
                            $text = block_maj_submissions::get_multilang_string('keynotespeech', $this->plugin);
                            $params = array('class' => 'bg-light text-danger d-inline-block border border-dark ml-2 px-2 py-0');
                            $label->intro .= ' '.html_writer::tag('big', $text, $params);
                        }
                        $newcm = $this->get_cm($msg, $label, 'label');
                    }

                    $video = (object)$this->defaultvalues;

                    $video->modname = $videomodname;
                    $video->videonum = self::CREATE_NEW;
                    $video->videoname = $record->title;
                    $video->coursesectionnum = $data->coursesectionnum;
                    $video->coursesectionname = $data->coursesectionname;

                    $video->intro = '';
                    $video->introformat = FORMAT_HTML; // = 1

                    // workaround for video mods, such as "mod_bigbluebuttonbn",
                    // that do not handle multilang content in the "intro" field
                    if (count($langs) > 1) {
                        $video->intro = '<style>'."\n".
                            '.lang-'.$langs[0].' .multilang:not([lang='.$langs[0].']),'.
                            '.lang-'.$langs[1].' .multilang:not([lang='.$langs[1].'])'.
                            ' { display: none; }'."\n".
                        '</style>'."\n";
                    }

                    // Add details of each field to intro
                    foreach ($fields as $name => $field) {
                        if (substr($name, -10) == '_contentid' || substr($name, -8) == '_fieldid') {
                            continue;
                        }
                        if ($name == 'presentation_title' || $name == 'event_name') {
                            continue;
                        }
                        if (empty($record->$name)) {
                            continue;
                        }
                        $content = $record->$name;
                        if ($name == 'presentation_abstract') {
                            $params = array('style' => 'text-align: justify; '.
                                                       'text-indent: 20px; '.
                                                       'max-width: 960px;');
                            $video->intro .= html_writer::tag('p', block_maj_submissions::plain_text($content), $params)."\n";
                        } else {
                            // Add link to file in submissions database.
                            if ($name == 'presentation_slides_file' || $name == 'presentation_handout_file') {
                                $contentid = $record->{$name.'_contentid'};
                                $url = '/pluginfile.php/'.$cm->context->id.'/mod_data/content/'.$contentid.'/'.$content;
                                $content = html_writer::tag('a', $content, array('href' => new moodle_url($url)));
                            }
                            $field = html_writer::tag('b', $field.': ', array('class' => 'text-info'));
                            $video->intro .= html_writer::tag('p', $field.$content)."\n";
                        }
                    }

                    // add link back to submissions database
                    $field = html_writer::tag('b', get_string('moreinfo').': ', array('class' => 'text-info'));
                    $params = array('d' => $record->dataid,
                                    'rid' => $record->recordid,
                                    'mode' => 'single');
                    $url = new moodle_url('/mod/data/view.php', $params);
                    $video->intro .= html_writer::tag('p', $field.html_writer::link($url, $record->title, array('target' => 'MAJ')))."\n";

                    $video->reusename = $data->resetvideos;
                    $video->aftermod = $newcm;

                    switch ($videomodname) {

                        case 'bigbluebuttonbn':
                            $video->type = 0; // room with recordings
                            $video->record = 1;
                            if ($video->openingtime = $record->schedule_starttime) {
                                $video->openingtime -= (MINSECS * 10); // open 10 mins before start
                            }
                            $video->closingtime = $record->schedule_finishtime; // may be zero
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

                        case 'jitsi':
                            if ($video->timeopen = $record->schedule_starttime) {
                                $video->minpretime = 10; // open 10 mins before start
                            }
                            break;
                    }

                    if ($newcm = $this->get_cm($msg, $video, 'video')) {

                        if ($videofieldid) {

                            $videourl = new moodle_url("/mod/$videomodname/view.php", array('id' => $newcm->id));
                            $videourl = $videourl->out(false); // convert to string (not escaped)

                            $params = array('recordid' => $record->recordid,
                                            'fieldid' => $videofieldid);
                            if ($DB->record_exists('data_content', $params)) {
                                $DB->set_field('data_content', 'content', $videourl, $params);
                            } else {
                                $params['content'] = $videourl;
                                $DB->insert_record('data_content', $params);
                            }
                        }

                        // If the slides file is available, we "preload" it into the BBB actvities.
                        // Note that the slides will only be actually visible if the following BBB
                        // setting is enabled: $CFG->bigbluebuttonbn_preuploadpresentation_editable.
                        $name = 'presentation_slides_file';
                        if ($videomodname == 'bigbluebuttonbn' && isset($record->$name) && $record->$name) {

                            $filepath = '/';
                            $filename = $record->$name;

                            // Check for valid filetypes ("... any office document or PDF file")
                            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                            if (in_array($filetype, array('pdf', 'pptx', 'ppt', 'docx', 'doc', 'xlsx', 'xls'))) {

                                if ($fs === null) {
                                    $fs = get_file_storage();
                                }

                                // Define settings to identify the slides file in the submission database.
                                $component = 'mod_data';
                                $filearea = 'content';
                                $itemid = $record->{$name.'_contentid'};
                                if ($oldfile = $fs->get_file($cm->context->id, $component, $filearea, $itemid, $filepath, $filename)) {

                                    // Define the target settings for "pre-ploading" the the slides file to the BBB activity. 
                                    $context = context_module::instance($newcm->id);
                                    $component = "mod_$videomodname";
                                    $filearea = 'presentation';
                                    $itemid = 0;
                                    if ($fs->file_exists($context->id, $component, $filearea, $itemid, $filepath, $filename)) {
                                        // Hmm, file already exists. This should not happen on a live site,
                                        // but may occur during development development of this plugin.
                                    } else {
                                        $newfile = array(
                                            'contextid' => $context->id,
                                            'component' => $component,
                                            'filearea'  => $filearea,
                                            'itemid'    => $itemid,
                                            'filepath'  => $filepath,
                                            'filename'  => $filename
                                        );
                                        $newfile = $fs->create_file_from_storedfile($newfile, $oldfile);
                                    }
                                }
                            }
                        }
                        $countcreated++;
                    }
                }

                // Collect $params required to extract $section info from $DB
                $params = array();
                if (isset($newcm) && is_object($newcm)) {
                    $params['id'] = $newcm->section;
                    $params['course'] = $newcm->course;
                } else if (empty($data->coursesectionnum) || $data->coursesectionnum == self::CREATE_NEW) {
                    $params = array(); // no enough data
                } else {
                    // no $newcm was created, but we still have enough info to extract the $section 
                    $params['course'] = $this->course->id;
                    $params['section'] = $data->coursesectionnum;
                }

                // Collect section access restrictions, if any.
                $restrictions = array();
                if ($roleid = $data->restrictroleid) {
                    $restrictions[] = (object)array('type' => 'role', 'id' => intval($roleid));
                }
                if ($groupid = $data->restrictgroupid) {
                    $restrictions[] = (object)array('type' => 'group', 'id' => intval($groupid));
                }

                // if we have enough to extract $section, then update $restrictions
                if (count($params) && count($restrictions)) {
                    if ($section = $DB->get_record('course_sections', $params)) {
                        $restrictions = (object)array('op' => '|', 'c' => $restrictions, 'show' => true);
                        self::set_section_restrictions($section, $restrictions);
                        $msg[] = get_string('accessupdatedsection', $this->plugin, $section->section);
                    }
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
     * sort_by_starttime
     *
     * @param object $a
     * @param object $b
     * @return integer if ($a < $b) -1; if ($a > $b) 1; Otherwise, 0.
     */
    static public function sort_by_starttime($a, $b) {
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
