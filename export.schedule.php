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
require_once('../../config.php');
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

    $ids = array();
    if ($cmid = $instance->config->collectpresentationscmid) {
        $ids[] = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    }
    if ($cmid = $instance->config->collectsponsoredscmid) {
        $ids[] = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    }
    if ($cmid = $instance->config->collectworkshopscmid) {
        $ids[] = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
    }
    $ids = array_filter($ids);
    if (empty($ids)) {
        $datawhere = '?';
        $dataparams = array(0);
    } else {
        list($datawhere, $dataparams) = $DB->get_in_or_equal($ids);
    }

    list($where, $params) = $DB->get_in_or_equal($fields);
    $select = 'dc.id, df.name, dc.recordid, dc.content';
    $from   = '{data_content} dc '.
              'LEFT JOIN {data_fields} df ON dc.fieldid = df.id';
    $where  = "df.dataid $datawhere AND df.name $where";
    $params = array_merge($dataparams, $params);
    $order  = 'dc.recordid';

    $records = array();
    $fieldnames = array();
    if ($values = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
        foreach ($values as $value) {
            if (empty($value->content)) {
                continue;
            }
            $rid = $value->recordid;
            $fieldname = $value->name;
            $fieldnames[$fieldname] = true;
            if (empty($records[$rid])) {
                $records[$rid] = new stdClass();
            }
            $records[$rid]->$fieldname = $value->content;
        }
    }

    // cache some useful regular expressions
    $timesearch = '/^ *(\d+) *: *(\d+) *- *(\d+) *: *(\d+) *$/';
    $mainlangsearch = '/<span[^>]*lang="en"[^>]*>(.*?)<\/span>/';
    $multilangsearch = '/<span[^>]*lang="[^""]*"[^>]*>.*?<\/span>/';

    // add fields from each record
    $lines = array();
    foreach ($records as $record) {
        $line = array();
        foreach ($fields as $name => $field) {
            if (! array_key_exists($field, $fieldnames)) {
                continue;
            }
            if (empty($record->$field)) {
                $line[] = '';
                continue;
            }
            switch ($name) {
                case 'text':
                    $record->$field = block_maj_submissions::trim_text($record->$field, 100, 100, 0);
                    $line[] = block_maj_submissions_tool_form::plain_text($record->$field);
                    break;
                case 'start_date':
                case 'end_date':
                    $record->$field = preg_replace($mainlangsearch, '$1', $record->$field);
                    $record->$field = preg_replace($multilangsearch, '', $record->$field);
                    $line[] = strtotime($record->$field);
                    break;
                case 'start_time':
                    $line[] = preg_replace($timesearch, '$1:$2', $record->$field);
                    break;
                case 'end_time':
                    $line[] = preg_replace($timesearch, '$3:$4', $record->$field);
                    break;
                default:
                    if (strpos($record->$field, 'multilang')) {
                        $record->$field = preg_replace($mainlangsearch, '$1', $record->$field);
                        $record->$field = preg_replace($multilangsearch, '', $record->$field);
                    }
                    $line[] = $record->$field;
            }
        }
        if (count($line)) {
            $lines[] = $line;
        }
    }

    // create data lines
    if (empty($lines)) {
        $lines = '';
    } else {
        $fp = fopen('php://temp', 'w+');
        foreach ($lines as $line) {
            fputcsv($fp, $line);
        }
        rewind($fp);
        $lines = stream_get_contents($fp);
        fclose($fp);
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
