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
 * Excel writer abstraction layer.
 *
 * @copyright  (C) 2001-3001 Eloy Lafuente (stronk7) {@link http://contiento.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    core
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define and operate over one Moodle Workbook.
 *
 * This class acts as a wrapper around another library
 * maintaining Moodle functions isolated from underlying code.
 *
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package moodlecore
 */
class block_maj_submissions_ExcelWorkbook extends MoodleExcelWorkbook {

    /**
     * Create one Moodle Worksheet, but notice that we override the base class
     * to use "block_maj_submissions_ExcelWorksheet" instead of "MoodleExcelWorksheet"
     *
     * @param string $name Name of the sheet
     * @return MoodleExcelWorksheet
     */
    public function add_worksheet($name = '') {
        return new block_maj_submissions_ExcelWorksheet($name, $this->objPHPExcel);
    }
}

/**
 * Define and operate over one Worksheet.
 *
 * This class acts as a wrapper around another library
 * maintaining Moodle functions isolated from underlying code.
 *
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   core
 */
class block_maj_submissions_ExcelWorksheet extends MoodleExcelWorksheet {
    
    /**
     * set page options for printing an Excel worksheet
     *
     * @param array $options see "lib/phpexcel/PHPExcel/Worksheet/PageSetup.php"
     * @return void, but will update page setup for this worksheet
     */
    public function setup_page($options) {
        foreach ($options as $name => $value) {
            switch (strtolower($name)) {

                case 'orientation':
                    $this->worksheet->getPageSetup()->setOrientation($value);
                    break;

                case 'scale':
                    $this->worksheet->getPageSetup()->setScale($value);
                    break;

                case 'papersize':
                    $this->worksheet->getPageSetup()->setPaperSize($value);
                    break;

                case 'fittopage':
                    $this->worksheet->getPageSetup()->setFitToPage($value);
                    break;

                case 'fittowidth':
                    $this->worksheet->getPageSetup()->setFitToWidth($value);
                    break;

                case 'fittoheight':
                    $this->worksheet->getPageSetup()->setFitToHeight($value);
                    break;

                case 'columnstorepeatatleft':
                    $this->worksheet->getPageSetup()->setColumnsToRepeatAtLeft($value);
                    break;

                case 'columnstorepeatatleftbystartandend':
                    $this->worksheet->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd($value[0], $value[1]);
                    break;

                case 'setrowstorepeatattop':
                    $this->worksheet->getPageSetup()->setRowsToRepeatAtTop($value);
                    break;

                case 'setrowstorepeatattopbystartandend':
                    $this->worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($value[0], $value[1]);
                    break;

                case 'horizontalcentered':
                    $this->worksheet->getPageSetup()->setHorizontalCentered($value);
                    break;

                case 'verticalcentered':
                    $this->worksheet->getPageSetup()->setVerticalCentered($value);
                    break;

                case 'printarea':
                    $this->worksheet->getPageSetup()->setPrintArea($value[0], $value[1]);
                    break;

                case 'printareabycolumnandrow':
                    $this->worksheet->getPageSetup()->setPrintAreaByColumnAndRow($value[0], $value[1], $value[2], $value[3]);
                    break;

                case 'firstpagenumber':
                    $this->worksheet->getPageSetup()->setFirstPageNumber($value);
                    break;
            }
        }
    }
}
