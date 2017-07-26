window.maj_load = function(url, runonload) {
    var r = new RegExp("^(.*?)/mod/data/.*?(\\bi?d=\\d+\\b).*$");
    var m = url.href.match(r);
    if (m==null || m[0]==null) {
        return false;
    }
    var src = m[1] + "/blocks/maj_submissions/presets.js.php";
    src += "?" + m[2] + "&" + "preset=workshops";
    if (runonload) {
        src += "&runonload=true";
    }
    var css = "@import url(" + src.replace("js.php", "css.php") + ")";
    var script = document.createElement("script");
    script.setAttribute("type","text/javascript");
    script.setAttribute("src", src);
    var style = document.createElement("style");
    style.setAttribute("type","text/css");
    style.appendChild(document.createTextNode(css));
    var head = document.getElementsByTagName("head");
    head[0].appendChild(script);
    head[0].appendChild(style);
    return true;
};
(function() {
    if (maj_load(window.location, true)) {
        return true;
    }
    var fn = function() {
        var s = "ul.nav.nav-tabs li a[href]";
        var a = document.querySelectorAll(s);
        if (a.length) {
            return maj_load(a[0]);
        }
    };
    if (window.addEventListener) {
        window.addEventListener("load", fn, false);
    } else if (window.attachEvent) {
        window.attachEvent("onload", fn);
    }
}());