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
require_once($CFG->dirroot.'/blocks/maj_submissions/tools/form.filterconditions.php');

/**
 * block_maj_submissions_tool_updatevetting
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_updatevetting extends block_maj_submissions_tool_filterconditions {

    /**
     * definition
     */
    public function definition() {

        $mform = $this->_form;

        // fetch default form values
        $default = $this->get_default_formvalues();

		$name = 'sourcedatabase';
		$this->add_field_sourcedatabase($mform, $name);

        // other fields are all depedent on the "sourcedatabase" being set
		$dependantname = $name;

		$include = 'peer_review_score,presentation_title,presentation_type,submission_status';
		$exclude = '';

		$name = 'filterconditions';
		$this->add_field_filterconditions($mform, $name, $dependantname, $include, $exclude);

		$name = 'search';
        $options = array('size' => self::TEXT_FIELD_SIZE);
        $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT, $options);
        $mform->disabledIf($name, $dependantname, 'eq', 0);

		$name = 'sort';
		$label = get_string($name, $this->plugin);
        $elements = array(
            $mform->createElement('select', $name.'field', '', $this->get_field_options($include, $exclude)),
            $mform->createElement('select', $name.'direction', '', $this->get_sortdirection_options())
        );
        $mform->addGroup($elements, $name, $label, ' ', false);
        $mform->addHelpButton($name, $name, $this->plugin);
        $mform->setType($name.'field', PARAM_INT);
        $mform->setType($name.'direction', PARAM_TEXT);
        $mform->setDefault($name.'field', $default->sortfield);
        $mform->setDefault($name.'direction', $default->sortdirection);
        $mform->disabledIf($name, $dependantname, 'eq', 0);

		$name = 'displayperpage';
		$options = $this->get_sortperpage_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options, $default->$name);
        $mform->disabledIf($name, $dependantname, 'eq', 0);

        // get all records matching the filters
        if (optional_param('submitbutton', '', PARAM_TEXT)) {
            $name = 'submissions';
            if ($elements = $this->get_submissions($mform, $name)) {
                $label = get_string($name, $this->plugin);
                $mform->addGroup($elements, $name, $label, '<br>', false);
                $mform->disabledIf($name, $dependantname, 'eq', 0);

                $name = 'newscore';
                $options = array('size' => self::TEXT_FIELD_SIZE);
                $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT, $options);
                $mform->disabledIf($name, $dependantname, 'eq', 0);

                $name = 'newstatus';
                $options = $this->get_newstatus_options();
                $this->add_field($mform, $this->plugin, $name, 'select', PARAM_TEXT, $options, $default->$name);
                $mform->disabledIf($name, $dependantname, 'eq', 0);

                // cache options for standard/custom fields
                $options = array(0 => get_string('standard', $this->plugin),
                                 1 => get_string('custom', $this->plugin));

                $names = array('newfeedback', 'emailmessage');
                foreach ($names as $name) {
                    $label = get_string($name, $this->plugin);
                    $elements = array(
                        $mform->createElement('select', $name.'type', '', $options),
                        $mform->createElement('textarea', $name.'text')
                    );
                    $mform->addGroup($elements, $name, $label, '<br>', false);
                    $mform->addHelpButton($name, $name, $this->plugin);
                    $mform->disabledIf($name, $dependantname, 'eq', 0);
                    $mform->disabledIf($name.'text', $name.'type', 'eq', 0);
                }

                $name = 'sendername';
                $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT);
                $mform->disabledIf($name, $dependantname, 'eq', 0);

                $name = 'senderemail';
                $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT);
                $mform->disabledIf($name, $dependantname, 'eq', 0);
            } else {
                $text = get_string('norecordsfound', $this->plugin);
                $mform->addElement('static', '', '', html_writer::tag('h3', $text));
            }
        }

        $this->add_action_buttons();
    }

    /**
     * get_default_formvalues
     */
    protected function get_default_formvalues() {
        global $SESSION;

        $dataid = $this->get_dataid('sourcedatabase');

        $default = new stdClass();

        // entries-per-page is stored in user preferences
        $default->displayperpage = 10; // initial default
        $default->displayperpage = get_user_preferences('data_perpage_'.$dataid, $default->displayperpage);
        $default->displayperpage = optional_param('displayperpage', $default->displayperpage, PARAM_INT);
        set_user_preference('data_perpage_'.$dataid, $default->displayperpage);

        // other values are stored as $SESSION values
        if (empty($SESSION->dataprefs[$dataid])) {
            $default->search = '';
            $default->sortfield = '';
            $default->sortdirection = 'ASC';
        } else {
            $default->search = $SESSION->dataprefs[$dataid]['search'];
            $default->sortfield = $SESSION->dataprefs[$dataid]['sort'];
            $default->sortdirection = $SESSION->dataprefs[$dataid]['order'];
        }

        $default->search = optional_param('sort', $default->search, PARAM_TEXT);
        $default->sortfield = optional_param('sort', $default->sortfield, PARAM_INT);
        $default->sortdirection = optional_param('order', $default->sortdirection, PARAM_ALPHA);
        $default->sortdirection = (($default->sortdirection == 'ASC') ? 'ASC': 'DESC');

        if (empty($SESSION->dataprefs)) {
            $SESSION->dataprefs = array();
        }
        if (empty($SESSION->dataprefs[$dataid])) {
            $SESSION->dataprefs[$dataid] = array('search_array' => array(),
                                                 'advanced' => 0);
        }
        $SESSION->dataprefs[$dataid]['search'] = $default->search;
        $SESSION->dataprefs[$dataid]['sort'] = $default->sortfield;
        $SESSION->dataprefs[$dataid]['order'] = $default->sortdirection;

		$options = $this->get_newstatus_options();
		$default->newstatus = '';
		foreach ($options as $key => $value) {
		    if (strpos($key, 'Accepted')===false) {
		        continue;
		    }
		    if (strpos($key, 'Not')===false && strpos($key, 'Conditionally')===false) {
		        $default->newstatus = $key;
		    }
		}

        return $default;
    }

    /**
     * get_default_formvalues
     *
     * @param object $mform
     * @param string $name of form field
     * @return array of form group elements
     */
    protected function get_submissions($mform, $name) {
        if (optional_param('submitbutton', '', PARAM_TEXT)) {
            $dataid = $this->get_dataid('sourcedatabase');
            $data = null; // $this->get_data is not available until AFTER the form has been setup
            $fields = array('submission_status' => '',
                            'peer_review_score' => '',
                            'presentation_type' => '',
                            'presentation_title' => '');
            $elements = array();
            $style = 'display: inline-block; padding: 0px 4px;';
            if ($records = $this->get_filtered_records($dataid, $data, $fields)) {

                // add titles
                $text = '';
                $text .= html_writer::tag('div', 'Submission ID', array('class' => 'submissionid', 'style' => $style));
                $text .= html_writer::tag('div', $fields['submission_status'], array('class' => 'subissionstatus', 'style' => $style));
                $text .= html_writer::tag('div', $fields['peer_review_score'], array('class' => 'peerreviewscore', 'style' => $style));
                $text .= html_writer::tag('div', $fields['presentation_type'], array('class' => 'presentationtype', 'style' => $style));
                $text .= html_writer::tag('div', get_string('user'), array('class' => 'user', 'style' => $style));
                $text .= html_writer::tag('div', $fields['presentation_title'], array('class' => 'presentationtitle', 'style' => $style));
                $elements[] = $mform->createElement('checkbox', $name."[0]", '', $text);

                // add selected submission records
                foreach ($records as $recordid => $record) {

                    // prepare link to submission record in database
                    $url = new moodle_url('/mod/data/view.php', array('d' => $dataid,
                                                                      'rid' => $record->recordid,
                                                                      'mode' => 'single'));
                    $record->submissionid = html_writer::link($url, $record->recordid, array('target' => '_blank'));

                    if (empty($record->submission_status)) {
                        $record->submission_status = '--';
                    }

                    if (empty($record->peer_review_score)) {
                        $record->peer_review_score = '--';
                    } else {
                        $record->peer_review_score .= '%';
                    }

                    // prepare link to user profile
                    $url = new moodle_url('/user/view.php', array('id' => $record->userid,
                                                                  'course' => $this->course->id));
                    $record->fullname = html_writer::link($url, $this->fullname($record->userid), array('target' => '_blank'));

                    // prepare $text summary for this submission
                    $text = '';
                    $text .= html_writer::tag('div', $record->submissionid, array('class' => 'submissionid', 'style' => $style));
                    $text .= html_writer::tag('div', $record->submission_status, array('class' => 'subissionstatus', 'style' => $style));
                    $text .= html_writer::tag('div', $record->peer_review_score, array('class' => 'peerreviewscore', 'style' => $style));
                    $text .= html_writer::tag('div', $record->presentation_type, array('class' => 'presentationtype', 'style' => $style));
                    $text .= html_writer::tag('div', $record->fullname, array('class' => 'fullname', 'style' => $style));
                    $text .= html_writer::tag('div', $record->presentation_title, array('class' => 'presentationtitle', 'style' => $style));

                    // add submission
                    $elements[] = $mform->createElement('checkbox', $name."[$recordid]", '', $text);
                }
            }
            if (count($elements)) {
                return $elements;
            }
        }
        return false; // no matching records :-(
    }

    /**
     * get_sortdirection_options
     */
    protected function get_sortdirection_options() {
        return array('ASC' => get_string('ascending','data'),
                     'DESC' => get_string('descending','data'));
    }

    /**
     * get_sortperpage_options
     */
    protected function get_sortperpage_options() {
        return array(1 => 1, 2 => 2, 3 => 3,
                     4 => 4, 5 => 5, 6 => 6,
                     7 => 7, 8 => 8, 9 => 9,
                     10 => 10, 15 => 15, 20 => 20,
                     30 => 30, 40 => 40, 50 => 50,
                     100 => 100, 200 => 200, 300 => 300,
                     400 => 400, 500 => 500, 1000 => 1000
        );
    }

    /**
     * get_newstatus_options
     *
     * @uses $DB
     * @return array of database fieldnames
     */
    protected function get_newstatus_options() {
        if ($dataid = $this->get_dataid('sourcedatabasenum')) {
            $options = $this->get_menufield_options($dataid, 'submission_status');
    	} else {
    		$options = array();
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

        // fetch the "workshop2data" class, for handling reviewfields and formatting emails
        require_once($CFG->dirroot.'/blocks/maj_submissions/tools/workshop2data/form.php');

        $cm = false;
        $time = time();
        $msg = array();
        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'sourcedatabase');
        }

        if (empty($data->submissions)) {
            $recordids = array();
        } else {
            $recordids = array_keys($data->submissions);
            $recordids = array_filter($recordids);
        }

        if ($cm && count($recordids)) {

            // cache the database id
            $dataid = $this->get_dataid('sourcedatabase');

			// specifiy the presentation fields that we want
			$fields = array('peer_review_details' => '', // set $submission->grade (must come 1st)
                            'submission_status'   => '', // set $status from $submission->grade
                            'peer_review_score'   => '', // requires $submission->grade
                            'peer_review_notes'   => '', // requires $status
                            'presentation_type'   => '',
                            'presentation_title'  => '',
                            'presentation_original' => '',
                            'presentation_abstract' => '');

			// get all records matching the filters (may update $data and $fields)
            if ($records = $this->get_filtered_records($dataid, $data, $fields, $recordids)) {
                foreach ($records as $record) {
                    $submission = (object)array(
                        'id' => null,
                        'title' => $record->presentation_title,
                        'content' => $record->presentation_abstract,
                        'status' => $record->submission_status,
                        'grade' => $record->peer_review_score
                    );
                    block_maj_submissions_tool_workshop2data::send_confirmation_email(
                        $this->plugin, $config, $data, $record, $submission, 'reviewupdate', $datarecords
                    );
                }
                if ($countupdated = count($datarecords)) {
                    // format list of updated submissions
                    uksort($datarecords, 'strnatcmp'); // natural sort by grade (low -> high)
                    $datarecords = array_reverse($datarecords); // reverse order (high -> low)
                    $msg[] = html_writer::tag('p', get_string('reviewsupdated', $this->plugin)).
                             html_writer::alist($datarecords, null, 'ol');
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }
}
