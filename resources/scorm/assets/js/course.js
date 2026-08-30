(function () {
    'use strict';

    var elements = {};
    var resumeData = null;
    var lessonStatus = '';
    var messageTimer = null;
    var state = {
        course: null,
        localized: false,
        language: null,
        videos: [],
        quiz: null,
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
        correctingSeek: false,
        finished: false,
        courseCompleted: false,
        stage: 'video',
        quizScore: null
    };

    var translations = {
        en: {
            overallProgress: 'Overall progress',
            watchedProgress: 'Watched progress',
            training: 'Training video',
            quiz: 'Final quiz',
            chapter: 'Chapter',
            of: 'of',
            previous: 'Previous',
            next: 'Next',
            openQuiz: 'Start quiz',
            backToVideo: 'Back to video',
            submitAnswers: 'Submit answers',
            knowledgeCheck: 'Knowledge check',
            instructions: 'Select every correct answer. One or more answers may be correct.',
            threshold: 'Pass mark: {value} %',
            question: 'Question',
            answerAll: 'Please answer all {total} questions before submitting.',
            passed: 'Passed with {score} %. Your result has been saved.',
            failed: 'Score: {score} %. You need {threshold} % to pass. Review your answers and try again.',
            completed: 'Course completed. Your result has been saved.',
            forwardBlocked: 'Fast-forward is disabled. You can go back or continue from the furthest watched point.',
            quizLocked: 'Complete the training video before opening the quiz.',
            loadError: 'The course could not be loaded.',
            videoError: 'The video cannot be played by this browser.',
            selected: 'Selected language',
            resume: 'Resume this language',
            lockedLanguage: 'A course attempt is already in progress in another language.'
        },
        de: {
            overallProgress: 'Gesamtfortschritt',
            watchedProgress: 'Angesehener Fortschritt',
            training: 'Schulungsvideo',
            quiz: 'Abschlusstest',
            chapter: 'Kapitel',
            of: 'von',
            previous: 'Zurück',
            next: 'Weiter',
            openQuiz: 'Quiz starten',
            backToVideo: 'Zurück zum Video',
            submitAnswers: 'Antworten absenden',
            knowledgeCheck: 'Wissenstest',
            instructions: 'Wählen Sie alle richtigen Antworten aus. Eine oder mehrere Antworten können richtig sein.',
            threshold: 'Bestehensgrenze: {value} %',
            question: 'Frage',
            answerAll: 'Bitte beantworten Sie alle {total} Fragen, bevor Sie absenden.',
            passed: 'Bestanden mit {score} %. Ihr Ergebnis wurde gespeichert.',
            failed: 'Ergebnis: {score} %. Zum Bestehen sind {threshold} % erforderlich. Prüfen Sie Ihre Antworten und versuchen Sie es erneut.',
            completed: 'Kurs abgeschlossen. Ihr Ergebnis wurde gespeichert.',
            forwardBlocked: 'Vorspulen ist deaktiviert. Sie können zurückgehen oder ab der zuletzt angesehenen Stelle fortfahren.',
            quizLocked: 'Schließen Sie das Schulungsvideo ab, bevor Sie das Quiz öffnen.',
            loadError: 'Der Kurs konnte nicht geladen werden.',
            videoError: 'Das Video kann in diesem Browser nicht abgespielt werden.',
            selected: 'Ausgewählte Sprache',
            resume: 'Diese Sprache fortsetzen',
            lockedLanguage: 'Ein Kursversuch in einer anderen Sprache wurde bereits begonnen.'
        }
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function initializeElements() {
        elements.languageScreen = byId('language-screen');
        elements.languageOptions = byId('language-options');
        elements.courseShell = byId('course-shell');
        elements.courseTitle = byId('course-title');
        elements.selectedLanguage = byId('selected-language');
        elements.overallProgressLabel = byId('overall-progress-label');
        elements.watchedProgressLabel = byId('watched-progress-label');
        elements.playlist = byId('playlist');
        elements.video = byId('course-video');
        elements.playerCard = byId('player-card');
        elements.videoTitle = byId('video-title');
        elements.videoPosition = byId('video-position');
        elements.videoProgress = byId('video-progress');
        elements.videoProgressText = byId('video-progress-text');
        elements.globalProgress = byId('global-progress');
        elements.globalProgressText = byId('global-progress-text');
        elements.previousButton = byId('previous-button');
        elements.nextButton = byId('next-button');
        elements.completionMessage = byId('completion-message');
        elements.navigationMessage = byId('navigation-message');
        elements.errorMessage = byId('error-message');
        elements.quizCard = byId('quiz-card');
        elements.quizEyebrow = byId('quiz-eyebrow');
        elements.quizTitle = byId('quiz-title');
        elements.quizThreshold = byId('quiz-threshold');
        elements.quizInstructions = byId('quiz-instructions');
        elements.quizForm = byId('quiz-form');
        elements.quizQuestions = byId('quiz-questions');
        elements.quizResult = byId('quiz-result');
        elements.quizBackButton = byId('quiz-back-button');
        elements.quizSubmitButton = byId('quiz-submit-button');
    }

    function text(key, replacements) {
        var code = state.language && translations[state.language.code] ? state.language.code : 'en';
        var value = translations[code][key] || translations.en[key] || key;

        Object.keys(replacements || {}).forEach(function (name) {
            value = value.replace('{'+name+'}', String(replacements[name]));
        });

        return value;
    }

    function showError(message) {
        elements.errorMessage.textContent = message;
        elements.errorMessage.hidden = false;
    }

    function showNavigationMessage(message) {
        window.clearTimeout(messageTimer);
        elements.navigationMessage.textContent = message;
        elements.navigationMessage.hidden = false;
        messageTimer = window.setTimeout(function () {
            elements.navigationMessage.hidden = true;
        }, 4200);
    }

    function fetchJson(path, errorMessage) {
        return fetch(path, { cache: 'no-store' }).then(function (response) {
            if (!response.ok) {
                throw new Error(errorMessage);
            }
            return response.json();
        });
    }

    function validateCourse(course) {
        if (!course || typeof course.title !== 'string') {
            throw new Error('The course data is invalid.');
        }

        course.completionThreshold = Math.min(100, Math.max(1, Number(course.completionThreshold) || 90));
        course.commitIntervalSeconds = Math.max(1, Number(course.commitIntervalSeconds) || 10);
        course.quizPassThreshold = Math.min(100, Math.max(1, Number(course.quizPassThreshold) || 80));

        if (Array.isArray(course.languages) && course.languages.length >= 2) {
            course.languages.forEach(function (language) {
                if (!language || typeof language.code !== 'string' || typeof language.title !== 'string' || !language.video || typeof language.video.src !== 'string' || typeof language.quizSrc !== 'string') {
                    throw new Error('A localized course resource is invalid.');
                }
            });
            return course;
        }

        if (!Array.isArray(course.videos) || course.videos.length === 0) {
            throw new Error('The course data is invalid.');
        }
        course.videos.forEach(function (video) {
            if (!video || typeof video.title !== 'string' || typeof video.src !== 'string') {
                throw new Error('A course video is invalid.');
            }
        });

        return course;
    }

    function validateQuiz(quiz) {
        if (!quiz || typeof quiz.title !== 'string' || !Array.isArray(quiz.questions) || quiz.questions.length === 0) {
            throw new Error('The quiz data is invalid.');
        }
        quiz.questions.forEach(function (question) {
            if (!question || typeof question.id !== 'string' || typeof question.text !== 'string' || !Array.isArray(question.options) || !Array.isArray(question.correct)) {
                throw new Error('A quiz question is invalid.');
            }
        });

        return quiz;
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

    function initializeScormSession() {
        Scorm.initialize();
        lessonStatus = Scorm.getValue('cmi.core.lesson_status');
        resumeData = readSuspendData();

        if (!lessonStatus || lessonStatus === 'not attempted') {
            lessonStatus = 'incomplete';
            Scorm.setValue('cmi.core.lesson_status', 'incomplete');
            Scorm.setValue('cmi.core.exit', 'suspend');
            Scorm.commit();
            state.lastCommitAt = Date.now();
        }
    }

    function hasStoredProgress() {
        return !!(resumeData && ((Array.isArray(resumeData.m) && resumeData.m.some(function (value) {
            return Number(value) > 0;
        })) || Number(resumeData.q) > 0 || resumeData.r));
    }

    function renderLanguageOptions() {
        elements.languageOptions.textContent = '';
        var lockedLanguage = resumeData && resumeData.l && hasStoredProgress() ? String(resumeData.l) : null;

        state.course.languages.forEach(function (language) {
            var button = document.createElement('button');
            var code = document.createElement('span');
            var copy = document.createElement('span');
            var title = document.createElement('strong');
            var detail = document.createElement('small');

            button.type = 'button';
            button.className = 'language-option';
            button.disabled = !!(lockedLanguage && lockedLanguage !== language.code);
            code.className = 'language-option-code';
            code.textContent = language.code.toUpperCase();
            title.textContent = language.label;
            detail.textContent = lockedLanguage === language.code ? translations[language.code].resume : language.title;
            if (button.disabled) {
                detail.textContent = translations[language.code] ? translations[language.code].lockedLanguage : translations.en.lockedLanguage;
            }
            copy.appendChild(title);
            copy.appendChild(detail);
            button.appendChild(code);
            button.appendChild(copy);
            button.addEventListener('click', function () {
                selectLanguage(language);
            });
            elements.languageOptions.appendChild(button);
        });

        elements.languageScreen.hidden = false;
    }

    function selectLanguage(language) {
        state.language = language;
        document.documentElement.lang = language.code;

        fetchJson(language.quizSrc, 'The quiz could not be loaded.').then(validateQuiz).then(function (quiz) {
            state.quiz = quiz;
            state.videos = [language.video];
            restoreProgress(resumeData && resumeData.l === language.code ? resumeData : null);
            applyLanguageCopy();
            elements.languageScreen.hidden = true;
            elements.courseShell.hidden = false;
            elements.selectedLanguage.hidden = false;
            elements.selectedLanguage.textContent = text('selected')+': '+language.label;
            loadVideo(state.currentIndex, state.positions[state.currentIndex]);

            if (resumeData && resumeData.l === language.code && resumeData.s === 'quiz' && state.completed.indexOf(0) !== -1) {
                showQuiz();
            }
            saveProgress(true, false);
        }).catch(function (error) {
            showError(error.message || text('loadError'));
            elements.courseShell.hidden = false;
        });
    }

    function applyLanguageCopy() {
        elements.courseTitle.textContent = state.language ? state.language.title : state.course.title;
        elements.overallProgressLabel.textContent = text('overallProgress');
        elements.watchedProgressLabel.textContent = text('watchedProgress');
        elements.previousButton.textContent = text('previous');
        elements.nextButton.textContent = state.localized ? text('openQuiz') : text('next');
        elements.quizEyebrow.textContent = text('knowledgeCheck');
        elements.quizTitle.textContent = state.quiz ? state.quiz.title : text('quiz');
        elements.quizThreshold.textContent = text('threshold', { value: state.course.quizPassThreshold });
        elements.quizInstructions.textContent = text('instructions');
        elements.quizBackButton.textContent = text('backToVideo');
        elements.quizSubmitButton.textContent = text('submitAnswers');
        elements.completionMessage.textContent = text('completed');
    }

    function restoreProgress(saved) {
        var count = state.videos.length;
        state.positions = new Array(count).fill(0);
        state.maxPercent = new Array(count).fill(0);
        state.frontierSeconds = new Array(count).fill(0);
        state.completed = [];
        state.currentIndex = 0;
        state.stage = 'video';
        state.quizScore = null;
        state.courseCompleted = lessonStatus === 'completed' || lessonStatus === 'passed';

        if (saved) {
            if (Array.isArray(saved.m)) {
                saved.m.slice(0, count).forEach(function (value, index) {
                    state.maxPercent[index] = Math.min(100, Math.max(0, Number(value) || 0));
                });
            }
            if (Array.isArray(saved.d)) {
                saved.d.forEach(function (index) {
                    if (Number.isInteger(index) && index >= 0 && index < count && state.completed.indexOf(index) === -1) {
                        state.completed.push(index);
                    }
                });
            }
            if (Number.isInteger(saved.v) && saved.v >= 0 && saved.v < count) {
                state.currentIndex = saved.v;
            }
            if (Number.isFinite(Number(saved.p))) {
                state.positions[state.currentIndex] = Math.max(0, Number(saved.p));
            }
            state.stage = saved.s === 'quiz' ? 'quiz' : 'video';
            state.quizScore = Number.isFinite(Number(saved.q)) ? Number(saved.q) : null;
            state.courseCompleted = state.courseCompleted || saved.r === 1;
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
        elements.completionMessage.hidden = !state.courseCompleted;
    }

    function highestUnlockedVideoIndex() {
        var index = 0;
        while (index < state.videos.length && state.completed.indexOf(index) !== -1) {
            index += 1;
        }
        return Math.min(state.videos.length - 1, index);
    }

    function renderPlaylist() {
        elements.playlist.textContent = '';

        if (state.localized) {
            renderStageButton('video', text('training'), 1, true, state.stage === 'video', state.completed.indexOf(0) !== -1);
            renderStageButton('quiz', text('quiz'), 2, state.completed.indexOf(0) !== -1, state.stage === 'quiz', state.courseCompleted);
            return;
        }

        var highestUnlocked = highestUnlockedVideoIndex();
        state.videos.forEach(function (video, index) {
            var unlocked = CoursePolicy.canNavigate(index, highestUnlocked);
            var item = document.createElement('li');
            var button = document.createElement('button');
            var number = document.createElement('span');
            var label = document.createElement('span');

            button.type = 'button';
            button.className = 'playlist-button';
            button.disabled = !unlocked;
            button.setAttribute('aria-current', index === state.currentIndex && state.stage === 'video' ? 'true' : 'false');
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

    function renderStageButton(stage, labelText, position, unlocked, active, complete) {
        var item = document.createElement('li');
        var button = document.createElement('button');
        var number = document.createElement('span');
        var label = document.createElement('span');

        button.type = 'button';
        button.className = 'playlist-button';
        button.disabled = !unlocked;
        button.setAttribute('aria-current', active ? 'true' : 'false');
        if (complete) {
            button.classList.add('is-complete');
        }
        number.className = 'playlist-index';
        number.textContent = complete ? '✓' : String(position);
        label.textContent = labelText;
        button.appendChild(number);
        button.appendChild(label);
        button.addEventListener('click', function () {
            if (stage === 'quiz') {
                showQuiz();
            } else {
                showVideo();
            }
        });
        item.appendChild(button);
        elements.playlist.appendChild(item);
    }

    function loadVideo(index, resumeSeconds) {
        var video = state.videos[index];
        state.currentIndex = index;
        state.stage = 'video';
        state.pendingResume = Math.max(0, Number(resumeSeconds) || 0);
        state.lastMediaTime = 0;
        state.seeking = false;
        state.correctingSeek = false;

        elements.videoTitle.textContent = video.title;
        elements.videoPosition.textContent = state.localized ? text('training') : text('chapter')+' '+(index + 1)+' '+text('of')+' '+state.videos.length;
        elements.video.src = video.src;
        elements.video.load();
        elements.previousButton.disabled = index === 0;
        elements.nextButton.disabled = state.localized ? state.completed.indexOf(0) === -1 : index === state.videos.length - 1 || state.completed.indexOf(index) === -1;
        document.title = video.title+' – '+state.course.title;
        showVideo();
        updateProgressDisplay();
    }

    function showVideo() {
        state.stage = 'video';
        elements.playerCard.hidden = false;
        elements.quizCard.hidden = true;
        renderPlaylist();
        saveProgress(false, false);
    }

    function navigateTo(index) {
        if (index < 0 || index >= state.videos.length || index === state.currentIndex) {
            return;
        }
        if (!CoursePolicy.canNavigate(index, highestUnlockedVideoIndex())) {
            showNavigationMessage(text('forwardBlocked'));
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
        var resume = Math.min(maximumResume, state.pendingResume, state.frontierSeconds[state.currentIndex] + 1.5);
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

        if (!state.seeking && !state.correctingSeek) {
            var delta = current - state.lastMediaTime;
            var frontier = state.frontierSeconds[index] || 0;
            if (delta >= 0 && delta <= 2.5 && state.lastMediaTime <= frontier + 2) {
                state.frontierSeconds[index] = Math.max(frontier, current);
                state.maxPercent[index] = Math.min(100, state.frontierSeconds[index] / duration * 100);
            }
        }

        state.lastMediaTime = current;
        state.positions[index] = current;
        evaluateVideoCompletion(index);
        updateProgressDisplay();

        if (Date.now() - state.lastCommitAt >= state.course.commitIntervalSeconds * 1000) {
            saveProgress(true, false);
        }
    }

    function onSeeking() {
        var requested = elements.video.currentTime;
        var frontier = state.frontierSeconds[state.currentIndex] || 0;
        var allowed = CoursePolicy.allowedSeekTime(requested, frontier, 1.5);

        if (allowed + 0.05 < requested) {
            state.correctingSeek = true;
            elements.video.currentTime = allowed;
            showNavigationMessage(text('forwardBlocked'));
            return;
        }

        state.seeking = true;
    }

    function onSeeked() {
        state.seeking = false;
        state.correctingSeek = false;
        state.lastMediaTime = elements.video.currentTime;
        state.positions[state.currentIndex] = elements.video.currentTime;
        saveProgress(true, false);
    }

    function evaluateVideoCompletion(index) {
        if (state.maxPercent[index] >= state.course.completionThreshold && state.completed.indexOf(index) === -1) {
            state.completed.push(index);
            renderPlaylist();
            elements.nextButton.disabled = state.localized ? false : index === state.videos.length - 1;
        }

        if (!state.localized && state.completed.length === state.videos.length && !state.courseCompleted) {
            state.courseCompleted = true;
            elements.completionMessage.textContent = text('completed');
            elements.completionMessage.hidden = false;
            Scorm.setValue('cmi.core.lesson_status', 'completed');
            Scorm.setValue('cmi.core.exit', '');
            saveProgress(true, false);
        }
    }

    function updateProgressDisplay() {
        var currentPercent = state.maxPercent[state.currentIndex] || 0;
        var globalPercent;

        if (state.localized) {
            var trainingPercent = Math.min(100, currentPercent / state.course.completionThreshold * 100);
            globalPercent = state.courseCompleted ? 100 : Math.round(trainingPercent * 0.8);
        } else {
            var total = state.maxPercent.reduce(function (sum, value) {
                return sum + Math.min(100, Math.max(0, value || 0));
            }, 0);
            globalPercent = state.videos.length > 0 ? Math.round(total / state.videos.length) : 0;
        }

        elements.videoProgress.value = Math.round(currentPercent);
        elements.videoProgressText.textContent = Math.round(currentPercent)+' %';
        elements.globalProgress.value = globalPercent;
        elements.globalProgressText.textContent = globalPercent+' %';
    }

    function renderQuiz() {
        elements.quizQuestions.textContent = '';
        state.quiz.questions.forEach(function (question, index) {
            var fieldset = document.createElement('fieldset');
            var legend = document.createElement('legend');
            var number = document.createElement('span');
            var questionText = document.createElement('span');

            fieldset.className = 'quiz-question';
            fieldset.dataset.questionId = question.id;
            number.className = 'quiz-question-number';
            number.textContent = text('question')+' '+(index + 1)+' / '+state.quiz.questions.length;
            questionText.textContent = question.text;
            legend.appendChild(number);
            legend.appendChild(questionText);
            fieldset.appendChild(legend);

            question.options.forEach(function (option) {
                var label = document.createElement('label');
                var input = document.createElement('input');
                var copy = document.createElement('span');

                label.className = 'quiz-option';
                input.type = 'checkbox';
                input.name = question.id;
                input.value = option.id;
                copy.textContent = option.id+'. '+option.text;
                label.appendChild(input);
                label.appendChild(copy);
                fieldset.appendChild(label);
            });

            elements.quizQuestions.appendChild(fieldset);
        });
    }

    function showQuiz() {
        if (!state.localized || state.completed.indexOf(0) === -1) {
            showNavigationMessage(text('quizLocked'));
            return;
        }

        elements.video.pause();
        state.stage = 'quiz';
        elements.playerCard.hidden = true;
        elements.quizCard.hidden = false;
        if (!elements.quizQuestions.children.length) {
            renderQuiz();
        }
        renderPlaylist();
        saveProgress(true, false);
        window.scrollTo(0, 0);
    }

    function collectQuizAnswers() {
        var answers = {};
        state.quiz.questions.forEach(function (question) {
            answers[question.id] = Array.prototype.map.call(
                elements.quizForm.querySelectorAll('input[name="'+question.id+'"]:checked'),
                function (input) {
                    return input.value;
                }
            );
        });

        return answers;
    }

    function markQuizQuestions(answers) {
        state.quiz.questions.forEach(function (question) {
            var fieldset = elements.quizQuestions.querySelector('[data-question-id="'+question.id+'"]');
            fieldset.classList.remove('is-correct', 'is-incorrect');
            fieldset.classList.add(QuizEngine.isCorrect(question, answers[question.id]) ? 'is-correct' : 'is-incorrect');
        });
    }

    function recordQuizInteractions(answers) {
        state.quiz.questions.forEach(function (question, index) {
            var selected = Array.isArray(answers[question.id]) ? answers[question.id].slice().sort() : [];
            var expected = question.correct.slice().sort();
            var prefix = 'cmi.interactions.'+index;

            Scorm.setValue(prefix+'.id', question.id);
            Scorm.setValue(prefix+'.type', 'choice');
            Scorm.setValue(prefix+'.correct_responses.0.pattern', expected.join(','));
            Scorm.setValue(prefix+'.student_response', selected.join(','));
            Scorm.setValue(prefix+'.result', QuizEngine.isCorrect(question, selected) ? 'correct' : 'wrong');
        });
    }

    function submitQuiz(event) {
        event.preventDefault();
        var answers = collectQuizAnswers();
        var result = QuizEngine.score(state.quiz.questions, answers);

        elements.quizResult.hidden = false;
        elements.quizResult.classList.remove('is-passed', 'is-failed');
        if (result.answered < result.total) {
            elements.quizResult.classList.add('is-failed');
            elements.quizResult.textContent = text('answerAll', { total: result.total });
            return;
        }

        markQuizQuestions(answers);
        recordQuizInteractions(answers);
        state.quizScore = result.percent;
        Scorm.setValue('cmi.core.score.min', '0');
        Scorm.setValue('cmi.core.score.max', '100');
        Scorm.setValue('cmi.core.score.raw', String(result.percent));

        if (result.percent >= state.course.quizPassThreshold) {
            state.courseCompleted = true;
            elements.quizResult.classList.add('is-passed');
            elements.quizResult.textContent = text('passed', { score: result.percent });
            elements.completionMessage.textContent = text('completed');
            elements.completionMessage.hidden = false;
            Scorm.setValue('cmi.core.lesson_status', 'passed');
            Scorm.setValue('cmi.core.exit', '');
        } else {
            state.courseCompleted = false;
            elements.quizResult.classList.add('is-failed');
            elements.quizResult.textContent = text('failed', {
                score: result.percent,
                threshold: state.course.quizPassThreshold
            });
            Scorm.setValue('cmi.core.lesson_status', 'failed');
            Scorm.setValue('cmi.core.exit', 'suspend');
        }

        renderPlaylist();
        updateProgressDisplay();
        saveProgress(true, false);
        elements.quizResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function compactProgress() {
        return JSON.stringify({
            l: state.language ? state.language.code : '',
            s: state.stage,
            v: state.currentIndex,
            p: Math.max(0, Math.round(state.positions[state.currentIndex] || 0)),
            m: state.maxPercent.map(function (value) {
                return Math.max(0, Math.min(100, Math.round(value || 0)));
            }),
            d: state.completed.slice().sort(function (a, b) {
                return a - b;
            }),
            q: state.quizScore,
            r: state.courseCompleted ? 1 : 0
        });
    }

    function formatSessionTime(milliseconds) {
        var totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;

        return String(hours).padStart(4, '0')+':'+String(minutes).padStart(2, '0')+':'+String(seconds).padStart(2, '0');
    }

    function saveProgress(commit, finish) {
        if (!state.course || state.finished || state.videos.length === 0) {
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
        elements.video.addEventListener('seeking', onSeeking);
        elements.video.addEventListener('seeked', onSeeked);
        elements.video.addEventListener('ended', function () {
            evaluateVideoCompletion(state.currentIndex);
            saveProgress(true, false);
        });
        elements.video.addEventListener('error', function () {
            showError(text('videoError'));
        });
        elements.previousButton.addEventListener('click', function () {
            navigateTo(state.currentIndex - 1);
        });
        elements.nextButton.addEventListener('click', function () {
            if (state.localized) {
                showQuiz();
            } else {
                navigateTo(state.currentIndex + 1);
            }
        });
        elements.quizBackButton.addEventListener('click', showVideo);
        elements.quizForm.addEventListener('submit', submitQuiz);
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

    function startLegacyCourse() {
        state.videos = state.course.videos;
        restoreProgress(resumeData);
        state.localized = false;
        state.language = null;
        elements.languageScreen.hidden = true;
        elements.courseShell.hidden = false;
        applyLanguageCopy();
        loadVideo(state.currentIndex, state.positions[state.currentIndex]);
    }

    function start() {
        initializeElements();
        bindEvents();
        initializeScormSession();

        fetchJson('data/course.json', 'The course data file could not be loaded.').then(validateCourse).then(function (course) {
            state.course = course;
            state.localized = Array.isArray(course.languages) && course.languages.length >= 2;
            if (state.localized) {
                renderLanguageOptions();
            } else {
                startLegacyCourse();
            }
        }).catch(function (error) {
            elements.courseShell.hidden = false;
            showError(error.message || translations.en.loadError);
        });
    }

    start();
}());
