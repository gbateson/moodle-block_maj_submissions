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

$html = '';
switch ($action) {

    case 'loadtools':

        $commands = array(
            'initializeschedule' => array(),
            'emptyschedule'      => array(),
            'populateschedule'   => array(),
            'renumberschedule'   => array(),
            'addday'  => array('above', 'below', 'start', 'end'),
            'addslot' => array('above', 'below', 'start', 'end'),
            'addroom' => array('left',  'right', 'start', 'end'),
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

                foreach ($subcommands as $subcommand) {
                    $params = array('id' => "$command-$subcommand",
                                    'class' => 'subcommand');
                    $text = get_string($subcommand, $plugin);
                    $html .= html_writer::tag('div', $text, $params);
                }
                $html .= html_writer::end_tag('div');
            }
            $html .= html_writer::end_tag('div');
        }
        $html .= html_writer::end_tag('div');
        break;

    case 'loadschedule':

        $instance = block_instance($blockname, $block_instance);
        if ($cmid = $instance->config->publishcmid) {
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

        $instance = block_instance($blockname, $block_instance);
        $config = $instance->config;
        $modinfo = get_fast_modinfo($course);

        // the database types
        $types = array('presentation',
                       'workshop',
                       'sponsored',
                       'event');

        // ignore these fieldtypes
        $fieldtypes = array('action', 'constant', 'file', 'picture', 'template', 'url');
        list($fieldwhere, $fieldparams) = $DB->get_in_or_equal($fieldtypes, SQL_PARAMS_QM, '', false);

        // cache certain strings
        $strnotattending = get_string('notattending', $plugin);

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
            $where  = 'df.dataid = ? AND df.type '.$fieldwhere;
            $order  = 'dc.recordid';
            $params = array_merge(array($cm->instance), $fieldparams);

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

        // search and replace strings for HTML tags with attributes
        $tagsearch = '/<(\/?\w+)\b[^>]+>/u';
        $tagreplace = '<$1>';

        // regex to detect tags and non-breaking spaces in summary
        $tagsearch = array('/<[^>]*>/u', '/(?:(?:&nbsp;)| )+/');
        $tagreplace = ' ';

        // add a "session" for each $item
        foreach ($items as $recordid => $item) {

            // start session DIV
            $html .= html_writer::start_tag('div', array('id' => 'id_recordid_'.$recordid,
                                                         'class' => 'session',
                                                         'style' => 'display: inline-block;'));

            // time and duration
            $html .= html_writer::start_tag('div', array('class' => 'time'));
            $html .= html_writer::tag('span', $item['schedule_time'], array('class' => 'startfinish'));
            $html .= html_writer::tag('span', $item['schedule_duration'], array('class' => 'duration'));
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
            $html .= html_writer::start_tag('div', array('class' => 'typecategory'));

            $type = $item['presentation_type'];
            $type = block_maj_submissions_tool_form::convert_to_multilang($type, $config);
            $html .= html_writer::tag('span', $type, array('class' => 'type'));

            $category = $item['presentation_category'];
            $category = block_maj_submissions_tool_form::convert_to_multilang($category, $config);
            $html .= html_writer::tag('span', $category, array('class' => 'category'));

            $html .= html_writer::end_tag('div'); // end categorytype DIV

            // summary (remove all tags and nbsp)
            $text = $item['presentation_abstract'];
            $text = preg_replace($tagsearch, $tagreplace, $text);
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