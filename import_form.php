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
 * mod/reader/admin/users/import_form.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * tool_fixlinks_form
 *
 * @package    tool
 * @subpackage fixlinks
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */
class block_maj_submissions_import_form extends moodleform {

    /**
     * constructor
     */
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
        if (method_exists('moodleform', '__construct')) {
            parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
        } else {
            parent::moodleform($action, $customdata, $method, $target, $attributes, $editable);
        }
    }

    /**
     * definition
     */
    public function definition() {
        $this->_form->addElement('filepicker', 'importfile', get_string('file'));
        $this->add_action_buttons(true, get_string('import'));
    }

    /**
     * import
     *
     * @param string $xml
     * @param object $block_instance
     * @param object $course
     * @return boolean true if import was successful, false otherwise
     */
    static public function import($xml, $block_instance, $course) {
        global $DB;

        if (! $xml = xmlize($xml, 0)) {
            return false;
        }

        if (! isset($xml['MAJSUBMISSIONSBLOCK']['#']['CONFIGFIELDS'][0]['#']['CONFIGFIELD'])) {
            return false;
        }
        $configfield = &$xml['MAJSUBMISSIONSBLOCK']['#']['CONFIGFIELDS'][0]['#']['CONFIGFIELD'];

        // $modinfo will be fetched later if needed
        $modinfo = null;

        // array to map old cmid onto new cmid
        $coursemodules = array();

        if (isset($xml['MAJSUBMISSIONSBLOCK']['#']['COURSEMODULES'][0]['#']['COURSEMODULE'])) {
            $coursemodule = &$xml['MAJSUBMISSIONSBLOCK']['#']['COURSEMODULES'][0]['#']['COURSEMODULE'];

            $i = 0;
            while (isset($coursemodule[$i]['#'])) {
                $cmid = $coursemodule[$i]['#']['CMID'][0]['#'];
                $name = $coursemodule[$i]['#']['NAME'][0]['#'];
                $modname = $coursemodule[$i]['#']['MODNAME'][0]['#'];
                $sectionnum = $coursemodule[$i]['#']['SECTIONNUM'][0]['#'];
                $newcmid = 0;
                if ($modinfo===null) {
                    $modinfo = get_fast_modinfo($course);
                }
                foreach ($modinfo->cms as $cm) {
                    if ($cm->sectionnum==$sectionnum && $cm->modname==$modname && $cm->name==$name) {
                        // same course section number, activity type and activity name
                        $newcmid = $cm->id;
                        break;
                    }
                }
                $coursemodules[$cmid] = $newcmid;
                $i++;
            }
        }

        $config = unserialize(base64_decode($block_instance->configdata));

        if (empty($config)) {
            $config = new stdClass();
        }

        $i = 0;
        while (isset($configfield[$i]['#'])) {
            $name = $configfield[$i]['#']['NAME'][0]['#'];
            $value = $configfield[$i]['#']['VALUE'][0]['#'];
            if (substr($name, -4)=='cmid') {
                if (empty($coursemodules[$value])) {
                    $value = 0;
                } else {
                    $value = $coursemodules[$value];
                }
            }
            $config->$name = $value;
            $i++;
        }

        if ($i==0) {
            return false;
        }

        $block_instance->configdata = base64_encode(serialize($config));
        $DB->set_field('block_instances', 'configdata', $block_instance->configdata, array('id' => $block_instance->id));
        return true;
    }
}
