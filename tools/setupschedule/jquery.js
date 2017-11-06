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
 * blocks/maj_submissions/tools/setupschedule.js
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

// set hide all sections when document has loaded
$(document).ready(function(){

    // extract toolroot URL and block instance id from page URL
    var blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
    var toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
    var id = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");

    // extract main language from body classes
    var regexp = new RegExp("lang-(\\w+)");
    var lang = $("body").attr('class').match(regexp)[1];

    // hide "Session information" section of form
    $("#id_sessioninfo").css("display", "none");

    // fetch styles.css
    var style = $("<style></style>", {"type" : "text/css"});
    style.text("@import url(" + toolroot + "/styles.css);\n" +
               "@import url(" + blockroot + "/templates/template.css)");
    style.insertAfter("head");

    var style = $("<style></style>", {"type" : "text/css"});
    style.text("@import url(" + blockroot + "/templates/template.css)");
    style.insertAfter("head");

    // create Tools area
    var tools = $("<div></div>", {"id" : "tools"}).insertAfter("#id_sessioninfo");

    // populate Tools area
    var p = {"id" : id, "action" : "loadtools"};
    tools.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });

    // create Schedule area
    var schedule = $("<div></div>", {"id" : "schedule"}).insertAfter("#tools");

    //populate Schedule area
    var p = {"id" : id, "action" : "loadschedule"};
    schedule.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
            $(this).find("span.multilang[lang!=" + lang + "]").css("display", "none");
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });

    // create Events area
    var items = $("<div></div>", {"id" : "items", "class" : "schedule"}).insertAfter("#schedule");

    //populate Events area
    var p = {"id" : id, "action" : "loaditems"};
    items.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });

    // fetch and display current schedule

    // make schedule dropable

    // fetch current session info from DB

    // make sesisons draggable

});
