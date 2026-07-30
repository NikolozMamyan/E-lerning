(function (global) {
    'use strict';

    var api = null;
    var initialized = false;
    var finished = false;
    var maxDepth = 20;

    function findInHierarchy(startWindow) {
        var current = startWindow;
        var depth = 0;

        while (current && depth < maxDepth) {
            try {
                if (current.API) {
                    return current.API;
                }
                if (!current.parent || current.parent === current) {
                    break;
                }
                current = current.parent;
            } catch (error) {
                break;
            }
            depth += 1;
        }

        return null;
    }

    function findApi() {
        var found = findInHierarchy(global);
        if (found) {
            return found;
        }

        try {
            if (global.opener && !global.opener.closed) {
                return findInHierarchy(global.opener);
            }
        } catch (error) {
            return null;
        }

        return null;
    }

    function isSuccess(value) {
        return value === true || String(value).toLowerCase() === 'true';
    }

    function call(method) {
        if (!api || typeof api[method] !== 'function') {
            return null;
        }

        try {
            var argumentsList = Array.prototype.slice.call(arguments, 1);
            return api[method].apply(api, argumentsList);
        } catch (error) {
            return null;
        }
    }

    global.Scorm = {
        initialize: function () {
            if (initialized) {
                return true;
            }

            api = findApi();
            if (!api) {
                return false;
            }

            initialized = isSuccess(call('LMSInitialize', ''));
            finished = false;

            return initialized;
        },

        getValue: function (key) {
            if (!initialized) {
                return '';
            }

            var value = call('LMSGetValue', key);
            return value === null || typeof value === 'undefined' ? '' : String(value);
        },

        setValue: function (key, value) {
            if (!initialized || finished) {
                return false;
            }

            return isSuccess(call('LMSSetValue', key, String(value)));
        },

        commit: function () {
            if (!initialized || finished) {
                return false;
            }

            return isSuccess(call('LMSCommit', ''));
        },

        finish: function () {
            if (!initialized || finished) {
                return false;
            }

            var result = isSuccess(call('LMSFinish', ''));
            finished = true;
            initialized = false;

            return result;
        },

        getLastError: function () {
            if (!api || typeof api.LMSGetLastError !== 'function') {
                return '0';
            }

            try {
                return String(api.LMSGetLastError());
            } catch (error) {
                return '0';
            }
        },

        isConnected: function () {
            return initialized && !finished;
        }
    };
}(window));
