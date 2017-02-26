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
 * blocks/maj_submissions/tools/setuppresentations.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** Include required files */
require_once('../../../config.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/lib.php');

$blockname = 'maj_submissions';
$plugin = "block_$blockname";

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

switch (true) {
    case optional_param('apply',  '', PARAM_ALPHA): $action = 'apply';  break;
    case optional_param('cancel', '', PARAM_ALPHA): $action = 'cancel'; break;
    case optional_param('delete', '', PARAM_ALPHA): $action = 'delete'; break;
    default: $action = '';
}

if ($action=='cancel') {
    // return to course page
    $params = array('id' => $course->id, 'sesskey' => sesskey());
    redirect(new moodle_url('/course/view.php', $params));
}

$strblockname = get_string('blockname', $plugin);
$strpagetitle = get_string('toolsetuppresentations', $plugin);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// It is the path below $CFG->wwwroot of this script
$url = new moodle_url($SCRIPT, array('id' => $id));

$PAGE->set_url($url);
$PAGE->set_title($strpagetitle);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($strblockname);
$PAGE->navbar->add($strpagetitle, $url);

// require_head_js($plugin);

echo $OUTPUT->header();
echo $OUTPUT->heading($strpagetitle);
echo $OUTPUT->box_start('generalbox');

echo html_writer::tag('p', get_string('toolsetuppresentations_desc', $plugin));

// get incoming data, if any
if ($cancel = optional_param('cancel', '', PARAM_ALPHA)) {
    $data = null;
    $action = '';
} else {
    $data = data_submitted();
    $action = optional_param('action', '', PARAM_ALPHA);
}

// process incoming data, if required
if ($data) {
    if (function_exists('require_sesskey')) {
        require_sesskey();
    } else if (function_exists('confirm_sesskey')) {
        confirm_sesskey();
    }
    // process incoming data (before creating the form)
}

// get context for presentation database, if possible
$block_maj_submissions = block_instance($blockname, $block_instance);
if ($cmid = $block_maj_submissions->config->collectpresentationscmid) {
    $context = block_maj_submissions::context(CONTEXT_MODULE, $cmid);
}

// initialize the form
$customdata = array('type'    => 'collectpresentations',
                    'cmid'    => $cmid,
                    'context' => $context,
                    'course'  => $course,
                    'plugin'  => $plugin);
$mform = new block_maj_submissions_tool_setuppresentations($url->out(false), $customdata);

// display form
$defaults = array();
$mform->set_data($defaults);
$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
