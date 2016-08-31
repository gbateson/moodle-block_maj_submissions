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
 * blocks/maj_submissions/edit_form.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * block_maj_submissions_mod_form
 *
 * @copyright 2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage taskchain
 */
class block_maj_submissions_edit_form extends block_edit_form {

    public $modnames = null;
    public $langnames = null;

    /**
     * specific_definition
     *
     * @param object $mform
     * @return void, but will update $mform
     */
    protected function specific_definition($mform) {

        $this->set_form_id($mform, get_class($this));

        // cache the plugin name, because
        // it is quite long and we use it a lot
        $plugin = 'block_maj_submissions';

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'title');
        //-----------------------------------------------------------------------------

        $element = $mform->addElement('static', 'description', get_string('description'), get_string('blockdescription', $plugin));

        $name = 'title';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('text', $config_name, $label, array('size' => 50));
        $mform->setType($config_name, PARAM_TEXT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'state');
        //-----------------------------------------------------------------------------

        $name = 'currentstate';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none'),
            1 => get_string('collect', $plugin),
            2 => get_string('review',  $plugin),
            3 => get_string('revise',  $plugin),
            4 => get_string('publish', $plugin),
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'collectsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'collect');

        $name = 'collectcmid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $this->add_repeat_elements($mform, $plugin, 'filterfields');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'reviewsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'review');

        $name = 'reviewsectionid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $this->add_repeat_elements($mform, $plugin, 'reviewcmids');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'revisesubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'revise');

        $name = 'revisesectionid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $this->add_repeat_elements($mform, $plugin, 'revisecmids');

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'publishsubmissions');
        //-----------------------------------------------------------------------------

        $this->add_time_startfinish($mform, $plugin, 'publish');

        $name = 'publishcmid';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $options = array(
            0 => get_string('none')
        );
        $mform->addElement('select', $config_name, $label, $options);
        $mform->setType($config_name, PARAM_INT);
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);
    }

    /**
     * set_form_id
     *
     * @param  object $mform
     * @param  string $id
     * @return mixed default value of setting
     */
    protected function set_form_id($mform, $id) {
        $attributes = $mform->getAttributes();
        $attributes['id'] = $id;
        $mform->setAttributes($attributes);
    }

    /**
     * get default value for a setting in this block
     *
     * @param  string $name of setting
     * @return mixed default value of setting
     */
    protected function defaultvalue($name) {
        if (isset($this->block->config->$name)) {
            return $this->block->config->$name;
        } else {
            return null;
        }
    }

    /**
     * add_header
     *
     * @param object  $mform
     * @param string  $component
     * @param string  $name of string
     * @param boolean $expanded (optional, default=TRUE)
     * @return void, but will update $mform
     */
    protected function add_header($mform, $component, $name, $expanded=true) {
        $label = get_string($name, $component);
        $mform->addElement('header', $name, $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
        }
    }

    /**
     * add_time_startfinish
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $type
     * @return void, but will update $mform
     */
    protected function add_time_startfinish($mform, $plugin, $type) {
        $name = $type.'timestart';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);

        $name = $type.'timefinish';
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $mform->addElement('date_time_selector', $config_name, get_string($name, $plugin));
        $mform->setDefault($config_name, $this->defaultvalue($name));
        $mform->addHelpButton($config_name, $name, $plugin);
    }

    /**
     * add_repeat_elements
     *
     * @param object  $mform
     * @param string  $plugin
     * @param string  $name
     * @return void, but will update $mform
     */
    protected function add_repeat_elements($mform, $plugin, $name) {
        global $OUTPUT;
        $config_name = 'config_'.$name;
        $label = get_string($name, $plugin);
        $get_options = 'get_options_'.$name;
        $options = $this->$get_options($plugin);
        $element = $mform->createElement('select', $config_name, $label, $options);
        $element->_helpbutton = $OUTPUT->help_icon($name, $plugin, '');
        $options = array($name => array('type' => PARAM_INT));
        $repeats = count($this->block->config->$name);
        $button  = get_string('add'.$name, $plugin, 1);
        $this->repeat_elements(array($element), $repeats, $options, 'count'.$name, 'add'.$name, 1, $button);
    }

    /**
     * get_options_filterfields
     *
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function get_options_filterfields($plugin) {
        return array(0 => get_string('none'));
    }

    /**
     * get_options_reviewcmids
     *
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function get_options_reviewcmids($plugin) {
        return array(0 => get_string('none'));
    }

    /**
     * get_options_revisecmids
     *
     * @param string  $plugin
     * @return void, but will update $mform
     */
    protected function get_options_revisecmids($plugin) {
        return array(0 => get_string('none'));
    }
}
