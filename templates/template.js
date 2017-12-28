if (window.MAJ==null) {
    window.MAJ = {};
}

if (MAJ.str==null) {
    MAJ.str = {};
}

MAJ.str.attending = "Attending";
MAJ.str.notattending = "Not attending";
MAJ.str.fullschedule = "Full schedule";
MAJ.str.myschedule = "My schedule";

MAJ.onload_attempts = 10;
MAJ.onload_interval = 500;

MAJ.onload_setup = function(count) {

    // set up tabs for short wide screens (e.g. landscape tablet)
    var items = document.querySelectorAll(".schedule .tab");

    // if tabs are NOT loaded, we try again later
    if (items.length==0) {
        count = (count==null ? 1 : (count + 1));
        if (count < MAJ.onload_attempts) {
            setTimeout(MAJ.onload_setup, MAJ.onload_interval, count);
        }
        return true;
    }

    //
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

    // set up attending/not attending checkboxes
    var items = document.querySelectorAll(".schedule .capacity");
    for (var i in items) {
        if (! items[i].addEventListener) {
            continue;
        }
        items[i].addEventListener("click", function(){
            console.log("clicked attendance: " + this.id);
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
        });
    }

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
};

MAJ.onload_setup();

