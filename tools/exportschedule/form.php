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

        $this->add_action_buttons(true, get_string('export', 'grades'));
    }

    protected function get_language_options() {

        // get list of all langs
        //$langs = get_string_manager()->get_list_of_languages();
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
                     'html' => get_string('filehtml', $plugin));
                     //'pdf' => get_string('filepdf', $plugin)
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
        $this->send_file($lines, 'csv');
        // script will die at end of send_file()
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
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        require_once($CFG->dirroot.'/lib/phpexcel/PHPExcel/IComparable.php');
        require_once($CFG->dirroot.'/lib/phpexcel/PHPExcel/RichText.php');
        require_once($CFG->dirroot.'/blocks/maj_submissions/tools/exportschedule/excellib.class.php');

        $msg = array();

        $html = $this->get_schedule($lang);

        $workbook = null;
        $worksheet = null;
        $formats = null;

        $search = (object)array(
            'days' => '/(<tbody[^>]*>)(.*?)<\/tbody>/isu',
            'rows' => '/(<tr[^>]*>)(.*?)<\/tr>/isu',
            'cells' => '/(<t[hd][^>]*>)(.*?)<\/t[hd]>/isu',
            'attributes' => '/(\w+)="([^"]*)"/isu',
            'cellspans' => '/(colspan|rowspan)="(\d+)"/isu',
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
            'workshop' => '/\bworkshop\b/',
            'lightning' => '/\blightning\b/',
            'presentation' => '/\bpresentation\b/',
            // cell content
            'eventdetails' => '/<br>.*$/isu'
        );

        $formats = (object)array(
            'date'         => ['h_align' => 'left',   'v_align' => 'center', 'size' => 18],
            'event'        => ['h_align' => 'left',   'v_align' => 'center', 'size' => 14],
            'roomheading'  => ['h_align' => 'center', 'v_align' => 'center', 'size' => 14, 'bg_color' => '#eeddee'], // purple
            'timeheading'  => ['h_align' => 'center', 'v_align' => 'top',    'size' => 12, 'bg_color' => '#ffffff'], // white
            'presentation' => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#fffcf6'], // yellow
            'lightning'    => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#f8ffff'], // blue
            'workshop'     => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#fff8ff'], // purple
            'keynote'      => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#f9f2ec'], // brown
            'default'      => ['h_align' => 'left',   'v_align' => 'top',    'size' => 10, 'bg_color' => '#ffffff']
        );

        $colwidth = (object)array('timeheading' => 15,
                                  'default' => 25);

        $rowheight = (object)array('date' => 32,
                                   'event' => 34,
                                   'multiroom' => 45,
                                   'roomheadings' => 40,
                                   'default' => 80);

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

                        if (preg_match_all($search->attributes, $rows[1][$r], $attributes)) {

                            $countattributes = count($attributes[0]);
                            for ($a=0; $a<$countattributes; $a++) {

                                switch ($attributes[1][$a]) {
                                    case 'class': $rowclass = trim($attributes[2][$a]); break;
                                    // ignore anything else
                                }
                            }
                        }

                        if (preg_match_all($search->cells, $rows[2][$r], $cells)) {

                            $is_date = preg_match($search->date, $rowclass);
                            $is_roomheadings = preg_match($search->roomheadings, $rowclass);
                            $is_multiroom = false;
                            $is_event = false;

                            $countcells = count($cells[0]);
                            if ($lastcol < $countcells) {
                                $lastcol = $countcells;
                            }

                            if (empty($offset[0])) {
                                $offset[0] = array_fill(0, $lastcol, 0);
                            }

                            // Process rowspan/colspan cells in the current row.
                            // We need to do this from from right to left (i.e. $c--)
                            // so that the $offset values are correctly calculated.
                            for ($c=($countcells-1); $c>=0; $c--) {

                                $colspan = 1;
                                $rowspan = 1;

                                if (preg_match_all($search->cellspans, $cells[1][$c], $cellspans)) {

                                    $countcellspans = count($cellspans[0]);
                                    for ($a=0; $a<$countcellspans; $a++) {

                                        switch ($cellspans[1][$a]) {
                                            case 'colspan': $colspan = intval($cellspans[2][$a]); break;
                                            case 'rowspan': $rowspan = intval($cellspans[2][$a]); break;
                                        }
                                    }
                                }

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

                                $cellclass = '';
                                $colspan = 1;
                                $rowspan = 1;

                                if (preg_match_all($search->attributes, $cells[1][$c], $attributes)) {

                                    $countattributes = count($attributes[0]);
                                    for ($a=0; $a<$countattributes; $a++) {

                                        switch ($attributes[1][$a]) {
                                            case 'class': $cellclass =  trim($attributes[2][$a]); break;
                                            case 'colspan': $colspan = intval($attributes[2][$a]); break;
                                            case 'rowspan': $rowspan = intval($attributes[2][$a]); break;
                                            // ignore "id", "style" and anything else
                                        }
                                    }
                                }

                                if (preg_match($search->multiroom, $cellclass)) {
                                    $is_multiroom = true;
                                }

                                if (preg_match($search->event, $cellclass)) {
                                    $is_event = true;
                                }

                                if ($workbook===null) {
                                    $filename = $this->make_filename('xlsx');
                                    $workbook = new block_maj_submissions_ExcelWorkbook($filename);
                                    $workbook->send($filename);
                                    foreach ($formats as $f => $format) {
                                        $format = $workbook->add_format($format);
                                        $format->set_text_wrap();
                                        $format->set_border(1); // 1=thin, 2=thick
                                        $formats->$f = $format;
                                    }
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
                                        'Orientation' => PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE,
                                        'PaperSize'   => PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4,
                                        'FitToPage'   => true,
                                        'FitToHeight' => 1,
                                        'FitToWidth'  => 1,
                                    ));
                                }

                                $text = html_entity_decode($cells[2][$c]);
                                if ($is_date && ! empty($this->instance->config->title)) {
                                    $text = $this->instance->config->title.': '.$text;
                                } else if ($is_event) {
                                    $text = preg_replace($search->eventdetails, '', $text);
                                }
                                $text = str_replace('<br>', chr(10), $text);

                                // set format for this cell
                                switch (true) {

                                    case $is_date:
                                        $format = $formats->date;
                                        break;

                                    case $is_event:
                                        $format = $formats->event;
                                        break;

                                    case preg_match($search->timeheading, $cellclass):
                                        $format = $formats->timeheading;
                                        break;

                                    case preg_match($search->roomheading, $cellclass):
                                        $format = $formats->roomheading;
                                        break;

                                    case preg_match($search->presentation, $cellclass):
                                        $format = $formats->presentation;
                                        break;

                                    case preg_match($search->lightning, $cellclass):
                                        $format = $formats->lightning;
                                        break;

                                    case preg_match($search->workshop, $cellclass):
                                        $format = $formats->workshop;
                                        break;

                                    case preg_match($search->keynote, $cellclass):
                                        $format = $formats->keynote;
                                        break;

                                    default:
                                        $format = $formats->default;
                                }

                                // $i(ndex) on chars in $text string
                                $i = 0;

                                // convert <big>, <small>, <b>, <i> and <u>, to richtext
                                if (preg_match_all($search->richtext, $text, $strings, PREG_OFFSET_CAPTURE)) {

                                    $richtext = new PHPExcel_RichText();

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

                            } // end loop through cells

                            if ($countcells) {
                                switch (true) {

                                    case $is_date:
                                        $height = $rowheight->date;
                                        break;

                                    case $is_event:
                                        $height = $rowheight->event;
                                        break;

                                    case $is_roomheadings:
                                        $height = $rowheight->roomheadings;
                                        break;

                                    case $is_multiroom:
                                        $height = $rowheight->multiroom;
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

                    } // end loop through rows

                    if ($lastcol) {
                        $worksheet->set_column(0, 0, $colwidth->timeheading);
                        $worksheet->set_column(1, $lastcol-1, $colwidth->default);
                    }
                }
            }
        }

        if ($workbook) {
            $workbook->close();
            die;
        }

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
        if ($html = $this->get_schedule_html($lang)) {
            $filename = $this->make_filename('html');
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
     * @return array $msg
     * @todo Finish documenting this function
     */
    public function export_pdf($lang) {
        global $CFG;
        require_once($CFG->libdir.'/pdflib.php');

        if ($html = $this->get_schedule($lang)) {

            $doc = new pdf('L'); // landscape orientation

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

            $filename = $this->make_filename('pdf');
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
    protected function get_schedule_html($lang) {
        global $CFG;
        if ($html = $this->get_schedule($lang)) {
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
            $html = html_writer::tag('head', $style).
                    html_writer::tag('body', $html);
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
    protected function get_schedule($lang) {
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

                    // remove embedded SCRIPT and STYLE tags and THEAD
                    $search = array('/<script[^>]*>.*<\/script>\s*/isu',
                                    '/<style[^>]*>.*<\/style>\s*/isu',
                                    '/<thead[^>]*>.*<\/thead>\s*/isu');
                    $html = preg_replace($search, '', $html);

                    // remove white space around HTML block elements
                    $search = '/\s*(<\/?(table|thead|tbody|tr|th|td|div)[^>]*>)\s*/s';
                    $html = preg_replace($search, '$1', $html);

                    // remove unwanted multilang strings
                    $search = '/<span[^>]*class="multilang"[^>]*>(.*?)<\/span>\s*/isu';
                    if (preg_match_all($search, $html, $matches, PREG_OFFSET_CAPTURE)) {
                        $i_max = count($matches[0]) - 1;
                        for ($i=$i_max; $i>=0; $i--) {
                            list($match, $start) = $matches[0][$i];
                            if (strpos($match, 'lang="'.$lang.'"')) {
                                $replace = $matches[1][$i][0];
                            } else {
                                $replace = '';
                            }
                            $html = substr_replace($html, $replace, $start, strlen($match));
                        }
                    }

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
                }
            }
        }
        return $html;
    }

    /**
     * send_file
     *
     * @uses $CFG
     * @param string $content
     * @param string $filetype
     * @return void
     */
    protected function send_file($content, $filetype) {
        $filename = $this->make_filename($filetype);
        send_file($content, $filename, 0, 0, true, true);
        // script will die at end of send_file()
    }

    /**
     * make_filename
     *
     * @param string $filetype
     * @return void
     */
    protected function make_filename($filetype) {
        $config = $this->instance->config;
        if (empty($config->title)) {
            $filename = '';
        } else {
            $filename = format_string($config->title, true);
        }
        if ($filename=='') {
            $filename = $this->instance->blockname;
        }
        $filename .= ".$filetype";
        $filename = preg_replace('/[ \.]+/', '.', $filename);
        $filename = clean_filename(strip_tags($filename));
        return $filename;
    }
}
