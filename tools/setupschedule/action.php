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
 * blocks/maj_submissions/tools/setupschedule/action.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

// announce that this is an ajax script
define('AJAX_SCRIPT', true);

/** Include required files */
require_once('../../../../config.php');

$blockname = 'maj_submissions';
$plugin = "block_$blockname";
$tool = 'toolsetupschedule';

// =========================================
// fetch main input parameters
//
// - to EDIT the schedule, we need $id and $action
//   actions: loadstrings
//            loadtools
//            loaditems
//            loadschedule
//            loadinfo
//
// - to VIEW the schedule, we need $cmid and $action
//   actions: loadattendance
//
// - to UPDATE attendance, we need $rid, $attend and $action
//   actions: updateattendance
// =========================================

$id = optional_param('id', 0, PARAM_INT); // block_instances id
$rid = optional_param('rid', 0, PARAM_INT); // database_records id
$cmid = optional_param('cmid', 0, PARAM_INT); // course_modules id
$attend = optional_param('attend', 0, PARAM_INT); // 1 or 0
$action = optional_param('action', '', PARAM_ALPHA);

// =========================================
// if necessary, determine cmid from rid
// =========================================

if ($rid) {
    $select = 'cm.id';
    $from   = '{course_modules} cm,'.
              '{modules} m,'.
              '{data_records} dr';
    $where  = 'dr.id = ? '.
              'AND dr.dataid = cm.instance '.
              'AND cm.module = m.id '.
              'AND m.name = ?';
    $params = array($rid, 'data');
    $cmid = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params);
}

// =========================================
// if necessary, determine block_instance id
// from cmid (only one block per course)
// =========================================

if ($cmid) {
    $select = 'bi.id';
    $from   = '{block_instances} bi,'.
              '{context} ctx,'.
              '{course_modules} cm';
    $where  = 'cm.id = ? '.
              'AND cm.course  = ctx.instanceid '.
              'AND ctx.contextlevel = ? '.
              'AND ctx.id = bi.parentcontextid '.
              'AND bi.blockname = ?';
    $params = array($cmid, CONTEXT_COURSE, 'maj_submissions');
    $id = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params);
}

// =========================================
// fetch main records from Moodle Database
// =========================================

if (! $block_instance = $DB->get_record('block_instances', array('id' => $id))) {
    print_error('invalidinstanceid', $plugin);
}

if (! $block = $DB->get_record('block', array('name' => $block_instance->blockname))) {
    print_error('invalidblockid', $plugin, $block_instance->blockid);
}

if (class_exists('context')) {
    $context = context::instance_by_id($block_instance->parentcontextid);
} else {
    $context = get_context_instance_by_id($block_instance->parentcontextid);
}

if (! $course = $DB->get_record('course', array('id' => $context->instanceid))) {
    print_error('invalidcourseid', $plugin, $block_instance->pageid);
}
$course->context = $context;

// =========================================
// check access rights
// =========================================

require_login($course->id);

if (isguestuser()) {
    die('');
}

if ($action=='loadattendance' || $action=='updateattendance') {
    // delegate wants to load/update attendance
    require_capability('mod/assign:view', $context);
} else {
    // manager wants to load strings/tools/schedule/items/info
    require_capability('moodle/course:manageactivities', $context);
}

// =========================================
// setup the block instance object
// =========================================

$instance = block_instance($blockname, $block_instance);
$instance->set_multilang(true);
$config = $instance->config;

// =========================================
// take appropriate action
// =========================================

