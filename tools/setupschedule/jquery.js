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

if (window.MAJ==null) {
    window.MAJ = {};
}

MAJ.sourcesession = null;
MAJ.targetsession = null;

MAJ.updaterecord = function(session) {
}

MAJ.clicksession = function(target) {

    // select source
    if (MAJ.sourcesession==null) {
        MAJ.sourcesession = target;
        $(target).addClass("ui-selected");
        return true;
    }

    // deselect source
    if (MAJ.sourcesession==target) {
        MAJ.sourcesession = null;
        $(target).removeClass("ui-selected");
        return true;
    }

    // target is an empty session
    if ($(target).hasClass("emptysession")) {
        $(target).removeClass("emptysession");

        $(target).addClass("ui-selected");

        // time (and duration)
        var div = $("<div></div>", {"class" : "time"});
        $(div).html($(target).parent(".slot")
                           .find(".timeheading")
                           .html());
        $(div).appendTo(target);

        // room (roomname, roomseats, roomtopic)
        var div = $("<div></div>", {"class" : "room"});
        $(div).html($(target).parent(".slot")
                           .prevAll(".roomheadings")
                           .first() // most recent TR
                           .find("th, td")
                           .eq(target.cellIndex)
                           .html());
        $(div).appendTo(target);

        // transfer title, authors, typecategory and abstract summary
        $(MAJ.sourcesession).children(".title, .authors, .typecategory, .summary").appendTo(target);

        // transfer "id"
        $(target).prop("id", $(MAJ.sourcesession).prop("id"));

        // empty/remove source session content
        switch (MAJ.sourcesession.tagName) {
            case "DIV":
                $(MAJ.sourcesession).remove();
                break;
            case "TD":
                $(MAJ.sourcesession).empty()
                                    .prop("id", "")
                                    .addClass("emptysession")
                                    .removeClass("ui-selected");
                break;
        }
        MAJ.sourcesession = null;

        $(target).removeClass("ui-selected");

        return true;
    }

    // source and target are both non-empty session
    if ($(target).hasClass("session")) {
        $(target).addClass("ui-selected");

        // create temp elements to store id and child nodes
        var temptarget = document.createElement("DIV");
        var tempsource = document.createElement("DIV");

        // transfer "emptysession" CSS class
        if ($(MAJ.sourcesession).hasClass("emptysession")) {
            $(MAJ.sourcesession).removeClass("emptysession");
            $(target).addClass("emptysession");
        }

        // transfer ids
        var sourceid = $(MAJ.sourcesession).prop("id");
        $(MAJ.sourcesession).prop("id", $(target).prop("id"));
        $(target).prop("id", sourceid);

        // move children to temp source
        $(MAJ.sourcesession).children(".time, .room").appendTo(tempsource);
        $(target).children(".title, .authors, .typecategory, .summary").appendTo(tempsource);
        $(MAJ.sourcesession).children(".capacity").appendTo(tempsource);

        // move children to temp target
        $(target).children(".time, .room").appendTo(temptarget);
        $(MAJ.sourcesession).children(".title, .authors, .typecategory, .summary").appendTo(temptarget);
        $(target).children(".capacity").appendTo(temptarget);

        // move children to real source and target
        $(temptarget).children().appendTo(target);
        $(tempsource).children().appendTo(MAJ.sourcesession);

        tempsource = null;
        temptarget = null;

        $(MAJ.sourcesession).removeClass("ui-selected");
        MAJ.sourcesession = null;

        $(target).removeClass("ui-selected");
        return true;
    }
}

MAJ.droppable = function(container, item) {
    if (item) {
        var target = $(item);
    } else {
        var target = $(container).find("td.session");
    }
    target.droppable({
        "accept" : ".session",
        "drop" : function(evt, ui) {
            $(this).removeClass("ui-dropping");
            MAJ.clicksession(this);
        },
        "out" : function(evt, ui) {
            $(this).removeClass("ui-dropping");
        },
        "over" : function(evt, ui) {
            $(this).addClass("ui-dropping");
        },
        "tolerance" : "pointer"
    });
};

MAJ.draggable = function(container, item) {
    if (item) {
        var target = $(item);
    } else {
        var target = $(container).find(".session");
    }
    target.draggable({
        "cursor" : "move",
        "scroll" : true,
        "stack" : ".session",
        "start" : function(evt, ui) {
            MAJ.sourcesession = this;
            $(this).addClass("ui-dragging");
            $(this).removeClass("ui-selected");
            $(this).data("startposition", {
                "top" : $(this).css("top"),
                "left" : $(this).css("left")
            });
        },
        "stop" : function(evt, ui) {
            $(this).removeClass("ui-dragging");
            var p = $(this).data("startposition");
            if (p) {
                $(this).addClass("ui-dropping");
                $(this).animate({
                    "top" : p.top,
                    "left" : p.left
                }, function(){
                    $(this).removeClass("ui-dropping");
                });
            }
        }
    });
};

MAJ.selectable = function(container, item) {
    if (item) {
        var target = $(item);
    } else {
        var target = $(container).find(".session");
    }
    target.click(function(){
        MAJ.clicksession(this);
    });
}

MAJ.multilang = function(container) {
    // extract main language from body classes
    var regexp = new RegExp("lang-(\\w+)");
    var lang = $("body").attr('class').match(regexp)[1];

    // hide SPANs that are not for main language
    $(container).find("span.multilang[lang!=" + lang + "]").css("display", "none");
}

