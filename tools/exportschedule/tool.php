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
 * blocks/maj_submissions/export.schedule.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson <gordon.bateson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/blocks/moodleblock.class.php'); // class block_base
require_once($CFG->dirroot.'/blocks/maj_submissions/block_maj_submissions.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');
require_once($CFG->dirroot.'/lib/filelib.php'); // function send_file()

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

if (! isset($block->version)) {
    $params = array('plugin' => 'block_maj_submissions', 'name' => 'version');
    $block->version = $DB->get_field('config_plugins', 'value', $params);
}

$lines = '';
if ($instance = block_instance('maj_submissions', $block_instance, $PAGE)) {

    $fields = array(
        'name'       => 'presentation_title',
        'start_time' => 'schedule_time',
        'start_date' => 'schedule_day',
        'end_time'   => 'schedule_time',
        'end_date'   => 'schedule_day',
        'reference'  => 'schedule_number',
        'text'       => 'presentation_abstract',
        'location'   => 'schedule_roomname',
        'track'      => 'schedule_roomtopic',
        'speaker_name'  => 'name_surname_en',
        //'speaker_email' => 'email',
        //'speaker_bio'   => 'bio',
        //'speaker_title' => 'name_title',
        'speaker_organisation' => 'affiliation_en'
    );

	$records = array();
	$fieldnames = array();
	list($records, $fieldnames) = $instance->get_submission_records($fields);

    // cache some useful regular expressions
    $timesearch = '/^ *(\d+) *: *(\d+) *- *(\d+) *: *(\d+) *$/';
    $mainlangsearch = '/<span[^>]*lang="en"[^>]*>(.*?)<\/span>/';
    $multilangsearch = '/<span[^>]*lang="[^""]*"[^>]*>.*?<\/span>/';

    $th = array('st ' => ' ',
    			'nd ' => ' ',
    			'rd ' => ' ',
    			'th ' => ' ',
    			' '   => '-');
    $dates = array();

    // add fields from each record
    $lines = array();
    foreach ($records as $record) {
        $line = array();

		// count the number of required fields present in this record
        $required = 0;

        foreach ($fields as $name => $field) {
            if (! array_key_exists($field, $fieldnames)) {
                continue;
            }
            if (empty($record->$field)) {
                $line[] = '';
                continue;
            }
            if ($name=='name' || $name=='start_time' || $name=='start_date' || $name=='end_time' || $name=='end_date') {
                $required++;
            }
			$value = $record->$field;
            switch ($name) {
                case 'speaker_name':
                    $value = block_maj_submissions::textlib('strtotitle', $value);
                    break;
                case 'text':
                    $value = block_maj_submissions_tool_form::plain_text($value);
                    $value = block_maj_submissions::trim_text($value, 100, 100, 0);
                    break;
                case 'start_date':
                case 'end_date':
                    if (isset($dates[$value])) {
						$value = $dates[$value];
                    } else {
						$value = preg_replace($mainlangsearch, '$1', $value);
						$value = preg_replace($multilangsearch, '', $value);
						// "Feb 23rd (Fri)"
						$value = strtr($value, $th);
						if ($pos = strpos($value, ' (')) {
							$value = substr($value, 0, $pos);
						}
						// Start/End date - must be DD-MM-YY, e.g. 15-10-15
						$value = date('d-m-y', strtotime(date('Y').'-'.$value));
						$dates[$record->$field] = $value;
                    }
                    break;
                case 'start_time':
                    // Start time - must be HH:MM
                    $value = preg_replace($timesearch, '$1:$2', $value);
                    break;
                case 'end_time':
                    // End time - must be HH:MM
                    $value = preg_replace($timesearch, '$3:$4', $value);
                    break;
                default:
                    if (strpos($value, 'multilang')) {
                        $value = preg_replace($mainlangsearch, '$1', $value);
                        $value = preg_replace($multilangsearch, '', $value);
                    }
                    $value = block_maj_submissions_tool_form::plain_text($value);
            }
			$line[] = '"'.str_replace('"', '""', $value).'"';
        }
        if ($required >= 5 && count($line)) {
            $lines[] = implode(',', $line);
        }
    }

    // create data lines
    if (empty($lines)) {
        $lines = '';
    } else {
        $lines = implode("\n", $lines);
    }

    // create heading line
    $line = array();
    foreach ($fields as $name => $field) {
        if (array_key_exists($field, $fieldnames)) {
            $line[] = $name;
        }
    }
    if ($line = implode(',', $line)) {
        $line .= "\n";
    }

    $lines = $line.$lines;
}

if (empty($instance->config->title)) {
    $filename = $block->name.'.csv';
} else {
    $filename = format_string($instance->config->title, true);
    $filename = clean_filename(strip_tags($filename).'.csv');
}
$filename = preg_replace('/[ \.]/', '.', $filename);
send_file($lines, $filename, 0, 0, true, true);
