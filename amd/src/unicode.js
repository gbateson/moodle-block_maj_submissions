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
 * load object to handle UNICODE strings
 *
 * @module      block_maj_submissions/unicode
 * @category    output
 * @copyright   Gordon Bateson
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since       2.9
 */
define([], function() {

    /** @alias module:block_maj_submissions/unicode */
    var UNICODE = {};

    // see http://speakingjs.com/es5/ch24.html
    // "Matching Any Code Unit and Any Code Point"
    UNICODE.character = "(?:[\\0-\\uD7FF\\uE000-\\uFFFF]|[\\uD800-\\uDBFF][\\uDC00-\\uDFFF])";
    UNICODE.singlechars = new RegExp(UNICODE.character, "g");

    UNICODE.strlen = function(str) {
        if (str===undefined || str===null || str==="") {
            return 0;
        }
        return (str.match(UNICODE.singlechars) || []).length;
    };

    UNICODE.substr = function(str, start, length) {

        if (str == "" || length == 0) {
            return "";
        }

        var strlen = UNICODE.strlen(str);

        if (start > 0 && start > strlen) {
            return "";
        }

        switch (true) {

            case (start > 0):
                var r = new RegExp("^" + UNICODE.character + "{" + start + "}(.*)$");
                str = str.replace(r, "$1");
                strlen -= start; // subtract
                break;

            case (start < 0 && Math.abs(start) < strlen):
                var r = new RegExp("^(.*)" + UNICODE.character + "{" + Math.abs(start) + "}$");
                str = str.replace(r, "$1");
                strlen += start; // subtract
                break;
        }

        if (length < 0 && Math.abs(length) > strlen) {
            return "";
        }

        switch (true) {

            case (length > 0 && length < strlen):
                var r = new RegExp("^(" + UNICODE.character + "{" + length + "}).*$");
                str = str.replace(r, "$1");
                strlen = length;
                break;

            case (length < 0):
                var r = new RegExp("^(.*)" + UNICODE.character + "{" + Math.abs(length) + "}$");
                str = str.replace(r, "$1");
                strlen = length;
                break;
        }

        return str;
    };

    /**
     * shorten
     *
     * @param   string   txt
     * @param   integer  txtlength (optional, default=28)
     * @param   integer  headlength (optional, default=10)
     * @param   integer  taillength (optional, default=10)
     * @param   boolean  singleline (optional, default=false)
     * @return  string
     */
    UNICODE.shorten = function(txt, txtlength, headlength, taillength, join, singleline) {
        if (txtlength===undefined || txtlength===null) {
            txtlength = 28;
        }
        if (headlength===undefined || headlength===null) {
            headlength = 14;
        }
        if (taillength===undefined || taillength===null) {
            taillength = 10;
        }
        if (join===undefined || join===null) {
            join = ' ... ';
        }
        if (singleline) {
            var r = new RegExp("( |\\t|\\r|\\n|(<br\\b[^>]*>))+", "g");
            txt = txt.replace(r, ' ');
        }
        if (txtlength && (headlength || taillength)) {
            var strlen = UNICODE.strlen(txt);
            if (strlen > txtlength) {
                var head = UNICODE.substr(txt, 0, headlength);
                var tail = UNICODE.substr(txt, strlen - taillength, taillength);
                txt = (head + join + tail);
            }
        }
        return txt;
    };

    return UNICODE;
});
