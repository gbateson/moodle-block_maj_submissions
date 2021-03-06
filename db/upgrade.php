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

    $dbman = $DB->get_manager();

    $newversion = 2017022350;
    if ($oldversion < $newversion) {

        /////////////////////////////////////////////////
        // standardize names of multilang fields
        // to use two-letter language as suffix
        /////////////////////////////////////////////////

        $select = $DB->sql_like('name', '?').' OR '.$DB->sql_like('name', '?');
        $params = array('%english%',  '%japanese%');
        if ($names = $DB->get_records_select_menu('data_fields', $select, $params, 'name DESC', 'id,name')) {
            $names = array_unique($names);
            $names = array_flip($names);
            krsort($names);
        } else {
            $names = array();
        }

        $search = '/^(.*)_(en|ja)(glish|panese)(.*)$/';
        $replace = '$1$4_$2';

        foreach ($names as $old => $new) {
            $names[$old] = preg_replace($search, $replace, $old);
        }

        $templates = block_maj_submissions_upgrade_templates();

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

    $newversion = 2017022352;
    if ($oldversion < $newversion) {

        /////////////////////////////////////////////////
        // standardize names of events and phrases
        /////////////////////////////////////////////////

        $names = array(
            // events
            'conference' => 'conference',
            'workshops'  => 'workshops',
            'reception'  => 'reception',
            // phases
            'collect'           => 'collectpresentations',
            'collectworkshop'   => 'collectworkshops',
            'collectsponsored'  => 'collectsponsoreds',
            'review'            => 'review',
            'revise'            => 'revise',
            'publish'           => 'publish',
            'register'          => 'registerdelegates',
            'registerpresenter' => 'registerpresenters',
        );

        block_maj_submissions_upgrade_property_names($names);
        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    $newversion = 2017042068;
    if ($oldversion < $newversion) {
        block_maj_submissions_upgrade_property_names();
        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    $newversion = 2017121842;
    if ($oldversion < $newversion) {
        block_maj_submissions_upgrade_multilang();
        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    $newversion = 2018010851;
    if ($oldversion < $newversion) {

        /////////////////////////////////////////////////
        // add table to storeattendance detalis
        /////////////////////////////////////////////////

        // Define table block_maj_submissions to be created.
        $table = new xmldb_table('block_maj_submissions');

        // Adding fields to table block_maj_submissions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recordid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_maj_submissions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('blocconf_ins_ix', XMLDB_KEY_FOREIGN, array('instanceid'), 'block_instances', array('id'));
        $table->add_key('blocconf_rec_ix', XMLDB_KEY_FOREIGN, array('recordid'), 'data_records', array('id'));
        $table->add_key('blocconf_use_ix', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        // Adding indexes to table block_maj_submissions.
        $table->add_index('blocconf_insuse_ix', XMLDB_INDEX_NOTUNIQUE, array('instanceid', 'userid'));
        $table->add_index('blocconf_insrecuse_ix', XMLDB_INDEX_UNIQUE, array('instanceid', 'recordid', 'userid'));

        // Conditionally launch create table for block_maj_submissions.
        if (! $dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    $newversion = 2018011767;
    if ($oldversion < $newversion) {
        block_maj_submissions_upgrade_multiroom();
        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    $newversion = 2018052891;
    if ($oldversion < $newversion) {
        $fieldnames = array('presentation_topics' => 'presentation_topic');
        block_maj_submissions_upgrade_fieldnames($fieldnames);
        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    $newversion = 2018060796;
    if ($oldversion < $newversion) {
        $fieldnames = array('presentation_topic');
        block_maj_submissions_upgrade_checkboxes($fieldnames);
        upgrade_block_savepoint($result, "$newversion", 'maj_submissions');
    }

    return $result;
}

function block_maj_submissions_upgrade_property_names($names=null) {
    global $DB;

    if ($names===null) {
        $names = array();
    }

    $block = new block_maj_submissions();
    $block->specialization(); // setup $block->config

    if ($instances = $DB->get_records('block_instances', array('blockname' => 'maj_submissions'))) {
        foreach ($instances as $instance) {

            if (empty($instance->configdata)) {
                continue;
            }

            $instance->config = unserialize(base64_decode($instance->configdata));

            if (empty($instance->config)) {
                continue;
            }

            if (isset($instance->config->displaylangs)) {
                $langs = $instance->config->displaylangs;
                $langs = explode(',', $langs);
                $langs = array_map('trim', $langs);
                $langs = array_filter($langs);
            } else {
                $langs = get_string_manager()->get_list_of_translations();
                $langs = array_keys($langs);
            }

            $oldnames = get_object_vars($instance->config);
            foreach ($oldnames as $oldname => $value) {
                $prefix = '';
                $suffix = '';
                $basename = $oldname;

                // $basename is $oldname without trailing lang code
                foreach ($langs as $lang) {
                    $len = strlen($lang);
                    if (substr($basename, -$len)==$lang) {
                        $suffix = $lang;
                        $basename = substr($basename, 0, -$len);
                        break; // stop foreach loop
                    }
                }

                // determine the prefix and suffix for this $basename
                if (substr($basename, -4)=='cmid') {
                    $suffix = 'cmid'.$suffix;
                    $prefix = substr($basename, 0, -4);
                } else if (substr($basename, -5)=='cmids') {
                    $suffix = 'cmids'.$suffix;
                    $prefix = substr($basename, 0, -5);
                } else if (substr($basename, -9)=='timestart') {
                    $suffix = 'timestart'.$suffix;
                    $prefix = substr($basename, 0, -9);
                } else if (substr($basename, -10)=='timefinish') {
                    $suffix = 'timefinish'.$suffix;
                    $prefix = substr($basename, 0, -10);
                }

                // unset the property if it is no longer used
                if (array_key_exists($prefix, $names)) {
                    $newname = $names[$prefix].$suffix;
                    if ($newname==$oldname) {
                        // do nothing
                    } else {
                        unset($instance->config->$oldname);
                        $instance->config->$newname = $value;
                    }
                } else if (property_exists($block->config, $basename)) {
                    // do nothing
                } else {
                    unset($instance->config->$oldname);
                }
            }

            $instance->configdata = base64_encode(serialize($instance->config));
            $DB->set_field('block_instances', 'configdata', $instance->configdata, array('id' => $instance->id));
        }
    }
}

function block_maj_submissions_upgrade_multiroom() {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/blocks/maj_submissions/block_maj_submissions.php');

    $blockname = 'maj_submissions';
    $plugin = "block_$blockname";

    $oldtext = '';
    $oldmenu = array();

    $newtext = '';
    $newmenu = array();

    list($dataids, $pageids) = block_maj_submissions_upgrade_cmids();

    $select = 'blockname = ? AND configdata IS NOT NULL AND configdata != ?';
    $params = array($blockname, '');
    if ($instance = $DB->get_records_select('block_instances', $select, $params)) {
        $instance = reset($instance); // just the first block instance
        $instance = block_instance($blockname, $instance);
        $instance->set_multilang(true);
        $newtext = $instance->get_string('schedule_roomtype', $plugin);
        $newmenu[] = $instance->get_string('normalroom', $plugin);
        $newmenu[] = $instance->get_string('largeroom', $plugin);
        $newmenu[] = $instance->get_string('multiplerooms', $plugin);
    } else {
        $newtext = get_string('schedule_roomtype', $plugin);
        $newmenu[] = get_string('normalroom', $plugin);
        $newmenu[] = get_string('largeroom', $plugin);
        $newmenu[] = get_string('multiplerooms', $plugin);
    }

    foreach ($pageids as $id) {
        if ($content = $DB->get_field('page', 'content', array('id' => $id))) {
            $content = str_replace('allrooms', 'multiroom', $content);
            $DB->set_field('page', 'content', $content, array('id' => $id));
        }
    }

    $templates = block_maj_submissions_upgrade_templates();

    $oldname = 'schedule_audience';
    $newname = 'schedule_roomtype';

    foreach ($dataids as $id) {
        if ($data = $DB->get_record('data', array('id' => $id))) {
            $params = array('dataid' => $id, 'name' => $oldname);
            if ($field = $DB->get_record('data_fields', $params)) {

                $field->name = $newname;
                $oldtext = $field->description;
                $field->description = $newtext;

                if ($field->type=='menu' || ($field->type=='admin' && $field->param10=='menu')) {
                    $oldmenu = $field->param1;
                    $field->param1 = implode("\n", $newmenu);
                    $oldmenu = preg_split('/[\r\n]+/', $oldmenu);
                    $oldmenu = array_filter($oldmenu);
                } else {
                    $oldmenu = array();
                }
                $DB->update_record('data_fields', $field);

                foreach ($templates as $template) {
                    $data->$template = str_replace($oldname, $newname, $data->$template);
                    $data->$template = str_replace($oldtext, $newtext, $data->$template);
                }
                $DB->update_record('data', $data);

                $content = array();

                $oldvalue = reset($oldmenu);
                $newvalue = reset($oldmenu);
                if ($oldvalue && $newvalue) {
                    array_push($content, '(WHEN content = ? THEN ?)');
                    array_push($params, $oldvalue, $newvalue);
                }

                $oldvalue = end($oldmenu);
                $newvalue = end($oldmenu);
                if ($oldvalue && $newvalue) {
                    array_push($content, '(WHEN content = ? THEN ?)');
                    array_push($params, $oldvalue, $newvalue);
                }

                if ($content = implode(' ', $content)) {
                    $content = "(CASE $content ELSE ? END)";
                } else {
                    $content = '?';
                }
                $params[] = '';

                // the following line is commented out because it caused a fatal MySQL error :-(
                // Error: Truncated incorrect DOUBLE value
                 //$DB->execute('UPDATE {data_content} SET content = '.$content.' WHERE fieldid = ?', $params);
            }
        }
    }
}

function block_maj_submissions_upgrade_multilang() {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');

    list($dataids, $pageids) = block_maj_submissions_upgrade_cmids();

    if (empty($dataids)) {
        return;
    }
    // cache search/replace strings for templates
    $templatesearchreplace = array('"multilang ' => '"',
                                   ' multilang"' => '"');

    // cache names of template fields in a data record
    $templates = block_maj_submissions_upgrade_templates();

    // cache names of field types with fixed values params
    $fixedvaluetypes = array('checkbox',
                             'menu',
                             'multimenu',
                             'radiobutton');

    // cache names of field types with multiline content
    $multilinetypes = array('checkbox',
                            'multimenu');

    // cache names of fields containing a money value
    $moneyfields = array('membership_fees',
                         'conference_fees',
                         'dinner_attend',
                         'dinner_attend_2',
                         'dinner_attend_3',
                         'dinner_attend_4',
                         'dinner_attend_5');

    // cache search string to match newlines before closing </span>
    $newlinesearch = array('/[\r\n]+(?=<\/span>)/s', '/[\r\n]+/s');
    $newlinereplace = array('', "\n");

    // cache special search/replace strings for presentation_topics
    // because these include some low-ascii chars within the Japanese
    $topicsearch = '\\x{0000}-\\x{007F}'; // low-ascii chars
    $topicsearch = '/^(.*?[^'.$topicsearch.']) (['.$topicsearch.']+)$/mu';
    $topicreplace = '<span class="multilang" lang="en">$2</span>'.
                    '<span class="multilang" lang="ja">$1</span>';

    // these fields will be converted to multilang SPANs
    $params = array('description', 'param1',
                         'param2', 'param3',
                         'param4', 'param5');

    $courseid = 0;
    foreach ($dataids as $dataid) {

        if (! $fields = $DB->get_records('data_fields', array('dataid' => $dataid))) {
            continue; // unexpected !!
        }

        if (! $data = $DB->get_record('data', array('id' => $dataid))) {
            continue; // shouldn't happen !!
        }

        // Get course context id.
        if ($courseid && $courseid == $data->course) {
            // do nothing
        } else {

            // Unset courseid.
            $courseid = 0;

            // Get context id for this course.
            if (! $contextid = $DB->get_field('context', 'id', array('contextlevel' => CONTEXT_COURSE, 'instanceid' => $data->course))) {
                continue; // shouldn't happen !!
            }

            // Get config data for "maj_submissions" block in this course.
            if (! $config = $DB->get_field('block_instances', 'configdata', array('blockname' => 'maj_submissions', 'parentcontextid' => $contextid))) {
                continue; // shouldn't happen !!
            }

            // Get $config object for the "maj_submissions" block in this course.
            $config = unserialize(base64_decode($config));
            if (empty($config)) {
                continue; // shouldn't happen !!
            }

            // Cache courseid
            $courseid == $data->course;
        }

        // Fix multilang fields in each template.
        foreach ($templates as $template) {
            if (empty($data->$template)) {
                continue;
            }
            $text = strtr($data->$template, $templatesearchreplace);
            if ($template=='addtemplate') {
                $name = 'fixmultilangvalues';
                if (! $DB->record_exists('data_fields', array('dataid' => $dataid, 'name' => $name))) {
                    $field = new stdClass();
                    $field->dataid = $dataid;
                    $field->name = $name;
                    $field->type = 'admin';
                    $field->description = '<span class="multilang" lang="en">Fix multilang values</span><span class="multilang" lang="ja">多言語値修正</span>';
                    $field->param10 = 'number';
                    $DB->insert_record('data_fields', $field);
                }
                $pos = strpos($text, '[['.$name.']]');
                if ($pos===false) {
                    $search = '[[setdefaultvalues]]';
                    $insert = "\n\n";
                    $insert .= '<!-- =============================================='."\n";
                    $insert .= ' The "fixmultilangvalues" field is required to fix'."\n";
                    $insert .= ' multilang values in "menu", "radio" and "text" fields.'."\n";
                    $insert .= '=============================================== -->'."\n";
                    $insert .= '[['.$name.']]';
                    $pos = strpos($text, $search);
                    if ($pos===false) {
                        $pos = strlen($text);
                    } else {
                        $pos += strlen($search);
                    }
                    $text = substr_replace($text, $insert, $pos, 0);
                }
            }
            if (strcmp($text, $data->$template)) {
                $DB->set_field('data', $template, $text, array('id' => $dataid));
            }
        }
        foreach ($fields as $fieldid => $field) {

            $name = $field->name;
            if ($field->type=='admin') {
                $type = $field->param10;
            } else {
                $type = $field->type;
            }

            $is_money_field = in_array($name, $moneyfields);
            $is_topics_field = ($name=='presentation_topics');
            $is_fixedvalue = in_array($type, $fixedvaluetypes);
            $is_multiline = in_array($type, $multilinetypes);

            foreach ($params as $param) {
                $is_topics_param = ($is_topics_field && $param=='param1');
                block_maj_submissions_upgrade_multilang_text(
                               'data_fields', $field, $param,
                                              $config, false,
                           $is_money_field, $is_topics_param,
                                 $topicsearch, $topicreplace,
                             $newlinesearch, $newlinereplace);
            }

            if ($is_fixedvalue) {
                $contents = $DB->get_records('data_content', array('fieldid' => $fieldid));
            } else {
                $contents = false;
            }
            if ($contents===false) {
                $contents = array();
            }

            foreach ($contents as $contentid => $content) {
                block_maj_submissions_upgrade_multilang_text(
                         'data_content', $content, 'content',
                                      $config, $is_multiline,
                           $is_money_field, $is_topics_field,
                                 $topicsearch, $topicreplace,
                             $newlinesearch, $newlinereplace);
            }
        }
    }
}

function block_maj_submissions_upgrade_multilang_text($table, $record, $field, $config, $is_multiline, $is_money, $is_topics, $topicsearch, $topicreplace, $newlinesearch, $newlinereplace) {
    global $DB;
    if (empty($record->$field)) {
        return '';
    }
    $text = $record->$field;
    if (substr($text, 0, 1)=='<') {
        return $text;
    }
    if (substr($text, 0, 2)=='[[') {
        return $text;
    }
    if ($is_multiline) {
        $text = str_replace('##', "\n", $text);
    }
    if ($is_topics) {
        $text = preg_replace($topicsearch, $topicreplace, $text);
    } else if ($is_money) {
        $search = '/^(.*?)( *\(.*?\))$/';
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines);
        foreach ($lines as $i => $line) {
            if (preg_match($search, $line, $amount)) {
                $line = $amount[1];
                $amount = $amount[2];
            } else {
                $amount = '';
            }
            $line = block_maj_submissions_tool_form::convert_to_multilang($line, $config);
            $lines[$i] = $line.$amount;
        }
        $text = implode("\n", $lines);
    } else {
        $text = block_maj_submissions_tool_form::convert_to_multilang($text, $config);
    }
    $text = preg_replace($newlinesearch, $newlinereplace, $text);
    if ($is_multiline) {
        $text = str_replace("\n", '##', $text);
    }
    if ($text==$record->$field) {
        return false;
    } else {
        $DB->set_field($table, $field, $text, array('id' => $record->id));
        return true;
    }
}

function block_maj_submissions_upgrade_fieldnames($fieldnames) {
    global $DB;

    $blockname = 'maj_submissions';
    $plugin = "block_$blockname";

    list($dataids, $pageids) = block_maj_submissions_upgrade_cmids();

    $templates = block_maj_submissions_upgrade_templates();

    foreach ($dataids as $id) {
        if ($data = $DB->get_record('data', array('id' => $id))) {
            foreach ($fieldnames as $oldname => $newname) {
                $params = array('dataid' => $id, 'name' => $oldname);
                if ($field = $DB->get_record('data_fields', $params)) {

                    $field->name = $newname;
                    $field->description = get_string($newname, $plugin);
                    $DB->update_record('data_fields', $field);

                    $search = "/\\b(id_)?$oldname\\b/";
                    foreach ($templates as $template) {
                        $data->$template = preg_replace($search, '$1'.$newname, $data->$template);
                    }
                }
            }
            $DB->update_record('data', $data);
        }
    }
}

function block_maj_submissions_upgrade_checkboxes($fieldnames) {
    global $DB;

    list($dataids, $pageids) = block_maj_submissions_upgrade_cmids();

    foreach ($dataids as $dataid) {
        foreach ($fieldnames as $name) {
            $select = 'dataid = ? AND name = ? AND (type = ? OR (type = ? AND param10 = ?))';
            $params = array($dataid, $name, 'checkbox', 'admin', 'checkbox');
            $field = $DB->get_record_select('data_fields', $select, $params);
            if (empty($field)) {
                continue;
            }
            if ($field->type=='admin') {
                $type = 'param10';
            } else {
                $type = 'type';
            }
            $DB->set_field('data_fields', $type, 'radiobutton', array('id' => $field->id));
            $search = 'fieldid = ? AND '.$DB->sql_like('content', '?');
            $params = array($field->id, '%##%');
            if ($contents = $DB->get_records('data_content', array('fieldid' => $field->id))) {
                foreach ($contents as $id => $content) {
                    $content = explode('##', $content->content);
                    $content = array_filter($content);
                    $content = preg_grep('/^\d+$/', $content,  PREG_GREP_INVERT); // remove '0'
                    $content = reset($content); // first non-blank item
                    $DB->set_field('data_content', 'content', $content, array('id' => $id));
                }
            }
        }
    }
}

/**
 * return data-ids and page-ids used by maj_submissions blocks on this Moodle site
 */
function block_maj_submissions_upgrade_cmids() {
    global $DB;

    static $dataids = null;
    static $pageids = null;

    if ($dataids===null || $pageids===null) {

        $dataids = array();
        $pageids = array();

        if ($instances = $DB->get_records('block_instances', array('blockname' => 'maj_submissions'))) {

            // cache id of "data" and "page" modules
            $datamoduleid = $DB->get_field('modules', 'id', array('name' => 'data'));
            $pagemoduleid = $DB->get_field('modules', 'id', array('name' => 'page'));

            foreach ($instances as $instance) {

                if (empty($instance->configdata)) {
                    continue;
                }

                $config = unserialize(base64_decode($instance->configdata));

                if (empty($config)) {
                    continue;
                }

                $names = array_keys(get_object_vars($config));
                $names = preg_grep('/^(collect|publish|register).*cmid$/', $names);
                // we expect the following settings:
                // - collect(presentations|sponsoreds|workshops)cmid
                // - register(delegates|presenters)cmid
                // - publishcmid (the schedule)

                foreach ($names as $name) {
                    if (empty($config->$name)) {
                        continue;
                    }
                    if (substr($name, 0, 7)=='publish') {
                        $params = array('id' => $config->$name, 'module' => $pagemoduleid);
                        $pageids[] = $DB->get_field('course_modules', 'instance', $params);
                    } else {
                        $params = array('id' => $config->$name, 'module' => $datamoduleid);
                        $dataids[] = $DB->get_field('course_modules', 'instance', $params);
                    }
                }
            }

            $dataids = array_filter($dataids);
            $dataids = array_unique($dataids);
            sort($dataids);

            $pageids = array_filter($pageids);
            $pageids = array_unique($pageids);
            sort($pageids);
        }
    }

    return array($dataids, $pageids);
}

/**
 * return an array of templates fieldnames used by mod_data
 */
function block_maj_submissions_upgrade_templates() {
    return array('listtemplate', 'listtemplateheader', 'listtemplatefooter',
                 'singletemplate',
                 'asearchtemplate',
                 'addtemplate',
                 'rsstemplate', 'rsstitletemplate',
                 'csstemplate',
                 'jstemplate');
}