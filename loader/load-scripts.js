(function(window, $){

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    'use strict';

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!_.isUndefined(window.ldc)){
        return;
    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    class helper {

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Hardcoded
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static namespace(){
            return 'luisdelcid/ldc/ldc'; // The unique namespace identifying the callback in the form `vendor/plugin/function`. Hardcoded.
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return mixed
         */
        static object_property(key = '', object_name = ''){
            var object = null;
            if(!key){
                return null;
            }
            if(_.isEmpty(object_name) && !_.isUndefined(helper.l10n)){
                object = helper.l10n;
            } else if(!_.isEmpty(object_name) && !_.isUndefined(window[object_name])){
                object = window[object_name];
            } else {
                return null;
            }
            if(_.isUndefined(object[key])){
                return null;
            }
            return object[key];
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Error Handling
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static caller_url(index = 0){
            var debug_backtrace = null;
            index = helper.absint(index) + 1;
            debug_backtrace = helper.debug_backtrace(index);
            if(_.isNull(debug_backtrace)){
                return '';
            }
            if(_.isUndefined(debug_backtrace.fileName)){
                return '';
            }
            return debug_backtrace.fileName;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static debug_backtrace(index = 0){
            var backtrace = [],
                error = null,
                fake_function = null,
                limit = 0;
            try {
                fake_function();
            } catch(e){
                error = e;
            }
            backtrace = helper.debug_context(error);
            if(_.isEmpty(backtrace)){
                return null;
            }
            index = helper.absint(index) + 1;
            limit = index + 1;
            if(limit > backtrace.length){
                return null;
            }
            return backtrace[index];
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static debug_context(error = null){
            var backtrace = [];
            if(!_.isError(error)){
                return backtrace;
            }
            $.each(ErrorStackParser.parse(error), function(index, value){
                var stackframe = {
                    args: [],
                    columnNumber: 0,
                    fileName: '',
                    functionName: '',
                    isEval: false,
                    isNative: false,
                    lineNumber: 0,
                    source: '',
                };
                $.each(stackframe, function(key, property){
                    if(!_.isUndefined(value[key])){
                        stackframe[key] = value[key];
                    }
                });
                backtrace.push(stackframe);
            });
            return backtrace;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Hooks
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        static add_action(hook_name = '', callback = null, priority = 10){
            wp.hooks.addAction(hook_name, helper.namespace(), callback, priority);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        static add_filter(hook_name = '', callback = null, priority = 10){
            wp.hooks.addFilter(hook_name, helper.namespace(), callback, priority);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return mixed
         */
        static apply_filters(hook_name = '', value = null, ...arg){
            return wp.hooks.applyFilters(hook_name, value, ...arg);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return null|string
         */
        static current_action(){
            wp.hooks.currentAction();
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return null|string
         */
        static current_filter(){
            wp.hooks.currentFilter();
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static did_action(hook_name = ''){
            return wp.hooks.didAction(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static did_filter(hook_name = ''){
            return wp.hooks.didFilter(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        static do_action(hook_name = '', ...arg){
            wp.hooks.doAction(hook_name, ...arg);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static doing_action(hook_name = ''){
            wp.hooks.doingAction(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static doing_filter(hook_name = ''){
            wp.hooks.doingFilter(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static has_action(hook_name = ''){
            return wp.hooks.hasAction(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static has_filter(hook_name = ''){
            return wp.hooks.hasFilter(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_action(hook_name = ''){
            return wp.hooks.removeAction(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_all_actions(hook_name = ''){
            return wp.hooks.removeAllActions(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_all_filters(hook_name = ''){
            return wp.hooks.removeAllFilters(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_filter(hook_name = ''){
            return wp.hooks.removeFilter(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Miscellaneous
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int
         */
        static absint(maybeint = 0){
            if(!_.isNumber(maybeint)){
                return 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
            }
            return Math.abs(parseInt(maybeint));
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object|null
         */
        static get_instance(class_name = ''){
            var parent = helper.plugin_prefix('singleton');
            if(!class_name){
                return null;
            }
            if(!_.isFunction(window[class_name])){
                return null;
            }
            if(!_.isFunction(window[parent])){
                return null;
            }
            if(!helper.is_subclass_of(window[class_name], parent)){
                return null;
            }
            return window[class_name].get_instance();
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static is_false(data = ''){
            return $.inArray(String(data), ['0', 'false', 'off']) > -1;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static is_subclass_of(func = null, class_name = ''){
            if(!_.isFunction(func)){
                return false;
            }
            if(!class_name){
                return false;
            }
            while(func && func !== Function.prototype){
                if(func === window[class_name]){
                    return true;
                }
                func = Object.getPrototypeOf(func);
            }
            return false;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static is_true(data = ''){
            return (-1 < $.inArray(String(data), ['1', 'on', 'true']));
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int
         */
        static rem_to_px(count = 0){
            var unit = parseInt($('html').css('font-size'));
            if(!unit){
                unit = 16;
            }
            if(!_.isNumber(count)){
                return unit;
            }
            if(count > 0){
                return count * unit;
            }
            return unit;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        static test(){
            console.log('Hello, World!');
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // PHP
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/strings/md5.js
         *
         * @return string
         */
        static md5(str = ''){
          //  discuss at: http://phpjs.org/functions/md5/
          // original by: Webtoolkit.info (http://www.webtoolkit.info/)
          // improved by: Michael White (http://getsprink.com)
          // improved by: Jack
          // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          //    input by: Brett Zamir (http://brett-zamir.me)
          // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          //  depends on: utf8_encode
          //   example 1: md5('Kevin van Zonneveld');
          //   returns 1: '6e658d4bfcb59cc13f96c14450ac40b9'

          var xl;

          var rotateLeft = function(lValue, iShiftBits) {
            return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
          };

          var addUnsigned = function(lX, lY) {
            var lX4, lY4, lX8, lY8, lResult;
            lX8 = (lX & 0x80000000);
            lY8 = (lY & 0x80000000);
            lX4 = (lX & 0x40000000);
            lY4 = (lY & 0x40000000);
            lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
            if (lX4 & lY4) {
              return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
            }
            if (lX4 | lY4) {
              if (lResult & 0x40000000) {
                return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
              } else {
                return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
              }
            } else {
              return (lResult ^ lX8 ^ lY8);
            }
          };

          var _F = function(x, y, z) {
            return (x & y) | ((~x) & z);
          };
          var _G = function(x, y, z) {
            return (x & z) | (y & (~z));
          };
          var _H = function(x, y, z) {
            return (x ^ y ^ z);
          };
          var _I = function(x, y, z) {
            return (y ^ (x | (~z)));
          };

          var _FF = function(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(_F(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
          };

          var _GG = function(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(_G(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
          };

          var _HH = function(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(_H(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
          };

          var _II = function(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(_I(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
          };

          var convertToWordArray = function(str) {
            var lWordCount;
            var lMessageLength = str.length;
            var lNumberOfWords_temp1 = lMessageLength + 8;
            var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
            var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
            var lWordArray = new Array(lNumberOfWords - 1);
            var lBytePosition = 0;
            var lByteCount = 0;
            while (lByteCount < lMessageLength) {
              lWordCount = (lByteCount - (lByteCount % 4)) / 4;
              lBytePosition = (lByteCount % 4) * 8;
              lWordArray[lWordCount] = (lWordArray[lWordCount] | (str.charCodeAt(lByteCount) << lBytePosition));
              lByteCount++;
            }
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
            lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
            lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
            return lWordArray;
          };

          var wordToHex = function(lValue) {
            var wordToHexValue = '',
              wordToHexValue_temp = '',
              lByte, lCount;
            for (lCount = 0; lCount <= 3; lCount++) {
              lByte = (lValue >>> (lCount * 8)) & 255;
              wordToHexValue_temp = '0' + lByte.toString(16);
              wordToHexValue = wordToHexValue + wordToHexValue_temp.substr(wordToHexValue_temp.length - 2, 2);
            }
            return wordToHexValue;
          };

          var x = [],
            k, AA, BB, CC, DD, a, b, c, d, S11 = 7,
            S12 = 12,
            S13 = 17,
            S14 = 22,
            S21 = 5,
            S22 = 9,
            S23 = 14,
            S24 = 20,
            S31 = 4,
            S32 = 11,
            S33 = 16,
            S34 = 23,
            S41 = 6,
            S42 = 10,
            S43 = 15,
            S44 = 21;

          //str = this.utf8_encode(str);
            str = __utf8_encode(str);
          x = convertToWordArray(str);
          a = 0x67452301;
          b = 0xEFCDAB89;
          c = 0x98BADCFE;
          d = 0x10325476;

          xl = x.length;
          for (k = 0; k < xl; k += 16) {
            AA = a;
            BB = b;
            CC = c;
            DD = d;
            a = _FF(a, b, c, d, x[k + 0], S11, 0xD76AA478);
            d = _FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
            c = _FF(c, d, a, b, x[k + 2], S13, 0x242070DB);
            b = _FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
            a = _FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF);
            d = _FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
            c = _FF(c, d, a, b, x[k + 6], S13, 0xA8304613);
            b = _FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
            a = _FF(a, b, c, d, x[k + 8], S11, 0x698098D8);
            d = _FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
            c = _FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1);
            b = _FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
            a = _FF(a, b, c, d, x[k + 12], S11, 0x6B901122);
            d = _FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
            c = _FF(c, d, a, b, x[k + 14], S13, 0xA679438E);
            b = _FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
            a = _GG(a, b, c, d, x[k + 1], S21, 0xF61E2562);
            d = _GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
            c = _GG(c, d, a, b, x[k + 11], S23, 0x265E5A51);
            b = _GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
            a = _GG(a, b, c, d, x[k + 5], S21, 0xD62F105D);
            d = _GG(d, a, b, c, x[k + 10], S22, 0x2441453);
            c = _GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681);
            b = _GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
            a = _GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6);
            d = _GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
            c = _GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87);
            b = _GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
            a = _GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905);
            d = _GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
            c = _GG(c, d, a, b, x[k + 7], S23, 0x676F02D9);
            b = _GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
            a = _HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942);
            d = _HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
            c = _HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122);
            b = _HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
            a = _HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44);
            d = _HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
            c = _HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60);
            b = _HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
            a = _HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6);
            d = _HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
            c = _HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085);
            b = _HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
            a = _HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039);
            d = _HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
            c = _HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8);
            b = _HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
            a = _II(a, b, c, d, x[k + 0], S41, 0xF4292244);
            d = _II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
            c = _II(c, d, a, b, x[k + 14], S43, 0xAB9423A7);
            b = _II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
            a = _II(a, b, c, d, x[k + 12], S41, 0x655B59C3);
            d = _II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
            c = _II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D);
            b = _II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
            a = _II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F);
            d = _II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
            c = _II(c, d, a, b, x[k + 6], S43, 0xA3014314);
            b = _II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
            a = _II(a, b, c, d, x[k + 4], S41, 0xF7537E82);
            d = _II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
            c = _II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB);
            b = _II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
            a = addUnsigned(a, AA);
            b = addUnsigned(b, BB);
            c = addUnsigned(c, CC);
            d = addUnsigned(d, DD);
          }

          var temp = wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);

          return temp.toLowerCase();
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/strings/ltrim.js
         *
         * @return string
         */
        static ltrim(str = '', charlist = ''){
          //  discuss at: http://phpjs.org/functions/ltrim/
          // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          //    input by: Erkekjetter
          // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          // bugfixed by: Onno Marsman
          //   example 1: ltrim('    Kevin van Zonneveld    ');
          //   returns 1: 'Kevin van Zonneveld    '

          charlist = !charlist ? ' \\s\u00A0' : (charlist + '')
            .replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '$1');
          var re = new RegExp('^[' + charlist + ']+', 'g');
          return (str + '')
            .replace(re, '');
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/strings/rtrim.js
         *
         * @return string
         */
        static rtrim(str = '', charlist = ''){
          //  discuss at: http://phpjs.org/functions/rtrim/
          // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          //    input by: Erkekjetter
          //    input by: rem
          // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          // bugfixed by: Onno Marsman
          // bugfixed by: Brett Zamir (http://brett-zamir.me)
          //   example 1: rtrim('    Kevin van Zonneveld    ');
          //   returns 1: '    Kevin van Zonneveld'

          charlist = !charlist ? ' \\s\u00A0' : (charlist + '')
            .replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:])/g, '\\$1');
          var re = new RegExp('[' + charlist + ']+$', 'g');
          return (str + '')
            .replace(re, '');
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/xml/utf8_decode.js
         *
         * @return string
         */
        static utf8_decode(str_data = ''){
          //  discuss at: http://phpjs.org/functions/utf8_decode/
          // original by: Webtoolkit.info (http://www.webtoolkit.info/)
          //    input by: Aman Gupta
          //    input by: Brett Zamir (http://brett-zamir.me)
          // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          // improved by: Norman "zEh" Fuchs
          // bugfixed by: hitwork
          // bugfixed by: Onno Marsman
          // bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          // bugfixed by: kirilloid
          // bugfixed by: w35l3y (http://www.wesley.eti.br)
          //   example 1: utf8_decode('Kevin van Zonneveld');
          //   returns 1: 'Kevin van Zonneveld'

          var tmp_arr = [],
            i = 0,
            c1 = 0,
            seqlen = 0;

          str_data += '';

          while (i < str_data.length) {
            c1 = str_data.charCodeAt(i) & 0xFF;
            seqlen = 0;

            // http://en.wikipedia.org/wiki/UTF-8#Codepage_layout
            if (c1 <= 0xBF) {
              c1 = (c1 & 0x7F);
              seqlen = 1;
            } else if (c1 <= 0xDF) {
              c1 = (c1 & 0x1F);
              seqlen = 2;
            } else if (c1 <= 0xEF) {
              c1 = (c1 & 0x0F);
              seqlen = 3;
            } else {
              c1 = (c1 & 0x07);
              seqlen = 4;
            }

            for (var ai = 1; ai < seqlen; ++ai) {
              c1 = ((c1 << 0x06) | (str_data.charCodeAt(ai + i) & 0x3F));
            }

            if (seqlen == 4) {
              c1 -= 0x10000;
              tmp_arr.push(String.fromCharCode(0xD800 | ((c1 >> 10) & 0x3FF)), String.fromCharCode(0xDC00 | (c1 & 0x3FF)));
            } else {
              tmp_arr.push(String.fromCharCode(c1));
            }

            i += seqlen;
          }

          return tmp_arr.join("");
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/xml/utf8_encode.js
         *
         * @return string
         */
        static utf8_encode(argString = ''){
          //  discuss at: http://phpjs.org/functions/utf8_encode/
          // original by: Webtoolkit.info (http://www.webtoolkit.info/)
          // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          // improved by: sowberry
          // improved by: Jack
          // improved by: Yves Sucaet
          // improved by: kirilloid
          // bugfixed by: Onno Marsman
          // bugfixed by: Onno Marsman
          // bugfixed by: Ulrich
          // bugfixed by: Rafal Kukawski
          // bugfixed by: kirilloid
          //   example 1: utf8_encode('Kevin van Zonneveld');
          //   returns 1: 'Kevin van Zonneveld'

          if (argString === null || typeof argString === 'undefined') {
            return '';
          }

          // .replace(/\r\n/g, "\n").replace(/\r/g, "\n");
          var string = (argString + '');
          var utftext = '',
            start, end, stringl = 0;

          start = end = 0;
          stringl = string.length;
          for (var n = 0; n < stringl; n++) {
            var c1 = string.charCodeAt(n);
            var enc = null;

            if (c1 < 128) {
              end++;
            } else if (c1 > 127 && c1 < 2048) {
              enc = String.fromCharCode(
                (c1 >> 6) | 192, (c1 & 63) | 128
              );
            } else if ((c1 & 0xF800) != 0xD800) {
              enc = String.fromCharCode(
                (c1 >> 12) | 224, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
              );
            } else {
              // surrogate pairs
              if ((c1 & 0xFC00) != 0xD800) {
                throw new RangeError('Unmatched trail surrogate at ' + n);
              }
              var c2 = string.charCodeAt(++n);
              if ((c2 & 0xFC00) != 0xDC00) {
                throw new RangeError('Unmatched lead surrogate at ' + (n - 1));
              }
              c1 = ((c1 & 0x3FF) << 10) + (c2 & 0x3FF) + 0x10000;
              enc = String.fromCharCode(
                (c1 >> 18) | 240, ((c1 >> 12) & 63) | 128, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128
              );
            }
            if (enc !== null) {
              if (end > start) {
                utftext += string.slice(start, end);
              }
              utftext += enc;
              start = end = n + 1;
            }
          }

          if (end > start) {
            utftext += string.slice(start, stringl);
          }

          return utftext;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Plugin Hooks
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static add_plugin_action(hook_name = '', callback = null, priority = 10){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.addAction(hook_name, helper.namespace(), callback, priority);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static add_plugin_filter(hook_name = '', callback = null, priority = 10){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.addFilter(hook_name, helper.namespace(), callback, priority);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return mixed
         */
        static apply_plugin_filters(hook_name = '', value = null, ...arg){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.applyFilters(hook_name, value, ...arg);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return null|string
         */
        static current_plugin_action(){
            wp.hooks.currentAction();
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return null|string
         */
        static current_plugin_filter(){
            wp.hooks.currentFilter();
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static did_plugin_action(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.didAction(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static did_plugin_action(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.didFilter(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        static do_plugin_action(hook_name = '', ...arg){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.doAction(hook_name, ...arg);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static doing_plugin_action(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.doingAction(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static doing_plugin_filter(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.doingFilter(hook_name);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static has_plugin_action(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return false;
            }
            return wp.hooks.hasAction(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static has_plugin_filter(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return false;
            }
            return wp.hooks.hasFilter(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string
         */
        static plugin_hook_name(hook_name = ''){
            var url = helper.caller_url(2); // Two levels above.
            hook_name = helper.plugin_prefix(hook_name, url);
            return hook_name;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_plugin_action(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeAction(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_all_plugin_actions(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeAllActions(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_all_plugin_filters(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeAllFilters(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|void
         */
        static remove_plugin_filter(hook_name = ''){
            hook_name = helper.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeFilter(hook_name, helper.namespace());
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Plugins
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static plugin_folder(url = ''){
            var folder = '',
                mu_plugins_url = helper.mu_plugins_url(),
                path = '',
                plugins_url = helper.plugins_url();
            if(_.isEmpty(url)){
                url = helper.caller_url(1); // One level above.
            }
            if(!url){
                return '';
            }
            if(url.indexOf(mu_plugins_url) === 0){
                 path = url.substr(mu_plugins_url.length, url.length - 1); // File is a must-use plugin.
             } else if(url.indexOf(plugins_url) === 0){
                 path = url.substr(plugins_url.length, url.length - 1); // File is a plugin.
             } else {
                return ''; // File is not a plugin.
            }
            folder = path.split('/', 3);
            if(folder.length < 3){
                return ''; // The entire plugin consists of just a single PHP file, like Hello Dolly or file is the plugin's main file.
            }
            return folder[1];
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static plugin_prefix(str = '', url = ''){
            var plugin_folder = '';
            if(_.isEmpty(url)){
                url = helper.caller_url(1); // One level above.
            }
            plugin_folder = helper.plugin_folder(url);
            if(_.isEmpty(plugin_folder)){
                return '';
            }
            return helper.str_prefix(str, plugin_folder);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static plugin_slug(str = '', url = ''){
            var plugin_folder = '';
            if(!url){
                url = helper.caller_url(1); // One level above.
            }
            plugin_folder = helper.plugin_folder(url);
            if(!plugin_folder){
                return '';
            }
            return helper.str_slug(str, plugin_folder);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Strings
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static canonicalize(str = ''){
            str = str.replaceAll('\\', '_'); // Fix namespaces.
            str = helper.sanitize_title(str);
            str = str.replaceAll('-', '_'); // Fix slugified.
            return str.replace(/^_+|_+$/g, '');
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static remove_accents(str = '', locale = ''){
            if(locale === ''){
                locale = helper.get_locale();
            }
            // Assume UTF-8.
            if(locale.startsWith('de')){
                str = str.replace('Ä', 'Ae');
                str = str.replace('ä', 'ae');
                str = str.replace('Ö', 'Oe');
                str = str.replace('ö', 'oe');
                str = str.replace('Ü', 'Ue');
                str = str.replace('ü', 'ue');
                str = str.replace('ß', 'ss');
            } else if(locale === 'da_DK'){
                str = str.replace('Æ', 'Ae');
                str = str.replace('æ', 'ae');
                str = str.replace('Ø', 'Oe');
                str = str.replace('ø', 'oe');
                str = str.replace('Å', 'Aa');
                str = str.replace('å', 'aa');
            } else if(locale === 'ca'){
                str = str.replace('l·l', 'll');
            } else if(locale === 'sr_RS' || locale === 'bs_BA'){
                str = str.replace('Đ', 'DJ');
                str = str.replace('đ', 'dj');
            }
            str = str.replace(new RegExp('[ÀÁÂÃÄÅĀĂĄẦẰẢẨẲẪẴẤẮẠẬẶǍ]', 'g'), 'A');
            str = str.replace(new RegExp('[Æ]', 'g'), 'AE');
            str = str.replace(new RegExp('[ÇĆĈĊČ]', 'g'), 'C');
            str = str.replace(new RegExp('[ÐĎĐ]', 'g'), 'D');
            str = str.replace(new RegExp('[ÈÉÊËĒĔĖĘĚƏ€ỀẺỂẼỄẾẸỆ]', 'g'), 'E');
            str = str.replace(new RegExp('[ĜĞĠĢ]', 'g'), 'G');
            str = str.replace(new RegExp('[ÌÍÎÏĨĪĬĮİỈỊǏ]', 'g'), 'I');
            str = str.replace(new RegExp('[Ĳ]', 'g'), 'IJ');
            str = str.replace(new RegExp('[Ĵ]', 'g'), 'J');
            str = str.replace(new RegExp('[Ķ]', 'g'), 'K');
            str = str.replace(new RegExp('[ĹĻĽĿŁ]', 'g'), 'L');
            str = str.replace(new RegExp('[ÑŃŅŇŊ]', 'g'), 'N');
            str = str.replace(new RegExp('[ÒÓÔÕÖØŌŎŐƠỒỜỎỔỞỖỠỐỚỌỘỢǑ]', 'g'), 'O');
            str = str.replace(new RegExp('[Œ]', 'g'), 'OE');
            str = str.replace(new RegExp('[ŔŖŘ]', 'g'), 'R');
            str = str.replace(new RegExp('[ŚŜŞŠȘ]', 'g'), 'S');
            str = str.replace(new RegExp('[ŢŤŦȚ]', 'g'), 'T');
            str = str.replace(new RegExp('[Þ]', 'g'), 'TH');
            str = str.replace(new RegExp('[ÙÚÛÜŨŪŬŮŰŲƯỪỦỬỮỨỤỰǕǗǓǙǛ]', 'g'), 'U');
            str = str.replace(new RegExp('[Ŵ]', 'g'), 'W');
            str = str.replace(new RegExp('[ÝŶŸỲỶỸỴ]', 'g'), 'Y');
            str = str.replace(new RegExp('[ŹŻŽ]', 'g'), 'Z');
            str = str.replace(new RegExp('[ªàáâãäåāăąầằảẩẳẫẵấắạậặɑǎ]', 'g'), 'a');
            str = str.replace(new RegExp('[æ]', 'g'), 'ae');
            str = str.replace(new RegExp('[çćĉċč]', 'g'), 'c');
            str = str.replace(new RegExp('[ðďđ]', 'g'), 'd');
            str = str.replace(new RegExp('[èéêëēĕėęěǝềẻểẽễếẹệ]', 'g'), 'e');
            str = str.replace(new RegExp('[ĝğġģ]', 'g'), 'g');
            str = str.replace(new RegExp('[ĥħ]', 'g'), 'h');
            str = str.replace(new RegExp('[ìíîïĩīĭįıỉịǐ]', 'g'), 'i');
            str = str.replace(new RegExp('[ĳ]', 'g'), 'ij');
            str = str.replace(new RegExp('[ĵ]', 'g'), 'j');
            str = str.replace(new RegExp('[ķĸ]', 'g'), 'k');
            str = str.replace(new RegExp('[ĺļľŀł]', 'g'), 'l');
            str = str.replace(new RegExp('[ñńņňŉŋ]', 'g'), 'n');
            str = str.replace(new RegExp('[ºòóôõöøōŏőơồờỏổởỗỡốớọộợǒ]', 'g'), 'o');
            str = str.replace(new RegExp('[œ]', 'g'), 'oe');
            str = str.replace(new RegExp('[ŕŗř]', 'g'), 'r');
            str = str.replace(new RegExp('[ßśŝşšſș]', 'g'), 's');
            str = str.replace(new RegExp('[ţťŧț]', 'g'), 't');
            str = str.replace(new RegExp('[þ]', 'g'), 'th');
            str = str.replace(new RegExp('[ùúûüũūŭůűųưừủửữứụựǖǘǔǚǜ]', 'g'), 'u');
            str = str.replace(new RegExp('[ŵ]', 'g'), 'w');
            str = str.replace(new RegExp('[ýÿŷỳỷỹỵ]', 'g'), 'y');
            str = str.replace(new RegExp('[źżž]', 'g'), 'z');
            return str;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static remove_whitespaces(str = ''){
            str = str.replace(/[\r\n\t ]+/g, ' ').trim();
            return str;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static sanitize_title(str = ''){
            str = helper.remove_accents(str);
            str = helper.sanitize_title_with_dashes(str);
            return str;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static sanitize_title_with_dashes(str = ''){
            str = str.toLowerCase();
            str = str.replace(/\s+/g, ' ');
            str = str.trim();
            str = str.replaceAll(' ', '-');
            str = str.replace(/[^a-z0-9-_]/g, '');
            return str;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static slugify(str = ''){
            str = str.replaceAll('\\', '_'); // Fix namespaces.
            str = helper.sanitize_title(str);
            str = str.replaceAll('_', '-'); // Fix canonicalized.
            return str.replace(/^-+|-+$/g, '');
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static str_prefix(str = '', prefix = ''){
            prefix = helper.canonicalize(prefix);
            str = helper.remove_whitespaces(str);
            if(!str){
                return prefix;
            }
            if(!prefix){
                prefix = str;
            }
            if(str.indexOf(prefix) === 0){
                return str; // Text is already prefixed.
            }
            return prefix + '_' + str;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static str_slug(str = '', slug = ''){
            slug = helper.slugify(slug);
            str = helper.remove_whitespaces(str);
            if(!str){
                return slug;
            }
            if(!slug){
                slug = str;
            }
            if(str.indexOf(prefix) === 0){
                return str; // Text is already slugged.
            }
            return slug + '-' + str;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // URLs
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static add_query_arg(key = '', value = '', url = ''){
            var a = {},
                href = '';
            a = helper.get_a(url);
            if(a.protocol){
                href += a.protocol + '//';
            }
            if(a.hostname){
                href += a.hostname;
            }
            if(a.port){
                href += ':' + a.port;
            }
            if(a.pathname){
                if(a.pathname[0] !== '/'){
                    href += '/';
                }
                href += a.pathname;
            }
            if(a.search){
                var search = [],
                    search_object = helper.parse_str(a.search);
                $.each(search_object, function(k, v){
                    if(k != key){
                        search.push(k + '=' + v);
                    }
                });
                if(search.length > 0){
                    href += '?' + search.join('&') + '&';
                } else {
                    href += '?';
                }
            } else {
                href += '?';
            }
            href += key + '=' + value;
            if(a.hash){
                href += a.hash;
            }
            return href;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static add_query_args(args = [], url = ''){
            var a = {},
                href = '';
            a = helper.get_a(url);
            if(a.protocol){
                href += a.protocol + '//';
            }
            if(a.hostname){
                href += a.hostname;
            }
            if(a.port){
                href += ':' + a.port;
            }
            if(a.pathname){
                if(a.pathname[0] !== '/'){
                    href += '/';
                }
                href += a.pathname;
            }
            if(a.search){
                var search = [],
                    search_object = helper.parse_str(a.search);
                $.each(search_object, function(k, v){
                    if(!(k in args)){
                        search.push(k + '=' + v);
                    }
                });
                if(search.length > 0){
                    href += '?' + search.join('&') + '&';
                } else {
                    href += '?';
                }
            } else {
                href += '?';
            }
            $.each(args, function(k, v){
                href += k + '=' + v + '&';
            });
            href = href.slice(0, -1);
            if(a.hash){
                href += a.hash;
            }
            return href;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        static get_a(url = ''){
            var a = document.createElement('a');
            if(!_.isUndefined(url) && url !== ''){
                a.href = url;
            } else {
                a.href = $(location).attr('href');
            }
            return a;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static get_locale(){
            var locale = helper.object_property('locale');
            return (_.isNull(locale) ? '' : locale);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static get_query_arg(key = '', url = ''){
            var search_object = {};
            search_object = helper.get_query_args(url);
            if(!_.isUndefined(search_object[key])){
                return search_object[key];
            }
            return '';
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        static get_query_args(url = ''){
            var a = {};
            a = helper.get_a(url);
            if(a.search){
                return helper.parse_str(a.search);
            }
            return {};
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static home_url(){
            var home_url = helper.object_property('home_url');
            return (_.isNull(home_url) ? '' : home_url);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static mu_plugins_url(){
            var mu_plugins_url = helper.object_property('mu_plugins_url');
            return (_.isNull(mu_plugins_url) ? '' : mu_plugins_url);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        static parse_str(str = ''){
            var i = 0, search_object = {},
                search_array = str.replace('?', '').split('&');
            for(i = 0; i < search_array.length; i ++){
                search_object[search_array[i].split('=')[0]] = search_array[i].split('=')[1];
            }
            return search_object;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object|string
         */
        static parse_url(url = '', component = ''){
            var a = {},
                components = {},
                keys = ['protocol', 'hostname', 'port', 'pathname', 'search', 'hash'];
            a = helper.get_a(url);
            if(_.isUndefined(component) || component === ''){
                $.map(keys, function(c){
                    components[c] = a[c];
                });
                return components;
            } else if($.inArray(component, keys) !== -1){
                return a[component];
            } else {
                return '';
            }
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static plugins_url(){
            var plugins_url = helper.object_property('plugins_url');
            return (_.isNull(plugins_url) ? '' : plugins_url);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static site_url(){
            var site_url = helper.object_property('site_url');
            return (_.isNull(site_url) ? '' : site_url);
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Page Visibility API
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string
         */
        static do_visibilitychange(event){
            helper.do_action('visibilitychange', helper.is_document_hidden()); // Hidden.
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        static document_visibility_change_event(){
            var visibilityChange = '';
            if(!_.isUndefined(document.hidden)){
                visibilityChange = 'visibilitychange'; // Opera 12.10 and Firefox 18 and later support
            } else if(!_.isUndefined(document.webkitHidden)){
                visibilityChange = 'webkitvisibilitychange';
            } else if(!_.isUndefined(document.msHidden)){
                visibilityChange = 'msvisibilitychange';
            } else if(!_.isUndefined(document.mozHidden)){
                visibilityChange = 'mozvisibilitychange'; // Deprecated
            }
            return visibilityChange;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        static is_document_hidden(){
            var hidden = false;
            if(!_.isUndefined(document.hidden)){
                hidden = document.hidden; // Opera 12.10 and Firefox 18 and later support
            } else if(!_.isUndefined(document.webkitHidden)){
                hidden = document.webkitHidden;
            } else if(!_.isUndefined(document.msHidden)){
                hidden = document.msHidden;
            } else if(!_.isUndefined(document.mozHidden)){
                hidden = document.mozHidden; // Deprecated
            }
            return hidden;
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        static track_document_visibility(){
            $(function(){
                var event_name = helper.document_visibility_change_event();
                $(document).on(event_name, helper.do_visibilitychange);
            });
        };

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    window.ldc = helper;

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

})(window, jQuery);
