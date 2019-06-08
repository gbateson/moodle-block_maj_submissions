// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the term of the GNU General Public License as published by
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
 * javascript for the the updatevetting tool
 *
 * @module      block_maj_submissions/tool_updatevetting
 * @category    output
 * @copyright   Gordon Bateson
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since       2.9
 */
define(["jquery", "jqueryui", "core/str"], function($, JUI, STR) {

    /** @alias module:block_maj_submissions/tool_updatevetting */
    var TOOL = {};

    // the DOM id of the dialog box
    TOOL.dialogid = "dialog";

    // initialize string cache
    TOOL.str = {};

    TOOL.init = function(opts) {

        // cache the opts, if any, passed from the server
        if (opts) {
            for (var i in opts) {
                TOOL[i] = opts[i];
            }
        }

        // set up strings
        STR.get_strings([
            {"key": "cancel", "component": "moodle"},
            {"key": "close",  "component": "moodle"},
            {"key": "ok",     "component": "moodle"}
        ]).done(function(s) {
            var i = 0;
            TOOL.str.cancel = s[i++];
            TOOL.str.close  = s[i++];
            TOOL.str.ok     = s[i++];
        });

        // extract URL and block instance id from page URL
        TOOL.blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
        TOOL.toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
        TOOL.pageid = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");
        TOOL.iconroot = $("img.iconhelp").prop("src").replace(new RegExp("/[^/]+$"), "");

        TOOL.iconedit = TOOL.iconroot + "/i/edit";
        TOOL.iconremove = TOOL.iconroot + "/i/delete";

        // fetch CSS
        $("<link/>", {
            rel: "stylesheet", type: "text/css",
            href: TOOL.toolroot + "/styles.css"
        }).appendTo("head");

        // setup select all/none
        $("input[type=checkbox][name='submissions[0]']").click(function(){
            var checked = $(this).prop("checked");
            $("input[type=checkbox][name^=submissions]").not("[name='submissions[0]']").each(function(){
                $(this).prop('checked', checked);
            });
        });

        $(".submissionid a").click(function(evt){
            var title = $(this).closest(".submissionid").siblings(".presentationtitle").text();
            $.ajax({
                "url": $(this).prop("href"),
                "dataType": "text",
                "method": "GET",
                "success": function(html) {
                    html = html.substr(html.indexOf('<ul class="nav nav-tabs">'));
                    html = html.substr(html.indexOf('</ul>'));
                    html = html.substr(0, html.indexOf('</form>'));
                    html = html.replace(new RegExp('<div class="paging">.*?</div>', "g"), '');
                    TOOL.open_dialog(evt, title, html);
                }
            });
            evt.stopPropagation();
            return false;
        });
    };

    TOOL.open_dialog = function(evt, title, html, actiontext, actionicon, actionfunction, showcancelbutton) {
        var showactionbutton = true;

        // locate dialog box in DOM
        // (create it, if necessary)
        var dialogbox = document.getElementById(TOOL.dialogid);
        if (TOOL.empty(dialogbox)) {
            dialogbox = document.createElement("DIV");
            dialogbox.setAttribute("id", TOOL.dialogid);
            $("body").append(dialogbox);
        }

        // cache jQuery object for dialog
        var dialog = $(dialogbox);

        // create/close the dialog element
        if (TOOL.empty(dialog.dialog("instance"))) {
            dialog.dialog({"autoOpen": false, "width": "auto"});
        } else {
            if (dialog.dialog("isOpen")) {
                dialog.dialog("close");
            }
        }

        // update the dialog title
        dialog.dialog("option", "title", title);

        // update the dialog HTML
        dialog.html(html);

        // set the dialog mode
        dialog.dialog("option", "modal", showcancelbutton);

        // update the dialog buttons
        var buttons = [];
        if (showactionbutton) {
            if (TOOL.empty(actiontext)) {
                actiontext = TOOL.str.ok;
            }
            if (TOOL.empty(actionicon)) {
                actionicon = "ui-icon-check";
            }
            if (TOOL.empty(actionfunction)) {
                actionfunction = function(){
                    $(this).dialog("close");
                };
            }
            buttons.push({"text": actiontext,"click": actionfunction}); // "icon": actionicon
        }
        if (showcancelbutton) {
            var canceltext = TOOL.str.cancel;
            //var cancelicon = "ui-icon-cancel";
            var cancelfunction = function(){
                $(this).dialog("close");
            };
            buttons.push({"text": canceltext, "click": cancelfunction}); // "icon": cancelicon
        }
        dialog.dialog("option", "buttons", buttons);

        // open the dialog box
        dialog.dialog("open");

        // prevent the current click causing
        // the parent element to be selected
        evt.stopPropagation();
    };

    TOOL.close_dialog = function() {
        $("#" + TOOL.dialogid).dialog("close");
    };

    TOOL.empty = function(v) {
        return (v===undefined || v===null);
    };

    return TOOL;
});
