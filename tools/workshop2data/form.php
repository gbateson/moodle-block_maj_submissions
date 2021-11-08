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
 * blocks/maj_submissions/block_maj_submissions.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// disable direct access to this script
defined('MOODLE_INTERNAL') || die();

// get required files
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.php');

/**
 * block_maj_submissions_tool_workshop2data
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_workshop2data extends block_maj_submissions_tool_form {

    const NOT_GRADED = -1;

    protected $type = '';
    protected $modulename = 'data';
    protected $defaultvalues = array(
        'intro'           => '', // see set_defaultintro()
        'introformat'     => FORMAT_HTML, // =1
        'comments'        => 0,
        'timeavailablefrom' => 0,
        'timeavailableto' => 0,
        'requiredentries' => 10,
        'requiredentriestoview' => 10,
        'maxentries'      => 1,
        'approval'        => 1,
        'manageapproved'  => 0,
        'assessed'        => 0
    );
    protected $timefields = array(
        'timestart' => array('timeavailablefrom'),
        'timefinish' => array('timeavailableto')
    );

    /**
     * definition
     */
    public function definition() {

        $mform = $this->_form;
        $this->set_form_id($mform);

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }

        // cache shortcut to block config settings
        $config = $this->instance->config;

        $warning = array();
        if (empty($config->revisetimefinish)) {
            $warning[] = get_string('missingrevisetime', $this->plugin);
        }

        if (empty($config->registerdelegatescmid) && empty($config->registerpresenterscmid)) {
            $warning[] = get_string('missingregistercmid', $this->plugin);
        }

        if ($warning = implode('</li><li>', $warning)) {
            $warning = '<ul><li>'.$warning.'</li></ul>';
            $a = array('sesskey' => sesskey(),
                       'id' => $this->course->id,
                       'bui_editid' => $this->instance->instance->id);
            $a = new moodle_url('/course/view.php', $a);
            $warning = html_writer::tag('p', get_string('missingconfig', $this->plugin, $a->out())).$warning;
            $this->add_field($mform, $this->plugin, 'warning', 'static', PARAM_TEXT, $warning);
        }

        $name = 'sourceworkshop';
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'workshop');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);

        $name = 'statusfilter';
        $label = get_string($name, $this->plugin);

        // create the $elements for a single filter condition
        $elements = array();
        $elements[] = $mform->createElement('static', '', '', get_string($name.'1', $this->plugin));
        $elements[] = $mform->createElement('select', $name.'grade', null, $this->get_statuslimit_options());
        $elements[] = $mform->createElement('static', '', '', get_string($name.'2', $this->plugin));
        $elements[] = $mform->createElement('select', $name.'status', null, $this->get_statuslevel_options());
        $elements[] = $mform->createElement('static', '', '', get_string($name.'3', $this->plugin));

        // prepare the parameters to pass to the "repeat_elements()" method
        $elements = array($mform->createElement('group', $name, $label, $elements, ' ', false));
        $repeats = optional_param('count'.$name, 0, PARAM_INT);
        $options = array($name.'level' => array('type' => PARAM_TEXT),
                         $name.'limit' => array('type' => PARAM_INT),
                         $name => array('helpbutton' => array($name, $this->plugin)));
        $addstring = get_string('add'.$name, $this->plugin, 1);
        $this->repeat_elements($elements, $repeats, $options, 'count'.$name, 'add'.$name, 1, $addstring, true);
        $mform->disabledIf('add'.$name, 'targetdatabasenum', 'eq', 0);

        $name = 'targetdatabase';
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $name = 'programcommittee';
        list($options, $default) = $this->get_group_options($name, $name);
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options, $default);
        $mform->disabledIf($name, 'sourceworkshop', 'eq', 0);
        $mform->disabledIf($name, 'targetdatabasenum', 'eq', 0);

        $name = 'sendername';
        $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT);
        $mform->disabledIf($name, 'sourceworkshop', 'eq', 0);
        $mform->disabledIf($name, 'targetdatabasenum', 'eq', 0);

        $name = 'senderemail';
        $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT);
        $mform->disabledIf($name, 'sourceworkshop', 'eq', 0);
        $mform->disabledIf($name, 'targetdatabasenum', 'eq', 0);

        $this->add_action_buttons();
    }

    /**
     * get_statuslevel_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_statuslevel_options() {
        if ($dataid = $this->get_dataid('targetdatabasenum')) {
            $options = $this->get_menufield_options($dataid, 'submission_status');
        } else {
            $options = array();
        }
        return $options;
    }

    /**
     * get_statuslimit_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_statuslimit_options() {
        $options = array();
        for ($i=100; $i>=0; $i--) {
            $options[$i] = ">= $i%";
        }
        $options[self::NOT_GRADED] = get_string('notgraded', 'question');
        return $options;
    }

    /**
     * form_postprocessing
     *
     * @uses $DB
     * @param object $data
     * @return not sure ...
     * @todo Finish documenting this function
     */
    public function form_postprocessing() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot.'/mod/workshop/locallib.php');

        $cm = false;
        $msg = array();

        // cache shortcut to block config settings
        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, 'targetdatabase');
        }

        if ($cm) {

            // get database
            $database = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
            $database->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
            $database->instance   = $cm->instance;
            $database->cmid       = $cm->id;

            // get workshop
            $cm = $DB->get_record('course_modules', array('id' => $data->sourceworkshop));
            $instance = $DB->get_record('workshop', array('id' => $cm->instance));
            $course = $DB->get_record('course', array('id' => $instance->course));
            $workshop = new workshop($instance, $cm, $course);

            // get workshop submissions
            $submissions = $DB->get_records('workshop_submissions', array('workshopid' => $workshop->id));
            if ($submissions===false) {
                $submissions = array();
            }

            // ids of data records that get updated
            $maxgrade = 0;
            $countselected = 0;
            $countskipped = 0;
            $counttransferred = 0;
            $datarecords = array();

            $multilangsearch = '/<span[^>]*lang="(\w+)"[^>]*>(.*?)<\/span>/i';

            foreach ($submissions as $sid => $submission) {

                // Get database records that link to this submission.
                // We only expect one record - but there may be more!
                if ($records = self::get_database_records($workshop, $sid, $database->id)) {
                    $record = reset($records);
                    if (self::send_confirmation_email($this->plugin, $config, $data, $record, $submission, 'reviewresult', $datarecords, $workshop->id)) {
                        $counttransferred++;
                    } else {
                        $countskipped++;
                    }
                }
                unset($records, $record);
            }

            if ($countskipped) {
                $msg[] = get_string('reviewsskipped', $this->plugin, $countskipped);
            }

            if ($counttransferred) {

                // format list of reviewed submissions
                uksort($datarecords, 'strnatcmp'); // natural sort by grade (low -> high)
                $datarecords = array_reverse($datarecords); // reverse order (high -> low)
                $msg[] = html_writer::tag('p', get_string('reviewstransferred', $this->plugin, $counttransferred)).
                         html_writer::alist($datarecords, null, 'ol');

                $suffix = $workshop->name;
                if (preg_match_all($multilangsearch, $suffix, $matches, PREG_OFFSET_CAPTURE)) {
                    $i_max = (count($matches[0]) - 1);
                    for ($i=$i_max; $i >= 0; $i--) {
                        list($match, $start) = $matches[2][$i]; // the "inner" text of the <span>
                        if (($pos = block_maj_submissions::textlib('strrpos', $match, '(')) ||
                            ($pos = block_maj_submissions::textlib('strrpos', $match, '（'))) {
                            $replace = block_maj_submissions::textlib('substr', $match, $pos);
                            $suffix = substr_replace($suffix, $replace, $start, strlen($match));
                        }
                    }
                    $suffix = get_string('labelsep', 'langconfig').$suffix;
                } else {
                    $suffix = '';
                }

                // prepare resource with list of reviewed submissions
                $pagedata = (object)array(
                    'modname' => 'page',
                    'pagenum' => self::CREATE_NEW,
                    'pagename' => $this->instance->get_string('vettingresults', $this->plugin).$suffix,
                    'coursesectionnum' => get_fast_modinfo($this->course)->get_cm($cm->id)->sectionnum,
                    'coursesectionname' => '',
                    'content' => end($msg),
                    'timemodified' => $this->time
                );

                // hide list of from ordinary users (=students)
                $name = 'programcommittee';
                if (empty($data->$name)) {
                    $pagedata->visible = 0; // hidden from everyone
                } else {
                    $pagedata->visible = 1; // visible, but only to program committe
                }

                // create Moodle page page
                $cm = $this->get_cm($msg, $pagedata, 'page');

                // restrict access to "Program Committee" only
                if (isset($data->$name) && is_numeric($data->$name)) {
                    $restrictions = (object)array(
                        'op' => '|',
                        'c' => array((object)array('type' => 'group', 'id' => intval($data->$name))),
                        'show' => true
                    );
                    self::set_cm_restrictions($cm, $restrictions);
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }

    /**
     * send_confirmation_email
     *
     * @uses $DB
     *
     * @param string $plugin the name of this plugin, i.e. block_maj_submissions
     * @param object $config
     * @param object $data from recently submitted form
     * @param object $record from "data_records" table
     * @param object $submission from "workshop_submissions" table
     * @param string $emailtype "reviewresult" or "reviewupdate"
     * @param array of $datarecords that were updated by this function (passed by reference)
     * @param integer $workshopid (optional, default = 0)
     */
    static public function send_confirmation_email($plugin, $config, $data, $record, $submission, $emailtype, &$datarecords, $workshopid=0) {
        global $DB, $USER;

        static $a = null; // email parameters
        static $reviewfields     = null;
        static $revisetimefinish = null;
        static $registrationlink = null;
        static $conferencemonth  = null;
        static $statusfilters    = null;
        static $noreply          = null;

        static $criteria         = null;
        static $maxgrade         = 0;

        // set up static variables
        if ($a===null) {
            $a = new stdClass();

            if (empty($config->conferencename)) {
                $a->conferencename = $config->conferencenameen;
            } else {
                $a->conferencename = $config->conferencename;
            }

            if (empty($config->reviewteamname)) {
                $a->reviewteamname = get_string('reviewteamname', $plugin);
            } else {
                $a->reviewteamname = $config->reviewteamname;
            }

            if (empty($data->sendername)) {
                $a->sendername = fullname($USER);
            } else {
                $a->sendername = $data->sendername;
            }

            if (empty($data->senderemail)) {
                $a->senderemail = $USER->email;
            } else {
                $a->senderemail = $data->senderemail;
            }

            // get reply address for email message
            if (class_exists('core_user')) {
                // Moodle >= 2.6
                $noreply = core_user::get_noreply_user();
            } else {
                // Moodle <= 2.5
                $noreply = generate_email_supportuser();
            }

            // get ids of fields to transfer to, or set in, the submissions DB
            // NOTE: the order of these items is important
            $reviewfields = array('peer_review_details', // set $submission->grade (must come 1st)
                                  'submission_status',   // set $status from $submission->grade
                                  'peer_review_score',   // requires $submission->grade
                                  'peer_review_notes',   // requires $status
                                  'presentation_original');
            $reviewfields = array_flip($reviewfields);
            foreach (array_keys($reviewfields) as $name) {
                $params = array('dataid' => $record->dataid, 'name' => $name);
                $reviewfields[$name] = $DB->get_field('data_fields', 'id', $params);
            }

            // get formatted deadline for revisions
            $dateformat = block_maj_submissions::get_date_format($config);

            if (empty($config->revisetimefinish)) {
                // no deadline has been set for the revisions, so we give them a week
                $revisetimefinish = strtotime('today midnight') + WEEKSECS - (5 * MINSECS);
            } else {
                $revisetimefinish = $config->revisetimefinish;
            }
            $revisetimefinish = userdate($revisetimefinish, $dateformat);

            $registrationlink = '';
            if (! empty($config->registerdelegatescmid)) {
                $registrationlink = 'registerdelegatescmid';
            }
            if (! empty($config->registerpresenterscmid)) {
                $registrationlink = 'registerpresenterscmid';
            }
            if ($registrationlink) {
                $cmid = $config->$registrationlink;
                if ($courseid = $DB->get_field('course_modules', 'course', array('id' => $cmid))) {
                    $modname = get_fast_modinfo($courseid)->get_cm($cmid)->modname;
                    $url = new moodle_url("/mod/$modname/view.php", array('id' => $cmid));
                    $txt = get_string($registrationlink, $plugin);
                    $registrationlink = html_writer::link($url, $txt, array('target' => '_blank'));
                } else {
                    // registration page/database has been removed - shouldn't happen !!
                    $registrationlink = '';
                }
            }

            if (empty($config->conferencetimestart)) {
                $conferencemonth = '';
            } else {
                $conferencemonth = $config->conferencetimestart;
                $conferencemonth = userdate($conferencemonth, '%B');
            }

            // setup $statusfilters which maps a submission grade to a data record status
            if (isset($data->statusfiltergrade) && isset($data->statusfilterstatus)) {
                $statusfilters = array(self::NOT_GRADED => null); // may get overwritten
                foreach ($data->statusfiltergrade as $i => $grade) {
                    if (array_key_exists($i ,$data->statusfilterstatus)) {
                        $statusfilters[intval($grade)] = $data->statusfilterstatus[$i];
                    }
                }
                // sort from highest grade to lowest grade
                krsort($statusfilters);
            }
        }

        // fetch information about $author
        // i.e. the creator of the original database record
        $author = $DB->get_record('user', array('id' => $record->userid));

        // set URL of submissions database
        $databaseurl = array('d' => $record->dataid, 'rid' => $record->recordid, 'mode' => 'single');
        $databaseurl = new moodle_url('/mod/data/view.php', $databaseurl);

        // add submission title to email parameters
        $a->title = $submission->title;

        // add author info to email parameters
        $a->author = $author->lastname;
        $a->recordid = $record->recordid;
        $a->fullname = fullname($author);
        $a->databaseurl = $databaseurl->out(false);

        // initialize review details for email message
        $a->submission_status = '';
        $a->peer_review_score = '';
        $a->peer_review_notes = '';

        // get info about criteria (=dimensions) and levels
        if ($criteria===null) {
            $params = array('workshopid' => $workshopid);
            if ($criteria = $DB->get_records('workshopform_rubric', $params, 'sort')) {
                foreach (array_keys($criteria) as $id) {
                    $criteria[$id]->levels = array();
                }
                list($select, $params) = $DB->get_in_or_equal(array_keys($criteria));
                if ($levels = $DB->get_records_select('workshopform_rubric_levels', "dimensionid $select", $params, 'grade')) {
                    while ($level = array_pop($levels)) {
                        $id = $level->dimensionid;
                        $grade = $level->grade;
                        $level = format_string($level->definition, $level->definitionformat);
                        $criteria[$id]->levels[$grade] = $level;
                    }
                }
                unset($levels);
                foreach (array_keys($criteria) as $id) {
                    asort($criteria[$id]->levels);
                    $grades = array_keys($criteria[$id]->levels);
                    $criteria[$id]->maxgrade = intval(max($grades));
                    $maxgrade += $criteria[$id]->maxgrade;

                    // format the rubric criteria description, assuming the following structure:
                    // <p><b>title</b><br />explanation ...</p>
                    $text = format_text($criteria[$id]->description, $criteria[$id]->descriptionformat);
                    $text = preg_replace('/^\\s*<(h1|h2|h3|h4|h5|h6|p)\\b[^>]*>(.*?)<\\/\\1>.*$/u', '$2', $text);
                    $text = preg_replace('/^(.*?)<br\\b[^>]*>.*$/u', '$1', $text);
                    $text = preg_replace('/<[^>]*>/u', '', $text); // strip tags
                    $text = preg_replace('/[[:punct:]]+$/u', '', $text);
                    $criteria[$id]->description = $text;
                }
            } else {
                $criteria = array();
            }
        }

        if ($workshopid) {
            // If $workshopid is set, then we are updating from the "workshop2data" tool
            // and we should not update review fields again, or send emails again.
            // Setup $select sql to detect review fields that are already filled in.
            list($select, $params) = $DB->get_in_or_equal($reviewfields);
            $select = "recordid = ? AND fieldid $select AND content IS NOT NULL AND content != ?";
            array_unshift($params, $record->recordid);
            array_push($params, '');
            if ($DB->record_exists_select('data_content', $select, $params)) {
                return false;
            }
        } else {
            // If $workshopid is 0, then we are updating from the "updatevetting" tool.
            // Setup $select sql to detect if current "submission_status" is same as new status
            // and current "peer_review_score" is same as new score.
            $select = array();
            $params = array();
            if ($data->newstatus && array_key_exists('submission_status', $reviewfields)) {
                $select[] = 'fieldid = ? AND content = ?';
                array_push($params, $reviewfields['submission_status'], $data->newstatus);
            }
            if ($data->newscore && array_key_exists('peer_review_score', $reviewfields)) {
                $select[] = 'fieldid = ? AND content = ?';
                array_push($params, $reviewfields['peer_review_score'], $data->newscore);
            }
            if ($count = count($select)) {
                if ($count == 1) {
                    $select = reset($select);
                } else {
                    $select = '('.implode(') OR (', $select).')';
                }
                $select = "recordid = ? AND $select";
                array_unshift($params, $record->recordid);
                if ($count == $DB->count_records_select('data_content', $select, $params)) {
                    return false;
                }
            }
        }

        // format and transfer each of the peer review fields
        foreach ($reviewfields as $name => $fieldid) {

            if (empty($fieldid)) {
                continue; // shouldn't happen !!
            }

            $content = '';
            $format = null; // textareas set this to FORMAT_HTML
            switch ($name) {

                case 'peer_review_details';
                    if ($submission->id) {
                        $assessments = $DB->get_records('workshop_assessments', array('submissionid' => $submission->id));
                    } else {
                        $assessments = false;
                    }
                    if ($assessments===false) {
                        $assessments = array();
                    }
                    $assessmentgrades = array();

                    $i = 1; // peer review number
                    foreach ($assessments as $aid => $assessment) {
                        if ($grades = $DB->get_records('workshop_grades', array('assessmentid' => $aid))) {

                            $content .= html_writer::tag('h4', get_string('peerreviewnumber', $plugin, $i++))."\n";

                            $content .= html_writer::start_tag('table');
                            $content .= html_writer::start_tag('tbody')."\n";

                            $content .= html_writer::start_tag('tr');
                            $content .= html_writer::tag('th', get_string('criteria', 'workshopform_rubric'));
                            $content .= html_writer::tag('th', get_string('assessment', 'workshop'), array('style' => 'text-align:center;'));
                            $content .= html_writer::end_tag('tr')."\n";

                            // CSS class for criteria grades
                            $params = array('class' => 'criteriagrade');

                            // the assessment grade is the sum of the criteria $grades
                            $assessmentgrade = 0;
                            foreach ($grades as $grade) {
                                $id = $grade->dimensionid;
                                $content .= html_writer::start_tag('tr');
                                $content .= html_writer::tag('td', $criteria[$id]->description);
                                $content .= html_writer::tag('td', intval($grade->grade).' / '.$criteria[$id]->maxgrade, $params);
                                $content .= html_writer::end_tag('tr')."\n";
                                $assessmentgrade += intval($grade->grade);
                            }
                            $assessmentgrades[] = $assessmentgrade;

                            // CSS class for submission grade
                            $params = array('class' => 'submissiongrade');

                            $content .= html_writer::start_tag('tr');
                            $content .= html_writer::tag('td', ' ');
                            $content .= html_writer::tag('td', $assessmentgrade.' / '.$maxgrade, $params);
                            $content .= html_writer::end_tag('tr')."\n";

                            $content .= html_writer::end_tag('tbody');
                            $content .= html_writer::end_tag('table')."\n";
                        }

                        // CSS class for feedback
                        $params = array('class' => 'feedback');
                        if ($feedback = block_maj_submissions::plain_text($assessment->feedbackauthor)) {
                            $feedback = html_writer::tag('b', get_string('feedback')).' '.$feedback;
                            $feedback = html_writer::tag('p', $feedback, $params);
                            $content .= $feedback;
                        }
                    }

                    if ($content) {
                        $format = FORMAT_HTML;
                    }

                    // set submission grade, if necessary
                    // only required if $submission has no grade
                    // e.g. if the workshop is not in "grading" phase yet
                    if (is_numeric($submission->grade)) {
                        $submission->grade = intval($submission->grade);
                    } else if (empty($assessmentgrades)) {
                        $submission->grade = 0;
                    } else {
                        $submission->grade = array_sum($assessmentgrades);
                        $submission->grade /= count($assessmentgrades);
                        $submission->grade = intval($submission->grade);
                    }
                    break;

                case 'submission_status';
                    if (! empty($data->newstatus)) {
                        $content = $data->newstatus;
                    } else if (! empty($statusfilters)) {
                        if (is_numeric($submission->grade)) {
                            $content = round($submission->grade, 0);
                            foreach ($statusfilters as $filtergrade => $filterstatus) {
                                if ($content >= $filtergrade) {
                                    $content = $filterstatus;
                                    break;
                                }
                            }
                        } else {
                            $content = $statusfilters[self::NOT_GRADED];
                        }
                    } else if (! empty($record->$name)) {
                        $content = $record->$name;
                    }
                    $a->$name = $content;
                    $record->$name = $content;
                    $submission->status = $content;
                    break;

                case 'peer_review_score';
                    if (! empty($data->newscore)) {
                        $content = $data->newscore;
                    } else if (! empty($submission->grade)) {
                        $content = $submission->grade;
                    } else if (! empty($record->$name)) {
                        $content = $record->$name;
                    } else {
                        $content = '';
                    }
                    if (is_numeric($content)) {
                        $content = round($content, 0);
                    }
                    $a->$name = $content;
                    $record->$name = $content;
                    $submission->grade = $content;
                    break;

                case 'peer_review_notes';
                    $params = array('class' => 'thanks');
                    $content .= html_writer::tag('p', get_string('peerreviewgreeting', $plugin), $params)."\n";

                    switch (true) {
                        case is_numeric(strpos($submission->status, 'Conditionally accepted')):
                            $content .= html_writer::tag('p', get_string('conditionallyaccepted', $plugin), array('class' => 'status'))."\n";
                            $advice = array(
                                get_string('pleasemakechanges', $plugin, $revisetimefinish),
                                get_string('youwillbenotified', $plugin)
                            );
                            $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                            break;

                        case is_numeric(strpos($submission->status, 'Not accepted')):
                            $content .= html_writer::tag('p', get_string('notaccepted', $plugin), array('class' => 'status'))."\n";
                            break;

                        case is_numeric(strpos($submission->status, 'Accepted')):
                            $content .= html_writer::tag('p', get_string('accepted', $plugin), array('class' => 'status'))."\n";
                            if ($registrationlink) {
                                $advice = array(
                                    get_string('pleaseregisteryourself', $plugin, $registrationlink),
                                    get_string('pleaseregistercopresenters', $plugin)
                                );
                                $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                            }
                            $content .= html_writer::tag('p', get_string('acceptedfarewell', $plugin, $conferencemonth), array('class' => 'farewell'));
                            break;

                        case is_numeric(strpos($submission->status, 'Waiting for review')):
                            $content .= html_writer::tag('p', get_string('waitingforreview', $plugin), array('class' => 'status'))."\n";
                            break;
                    }
                    if ($content) {
                        $format = FORMAT_HTML;
                    }
                    $a->$name = html_to_text($content, 0, false);
                    $a->$name = preg_replace('/[\r\n]+/', "\n", $a->$name);
                    $a->$name = preg_replace('/[ \t]+(?=\*)/', '', $a->$name);
                    $a->$name = trim($a->$name);
                    break;

                case 'presentation_original':
                    if (empty($record->$name)) {
                        $content = $submission->content;
                        $format = FORMAT_HTML;
                    }
                    break;

            }

            // update this review field
            if ($content) {
                $params = array('fieldid'  => $fieldid,
                                'recordid' => $record->recordid);
                if ($DB->record_exists('data_content', $params)) {
                    $DB->set_field('data_content', 'content', $content, $params);
                } else {
                    $content = (object)array(
                        'fieldid'  => $fieldid,
                        'recordid' => $record->recordid,
                        'content'  => $content,
                        'content1'  => $format
                    );
                    $content->id = $DB->insert_record('data_content', $content);
                }
            }
        } // end foreach $reviewfields

        // URL to this data record
        $params = array('d' => $record->dataid, 'rid' => $record->recordid, 'mode' => 'single');
        $datarecord = new moodle_url('/mod/data/view.php', $params);

        // link to this data record
        $params = array('target' => '_blank');
        $datarecord = html_writer::link($datarecord, $record->recordid, $params);

        // message text for this data record
        $datarecord = "[$datarecord] ($submission->grade%) ".$submission->title;

        // cache the message text and index by grade for sorting later
        // (append recordid to key to ensure uniqueness)
        $datarecords[$submission->grade.'_'.$record->recordid] = $datarecord;

        // send email to the author regarding review results
        if (empty($CFG->noemailever) && $submission->status) {
            $subject = get_string($emailtype.'subject', $plugin, $a);
            $messagetext = get_string($emailtype.'message', $plugin, $a);
            $messagehtml = format_text($messagetext, FORMAT_MOODLE);
            email_to_user($author, $noreply, $subject, $messagetext, $messagehtml);
        }

        return true;
    }
}
