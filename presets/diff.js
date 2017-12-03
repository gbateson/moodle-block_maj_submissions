/*
 * Javascript Diff Algorithm
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
    var n = s;
    n = n.replace(/&/g, "&amp;");
    n = n.replace(/</g, "&lt;");
    n = n.replace(/>/g, "&gt;");
    n = n.replace(/"/g, "&quot;");
    return n;
}

MAJ.diffString = function(o, n) {
    o = o.replace(/\s+$/, '');
    n = n.replace(/\s+$/, '');

    var out = MAJ.diff(o == "" ? [] : o.split(/\s+/),
                       n == "" ? [] : n.split(/\s+/));
    var str = "";

    var oSpace = o.match(/\s+/g);
    if (oSpace == null) {
        oSpace = ["\n"];
    } else {
        oSpace.push("\n");
    }
    var nSpace = n.match(/\s+/g);
    if (nSpace == null) {
        nSpace = ["\n"];
    } else {
        nSpace.push("\n");
    }

    if (out.n.length == 0) {
        if (out.o.length) {
            str += "<del>";
            for (var i = 0; i < out.o.length; i++) {
                str += MAJ.escape(out.o[i]) + oSpace[i];
            }
            str += "</del>";
        }
    } else {
        if (out.n[0].text == null && out.o.length) {
            str += "<del>";
            for (n = 0; n < out.o.length && out.o[n].text == null; n++) {
                str += MAJ.escape(out.o[n]) + oSpace[n];
            }
            str += "</del>";
        }

        var currenttag = "";
        for (var i = 0; i < out.n.length; i++) {
            var bg = "";
            var tag = "";
            var txt = "";
            if (out.n[i].text == null) {
                tag = "ins";
                bg = "#eeffee";
                border = "1px solid #99cc99";
                txt = MAJ.escape(out.n[i]) + nSpace[i];
            } else {
                tag = "del";
                bg = "#ffeeee";
                border = "1px solid #cc9999";
                for (n = out.n[i].row + 1; n < out.o.length && out.o[n].text == null; n++) {
                    txt += MAJ.escape(out.o[n]) + oSpace[n];
                }
                if (out.n[i].text) {
                    if (currenttag) {
                        str += "</" + currenttag + ">";
                        currenttag = "";
                    }
                    str += out.n[i].text + nSpace[i];
                }
            }
            if (txt) {
                if (currenttag==tag) {
                    // do nothing
                } else {
                    if (currenttag) {
                        str += "</" + currenttag + ">";
                    }
                    if (tag) {
                        str += "<" + tag + ' style="background-color: ' + bg + '; ' +
                                                   'border: ' + border + '; ' +
                                                   'padding: 2px;">';
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

MAJ.randomColor = function() {
    return "rgb(" + (Math.random() * 100) + "%, " +
                    (Math.random() * 100) + "%, " +
                    (Math.random() * 100) + "%)";
}

MAJ.diffString2 = function(o, n) {
    o = o.replace(/\s+$/, '');
    n = n.replace(/\s+$/, '');

    var out = MAJ.diff(o == "" ? [] : o.split(/\s+/), n == "" ? [] : n.split(/\s+/));

    var oSpace = o.match(/\s+/g);
    if (oSpace == null) {
        oSpace = ["\n"];
    } else {
        oSpace.push("\n");
    }

    var nSpace = n.match(/\s+/g);
    if (nSpace == null) {
        nSpace = ["\n"];
    } else {
        nSpace.push("\n");
    }

    var os = "";
    var colors = [];
    for (var i = 0; i < out.o.length; i++) {
        colors[i] = MAJ.randomColor();

        if (out.o[i].text != null) {
            os += '<span style="background-color: ' +colors[i]+ '">' +
                        escape(out.o[i].text) + oSpace[i] + "</span>";
        } else {
            os += "<del>" + escape(out.o[i]) + oSpace[i] + "</del>";
        }
    }

    var ns = "";
    for (var i = 0; i < out.n.length; i++) {
        if (out.n[i].text != null) {
            ns += '<span style="background-color: ' +colors[out.n[i].row]+ '">' +
                        escape(out.n[i].text) + nSpace[i] + "</span>";
        } else {
            ns += "<ins>" + escape(out.n[i]) + nSpace[i] + "</ins>";
        }
    }

    return {o : os, n : ns};
}

MAJ.diff = function(o, n) {
    var ns = new Object();
    var os = new Object();

    for (var i = 0; i < n.length; i++) {
        if (ns[n[i]] == null)
            ns[n[i]] = {"rows": new Array(),
                          "o"   : null};
        ns[n[i]].rows.push(i);
    }

    for (var i = 0; i < o.length; i++) {
        if (os[o[i]] == null)
            os[o[i]] = {rows: new Array(), n: null};
        os[o[i]].rows.push(i);
    }

    for (var i in ns) {
        if (ns[i].rows.length == 1 && typeof(os[i]) != "undefined" && os[i].rows.length == 1) {
            n[ns[i].rows[0]] = {"text": n[ns[i].rows[0]], "row": os[i].rows[0]};
            o[os[i].rows[0]] = {"text": o[os[i].rows[0]], "row": ns[i].rows[0]};
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
