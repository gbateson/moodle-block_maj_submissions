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
            'resetschedule'      => array(),
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
            $select = 'dc.id, dc.fieldid, dc.recordid, df.name AS fieldname, dc.content';
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

        // add a "session" for each $item
        foreach ($items as $recordid => $item) {

            // start session DIV
            $html .= html_writer::start_tag('div', array('class' => 'session',
                                                         'id' => 'rid'.$recordid,
                                                         'style' => 'display: inline-block;'));

            // time and duration
            $html .= html_writer::start_tag('div', array('class' => 'time'));
            $html .= $item['schedule_time'];
            $html .= html_writer::tag('span', $item['schedule_duration'], array('class' => 'duration'));
            $html .= html_writer::end_tag('div');

            // get room info
            $room = (object)array(
                'roomname'   => 'Room 123',
                'totalseats' => '100 seats',
                'roomtopic'  => 'Topic XYZ',
                'emptyseats' => '40 seats left'
            );

            // room
            $html .= html_writer::start_tag('div', array('class' => 'room'));
            $html .= html_writer::tag('span', $room->roomname, array('class' => 'roomname'));
            $html .= html_writer::tag('span', $room->totalseats, array('class' => 'totalseats'));
            $html .= html_writer::tag('span', $room->roomtopic, array('class' => 'roomtopic'));
            $html .= html_writer::end_tag('div');

            // title
            $html .= html_writer::tag('div', $item['presentation_title'], array('class' => 'title'));

            // format authors
            $authors = 'Tom, Dick, Harry';

            // schedule number and authors
            $html .= html_writer::start_tag('div', array('class' => 'authors'));
            $html .= html_writer::tag('span', $item['schedule_number'], array('class' => 'schedulenumber'));
            $html .= $authors;
            $html .= html_writer::end_tag('div');

            // summary
            $html .= html_writer::tag('div', $item['presentation_abstract'], array('class' => 'summary'));

            // capacity
            $html .= html_writer::start_tag('div', array('class' => 'capacity'));
            $html .= html_writer::tag('div', $room->emptyseats, array('class' => 'emptyseats'));
            $html .= html_writer::start_tag('div', array('class' => 'attendance'));
            $html .= html_writer::empty_tag('input', array('type' => 'checkbox', 'id' => 'attend'.$recordid, 'value' => '1'));
            $html .= html_writer::tag('span', $strnotattending, array('class' => 'text'));
            $html .= html_writer::end_tag('div');
            $html .= html_writer::end_tag('div');

            // finish session DIV
            $html .= html_writer::end_tag('div');
        }
        break;

    default:
        $html = $SCRIPT.'<br />'.$action.': '.get_string('unknowaction', 'error');
        $html = $OUTPUT->notification($html, 'warning');
}
echo $html;