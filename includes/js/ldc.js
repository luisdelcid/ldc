/*
 * LDC, the JavaScript side.
 *
 * Every function is a method of LDC, reached through ldc(), the same way the PHP
 * side works. E.g. ldc().slugify(title); Or without the call: ldc.slugify(title);
 *
 * Everything runs inside the function below, so only the three names it
 * exports on purpose reach the global scope: ldc, LDC and LDC_Singleton.
 */

(function(){

    'use strict';

    // -------------------------------------------------------------------------
    //
    // Singleton
    //
    // -------------------------------------------------------------------------

    /**
     * One instance per class, like the PHP LDC_Singleton keeps them.
     */
    const instances = new Map();

    class LDC_Singleton {

        /**
         * Returns the one instance of the class it is called on, creating it the
         * first time. `this` is that class, so each subclass gets its own.
         *
         * @return LDC_Singleton
         */
        static get_instance(){
            if(!instances.has(this)){
                instances.set(this, new this());
            }
            return instances.get(this);
        }

    }

    // -------------------------------------------------------------------------
    //
    // LDC
    //
    // -------------------------------------------------------------------------

    /**
     * The stack at the last lookup of one of the methods that read the stack,
     * taken by ldc() in the frame that looked it up: see _restore_caller_frame().
     */
    let caller_error = null;

    /**
     * The instance behind each Proxy the LDC constructor returns. ldc reads from
     * it directly: going through that Proxy would note the stack a second time,
     * one frame deeper.
     */
    const behind = new WeakMap();

    /**
     * The get trap of the Proxy ldc() hands the instance out behind, and of ldc
     * itself. It notes the stack each time one of the methods that read it is
     * looked up (see _caller_methods()), and it does so inline, in its own frame,
     * so the frame right below it is always the one that looked the method up:
     * the one _restore_caller_frame() puts back. instance() returns the instance
     * itself, not its Proxy. A property the instance does not have is read from
     * the Proxy's own target, which for ldc is the function: ldc.call(), ldc.name.
     *
     * @return function
     */
    function caller_trap(instance){
        var caller_methods = null;
        return function(target, name){
            var fake_function = null,
                object = instance();
            caller_methods = caller_methods || object._caller_methods();
            if(caller_methods.indexOf(name) !== -1){
                try {
                    fake_function();
                } catch(e){
                    caller_error = e;
                }
            }
            return Reflect.get(name in object ? object : target, name);
        };
    }

    class LDC extends LDC_Singleton {

        /**
         * Binds every method to the instance, so a method can be handed over as a
         * callback — jQuery.map(titles, ldc().slugify) — the way the functions
         * could. Taken off the object unbound, it would lose `this`.
         *
         * What it returns is the instance behind a Proxy that notes the stack
         * each time one of the methods that read it is looked up (see
         * caller_trap()). The lookup runs in the caller's own frame, before the
         * call does, which is what lets _restore_caller_frame() put that frame
         * back when a tail call has taken it off. Methods run on the instance, so
         * calls inside the class never go through the Proxy.
         *
         * @return LDC
         */
        constructor(){
            super();
            for(let proto = Object.getPrototypeOf(this); proto && proto !== LDC_Singleton.prototype; proto = Object.getPrototypeOf(proto)){
                for(const name of Object.getOwnPropertyNames(proto)){
                    if(name === 'constructor' || Object.prototype.hasOwnProperty.call(this, name) || typeof(this[name]) !== 'function'){
                        continue;
                    }
                    this[name] = this[name].bind(this);
                }
            }
            const proxy = new Proxy(this, {get: caller_trap(() => this)});
            behind.set(proxy, this);
            return proxy;
        }

        // ---------------------------------------------------------------------
        //
        // Hardcoded
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        document_has_visibility_event(){
            var event_name = this.document_visibility_change_event(),
                events = jQuery._data(document, 'events'),
                handler = this.do_visibilitychange_action, // Bound once, in the constructor, so it is always the same function.
                has_visibility_event = false;
            if(typeof(events) === 'undefined' || typeof(events[event_name]) === 'undefined'){
                return has_visibility_event; // jQuery keeps no events object for the document until something is bound to it.
            }
            jQuery.each(events[event_name], function(index, value){
                if(value.handler !== handler){
                    return true; // Continue.
                }
                has_visibility_event = true;
                return false; // Break.
            });
            return has_visibility_event;
        }

        // ---------------------------------------------------------------------

        /**
         * @return LDC_Singleton|null
         */
        get_instance(class_name = ''){
            var parent = 'LDC_Singleton'; // Hardcoded.
            if(!class_name){
                return null;
            }
            if(!_.isFunction(window[class_name])){
                return null;
            }
            if(!_.isFunction(window[parent])){
                return null;
            }
            if(!this.is_subclass_of(window[class_name], parent)){
                return null;
            }
            return window[class_name].get_instance();
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * The methods whose result depends on the file that calls them, so ldc()
         * notes the stack whenever one of them is looked up: see the constructor.
         * A method that reads the stack, itself or through one of these, belongs
         * here.
         *
         * @return array
         */
        _caller_methods(){
            return ['add_plugin_action', 'add_plugin_filter', 'apply_plugin_filters', 'caller_url', 'debug_backtrace', 'did_plugin_action', 'did_plugin_filter', 'do_plugin_action', 'doing_plugin_action', 'doing_plugin_filter', 'has_plugin_action', 'has_plugin_filter', 'plugin_folder', 'plugin_hook_name', 'plugin_prefix', 'plugin_slug', 'remove_all_plugin_actions', 'remove_all_plugin_filters', 'remove_plugin_action', 'remove_plugin_filter']; // Hardcoded.
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string
         */
        _namespace(){
            return 'luisdelcid/ldc/ldc'; // Hardcoded. The unique namespace identifying the callback in the form `vendor/plugin/function`.
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string
         */
        _object_name(){
            return 'ldc_l10n'; // Hardcoded.
        }

        // ---------------------------------------------------------------------
        //
        // Error Handling
        //
        // ---------------------------------------------------------------------

        /**
         * The file of a caller up the stack: 0 is whoever called this method, 1
         * whoever called that one, and so on. It counts frames, and Safari
         * implements proper tail calls in strict code: a frame that returns a call
         * directly is gone before the call runs, and the count lands one file off.
         * A class body is strict, so no method on the way here may do that, like
         * return this.plugin_prefix(...): store the result, then return it. A
         * caller outside that does it gets its frame back from
         * _restore_caller_frame().
         *
         * @return string
         */
        caller_url(index = 0){
            var debug_backtrace = null;
            index = this.absint(index) + 1;
            debug_backtrace = this.debug_backtrace(index);
            if(_.isNull(debug_backtrace)){
                return '';
            }
            if(typeof(debug_backtrace.fileName) === 'undefined'){
                return '';
            }
            return debug_backtrace.fileName;
        }

        // ---------------------------------------------------------------------

        /**
         * @return object
         */
        debug_backtrace(index = 0){
            var backtrace = [],
                error = null,
                fake_function = null,
                limit = 0;
            try {
                fake_function();
            } catch(e){
                error = e;
            }
            backtrace = this.debug_context(error);
            backtrace = this._restore_caller_frame(backtrace);
            if(_.isEmpty(backtrace)){
                return null;
            }
            index = this.absint(index) + 1;
            limit = index + 1;
            if(limit > backtrace.length){
                return null;
            }
            return backtrace[index];
        }

        // ---------------------------------------------------------------------

        /**
         * @return array
         */
        debug_context(error = null){
            var backtrace = [];
            if(!_.isError(error)){
                return backtrace;
            }
            jQuery.each(ErrorStackParser.parse(error), function(index, value){
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
                jQuery.each(stackframe, function(key, property){
                    if(typeof(value[key]) !== 'undefined'){
                        stackframe[key] = value[key];
                    }
                });
                backtrace.push(stackframe);
            });
            return backtrace;
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Safari implements proper tail calls in strict code, and not only in
         * LDC's: in a function that ends in return ldc().plugin_prefix(s), or in
         * an arrow like (s) => ldc().plugin_prefix(s), that function's frame is gone
         * before plugin_prefix() runs, and every frame counted from there lands on
         * whoever called it. The lookup of ldc().plugin_prefix runs earlier, in that
         * very frame, and ldc() keeps the stack it had (see the constructor). When
         * the frames right above LDC's own are exactly the ones that were
         * below that lookup, the frame in between is the one the tail call took,
         * and this puts it back. Anywhere else the stack is left as it is.
         *
         * @return array
         */
        _restore_caller_frame(backtrace = []){
            var caller = [],
                i = 0,
                own = '',
                start = 0;
            if(_.isNull(caller_error) || _.isEmpty(backtrace)){
                return backtrace;
            }
            caller = this.debug_context(caller_error); // 0: ldc()'s lookup, 1: the frame that looked the method up, 2 and on: its callers.
            if(caller.length < 3){
                return backtrace; // Looked up from the top level of a script, which no tail call can leave.
            }
            own = backtrace[0].fileName;
            while(start < backtrace.length && backtrace[start].fileName === own){
                start ++; // LDC's own frames.
            }
            for(i = 0; start + i < backtrace.length && i + 2 < caller.length; i ++){
                if(backtrace[start + i].fileName !== caller[i + 2].fileName || backtrace[start + i].lineNumber !== caller[i + 2].lineNumber || backtrace[start + i].columnNumber !== caller[i + 2].columnNumber){
                    return backtrace; // The frame is still there, or this is some other call.
                }
            }
            if(i === 0){
                return backtrace;
            }
            backtrace.splice(start, 0, caller[1]);
            return backtrace;
        }

        // ---------------------------------------------------------------------
        //
        // Hooks
        //
        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        add_action(hook_name = '', callback = null, priority = 10){
            wp.hooks.addAction(hook_name, this._namespace(), callback, priority);
        }

        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        add_filter(hook_name = '', callback = null, priority = 10){
            wp.hooks.addFilter(hook_name, this._namespace(), callback, priority);
        }

        // ---------------------------------------------------------------------

        /**
         * @return mixed
         */
        apply_filters(hook_name = '', value = null, ...arg){
            return wp.hooks.applyFilters(hook_name, value, ...arg);
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        current_action(){
            return wp.hooks.currentAction();
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        current_filter(){
            return wp.hooks.currentFilter();
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        did_action(hook_name = ''){
            return wp.hooks.didAction(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        did_filter(hook_name = ''){
            return wp.hooks.didFilter(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        do_action(hook_name = '', ...arg){
            wp.hooks.doAction(hook_name, ...arg);
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        doing_action(hook_name = ''){
            return wp.hooks.doingAction(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        doing_filter(hook_name = ''){
            return wp.hooks.doingFilter(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        has_action(hook_name = ''){
            return wp.hooks.hasAction(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        has_filter(hook_name = ''){
            return wp.hooks.hasFilter(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_action(hook_name = ''){
            return wp.hooks.removeAction(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_all_actions(hook_name = ''){
            return wp.hooks.removeAllActions(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_all_filters(hook_name = ''){
            return wp.hooks.removeAllFilters(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_filter(hook_name = ''){
            return wp.hooks.removeFilter(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------
        //
        // Magic methods
        //
        // ---------------------------------------------------------------------

        /**
         * @see JavaScript Magic Methods
         * @link https://gist.github.com/loilo/4d385d64e2b8552dcc12a0f5126b6df8
         *
         * @return object
         */
        add_magic_methods(clazz){
            /**
             * A toggle switch for the __isset method
             * Needed to control "prop in instance" inside of getters
             */
            let issetEnabled = true;
            const classHandler = Object.create(null);
            /**
             * Trap for class instantiation
             */
            classHandler.construct = (target, args, receiver) => {
                /**
                 * Wrapped class instance
                 */
                const instance = Reflect.construct(target, args, receiver);
                /**
                 * Instance traps
                 */
                const instanceHandler = Object.create(null);
                /**
                 * __get()
                 * Catches "instance.property"
                 */
                const get = Object.getOwnPropertyDescriptor(clazz.prototype, '__get');
                if(get){
                    instanceHandler.get = (target, name, receiver) => {
                        // We need to turn off the __isset() trap for the moment to establish compatibility with PHP behaviour
                        // PHP's __get() method doesn't care about its own __isset() method, so neither should we
                        issetEnabled = false;
                        const exists = Reflect.has(target, name);
                        issetEnabled = true;
                        if(exists){
                            return Reflect.get(target, name, receiver);
                        } else {
                            return get.value.call(target, name);
                        }
                    }
                }
                /**
                 * __set()
                 * Catches "instance.property = ..."
                 */
                const set = Object.getOwnPropertyDescriptor(clazz.prototype, '__set');
                if(set){
                    instanceHandler.set = (target, name, value, receiver) => {
                        if(name in target){
                            Reflect.set(target, name, value, receiver);
                        } else {
                            return target.__set.call(target, name, value);
                        }
                    }
                }
                /**
                 * __isset()
                 * Catches "'property' in instance"
                 */
                const isset = Object.getOwnPropertyDescriptor(clazz.prototype, '__isset');
                if(isset){
                    instanceHandler.has = (target, name) => {
                        if(!issetEnabled){
                            return Reflect.has(target, name);
                        }
                        return isset.value.call(target, name);
                    }
                }
                /**
                 * __unset()
                 * Catches "delete instance.property"
                 */
                const unset = Object.getOwnPropertyDescriptor(clazz.prototype, '__unset');
                if(unset){
                    instanceHandler.deleteProperty = (target, name) => {
                        return unset.value.call(target, name);
                    }
                }
                return new Proxy(instance, instanceHandler);
            }
            /**
             * __getStatic()
             * Catches "class.property"
             */
            if(Object.getOwnPropertyDescriptor(clazz, '__getStatic')){
                classHandler.get = (target, name, receiver) => {
                    if(name in target){
                        return target[name];
                    } else {
                        return target.__getStatic.call(receiver, name);
                    }
                }
            }
            /**
             * __setStatic()
             * Catches "class.property = ..."
             */
            if(Object.getOwnPropertyDescriptor(clazz, '__setStatic')){
                classHandler.set = (target, name, value, receiver) => {
                    if(name in target){
                        return target[name];
                    } else {
                        return target.__setStatic.call(receiver, name, value);
                    }
                }
            }
            return new Proxy(clazz, classHandler);
        }

        // ---------------------------------------------------------------------
        //
        // Miscellaneous
        //
        // ---------------------------------------------------------------------

        /**
         * @return int
         */
        absint(maybeint = 0){
            if(!_.isNumber(maybeint)){
                return 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
            }
            return Math.abs(parseInt(maybeint));
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        get_locale(){
            var locale = this.object_property('locale', this._object_name());
            return _.isNull(locale) ? '' : locale;
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        is_false(data){
            return jQuery.inArray(String(data), ['0', 'false', 'off']) > -1;
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        is_subclass_of(func = null, class_name = ''){
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
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        is_true(data){
            return jQuery.inArray(String(data), ['1', 'on', 'true']) > -1;
        }

        // ---------------------------------------------------------------------

        /**
         * @return mixed
         */
        object_property(key = '', object_name = ''){
            if(!key){
                return null;
            }
            if(!object_name){
                return null;
            }
            if(typeof(window[object_name]) === 'undefined'){
                return null;
            }
            if(typeof(window[object_name][key]) === 'undefined'){
                return null;
            }
            return window[object_name][key];
        }

        // ---------------------------------------------------------------------

        /**
         * @return int
         */
        rem_to_px(count){
            var unit = parseInt(jQuery('html').css('font-size'));
            if(!unit){
                unit = 16;
            }
            if(!_.isNumber(count)){
                return unit;
            }
            if(count > 0){
                return (count * unit);
            }
            return unit;
        }

        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        test(){
            console.log('Hello, World!');
        }

        // ---------------------------------------------------------------------
        //
        // Page Visibility API
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        document_visibility_change_event(){
            var visibilityChange = '';
            if(typeof(document.hidden) !== 'undefined'){
                visibilityChange = 'visibilitychange'; // Opera 12.10 and Firefox 18 and later support.
            } else if(typeof(document.webkitHidden) !== 'undefined'){
                visibilityChange = 'webkitvisibilitychange';
            } else if(typeof(document.msHidden) !== 'undefined'){
                visibilityChange = 'msvisibilitychange';
            } else if(typeof(document.mozHidden) !== 'undefined'){
                visibilityChange = 'mozvisibilitychange'; // Deprecated.
            }
            return visibilityChange;
        }

        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        enable_document_visibility(){
            var data = this;
            jQuery(($) => {
                var event_name = this.document_visibility_change_event();
                if(this.document_has_visibility_event()){
                    return; // It should not be added twice.
                }
                jQuery(document).on(event_name, data, this.do_visibilitychange_action);
            });
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        is_document_hidden(){
            var hidden = false;
            if(typeof(document.hidden) !== 'undefined'){
                hidden = document.hidden; // Opera 12.10 and Firefox 18 and later support.
            } else if(typeof(document.webkitHidden) !== 'undefined'){
                hidden = document.webkitHidden;
            } else if(typeof(document.msHidden) !== 'undefined'){
                hidden = document.msHidden;
            } else if(typeof(document.mozHidden) !== 'undefined'){
                hidden = document.mozHidden; // Deprecated.
            }
            return hidden;
        }

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string
         */
        do_visibilitychange_action(event){
            var data = event.data;
            this.do_action('visibilitychange', this.is_document_hidden()); // Hidden.
        }

        // ---------------------------------------------------------------------
        //
        // Paths
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        home_url(){
            var home_url = this.object_property('home_url', this._object_name());
            return _.isNull(home_url) ? '' : home_url;
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        mu_plugins_url(){
            var mu_plugins_url = this.object_property('mu_plugins_url', this._object_name());
            return _.isNull(mu_plugins_url) ? '' : mu_plugins_url;
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        plugins_url(){
            var plugins_url = this.object_property('plugins_url', this._object_name());
            return _.isNull(plugins_url) ? '' : plugins_url;
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        site_url(){
            var site_url = this.object_property('site_url', this._object_name());
            return _.isNull(site_url) ? '' : site_url;
        }

        // ---------------------------------------------------------------------
        //
        // PHP
        //
        // ---------------------------------------------------------------------

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/strings/md5.js
         *
         * @return string
         */
        md5(str = ''){
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
            str = this.utf8_encode(str);
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
        }

        // ---------------------------------------------------------------------

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/strings/ltrim.js
         *
         * @return string
         */
        ltrim(str = '', charlist = ''){
          //  discuss at: http://phpjs.org/functions/ltrim/
          // original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          //    input by: Erkekjetter
          // improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
          // bugfixed by: Onno Marsman
          //   example 1: ltrim('    Kevin van Zonneveld    ');
          //   returns 1: 'Kevin van Zonneveld    '

          charlist = !charlist ? ' \\s\u00A0' : (charlist + '')
            .replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:\\\-])/g, '\\$1'); // Every character is taken literally.
          var re = new RegExp('^[' + charlist + ']+', 'g');
          return (str + '')
            .replace(re, '');
        }

        // ---------------------------------------------------------------------

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/strings/rtrim.js
         *
         * @return string
         */
        rtrim(str = '', charlist = ''){
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
            .replace(/([\[\]\(\)\.\?\/\*\{\}\+\$\^\:\\\-])/g, '\\$1'); // Every character is taken literally.
          var re = new RegExp('[' + charlist + ']+$', 'g');
          return (str + '')
            .replace(re, '');
        }

        // ---------------------------------------------------------------------

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/xml/utf8_decode.js
         *
         * @return string
         */
        utf8_decode(str_data = ''){
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
        }

        // ---------------------------------------------------------------------

        /**
         * @see https://github.com/hirak/phpjs/blob/master/functions/xml/utf8_encode.js
         *
         * @return string
         */
        utf8_encode(argString = ''){
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
        }

        // ---------------------------------------------------------------------
        //
        // Plugin hooks
        //
        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        add_plugin_action(hook_name = '', callback = null, priority = 10){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.addAction(hook_name, this._namespace(), callback, priority);
        }

        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        add_plugin_filter(hook_name = '', callback = null, priority = 10){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.addFilter(hook_name, this._namespace(), callback, priority);
        }

        // ---------------------------------------------------------------------

        /**
         * @return mixed
         */
        apply_plugin_filters(hook_name = '', value = null, ...arg){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.applyFilters(hook_name, value, ...arg);
        }

        // ---------------------------------------------------------------------

        /**
         * @return null|string
         */
        current_plugin_action(){
            return wp.hooks.currentAction();
        }

        // ---------------------------------------------------------------------

        /**
         * @return null|string
         */
        current_plugin_filter(){
            return wp.hooks.currentFilter();
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        did_plugin_action(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.didAction(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        did_plugin_filter(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.didFilter(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return void
         */
        do_plugin_action(hook_name = '', ...arg){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            wp.hooks.doAction(hook_name, ...arg);
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        doing_plugin_action(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return false;
            }
            return wp.hooks.doingAction(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        doing_plugin_filter(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return false;
            }
            return wp.hooks.doingFilter(hook_name);
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        has_plugin_action(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return false;
            }
            return wp.hooks.hasAction(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return bool
         */
        has_plugin_filter(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return false;
            }
            return wp.hooks.hasFilter(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_all_plugin_actions(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeAllActions(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_all_plugin_filters(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeAllFilters(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_plugin_action(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeAction(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * @return int|void
         */
        remove_plugin_filter(hook_name = ''){
            hook_name = this.plugin_hook_name(hook_name);
            if(hook_name === ''){
                return;
            }
            return wp.hooks.removeFilter(hook_name, this._namespace());
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string
         */
        plugin_hook_name(hook_name = ''){
            var url = this.caller_url(2); // Two levels above.
            hook_name = this.plugin_prefix(hook_name, url);
            return hook_name;
        }

        // ---------------------------------------------------------------------
        //
        // Plugins
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        plugin_folder(url = ''){
            var folder = '',
                mu_plugins_url = this.mu_plugins_url(),
                path = '',
                plugins_url = this.plugins_url();
            if(!url){
                url = this.caller_url(1); // One level above.
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
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        plugin_prefix(str = '', url = ''){
            var plugin_folder = '';
            if(_.isEmpty(url)){
                url = this.caller_url(1); // One level above.
            }
            plugin_folder = this.plugin_folder(url);
            if(_.isEmpty(plugin_folder)){
                return '';
            }
            return this.str_prefix(str, plugin_folder);
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        plugin_slug(str = '', url = ''){
            var plugin_folder = '';
            if(_.isEmpty(url)){
                url = this.caller_url(1); // One level above.
            }
            plugin_folder = this.plugin_folder(url);
            if(_.isEmpty(plugin_folder)){
                return '';
            }
            return this.str_slug(str, plugin_folder);
        }

        // ---------------------------------------------------------------------
        //
        // Strings
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        canonicalize(str = ''){
            str = str.replaceAll('\\', '_'); // Fix namespaces.
            str = this.sanitize_title(str);
            str = str.replaceAll('-', '_'); // Fix slugified.
            return str.replace(/^_+|_+$/g, '');
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        remove_accents(str = '', locale = ''){
            if(locale === ''){
                locale = this.get_locale();
            }
            // Assume UTF-8.
            if(locale.startsWith('de')){
                str = str.replaceAll('Ä', 'Ae');
                str = str.replaceAll('ä', 'ae');
                str = str.replaceAll('Ö', 'Oe');
                str = str.replaceAll('ö', 'oe');
                str = str.replaceAll('Ü', 'Ue');
                str = str.replaceAll('ü', 'ue');
                str = str.replaceAll('ẞ', 'SS');
                str = str.replaceAll('ß', 'ss');
            } else if(locale === 'da_DK'){
                str = str.replaceAll('Æ', 'Ae');
                str = str.replaceAll('æ', 'ae');
                str = str.replaceAll('Ø', 'Oe');
                str = str.replaceAll('ø', 'oe');
                str = str.replaceAll('Å', 'Aa');
                str = str.replaceAll('å', 'aa');
            } else if(locale === 'ca'){
                str = str.replaceAll('l·l', 'll');
            } else if(locale === 'sr_RS' || locale === 'bs_BA'){
                str = str.replaceAll('Đ', 'DJ');
                str = str.replaceAll('đ', 'dj');
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
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        remove_whitespaces(str = ''){
            str = str.replace(/[\r\n\t ]+/g, ' ').trim();
            return str;
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        sanitize_title(str = ''){
            str = this.remove_accents(str);
            str = this.sanitize_title_with_dashes(str);
            return str;
        }

        // ---------------------------------------------------------------------

        /**
         * TODO: Improve.
         *
         * @return string
         */
        sanitize_title_with_dashes(str = ''){
            str = str.toLowerCase();
            str = str.replace(/\s+/g, ' ');
            str = str.trim();
            str = str.replaceAll(' ', '-');
            str = str.replace(/[^a-z0-9-_]/g, '');
            return str;
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        slugify(str = ''){
            str = str.replaceAll('\\', '_'); // Fix namespaces.
            str = this.sanitize_title(str);
            str = str.replaceAll('_', '-'); // Fix canonicalized.
            return str.replace(/^-+|-+$/g, '');
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        str_prefix(str = '', prefix = ''){
            prefix = this.canonicalize(prefix);
            str = this.remove_whitespaces(str);
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
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        str_slug(str = '', slug = ''){
            slug = this.slugify(slug);
            str = this.remove_whitespaces(str);
            if(!str){
                return slug;
            }
            if(!slug){
                slug = str;
            }
            if(str.indexOf(slug) === 0){
                return str; // Text is already slugged.
            }
            return slug + '-' + str;
        }

        // ---------------------------------------------------------------------
        //
        // URLs
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        add_query_arg(key = '', value = '', url = ''){
            var a = {},
                href = '';
            a = this.get_a(url);
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
                    search_object = this.parse_str(a.search);
                jQuery.each(search_object, function(k, v){
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
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        add_query_args(args = [], url = ''){
            var a = {},
                href = '';
            a = this.get_a(url);
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
                    search_object = this.parse_str(a.search);
                jQuery.each(search_object, function(k, v){
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
            jQuery.each(args, function(k, v){
                href += k + '=' + v + '&';
            });
            href = href.slice(0, -1);
            if(a.hash){
                href += a.hash;
            }
            return href;
        }

        // ---------------------------------------------------------------------

        /**
         * @return object
         */
        get_a(url = ''){
            var a = document.createElement('a');
            if(url !== ''){
                a.href = url;
            } else {
                a.href = jQuery(location).attr('href');
            }
            return a;
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        get_query_arg(key = '', url = ''){
            var search_object = {};
            search_object = this.get_query_args(url);
            if(typeof(search_object[key]) === 'undefined'){
                return '';
            }
            return search_object[key];
        }

        // ---------------------------------------------------------------------

        /**
         * @return object
         */
        get_query_args(url = ''){
            var a = {};
            a = this.get_a(url);
            if(a.search){
                return this.parse_str(a.search);
            }
            return {};
        }

        // ---------------------------------------------------------------------

        /**
         * @return object
         */
        parse_str(str){
            var decode = function(s){
                    try {
                        return decodeURIComponent(String(s).replace(/\+/g, ' ')); // '+' is a space in query strings.
                    } catch(e){
                        return s; // Malformed sequence (e.g. a lone '%'); return as-is instead of throwing.
                    }
                },
                i = 0,
                key = '',
                pair = [],
                search_object = {},
                value = '',
                search_array = String(str).replace('?', '').split('&');
            for(i = 0; i < search_array.length; i ++){
                if(search_array[i] === ''){
                    continue; // Skip empty segments.
                }
                pair = search_array[i].split('=');
                key = decode(pair[0]);
                if(!key){
                    continue;
                }
                value = (typeof(pair[1]) === 'undefined') ? '' : decode(pair.slice(1).join('=')); // slice/join preserves '=' inside values.
                search_object[key] = value;
            }
            return search_object;
        }

        // ---------------------------------------------------------------------

        /**
         * @return object|string
         */
        parse_url(url = '', component = ''){
            var a = {},
                components = {},
                keys = ['protocol', 'hostname', 'port', 'pathname', 'search', 'hash'];
            a = this.get_a(url);
            if(component === ''){
                jQuery.map(keys, function(c){
                    components[c] = a[c];
                });
                return components;
            } else if(jQuery.inArray(component, keys) !== -1){
                return a[component];
            } else {
                return '';
            }
        }

        // ---------------------------------------------------------------------
        //
        // Urchin Tracking Module (UTM)
        //
        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        current_utm_param(name = ''){
            var utm_params = this.current_utm_params();
            if(typeof(utm_params[name]) === 'undefined'){
                return '';
            }
            return utm_params[name];
        }

        // ---------------------------------------------------------------------

        /**
         * @return object
         */
        current_utm_params(){
            if(this.at_least_one_utm_get_param()){
                return this.utm_params_from_get(); // 1. GET
            }
            return this.utm_params_from_localstorage(); // 2. localStorage
        }

        // ---------------------------------------------------------------------

        /**
         * Keeps the UTM parameters of the URL, when it has any, in the browser's
         * localStorage for two months, the way PHP kept them in a cookie: a later
         * page without them still finds them, through current_utm_params(). All
         * of it happens in the browser, so a page a cache like Varnish serves the
         * same to everyone still keeps each visitor's own. A visit with UTM
         * parameters replaces all the ones kept before. ldc()->enable_utm() adds
         * the call to the page. E.g. ldc().enable_utm();
         *
         * @return void
         */
        enable_utm(){
            var lifetime = 60 * 24 * 60 * 60 * 1000, // Two months of 30 days, as the cookie had: https://support.google.com/analytics/answer/7667196?hl=en_US&utm_id=ad
                storage_key = '',
                value = '';
            if(!this.at_least_one_utm_get_param()){
                return;
            }
            storage_key = this.utm_localstorage_key();
            value = JSON.stringify({
                expires: Date.now() + lifetime,
                params: this.utm_params_from_get(),
            });
            try {
                window.localStorage.setItem(storage_key, value);
            } catch(e){
                // No localStorage here: turned off, full, or not allowed in this frame. Nothing is kept.
            }
        }

        // ---------------------------------------------------------------------

        /**
         * @return string
         */
        utm_param_name(name = ''){
            var utm_pairs = this.utm_pairs();
            if(typeof(utm_pairs[name]) === 'undefined'){
                return '';
            }
            return utm_pairs[name];
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return bool
         */
        at_least_one_utm_get_param(){
            var at_least_one = false,
                utm_params = this.utm_params_from_get();
            jQuery.each(utm_params, function(key, value){
                if(!value){
                    return true; // Continue.
                }
                at_least_one = true;
                return false; // Break.
            });
            return at_least_one;
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return array
         */
        utm_keys(){
            return _.keys(this.utm_pairs());
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string
         */
        utm_localstorage_key(){
            var storage_key = this.plugin_prefix('utm_parameters'); // Not returned directly: see caller_url().
            return storage_key;
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return object
         */
        utm_pairs(){
            var utm_pairs = {
                utm_campaign: 'Name',
                utm_content: 'Content',
                utm_id: 'ID',
                utm_medium: 'Medium',
                utm_source: 'Source',
                utm_term: 'Term',
            };
            return utm_pairs;
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return object
         */
        utm_params_from_get(){
            var query_args = this.get_query_args(),
                utm_params = {};
            jQuery.each(this.utm_keys(), function(index, key){
                if(typeof(query_args[key]) === 'undefined'){
                    utm_params[key] = '';
                } else {
                    utm_params[key] = query_args[key];
                }
            });
            return utm_params;
        }

        // ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return object
         */
        utm_params_from_localstorage(){
            var storage_key = this.utm_localstorage_key(),
                stored = null,
                utm_params = {};
            try {
                stored = JSON.parse(window.localStorage.getItem(storage_key));
            } catch(e){
                stored = null; // No localStorage here, or something else under that key.
            }
            if(stored === null || typeof(stored) !== 'object' || stored.params === null || typeof(stored.params) !== 'object' || typeof(stored.expires) !== 'number'){
                stored = {params: {}};
            } else if(stored.expires < Date.now()){
                stored = {params: {}};
                try {
                    window.localStorage.removeItem(storage_key); // Gone after two months, as the cookie would have been.
                } catch(e){
                    // No localStorage here.
                }
            }
            jQuery.each(this.utm_keys(), function(index, key){
                utm_params[key] = typeof(stored.params[key]) === 'string' ? stored.params[key] : '';
            });
            return utm_params;
        }

        // ---------------------------------------------------------------------

    }

    // -------------------------------------------------------------------------
    //
    // Exports
    //
    // -------------------------------------------------------------------------

    /**
     * The master function: returns the one LDC instance, from which every method
     * is reached. E.g. ldc().slugify(title);
     *
     * It is that instance too, without the call: ldc.slugify(title); A Proxy reads
     * every property the instance has from it, through the same trap the
     * instance is handed out behind, so ldc.plugin_prefix() names the same plugin
     * ldc().plugin_prefix() does. What the instance does not have is still the
     * function's: ldc.call(), ldc.name. A property set on it is set on the
     * instance, the way ldc().x = y sets it.
     *
     * @return LDC
     */
    const ldc = new Proxy(function ldc(){
        return LDC.get_instance();
    }, {
        get: caller_trap(() => behind.get(LDC.get_instance())),
        set: function(target, name, value){
            return Reflect.set(behind.get(LDC.get_instance()), name, value);
        },
    });

    // All three names are global and short, so another script may have taken one
    // first. Overwriting it would break that script, so it is left alone and the
    // console says which, instead of something failing far from the cause.
    const exported = {ldc: ldc, LDC: LDC, LDC_Singleton: LDC_Singleton};
    const taken = [];
    for(const name in exported){
        if(typeof(window[name]) !== 'undefined' && window[name] !== exported[name]){
            taken.push(name);
            continue;
        }
        window[name] = exported[name];
    }
    if(taken.length){
        console.error('LDC: ' + taken.join(', ') + ' already defined by another script, so LDC left ' + (taken.length === 1 ? 'it' : 'them') + ' alone.');
    }

})();
