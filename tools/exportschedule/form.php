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
 * block/maj_submissions/tools/exportschedule/form.php
 *
 * @package    block
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');

/**
 * block_maj_submissions_tool_exportschedule
 *
 * @package    tool
 * @subpackage maj_submissions
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */
class block_maj_submissions_tool_exportschedule extends block_maj_submissions_tool_form {

    /**
     * definition
     */
    public function definition() {

        $mform = $this->_form;
        $config = $this->instance->config;

        $this->set_form_id($mform);

        $name = 'language';
        $label = get_string($name);
        $options = $this->get_language_options($config);
        if (count($options)==1) {
            $lang = key($options);
            $mform->addElement('hidden', $name, $lang);
            $mform->addElement('static', '', $label, $options[$lang]);
        } else {
            $mform->addElement('select', $name, $label, $options);
        }
        $mform->setType($name, PARAM_ALPHANUM);

        $name = 'fileformat';
        $label = get_string($name, 'question');
        $options = $this->get_fileformat_options($config);
        $mform->addElement('select', $name, $label, $options);
        $mform->setType($name, PARAM_ALPHANUM);

        $this->add_action_buttons(true, get_string('export', 'grades'));
    }

    protected function get_language_options($config) {
        $langs = get_string_manager()->get_list_of_languages();

        if (empty($config->displaylangs)) {
            $options = array();
        } else {
            $options = explode(',', $config->displaylangs);
            $options = array_map('trim', $options);
            $options = array_filter($options);
        }
        if (empty($options)) {
            $options[] = 'en';
        }
        $options = array_combine($options, array_fill(0, count($options), ''));
        foreach ($options as $lang => $text) {
            if (array_key_exists($lang, $langs)) {
                $text = $langs[$lang];
            } else {
                $text = get_string('unknownlanguage', $this->plugin);
            }
            $options[$lang] = "$text ($lang)";
        }
        return $options;
    }

    protected function get_fileformat_options($config) {
        return array('csvshowgizmo' => get_string('filecsvshowgizmo', $this->plugin),
                     'excel' => get_string('fileexcel', $this->plugin),
                     'html' => get_string('filehtml', $this->plugin),
                     'pdf' => get_string('filepdf', $this->plugin));
    }

    /**
     * form_postprocessing
     *
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function form_postprocessing() {

        if ($data = $this->get_data()) {

            $lang = $data->language;
            switch ($data->fileformat) {

                case 'csvshowgizmo':
                    $msg = $this->export_csvshowgizmo($lang);
                    break;

                case 'excel':
                    $msg = $this->export_excel($lang);
                    break;

                case 'html':
                    $msg = $this->export_html($lang);
                    break;

                case 'pdf':
                    $msg = $this->export_pdf($lang);
                    break;

                default:
                    $msg = array(); // shouldn't happen !!
            }
        }

        return $msg;
    }


    /**
     * export_csvshowgizmo
     *
     * @uses $CFG
     * @param string $lang
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_csvshowgizmo($lang) {
        global $CFG;

        $msg = array();
        $config = $this->instance->config;

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
        list($records, $fieldnames) = $this->instance->get_submission_records($fields);

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
                        $value = block_maj_submissions::plain_text($value);
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
                        $value = block_maj_submissions::plain_text($value);
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

        if (empty($config->title)) {
            $filename = $instance->blockname.'.csv';
        } else {
            $filename = format_string($config->title, true);
            $filename = clean_filename(strip_tags($filename).'.csv');
        }
        $filename = preg_replace('/[ \.]+/', '.', $filename);
        send_file($lines, $filename, 0, 0, true, true, '', true); // don't die

        return $msg;
    }

    /**
     * export_excel
     *
     * @uses $CFG
     * @param string $lang
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_excel($lang) {
        global $CFG;
        $msg = array();
        return $msg;
    }

    /**
     * export_html
     *
     * @uses $CFG
     * @param string $lang
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_html($lang) {
        global $CFG;
        $msg = array();
        return $msg;
    }

    /**
     * export_pdf
     *
     * @uses $CFG
     * @param string $lang
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_pdf($lang) {
        global $CFG;
        $msg = array();
        return $msg;
    }
}