MAJ.setuptools = function() {
    var missing = [];
    $("#tools .command").each(function(){
        var activecommand = true;
        $(this).find(".subcommand").each(function(){
            // extract c(ommand) and s(ubcommand)
            // from id, e.g. addslot-above
            $(this).click(function(evt){
                var id = $(this).prop("id");
                var i = id.indexOf("-");
                var c = id.substring(0, i);
                var s = id.substring(i + 1);
                MAJ[c](evt, s);
            });
            activecommand = false;
        });
        if (activecommand) {
            $(this).click(function(evt){
                var c = $(this).prop("id");
                MAJ[c](evt);
            });
        }
    });
}

MAJ.setupitems = function() {
    var s = $("table.schedule");
    var i = $("#items");
    if (s.length && i.length) {
        var w = s.width();
        w = (50 * parseInt(w / 50));
        i.css("max-width", w + "px");
    } else {
        setTimeout(MAJ.setupitems, 500);
    }
}

MAJ.initializeschedule = function() {
}

MAJ.emptyschedule = function(evt) {
    $("table.schedule .session").not(".emptysession").each(function(){

		// empty this session cell
		$(this).addClass("emptysession");
		$(this).removeClass("attending");
		$(this).find(".capacity").remove();

		// move session details to #items container
		var id = $(this).prop("id");
		if (id=="" || id.indexOf("id_recordid_") < 0) {
			// sessions without an "id" are dummy sessions are removed
			$(this).find(".title, .authors, .typecategory, .summary").remove();
		} else {
			// sessions with an "id" are moved to the "#items" DIV
			var div = $("<div></div>", {
				"id" : id,
				"style" : "display: inline-block",
			}).addClass("session");
			$(this).prop("id", "");
			$(this).children(".title, .authors, .typecategory, .summary").appendTo(div);
			MAJ.draggable(null, div);
			MAJ.selectable(null, div);
			$("#items").append(div);
		}
    });

	// remove demo sessions from #items container
    $("#items .session").not("div[id^=id_record]").each(function(){
		$(this).remove();
    });
}

MAJ.populateschedule = function(evt, confirm) {

    // add initial dialog to select days
    var dialog = $("#dialog");

    if (confirm) {
        // close dialog box
        dialog.dialog("close");

        // cancel previous session clicks, if any
        if (MAJ.sourcesession) {
            MAJ.clicksession(MAJ.sourcesession);
        }

        // select all empty sessions
        var empty = $(".session.emptysession");

        // select all unassigned sessions
        var items = $("#items .session");

        // mimic clicks to assign sessions
        var i_max = Math.min(items.length,
                             empty.length);
        for (var i=(i_max-1); i>=0; i--) {
            MAJ.clicksession(items.get(i));
            MAJ.clicksession(empty.get(i));
        }
        return true;
    }

    // add dialog box content
    var i = 0;
    var html = "";
    $("tbody.day tr.date td:first-child").each(function(){
        var checkbox = '<input type="checkbox" name="day' + (++i) + '" value="1" />';
        html += "<tr><th>" + $(this).html() + "</th><td>" + checkbox + "</td></tr>";
    });
    html = '<table cellpadding="4" cellspacing="4"><tbody>' + html + "</tbody></table>";
    dialog.html(html);

    // set up dialog box button
    var buttons = {
        "Populate" : function(){MAJ.populateschedule(evt, true);},
        "Cancel"   : function(){$(this).dialog("close");}
    };
    dialog.dialog({"buttons" : buttons});

    // open dialog box
    dialog.dialog("open");
}


MAJ.renumberschedule = function(evt) {
}

MAJ.addday = function(evt, pos) {
}

MAJ.addslot = function(evt, pos) {
}

MAJ.addroom = function(evt, pos) {
}

MAJ.editcss = function(evt) {
}

// set hide all sections when document has loaded
$(document).ready(function(){

    // extract toolroot URL and block instance id from page URL
    var blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
    var toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
    var id = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");

    // hide "Session information" section of form
    $("#id_sessioninfo").css("display", "none");

    // fetch CSS and JS files
    $("<link/>", {
        rel: "stylesheet", type: "text/css",
        href: toolroot + "/styles.css"
    }).appendTo("head");

    $("<link/>", {
        rel: "stylesheet", type: "text/css",
        href: blockroot + "/templates/template.css"
    }).appendTo("head");

    $.getScript(blockroot + "/templates/template.js")

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
            MAJ.setuptools();
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });

    // create Schedule area
    var schedule = $("<div></div>", {"id" : "schedule"}).insertAfter("#tools");

    // populate Schedule area
    var p = {"id" : id, "action" : "loadschedule"};
    schedule.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
            MAJ.multilang(this);
            MAJ.droppable(this);
            MAJ.draggable(this);
            MAJ.selectable(this);
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });

    // create Items area
    var items = $("<div></div>", {"id" : "items", "class" : "schedule"}).insertAfter("#schedule");

    // populate Items area
    var p = {"id" : id, "action" : "loaditems"};
    items.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
            MAJ.setupitems();
            MAJ.draggable(this);
            MAJ.selectable(this);
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });

    var dialog = $("<div></div>", {"id" : "dialog"})
                    .css({"background-color" : "white",
                          "border"           : "solid 4px #999",
                          "border-radius"    : "8px",
                          "display"          : "none",
                          "padding"          : "6px 12px"})
                    .insertAfter("#items");
});
