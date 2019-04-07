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
        'visible'         => 1,  // course_modules.visible
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
        $options = $this->get_group_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);
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
        if ($cmid = optional_param('targetdatabasenum', null, PARAM_INT)) {
            $dataid = get_fast_modinfo($this->course)->get_cm($cmid)->instance;
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
        $time = time();

        // cache shortcut to block config settings
        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'targetdatabase');
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

            // get ids of fields to transfer to, or set in, the submissions DB
            // NOTE: the order of these items is important
            $reviewfields = array(
                'peer_review_details', // set $submission->grade (must come 1st)
                'submission_status',   // set $status from $submission->grade
                'peer_review_score',   // requires $submission->grade
                'peer_review_notes',   // requires $status
                'presentation_original'
            );
            $reviewfields = array_flip($reviewfields);
            foreach (array_keys($reviewfields) as $name) {
                $params = array('dataid' => $database->id, 'name' => $name);
                $reviewfields[$name] = $DB->get_field('data_fields', 'id', $params);
            }

            // get formatted deadline for revisions
            if (! $dateformat = $config->customdatefmt) {
                if (! $dateformat = $config->moodledatefmt) {
                    $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
                }
                $dateformat = get_string($dateformat);
            }

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
                $params = array('id' => $config->$registrationlink);
                $registrationlink = html_writer::link(new moodle_url('/mod/data/view.php', $params),
                                                      get_string($registrationlink, $this->plugin),
                                                      array('target' => '_blank'));
            }

            if (empty($config->conferencetimestart)) {
                $conferencemonth = '';
            } else {
                $conferencemonth = $config->conferencetimestart;
                $conferencemonth = userdate($conferencemonth, '%B');
            }

            // get workshop submissions
            $submissions = $DB->get_records('workshop_submissions', array('workshopid' => $workshop->id));
            if ($submissions===false) {
                $submissions = array();
            }

            // setup $statusfilters which maps a submission grade to a data record status
            $statusfilters = array(self::NOT_GRADED => null); // may get overwritten
            if (isset($data->statusfiltergrade) && isset($data->statusfilterstatus)) {
                foreach ($data->statusfiltergrade as $i => $grade) {
                    if (array_key_exists($i ,$data->statusfilterstatus)) {
                        $statusfilters[intval($grade)] = $data->statusfilterstatus[$i];
                    }
                }
                // sort from highest grade to lowest grade
                krsort($statusfilters);
            }

            // ids of data records that get updated
            $maxgrade = 0;
            $countselected = 0;
            $counttransferred = 0;
            $datarecords = array();

            // get info about criteria (=dimensions) and levels
            $params = array('workshopid' => $workshop->id);
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

            // get reply address for email message
            if (class_exists('core_user')) {
                // Moodle >= 2.6
                $noreply = core_user::get_noreply_user();
            } else {
                // Moodle <= 2.5
                $noreply = generate_email_supportuser();
            }

            // initialize parameters for email message
            $a = new stdClass();

            if (empty($config->conferencename)) {
                $a->conferencename = $config->conferencenameen;
            } else {
                $a->conferencename = $config->conferencename;
            }

            if (empty($config->reviewteamname)) {
                $a->reviewteamname = get_string('reviewteamname', $this->plugin);
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

            foreach ($submissions as $sid => $submission) {

                // add submission title to email parameters
                $a->title = $submission->title;

                // get database records that link to this submission
                if ($records = self::get_database_records($workshop, $sid, $database->id)) {

                    // we only expect one record
                    $record = reset($records);

                    // fetch information about $author
                    // i.e. the creator of the original database record
                    $author = $DB->get_record('user', array('id' => $record->userid));

                    // NOTE: $cm is now the WORKSHOP cm record
                    $databaseurl = array('id' => $database->cmid, 'rid' => $record->recordid);
                    $databaseurl = new moodle_url('/mod/data/view.php', $databaseurl);

                    // add author info to email parameters
                    $a->author = $author->lastname;
                    $a->recordid = $record->recordid;
                    $a->fullname = fullname($author);
                    $a->databaseurl = $databaseurl->out(false);

                    // initialize review details for email message
                    $a->submission_status = '';
                    $a->peer_review_score = '';
                    $a->peer_review_notes = '';

                    // initialize the status - it should be reset from $statusfilters
                    $status = '';

                    // format and transfer each of the peer review fields
                    foreach ($reviewfields as $name => $fieldid) {
                        if (empty($fieldid)) {
                            continue; // shouldn't happen !!
                        }
                        $content = '';
                        $format = null; // textareas set this to FORMAT_HTML
                        switch ($name) {

                            case 'peer_review_details';
                                $assessments = $DB->get_records('workshop_assessments', array('submissionid' => $sid));
                                if ($assessments===false) {
                                    $assessments = array();
                                }
                                $assessmentgrades = array();

                                $i = 1; // peer review number
                                foreach ($assessments as $aid => $assessment) {
                                    if ($grades = $DB->get_records('workshop_grades', array('assessmentid' => $aid))) {

                                        $content .= html_writer::tag('h4', get_string('peerreviewnumber', $this->plugin, $i++))."\n";

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
                                if (is_numeric($submission->grade)) {
                                    $content = round($submission->grade, 0);
                                    foreach ($statusfilters as $grade => $status) {
                                        if ($content >= $grade) {
                                            $content = $status;
                                            break;
                                        }
                                    }
                                } else {
                                    $content = $statusfilters[self::NOT_GRADED];
                                }
                                $a->$name = $content;
                                break;

                            case 'peer_review_score';
                                if (is_numeric($submission->grade)) {
                                    $content = round($submission->grade, 0);
                                }
                                $a->$name = $content;
                                break;

                            case 'peer_review_notes';
                                $params = array('class' => 'thanks');
                                $content .= html_writer::tag('p', get_string('peerreviewgreeting', $this->plugin), $params)."\n";

                                switch (true) {
                                    case is_numeric(strpos($status, 'Conditionally accepted')):
                                        $content .= html_writer::tag('p', get_string('conditionallyaccepted', $this->plugin), array('class' => 'status'))."\n";
                                        $advice = array(
                                            get_string('pleasemakechanges', $this->plugin, $revisetimefinish),
                                            get_string('youwillbenotified', $this->plugin)
                                        );
                                        $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                                        break;

                                    case is_numeric(strpos($status, 'Not accepted')):
                                        $content .= html_writer::tag('p', get_string('notaccepted', $this->plugin), array('class' => 'status'))."\n";
                                        break;

                                    case is_numeric(strpos($status, 'Accepted')):
                                        $content .= html_writer::tag('p', get_string('accepted', $this->plugin), array('class' => 'status'))."\n";
                                        if ($registrationlink) {
                                            $advice = array(
                                                get_string('pleaseregisteryourself', $this->plugin, $registrationlink),
                                                get_string('pleaseregistercopresenters', $this->plugin)
                                            );
                                            $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                                        }
                                        $content .= html_writer::tag('p', get_string('acceptedfarewell', $this->plugin, $conferencemonth), array('class' => 'farewell'));
                                        break;

                                    case is_numeric(strpos($status, 'Waiting for review')):
                                        $content .= html_writer::tag('p', get_string('waitingforreview', $this->plugin), array('class' => 'status'))."\n";
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
                                $content = $submission->content;
                                $format = FORMAT_HTML;
                                break;

                        }

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

                    // URL to this data record
                    $params = array('d' => $database->id, 'rid' => $record->recordid);
                    $datarecord = new moodle_url('/mod/data/view.php', $params);

                    // link to this data record
                    $params = array('target' => '_blank');
                    $datarecord = html_writer::link($datarecord, $record->recordid, $params);

                    // message text for this data record
                    $datarecord = "[$datarecord] ($submission->grade%) ".$submission->title;

                    // cache the message text and index by grade for sorting later
                    // (append recordid to key to ensure uniqueness)
                    $datarecords[$submission->grade.'_'.$record->recordid] = $datarecord;
                    $counttransferred++;

                    // send email to the author regarding review results
                    if (empty($CFG->noemailever) && $status) {
                        $subject = get_string('reviewresultsubject', $this->plugin, $a);
                        $messagetext = get_string('reviewresultmessage', $this->plugin, $a);
                        $messagehtml = format_text($messagetext, FORMAT_MOODLE);
                        email_to_user($author, $noreply, $subject, $messagetext, $messagehtml);
                    }

                    // TODO: allow $USER to select a presenters group in the form
                    // TODO: mention the presenters group in the email message
                    // TODO: add the $author to the presenters group
                    // TODO: add the co-authors to the presenters group

                    // TODO: setup a presenters forum
                    // TODO: subscribe all presenters to the forum
                }
                unset($records, $record);
            }

            if ($counttransferred) {

                // format list of reviewed submissions
                uksort($datarecords, 'strnatcmp'); // natural sort by grade (low -> high)
                $datarecords = array_reverse($datarecords); // reverse order (high -> low)
                $msg[] = html_writer::tag('p', get_string('reviewstransferred', $this->plugin)).
                         html_writer::alist($datarecords, null, 'ol');

                // prepare resource with list of reviewed submissions
                $pagedata = (object)array(
                    'modname' => 'page',
                    'pagenum' => self::CREATE_NEW,
                    'pagename' => $this->instance->get_string('vettingresults', $this->plugin),
                    'coursesectionnum' => get_fast_modinfo($this->course)->get_cm($cm->id)->sectionnum,
                    'coursesectionname' => '',
                    'content' => end($msg)
                );

                // hide list of from ordinary users (=students)
				$name = 'programcommittee';
				if (empty($data->$name)) {
					$pagedata->visible = 0;
				} else {
					$pagedata->visible = 1;
				}

				// create Moodle page page
                $cm = $this->get_cm($msg, $pagedata, $time, 'page', $a);

                // restrict access to "Program Committee" only
                if (isset($data->$name) && is_numeric($data->$name)) {
                    $restrictions = array((object)array(
                        'type' => 'group',
                        'id' => intval($data->$name)
                    ));
                    self::set_cm_restrictions($cm, $restrictions);
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }
}
