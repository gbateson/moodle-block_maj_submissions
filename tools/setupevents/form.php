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
 * blocks/maj_submissions/block_maj_submissions.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// disable direct access to this script
defined('MOODLE_INTERNAL') || die();

// get required files
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.setupdatabase.php');

class block_maj_submissions_tool_setupevents extends block_maj_submissions_tool_setupdatabase {
    protected $type = 'registerevents';
    protected $defaultpreset = 'events';
    protected $defaultname = 'conferenceevents';
    protected $permissions = array();

    /**
     * This is a readonly database, to which teacher/admin adds content.
     */
    protected function is_readonly() {
        return true;
    }

    /**
     * get_defaultintro
     *
     * @return string
     */
    protected function get_defaultintro() {
        $howto = 'setupevents';
        if ($intro = $this->instance->get_string("howto$howto", $this->plugin)) {
            $intro = html_writer::tag('p', $intro);
            $intro = html_writer::tag('div', $intro, array('class' => "howto $howto"));
        }
        return $intro;
    }
}
