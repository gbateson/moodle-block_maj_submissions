if (window.MAJ==null) {
    window.MAJ = {};
}

if (MAJ.str==null) {
    MAJ.str = {};
}

MAJ.setup_attempts = 10;
MAJ.setup_interval = 500;

MAJ.setup_tabs = function(count) {

    // set up tabs for short wide screens (e.g. landscape tablet)
    var items = document.querySelectorAll(".schedule .tab");

    if (items.length==0) {
        count = (count==null ? 1 : (count + 1));
        if (count < MAJ.setup_attempts) {
            setTimeout(MAJ.setup_tabs, MAJ.setup_interval, count);
        }
        return true;
    }

    // set up tabs
    for (var i in items) {
        if (! items[i].addEventListener) {
            continue;
        }
        items[i].addEventListener("click", function(){
            if (typeof(this.className)=="string") {
                if (this.className.indexOf(" active") >= 0) {
                    return true;
                }
                var items = document.querySelectorAll(".tab.active, .day.active");
                for (var i in items) {
                    if (items[i].className) {
                        items[i].className = items[i].className.replace(" active", "");
                    }
                }
                var day = this.className.substr(this.className.length - 4);
                var items = document.querySelectorAll("." + day);
                for (var i in items) {
                    if (items[i].className) {
                        items[i].className += " active";
                    }
                }
            }
        });
    }
};

MAJ.setup_autolinks = function() {
    var links = document.querySelectorAll("a.autolink");
    for (var i=0; i<links.length; i++) {
        links[i].target = "MAJ";
    }
};

MAJ.setup_attendance = function() {

    if (location.href.indexOf("/mod/page/view.php") < 0) {
        return true;
    }

    // extract values from a Moodle URL
    var wwwroot = location.href.replace(new RegExp("^(.*?)/mod/page/.*$"), "$1");
    var cmid = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");
    var action = wwwroot + "/blocks/maj_submissions/tools/setupschedule/action.php";

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function(){
        if (this.readyState == 4 && this.status == 200) {

            // get the new MAJ settings from the incoming data
            eval(this.responseText);

            // remove any remnants of previous attendance elements
            var items = document.querySelectorAll(".schedule .capacity");
            for (var i in items) {
                if (items[i].parentNode) {
                    items[i].parentNode.removeChild(items[i]);
                }
            }
            items = null;

            // RegExp to trim leading/trailing white space
            var trimspace = new RegExp("(^\\s+)|(\\s+)$", "g");

            // set up attending/not attending checkboxes
            var sessions = document.querySelectorAll("table.schedule .session");
            for (var i in sessions) {

                if (! sessions[i].id) {
                    continue;
                }

                var id = sessions[i].id;
                if (id.indexOf("id_recordid_")) {
                    continue;
                }
                var rid = id.substr(12);

                // remove "attending" class name
                sessions[i].className = sessions[i].className.replace(" attending", "");

                // add "attending" class if necessary
                if (MAJ.attend[rid]) {
                    sessions[i].className += " attending";
                }

                // set up empty seats info
                var emptyseats = document.createElement("DIV");
                emptyseats.className = "emptyseats";
                if (MAJ.emptyseats[rid]) {
                    emptyseats.innerHTML = MAJ.emptyseats[rid];
                } else {
                    emptyseats.innerHTML = MAJ.str.seatsavailable;
                }

                // set up checkbox
                var name = "attend[" + rid + "]";
                var checked = (MAJ.attend[rid] ? true : false);

                var checkbox = document.createElement("INPUT");
                checkbox.setAttribute("type", "checkbox");
                checkbox.setAttribute("id", "id_" + name);
                checkbox.setAttribute("name", name);
                checkbox.setAttribute("value", "1");
                checkbox.checked = checked;

                // setup checkbox label
                var label = document.createElement("LABEL");
                label.setAttribute("for", "id_" + name);
                if (checked) {
                    label.innerHTML = MAJ.str.attending;
                } else {
                    label.innerHTML = MAJ.str.notattending;
                }

                var attendance = document.createElement("DIV");
                attendance.className = "attendance";
                attendance.appendChild(label);
                attendance.appendChild(checkbox);

                var capacity = document.createElement("DIV");
                capacity.className = "capacity";
                capacity.setAttribute("data-rid", rid);
                capacity.appendChild(emptyseats);
                capacity.appendChild(attendance);

                capacity.addEventListener("click", function(){
                    var checkbox = this.querySelector("input[type=checkbox]");
                    var label = this.querySelector(".attendance label");
                    var session = this.parentNode;
                    while (session.className.indexOf("session") < 0) {
                        session = session.parentNode;
                    }
                    if (checkbox.checked) {
                        label.textContent = MAJ.str.attending;
                        if (session.className.indexOf(" attending") < 0) {
                            session.className += " attending";
                        }
                    } else {
                        label.textContent = MAJ.str.notattending;
                        session.className = session.className.replace(" attending", "");
                    }
                    var target = this;
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", action, true);
                    xhr.onreadystatechange = function(){
                        if (this.readyState == 4 && this.status == 200) {
                            var seats = target.querySelector(".emptyseats");
                            if (seats) {
                                seats.innerHTML = this.responseText;
                            }
                        }
                    };
                    var rid = target.getAttribute('data-rid');
                    var attend = (checkbox.checked ? 1 : 0);
                    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xhr.send("action=updateattendance&rid=" + rid + "&attend=" + attend);
                });

                sessions[i].appendChild(capacity);
            }
            sessions = null;

            // add schedule chooser
            var title = document.querySelector(".schedule .scheduletitle td");
            if (title) {

                var full = document.createElement("SPAN");
                full.setAttribute("class", "fullschedule active");
                full.appendChild(document.createTextNode(MAJ.str.fullschedule));

                var my = document.createElement("SPAN");
                my.setAttribute("class", "myschedule");
                my.appendChild(document.createTextNode(MAJ.str.myschedule));

                var chooser = document.createElement("DIV");
                chooser.setAttribute("class", "schedulechooser");

                chooser.appendChild(full);
                chooser.appendChild(my);

                title.appendChild(chooser);
            }
            title = null;

            // set up event handlers for schedule chooser
            var items = document.querySelectorAll(".schedule .schedulechooser span");
            for (var i in items) {
                if (! items[i].addEventListener) {
                    continue;
                }
                items[i].addEventListener("click", function(){
                    if (this.className.indexOf(" active") < 0) {
                        var items = this.parentNode.querySelectorAll("span");
                        for (var i in items) {
                            if (typeof(items[i].className)=="string") {
                                if (items[i]==this) {
                                    items[i].className += " active";
                                } else {
                                    items[i].className = items[i].className.replace(" active", "");
                                }
                            }
                        }

                        // locate table.schedule
                        var schedule = document.querySelector("table.schedule");

                        // remove previous (full|my)schedule class, if any
                        var regexp = new RegExp(" ?(full|my)schedule");
                        schedule.className = schedule.className.replace(regexp, "");

                        // add new (full|my)schedule class
                        if (this.className.indexOf("fullschedule") >= 0) {
                            schedule.className += " fullschedule";
                        } else if (this.className.indexOf("myschedule") >= 0) {
                            schedule.className += " myschedule";
                        }
                    }
                });

            }
            items = null;
        }
    };

    xhr.open("POST", action, true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("action=loadattendance&cmid=" + cmid);
}

MAJ.setup_tabs();
MAJ.setup_autolinks();
MAJ.setup_attendance();
