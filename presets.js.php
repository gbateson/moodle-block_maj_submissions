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

// session not used here
//define('NO_MOODLE_COOKIES', true);

require_once('../../config.php');

$d = optional_param('d', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$preset = optional_param('preset', '', PARAM_ALPHANUM);
$runonload = optional_param('runonload', false, PARAM_BOOL);

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

    // set $PAGE url
    $params = array('d' => $data->id, 'preset' => $preset);
    $PAGE->set_url('/blocks/maj_submissions/presets.js.php', $params);

    // MAJ.jquery_version = "1.11.1"; // Moodle 2.8
    // MAJ.jquery_version = "1.11.2"; // Moodle 2.9
    // MAJ.jquery_version = "1.11.3"; // Moodle 3.0
    // MAJ.jquery_version = "1.12.1"; // Moodle 3.1

    // detect jquery version on the Moodle site
    $jquery_version = '';
    $jquery_search = '/jquery-([0-9.]+)(\.min)?\.js$/';
    if (method_exists($PAGE->requires, 'jquery')) {
        // Moodle >= 2.5.
        if ($jquery_version == '') {
            include($CFG->dirroot.'/lib/jquery/plugins.php');
            if (isset($plugins['jquery']['files'][0])) {
                if (preg_match($jquery_search, $plugins['jquery']['files'][0], $matches)) {
                    $jquery_version = $matches[1];
                }
            }
        }
        if ($jquery_version == '') {
            $filename = $CFG->dirroot.'/lib/jquery/jquery*.js';
            foreach (glob($filename) as $filename) {
                if (preg_match($jquery_search, $filename, $matches)) {
                    $jquery_version = $matches[1];
                    break;
                }
            }
        }
    }

    // can we somehow find the "payment" and "membership" pages?
    // perhaps from a maj_submissions setting?
    // or from $course->modinfo?
    $payment_cm = '/mod/page/view.php?id=0';
    $membership_cm = '/mod/page/view.php?id=0';

    $time = time();
    $lifetime = 600; // Seconds to cache this content
    $fmt = 'D, d M Y H:i:s'; // GMT date format string

    header('Last-Modified: '.gmdate($fmt, $time).' GMT');
    header('Expires: '.gmdate($fmt, $time + $lifetime).' GMT');
    header('Cache-control: max_age = '.$lifetime);
    header('Pragma: ');
    header('Content-type: application/javascript; charset=utf-8');

    echo 'if (typeof(window.MAJ)=="undefined") {'."\n";
    echo '    window.MAJ = {};'."\n";
    echo '}'."\n";

    // maximum number of times to try and load JQuery
    echo 'MAJ.onload_max_count = 100;'."\n";

    // extract wwwroot for this Moodle site
    echo 'MAJ.wwwroot = "'.$CFG->wwwroot.'";'."\n";
    echo 'MAJ.presets_js = "/block/maj_submissions/presets.js.php";'."\n";
    echo 'MAJ.jquery_js = "/lib/jquery/jquery-'.$jquery_version.'.min.js";'."\n";

    $sortable = array();
    if ($fields = $DB->get_records('data_fields', array('dataid' => $data->id), 'name')) {
        foreach ($fields as $id => $field) {
            $name = $field->name;
            $type = $field->type;
            if ($type=='admin') {
                $type = $field->param10;
            }
            if ($type=='action' || $type=='constant' || $type=='file' || $type=='multimenu' || $type=='picture' || $type=='template' || $type=='textarea' || $type=='url') {
                // unsortable field types
            } else if ($name=='setdefaultvalues' || $name=='fixdisabledfields' || $name=='unapprove') {
                // special admin fields
            } else if (preg_match('/^print_/', $name) || preg_match('/^name_?title(.*)$/', $name) || preg_match('/^(affiliation|dinner|name)(.*)_\d+(_[a-z]{2})?$/', $name)) {
                // print fields, nametitle fields, optional fields
            } else {
                $sortable[] = $name;
            }
        }
    }

    if ($sortable = implode('", "', $sortable)) {
        $sortable = '"'.$sortable.'"';
    }
    echo 'MAJ.sortablefields = ['.$sortable.'];'."\n";

    // maybe we should get these URLs from MAJ block
    echo 'MAJ.payment_link_cm = "'.$payment_cm.'";'."\n";
    echo 'MAJ.membership_link_cm = "'.$membership_cm.'";'."\n";

    $file = $CFG->dirroot.'/blocks/maj_submissions/presets.js';
    if (file_exists($file)) {
        readfile($file);
    }

    $file = $CFG->dirroot."/blocks/maj_submissions/presets/$preset.js";
    if (file_exists($file)) {
        readfile($file);
    }

    echo "\n";
    if ($runonload) {
        echo 'if (window.addEventListener) {'."\n";
        echo '    window.addEventListener("load", MAJ.onload, false);'."\n";
        echo '} else if (window.attachEvent) {'."\n";
        echo '    window.attachEvent("onload", MAJ.onload);'."\n";
        echo '}'."\n";
    } else {
        echo 'MAJ.onload();'."\n";
    }
}
