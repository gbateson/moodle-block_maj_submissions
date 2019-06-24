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
 * blocks/maj_submissions/tools/authorsforum/tool.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/authorsforum/form.php');

$blockname = 'maj_submissions';
$plugin = "block_$blockname";
$tool = 'toolauthorsforum';

$id = required_param('id', PARAM_INT); // block_instance id

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

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// It is the path below $CFG->wwwroot of this script
$url = new moodle_url($SCRIPT, array('id' => $id));

$strblockname = get_string('blockname', $plugin);
$strpagetitle = get_string($tool, $plugin);

$PAGE->set_url($url);
$PAGE->set_title($strpagetitle);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($strblockname);
$PAGE->navbar->add($strpagetitle, $url);

// initialize the form
$customdata = array('course'   => $course,
                    'plugin'   => $plugin,
                    'instance' => $block_instance);
$mform = 'block_maj_submissions_tool_authorsforum';
$mform = new $mform($url->out(false), $customdata);

if ($mform->is_cancelled()) {
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    redirect($url);
}

if ($mform->is_submitted()) {
    $message = $mform->form_postprocessing();
} else {
    $message = '';
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strpagetitle);

echo html_writer::tag('p', get_string($tool.'_desc', $plugin).
                           $OUTPUT->help_icon($tool, $plugin));

if ($message) {
    echo $OUTPUT->notification($message, 'notifysuccess');
}

$mform->display();

echo $OUTPUT->footer($course);
