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
 * block_maj_submissions_tool_setupvetting
 *
 * @copyright 2017 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class block_maj_submissions_tool_setupvetting extends block_maj_submissions_tool_form {

    //protected $availability = (object)array(
    //    'op'    => '&',
    //    'c'     => array((object)array('type' => 'group', 'id' => 0)),
    //    'showc' => array(true),
    //    'show'  => false
    //);

    protected $modulename = 'page';
    protected $defaultname = 'reviewersloginpage';
    protected $defaultvalues = array(
        'intro' => '',
        'introformat' => FORMAT_HTML, // =1
        'content' => '',
        'contentformat' => FORMAT_HTML, // =1,
        'display' => 0, // = RESOURCELIB_DISPLAY_AUTO
        'displayoptions' => array('printheading' => 0, 'printintro' => 0)
    );

    /**
     * The name of the form field containing
     * the id of a group of anonymous assessors
     */
    protected $groupfieldname = 'anonymousreviewers';

    /**
     * definition
     */
    public function definition() {
        $mform = $this->_form;

        $name = 'targetworkshop';
        $options = self::get_cmids($mform, $this->course, $this->plugin, 'workshop');
        $this->add_field($mform, $this->plugin, $name, 'selectgroups', PARAM_INT, $options, 0);

        $name = 'reviewers';
        $options = $this->get_group_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'anonymousreviewers';
        $options = $this->get_group_options();
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, $options);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'resetpasswords';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'reviewspersubmission';
        $this->add_field($mform, $this->plugin, $name, 'select', PARAM_INT, range(0, 10), 3);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'resetassessments';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'sendername';
        $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $name = 'senderemail';
        $this->add_field($mform, $this->plugin, $name, 'text', PARAM_TEXT);
        $mform->disabledIf($name, 'targetworkshop', 'eq', 0);

        $this->add_action_buttons();
    }

    /**
     * Perform extra validation on form values.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        global $DB;
        $errors = array();

        $countreviewers = null;
        $countanonymous = null;

        $name = 'targetworkshop';
        if (empty($data[$name])) {
            $errors[$name] = get_string('requiredelement', 'form');
        } else {
            $name = 'reviewers';
            if (array_key_exists($name, $data)) {
                $params = array('groupid' => $data[$name]);
                $countreviewers = $DB->get_field('groups_members', 'COUNT(*)', $params);
            }
            if ($countreviewers===null) {
                $errors[$name] = get_string('requiredelement', 'form');
            } else if ($countreviewers===false || $countreviewers===0 || $countreviewers==='0') {
                $errors[$name] = get_string('toofewmembers', $this->plugin);
            }

            $name = 'anonymousreviewers';
            if (array_key_exists($name, $data)) {
                $params = array('groupid' => $data[$name]);
                $countanonymous = $DB->get_field('groups_members', 'COUNT(*)', $params);
            }
            if ($countanonymous===null) {
                $errors[$name] = get_string('requiredelement', 'form');
            } else if ($countanonymous===false || $countanonymous===0 || $countanonymous==='0') {
                $errors[$name] = get_string('toofewmembers', $this->plugin);
            }

            if ($countanonymous && $countreviewers && $countanonymous < $countreviewers) {
                // number of anonymous reviewers is less than the number of real reviewers
                $a = (object)array(
                    'countanonymous' => $countanonymous,
                    'countreviewers' => $countreviewers,
                );
                $errors[$name] = get_string('toofewreviewers', $this->plugin, $a);
            }
        }

        return $errors;
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

        $cm = null;
        $msg = array();
        $time = time();

        $config = $this->instance->config;

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {
            if ($cm = $data->targetworkshop) {
                $cm = get_fast_modinfo($this->course)->get_cm($cm);
            }
            if ($reviewers = $data->reviewers) {
                $params = array('groupid' => $reviewers);
                $reviewers = $DB->get_records_menu('groups_members', $params, null, 'id,userid');
            }
            if ($anonymous = $data->anonymousreviewers) {
                $params = array('groupid' => $anonymous);
                $anonymous = $DB->get_records_menu('groups_members', $params, null, 'id,userid');
            }
            $resetpasswords = $data->resetpasswords;
            $resetassessments = $data->resetassessments;
            $reviewspersubmission = $data->reviewspersubmission;
        } else {
            $reviewers = null;
            $anonymous = null;
            $resetpasswords = false;
            $resetassessments = false;
            $reviewspersubmission = 0;
        }

        $countreviewers = (empty($reviewers) ? 0 : count($reviewers));
        $countanonymous = (empty($anonymous) ? 0 : count($anonymous));

        if ($cm && $countreviewers && ($countanonymous >= $countreviewers)) {

            // get workshop object for this $cm
            $workshop = $DB->get_record('workshop', array('id' => $cm->instance));
            $workshop = new workshop($workshop, $cm, $this->course);

            // select only the required number of anonymous reviewers
            $anonymous = array_slice($anonymous, 0, $countreviewers);
            $countanonymous = $countreviewers;

            if ($anonymous===false) {
                $anonymous = array(); // shouldn't happen !!
            }

            // shuffle reviewerids so that they are mapped randomly to anonymous users
            shuffle($reviewers);

            // map $anonymous reviewers onto real $reviewers
            $reviewers = array_combine($anonymous, $reviewers);

            // map anonymous reviewers to simple object
            // (realuserid, review and reviewcount)
            foreach ($reviewers as $userid => $realuserid) {
                if ($resetpasswords) {
                    // random string generator in "lib/moodlelib.php"
                    $password = random_string(8);
                    $DB->set_field('user', 'password', md5($password), array('id' => $userid));
                } else {
                    $password = '';
                }
                $reviewers[$userid] = (object)array(
                    'realuser'     => $DB->get_record('user', array('id' => $realuserid)),
                    'password'     => $password,
                    'reviews'      => array(),
                    'countreviews' => 0
                );
            }

            $submissions = $DB->get_records('workshop_submissions', array('workshopid' => $workshop->id), null, 'id,authorid');
            if ($submissions===false) {
                $submissions = array();
            }

            $reviewfields = array();

            $reset = (object)array(
                'submissionids' => array(),
                'datarecordids' => array()
            );

            foreach ($submissions as $sid => $submission) {
                $submissions[$sid]->countreviews = 0;
                $submissions[$sid]->reviews = array();

                $assessments = $DB->get_records('workshop_assessments', array('submissionid' => $sid));
                if ($assessments===false) {
                    $assessments = array();
                }

                if ($resetassessments) {
                    // reset workshop (=remove previous assessments and grades), if necessary
                    foreach (array_keys($assessments) as $aid) {
                        $DB->delete_records('workshop_grades', array('assessmentid' => $aid));
                    }
                    $DB->delete_records('workshop_assessments', array('submissionid' => $sid));
                    $DB->set_field('workshop_submissions', 'grade', null, array('id' => $sid));
                    $reset->submissionids[] = $sid;

                } else {
                    foreach ($assessments as $aid => $assessment) {
                        $rid = $assessment->reviewerid;
                        if (empty($reviewers[$rid])) {
                            // user is not in the anonymous reviewer group
                            // probably left over from a previous vetting
                        } else {
                            $reviewers[$rid]->countreviews++;
                            $reviewers[$rid]->reviews[$sid] = $aid;
                        }
                        $submissions[$sid]->countreviews++;
                        $submissions[$sid]->reviews[$rid] = $aid;
                    }
                }
                unset($assessments);

                // initialize reviewerauthorids for this submission
                $submissions[$sid]->reviewerauthorids = array();

                // get database records that link to this submission
                if ($records = self::get_database_records($workshop, $sid)) {

                    // cache real authorids if for authors who are also reviewers
                    $record = reset($records);
                    $submissions[$sid]->reviewerauthorids = self::get_reviewerauthorids($record, $reviewers);

                    if ($resetassessments) {
                        foreach ($records as $record) {

                            // get peer_review fields for this dataid
                            if (! array_key_exists($record->dataid, $reviewfields)) {
                                $select = 'dataid = ? AND name IN (?, ?, ?)';
                                $params = array($record->dataid, 'peer_review_score',
                                                                 'peer_review_details',
                                                                 'peer_review_notes');
                                if ($reviewfields[$record->dataid] = $DB->get_records_select_menu('data_fields', $select, $params, null, 'name,id')) {
                                    $reviewfields[$record->dataid] = $DB->get_in_or_equal($reviewfields[$record->dataid]);
                                }
                            }

                            // reset content for peer_review fields with this recordid
                            if ($reviewfields[$record->dataid]) {
                                list($select, $params) = $reviewfields[$record->dataid];
                                $select = "fieldid $select AND recordid = ?";
                                $params[] = $record->recordid;
                                $DB->set_field_select('data_content', 'content', '', $select, $params);
                                $reset->datarecordids[] = $record->recordid;
                            }
                        }
                    }
                }
                unset($records, $record);
            }

            if ($count = count($reset->submissionids)) {
                sort($reset->submissionids);
                $a = (object)array(
                    'count' => $count,
                    'ids' => implode(', ', $reset->submissionids)
                );
                $msg[] = get_string('submissiongradesreset', $this->plugin, $a);
            }

            if ($count = count($reset->datarecordids)) {
                sort($reset->datarecordids);
                $a = (object)array(
                    'count' => $count,
                    'ids' => implode(', ', $reset->datarecordids)
                );
                $msg[] = get_string('datarecordsreset', $this->plugin, $a);
            }

            // switch workshop to EVALUATION phase
            $workshop->switch_phase(workshop::PHASE_EVALUATION);

            if (empty($data->reviewspersubmission)) {
                $countreviews = $countreviewers;
            } else {
                $countreviews = $data->reviewspersubmission;
            }

            // Allocate reviewers to submission as necessary.
            foreach (array_keys($submissions) as $sid) {
                $new = array();
                while ($submissions[$sid]->countreviews < $countreviews) {
                    if (! $this->add_reviewer($workshop, $sid, $submissions, $reviewers, $new)) {
                        break; // could not add reviewer for some reason
                    }
                }
                if ($count = count($new)) {
                    sort($new);
                    $a = (object)array(
                        'sid'   => $sid,
                        'count' => $count,
                        'ids'   => implode(', ', $new)
                    );
                    $msg[] = get_string('reviewersadded', $this->plugin, $a);
                }
            }

            // set subject for emails
            $subject = get_string('reviewersubject', $this->plugin, $config->conferencenameen);

            // get reply address for message
            if (class_exists('core_user')) {
                // Moodle >= 2.6
                $noreply = core_user::get_noreply_user();
            } else {
                // Moodle <= 2.5
                $noreply = generate_email_supportuser();
            }

            if (empty($data->sendername)) {
                $data->sendername = fullname($USER);
            }
            if (empty($data->senderemail)) {
                $data->senderemail = $USER->email;
            }
            if (empty($config->reviewteamname)) {
                $config->reviewteamname = '提出審査委員 Submission review team';
            }
            if (empty($config->conferencename)) {
                $config->conferencename = $config->conferencenameen;
            }

            // initialize values for email message
            $a = (object)array(
                'reviewer'       => '', // added later
                'organization'   => $DB->get_field('course', 'fullname', array('id' => SITEID)),
                'workshopurl'    => $workshop->view_url()->out(),
                'username'       => '', // added later
                'password'       => '', // added later
                'deadline'       => userdate($config->reviewtimefinish),
                'senderfullname' => $data->sendername,
                'senderemail'    => $data->senderemail,
                'conferencename' => $config->conferencename,
                'reviewteamname' => $config->reviewteamname
            );

            // create resource showing real/anon users and login passwords if available
            $table = new html_table();
            $table->cellpadding = 4;
            $table->cellspacing = 4;

            $cells = array('realuser',
                           'anonymoususername',
                           'anonymouspassword',
                           'submissionscount',
                           'submissionslist');

            if (empty($resetpasswords)) {
                $i = array_search('anonymouspassword', $cells);
                $cells = array_splice($cells, $i, 1);
            }

            // add headers
            foreach ($cells as $cell) {
                $cell = get_string($cell, $this->plugin);
                $cell = new html_table_cell($cell);
                $table->head[] = $cell;
            }

            // fetch and sort anonymous reviewers' usernames
            list($select, $params) = $DB->get_in_or_equal(array_keys($reviewers));
            $usernames = $DB->get_records_select_menu('user', "id $select", $params, null, 'id,username');
            asort($usernames);

            foreach ($usernames as $userid => $username) {
                $row = new html_table_row();

                // shortcut to $reviewer
                $reviewer = $reviewers[$userid];

                // link to the real user
                $link = '/user/view.php';
                $link = new moodle_url($link, array('id' => $reviewer->realuser->id, 'course' => $cm->course));
                $link = html_writer::link($link, fullname($reviewer->realuser), array('target' => '_blank'));
                $row->cells[] = new html_table_cell($link);

                // the anonymmous username
                $row->cells[] = new html_table_cell($username);

                // the anonymmous password (if required)
                if ($resetpasswords) {
                    $row->cells[] = new html_table_cell($reviewer->password);
                }

                // the number of submissions to review
                $row->cells[] = new html_table_cell($reviewer->countreviews);

                // links to the submissions
                $cell = array();
                foreach ($reviewer->reviews as $sid => $aid) {
                    $link = '/mod/workshop/assessment.php';
                    $link = new moodle_url($link, array('asid' => $aid));
                    $link = html_writer::link($link, $aid, array('target' => '_blank'));
                    $cell[] = $link.': '.$DB->get_field('workshop_submissions', 'title', array('id' => $sid));
                }
                $cell = html_writer::alist($cell, null, 'ol');
                $row->cells[] = new html_table_cell($cell);

                $table->data[] = $row;

                // send email to reviewer, if this Moodle site sends email
                if (empty($CFG->noemailever)) {
                    $a->reviewer = fullname($reviewer->realuser);
                    $a->username = $username;
                    $a->password = $reviewer->password;
                    $messagetext = get_string('reviewerinstructions', $this->plugin, $a);
                    $messagehtml = format_text($messagetext, FORMAT_MOODLE);
                    email_to_user($reviewer->realuser, $noreply, $subject, $messagetext, $messagehtml);
                }

				// TODO: setup a reviewers forum
				// TODO: subscribe all reviewers to the forum
            }

            // create a page resource
            if (count($table->data)) {
                $a = array();
                $search = '/<span\b[^>]*lang="([^"]+)"[^>]*>(.*?)<\/span>/';
                if (preg_match_all($search, $workshop->name, $spans)) {
                    // generate multilang params for "reviewersloginpage"
                    $search = array('/^.*\((.*?)\)$/', // 1-byte trailing "()"
                                    '/^.*\[(.*?)\]$/', // 1-byte trailing "[]"
                                    '/^.*\{(.*?)\}$/', // 1-byte trailing "{}"
                                    '/^.*（(.*?)）$/',  // 2-byte trailing "（）"
                                    '/^.*『(.*?)』$/',  // 2-byte trailing "『』"
                                    '/^.*「(.*?)」$/'); // 2-byte trailing "「」"
                    $i_max = count($spans[0]);
                    for ($i=0; $i<$i_max; $i++) {
                        $lang = $spans[1][$i];
                        $span = $spans[2][$i];
                        $count = 0;
                        $span = preg_replace($search, '$1', $span, -1, $count);
                        if ($count) {
                            $a[$lang] = $span;
                        }
                    }
                }
                $resourcedata = (object)array(
                    'resourcenum' => self::CREATE_NEW,
                    'resourcename' => '',
                    'coursesectionnum' => $cm->sectionnum,
                    'coursesectionname' => '',
                    'content' => html_writer::table($table),
                    'visible' => 0
                );
                $this->get_cm($msg, $resourcedata, $time, 'resource', $a);
            }
        }

        return $this->form_postprocessing_msg($msg);
    }

    /**
     * add_reviewer, while observing the following limitations:
     * (1) reviewers should be assigned an equal number of submissions
     * (2) reviewers should not be assigned more than once to the same submission
     * (3) real reviewers should not be not assigned to their own submissions
     *     i.e. they can be neither the main presenter nor a co-presenter
     *
     * @param integer submission id
     * @param object  (passed by reference) $submission
     * @param array   (passed by reference) ids of $anonymous reviewers
     * @param array   (passed by reference) map submision authors to $realathours
     * @param array   (passed by reference) $msg strings
     * @return boolean,  and may also update the $submissions and $reviewers arrays
     */
    protected function add_reviewer($workshop, $sid, &$submissions, &$reviewers, &$new) {
        uasort($reviewers, array(get_class($this), 'sort_reviewers'));
        foreach ($reviewers as $rid => $reviewer) {
            if (array_key_exists($rid, $submissions[$sid]->reviews)) {
                continue; // reviewer is already reviewing this submission
            }
            if (in_array($reviewer->realuser->id, $submissions[$sid]->reviewerauthorids)) {
                continue; // reviewers cannot review their own submissions
            }
            $aid = $workshop->add_allocation($submissions[$sid], $rid);
            if ($aid===false || $aid==workshop::ALLOCATION_EXISTS) {
                continue; // unusual - could not add new allocation
            }
            $new[] = $rid;
            $reviewers[$rid]->countreviews++;
            $reviewers[$rid]->reviews[$sid] = $aid;
            $submissions[$sid]->countreviews++;
            $submissions[$sid]->reviews[$rid] = $aid;
            return true;
        }
        return false;
    }

    /**
     * sort_reviewers
     *
     * @param object $a
     * @param object $b
     * @return integer if ($a < $b) -1; if ($a > $b) 1; Otherwise, 0.
     */
    static public function sort_reviewers($a, $b) {
        if ($a->countreviews < $b->countreviews) {
            return -1;
        }
        if ($a->countreviews > $b->countreviews) {
            return 1;
        }
        return mt_rand(-1, 1); // random shuffle
    }

    /**
     * is_reviewer_userid
     *
     * @param integer $userid
     * @param array   $reviewers
     * @return boolean TRUE if $authorid is a
     */
    static public function is_reviewer_userid($userid, $reviewers) {
        foreach ($reviewers as $reviewer) {
            if ($reviewer->realuser->id==$userid) {
                return $reviewer->realuser->id;
            }
        }
        return false;
    }

    /**
     * is_reviewer_name
     *
     * @param object  $name
     * @param array   $reviewers
     * @return boolean TRUE if $authorid is a
     */
    static public function is_reviewer_name($name, $reviewers, $mainauthorid) {
        foreach ($reviewers as $reviewer) {
            // skip main author
            if ($reviewer->realuser->id==$mainauthorid) {
                continue;
            }
            // compare first/last name
            if ($firstname = trim($reviewer->realuser->firstname)) {
                $firstname = block_maj_submissions::textlib('strtoupper', $firstname);
            }
            if ($firstname==$name->firstname) {
                if ($lastname = trim($reviewer->realuser->lastname)) {
                    $lastname = block_maj_submissions::textlib('strtoupper', $lastname);
                }
                if ($lastname==$name->lastname) {
                    return $reviewer->realuser->id;
                }
            }
            // compare phonetic first/last name
            if ($firstname = trim($reviewer->realuser->firstnamephonetic)) {
                $firstname = block_maj_submissions::textlib('strtoupper', $firstname);
            }
            if ($firstname==$name->firstname) {
                if ($lastname = trim($reviewer->realuser->lastnamephonetic)) {
                    $lastname = block_maj_submissions::textlib('strtoupper', $lastname);
                }
                if ($lastname==$name->lastname) {
                    return $reviewer->realuser->id;
                }
            }
        }
        return false;
    }

    /**
     * get_defaultvalues
     *
     * @param object $data from newly submitted form
     * @param integer $time
     */
    protected function get_defaultvalues($data, $time) {
        $defaultvalues = parent::get_defaultvalues($data, $time);

        $name = 'displayoptions';
        if (is_array($defaultvalues[$name])) {
            $defaultvalues[$name] = serialize($defaultvalues[$name]);
        }

        $name = 'content';
        if (isset($data->$name)) {
            $defaultvalues[$name] = $data->$name;
        }

        return $defaultvalues;
    }

    /**
     * get_database_records
     *
     * get records from database activites that link to
     * the specified submission in the specified workshop
     *
     * @uses $DB
     * @param object $record from a submissions database
     * @return array $userids of co-authors (may be empty)
     */
    static public function get_reviewerauthorids($record, $reviewers) {
        global $DB;
        $reviewerauthors = array();

        // add main author, if (s)he is a reviewer
        if ($userid = self::is_reviewer_userid($record->userid, $reviewers)) {
            $reviewerauthors[$userid] = true;
        }

        $select = 'dc.id, dc.content AS content, df.name AS fieldname';
        $from   = '{data_content} dc JOIN {data_fields} df ON dc.fieldid = df.id';

        $where = 'dc.recordid = :recordid'.
                 ' AND dc.content IS NOT NULL'.
                 ' AND dc.content <> :emptystring'.
                 ' AND ('.$DB->sql_like('df.name', ':firstname').
                        ' OR '.$DB->sql_like('df.name', ':lastname').')';

        $params = array('recordid' => $record->recordid,
                        'emptystring' => '',
                        'firstname' => 'name_given_%',
                        'lastname' => 'name_surname_%');

        $contents = "SELECT $select FROM $from WHERE $where";
        if ($contents = $DB->get_records_sql($contents, $params)) {
            $names = array();
            foreach ($contents as $content) {
                $i = 0;
                $name = '';
                $type = '';
                $lang = 'xx';
                $parts = explode('_', $content->fieldname);
                switch (count($parts)) {
                    case 2:
                        list($name, $type) = $parts;
                        break;
                    case 3:
                        if (is_numeric($parts[2])) {
                            list($name, $type, $i) = $parts;
                        } else {
                            list($name, $type, $lang) = $parts;
                        }
                        break;
                    case 4:
                        if (is_numeric($parts[2])) {
                            list($name, $type, $i, $lang) = $parts;
                        } else {
                            list($name, $type, $lang, $i) = $parts;
                        }
                        break;
                }
                switch ($type) {
                    case 'given': $type = 'firstname'; break;
                    case 'surname': $type = 'lastname'; break;
                }
                if (empty($names[$i.'_'.$lang])) {
                    $names[$i.'_'.$lang] = new stdClass();
                }
                $names[$i.'_'.$lang]->$type = block_maj_submissions::textlib('strtoupper', $content->content);
            }

            // add any co-authors, excluding main author, who are also reviewers
            foreach ($names as $name) {
                if ($userid = self::is_reviewer_name($name, $reviewers, $record->userid)) {
                    $reviewerauthors[$userid] = true;
                }
            }
        }
        return array_keys($reviewerauthors);
    }
}
