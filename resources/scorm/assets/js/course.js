(function () {
    'use strict';

    var elements = {};
    var state = {
        course: null,
        currentIndex: 0,
        positions: [],
        maxPercent: [],
        frontierSeconds: [],
        completed: [],
        pendingResume: 0,
        lastMediaTime: 0,
        lastCommitAt: 0,
        sessionStartedAt: Date.now(),
        seeking: false,
        finished: false,
        courseCompleted: false
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function initializeElements() {
        elements.courseTitle = byId('course-title');
        elements.playlist = byId('playlist');
        elements.video = byId('course-video');
        elements.videoTitle = byId('video-title');
        elements.videoPosition = byId('video-position');
        elements.videoProgress = byId('video-progress');
        elements.videoProgressText = byId('video-progress-text');
        elements.globalProgress = byId('global-progress');
        elements.globalProgressText = byId('global-progress-text');
        elements.previousButton = byId('previous-button');
        elements.nextButton = byId('next-button');
        elements.completionMessage = byId('completion-message');
        elements.errorMessage = byId('error-message');
    }

    function showError(message) {
        elements.errorMessage.textContent = message;
        elements.errorMessage.hidden = false;
    }

    function fetchCourse() {
        return fetch('data/course.json', { cache: 'no-store' }).then(function (response) {
            if (!response.ok) {
                throw new Error('The course data file could not be loaded.');
            }
            return response.json();
        });
    }

    function validateCourse(course) {
        if (!course || typeof course.title !== 'string' || !Array.isArray(course.videos) || course.videos.length === 0) {
            throw new Error('The course data is invalid.');
        }

        course.completionThreshold = Math.min(100, Math.max(1, Number(course.completionThreshold) || 90));
        course.commitIntervalSeconds = Math.max(1, Number(course.commitIntervalSeconds) || 10);
        course.videos.forEach(function (video) {
            if (!video || typeof video.title !== 'string' || typeof video.src !== 'string') {
                throw new Error('A course video is invalid.');
            }
        });

        return course;
    }

    function readSuspendData() {
        var raw = Scorm.getValue('cmi.suspend_data');
        if (!raw) {
            return null;
        }

        try {
            var data = JSON.parse(raw);
            return data && typeof data === 'object' ? data : null;
        } catch (error) {
            return null;
        }
    }

    function readLocation() {
        var raw = Scorm.getValue('cmi.core.lesson_location');
        var parts = raw.split('|');
        if (parts.length !== 2) {
            return null;
        }

        var index = Number(parts[0]);
        var seconds = Number(parts[1]);
        if (!Number.isFinite(index) || !Number.isFinite(seconds)) {
            return null;
        }

        return { index: Math.floor(index), seconds: Math.max(0, seconds) };
    }

    function restoreProgress() {
        var count = state.course.videos.length;
        state.positions = new Array(count).fill(0);
        state.maxPercent = new Array(count).fill(0);
        state.frontierSeconds = new Array(count).fill(0);
        state.completed = [];

        var suspend = readSuspendData();
        if (suspend) {
            if (Array.isArray(suspend.m)) {
                suspend.m.slice(0, count).forEach(function (value, index) {
                    state.maxPercent[index] = Math.min(100, Math.max(0, Number(value) || 0));
                });
            }
            if (Array.isArray(suspend.d)) {
                suspend.d.forEach(function (index) {
                    if (Number.isInteger(index) && index >= 0 && index < count && state.completed.indexOf(index) === -1) {
                        state.completed.push(index);
                    }
                });
            }
            if (Number.isInteger(suspend.v) && suspend.v >= 0 && suspend.v < count) {
                state.currentIndex = suspend.v;
            }
            if (Number.isFinite(Number(suspend.p))) {
                state.positions[state.currentIndex] = Math.max(0, Number(suspend.p));
            }
        }

        var location = readLocation();
        if (location && location.index >= 0 && location.index < count) {
            state.currentIndex = location.index;
            state.positions[state.currentIndex] = location.seconds;
        }

        state.maxPercent.forEach(function (percent, index) {
            if (percent >= state.course.completionThreshold && state.completed.indexOf(index) === -1) {
                state.completed.push(index);
            }
        });
    }

    function initializeScormSession() {
        Scorm.initialize();
        var status = Scorm.getValue('cmi.core.lesson_status');
        state.courseCompleted = status === 'completed';

        if (!status || status === 'not attempted') {
            Scorm.setValue('cmi.core.lesson_status', 'incomplete');
            Scorm.setValue('cmi.core.exit', 'suspend');
            Scorm.commit();
            state.lastCommitAt = Date.now();
        }
    }

    function renderPlaylist() {
        elements.playlist.textContent = '';

        state.course.videos.forEach(function (video, index) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            var number = document.createElement('span');
            var label = document.createElement('span');

            button.type = 'button';
            button.className = 'playlist-button';
            button.dataset.index = String(index);
            button.setAttribute('aria-current', index === state.currentIndex ? 'true' : 'false');
            if (state.completed.indexOf(index) !== -1) {
                button.classList.add('is-complete');
            }

            number.className = 'playlist-index';
            number.textContent = state.completed.indexOf(index) !== -1 ? '✓' : String(index + 1);
            label.textContent = video.title;
            button.appendChild(number);
            button.appendChild(label);
            button.addEventListener('click', function () {
                navigateTo(index);
            });
            item.appendChild(button);
            elements.playlist.appendChild(item);
        });
    }

    function loadVideo(index, resumeSeconds) {
        var video = state.course.videos[index];
        state.currentIndex = index;
        state.pendingResume = Math.max(0, Number(resumeSeconds) || 0);
        state.lastMediaTime = 0;
        state.seeking = false;

        elements.videoTitle.textContent = video.title;
        elements.videoPosition.textContent = 'Chapter '+(index + 1)+' of '+state.course.videos.length;
        elements.video.src = video.src;
        elements.video.load();
        elements.previousButton.disabled = index === 0;
        elements.nextButton.disabled = index === state.course.videos.length - 1;
        document.title = video.title+' – '+state.course.title;
        renderPlaylist();
        updateProgressDisplay();
    }

    function navigateTo(index) {
        if (index < 0 || index >= state.course.videos.length || index === state.currentIndex) {
            return;
        }

        saveProgress(true, false);
        loadVideo(index, state.positions[index]);
    }

    function onLoadedMetadata() {
        var duration = elements.video.duration;
        if (!Number.isFinite(duration) || duration <= 0) {
            return;
        }

        state.frontierSeconds[state.currentIndex] = duration * state.maxPercent[state.currentIndex] / 100;
        var maximumResume = Math.max(0, duration - 0.25);
        var resume = Math.min(maximumResume, state.pendingResume);
        if (resume > 0) {
            elements.video.currentTime = resume;
        }
        state.positions[state.currentIndex] = resume;
        state.lastMediaTime = resume;
        updateProgressDisplay();
    }

    function onTimeUpdate() {
        var current = elements.video.currentTime;
        var duration = elements.video.duration;
        var index = state.currentIndex;

        if (!Number.isFinite(current) || !Number.isFinite(duration) || duration <= 0) {
            return;
        }

        if (!state.seeking) {
            var delta = current - state.lastMediaTime;
            var frontier = state.frontierSeconds[index] || 0;
            if (delta >= 0 && delta <= 2.5 && state.lastMediaTime <= frontier + 2) {
                state.frontierSeconds[index] = Math.max(frontier, current);
                state.maxPercent[index] = Math.min(100, state.frontierSeconds[index] / duration * 100);
            }
        }

        state.lastMediaTime = current;
        state.positions[index] = current;
        evaluateCompletion(index);
        updateProgressDisplay();

        if (Date.now() - state.lastCommitAt >= state.course.commitIntervalSeconds * 1000) {
            saveProgress(true, false);
        }
    }

    function evaluateCompletion(index) {
        if (state.maxPercent[index] >= state.course.completionThreshold && state.completed.indexOf(index) === -1) {
            state.completed.push(index);
            renderPlaylist();
        }

        if (state.completed.length === state.course.videos.length && !state.courseCompleted) {
            state.courseCompleted = true;
            elements.completionMessage.hidden = false;
            Scorm.setValue('cmi.core.lesson_status', 'completed');
            Scorm.setValue('cmi.core.exit', '');
            saveProgress(true, false);
        }
    }

    function updateProgressDisplay() {
        var currentPercent = state.maxPercent[state.currentIndex] || 0;
        var total = state.maxPercent.reduce(function (sum, value) {
            return sum + Math.min(100, value || 0);
        }, 0);
        var globalPercent = state.maxPercent.length > 0 ? total / state.maxPercent.length : 0;

        elements.videoProgress.value = currentPercent;
        elements.videoProgressText.textContent = Math.floor(currentPercent)+' %';
        elements.globalProgress.value = globalPercent;
        elements.globalProgressText.textContent = Math.floor(globalPercent)+' %';
        elements.completionMessage.hidden = !state.courseCompleted;
    }

    function compactProgress() {
        return JSON.stringify({
            v: state.currentIndex,
            p: Math.max(0, Math.round(state.positions[state.currentIndex] || 0)),
            m: state.maxPercent.map(function (value) {
                return Math.min(100, Math.max(0, Math.floor(value || 0)));
            }),
            d: state.completed.slice().sort(function (left, right) {
                return left - right;
            })
        });
    }

    function formatSessionTime(milliseconds) {
        var seconds = Math.max(0, Math.floor(milliseconds / 1000));
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor(seconds % 3600 / 60);
        var remainingSeconds = seconds % 60;

        return String(hours).padStart(4, '0')+':'+String(minutes).padStart(2, '0')+':'+String(remainingSeconds).padStart(2, '0');
    }

    function saveProgress(commit, finish) {
        if (!state.course || state.finished) {
            return;
        }

        if (Number.isFinite(elements.video.currentTime)) {
            state.positions[state.currentIndex] = elements.video.currentTime;
        }

        Scorm.setValue('cmi.core.lesson_location', state.currentIndex+'|'+Math.max(0, Math.round(state.positions[state.currentIndex] || 0)));
        Scorm.setValue('cmi.suspend_data', compactProgress());
        Scorm.setValue('cmi.core.session_time', formatSessionTime(Date.now() - state.sessionStartedAt));
        Scorm.setValue('cmi.core.exit', state.courseCompleted ? '' : 'suspend');

        if (commit || finish) {
            Scorm.commit();
            state.lastCommitAt = Date.now();
        }
        if (finish) {
            Scorm.finish();
            state.finished = true;
        }
    }

    function bindEvents() {
        elements.video.addEventListener('loadedmetadata', onLoadedMetadata);
        elements.video.addEventListener('timeupdate', onTimeUpdate);
        elements.video.addEventListener('play', function () {
            state.lastMediaTime = elements.video.currentTime;
        });
        elements.video.addEventListener('pause', function () {
            saveProgress(true, false);
        });
        elements.video.addEventListener('seeking', function () {
            state.seeking = true;
        });
        elements.video.addEventListener('seeked', function () {
            state.seeking = false;
            state.lastMediaTime = elements.video.currentTime;
            state.positions[state.currentIndex] = elements.video.currentTime;
            saveProgress(true, false);
        });
        elements.video.addEventListener('ended', function () {
            evaluateCompletion(state.currentIndex);
            saveProgress(true, false);
        });
        elements.video.addEventListener('error', function () {
            showError('The video cannot be played by this browser.');
        });
        elements.previousButton.addEventListener('click', function () {
            navigateTo(state.currentIndex - 1);
        });
        elements.nextButton.addEventListener('click', function () {
            navigateTo(state.currentIndex + 1);
        });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                saveProgress(true, false);
            }
        });
        window.addEventListener('pagehide', function () {
            saveProgress(true, true);
        });
        window.addEventListener('beforeunload', function () {
            saveProgress(true, true);
        });
    }

    function start() {
        initializeElements();
        bindEvents();

        fetchCourse().then(function (course) {
            state.course = validateCourse(course);
            elements.courseTitle.textContent = state.course.title;
            initializeScormSession();
            restoreProgress();
            loadVideo(state.currentIndex, state.positions[state.currentIndex]);
        }).catch(function (error) {
            showError(error && error.message ? error.message : 'The course could not be started.');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
