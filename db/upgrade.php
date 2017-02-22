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
 * blocks/maj_submissions/db/upgrade.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// prevent direct access to this script
defined('MOODLE_INTERNAL') || die();

function xmldb_block_maj_submissions_upgrade($oldversion=0) {
    global $CFG, $DB;

    $result = true;

    $newversion = 2017022246;
    if ($oldversion < $newversion) {

        /////////////////////////////////////////////////
        // standardize names of multilang fields
        // to use two-letter language as suffix
        /////////////////////////////////////////////////

        $names = array('conference_name_english',  'conference_name_japanese',
                       'conference_venue_english', 'conference_venue_japanese',
                       'conference_dates_english', 'conference_dates_japanese',
                       'dinner_date_english',      'dinner_date_japanese',
                       'dinner_name_english',      'dinner_name_japanese',
                       'name_japanese_given',      'name_japanese_surname',
                       'name_english_given',       'name_english_surname',
                       'name_english_given_2',     'name_english_surname_2',
                       'name_english_given_3',     'name_english_surname_3',
                       'name_english_given_4',     'name_english_surname_4',
                       'name_english_given_5',     'name_english_surname_5',
                       'name_english_given_6',     'name_english_surname_6',
                       'name_english_given_7',     'name_english_surname_7',
                       'affiliation_english',      'affiliation_japanese',
                       'affiliation_english_2',    'affiliation_japanese_2',
                       'affiliation_english_3',    'affiliation_japanese_3',
                       'affiliation_english_4',    'affiliation_japanese_4',
                       'affiliation_english_5',    'affiliation_japanese_5',
                       'fee_description_english',  'fee_description_japanese',
                       'fee_type_english',         'fee_type_japanese');

        $search = '/(.*)_(en|ja)(glish|panese)(.*)/';
        $replace = '$1$4_$2';

        $names = array_flip($names);
        foreach ($names as $old => $new) {
            $names[$old] = preg_replace($search, $replace, $old);
        }

        $templates = array('singletemplate',
                           'listtemplate', 'listtemplateheader', 'listtemplatefooter',
                           'addtemplate',  'csstemplate',        'jstemplate',
                           'rsstemplate',  'rsstitletemplate',   'asearchtemplate');

        foreach ($names as $old => $new) {
            $params = array('old' => $old,
                            'new' => $new);

            $DB->execute('UPDATE {data_fields} '.
                         'SET name = REPLACE(name, :old, :new) '.
                         'WHERE name IS NOT NULL', $params);

            $DB->execute('UPDATE {data_fields} '.
                         'SET param1 = REPLACE(param1, :old, :new) '.
                         'WHERE param1 IS NOT NULL', $params);

            foreach ($templates as $template) {
                $DB->execute('UPDATE {data} '.
                             'SET '.$template.' = REPLACE('.$template.', :old, :new) '.
                             'WHERE '.$template.' IS NOT NULL', $params);
            }
        }

        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }


    return $result;
}
