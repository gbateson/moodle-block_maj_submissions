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
 * block_maj_submissions_tool_data2workshop
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_data2workshop extends block_maj_submissions_tool_filterconditions {

    protected $type = '';
    protected $modulename = 'workshop';
    protected $defaultname = 'reviewsubmissions';

    protected $template = null;

    protected $defaultvalues = array(
        'visible'         => 1,
        'submissionstart' => 0,
        'submissionend'   => 0,
        'assessmentstart' => 0,
        'assessmentend'   => 0,
        'phase'           => 10, // 10=setup, 20=submission, 30=assessment, 40=evaluation, 50=closed
        'grade'           => 100.0,
        'gradinggrade'    => 0.0,
        'strategy'        => 'rubric',
        'evaluation'      => 'best',
        'latesubmissions' => 1,
        'maxbytes'        => 0,
        'usepeerassessment' => 1,
        'overallfeedbackmaxbytes' => 0
    );

    protected $timefields = array(
        'timestart' => array('submissionstart', 'assessmentstart'),
        'timefinish' => array('submissionend', 'assessmentend')
    );

    /**
     * The name of the form field containing
     * the id of a group of anonymous submitters
     */
    protected $groupfieldnames = 'programcommittee,anonymousauthors';

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

		$name = 'sourcedatabase';
		$this->add_field_sourcedatabase($mform, $name);
		$this->add_field_filterconditions($mform, 'filterconditions', $name);

        $name = 'targetworkshop';
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_template($mform, $this->plugin, 'templateactivity', $this->modulename, $name);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $name = 'resetsubmissions';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', 0);
        $mform->disabledIf($name, 'targetworkshopnum', 'eq', self::CREATE_NEW);

        $this->add_group_fields($mform);

        $this->add_action_buttons();
    }

    /**
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        $defaultvalues = parent::get_defaultvalues($data, $time);

        if ($template = $this->get_template($data)) {
            foreach ($template as $name => $value) {
                if ($name=='id' || $name=='name') {
                    continue; // skip these fields
                }
                if (is_scalar($value)) {
                    $defaultvalues[$name] = $value;
                }
            }
        }

        return $defaultvalues;
    }

    /**
     * get_template
     */
    protected function get_template($data, $name='templateactivity') {
        global $DB;
        if ($this->template===null) {
            $this->template = false;
            if (empty($data->$name)) {
                return $this->template;
            }
            if (! $cm = $DB->get_record('course_modules', array('id' => $data->$name))) {
                return $this->template;
            }
            if (! $module = $DB->get_record('modules', array('id' => $cm->module, 'name' => $this->modulename))) {
                return $this->template;
            }
            if (! $instance = $DB->get_record($this->modulename, array('id' => $cm->instance))) {
                return $this->template;
            }
            if (! $course = $DB->get_record('course', array('id' => $instance->course))) {
                return $this->template;
            }
            $this->template = new workshop($instance, $cm, $course);
        }
        return $this->template;
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
        $time = time();
        $msg = array();
        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            $cm = $this->get_cm($msg, $data, $time, 'targetworkshop');
        }

        if ($cm) {

            // cache the database id
            $databasenum = $data->sourcedatabase;
            $dataid = get_fast_modinfo($this->course)->get_cm($databasenum)->instance;

            // cache the workshop cmid
            $workshopnum  = $data->targetworkshopnum;

            // initialize counters
            $counttotal = $DB->get_field('data_records', 'COUNT(*)', array('dataid' => $dataid));
            $countselected = 0;
            $counttransferred = 0;

            // get workshop object
            $workshop = $DB->get_record('workshop', array('id' => $cm->instance));
            $workshop = new workshop($workshop, $cm, $this->course);

            // get ids of anonymous authors
            $params = array('groupid' => $data->anonymousauthors);
            $anonymous = $DB->get_records_menu('groups_members', $params, null, 'id,userid');

			// specifiy the presentation fields that we want
			$fields = array('presentation_title' => '',
							'presentation_type' => '',
							'presentation_language' => '',
							'presentation_keywords' => '',
							'charcount' => 0,
							'wordcount' => 0,
							'presentation_abstract' => '');

			// get all records matching the filters (may update $data and $fields)
            if ($records = $this->get_filtered_records($dataid, $data, $fields)) {

				$duplicaterecords = array();
				$duplicateauthors = array();
				$duplicatesubmissions = array();

				// sanitize submission titles (and remove duplicates)
				foreach ($records as $id => $record) {
					if (empty($record->title)) {
						$title = get_string('notitle', $this->plugin, $record->recordid);
					} else {
						$title = block_maj_submissions::plain_text($record->title);
					}
					if (array_key_exists($title, $duplicaterecords)) {
						$duplicaterecords[$title]++;
						unset($records[$id]);
					} else {
						$duplicaterecords[$title] = 0;
						$records[$id]->title = $title;
					}
				}

				// remove duplicate authors and submissions
				if (empty($data->resetsubmissions)) {

					// we should exclude authors who already have a submission,
					// because the workshop module allows only ONE submission per user.
					if ($submissions = $workshop->get_submissions('all', $data->anonymousauthors)) {
						foreach ($submissions as $submission) {
							$id = array_search($submission->authorid, $anonymous);
							if (is_numeric($id)) {
								$duplicateauthors[] = $submission->authorid;
								unset($anonymous[$id]);
							}
						}
					}

					// skip database records that already exist in the workshop
					foreach ($records as $id => $record) {
						$title = $record->title;
						if (array_key_exists($title, $duplicatesubmissions)) {
							$duplicatesubmissions[$title]++;
							unset($records[$id]);
						} else if ($DB->record_exists('workshop_submissions', array('title' => $title, 'workshopid' => $cm->instance))) {
							$duplicatesubmissions[$title] = 1;
							unset($records[$id]);
						} else {
							$duplicatesubmissions[$title] = 0;
						}
					}
				}

				if ($count = count($duplicateauthors)) {
					$msg[] = get_string('duplicateauthors', $this->plugin, $count);
				}

				$duplicaterecords = array_filter($duplicaterecords);
				if ($count = count($duplicaterecords)) {
					$a = html_writer::alist(array_keys($duplicaterecords));
					$a = (object)array('count' => $count,'list' => $a);
					$msg[] = get_string('duplicaterecords', $this->plugin, $a);
				}

				$duplicatesubmissions = array_filter($duplicatesubmissions);
				if ($count = count($duplicatesubmissions)) {
					$a = html_writer::alist(array_keys($duplicatesubmissions));
					$a = (object)array('count' => $count,'list' => $a);
					$msg[] = get_string('duplicatesubmissions', $this->plugin, $a);
				}

                $countanonymous = count($anonymous);
                $countselected = count($records);

                if ($countanonymous < $countselected) {
                    $a = (object)array('countanonymous' => $countanonymous,
                                       'countselected' => $countselected);
                    $msg[] = get_string('toofewauthors', $this->plugin, $a);
                } else {

                    // select only the required number of authors and shuffle them randomly
                    $anonymous = array_slice($anonymous, 0, $countselected);
                    shuffle($anonymous);

                    // get/create id of "peer_review_link" field
                    $peer_review_link_fieldid = self::peer_review_link_fieldid($this->plugin, $dataid);

                    // do we want to overwrite previous peer_review_links ?
                    if ($workshopnum==self::CREATE_NEW) {
                        $overwrite_peer_review_links = true;
                    } else if ($data->resetsubmissions) {
                        $overwrite_peer_review_links = true;
                    } else {
                        $overwrite_peer_review_links = false;
                    }

                    // transfer settings from $template to $workshop
                    if ($template = $this->get_template($data)) {
                        // transfer grading strategy (e.g. rubric)
                        $strategy = $template->grading_strategy_instance();
                        $formdata = $this->get_strategy_formdata($strategy);
                        $formdata->workshopid = $workshop->id;
                        $strategy = $workshop->grading_strategy_instance();
                        $strategy->save_edit_strategy_form($formdata);
                    }

                    // reset workshop (=remove previous submissions), if necessary
                    if (isset($data->resetsubmissions) && $data->resetsubmissions) {
                        if ($count = $workshop->count_submissions()) {
                            $reset = (object)array(
                                // mimic settings from course reset form
                                'reset_workshop_assessments' => 1,
                                'reset_workshop_submissions' => 1,
                                'reset_workshop_phase' => 1
                            );
                            $workshop->reset_userdata($reset);
                            $msg[] = get_string('submissionsdeleted', $this->plugin, $count);
                        }
                    }

                    // switch workshop to ASSESSMENT phase
                    $workshop->switch_phase(workshop::PHASE_ASSESSMENT);

                    // transfer submission records from database to workshop
                    foreach ($records as $record) {

                        // sanitize submission abstract
                        $name = 'abstract';
                        if (empty($record->$name)) {
                            $record->$name = get_string('noabstract', $this->plugin);
                        } else {
                            $record->$name = block_maj_submissions::plain_text($record->$name);
                            if (substr_count($record->abstract, ' ') > 2) {
                                $record->wordcount = str_word_count($record->abstract);
                            }
                            $record->charcount = block_maj_submissions::textlib('strlen', $record->abstract);
                        }

                        // create content for this submission
                        $content = '';
                        foreach ($fields as $name => $field) {
                            if (isset($record->$name)) {
                                if ($name=='abstract') {
                                    $params = array('style' => 'text-align: justify; '.
                                                               'text-indent: 20px;');
                                    $content .= html_writer::tag('p', $record->$name, $params);
                                } else if ($name=='charcount' || $name=='wordcount' && $record->$name) {
                                    if ($record->$name > 0) {
                                        $fieldname = $this->instance->get_string($name, $this->plugin);
                                        $fieldname = html_writer::tag('b', $fieldname.': ');
                                        $content .= html_writer::tag('p', $fieldname.$record->$name);
                                    }
                                } else {
                                    $fieldname = self::convert_to_multilang($field, $config);
                                    $fieldname = html_writer::tag('b', $fieldname.': ');
                                    $fieldvalue = block_maj_submissions::plain_text($record->$name);
                                    $fieldvalue = self::convert_to_multilang($fieldvalue, $config);
                                    $content .= html_writer::tag('p', $fieldname.$fieldvalue);
                                }
                            }
                        }

                        // create new submission record
                        $submission = (object)array(
                            'workshopid' => $cm->instance,
                            'authorid' => array_shift($anonymous),
                            'timecreated' => $time,
                            'timemodified' => $time,
                            'title' => $record->title,
                            'content' => $content,
                            'contentformat' => 0,
                            'contenttrust' => 0
                        );

                        // add submission to workshop (dupicates have already been removed)
                        if ($submission->id = $DB->insert_record('workshop_submissions', $submission)) {

                            // add reference to this submission from the database record
                            $link = $workshop->submission_url($submission->id)->out(false);

                            $params = array('fieldid'  => $peer_review_link_fieldid,
                                            'recordid' => $record->recordid);
                            if ($content = $DB->get_record('data_content', $params)) {
                                if (empty($content->content) || $overwrite_peer_review_links) {
                                    $content->content = $link;
                                    $DB->update_record('data_content', $content);
                                }
                            } else {
                                $content = (object)array(
                                    'fieldid'  => $peer_review_link_fieldid,
                                    'recordid' => $record->recordid,
                                    'content'  => $link
                                );
                                $content->id = $DB->insert_record('data_content', $content);
                            }
                            $counttransferred++;
                        }
                    }
                    $a = (object)array('total' => $counttotal,
                                       'selected' => $countselected,
                                       'transferred' => $counttransferred);
                    $msg[] = get_string('submissionstranferred', $this->plugin, $a);
                }
            }
        }

        return $this->form_postprocessing_msg($msg);
    }

    /**
     * get_strategy_formdata
     *
     * @param object $strategy
     */
    protected function get_strategy_formdata($strategy) {
        $mform = $strategy->get_edit_strategy_form();
        $mform = $mform->_form;

        // initialize form $data object
        $data = new stdClass();

        // add hidden fields
        $names = array('workshopid',
                       'strategy',
                       'norepeats',
                       'sesskey',
                       '_qf__workshop_edit_rubric_strategy_form');
        foreach ($names as $name) {
            $data->$name = $mform->getElement($name)->getValue();
        }

        // add criteria
        $x = 0;
        while ($mform->elementExists("dimension$x")) {

            // set the dimensionid to 0, to force creation of a new record
            // $data->$name = $mform->getElement($name)->getValue();
            $name = "dimensionid__idx_{$x}";
            $data->$name = 0;

            // fetch criterion description
            $name = "description__idx_{$x}_editor";
            $element = $mform->getElement($name);
            $data->$name = $element->getValue();

            // fetch criterion levels
            $y = 0;
            while ($mform->elementExists("levelid__idx_{$x}__idy_{$y}")) {

                // set the levelid to 0, to force creation of a new record
                // $data->$name = $mform->getElement($name)->getValue();
                $name = "levelid__idx_{$x}__idy_{$y}";
                $data->$name = 0;

                // get grade and definition(format) for each level
                $group = $mform->getElement("level__idx_{$x}__idy_{$y}");
                foreach ($group->getElements() as $element) {
                    $name = $element->getName();
                    $value = $element->getValue();
                    if (is_array($value)) {
                        $value = reset($value);
                    }
                    $data->$name = $value;
                }
                $y++;
            }

            $name = "numoflevels__idx_{$x}";
            $data->$name = $y;

            $x++;
        }

        $name = 'config_layout';
        $group = $mform->getElement('layoutgrp');
        foreach ($group->getElements() as $element) {
            if ($name==$element->getName() && $element->getChecked()) {
                $data->$name = $element->getValue();
            }
        }

        // add submit button
        $group = $mform->getElement('buttonar');
        foreach ($group->getElements() as $element) {
            $name = $element->getName();
            if ($name=='saveandclose') {
                $data->$name = $element->getValue();
            }
        }

        return $data;
    }
}
