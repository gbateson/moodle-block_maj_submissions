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

$html = '';
if ($instance = block_instance('maj_submissions', $block_instance, $PAGE)) {

    $fields = array(
        'name'       => 'presentation_title',
        'start_time' => 'schedule_time',
        'start_date' => 'schedule_day',
        'end_time'   => 'schedule_time',
        'end_date'   => 'schedule_day',
        'reference'  => 'schedule_number',
        'text'       => 'presentation_abstract',
        'type'       => 'presentation_type',
        'location'   => 'schedule_roomname',
        'track'      => 'schedule_roomtopic',
        'speaker_name' => 'name_given',
        'speaker_surname' => 'name_surname',
        'speaker_bio_1' => 'biography',
        'speaker_bio_2' => 'biography_2',
        'speaker_bio_3' => 'biography_3',
        'speaker_bio_4' => 'biography_4',
        'speaker_bio_5' => 'biography_5',
        'speaker_organisation' => 'affiliation'
    );

    $records = array();
    $fieldnames = array();
    list($records, $fieldnames) = $instance->get_submission_records($fields);

    // sort $records by ('schedule_day', 'schedule_time', 'schedule_roomname')
    uasort($records, function($a, $b) {
        $fields = array('schedule_day', 'schedule_time', 'presentation_type', 'schedule_roomname');
        foreach ($fields as $field) {
            if (isset($a->$field) && isset($b->$field)) {
                if ($a->$field > $b->$field) {
                    return 1;
                }
                if ($a->$field < $b->$field) {
                    return -1;
                }
            } else if (isset($a->$field)) {
                return -1; // $field missing from $b !!
            } else if (isset($b->$field)) {
                return 1; // $field missing from $a !!
            }
        }
        return 0; // equal values  - unexpected ?!
    });

    $day = '';
    $type = '';
    foreach ($records as $record) {
        //if (empty($record->schedule_day) || empty($record->schedule_time) || empty($record->schedule_room)) {
        //    continue;
        //}
        if (empty($record->schedule_day)) {
        	$record->schedule_day = '';
        }
        if ($day && $day==$record->schedule_day) {
            // same day - do nothing
        } else {
            if ($day) {
                // finish previous day
            }
            // start new day
            $day = $record->schedule_day;
            $html .= html_writer::tag('h2', $record->schedule_day);
            // TODO: day description (Pre-conference Workshops, Conference Day 1, etc)
        }
        if ($type && $type==$record->presentation_type) {
            // same type - do nothing
        } else {
            if ($type) {
                // finish previous type
            }
            // start new type
            $type = $record->presentation_type;
            $html .= html_writer::tag('h3', $record->presentation_type);
            // TODO: type description (Concurrent Sessions, Symposiums)
        }

		// schedule day, time and room
        $text = '';
        if (isset($record->schedule_day) && $record->schedule_day) {
			$text .= html_writer::tag('span', $record->schedule_day, array('style' => 'font-size: 1.6em; color: #999;')).' ';
        }
        if (isset($record->schedule_time) && $record->schedule_time) {
			$text .= html_writer::tag('span', $record->schedule_time, array('style' => 'font-size: 1.6em;')).' &nbsp; ';
        }
        if (isset($record->schedule_roomname) && $record->schedule_roomname) {
			$text .= html_writer::tag('span', '('.$record->schedule_roomname.')', array('style' => 'font-size: 1.2em;'));
        }
        if ($text) {
			$html .= html_writer::tag('h4', $text, array('style' => 'margin: 12px 0px;'));
        }

		// schedule number and presentation title
		$text = '';
        if (isset($record->schedule_number) && $record->schedule_number) {
            $text = html_writer::tag('span', '['.$record->schedule_number.']', array('style' => 'color: #f60; font-size: 0.8em;')).' ';
        }
        if (isset($record->presentation_title) && $record->presentation_title) {
            $text .= html_writer::tag('span', $record->presentation_title);
        }
        if ($text) {
        	$text = html_writer::tag('b', $text);
			$html .= html_writer::tag('p', $text, array('style' => 'margin: 6px 0px; font-size: 24px;'));
        }

		// abstract
		if (isset($record->presentation_abstract) && $record->presentation_abstract) {
			$html .= html_writer::tag('p', $record->presentation_abstract, array('style' => 'margin: 6px 0px; text-indent: 24px;'));
		}

		// biography information
        $text = '';
        for ($i=1; $i<=5; $i++) {
            $field = 'biography';
            if ($i >= 2) {
                $field .= "_$i";
            }
            if (isset($record->$field) && $record->$field) {
				$text .= html_writer::tag('p', $record->$field, array('style' => 'margin: 6px 0px;'));
            }
        }
        if ($text) {
			$html .= html_writer::tag('h5', get_string('biodata', $plugin), array('style' => 'font-size: 1.1em; margin: 12px 0px;'));
			$html .= html_writer::tag('div', $text, array('style' => 'font-style: italic;'));
        }
    }
}

if ($html) {
    $html = html_writer::tag('div', $html, array('style' => 'max-width: 720px; text-align: justify;'));
}

if (empty($instance->config->title)) {
    $filename = $block->name.'.html';
} else {
    $filename = format_string($instance->config->title, true);
    $filename = clean_filename(strip_tags($filename).'.html');
}
$filename = preg_replace('/[ \.]/', '.', $filename);

send_file($html, $filename, 0, 0, true, true);
//echo $html;
