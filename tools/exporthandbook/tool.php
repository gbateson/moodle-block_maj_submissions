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
 * blocks/maj_submissions/tools/exporthandbook/tool.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/blocks/moodleblock.class.php'); // class block_base
require_once($CFG->dirroot.'/blocks/maj_submissions/block_maj_submissions.php');
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');
require_once($CFG->dirroot.'/lib/filelib.php'); // function send_file()

$blockname = 'maj_submissions';
$plugin = "block_$blockname";
$tool = 'toolexporthandbook';

// get the incoming block_instance id
$id = required_param('id', PARAM_INT);

if (! $block_instance = $DB->get_record('block_instances', array('id' => $id))) {
    print_error('invalidinstanceid', $plugin);
}
if (! $block = $DB->get_record('block', array('name' => $block_instance->blockname))) {
    print_error('invalidblockid', $plugin, $block_instance->blockid);
}
if (class_exists('context')) {
    $context = context::instance_by_id($block_instance->parentcontextid);
} else {
    $context = get_context_instance_by_id($block_instance->parentcontextid);
}
if (! $course = $DB->get_record('course', array('id' => $context->instanceid))) {
    print_error('invalidcourseid', $plugin, $block_instance->pageid);
}
$course->context = $context;

require_login($course->id);
require_capability('moodle/course:manageactivities', $context);

if (! isset($block->version)) {
    $params = array('plugin' => $plugin, 'name' => 'version');
    $block->version = $DB->get_field('config_plugins', 'value', $params);
}

$html = '';
$presenters = array();

