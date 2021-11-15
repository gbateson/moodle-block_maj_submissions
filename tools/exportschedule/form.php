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
        $this->set_form_id($mform);

        $name = 'language';
        $label = get_string($name);
        $options = $this->get_language_options();
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
        $options = $this->get_fileformat_options();
        $mform->addElement('select', $name, $label, $options);
        $mform->setType($name, PARAM_ALPHANUM);
        if (array_key_exists('excel', $options)) {
            $mform->setDefault($name, 'excel');
        }

        // add checkboxes
        $names = array('addbannerimage',
                       'addconferencename',
                       'addscheduletitle');
        foreach ($names as $name) {
            $this->add_field($mform, $this->plugin, $name, 'checkbox', PARAM_INT);
            $mform->disabledIf($name, 'fileformat', 'eq', 'csvshowgizmo');
        }

        $this->add_action_buttons(true, get_string('export', 'grades'));
    }

    protected function get_language_options() {

        // Get list of all languages.
        $langs = get_string_manager()->get_list_of_translations(true);

        $config = $this->instance->config;
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
            $options[$lang] = $text;
        }
        return $options;
    }

    protected function get_fileformat_options() {
        $plugin = $this->plugin;
        return array('csvshowgizmo' => get_string('filecsvshowgizmo', $plugin),
                     'excel' => get_string('fileexcel', $plugin),
                     //'pdf' => get_string('filepdf', $plugin),
                     'html' => get_string('filehtml', $plugin));
    }

    /**
     * form_postprocessing
     *
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function form_postprocessing() {
        if ($data = $this->get_data()) {

            // Ensure checbox values are set.
            $names = array('addbannerimage',
                           'addconferencename',
                           'addscheduletitle');
            foreach ($names as $name) {
                if (empty($data->$name)) {
                    $data->$name = 0;
                }
            }

            $lang = $data->language;
            switch ($data->fileformat) {

                case 'csvshowgizmo':
                    $msg = $this->export_csvshowgizmo($lang, $data);
                    break;

                case 'excel':
                    $msg = $this->export_excel($lang, $data);
                    break;

                case 'html':
                    $msg = $this->export_html($lang, $data);
                    break;

                case 'pdf':
                    $msg = $this->export_pdf($lang, $data);
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
     * @param array $data
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_csvshowgizmo($lang, $data) {
        global $CFG;

        $msg = array();

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
        $this->send_file($lines, 'csv', $data);
        // script will die at end of send_file()
    }

    /**
     * export_excel
     *
     * @uses $CFG
     * @param string $lang
     * @param array $data
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_excel($lang, $data) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        if (is_dir($CFG->dirroot.'/lib/phpspreadsheet')) {
            // Moodle >= 3.8
            require_once($CFG->dirroot.'/lib/phpspreadsheet/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/RichText/RichText.php');
        }
        if (is_dir($CFG->dirroot.'/lib/phpexcel/PHPExcel')) {
            // Moodle 2.5 - 3.7
            require_once($CFG->dirroot.'/lib/phpexcel/PHPExcel/IComparable.php');
            require_once($CFG->dirroot.'/lib/phpexcel/PHPExcel/RichText.php');
        }
        require_once($CFG->dirroot.'/blocks/maj_submissions/tools/exportschedule/excellib.class.php');

        $msg = array();

        $html = $this->get_schedule($lang, false);

        $workbook = null;
        $worksheet = null;
        $formats = null;
        $banner = null;

        $search = (object)array(
            'days' => '/(<tbody[^>]*>)(.*?)<\/tbody>/isu',
            'rows' => '/(<tr[^>]*>)(.*?)<\/tr>/isu',
            'cells' => '/(<t[hd][^>]*>)(.*?)<\/t[hd]>/isu',
            'attributes' => '/(\w+)="([^"]*)"/isu',
            'richtext' => '/<(b|i|u|s|strike|sub|sup|big|small)\b[^>]*>(.*?)<\/\\1>/isu',
            // row classes
            'date' => '/\bdate\b/',
            'roomheadings' => '/\broomheadings\b/',
            // cell classes
            'roomheading' => '/\broomheading\b/',
            'timeheading' => '/\btimeheading\b/',
            'multiroom' => '/\bmultiroom\b/',
            'event' => '/\bevent\b/',
            'keynote' => '/\bkeynote\b/',
            'lightning' => '/\blightning\b/',
            'poster' => '/\b(poster|symposium)\b/',
            'presentation' => '/\b(paper|presentation)\b/',
            'sponsoredlunch' => '/\bsponsored +lunch\b/',
            'virtual' => '/\b(virtual|case|casestudy)\b/',
            'workshop' => '/\bworkshop\b/',
            // cell content
            'eventdetails' => '/<br>.*$/isu',
            'sponsorname' => '/<i>[^<>()]*\(([^<>()]*)\)<\/i>/',
            'et_al' => '/<i>([^<>()]*?) *(et al\.)<\/i>/'
        );

        $formats = array(
            'conferencename' => ['h_align' => 'center', 'v_align' => 'center', 'size' => 36, 'border' => 0],
            'scheduletitle' => ['h_align' => 'center', 'v_align' => 'center', 'size' => 24, 'border' => 0],
            'bannerimage'  => ['h_align' => 'center', 'v_align' => 'center', 'size' => 12, 'border' => 0],
            'default'      => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10],
            'date'         => ['h_align' => 'left',   'v_align' => 'center', 'size' => 18, 'bg_color' => '#af2418'], // dark red
            'event'        => ['h_align' => 'left',   'v_align' => 'center', 'size' => 14, 'bg_color' => '#f2f2f2'], // grey (5%)
            'roomheading'  => ['h_align' => 'center', 'v_align' => 'center', 'size' => 14, 'bg_color' => '#eeddee'], // purple
            'smallevent'   => ['h_align' => 'left',   'v_align' => 'top',    'size' => 14, 'bg_color' => '#f2f2f2'], // grey (5%)
            'timeheading'  => ['h_align' => 'center', 'v_align' => 'top',    'size' => 12, 'bg_color' => '#ffffff'], // white
            'lightning'    => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#f8ffff'], // blue
            'keynote'      => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#f9f2ec'], // brown
            'poster'       => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#edf8f2'], // light green
            'presentation' => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#fffcf6'], // yellow
            'virtual'      => ['h_align' => 'left',   'v_align' => 'center', 'size' => 10, 'bg_color' => '#f8f8ff'], // light blue
            'workshop'     => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#fff8ff'], // purple
        );

        if ($data->addbannerimage) {
            $banner = $this->get_banner_image();
            if (empty($banner)) {
                $data->addbannerimage = 0;
            }
        }

        if (empty($data->addbannerimage)) {
            unset($formats['bannerimage']);
        }

        if (empty($data->addconferencename)) {
            unset($formats['conferencename']);
        }

        if (empty($data->addscheduletitle)) {
            unset($formats['scheduletitle']);
        }

        if (empty($this->instance->config->title)) {
            $instancetitle = '';
        } else {
            $instancetitle = $this->instance->config->title;
        }

        $colwidth = (object)array('timeheading' => 15,
                                  'default' => 25);

        $rowheight = (object)array('conferencename' => 42,
                                   'date' => 32,
                                   'event' => 34,
                                   'multiroom' => 45,
                                   'roomheadings' => 40,
                                   'scheduletitle' => 30,
                                   'sponsoredlunch' => 70,
                                   'virtual' => 54,
                                   'default' => -1); // = autofit

        if (class_exists('\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup')) {
            // Moodle >= 3.8
            $landscape = \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE;
            $papersize_a4 = \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4;
        } else if (class_exists('PHPExcel_Worksheet_PageSetup')) {
            // Moodle 2.5 - 3.7
            $landscape = PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE;
            $papersize_a4 = PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4;
        } else {
            // shouldn't happen !!
            $landscape = 'landscape';
            $papersize_a4 = 9;
        }

        if (preg_match_all($search->days, $html, $days)) {

            $countdays = count($days[0]);
            for ($d=0; $d<$countdays; $d++) {

                $dayname = '';
                $worksheet = null;

                if (preg_match_all($search->rows, $days[0][$d], $rows)) {

                    $row = 0;
                    $lastcol = 0;
                    $offset = array();

                    // setup the $offset array for the whole day
                    // BEFORE you start generating any Excel cells

                    $countrows = count($rows[0]);
                    for ($r=0; $r<$countrows; $r++) {

                        $col = 0;
                        $rowclass = '';

                        if (preg_match_all($search->attributes, $rows[1][$r], $matches)) {

                            $count = count($matches[0]);
                            for ($i=0; $i<$count; $i++) {

                                switch ($matches[1][$i]) {
                                    case 'class': $rowclass = trim($matches[2][$i]); break;
                                    // ignore anything else
                                }
                            }
                        }

                        if (preg_match_all($search->cells, $rows[2][$r], $cells)) {

                            $row_is_date = preg_match($search->date, $rowclass);
                            $row_is_roomheadings = preg_match($search->roomheadings, $rowclass);
                            $row_is_multiroom = false;
                            $row_is_event = false;
                            $row_is_sponsoredlunch = false;
                            $row_is_virtual = false;

                            $countcells = count($cells[0]);
                            if ($lastcol < $countcells) {
                                $lastcol = $countcells;
                            }

                            if (empty($offset[0])) {
                                $offset[0] = array_fill(0, $lastcol, 0);
                            }

                            // cache the cell attributes (class, colspan, rowspan)
                            // for all cels in the current row
                            $attributes = array();
                            for ($c=0; $c<$countcells; $c++) {

                                $attribute = array('', 1, 1); // class, colspan, rowspan
                                if (preg_match_all($search->attributes, $cells[1][$c], $matches)) {

                                    $count = count($matches[0]);
                                    for ($i=0; $i<$count; $i++) {

                                        switch ($matches[1][$i]) {
                                            case 'class': $attribute[0] =  trim($matches[2][$i]); break;
                                            case 'colspan': $attribute[1] = intval($matches[2][$i]); break;
                                            case 'rowspan': $attribute[2] = intval($matches[2][$i]); break;
                                            // ignore "id", "style" and anything else
                                        }
                                    }
                                }
                                $attributes[$c] = $attribute;
                            }

                            // Process rowspan/colspan cells in the current row.
                            // We need to do this from from right to left (i.e. $c--) so that
                            // the $offset values in subsequent rows are calculated correctly.
                            for ($c=($countcells-1); $c>=0; $c--) {

                                list($cellclass, $colspan, $rowspan) = $attributes[$c];

                                if ($colspan > 1 || $rowspan > 1) {
                                    for ($roffset=0; $roffset<$rowspan; $roffset++) {
                                        if (empty($offset[$roffset])) {
                                            $offset[$roffset] = array_fill(0, $lastcol, 0);
                                        }
                                        // For the current row, we clear cells to the right of the current cell.
                                        // For subsequent rows, we clear clear cells including current column.
                                        $coffsetmin = ($roffset==0 ? 1 : 0);

                                        $renumber = false;
                                        for ($coffset = $coffsetmin; $coffset < $colspan; $coffset++) {
                                            array_splice($offset[$roffset], $c + $coffset, 1);
                                            $renumber = true;
                                        }
                                        if ($renumber) {
                                            $offset[$roffset] = array_values($offset[$roffset]);
                                        }
                                        $cindexmin = $c + $coffsetmin;
                                        $cindexmax = count($offset[$roffset]);
                                        $cincrement = ($colspan - $coffsetmin);
                                        for ($cindex = $cindexmin; $cindex < $cindexmax; $cindex++) {
                                            $offset[$roffset][$cindex] += $cincrement;
                                        }
                                    }
                                }
                            }

                            // format the cells in this row
                            for ($c=0; $c<$countcells; $c++) {

                                list($cellclass, $colspan, $rowspan) = $attributes[$c];

                                // Set flags that apply to the entire row
                                if (preg_match($search->multiroom, $cellclass)) {
                                    $row_is_multiroom = true;
                                }
                                if (preg_match($search->virtual, $cellclass)) {
                                    $row_is_virtual = true;
                                }
                                if (preg_match($search->event, $cellclass)) {
                                    $row_is_event = true;
                                    $cell_is_event = true;
                                } else {
                                    $cell_is_event = false;
                                }
                                if (preg_match($search->sponsoredlunch, $cellclass)) {
                                    $row_is_sponsoredlunch = true;
                                    $cell_is_sponsoredlunch = true;
                                } else {
                                    $cell_is_sponsoredlunch = false;
                                }

                                if ($workbook===null) {
                                    $filename = $this->make_filename('xlsx', $data);
                                    $workbook = new block_maj_submissions_ExcelWorkbook($filename);
                                    $workbook->send($filename);
                                    foreach ($formats as $f => $format) {
                                        $formats[$f] = new block_maj_submissions_ExcelFormat($format);
                                    }
                                    $formats = (object)$formats;
                                }

                                if ($worksheet===null) {
                                    if (preg_match($search->date, $rowclass)) {
                                        $dayname = block_maj_submissions::plain_text($cells[2][$c]);
                                    } else {
                                        $dayname = get_string('day', $this->plugin).': '.($d + 1);
                                    }
                                    $worksheet = $workbook->add_worksheet($dayname);

                                    // http://www.craiglotter.co.za/2010/04/18/setting-your-worksheet-printing-layout-options-in-phpexcel/
                                    $worksheet->setup_page(array(
                                        'Orientation' => $landscape,
                                        'PaperSize'   => $papersize_a4,
                                        'FitToPage'   => true,
                                        'FitToHeight' => 1,
                                        'FitToWidth'  => 1,
                                    ));
                                }

                                $text = html_entity_decode($cells[2][$c]);

                                // Fix white space before "et al."
                                $text = preg_replace($search->et_al, '<i>$1 $2</i>', $text);

                                switch (true) {
                                    case $row_is_date && $instancetitle:
                                        $text = "$instancetitle: $text";
                                        break;

                                    case $cell_is_event:
                                        $text = preg_replace($search->eventdetails, '', $text);
                                        break;

                                    case $cell_is_sponsoredlunch:
                                        $text = preg_replace($search->sponsorname, '$1', $text);
                                        break;
                                }

                                $text = str_replace('<br>', chr(10), $text);

                                // set format for this cell
                                switch (true) {

                                    case $row_is_date:
                                        $format = $formats->date;
                                        break;

                                    case $cell_is_event:
                                        if ($countcells <= 2) {
                                            $format = $formats->event;
                                        } else {
                                            $format = $formats->smallevent;
                                        }
                                        break;

                                    case preg_match($search->timeheading, $cellclass):
                                        $format = $formats->timeheading;
                                        break;

                                    case preg_match($search->roomheading, $cellclass):
                                        $format = $formats->roomheading;
                                        break;

                                    case preg_match($search->lightning, $cellclass):
                                        $format = $formats->lightning;
                                        break;

                                    case preg_match($search->keynote, $cellclass):
                                        $format = $formats->keynote;
                                        break;

                                    case preg_match($search->poster, $cellclass):
                                        $format = $formats->poster;
                                        break;

                                    case preg_match($search->presentation, $cellclass):
                                        $format = $formats->presentation;
                                        break;

                                    case preg_match($search->virtual, $cellclass):
                                        $format = $formats->virtual;
                                        break;

                                    case preg_match($search->workshop, $cellclass):
                                        $format = $formats->workshop;
                                        break;

                                    default:
                                        $format = $formats->default;
                                }

                                // $i(ndex) on chars in $text string
                                $i = 0;

                                // convert <big>, <small>, <b>, <i> and <u>, to richtext
                                if (preg_match_all($search->richtext, $text, $strings, PREG_OFFSET_CAPTURE)) {

                                    if (class_exists('\PhpOffice\PhpSpreadsheet\RichText\RichText')) {
                                        $richtext = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                                    } else if (class_exists('PHPExcel_RichText')) {
                                        $richtext = new PHPExcel_RichText();
                                    } else {
                                        $richtext = null;
                                    }

                                    // fetch default font size for this cell
                                    $fontsize = $format->get_format_array();
                                    $fontsize = $fontsize['font']['size'];

                                    $countstrings = count($strings[0]);
                                    for ($s=0; $s<$countstrings; $s++) {

                                        // extract next match and start position
                                        list($string, $start) = $strings[0][$s];

                                        // append plain text, if any
                                        if ($i < $start) {
                                            // Because PREG_OFFSET_CAPTURE captures only the plain byte number,
                                            // not the unicode character number, we use the plain "substr"
                                            // function, and not the multibyte equivalent method in "core_text".
                                            $richtext->createText(substr($text, $i, $start - $i));
                                        }

                                        // move $i(ndex) to end of current $string
                                        $i = $start + strlen($string);

                                        // convert next $string to richtext
                                        $string = $strings[2][$s][0];
                                        $font = $richtext->createTextRun($string)->getFont();

                                        // set font size to that of parent cell
                                        $font->setSize($fontsize);

                                        // add font format info, if necessary
                                        switch ($strings[1][$s][0]) {
                                            case 'b': $font->setBold(true); break;
                                            case 'i': $font->setItalic(true); break;
                                            case 'u': $font->setUnderline(true); break;
                                            case 'sub': $font->setSubScript(true); break;
                                            case 'sup': $font->setSuperScript(true); break;
                                            case 's': // alias for "strike"
                                            case 'strike': $font->setStrikethrough(true); break;
                                            case 'big': $font->setSize(intval($fontsize * 1.2)); break;
                                            case 'small': $font->setSize(intval($fontsize * 0.8)); break;
                                        }
                                    }

                                    // append trailing plain text, if any
                                    $richtext->createText(substr($text, $i));

                                    $text = $richtext;
                                }

                                $row = $r;
                                $col = $c;
                                if (isset($offset[0][$c])) {
                                    $col += $offset[0][$c];
                                }

                                $worksheet->write_string($row, $col, $text, $format);

                                if ($colspan > 1 || $rowspan > 1) {
                                    // add blank cells for the merge
                                    for ($roffset = 0; $roffset < $rowspan; $roffset++) {
                                        $coffsetmin = ($roffset==0 ? 1 : 0);
                                        for ($coffset = $coffsetmin; $coffset < $colspan; $coffset++) {
                                            $worksheet->write_blank($row + $roffset, $col + $coffset, $format);
                                        }
                                    }
                                    // now we can merge the cells
                                    $rowmax = ($row + $rowspan - 1);
                                    $colmax = ($col + $colspan - 1);
                                    $worksheet->merge_cells($row, $col, $rowmax, $colmax);
                                }

                            } // end for ($c=0 ...) loop through cells

                            // The order of these case statements is important.
                            if ($countcells) {
                                switch (true) {

                                    case $row_is_date:
                                        $height = $rowheight->date;
                                        break;

                                    case $row_is_sponsoredlunch:
                                        $height = $rowheight->sponsoredlunch;
                                        break;

                                    case $row_is_event:
                                        $height = $rowheight->event;
                                        break;

                                    case $row_is_roomheadings:
                                        $height = $rowheight->roomheadings;
                                        break;

                                    case $row_is_multiroom:
                                        $height = $rowheight->multiroom;
                                        break;

                                    case $row_is_virtual:
                                        $height = $rowheight->virtual;
                                        break;

                                    default:
                                        $height = $rowheight->default;
                                        break;
                                }
                                $worksheet->set_row($r, $height);
                            }
                        }

                        // remove offets for this row
                        array_shift($offset);

                    } // end for ($r=0 ...) loop through rows

                    if ($lastcol) {
                        $worksheet->set_column(0, 0, $colwidth->timeheading);
                        $worksheet->set_column(1, $lastcol - 1, $colwidth->default);
                    }

                    if ($countrows > 18) {
                        $worksheet->setup_page(array('FitToHeight' => ceil($countrows / 18)));
                    }

                    if ($d==0) {

                        $row = 0;
                        $col = 0;

                        // Add add banner image, if required.
                        if ($data->addbannerimage) {

                            // Get total width (in chars)
                            $width = $colwidth->timeheading;
                            $width += ($colwidth->default * ($lastcol - 1));

                            // Convert width-in-chars to width-in-pixels (8.43 chars = 64 pixels)
                            $width = ($width * (64/8.43));

                            // Determine scale factor
                            $scale = ($width / $banner->width);

                            // Multiply by 0.75 to convert pixels to "points" used by Excel.
                            $height = ($scale * $banner->height * 0.75);

                            $worksheet->insert_rows($row + 1);
                            $worksheet->merge_cells($row, $col, $row, $lastcol - 1);
                            $worksheet->insert_bitmap($row, $col, $banner->filepath, 0, 0, $scale, $scale);
                            $worksheet->write_string($row, $col, '', $formats->bannerimage);
                            $worksheet->set_row($row, $height);
                            $row++;
                        }

                        // Add conference name, if required.
                        if ($data->addconferencename) {
                            $text = $this->instance->config->title;
                            $worksheet->insert_rows($row + 1);
                            $worksheet->merge_cells($row, $col, $row, $lastcol - 1);
                            $worksheet->write_string($row, $col, $text, $formats->conferencename);
                            $worksheet->set_row($row, $rowheight->conferencename);
                            $row++;
                        }

                        // Add schedule title, if required.
                        if ($data->addscheduletitle) {
                            $text = $this->get_schedule_title($lang);
                            $worksheet->insert_rows($row + 1);
                            $worksheet->merge_cells($row, $col, $row, $lastcol - 1);
                            $worksheet->write_string($row, $col, $text, $formats->scheduletitle);
                            $worksheet->set_row($row, $rowheight->scheduletitle);
                            $row++;
                        }

                        // Add a spacer row, if required.
                        if ($row) {
                            $worksheet->insert_rows($row + 1);
                            $row++;
                        }
                    }
                } // end if preg_match($search->rows ...)

            } // end for ($d=0 ...) loop through days
        } // end if preg_match($search->days ...)

        if ($workbook) {
            $workbook->close();

            // Remove banner image from local hard disk
            if ($banner && $banner->filepath) {
                @unlink($banner->filepath);
            }

            die;
        }

        return $msg;
    }

    /**
     * export_html
     *
     * @uses $CFG
     * @param string $lang
     * @param array $data
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_html($lang, $data) {
        if ($html = $this->get_schedule_html($lang, $data)) {
            $filename = $this->make_filename('html', $data);
            send_file($html, $filename, 0, 0, true, true);
            // script will die here if schedule was found
        }
        return array(get_string('noschedule', $this->plugin));
    }

    /**
     * export_pdf
     *
     * @uses $CFG
     * @param string $lang
     * @param array $data
     * @return array $msg
     * @todo Finish find a way to add the CSS styles to the PDF output
     */
    public function export_pdf($lang, $data) {
        global $CFG, $PAGE;
        require_once($CFG->libdir.'/pdflib.php');

        if ($html = $this->get_schedule($lang, false)) {

            // generate/fetch css
            // Currently the ful CSS causes a timeout
            // while the styles.css for this plugin has no effect :-(
            $css = '';
            if ($css) {
                if ($PAGE->theme->has_css_cached_content()) {
                    $css = $PAGE->theme->get_css_cached_content();
                } else {
                    $css = $PAGE->theme->get_css_content();
                    $PAGE->theme->set_css_content_cache($css);
                }
                $css = html_writer::tag('style', $css);
            }

            // Create new PDF doc (in landscape orientation)
            $doc = new pdf('L');

            // basic info
            // $doc->SetTitle($value);
            // $doc->SetAuthor($value);
            // $doc->SetCreator($value);
            // $doc->SetKeywords($value);
            // $doc->SetSubject('Schedule');
            $doc->SetMargins(15, 30);

            // header info
            // $doc->setPrintHeader($value);
            // $doc->setHeaderMargin($value);
            // $doc->setHeaderFont($value);
            // $doc->setHeaderData($value);

            // footer info
            // $doc->setPrintFooter($value);
            // $doc->setFooterMargin($value);
            // $doc->setFooterFont($value);

            // text and font info
            // $doc->SetTextColor($value[0], $value[1], $value[2]);
            // $doc->SetFillColor($value[0], $value[1], $value[2]);
            $doc->SetFont('kozgopromedium', '', 10); // family, style, size

            $doc->AddPage();
            $doc->writeHTML($html);

            $filename = $this->make_filename('pdf', $data);
            $doc->Output($filename, 'D'); // force download
            die;
        }

        return array(get_string('noschedule', $this->plugin));
    }

    /**
     * get_schedule_html
     *
     * @uses $CFG
     * @param string $lang
     * @return array $msg
     * @todo Finish documenting this function
     */
    protected function get_schedule_html($lang, $data) {
        global $CFG;
        if ($html = $this->get_schedule($lang, true)) {

            $headers = array();
            if ($data->addbannerimage && ($banner = $this->get_banner_image())) {
                $header = base64_encode(file_get_contents($banner->filepath));
                $header = '<img src="data:'.mime_content_type($banner->filepath).';base64,'.$header.'">';
                $header = html_writer::tag('p', $header)."\n";
                @unlink($banner->filepath);
                $headers[] = $header;
            }

            if ($data->addconferencename) {
                $header = $this->instance->config->title;
                $header = html_writer::tag('h1', $header);
                $headers[] = $header;
            }

            if ($data->addscheduletitle) {
                $header = $this->get_schedule_title($lang);
                $header = html_writer::tag('h2', $header);
                $headers[] = $header;
            }

            if ($headers = implode('', $headers)) {
                $html = $headers.$html;
            }

            $filename = $CFG->dirroot.'/blocks/maj_submissions/templates/template.css';
            if (file_exists($filename)) {
                if ($style = file_get_contents($filename)) {
                    $style = "\n".$style."\n";
                    $params = array('type' => 'text/css');
                    $style = html_writer::tag('style', $style, $params);
                }
            } else {
                $style = '';
            }

            $filename = $CFG->dirroot.'/blocks/maj_submissions/templates/template.js';
            if (file_exists($filename)) {
                if ($script = file_get_contents($filename)) {
                    $script = "\n//<![CDATA[\n".$script."\n//]]>\n";
                    $params = array('type' => 'text/javascript');
                    $script = html_writer::tag('script', $script, $params);
                }
            } else {
                $script = '';
            }
            $html = html_writer::tag('head', "\n".'<meta charset="UTF-8">'."\n".$style)."\n".
                    html_writer::tag('body', "\n".$html."\n".$script, array('class' => 'lang-'.$lang));
            $html = html_writer::tag('html', $html);
        }
        return $html;
    }

    /**
     * get_schedule
     *
     * @uses $CFG
     * @param string $lang
     * @return string HTML content from the conference schedule page resource
     * @todo Finish documenting this function
     */
    protected function get_schedule($lang, $keep_thead) {
        global $DB;

        $html = '';
        $config = $this->instance->config;
        if ($cmid = $config->publishcmid) {
            $modinfo = get_fast_modinfo($this->course);
            if (array_key_exists($cmid, $modinfo->cms)) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname=='page') {
                    $html = $DB->get_field('page', 'content', array('id' => $cm->instance));

                    // Put room name after title for multiroom sessions.
                    $search = '/<td class="[^"]*multiroom[^"]*"[^>]*>(.+?)<\/td>/isu';
                    if (preg_match_all($search, $html, $matches, PREG_OFFSET_CAPTURE)) {
                        $i_max = count($matches[0]);
                        for ($i = ($i_max - 1); $i >= 0; $i--) {
                            list($match, $start) = $matches[0][$i];
                            $length = strlen($match);
                            $search = '/<span class="roomname">(.+?)<\/span>/';
                            if (preg_match($search, $match, $roomname)) {
                                $roomname = block_maj_submissions::plain_text($roomname[1]);
                                if ($roomname = trim($roomname)) {
                                    $roomname = html_writer::tag('b', '('.$roomname.')');
                                    $search = '/(<div class="title">)(.+?)(<\/div>)/';
                                    $match = preg_replace($search, '$1$2'.' '.$roomname.'$3', $match);
                                }
                            }
                            $html = substr_replace($html, $match, $start, $length);
                        }
                    }

                    $thead = '';
                    if ($keep_thead) {
                        $search = '/<thead[^>]*>.*?<\/thead>/isu';
                        if (preg_match($search, $html, $match)) {
                            $thead = $match[0];
                        }
                    }

                    // remove embedded SCRIPT and STYLE tags and THEAD
                    $search = array('/<script[^>]*>.*?<\/script>\s*/isu',
                                    '/<style[^>]*>.*?<\/style>\s*/isu',
                                    '/<thead[^>]*>.*?<\/thead>\s*/isu');
                    $html = preg_replace($search, '', $html);


                    // remove white space around HTML block elements
                    $search = '/\s*(<\/?(table|thead|tbody|tr|th|td|div)[^>]*>)\s*/s';
                    $html = preg_replace($search, '$1', $html);

                    // remove unwanted multilang strings
                    $html = $this->remove_multilang_spans($html, $lang);


                    // remove SPAN.category|topic (within DIV.categorytypetopic)
                    // remove SPAN:empty
                    // remove DIV.roomtopic (within DIV.room)
                    // remove DIV.time|room|summary
                    // remove DIV.times|keywords
                    // remove DIV.scheduleinfo
                    // remove DIV:empty
                    $search = array('/<span[^>]*class="(category|topic)"[^>]*>.*?<\/span>\s*/',
                                    '/<span[^>]*><\/span>\s*/',
                                    '/<div[^>]*class="(roomtopic)"[^>]*>.*?<\/div>\s*/',
                                    '/<div[^>]*class="(time|room|summary)"[^>]*>.*?<\/div>\s*/',
                                    '/<div[^>]*class="(times|keywords)"[^>]*>.*?<\/div>\s*/',
                                    '/<div[^>]*class="(scheduleinfo)"[^>]*>.*?<\/div>\s*/',
                                    '/<div[^>]*><\/div>\s*/');
                    $html = preg_replace($search, '', $html);

                    // format certain non-empty cells
                    $search = array('/<span[^>]*class="startfinish"[^>]*>(.*?)<\/span>\s*/',
                                    '/<span[^>]*class="duration"[^>]*>(.*?)<\/span>\s*/',
                                    '/<span[^>]*class="roomname"[^>]*>(.*?)<\/span>\s*/',
                                    '/<span[^>]*class="roomseats"[^>]*>(.*?)<\/span>\s*/',
                                    '/<div[^>]*class="title"[^>]*>(.*?)<\/div>\s*/',
                                    '/<span[^>]*class="schedulenumber"[^>]*>(.*?)<\/span>\s*/',
                                    '/<span[^>]*class="authornames"[^>]*>(.*?)<\/span>\s*/',
                                    '/<span[^>]*class="type"[^>]*>(.*?)<\/span>\s*/');
                    $replace = array('<b>$1</b>', '<br><i>$1</i>', '<b>$1</b>', '<br>($1)', '$1<br>', '[$1]', ' <i>$1</i>', '<small>$1</small>');
                    $html = preg_replace($search, $replace, $html);

                    // reduce SPAN tags
                    // reduce DIV tags
                    // reduce P tags
                    // remove <br> at end of table data cell
                    $search = array('/<span[^>]*>(.*?)<\/span>/',
                                    '/<div[^>]*>(.*?)<\/div>/',
                                    '/<p[^>]*>(.*?)<\/p>/',
                                    '/<br>(?=<\/td>)/');
                    $replace = array('$1', '$1<br>', '$1<br>', '');
                    $html = preg_replace($search, $replace, $html);

                    // remove the "authors" DIV from shared sessions (e.g. poster sessions)
                    $search ='/<div[^>]*class="authors"[^>]*>(.*?)<\/div>\s*/';
                    $html = preg_replace($search, '$1', $html);

                    if ($thead) {
                        $html = preg_replace('/<tbody[^>]*>/', $thead.'$0', $html, 1);
                    }
                }
            }
        }
        return $html;
    }

    /**
     * get_schedule_title
     *
     * @param string $lang
     * @return string schedule title
     * @todo Finish documenting this function
     */
    protected function get_schedule_title($lang='') {
        global $DB;
        $text = '';
        $config = $this->instance->config;
        if ($cmid = $config->publishcmid) {
            $modinfo = get_fast_modinfo($this->course);
            if (array_key_exists($cmid, $modinfo->cms)) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname=='page') {
                    $text = $DB->get_field('page', 'name', array('id' => $cm->instance));
                }
            }
        }
        return $this->remove_multilang_spans($text, $lang);
    }

    /**
     * get_banner_image
     *
     * @return object with properties "src", "width", and "height"
     * @todo Finish documenting this function
     */
    protected function get_banner_image() {
        global $DB;
        if ($section = $DB->get_record('course_sections', array('course' => $this->course->id, 'section' => 0))) {
            if (preg_match_all('/<img[^>]*>/', $section->summary, $images)) {
                $search = '/(alt|height|src|width)="([^"]+)"/';
                foreach ($images as $image) {
                    if (preg_match_all($search, $image[0], $parts)) {

                        // create the $banner image object.
                        $image = (object)array_combine($parts[1], $parts[2]);

                        // Sanity checks on image src.
                        if (empty($image->src)) {
                            continue;
                        }
                        $parts = parse_url($image->src);
                        if (empty($parts) || empty($parts['path'])) {
                            continue;
                        }

                        // Sanity checks on image filename and extension.
                        $parts = pathinfo($parts['path']);
                        if (empty($parts['basename'])) {
                            continue;
                        }
                        if (empty($parts['extension'])) {
                            continue;
                        }

                        // Standardize the dirname.
                        if (empty($parts['dirname']) || $parts['dirname'] == '.') {
                            $parts['dirname'] = '/';
                        } else {
                            $parts['dirname'] = preg_replace('/@@PLUGINFILE@@/', '', $parts['dirname']);
                            $parts['dirname'] = trim($parts['dirname'], '/');
                            if ($parts['dirname'] == '') {
                                $parts['dirname'] = '/';
                            } else {
                                $parts['dirname'] = '/'.$parts['dirname'].'/';
                            }
                        }

                        // Create $filerecord for use with Moodle file API
                        $filerecord = (object)array(
                            'contextid' => $this->course->context->id,
                            'component' => 'course',
                            'filearea'  => 'section',
                            'itemid'    => $section->id,
                            'filepath'  => $parts['dirname'],
                            'filename'  => $parts['basename'],
                            'filetype'  => $parts['extension']
                        );

                        // Convert PLUGINFILE urls within $image->src.
                        $image->src = file_rewrite_pluginfile_urls(
                            $image->src, 'pluginfile.php',
                            $filerecord->contextid,
                            $filerecord->component,
                            $filerecord->filearea,
                            $filerecord->itemid
                        );

                        // Set the "alt" text, if required.
                        if (empty($image->alt)) {
                            $image->alt = $filerecord->filename;
                        }

                        // Locate (or create) this file in the Moodle file system.
                        $fs = get_file_storage();
                        $pathnamehash = $fs->get_pathname_hash($filerecord->contextid,
                                                               $filerecord->component,
                                                               $filerecord->filearea,
                                                               $filerecord->itemid,
                                                               $filerecord->filepath,
                                                               $filerecord->filename);
                        if ($fs->file_exists_by_hash($pathnamehash)) {
                            $image->file = $fs->get_file_by_hash($pathnamehash);
                        } else {
                            // Not a file on this Moodle site - unexpected!!
                            $image->file = $fs->create_file_from_url($filerecord, $image->src, array('skipcertverify' => true), true);
                        }

                        // Extract image's real width and height, if available
                        if ($info = $image->file->get_imageinfo()) {
                            $image->height = $info['height'];
                            $image->width = $info['width'];
                        }

                        // Copy image to a temporary file - could also use "tmpfile()" for this.
                        if ($dirpath = make_temp_directory($this->plugin)) {
                            if ($filepath = tempnam($dirpath, 'bannerimage_'.$filerecord->contextid.'_')) {
                                $image->filepath = $filepath.'.'.$filerecord->filetype;
                                rename($filepath, $image->filepath);
                                $image->file->copy_content_to($image->filepath);
                                return $image;
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * remove_multilang_spans
     *
     * @param string $text
     * @param string $lang
     * @return string schedule title
     * @todo Finish documenting this function
     */
    protected function remove_multilang_spans($text, $lang='') {
        // remove unwanted multilang strings
        $search = '/<span[^>]*class="multilang"[^>]*>(.*?)<\/span>\s*/isu';
        if (preg_match_all($search, $text, $matches, PREG_OFFSET_CAPTURE)) {
            $i_max = count($matches[0]) - 1;
            for ($i=$i_max; $i>=0; $i--) {
                list($match, $start) = $matches[0][$i];
                if (strpos($match, 'lang="'.$lang.'"')) {
                    $replace = $matches[1][$i][0];
                } else {
                    $replace = '';
                }
                $text = substr_replace($text, $replace, $start, strlen($match));
            }
        }
        return $text;
    }

    /**
     * send_file
     *
     * @uses $CFG
     * @param string $content
     * @param string $filetype
     * @return void
     */
    protected function send_file($content, $filetype, $data) {
        $filename = $this->make_filename($filetype, $data);
        send_file($content, $filename, 0, 0, true, true);
        // script will die at end of send_file()
    }

    /**
     * make_filename
     *
     * @param string $filetype
     * @return void
     */
    protected function make_filename($filetype, $data) {
        $config = $this->instance->config;
        if (empty($data->language)) {
            $lang = '';
        } else {
            $lang = $data->language;
        }
        switch (true) {
            case (! empty($config->title)):
                $filename = format_string($config->title, true);
                break;
            case (! empty($config->conferencename)):
                $filename = $config->conferencename;
                break;
            case (! empty($config->{"conferencename$lang"})):
                $filename = $config->{"conferencename$lang"};
                break;
            case (! empty($this->instance->instance->blockname)):
                $filename = $this->instance->instance->blockname;
                break;
            case (! empty($this->plugin)):
                $filename = $this->plugin;
                break;
            default:
                $filename = get_class($this);
        }
        if ($lang) {
            if ($options = $this->get_language_options()) {
                if (count($options)==1 && $lang==key($options)) {
                    // only one display language - do nothing
                } else {
                    $filename .= ".$lang";
                }
            }
        }
        $filename .= ".$filetype";
        $filename = preg_replace('/[ \.]+/', '.', $filename);
        $filename = clean_filename(strip_tags($filename));
        return $filename;
    }
}
