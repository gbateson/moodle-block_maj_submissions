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

    $newversion = 2017022349;
    if ($oldversion < $newversion) {

        /////////////////////////////////////////////////
        // standardize names of multilang fields
        // to use two-letter language as suffix
        /////////////////////////////////////////////////

        $select = $DB->sql_like('name', ':en').' OR '.$DB->sql_like('name', ':ja');
        $params = array('en' => '%english%', 'ja' => '%japanese%');
        if ($names = $DB->get_records_select_menu('data_fields', $select, $params, 'name', 'id,name')) {
            $names = array_unique($names);
            $names = array_flip($names);
        } else {
            $names = array();
        }

        $search = '/(.*)_(en|ja)(glish|panese)(.*)/';
        $replace = '$1$4_$2';

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

            $params = array('old' => $old,
                            'new' => $new,
                            'type' => 'template');

            $DB->execute('UPDATE {data_fields} '.
                         'SET param1 = REPLACE(param1, :old, :new) '.
                         'WHERE param1 IS NOT NULL '.
                         'AND type = :type', $params);

            $params = array('old' => $old,
                            'new' => $new);

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
