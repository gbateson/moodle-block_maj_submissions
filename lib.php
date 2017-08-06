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
 * Form for editing HTML block instances.
 *
 * @copyright 2010 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   block_maj_submissions
 * @category  files
 * @param stdClass $course course object
 * @param stdClass $blockinstance block instance record
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 * @todo MDL-36050 improve capability check on stick blocks, so we can check user capability before sending images.
 */
function block_maj_submissions_pluginfile($course, $blockinstance, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    // sanity checks on the incoming parameters
    if (empty($context) || empty($blockinstance)) {
        send_file_not_found();
    }
    if ($blockinstance->blockname != 'maj_submissions') {
        send_file_not_found();
    }
    if ($context->contextlevel != CONTEXT_BLOCK) {
        send_file_not_found();
    }
    if ($filearea != 'files') {
        send_file_not_found();
    }

    // If block is in course context, check user can access course.
    $sendfile = false;
    if ($context->get_course_context(false)) {
        require_course_login($course);
        $sendfile = true;
    } else if ($CFG->forcelogin) {
        require_login();
        $sendfile = true;
    } else if ($parentcontext = $context->get_parent_context()) {
        // Check if user has proper permission in parent context.
        if ($parentcontext->contextlevel === CONTEXT_COURSECAT) {
            // Check if user can view this category.
            if ($category = $DB->get_record('course_categories', array('id' => $parentcontext->instanceid))) {
                if ($category->visible) {
                    $sendfile = true;
                } else {
                    // block is in a hidden category - unusual !!
                    $capability = 'moodle/category:viewhiddencategories';
                    $sendfile = has_capability($capability, $parentcontext);
                }
            }
        } else if ($parentcontext->contextlevel === CONTEXT_USER) {
            // The block is in the context of a user,
            // it is only visible to the user who it belongs to.
            $sendfile = ($parentcontext->instanceid==$USER->id);
        }
    }

    if ($sendfile==false) {
        send_file_not_found();
    }

    // user is allowed to access this context

    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';
    $file = $fs->get_file($context->id, 'block_maj_submissions', $filearea, 0, $filepath, $filename);

    // check that the file exists
    if (empty($file) || $file->is_directory()) {
        send_file_not_found();
    }

    if ($parentcontext = context::instance_by_id($blockinstance->parentcontextid, IGNORE_MISSING)) {
        if ($parentcontext->contextlevel == CONTEXT_USER) {
            // force download on all personal pages including /my/
            //because we do not have reliable way to find out from where this is used
            $forcedownload = true;
        }
    } else {
        // weird, there should be parent context, better force dowload then
        $forcedownload = true;
    }

    // NOTE: it would be nice to have file revisions here, for now rely on standard file lifetime,
    //       do not lower it because the files are dispalyed very often.
    \core\session\manager::write_close();
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Perform global search replace such as when migrating site to new URL.
 * @param  $search
 * @param  $replace
 * @return void
 */
function block_maj_submissions_global_db_replace($search, $replace) {
    global $DB;

    $instances = $DB->get_recordset('block_instances', array('blockname' => 'maj_submissions'));
    foreach ($instances as $instance) {
        // TODO: intentionally hardcoded until MDL-26800 is fixed
        $config = unserialize(base64_decode($instance->configdata));
        if (isset($config->text) and is_string($config->text)) {
            $config->text = str_replace($search, $replace, $config->text);
            $DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), array('id' => $instance->id));
        }
    }
    $instances->close();
}

/**
 * Get the pluginfile URL for the maj_submissions_block in the given $course.
 * @param  $course
 * @return void
 */
function block_maj_submissions_pluginfile_baseurl($course, $blockcontext=null) {
    global $CFG, $DB;

    $blockname = 'maj_submissions';
    $pluginname = "block_$blockname";

    if ($blockcontext===null) {
        if ($coursecontext = block_maj_submissions::context(CONTEXT_COURSE, $course->id)) {
            $params = array('blockname' => $blockname,
                            'parentcontextid' => $coursecontext->id);
            if ($block = $DB->get_records('block_instances', $params)) {
                $block = reset($block);
                $blockcontext = block_maj_submissions::context(CONTEXT_BLOCK, $block->id);
            }
        }
    }

    $url = new moodle_url('/pluginfile.php');
    if (empty($CFG->slasharguments)) {
        $url .= '?file=';
    }
    return $url."/$blockcontext->id/$pluginname/files/";
}