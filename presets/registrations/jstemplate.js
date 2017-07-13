(function(){
    // fetch the standard js and css files for MAJ Submisisons
    var r = new RegExp("^(.*?)/mod/data/.*?(\\bi?d=\\d+\\b).*$");
    var m = location.href.match(r);
    if (m && m[0]) {
        var src = m[1] + "/blocks/maj_submissions/presets.js.php";
        src += "?" + m[2] + "&" + "preset=registrations";
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
    }
}());