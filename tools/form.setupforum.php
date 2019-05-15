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

abstract class block_maj_submissions_tool_setupforum extends block_maj_submissions_tool_form {

    protected $type = '';
    protected $modulename = 'forum';
    protected $defaultvalues = array(
        'visible'          => 1,  // course_modules.visible
        'intro'            => '', // see set_defaultintro()
        'introformat'      => FORMAT_HTML, // =1
        'type'             => 'general',
        'assessed'         => 0,
        'accesstimestart'  => 0,
        'accesstimefinish' => 0,
        'scale'            => 100,
        'maxbytes'         => 0,
        'maxattachments'   => 9
    );
    protected $timefields = array(
        'timestart' => array('accesstimestart'),
        'timefinish' => array('accesstimefinish')
    );
    protected $groupfieldnames = 'programcommittee,forumgroup';
    protected $forumfieldname = 'forumactivity';

    /**
     * definition
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;

        $maxbytes = array(get_config(null, 'maxbytes'),
                          $this->course->maxbytes,
                          get_config(null, 'forum_maxbytes'));
        $maxbytes = array_filter($maxbytes);
        $maxbytes = (count($maxbytes) ? max($maxbytes) : 0);

        // extract the module context and course section, if possible
        if ($this->cmid) {
            $context = block_maj_submissions::context(CONTEXT_MODULE, $this->cmid);
            $sectionnum = get_fast_modinfo($this->course)->get_cm($this->cmid)->sectionnum;
        } else {
            $context = $this->course->context;
            $sectionnum = 0;
        }

        $this->add_group_fields($mform);
        foreach ($this->groupfieldnames as $name) {
            $mform->disabledIf($name, $this->forumfieldname, 'eq', 0);
        }

        $name = $this->forumfieldname;
        $this->add_field_cm($mform, $this->course, $this->plugin, $name, $this->cmid);
        $this->add_field_section($mform, $this->course, $this->plugin, 'coursesection', $name, $sectionnum);

        $name = 'resetforum';
        $this->add_field($mform, $this->plugin, $name, 'selectyesno', PARAM_INT);
        $mform->disabledIf($name, $this->forumfieldname.'num', 'eq', 0);
        $mform->disabledIf($name, $this->forumfieldname.'num', 'eq', self::CREATE_NEW);

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
        $errors = array();

        $require_activity = true;
        $require_section = false;

        if ($require_activity) {
            $name = $this->forumfieldname;
            $group = 'group_'.$name;
            $num = $name.'num';
            $name = $name.'name';
            if (empty($data[$num])) {
                $errors[$group] = get_string("missing$num", $this->plugin);
            } else if ($data[$num]==self::CREATE_NEW) {
                if (empty($data[$name])) {
                    $errors[$group] = get_string("missing$name", $this->plugin);
                }
                $require_section = true;
            }
        }

        if ($require_section) {
            $name = 'coursesection';
            $group = 'group_'.$name;
            $num = $name.'num';
            $name = $name.'name';
            if ($data[$num]=='') { // section 0 is allowed
                $errors[$group] = get_string("missing$num", $this->plugin);
            } else if ($data[$num]==self::CREATE_NEW) {
                if (empty($data[$name])) {
                    $errors[$group] = get_string("missing$name", $this->plugin);
                }
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
        global $CFG, $DB;

        require_once($CFG->dirroot.'/mod/forum/lib.php');
		require_once($CFG->dirroot.'/rating/lib.php');

        $cm = false;
        $msg = array();
        $time = time();

		$groupid = 0;
		$groupname = '';

        // get/create the $cm record and associated $section
        if ($data = $this->get_data()) {

            if (empty($data->resetforum)) {
            	$data->resetforum = 0;
            } else {
                // remove ALL access restrictions first
                //$DB->set_field('course_modules', 'availability', '', array('id' => $cm->id));
            }

            $cm = $this->get_cm($msg, $data, $time, $this->forumfieldname);
        }

        if ($cm) {

            $ids = array();
            $countnew = 0;
            $countold = 0;
            $countusers = 0;

			// fetch forum object
			$forum = $DB->get_record('forum', array('id' => $cm->instance), '*', MUST_EXIST);
			$forum->cmidnumber = (empty($cm->idnumber) ? '' : $cm->idnumber);
			$forum->courseid = $forum->course;
			$forum->instance = $forum->id;

            if ($data->resetforum) {
                if ($ids = $DB->get_records('forum_discussions', array('forum' => $cm->instance), '', 'id')) {
                    $ids = array_keys($ids);
                    $DB->delete_records_list('forum_posts', 'discussion', $ids);
                    $DB->delete_records_list('forum_queue', 'discussionid', $ids);
                    $DB->delete_records_list('forum_discussion_subs', 'discussion', $ids);
                }
                $DB->delete_records('forum_digests',       array('forum' => $cm->instance));
                $DB->delete_records('forum_discussions',   array('forum' => $cm->instance));
                $DB->delete_records('forum_read',          array('forumid' => $cm->instance));
                $DB->delete_records('forum_track_prefs',   array('forumid' => $cm->instance));

                // fetch forum context
                $context = context_module::instance($cm->id);

                // remove all attachments
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_forum', 'post');
                $fs->delete_area_files($context->id, 'mod_forum', 'attachment');

                // remove tags (not usually necessary)
                core_tag_tag::delete_instances('mod_forum', null, $context->id);

                // remove ratings (not usually necessary)
                $rm = new rating_manager();
                $options = (object)array('component'  => 'mod_forum',
                                         'ratingarea' => 'post',
                                         'contextid'  => $context->id);
                $rm->delete_ratings($options);

                // reset gradebook (not usually necessary)
                forum_grade_item_update($forum, 'reset');

                // fetch subscription ids (so they can be reused)
                $ids = $DB->get_records_menu('forum_subscriptions', array('forum' => $cm->instance), 'id', 'id,userid');
                if ($ids==false) {
                	$ids = array();
                }
                $msg[] = get_string('forumreset', $this->plugin, $forum->name);
            }
            if ($groupid = $data->forumgroup) {

				$groupname = groups_get_group_name($groupid);
				$groupname = format_string($groupname);

                if ($userids = $DB->get_records_menu('groups_members', array('groupid' => $groupid), 'id', 'id,userid')) {
					$countusers = count($userids);

					// remove users who are already subscribed
                    foreach ($userids as $id => $userid) {
                    	$i = array_search($userid, $ids);
                    	if (is_numeric($i)) {
                    		$countold++;
                    		unset($ids[$i]);
                    		unset($userids[$id]);
                    	}
                    }

                    // get any remaining subscription record ids
                	$ids = array_keys($ids);

					// subscribe any remaining users
                    foreach ($userids as $id => $userid) {
                        $params = array('forum' => $cm->instance, 'userid' => $userid);
                        if (! $DB->record_exists('forum_subscriptions', $params)) {
                            if (count($ids)) {
                                $params['id'] = array_shift($ids);
                                $DB->update_record('forum_subscriptions', $params);
                            } else {
                                $params['id'] = $DB->insert_record('forum_subscriptions', $params);
                            }
                            $countnew++;
                        }
                    }
                }
            }
            if (count($ids)) {
                $DB->delete_records_list('forum_subscriptions', 'id', $ids);
            }
            if ($countusers) {
                $a = (object)array('new' => $countnew,
                                   'old' => $countold,
                                   'users' => $countusers,
                                   'group' => $groupname,
                                   'forum' => $forum->name);
                $msg[] = get_string('subscribersadded', $this->plugin, $a);
            }
        }

        return $this->form_postprocessing_msg($msg);
    }
}
