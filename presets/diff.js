/*
 * Based on Javascript Diff Algorithm
 *    By John Resig (http://ejohn.org/)
 *    Modified by Chu Alan "sprite"
 *
 * Released under the MIT license.
 *
 * More Info:
 *    http://ejohn.org/projects/javascript-diff-algorithm/
 */

if (window.MAJ == null) {
    window.MAJ = {};
}

MAJ.escape = function(s) {
    var entities = {"&" : "&amp;",
    				"<" : "&lt;",
    				">" : "&gt;",
    				'"' : "&quot;"};
    for (var x in entities) {
    	s = s.replace(new RegExp(x, "g"), entities[x])
    }
    return s;
}

MAJ.diffString = function(o, n) {

    // remove HTML tags
    var s = new RegExp("<[^>]*>", "g");
    o = o.replace(s, " ");
    n = n.replace(s, " ");

    // remove leading/trailing whitespace
    var s = new RegExp("(^\\s+)|(\\s+$)", "g");
    o = o.replace(s, "");
    n = n.replace(s, "");

    // standardize inner whitespace
    var s = new RegExp("\\s+", "g");
    o = o.replace(s, " ");
    n = n.replace(s, " ");

    // set s(plit) char depending space ratio
    // normal English has a space ratio of about 15%
    // a low space ratio indicates that spaces are not used as separators
    if (MAJ.spaceratio(o) <= 5) {
        var s = ""; // i.e. compare individual characters
    } else {
        var s = " "; // i.e. compare space-delimited words
    }

    var d = MAJ.diff(o == "" ? [] : o.split(s),
                     n == "" ? [] : n.split(s));

    var str = "";
    if (d.n.length == 0) {
        if (d.o.length) {
            var txt = "";
            for (var i = 0; i < d.o.length; i++) {
                txt += (txt=="" ? "" : s);
                txt += MAJ.escape(d.o[i]);
            }
            str += (str=="" ? "" : s);
            str += "<del>" + txt + "</del>";
        }
    } else {
        if (d.n[0].text == null && d.o.length) {
            var txt = "";
            for (n = 0; n < d.o.length && d.o[n].text == null; n++) {
                txt += (txt=="" ? "" : s);
                txt += MAJ.escape(d.o[n]);
            }
            str += (str=="" ? "" : s);
            str += "<del>" + txt + "</del>";
        }

        var currenttag = "";
        for (var i = 0; i < d.n.length; i++) {
            var tag = "";
            var txt = "";
            if (d.n[i].text == null) {
                tag = "ins";
                txt = MAJ.escape(d.n[i]);
            } else {
                tag = "del";
                for (n = d.n[i].row + 1; n < d.o.length && d.o[n].text == null; n++) {
                    txt += (txt=="" ? "" : s);
                    txt += MAJ.escape(d.o[n]);
                }
                if (d.n[i].text) {
                    if (currenttag) {
                        str += "</" + currenttag + ">";
                        currenttag = "";
                    }
                    str += (str=="" ? "" : s);
                    str += d.n[i].text;
                }
            }
            if (txt) {
                if (currenttag==tag) {
                    str += (str=="" ? "" : s);
                } else {
                    if (currenttag) {
                        str += "</" + currenttag + ">";
                    }
                    str += (str=="" ? "" : s);
                    if (tag) {
                        var style = "";
                        switch (tag) {
                            case "ins": style = "background-color: #eeffee; " +
                                                "border: 1px solid #99cc99; " +
                                                "text-decoration: none; " +
                                                "border-radius: 3px; " +
                                                "padding: 0px 2px;"; break;
                            case "del": style = "background-color: #ffeeee; " +
                                                "border: 1px solid #cc9999; " +
                                                "border-radius: 3px; " +
                                                "padding: 0px 2px;"; break;
                        }
                        str += "<" + tag + ' style="' + style + '">';
                    }
                    currenttag = tag;
                }
                str += txt;
            }
        }
        if (currenttag) {
            str += "</" + currenttag + ">";
        }
    }

    return str;
}

MAJ.spaceratio = function(s) {
    var count = 0;
    var total = s.length;
    for (var i=0; i<total; i++) {
        if (s.charAt(i)==" ") {
            count++;
        }
    }
    if (count==0 || total==0) {
        return 0;
    } else {
        return Math.round(100 * count / total);
    }
}

MAJ.diff = function(o, n) {
    var ns = {};
    var os = {};

    for (var i = 0; i < n.length; i++) {
        if (ns[n[i]] == null)
            ns[n[i]] = {"rows": [],
                        "o":    null};
        ns[n[i]].rows.push(i);
    }

    for (var i = 0; i < o.length; i++) {
        if (os[o[i]] == null)
            os[o[i]] = {"rows": [],
                        "n":    null};
        os[o[i]].rows.push(i);
    }

    for (var i in ns) {
        if (typeof(os[i]) == "undefined") {
            continue;
        }
        if (ns[i].rows.length == 1 && os[i].rows.length == 1) {
            n[ns[i].rows[0]] = {"text": n[ns[i].rows[0]],
                                "row":  os[i].rows[0]};
            o[os[i].rows[0]] = {"text": o[os[i].rows[0]],
                                "row":  ns[i].rows[0]};
        }
    }

    for (var i = 0; i < n.length - 1; i++) {
        if (n[i].text == null) {
            continue;
        }
        if (n[i+1].text) {
            continue;
        }
        var r = n[i].row;
        if (r + 1 >= o.length) {
            continue;
        }
        if (o[r + 1].text) {
            continue;
        }
        if (n[i + 1] == o[r + 1]) {
            n[i + 1] = {"text": n[i + 1],
                        "row" : r + 1};
            o[r + 1] = {"text": o[r + 1],
                        "row" : i + 1};
        }
    }

    for (var i = n.length - 1; i > 0; i--) {
        if (n[i].text == null) {
            continue;
        }
        if (n[i - 1].text) {
            continue;
        }
        var r = n[i].row;
        if (r <= 0) {
            continue;
        }
        if (o[r - 1].text) {
            continue;
        }
        if (n[i - 1] == o[r - 1]) {
            n[i - 1] = {"text": n[i - 1],
                        "row" : r - 1};
            o[r - 1] = {"text": o[r - 1],
                        "row" : i - 1};
        }
    }

    return {o: o, n: n};
}
