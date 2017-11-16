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

        // room (roomname, totalseats, roomtopic)
        var div = $("<div></div>", {"class" : "room"});
        $(div).html($(target).parent(".slot")
                           .prevAll(".roomheadings")
                           .first() // most recent TR
                           .find("th, td")
                           .eq(target.cellIndex)
                           .html());
        $(div).appendTo(target);

        // transfer title, authors, and abstract summary
        $(MAJ.sourcesession).children(".title, .authors, .summary").appendTo(target);

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

        // create temp elements to store id and ".title, .authors, .summary" nodes
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

        // move children nodes to temp source
        $(MAJ.sourcesession).children(".time, .room").appendTo(tempsource);
        $(target).children(".title, .authors, .summary").appendTo(tempsource);
        $(MAJ.sourcesession).children(".capacity").appendTo(tempsource);

        // move children nodes to temp target
        $(target).children(".time, .room").appendTo(temptarget);
        $(MAJ.sourcesession).children(".title, .authors, .summary").appendTo(temptarget);
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
        "drop" : function(event, ui) {
            $(this).removeClass("ui-dropping");
            MAJ.clicksession(this);
        },
        "out" : function(event, ui) {
            $(this).removeClass("ui-dropping");
        },
        "over" : function(event, ui) {
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
        "start" : function(event, ui) {
            MAJ.sourcesession = this;
            $(this).addClass("ui-dragging");
            $(this).removeClass("ui-selected");
            $(this).data("startposition", {
                "top" : $(this).css("top"),
                "left" : $(this).css("left")
            });
        },
        "stop" : function(event, ui) {
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
            var id = $(this).prop("id");
            var i = id.indexOf("-");
            var c = id.substring(0, i);
            var s = id.substring(i + 1);
            $(this).click(new Function("s", "MAJ." + c + "(s)"));
            activecommand = false;
        });
        if (activecommand) {
            var c = $(this).prop("id");
            $(this).click(new Function("MAJ." + c + "()"));
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

MAJ.emptyschedule = function() {
    $("table.schedule .session").each(function(){
        if ($(this).hasClass("emptysession")) {
            // do nothing
        } else {
            $(this).addClass("emptysession");
            $(this).removeClass("attending");
            $(this).find(".capacity").remove();

            if ($(this).prop("id")=="") {
                // sessions without an "id" are dummy sessions are removed
                $(this).find(".title, .authors, .summary").remove();
            } else {
                // sessions with an "id" are moved to the "#items" DIV
                var div = $("<div></div>", {
                    "id" : $(this).prop("id"),
                    "style" : "inline-block",
                }).addClass("session");
                $(this).prop("id", "");
                $(this).children(".title, .authors, .summary").appendTo(div);
                MAJ.draggable(null, div);
                MAJ.selectable(null, div);
                $("#items").append(div);
            }
        }
    });
}

MAJ.resetschedule = function() {
}

MAJ.renumberschedule = function() {
}

MAJ.addday = function(pos) {
}

MAJ.addslot = function(pos) {
}

MAJ.addroom = function(pos) {
}

MAJ.editcss = function(pos) {
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
    $("head").first()
        .append(
            $("<style></style>", {
                "type" : "text/css"
            }).text(
                "@import url(" + toolroot + "/styles.css);\n" +
                "@import url(" + blockroot + "/templates/template.css)"
            )
        )
        .append(
            $("<script></script>", {
                "type" : "text/javascript",
                "src" : blockroot + "/templates/template.js"
            }
        )
    );


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

    // fetch current session info from DB

    // make sesisons draggable

});
