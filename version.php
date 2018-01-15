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
 * block/maj_submissions/version.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

$plugin->component = 'block_maj_submissions';
$plugin->dependencies = array(
    'datafield_action'   => ANY_VERSION,
    'datafield_admin'    => ANY_VERSION,
    'datafield_constant' => ANY_VERSION,
    'datafield_report'   => ANY_VERSION,
    'datafield_template' => ANY_VERSION,
    'tool_createusers'   => ANY_VERSION
);
$plugin->maturity  = MATURITY_STABLE;
$plugin->requires  = 2012062500; // Moodle 2.3
$plugin->version   = 2018011565;
$plugin->release   = '2018-01-15 (65)';
