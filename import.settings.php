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
 * blocks/maj_submissions/import.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson <gordon.bateson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/lib/xmlize.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/import_form.php');

// cache the plugin name  - because it is quite long ;-)
$plugin = 'block_maj_submissions';

// get the incoming block_instance id
$id = required_param('id', PARAM_INT);

if (! $block_instance = $DB->get_record('block_instances', array('id' => $id))) {
    print_error('invalidinstanceid', $plugin, '', $id);
}
if (! $block = $DB->get_record('block', array('name' => $block_instance->blockname))) {
    print_error('invalidblockname', $plugin, '', $block_instance);
}
if (! $context = $DB->get_record('context', array('id' => $block_instance->parentcontextid))) {
    print_error('invalidcontextid', $plugin, '', $block_instance);
}
if (! $course = $DB->get_record('course', array('id' => $context->instanceid))) {
    print_error('invalidcourseid', $plugin, '', $context);
}

require_login($course->id);

if (class_exists('context')) {
    $context = context::instance_by_id($context->id);
} else {
    $context = get_context_instance_by_id($context->id);
}
require_capability('moodle/site:manageblocks', $context);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// It is the path below $CFG->wwwroot of this script
$url = new moodle_url($SCRIPT, array('id' => $id));

// initialize form
$mform = new block_maj_submissions_import_form($url);

if ($mform->is_cancelled()) {
    $params = array('id' => $course->id,
                    'sesskey' => sesskey(),
                    'bui_editid' => $block_instance->id);
    redirect(new moodle_url('/course/view.php', $params));
}

$blockname = get_string('blockname', $plugin);
$pagetitle = get_string('importsettings', $plugin);

$PAGE->set_url($url);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($pagetitle, $url);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
echo $OUTPUT->box_start('generalbox');

if ($xml = $mform->get_file_content('importfile')) {
    if ($mform->import($xml, $block_instance, $course)) {
        // successful import
        $msg   = get_string('validimportfile', $plugin);
        $style = 'notifysuccess';
    } else {
        // import didn't work - shouldn't happen !!
        $msg   = get_string('invalidimportfile', $plugin);
        $style = 'notifyproblem';
    }
    echo $OUTPUT->notification($msg, $style);

    $params = array('id' => $course->id,
                    'sesskey' => sesskey(),
                    'bui_editid' => $block_instance->id);
    $url   = new moodle_url('/course/view.php', $params);

    echo $OUTPUT->continue_button($url);

} else {
    // show the import form
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
