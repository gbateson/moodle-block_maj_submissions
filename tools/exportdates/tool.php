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
 * blocks/maj_submissions/tools/exportdates/tool.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/blocks/moodleblock.class.php'); // class block_base
require_once($CFG->dirroot.'/blocks/maj_submissions/block_maj_submissions.php');
require_once($CFG->dirroot.'/lib/filelib.php'); // function send_file()

$blockname = 'maj_submissions';
$plugin = "block_$blockname";
$tool = 'toolexportdates';

// get the incoming block_instance id
$id = required_param('id', PARAM_INT);

if (! $block_instance = $DB->get_record('block_instances', array('id' => $id))) {
    print_error('invalidinstanceid', $plugin);
}
if (! $block = $DB->get_record('block', array('name' => $block_instance->blockname))) {
    print_error('invalidblockid', $plugin, $block_instance->blockid);
}
if (class_exists('context')) {
    $context = context::instance_by_id($block_instance->parentcontextid);
} else {
    $context = get_context_instance_by_id($block_instance->parentcontextid);
}
if (! $course = $DB->get_record('course', array('id' => $context->instanceid))) {
    print_error('invalidcourseid', $plugin, $block_instance->pageid);
}
$course->context = $context;

require_login($course->id);
require_capability('moodle/course:manageactivities', $context);

if (! isset($block->version)) {
    $params = array('plugin' => $plugin, 'name' => 'version');
    $block->version = $DB->get_field('config_plugins', 'value', $params);
}

if ($instance = block_instance('maj_submissions', $block_instance, $PAGE)) {
    $instance->set_multilang(true);
    $content = $instance->get_content()->text;

    // remove the links and icons for edit, import and export
    $content = preg_replace('/\s*<img\b[^>]*>(<\/img)?/', '', $content);
    $content = preg_replace('/\s*<a\b[^>]*><\/a>/', '', $content);

    // remove statistics about registrations/submissions so far
    $content = preg_replace('/\s*<i\b[^>]*>.*?<\/i>/', '', $content);

    // remove "h4.toollinks", "p.toollink" and "p.tooldivider" tags
    $content = preg_replace('/\s*<(h4|p|ul)\b[^>]*class="(quicklinks|toollinks?|tooldivider)"[^>]*>.*?<\/\1>/s', '', $content);

    // format block and style tags
    $n = "\n"; // newline
    $t = "    "; // tab
    $content = strtr($content, array('<h4>'   => "$n<h4>",
                                     '<h4 '   => "$n<h4 ",
                                     '<ul>'   => "$n<ul>",
                                     '<ul '   => "$n<ul ",
                                     '</ul>'  => "$n</ul>",
                                     '<li>'   => "$n$t<li>",
                                     '<li '   => "$n$t<li ",
                                     '</li>'  => "$n$t</li>",
                                     '<b>'    => "$n$t$t<b>",
                                     '<b '    => "$n$t$t<b ",
                                     '<span>' => "$n$t$t<span>"));

    // convert divider DIV to one line
    $content = preg_replace('/(<li class="divider">)\s+(<\/li>)/s', '$1$2', $content);

    // add divider styles as inline CSS
    $filename = $CFG->dirroot.'/blocks/maj_submissions/styles.css';
    if (file_exists($filename)) {
        $css = file_get_contents($filename);

        $search = '/\/\*.*?\*\//s'; // comments
        $css = preg_replace($search, '', $css);

        $search = '/div.block_maj_submissions ([^\{]* li.divider\s*)\{([^\}]*)\}/s';
        // $1 : selectors
        // $2 : definitions
        if (preg_match_all($search, $css, $matches)) {

            $definitions = array();
            foreach ($matches[2] as $definition) {
                $definition = preg_replace('/\s+/s', ' ', $definition);
                $definition = preg_replace('/\s*([:;])\s*/s', '$1 ', $definition);
                $definitions[] = trim($definition);
            }

            if ($definitions = implode(' ', $definitions)) {
                $content = str_replace('class="divider"', 'style="'.s($definitions).'"', $content);
            }
        }
    }

    // Put $content into DIV.block_maj_submissions so that CSS styles are applied as expected.
    $content = html_writer::tag('div', $content, array('class' => 'block_maj_submissions'));

    // create $table version of the html $content
    $table = $content;

    $search = '/<li class="(date[^"]*)">\s*<b>(.*?)<\/b>\s*<br[^>]*>\s*(.*?)\s*<\/li>/us';
    $replace = '<dl class="row my-0 $1">'.
                '<dt class="col-sm-5 col-md-4 col-lg-3 my-0 py-1 px-sm-3">$2</dt>'.
                '<dd class="col-sm-7 col-md-8 col-lg-9 my-0 py-1">$3</dd>'.
               '</dl>';
    $content = preg_replace($search, $replace, $content);

    $search = '/<ul class="importantdates">(.*?)<\/ul>/us';
    $replace = '<div class="container importantdates mx-0 my-2">$1</div>';
    $content = preg_replace($search, $replace, $content);

    // convert empty list items (e.g. dividers) to <HR> tags
    $replace = '<hr size="2" class="ml-0" style="max-width: 420px;">';
    $content = preg_replace('/\s*<li[^>]*><\/li>/s', $replace, $content);

    // remove link tags, i.e. <a...> and </a>
    $table = preg_replace('/<\/?a[^<]*>\s*/', '', $table);

    // convert UL+LI version to TABLE/TBODY+TR/TH/TD version
    $table = preg_replace('/<(ul)([^>]*)>(.*?)<\/\1>/s', '<table$2"><tbody>$3</tbody></table>', $table);
    $table = preg_replace('/<(li)([^>]*)>(.*?)<\/\1>/s', '<tr$2>$3</tr>', $table);
    $table = preg_replace('/<b>(.*)<\/b><br[^>]*>/', '<th>$1</th>', $table);
    $table = preg_replace('/(<\/th>)(.*)/', '$1'."$n$t$t".'<td>$2</td>', $table);

    // convert divider to <hr>
    $replace = '<tr>'."$n$t$t".'<td colspan="2"><hr$1 /></td>'."$n$t".'</tr>';
    $table = preg_replace('/<tr([^<]*)>\s*<\/tr>/', $replace, $table);

    // append $table to html $content
    $content .= "$n$n".$table."$n";
}

// TODO: add a settings page to select...
//     (1) page/file output type
//     (2) list/table output format 

// On development sites, we show the output on the screen
if (debugging('', DEBUG_DEVELOPER)) {
    $url = new moodle_url($SCRIPT, array('id' => $id));
    $strblockname = get_string('blockname', $plugin);
    $strpagetitle = get_string($tool, $plugin);

    $PAGE->set_url($url);
    $PAGE->set_title($strpagetitle);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('incourse');
    $PAGE->navbar->add($strblockname);
    $PAGE->navbar->add($strpagetitle, $url);

    echo $OUTPUT->header();
    echo html_writer::tag('pre', htmlspecialchars($content));
    echo $OUTPUT->footer();
    die;
}

// On production sites, we send the output as a file
if (empty($instance->config->title)) {
    $filename = $block->name;
} else {
    $filename = format_string($instance->config->title, true);
    $filename = clean_filename(strip_tags($filename));
}
if (empty($filename)) {
    $filename = get_string('exportdates', $plugin);
}
$filename = preg_replace('/[ \.]/', '-', $filename).'.html';
send_file($content, $filename, 0, 0, true, true);
