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

        foreach ($commands as $command => $subcommands) {

            $params = array('id' => $command,
                            'class' => 'command');
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

    case 'loadsessions':
        echo 'Sessions goes here';
/*
<div class="session">
    <div class="time">
        09:00 - 09:30
        <span class="duration">30 mins</span>
    </div>
    <div class="room">
        <span class="roomname">Room 2</span>
        <span class="totalseats">Seats 40</span>
        <div class="roomtopic">Developers</div>
    </div>
    <div class="title">Presentation 1.1.2</div>
    <div class="authors">
        <span class="schedulenumber">112-P</span>
        Honda, Nguyen
    </div>
    <div class="summary">aegnp bcmtz ekmxy cjn adltuwy prz knsvy dkm bhimpsx atx adgjls acfgjl befnry etu efjkoxy rsy gimqtw dmz nox ejtw muvwx bdp biklqsv gjksw bjmq efgqw abjoy flnsux gknprvx lrsz bcdghow fhkpuy fmnqr agksuv bhwx acmrvz aegx cdiowy imr dpqw</div>
    <div class="capacity">
        <div class="emptyseats">Seats 40 left</div>
        <div class="attendance">
            <input type="checkbox" value="1" />
            <span class="text">Not attending</span>
        </div>
    </div>
</div>
*/
        break;

    default:
        $html = $SCRIPT.'<br />'.$action.': '.get_string('unknowaction', 'error');
        $html = $OUTPUT->notification($html, 'warning');
}
echo $html;