if ($instance = block_instance('maj_submissions', $block_instance, $PAGE)) {

    // each submission may have more than one user each of whom has info in more than one language
    // e.g. name_given_en, name_given_2_en
    $userfields = array(
        'name_given',
        'name_surname',
        'name_order',
        'email',
        'biography',
        'affiliation',
        'affiliation_state',
        'affiliation_country'
    );

    // the schedule fields should appear at most once for each submission
    $schedulefields = array(
        'presentation_title',
        'presentation_abstract',
        'presentation_type',
        'presentation_language',
        'schedule_time',
        'schedule_day',
        'schedule_time',
        'schedule_day',
        'schedule_number',
        'schedule_roomname',
        'schedule_roomtopic',
        'submission_status'
    );

    // get multilang versions of the user fields
    $fields = $instance->get_multilang_fieldnames($userfields);

    $nums = array();
    $userfields = array();

    // regex to extract (name)_(num)_(lang) from a field name
    $search = '/^(.+?)('.'(?:_[0-9]+)?'.')('.'(?:_[a-z]{2})?'.')$/';

    foreach ($fields as $field) {
        if (preg_match($search, $field, $match)) {
            list($field, $name, $num, $lang) = $match;
            if (empty($num)) {
                $num = '1';
            } else {
                $num = substr($num, 1);
            }
            if (empty($lang)) {
                $lang = 'en';
            } else {
                $lang = substr($lang, 1);
            }
            if (empty($userfields[$name])) {
                $userfields[$name] = array();
            }
            if (empty($userfields[$name][$num])) {
                $userfields[$name][$num] = array();
                ksort($userfields[$name]);
                $nums[intval($num)] = 1;
            }
            $userfields[$name][$num][$lang] = $field;
        }
    }

    // set value of maximum name index
    $i_max = max(array_keys($nums));

    $fields = array_merge($fields, $schedulefields);
    $fields = array_combine($fields, $fields);

    $records = array();
    $fieldnames = array();
    list($records, $fieldnames) = $instance->get_submission_records($fields);

    // sort $records by ('schedule_day', 'schedule_time', 'schedule_roomname')
    uasort($records, function($a, $b) {
        $fields = array('schedule_day', 'schedule_time', 'presentation_type', 'schedule_roomname');
        $search = '/(?<!\d)[1-9](?!\d)/';
        foreach ($fields as $field) {
            if (isset($a->$field) && isset($b->$field)) {
                $asort = $a->$field;
                $bsort = $b->$field;
                if ($field=='schedule_day' || $field='schedule_time') {
                    $asort = preg_replace($search, '0$0', $asort);
                    $bsort = preg_replace($search, '0$0', $bsort);
                }
                if ($asort > $bsort) {
                    return 1;
                }
                if ($asort < $bsort) {
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

    // setup ascii search and replace strings
    //  - $ascii matches all low ascii chars
    //  - $chars matches ascii chars excluding numbers: 0-9 (=hex 30-39)
    $ascii = block_maj_submissions_tool_form::low_ascii_substring();
    $chars = '\\x{0000}-\\x{0029}\\x{0040}-\\x{007F}';
    $daysearch = '/^(.*?\)) (.*?\)).*$/mu';
    $roomsearch = '/^(.+?) *\(.*?\) (.+?) *[(\\x{FF08}].*?[\\x{FF09})]$/mu';
    $asciisearch = '/^(['.$chars.']['.$ascii.']+) *([^'.$chars.']+?)$/mu';
    if (block_maj_submissions_tool_form::is_low_ascii_language()) {
        $dayreplace = '$1';
        $roomreplace = '$1';
        $asciireplace = '$1';
    } else {
        $dayreplace = '$2';
        $roomreplace = '$2';
        $asciireplace = '$2';
    }

    // check fields and format info about presenters
    foreach ($records as $recordid => $record) {

        //if (empty($record->schedule_day) || empty($record->schedule_time) || empty($record->schedule_room)) {
        //    continue;
        //}

        if (empty($record->submission_status)) {
            unset($records[$recordid]);
            continue;
        }
        if (is_numeric(strpos($record->submission_status, 'Not accepted'))) {
            unset($records[$recordid]);
            continue;
        }
        if (is_numeric(strpos($record->submission_status, 'Cancelled'))) {
            unset($records[$recordid]);
            continue;
        }

        // ensure all $fields exist
        foreach ($fields as $field) {
            if (empty($record->$field)) {
                $record->$field = '';
            }
        }

        foreach ($userfields as $name => $nums) {
            foreach ($nums as $num => $langs) {

                $values = array();
                foreach ($langs as $lang => $field) {
                    if (isset($record->$field)) {
                        if ($record->$field) {
                            $values[$lang] = $record->$field;
                        }
                        // remove this lang version of the field
                        unset($record->$field);
                    }
                }

                // remove the unnumbered version of this field
                if (isset($record->$name)) {
                    unset($record->$name);
                }

                // set the numbered version of the field
                $field = $name.'_'.$num;
                $record->$field = $instance->multilang_string($values);

                // reduce multilang spans to a single string
                if (strpos($record->$field, 'multilang')) {
                    $record->$field = format_string($record->$field, true);
                }

                // append country to affiliation
                if ($name=='affiliation_country') {
                    if ($text = $record->$field) {
                        $text = block_maj_submissions::textlib('strtolower', $text);
                        switch (true) {
                            case preg_match('/\b(brunei)\b/', $text): $text = 'Brunei'; break;
                            case preg_match('/\b(uk|(united kingdom)\b)/', $text): $text = 'UK'; break;
                            case preg_match('/\b(usa|(united states))\b/', $text): $text = 'USA'; break;
                            case preg_match('/\b(uae|(united emirates))\b/', $text): $text = 'UAE'; break;
                            default: $text = ucwords($text);
                        }
                        $record->{"affiliation_$num"} .= ' ('.$text.')';
                    }
                }
            }
        }

        foreach ($schedulefields as $field) {
            if ($text = $record->$field) {
                switch ($field) {
                    case 'presentation_abstract':
                        $record->$field = format_text($text);
                        break;
                    case 'schedule_day':
                        $record->$field = preg_replace($daysearch, $dayreplace, $text);
                        break;
                    case 'schedule_roomname':
                        $record->$field = preg_replace($roomsearch, $roomreplace, $text);
                        break;
                    default:
                        $record->$field = preg_replace($asciisearch, $asciireplace, $text);
                }
            }
        }

        // create link to this record in submissions database
        if ($text = $record->schedule_number) {
            $link = new moodle_url('/mod/data/view.php', array('rid' => $recordid));
            $link = html_writer::link($link, $text, array('target' => 'MAJ'));
        } else {
            $link = '';
        }

        // get name, email, affiliation of ALL presenters
        $names = array();
        for ($i=1; $i<=$i_max; $i++) {

            $givenname = "name_given_$i";
            $surname = "name_surname_$i";
            $nameorder = "name_order_$i";
            $affiliation = "affiliation_$i";
            $biography = "biography_$i";
            $email = "email_$i";

            // get multilang name and affiliation
            $SURNAME = block_maj_submissions::textlib('strtoupper', $record->$surname);
            if (isset($record->$nameorder) && strpos($record->$nameorder, 'SURNAME')===0) {
                $name = array($SURNAME, $record->$givenname);
            } else {
                $name = array($record->$givenname, $SURNAME);
            }
            $name = array_filter($name);
            if ($name = implode(' ', $name)) {

                // format author name + affiliation
                $text = html_writer::tag('b', $name);
                if ($record->$affiliation) {
                    $text .= ' '.html_writer::tag('i', $record->$affiliation);
                }
                $names[] = html_writer::tag('dd', $text, array('style' => 'margin: 8px 18px;'));

                // create key for presenters array
                $NAME = trim($record->$surname.' '.$record->$givenname);
                $NAME = block_maj_submissions::textlib('strtoupper', $NAME);

                // add to presenters list, if necessary
                if (! array_key_exists($NAME, $presenters)) {

                    // initialize presenter object
                    $presenter = (object)array('name' => $name,
                                               'email' => '',
                                               'affiliation' => '',
                                               'presentations' => array());
                    if (isset($record->$affiliation)) {
                        $presenter->affiliation = $record->$affiliation;
                    }
                    if (isset($record->$email)) {
                        $presenter->email = $record->$email;
                    }
                    if (empty($presenter->email)) {
                        // lookup email address
                        $select = '(firstname = ? AND lastname = ?) OR (firstnamephonetic = ? AND lastnamephonetic = ?)';
                        $params = array($record->$givenname,
                                        $record->$surname,
                                        $record->$givenname,
                                        $record->$surname);
                        if ($users = $DB->get_records_select('user', $select, $params)) {
                            $presenter->email = reset($users)->email;
                        }
                    }

                    // append presenter object to list
                    $presenters[$NAME] = $presenter;
                }

                // add link to submission database, if necessary
                if ($link) {
                    $presenters[$NAME]->presentations[] = $link;
                }
            }
        }

        $record->presenters = implode('', $names);
        $records[$recordid] = $record;
    }

    $day = '';
    $type = '';
    foreach ($records as $recordid => $record) {

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
        if ($record->schedule_day) {
            $text .= html_writer::tag('span', $record->schedule_day, array('style' => 'font-size: 1.6em; color: #999;')).' ';
        }
        if ($record->schedule_time) {
            $text .= html_writer::tag('span', $record->schedule_time, array('style' => 'font-size: 1.6em;')).' &nbsp; ';
        }
        if ($record->schedule_roomname) {
            if (strpos($record->schedule_roomname, '(')===false) {
                $record->schedule_roomname = '('.$record->schedule_roomname.')';
            }
            $text .= html_writer::tag('span', $record->schedule_roomname, array('style' => 'font-size: 1.2em;'));
        }
        if ($text) {
            $html .= html_writer::tag('h4', $text, array('style' => 'margin: 12px 0px;'));
        }

        // schedule number and presentation title
        $text = '';
        if ($record->schedule_number) {
            $text = html_writer::tag('span', '['.$record->schedule_number.']', array('style' => 'color: #f60; font-size: 0.8em;')).' ';
        }
        if ($record->presentation_title) {
            $text .= html_writer::tag('span', $record->presentation_title);
        }
        if ($text) {
            $text = html_writer::tag('b', $text);
            $html .= html_writer::tag('p', $text, array('style' => 'margin: 6px 0px; font-size: 24px;'));
        }

        // presenters
        if ($text = $record->presenters) {
            $html .= html_writer::tag('dl', $text);
        }

        // abstract
        if ($text = $record->presentation_abstract) {
            $text = html_writer::tag('dd', $text, array('style' => 'margin: 8px 18px; text-indent: 24px;'));
            $text = html_writer::tag('dt', get_string('abstract', $plugin), array('style' => 'font-weight: bold;')).$text;
            $html .= html_writer::tag('dl', $text);
        }

        // biography information
        $text = '';
        for ($i=1; $i<=5; $i++) {
            $biography = "biography_$i";
            if (isset($record->$biography) && $record->$biography) {
                $text .= html_writer::tag('dd', $record->$biography, array('style' => 'font-style: italic; margin: 8px 18px; text-indent: 24px;'));
            }
        }
        if ($text) {
            $text = html_writer::tag('dt', get_string('biodata', $plugin), array('style' => 'font-weight: bold;')).$text;
            $html .= html_writer::tag('dl', $text);
        }
    }
}

if (count($presenters)) {
    $html .= html_writer::tag('h2', get_string('listofpresenters', $plugin));

    $html .= html_writer::start_tag('table', array('border' => 0,
                                                   'cellpadding' => 8,
                                                   'cellspacing' => 0,
                                                   'width' => '100%'));
    $html .= html_writer::start_tag('tbody');

    $params = array('align' => 'left', 'style' => 'padding: 12px 8px;');
    $html .= html_writer::start_tag('tr', array('style' => 'background-color: #ffe4cc; font-size: 1.2em;'));
    $html .= html_writer::tag('th', get_string('name',         'moodle'), $params);
    $html .= html_writer::tag('th', get_string('affiliation',   $plugin), $params);
    $html .= html_writer::tag('th', get_string('email',        'moodle'), $params);
    $html .= html_writer::tag('th', get_string('presentations', $plugin), $params);
    $html .= html_writer::end_tag('tr');

    // sort $presenters by name
    ksort($presenters);

    $odd = 0;
    foreach ($presenters as $presenter) {
        $odd = ($odd ? 0 : 1);
        $params =  array('valign' => 'top', 'style' => 'background-color: '.($odd ? '#f0f0f0' : '#ddd').';');
        $html .= html_writer::start_tag('tr', $params);
        $html .= html_writer::tag('th', $presenter->name, array('align' => 'left'));
        $html .= html_writer::tag('td', $presenter->affiliation);
        $html .= html_writer::tag('td', $presenter->email);
        $html .= html_writer::tag('td', implode(', ', $presenter->presentations));
        $html .= html_writer::end_tag('tr');
    }

    $html .= html_writer::end_tag('tbody');
    $html .= html_writer::end_tag('table');
}

if ($html) {
    $html = html_writer::tag('div', $html, array('style' => 'max-width: 720px; text-align: justify;'));
}

if (empty($instance->config->title)) {
    $filename = $block->name;
} else {
    $filename = format_string($instance->config->title, true);
    $filename = clean_filename(strip_tags($filename));
}
$filename .= '_'.current_language().'.html';
$filename = preg_replace('/[ \.]/', '.', $filename);

send_file($html, $filename, 0, 0, true, true);
