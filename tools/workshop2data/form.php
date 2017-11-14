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

        $mform->disabledIf('add'.$name, 'targetdatabase', 'eq', 0);

        $name = 'targetdatabase';
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $this->add_action_buttons();
    }

    /**
     * get_statuslevel_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_statuslevel_options() {
        global $DB;
        $options = array();
        if ($cmid = optional_param('targetdatabasenum', null, PARAM_INT)) {
            $dataid = get_fast_modinfo($this->course)->get_cm($cmid)->instance;
            $params = array('dataid' => $dataid, 'name' => 'submission_status');
            if ($record = $DB->get_record('data_fields', $params)) {
                $search = self::bilingual_string();
                if (self::is_low_ascii_language()) {
                    $replace = '$2'; // low-ascii language e.g. English
                } else {
                    $replace = '$1'; // high-ascii/multibyte language
                }
                $options = preg_split('/[\r\n]+/', $record->param1);
                $options = array_filter($options);
                $options = array_flip($options);
                foreach (array_keys($options) as $option) {
                    $options[$option] = preg_replace($search, $replace, $option);
                }
            }
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
        $options = array(
            self::NOT_GRADED => get_string('notgraded', 'question')
        );
        foreach (range(0, 100) as $i) {
            $options[$i] = ">= $i%";
        }
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
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/workshop/locallib.php');

        $cm = false;
        $msg = array();
        $time = time();

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'targetdatabase');
        }

        if ($cm) {

            // get database
            $database = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);
            $database->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
            $database->instance   = $cm->instance;

            // get workshop
            $cm = $DB->get_record('course_modules', array('id' => $data->sourceworkshop));
            $instance = $DB->get_record('workshop', array('id' => $cm->instance));
            $course = $DB->get_record('course', array('id' => $instance->course));
            $workshop = new workshop($instance, $cm, $course);

            // get ids of peer_review_fields
            $reviewfields = array(
                'peer_review_details' => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'peer_review_details')),
                'peer_review_score'   => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'peer_review_score')),
                'peer_review_notes'   => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'peer_review_notes')),
                'submission_status'   => $DB->get_field('data_fields', 'id', array('dataid' => $database->id, 'name' => 'submission_status')),
            );

            // get formatted deadline for revisions
            if (! $dateformat = $this->instance->config->customdatefmt) {
                if (! $dateformat = $this->instance->config->moodledatefmt) {
                    $dateformat = 'strftimerecent'; // default: 11 Nov, 10:12
                }
                $dateformat = get_string($dateformat);
            }
            $revisetimefinish = $this->instance->config->revisetimefinish;
            $revisetimefinish = userdate($revisetimefinish, $dateformat);

            $registrationlink = '';
            if (! empty($this->instance->config->registerdelegatescmid)) {
                $registrationlink = 'registerdelegatescmid';
            }
            if (! empty($this->instance->config->registerpresenterscmid)) {
                $registrationlink = 'registerpresenterscmid';
            }
            if ($registrationlink) {
                $params = array('id' => $this->instance->config->$registrationlink);
                $registrationlink = html_writer::link(new moodle_url('/mod/data/view.php', $params),
                                                      get_string($registrationlink, $this->plugin),
                                                      array('target' => '_blank'));
            }

            if (empty($this->instance->config->conferencetimestart)) {
                $conferencemonth = '';
            } else {
                $conferencemonth = $this->instance->config->conferencetimestart;
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
            $datarecordids = array();

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

            foreach ($submissions as $sid => $submission) {

                // get database records that link to this submission
                if ($records = self::get_database_records($workshop, $sid, $database->id)) {

                    // we only expect one record
                    $record = reset($records);

                    // initialize the status - it should be reset from $statusfilters
                    $status = '';

                    // format and transfer each of the peer review fields
                    foreach ($reviewfields as $name => $fieldid) {
                        if (empty($fieldid)) {
                            continue; // shouldn't happen !!
                        }
                        $content = '';
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
                                    if ($feedback = self::plain_text($assessment->feedbackauthor)) {
                                        $feedback = html_writer::tag('b', get_string('feedback')).' '.$feedback;
                                        $feedback = html_writer::tag('p', $feedback, $params);
                                        $content .= $feedback;
                                    }
                                }

                                // set submission grade, if necessary
                                // only be required if the workshop is not in "grading" phase
                                if (is_numeric($submission->grade)) {
                                    // do nothing
                                } else if (empty($assessmentgrades)) {
                                    $submission->grade = 0;
                                } else {
                                    $submission->grade = array_sum($assessmentgrades);
                                    $submission->grade /= count($assessmentgrades);
                                    $submission->grade = intval($submission->grade);
                                }
                                break;

                            case 'peer_review_score';
                                if (is_numeric($submission->grade)) {
                                    $content = round($submission->grade, 0);
                                }
                                break;

                            case 'peer_review_notes';
                                $params = array('class' => 'thanks');
                                $content .= html_writer::tag('p', get_string('peerreviewgreeting', $this->plugin), $params)."\n";

                                switch (true) {
                                    case strpos($status, 'Conditionally accepted'):
                                        $content .= html_writer::tag('p', get_string('conditionallyaccepted', $this->plugin), array('class' => 'status'))."\n";
                                        $advice = array(
                                            get_string('pleasemakechanges', $this->plugin, $revisetimefinish),
                                            get_string('youwillbenotified', $this->plugin)
                                        );
                                        $content .= html_writer::alist($advice, array('class' => 'advice'))."\n";
                                        break;

                                    case strpos($status, 'Not accepted'):
                                        $content .= html_writer::tag('p', get_string('notaccepted', $this->plugin), array('class' => 'status'))."\n";
                                        break;

                                    case strpos($status, 'Accepted'):
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

                                    case strpos($status, 'Waiting for review'):
                                        $content .= html_writer::tag('p', get_string('waitingforreview', $this->plugin), array('class' => 'status'))."\n";
                                        break;
                                }

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
                                'content1' => ($name=='peer_review_score' ? null : FORMAT_HTML)
                            );
                            $content->id = $DB->insert_record('data_content', $content);
                        }

                        $counttransferred++;
                        $datarecordids[] = $record->recordid;
                    }
                }
                unset($records, $record);
            }
        }

        return $this->form_postprocessing_msg($msg);
    }
}
