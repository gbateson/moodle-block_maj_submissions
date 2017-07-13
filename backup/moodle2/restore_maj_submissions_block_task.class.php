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
 * @package    block_maj_submissions
 * @copyright  2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Specialised restore task for the maj_submissions block
 * (using execute_after_tasks for recoding of target quiz)
 *
 * TODO: Finish phpdocs
 *
 * @copyright  2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_maj_submissions_block_task extends restore_block_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
    }

    public function get_fileareas() {
        return array(); // No associated fileareas
    }

    public function get_configdata_encoded_attributes() {
        return array(); // No special handling of configdata
    }

    /**
     * This function, executed after all the tasks in the plan
     * have been executed, will perform the recode of the
     * target activity ids for this block.
     * This must be done here and not in normal execution steps
     * because the activities can be restored after the block.
     */
    public function after_restore() {
        global $DB;

        // Get the blockid.
        $blockid = $this->get_blockid();

        // Extract block configdata and update it to point to the new activities
        if ($configdata = $DB->get_field('block_instances', 'configdata', array('id' => $blockid))) {

            $config = unserialize(base64_decode($configdata));
            $update = false;
            $types = array('collectpresentations', 'collectworkshops', 'collectsponsoreds',
                           'conference', 'workshops', 'reception', 'publish',
                           'registerdelegates', 'registerpresenters');
            foreach ($types as $type) {
                if ($this->after_restore_fix_cmid($config, $type)) {
                    $update = true;
                }
            }

            // cache number of sections in this course
            $numsections = self::get_numesctions($this->get_courseid());

            $types = array('review', 'revise');
            foreach ($types as $type) {
                if ($this->after_restore_fix_sectionnum($config, $type, $numsections)) {
                    $update = true;
                }
            }

            if ($update) {
                $configdata = base64_encode(serialize($config));
                $DB->set_field('block_instances', 'configdata', $configdata, array('id' => $blockid));
            }
        }
    }

    protected function after_restore_fix_cmid($config, $type) {
        $cmid = $type.'cmid';
        if (empty($config->$cmid)) {
            return false;
        }
        $map = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $config->$cmid);
        if ($config->$cmid==$map->newitemid) {
            return false;
        }
        $config->$cmid = $map->newitemid;
        return true;
    }

    protected function after_restore_fix_sectionnum($config, $type, $numsections) {
        $sectionnum = $type.'sectionnum';
        if (empty($config->$sectionnum)) {
            return false;
        }
        if ($config->$sectionnum <= $numsections) {
            return false;
        }
        $config->$sectionnum = 0;
        return true;
    }

    /**
     * get_numsections
     *
     * a wrapper method to offer consistent API for $course->numsections
     * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
     * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
     *
     * @uses   $DB
     * @param  mixed   $course, either object (DB record) or integer (id)
     * @return integer $numsections
     */
    static public function get_numesctions($courseid) {
        global $DB;
        $course = $DB->get_record('course', array('id' => $courseid));
        if (isset($course->numsections)) {
            // Moodle <= 2.3
            return $course->numsections;
        }
        if (isset($course->format)) {
            // Moodle >= 2.4
            $params = array('courseid' => $course->id,
                            'format'   => $course->format,
                            'name'     => 'numsections');
            return $DB->get_field('course_format_options', 'value', $params);
        }
        return 0; // shouldn't happen !!
    }

    static public function define_decode_contents() {
        return array();
    }

    static public function define_decode_rules() {
        return array();
    }
}
