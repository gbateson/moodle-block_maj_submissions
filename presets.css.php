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
 * block/maj_submissions/presets.js.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

require_once('../../config.php');

require_once($CFG->dirroot.'/blocks/moodleblock.class.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/block_maj_submissions.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/lib.php');

$d = optional_param('d', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$preset = optional_param('preset', '', PARAM_ALPHANUM);

if (($d || $id) && $preset) {
    if ($d) {
        $data   = $DB->get_record('data', array('id' => $d), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $data->course), '*', MUST_EXIST);
        $cm     = get_coursemodule_from_instance('data', $data->id, $course->id, false, MUST_EXIST);
    } else {
        $cm     = get_coursemodule_from_id('data', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $data   = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
    }

    require_login($course, true, $cm);
    require_capability('mod/data:viewentry', $PAGE->context);

    $params = array('d' => $data->id, 'preset' => $preset);
    $PAGE->set_url('/blocks/maj_submissions/presets.css.php', $params);

    $time = time();
    $lifetime = 600; // Seconds to cache this content
    $fmt = 'D, d M Y H:i:s'; // GMT date format string

    header('Last-Modified: '.gmdate($fmt, $time).' GMT');
    header('Expires: '.gmdate($fmt, $time + $lifetime).' GMT');
    header('Cache-control: max_age = '.$lifetime);
    header('Pragma: ');
    header('Content-type: text/css; charset=utf-8');

    $css = '';

    // hide all multilang spans that are not for the current language
    $langs = block_maj_submissions::get_languages();
    foreach ($langs as $lang) {
        $css .= '.lang-'.$lang.' span.multilang:not([lang="'.$lang.'"]) { display: none; }'."\n";
    }

    $file = $CFG->dirroot.'/blocks/maj_submissions/presets.css';
    if (file_exists($file)) {
        $css .= trim(file_get_contents($file))."\n";
    }

    $file = $CFG->dirroot."/blocks/maj_submissions/presets/$preset.css";
    if (file_exists($file)) {
        $css .= trim(file_get_contents($file))."\n";
    }

    if ($pluginfiles = block_maj_submissions_pluginfile_baseurl($course)) {
        $css = str_replace('[[pluginfiles]]', $pluginfiles, $css);
    }

    echo $css;
}