$html = '';
switch ($action) {

    case 'loadattendance':

        $names = array('attending', 'emptyseatsx', 'fullschedule', 'myschedule', 'notattending', 'seatsavailable');
        foreach ($names as $name) {
            $a = ($name=='emptyseatsx' ? 99 : null);
            $string = json_encode(get_string($name, $plugin, $a));
            $html .= 'MAJ.str.'.$name.' = '.$string.';'."\n";
        }

        $html .= 'MAJ.attend = {};'."\n";
        $params = array('instanceid' => $id,
                        'userid'     => $USER->id,
                        'attend'     => 1);
        if ($attendances = $DB->get_records($plugin, $params)) {
            foreach ($attendances as $a) {
                $html .= 'MAJ.attend['.$a->recordid.'] = '.intval($a->attend).';';
            }
        }

        // get number of empty seats in each room
        if ($info = $config->collectpresentationscmid) {
            $info = block_maj_submissions::get_room_info($info, 0, 'seats');
            $info = block_maj_submissions::get_seats_info($info);
        } else {
            $info = new stdClass();
        }
        $html .= 'MAJ.emptyseats = '.json_encode($info).';'."\n";
        break;

    case 'updateattendance':

        if ($rid) {
            $params = array('instanceid' => $id,
                            'recordid'   => $rid,
                            'userid'     => $USER->id);
            if (! $DB->record_exists($plugin, $params)) {
                $DB->insert_record($plugin, $params);
            }
            $DB->set_field($plugin, 'attend', ($attend ? 1 : 0), $params);

            // get number of seats allocated in this presentation
            $params = array('instanceid' => $id,
                            'recordid'   => $rid,
                            'attend'     => 1);
            if ($usedseats = $DB->get_field($plugin, 'COUNT(*)', $params)) {
                $usedseats = intval($usedseats);
            } else {
                $usedseats = 0;
            }

            if ($totalseats = block_maj_submissions::get_room_seats($cmid, $rid)) {
                $html .= get_string('emptyseatsx', $plugin, $totalseats - $usedseats);
            }
        }
        break;

    case 'loadstrings':

        // languages
        $langs = get_string_manager()->get_list_of_languages();
        $names = block_maj_submissions::get_languages($config->displaylangs);
        foreach ($names as $name) {
            if (strpos($name, '_')) {
                $parent = substr($name, 0, 2);
            } else {
                $parent = '';
            }
            switch (true) {
                case array_key_exists($name, $langs):
                    $lang = $langs[$name];
                    break;
                case array_key_exists($parent, $langs):
                    $lang = $langs[$parent]." ($name)";
                    break;
                default:
                    $lang = $name;
            }
            $lang = json_encode($lang);
            $html .= 'MAJ.str.'.$name.' = '.$lang.';'."\n";
        }

        // langconfig strings
        $names = array('labelsep');
        foreach ($names as $name) {
            $string = json_encode(trim(get_string($name, 'langconfig')));
            $html .= 'MAJ.str.'.$name.' = '.$string.';'."\n";
        }

        // plugin strings
        $names = array('addday','addroom', 'addroomheadings', 'addslot',
                       'addedday', 'addedroom', 'addedroomheadings', 'addedslot',
                       'allheadingsalldays', 'allheadingsthisday', 'applyto',
                       'confirmday', 'confirmroom', 'confirmroomheadings', 'confirmsession', 'confirmslot',
                       'currentheadings', 'daytext', 'duration', 'durationseparator',
                       'editday', 'editroom', 'editroomheadings', 'editsession', 'editslot',
                       'editedday', 'editedroom', 'editedroomheadings', 'editedsession', 'editedslot',
                       'finishtime', 'numhours', 'nummins', 'position', 'positionbefore', 'positionlast',
                       'removeday', 'removeroom', 'removeroomheadings', 'removesession', 'removeslot',
                       'removedday', 'removedroom', 'removedroomheadings', 'removedsession', 'removedslot',
                       'room', 'roomcount', 'roomname', 'roomseats', 'roomtopic', 'largeroom',
                       'slot', 'slotcount', 'slotinterval', 'slotlength', 'slotstart', 'starttime',
                       'title', 'authornames', 'schedulenumber', 'category', 'type', 'rowspan', 'colspan');

        foreach ($names as $name) {
            $string = json_encode(get_string($name, $plugin));
            $html .= 'MAJ.str.'.$name.' = '.$string.';'."\n";
        }

        // standard strings
        $names = array('add', 'cancel', 'ok', 'remove', 'update');
        foreach ($names as $name) {
            $string = json_encode(get_string($name));
            $html .= 'MAJ.str.'.$name.' = '.$string.';'."\n";
        }

        // standard form strings
        $names = array('day', 'time');
        foreach ($names as $name) {
            $string = json_encode(get_string($name, 'form'));
            $html .= 'MAJ.str.'.$name.' = '.$string.';'."\n";
        }

        // multilang strings
        $names = array('durationtxt');
        foreach ($names as $name) {
            switch ($name) {
                case 'durationtxt';
                    $string = $instance->multilang_format_time(300);
                    $string = str_replace('5', '{a}', $string);
                    break;
            }
            $html .= 'MAJ.str.'.$name.' = '.json_encode($string).';'."\n";
        }

        break;

    case 'loadtools':

        // determine earliest start time
        $start = array(
            $config->workshopstimestart,
            $config->conferencetimestart,
        );
        $start = min(array_filter($start));

        // determine latest start time
        $finish = array(
            $config->workshopstimefinish,
            $config->conferencetimefinish,
        );
        $finish = max(array_filter($finish));

        // determine number of days
        $numdays = max(0, $finish - $start);
        $numdays = ceil($numdays / DAYSECS);

        // cache schedule date format
        $dateformat = get_string('scheduledatetabtext', $plugin);

        // cache day strings
        $days = array();
        if ($numdays) {
            $days['alldays'] = get_string('alldays', $plugin);
            for ($i=1; $i<=$numdays; $i++) {
                $text = ($start + (($i - 1) * DAYSECS));
                $text = userdate($text, $dateformat);
                $days["day$i"] = $text;
            }
        }

        $commands = array(
            'initializeschedule'  => $days,
            'emptyschedule'       => $days,
            'populateschedule'    => $days,
            'renumberschedule'    => $days,
            'scheduleinfo'    => array('add' => get_string('add', $plugin),
                                       'remove' => get_string('remove', $plugin)),
            'add' => array('slot' => get_string('slot', $plugin),
                           'room' => get_string('room', $plugin),
                           'roomheadings' => get_string('roomheadings', $plugin),
                           'day'  => get_string('day', $plugin))
        );

        $params = array('class' => 'commands');
        $html .= html_writer::start_tag('div', $params);

        $zindex = 100;
        foreach ($commands as $command => $subcommands) {

            $zindex -= 10;
            $params = array('id' => $command,
                            'class' => 'command',
                            'style' => "z-index: $zindex;");
            $html .= html_writer::start_tag('div', $params);
            $html .= get_string($command, $plugin);

            if (count($subcommands)) {

                $params = array('class' => 'subcommands');
                $html .= html_writer::start_tag('div', $params);

                foreach ($subcommands as $subcommand => $text) {
                    $params = array('id' => "$command-$subcommand",
                                    'class' => 'subcommand');
                    $html .= html_writer::tag('div', $text, $params);
                }
                $html .= html_writer::end_tag('div');
            }
            $html .= html_writer::end_tag('div');
        }
        $html .= html_writer::end_tag('div');
        break;

    case 'loadschedule':

        if ($cmid = $config->publishcmid) {
            $cm = get_fast_modinfo($course)->get_cm($cmid);
            if ($cm->modname=='page') {
                $html = $DB->get_field('page', 'content', array('id' => $cm->instance));
                $html = preg_replace('/<script[^>]*>.*<\/script>/s', '', $html);
            }
        }
        break;

    case 'loaditems':

        // the following line will not be necessary when DB fields use multilang SPANs
        require_once("$CFG->dirroot/blocks/$blockname/tools/form.php");

        // cache certain strings
        $strnotattending = get_string('notattending', $plugin);

		// cache the modinfo for this $course
        $modinfo = get_fast_modinfo($course);

        // ignore these fieldtypes
        $fieldtypes = array('action',
        					'constant',
        					'file',
        					'picture',
        					'template',
        					'url');
        list($fieldignore, $fieldparams) = $DB->get_in_or_equal($fieldtypes, SQL_PARAMS_QM, '', false);
        $fieldignore = ' AND df.type '.$fieldignore;
        unset($fieldtypes);

		// ignore record ids that are already in the schedule
		$ridignore = '';
        $ridparams = array();
        if ($cmid = $config->publishcmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->modname=='page') {
                $content = $DB->get_field('page', 'content', array('id' => $cm->instance));
                if (preg_match_all('/(?<=id_recordid_)\d+/', $content, $recordids)) {
                	$recordids = array_unique($recordids[0]);
                	list($ridignore, $ridparams) = $DB->get_in_or_equal($recordids, SQL_PARAMS_QM, '', false);
					$ridignore = ' AND dc.recordid '.$ridignore;
                }
                unset($content, $recordids);
            }
        }

        // search/replace strings to extract CSS class from field param1
        $multilangsearch = array('/<(span|lang)\b[^>]*>([ -~]*?)<\/\1>/u',
                                 '/<(span|lang)\b[^>]*>.*?<\/\1>/u');
        $multilangreplace = array('$2', '');

        $firstwordsearch = array('/[^a-zA-Z0-9 ]/u', '/ .*$/u');
        $firstwordreplace = array('', '');

        $durationsearch = array('/(^.*\()|(\).*$)/u', '/[^0-9]/', '/^.*$/');
        $durationreplace = array('', '', 'duration$0');

        // cache for CSS classes derived from
        // presentation "type" and "category"
        $classes = array('category' => array(),
                         'type'     => array(),
                         'duration' => array());

        // check these database types
        $types = array('presentation',
                       'workshop',
                       'sponsored',
                       'event');

        $items = array();
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

            // get all records in this DB
            $select = 'dc.id, dc.fieldid, dc.recordid, '.
                      'df.name AS fieldname, dc.content';
            $from   = '{data_content} dc '.
                      'JOIN {data_fields} df ON df.id = dc.fieldid';
            $where  = 'df.dataid = ?'.$ridignore.$fieldignore.' AND dc.recordid IN (143,144)';
            $order  = 'dc.recordid';
            $params = array_merge(array($cm->instance), $ridparams, $fieldparams);

            $contents = "SELECT $select FROM $from WHERE $where ORDER BY $order";
            if ($contents = $DB->get_records_sql($contents, $params)) {
                foreach ($contents as $content) {
                    $recordid = $content->recordid;
                    $fieldname = $content->fieldname;
                    if (! array_key_exists($recordid, $items)) {
                        $items[$recordid] = array();
                    }
                    $items[$recordid][$fieldname] = $content->content;
                }
            }
            unset($contents, $content);
        }

        // add a "session" for each $item
        foreach ($items as $recordid => $item) {

            $name = 'submission_status';
            if (array_key_exists($name, $item)) {
                if (strpos($item[$name], 'Not accepted') || strpos($item[$name], 'Cancelled')) {
                    continue;
                }
            }

            $sessionclass = 'session';

            // extract category
            //     個人の発表 Individual presentation
            //     スポンサー提供の発表 Sponsored presentation
            //     日本ムードル協会の補助金報告 MAJ R&D grant report
            if (empty($item['presentation_category'])) {
                $presentationcategory = '';
            } else {
                $presentationcategory = $item['presentation_category'];
                if (empty($classes['category'][$presentationcategory])) {
                    $class = $presentationcategory;
                    if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                        $class = preg_replace($multilangsearch, $multilangreplace, $class);
                    }
                    $class = preg_replace($firstwordsearch, $firstwordreplace, $class);
                    $classes['category'][$presentationcategory] = strtolower(trim($class));
                }
                $sessionclass .= ' '.$classes['category'][$presentationcategory];
            }

            // extract type
            //     ライトニング・トーク（１０分） Lightning talk (10 mins)
            //     ケース・スタディー（２０分） Case study (20 mins)
            //     プレゼンテーション（２０分） Presentation (20 mins)
            //     プレゼンテーション（４０分） Presentation (40 mins)
            //     プレゼンテーション（９０分） Presentation (90 mins)
            //     ショーケース（９０分） Showcase (90 mins)
            //     商用ライトニング・トーク（１０分） Commercial lightning talk (10 mins)
            //     商用プレゼンテーション（４０分） Commercial presentation (40 mins)
            //     商用プレゼンテーション（９０分） Commercial presentation (90 mins)
            if (empty($item['presentation_type'])) {
                $presentationtype = '';
            } else {
                $presentationtype = $item['presentation_type'];
                if (empty($classes['type'][$presentationtype])) {
                    $class = $presentationtype;
                    if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                        $class = preg_replace($multilangsearch, $multilangreplace, $class);
                    }
                    $class = preg_replace($firstwordsearch, $firstwordreplace, $class);
                    $classes['type'][$presentationtype] = strtolower(trim($class));
                }
                $sessionclass .= ' '.$classes['type'][$presentationtype];
            }

            // extract duration CSS class e.g. duration40mins
            if (empty($item['schedule_duration'])) {
                $scheduleduration = $presentationtype;
            } else {
                $scheduleduration = $item['schedule_duration'];
            }
            if ($scheduleduration) {
                if (isset($classes['duration'][$scheduleduration])) {
                    $class = $classes['duration'][$scheduleduration];
                } else {
                    $class = $scheduleduration;
                    if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                        $class = preg_replace($multilangsearch, $multilangreplace, $class);
                    }
                    $class = preg_replace($durationsearch, $durationreplace, $class);
                    if (preg_match('/^\s*duration\d+\s*$/i', $class)) {
                        $class = strtolower(trim($class));
                    } else {
                        $class = ''; // no duration specfied
                    }
                    $classes['duration'][$scheduleduration] = $class;
                }
                if ($class) {
                    $sessionclass .= ' '.$class;
                }
            }

            // extract duration
            if (empty($item['schedule_duration'])) {
                $duration = $item['presentation_type'];
                $duration = preg_match('/[^0-9]/', '', $duration);
                $duration = $instance->multilang_format_time($duration);
            } else {
                $duration = $item['schedule_duration'];
            }

            // start session DIV
            $html .= html_writer::start_tag('div', array('id' => 'id_recordid_'.$recordid,
                                                         'class' => $sessionclass,
                                                         'style' => 'display: inline-block;'));
            // time and duration
            $html .= html_writer::start_tag('div', array('class' => 'time'));
            $html .= html_writer::tag('span', $item['schedule_time'], array('class' => 'startfinish'));
            $html .= html_writer::tag('span', $duration, array('class' => 'duration'));
            $html .= html_writer::end_tag('div');

            // room
            $html .= html_writer::start_tag('div', array('class' => 'room'));
            $html .= html_writer::tag('span', $item['schedule_roomname'], array('class' => 'roomname'));
            $html .= html_writer::tag('span', '', array('class' => 'roomseats'));
            $html .= html_writer::tag('span', '', array('class' => 'roomtopic'));
            $html .= html_writer::end_tag('div');

            // title
            $html .= html_writer::tag('div', $item['presentation_title'], array('class' => 'title'));

            // format authornames
            $authornames = array();
            $fields = preg_grep('/^name_(surname)(.*)$/', array_keys($item));
            foreach ($fields as $field) {
                if (empty($item[$field])) {
                    continue;
                }
                if (trim($item[$field])=='') {
                    continue;
                }
                $i = 0;
                $name = '';
                $type = '';
                $lang = 'xx';
                $parts = explode('_', $field);
                switch (count($parts)) {
                    case 2:
                        list($name, $type) = $parts;
                        break;
                    case 3:
                        if (is_numeric($parts[2])) {
                            list($name, $type, $i) = $parts;
                        } else {
                            list($name, $type, $lang) = $parts;
                        }
                        break;
                    case 4:
                        if (is_numeric($parts[2])) {
                            list($name, $type, $i, $lang) = $parts;
                        } else {
                            list($name, $type, $lang, $i) = $parts;
                        }
                        break;
                }
                if (empty($authornames[$i])) {
                    $authornames[$i] = array();
                }
                if (empty($authornames[$i][$lang])) {
                    $authornames[$i][$lang] = array();
                }
                $authornames[$i][$lang][$type] = block_maj_submissions::textlib('strtotitle', $item[$field]);
            }

            ksort($authornames);
            foreach ($authornames as $i => $langs) {
                // remove names with no surname
                foreach ($langs as $lang => $name) {
                    if (empty($name['surname'])) {
                        unset($langs[$lang]);
                    }
                }
                // format names as multilang if necessary
                $count = count($langs);
                if ($count==0) {
                    $authornames[$i] = '';
                    continue;
                }
                if ($count==1) {
                    $authornames[$i] = reset($langs);
                    $authornames[$i] = $authornames[$i]['surname'];
                    continue;
                }
                foreach ($langs as $lang => $name) {
                    $name = $name['surname'];
                    $params = array('class' => 'multilang', 'lang' => $lang);
                    $authornames[$i][$lang] = html_writer::tag('span', $name, $params);
                }
                $authornames[$i] = implode('', $authornames[$i]);
            }
            $authornames = array_filter($authornames);
            $authornames = implode(', ', $authornames);

            if ($authornames=='') {
                $authornames = 'Tom, Dick, Harry';
            }

            // for commercial presentations, we append the (Company name) too
            if (strpos($presentationcategory, 'Sponsored')) {
                $affiliation = array();
                $fields = preg_grep('/^affiliation(.*)$/', array_keys($item));
                foreach ($fields as $field) {
                    if (empty($item[$field])) {
                        continue;
                    }
                    if (trim($item[$field])=='') {
                        continue;
                    }
                    $parts = explode('_', $field);
                    if (count($parts) > 1) {
                        $lang = end($parts);
                    } else {
                        $lang = 'xx';
                    }
                    $affiliation[$lang] = $item[$field];
                }
                if (count($affiliation) > 2) {
                    foreach ($affiliation as $lang => $name) {
                        $params = array('class' => 'multilang', 'lang' => $lang);
                        $affiliation[$lang] = html_writer::tag('span', $name, $params);
                    }
                }
                if ($affiliation = implode('', $affiliation)) {
                    $authornames .= " ($affiliation)";
                }
            }

            // schedule number and authornames
            $html .= html_writer::start_tag('div', array('class' => 'authors'));
            $html .= html_writer::tag('span', $item['schedule_number'], array('class' => 'schedulenumber'));
            $html .= html_writer::tag('span', $authornames, array('class' => 'authornames'));
            $html .= html_writer::end_tag('div');

            // category and type
            $html .= html_writer::start_tag('div', array('class' => 'categorytype'));
            $html .= html_writer::tag('span', $presentationcategory, array('class' => 'category'));
            $html .= html_writer::tag('span', $presentationtype, array('class' => 'type'));

            $html .= html_writer::end_tag('div'); // end categorytype DIV

            // summary (remove all tags and nbsp)
            $text = $item['presentation_abstract'];
            $text = block_maj_submissions_tool_form::plain_text($text);
            $html .= html_writer::tag('div', $text, array('class' => 'summary'));

            // capacity
            $html .= html_writer::start_tag('div', array('class' => 'capacity'));
            $html .= html_writer::tag('div', '', array('class' => 'emptyseats'));
            $html .= html_writer::start_tag('div', array('class' => 'attendance'));
            $html .= html_writer::empty_tag('input', array('id' => 'id_attend_'.$recordid,
                                                           'name' => 'attend['.$recordid.']',
                                                           'type' => 'checkbox',
                                                           'value' => '1'));
            $html .= html_writer::tag('label', $strnotattending, array('for' => 'id_attend_'.$recordid));
            $html .= html_writer::end_tag('div'); // end attendance DIV
            $html .= html_writer::end_tag('div'); // end capacity DIV

            $html .= html_writer::end_tag('div'); // end session DIV
        }
        break;

    case 'loadinfo':
        if ($cmid = $config->collectpresentationscmid) {

            $info = new stdClass();
            $info->userids = array();
            $info->icons = array();

            $names = array(); // firstname + lastname or authors
            $fields = array(); // suffix of target fields e.g. times

            $params = array('presentation_language',
                            'presentation_times',
                            'presentation_topics',
                            'presentation_keywords');
            list($where, $params) = $DB->get_in_or_equal($params);
            $select = 'dc.id, '.
                      'dc.content AS value, '.
                      'dc.recordid, '.
                      'dr.userid, '.
                      'df.name AS field';
            $from   = '{data_content} dc, '.
                      '{data_records} dr, '.
                      '{data_fields} df, '.
                      '{course_modules} cm';
            $where  = 'dc.recordid = dr.id '.
                      'AND dc.fieldid = df.id '.
                      'AND (df.name '.$where.
                       ' OR '.$DB->sql_like('df.name', '?').' '.
                       ' OR '.$DB->sql_like('df.name', '?').
                       ') '.
                      'AND df.dataid = cm.instance '.
                      'AND cm.id = ? '.
                      'AND dc.content <> ?';
            $order  = 'dc.recordid, df.name';
            array_push($params, 'name_given_%', 'name_surname_%', $cmid, '');
            if ($contents = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {

                // sort the $contents records recordid, language-times-topics-keywords
                uasort($contents, function ($a, $b) {
                    switch (true) {
                        case ($a->recordid < $b->recordid): return -1;
                        case ($a->recordid > $b->recordid): return  1;
                    }
                    $afield = substr($a->field, 13);
                    $bfield = substr($b->field, 13);
                    switch (true) {
                        case ($afield=='language'): return -1;
                        case ($bfield=='language'): return  1;
                        case ($afield=='times')   : return -1;
                        case ($bfield=='times')   : return  1;
                        case ($afield=='topics')  : return -1;
                        case ($bfield=='topics')  : return  1;
                        case ($afield=='keywords'): return -1;
                        case ($bfield=='keywords'): return  1;
                    }
                    return ($a->field < $b->field ? -1 : ($a->field > $b->field ? 1 : 0));
                });

                foreach ($contents as $content) {
                    $rid = $content->recordid;
                    $value = $content->value;
                    $field = $content->field;

                    // initialize the array of userids
                    // for this submission recordid
                    if (empty($info->userids[$rid])) {
                        $info->userids[$rid] = array();
                    }

                    // add the userid of the main presenter
                    $info->userids[$rid][$content->userid] = 1;

                    if (substr($field, 0, 5)=='name_') {

                        // process name field
                        $i = 0;
                        $name = '';
                        $type = '';
                        $lang = 'xx';

                        $parts = explode('_', $field);
                        switch (count($parts)) {
                            case 2:
                                list($name, $type) = $parts;
                                break;
                            case 3:
                                if (is_numeric($parts[2])) {
                                    list($name, $type, $i) = $parts;
                                } else {
                                    list($name, $type, $lang) = $parts;
                                }
                                break;
                            case 4:
                                if (is_numeric($parts[2])) {
                                    list($name, $type, $i, $lang) = $parts;
                                } else {
                                    list($name, $type, $lang, $i) = $parts;
                                }
                                break;
                        }
                        if ($i) {
                            if (empty($names[$rid])) {
                                $names[$rid] = array();
                            }
                            if (empty($names[$rid][$i])) {
                                $names[$rid][$i] = array();
                            }
                            if (empty($names[$rid][$i][$lang])) {
                                $names[$rid][$i][$lang] = array();
                            }
                            switch ($type) {
                                case 'given': $type = 'firstname'; break;
                                case 'surname': $type = 'lastname'; break;
                            }
                            $value = block_maj_submissions::textlib('strtoupper', $value);
                            $names[$rid][$i][$lang][$type] = $value;
                        }

                    } else {

                        // process "presentation_" fields
                        $field = substr($field, 13);
                        switch ($field) {
                            case 'language': $delimiter = '';   break;
                            case 'times':    $delimiter = '##'; break;
                            case 'topics':   $delimiter = '##'; break;
                            case 'keywords': $delimiter = '/[,\x{3000}\x{3001}\x{FF0C}]/u';  break;
                            default:         $delimiter = null;
                        }
                        if (empty($info->$field)) {
                            $info->$field = array();
                        }
                        if ($delimiter) {
                            if ($delimiter=='##') {
                                $values = explode($delimiter, $value);
                            } else {
                                $values = preg_split($delimiter, $value);
                            }
                            $values = array_map('trim', $values);
                            $values = array_filter($values);
                            foreach ($values as $value) {
                                if (array_key_exists($value, $info->$field)) {
                                    array_push($info->$field[$value], $rid);
                                } else {
                                    $info->$field[$value] = array($rid);
                                }
                            }
                        } else if ($delimiter==='') {
                            if (array_key_exists($value, $info->$field)) {
                                array_push($info->$field[$value], $rid);
                            } else {
                                $info->$field[$value] = array($rid);
                            }
                        }
                        if ($delimiter || $delimiter==='') {
                            $fields[$field] = 1;
                        }
                    }
                }
            }

            foreach ($names as $rid => $parts) {
                foreach ($parts as $i => $langs) {
                    foreach ($langs as $lang => $types) {
                        if (array_key_exists('firstname', $types) && array_key_exists('lastname', $types)) {
                            $select = '(firstname = ? AND lastname = ?) OR (firstnamephonetic = ? AND lastnamephonetic = ?)';
                            $params = array($types['firstname'],
                                            $types['lastname'],
                                            $types['firstname'],
                                            $types['lastname']);
                            if ($users = $DB->get_records_select('user', $select, $params)) {
                                foreach ($users as $userid => $user) {
                                    $info->userids[$rid][$userid] = 1;
                                }
                            }
                        }
                    }
                }
            }

            // declare annonymous function for sorting by number of $rids
            // (more popular items will appear first)
            $uasort = function ($a, $b) {
                $acount = (empty($a) ? 0 : count($a));
                $bcount = (empty($b) ? 0 : count($b));
                return ($acount < $bcount ? 1 : ($acount > $bcount ? -1 : 0));
            };

            $fields = array_keys($fields);
            foreach ($fields as $field) {
                uasort($info->$field, $uasort);
                $info->icons[$field] = array();
                switch ($field) {
                    case 'language':
                        // 6 items (musical symbols)
                        array_push($info->icons[$field],
                            "\u{266A}", "\u{266B}", "\u{266C}", // quaver, quavers, semi-quavers
                            "\u{266D}", "\u{266E}", "\u{266f}"); // flat, natural, sharp
                        break;
                    case 'times':
                        // 8 items (card suits) diamond, club, spade, heart
                        array_push($info->icons[$field],
                            "\u{2662}", "\u{2667}", "\u{2664}", "\u{2661}", // empty
                            "\u{2666}", "\u{2663}", "\u{2660}", "\u{2665}"); // filled
                        break;
                    case 'topics':
                        // 12 items (chess pieces) King, Queen, Rook, Bishop, Knight, Pawn
                        array_push($info->icons[$field],
                            "\u{265A}", "\u{265B}", "\u{265C}", "\u{265D}", "\u{265E}", "\u{265F}", // filled
                            "\u{2654}", "\u{2655}", "\u{2656}", "\u{2657}", "\u{2658}", "\u{2659}"); // empty
                        break;
                    case 'keywords':
                        // 160 items (circled and bracketed numbers)
                        $i_min = 0x2460; $i_max = 0x24EF;
                        for ($i=$i_min; $i<=$i_max; $i++) {
                            $info->icons[$field][] = block_maj_submissions::textlib('code2utf8', $i);
                        }
                        break; // use first letter of keyword
                }
            }

            // set all presenters as "presenting" at their own presentation
            foreach ($info->userids as $rid => $userids) {
                foreach ($userids as $userid => $attend) {
                    $params = array('instanceid' => $id,
                                    'recordid'   => $rid,
                                    'userid'     => $userid);
                    if (! $DB->record_exists($plugin, $params)) {
                        $DB->insert_record($plugin, $params);
                    }
                    $DB->set_field($plugin, 'attend', $attend, $params);
                }
            }
            $html = json_encode($info);
        }
        break;

    default:
        $html = $SCRIPT.'<br />'.$action.': '.get_string('unknowaction', 'error');
        $html = $OUTPUT->notification($html, 'warning');
}
echo $html;