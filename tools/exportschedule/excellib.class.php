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
     * Create a single Moodle Worksheet, but notice that we override the base class
     * to use "block_maj_submissions_ExcelWorksheet" instead of "MoodleExcelWorksheet"
     * because we need the modified methods of "block_maj_submissions_ExcelWorksheet".
     *
     * @param string $name Name of the sheet
     * @return MoodleExcelWorksheet
     */
    public function add_worksheet($name = '') {
        if (isset($this->objspreadsheet)) {
            // Moodle >= 3.8
            $obj = $this->objspreadsheet;
        } else if (isset($this->objPHPExcel)) {
            // Moodle 2.5 - 3.7
            $obj = $this->objPHPExcel;
        } else {
            $obj = null; // shouldn't happen !!
        }
        if (empty($obj)) {
            return false;
        }
        return new block_maj_submissions_ExcelWorksheet($name, $obj);
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
     * @return void, but may update page setup property this worksheet's worksheet
     */
    public function setup_page($options) {
        $ps = $this->worksheet->getPageSetup();
        foreach ($options as $name => $value) {
            switch (strtolower($name)) {

                case 'orientation':
                    $ps->setOrientation($value);
                    break;

                case 'scale':
                    $ps->setScale($value);
                    break;

                case 'papersize':
                    $ps->setPaperSize($value);
                    break;

                case 'fittopage':
                    $ps->setFitToPage($value);
                    break;

                case 'fittowidth':
                    $ps->setFitToWidth($value);
                    break;

                case 'fittoheight':
                    $ps->setFitToHeight($value);
                    break;

                case 'columnstorepeatatleft':
                    $ps->setColumnsToRepeatAtLeft($value);
                    break;

                case 'columnstorepeatatleftbystartandend':
                    $ps->setColumnsToRepeatAtLeftByStartAndEnd($value[0], $value[1]);
                    break;

                case 'setrowstorepeatattop':
                    $ps->setRowsToRepeatAtTop($value);
                    break;

                case 'setrowstorepeatattopbystartandend':
                    $ps->setRowsToRepeatAtTopByStartAndEnd($value[0], $value[1]);
                    break;

                case 'horizontalcentered':
                    $ps->setHorizontalCentered($value);
                    break;

                case 'verticalcentered':
                    $ps->setVerticalCentered($value);
                    break;

                case 'printarea':
                    $ps->setPrintArea($value[0], $value[1]);
                    break;

                case 'printareabycolumnandrow':
                    $ps->setPrintAreaByColumnAndRow($value[0], $value[1], $value[2], $value[3]);
                    break;

                case 'firstpagenumber':
                    $ps->setFirstPageNumber($value);
                    break;
            }
        }
    }

    /**
     * set options for default row/column dimension
     *
     * @param array $options see "lib/phpexcel/PHPExcel/Worksheet/RowDimension.php"
     * @return void, but may update page setup property this worksheet's worksheet
     */
    public function set_default_dimension($options) {
        foreach ($options as $name => $value) {
            switch (strtolower($name)) {

                case 'rowheight':
                    $this->worksheet->getDefaultRowDimension()->setRowHeight($value);
                    break;

                case 'columnwidth':
                    $this->worksheet->getDefaultColumnDimension()->setWidth($value);
                    break;

                case 'columnautosize':
                    $this->worksheet->getDefaultColumnDimension()->setAutoSize($value);
                    break;
            }
        }
    }

   /**
    * Insert an image in a worksheet.
    *
    * The standard version of this method has a bug:
    * It tries to use "getWidth()" to try to set the width !! 
    * So this method is the same as the standard method but it uses "setWidth()" ;-)
    *
    * @param integer $row     The row we are going to insert the bitmap into
    * @param integer $col     The column we are going to insert the bitmap into
    * @param string  $bitmap  The bitmap filename
    * @param integer $x       The horizontal position (offset) of the image inside the cell.
    * @param integer $y       The vertical position (offset) of the image inside the cell.
    * @param integer $scale_x The horizontal scale
    * @param integer $scale_y The vertical scale
    */
    public function insert_bitmap($row, $col, $bitmap, $x = 0, $y = 0, $scale_x = 1, $scale_y = 1) {
        if (class_exists('\PhpOffice\PhpSpreadsheet\Worksheet\Drawing')) {
            // Moodle >= 3.8
            // For PhpSpreadsheet library, the column indexes start on 1
            $objDrawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $col += 1;
        } else if (class_exists('PHPExcel_Worksheet_Drawing')) {
            // Moodle 2.5 - 3.7
            $objDrawing = new PHPExcel_Worksheet_Drawing();
        } else {
            $objDrawing = null; // shouldn't happen !!
        }
        $objDrawing->setPath($bitmap);
        if (class_exists('\PhpOffice\PhpSpreadsheet\Cell\Coordinate')) {
            $coords = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . ($row+1);
        } else {
            $coords = PHPExcel_Cell::stringFromColumnIndex($col) . ($row+1);
        }
        $objDrawing->setCoordinates($coords);
        $objDrawing->setOffsetX($x);
        $objDrawing->setOffsetY($y);
        $objDrawing->setWorksheet($this->worksheet);
        if ($scale_x != 1) {
            $objDrawing->setResizeProportional(false);
            $objDrawing->setWidth($objDrawing->getWidth() * $scale_x);
        }
        if ($scale_y != 1) {
            $objDrawing->setResizeProportional(false);
            $objDrawing->setHeight($objDrawing->getHeight() * $scale_y);
        }
    }

    /**
     * Insert one or more rows before the specified row.
     *
     * @param int $row        Insert before this row (optional, default=1)
     * @param int $numrows    Number of rows to insert (optional, default=1)
     * @return void, but may update this worksheet's worksheet
     */
    public function insert_rows($row=1, $numrows=1) {
        $this->worksheet->insertNewRowBefore($row, $numrows);
    }
}

class block_maj_submissions_ExcelFormat extends MoodleExcelFormat {
    /**
     * Constructs one Moodle Format.
     * Override standard constructor to add more default formats
     *
     * @param array $properties
     */
    public function __construct($properties = array()) {
        // set default format properties, according to which classes are available.
        switch (true) {
            case class_exists('\PhpOffice\PhpSpreadsheet\Style\Border'):
                // Moodle >= 3.8
                $alignment = array('wrapText' => true);
                $border = \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;
                $border = array('borderStyle' => $border);
                break;
            case class_exists('PHPExcel_Style_Border'):
                // Moodle 2.5 - 3.7
                $alignment = array('wrap' => true);
                $border = PHPExcel_Style_Border::BORDER_THIN;
                $border = array('style' => $border);
                break;
            default:
                $alignment = array('wrap' => true);
                $border = array('style' => 'thin');
        }
        $this->format['alignment'] = $alignment;
        $this->format['borders'] = array('top' => $border, 'bottom' => $border, 'left' => $border, 'right' => $border);
        //$this->format['fill'] = array();
        //$this->format['numberformat'] = array();
        parent::__construct($properties);
    }
}