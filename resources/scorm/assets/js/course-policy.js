(function (global) {
    'use strict';

    function number(value, fallback) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    global.CoursePolicy = {
        allowedSeekTime: function (requestedSeconds, watchedFrontierSeconds, toleranceSeconds) {
            var requested = Math.max(0, number(requestedSeconds, 0));
            var frontier = Math.max(0, number(watchedFrontierSeconds, 0));
            var tolerance = Math.max(0, number(toleranceSeconds, 1.5));
            return requested > frontier + tolerance ? frontier : requested;
        },

        canNavigate: function (targetIndex, highestUnlockedIndex) {
            var target = Math.floor(number(targetIndex, -1));
            var unlocked = Math.floor(number(highestUnlockedIndex, 0));
            return target >= 0 && target <= unlocked;
        }
    };
}(window));
