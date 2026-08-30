(function (global) {
    'use strict';

    function normalized(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        return values.map(function (value) {
            return String(value).trim().toUpperCase();
        }).filter(Boolean).sort();
    }

    function isCorrect(question, selectedValues) {
        var expected = normalized(question && question.correct);
        var selected = normalized(selectedValues);

        return expected.length > 0 && expected.length === selected.length && expected.every(function (value, index) {
            return value === selected[index];
        });
    }

    global.QuizEngine = {
        isCorrect: isCorrect,

        score: function (questions, answers) {
            var list = Array.isArray(questions) ? questions : [];
            var answerMap = answers && typeof answers === 'object' ? answers : {};
            var correct = 0;
            var answered = 0;

            list.forEach(function (question) {
                var selected = normalized(answerMap[String(question.id)]);
                if (selected.length > 0) {
                    answered += 1;
                }
                if (isCorrect(question, selected)) {
                    correct += 1;
                }
            });

            return {
                correct: correct,
                answered: answered,
                total: list.length,
                percent: list.length > 0 ? Math.round(correct / list.length * 100) : 0
            };
        }
    };
}(window));
