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

/** Include required files */
require_once('../../../../config.php');

$blockname = 'maj_submissions';
$plugin = "block_$blockname";
$tool = 'toolsetupschedule';

$id = required_param('id', PARAM_INT); // block_instance id
$action = required_param('action', PARAM_ALPHA);

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

require_login($course->id);
require_capability('moodle/course:manageactivities', $context);

// get the block instance object
$instance = block_instance($blockname, $block_instance);
$config = $instance->config;

$html = '';
switch ($action) {

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

        // cache position strings
        $str = (object)array('above' => get_string('above', $plugin),
                             'below' => get_string('below', $plugin),
                             'left'  => get_string('left',  $plugin),
                             'right' => get_string('right', $plugin),
                             'start' => get_string('start', $plugin),
                             'end'   => get_string('end',   $plugin));

        $commands = array(
            'initializeschedule' => $days,
            'emptyschedule'      => $days,
            'populateschedule'   => $days,
            'renumberschedule'   => $days,
            'addday'  => array('above' => $str->above, 'below' => $str->below, 'start' => $str->start, 'end' => $str->end),
            'addslot' => array('above' => $str->above, 'below' => $str->below, 'start' => $str->start, 'end' => $str->end),
            'addroom' => array('left'  => $str->left,  'right' => $str->right, 'start' => $str->start, 'end' => $str->end),
            'editcss' => array()
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

        $durationsearch = array('/(^.*\()|(\).*$)/u', '/[^a-zA-Z0-9]/', '/^.*$/');
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
            $where  = 'df.dataid = ?'.$ridignore.$fieldignore;
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
                if (empty($classes['duration'][$scheduleduration])) {
                    $class = $scheduleduration;
                    if (strpos($class, '</span>') || strpos($class, '</lang>')) {
                        $class = preg_replace($multilangsearch, $multilangreplace, $class);
                    }
                    $class = preg_replace($durationsearch, $durationreplace, $class);
                    $classes['duration'][$scheduleduration] = strtolower(trim($class));
                }
                $sessionclass .= ' '.$classes['duration'][$scheduleduration];
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
            $html .= html_writer::tag('span', $item['schedule_room'], array('class' => 'roomname'));
            $html .= html_writer::tag('span', '', array('class' => 'roomseats'));
            $html .= html_writer::tag('span', '', array('class' => 'roomtopic'));
            $html .= html_writer::end_tag('div');

            // title
            $html .= html_writer::tag('div', $item['presentation_title'], array('class' => 'title'));

            // format authornames
            $authornames = array();
            $namefields = preg_grep('/^name_(surname)(.*)$/', array_keys($item));
            foreach ($namefields as $namefield) {
                if (empty($item[$namefield])) {
                    continue;
                }
                if (trim($item[$namefield])=='') {
                    continue;
                }
                $i = 0;
                $name = '';
                $type = '';
                $lang = 'xx';
                $parts = explode('_', $namefield);
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
                $authornames[$i][$lang][$type] = block_maj_submissions::textlib('strtotitle', $item[$namefield]);
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

    default:
        $html = $SCRIPT.'<br />'.$action.': '.get_string('unknowaction', 'error');
        $html = $OUTPUT->notification($html, 'warning');
}
echo $html;