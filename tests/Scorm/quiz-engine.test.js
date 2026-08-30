'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '..', '..', 'resources', 'scorm', 'assets', 'js', 'quiz-engine.js'),
    'utf8'
);

function engine() {
    const window = {};
    vm.runInNewContext(source, { window, Array, String, Math });
    return window.QuizEngine;
}

test('multiple-answer question requires the exact set of correct answers', () => {
    const quizEngine = engine();
    const question = { correct: ['A', 'C'] };

    assert.equal(quizEngine.isCorrect(question, ['C', 'A']), true);
    assert.equal(quizEngine.isCorrect(question, ['A']), false);
    assert.equal(quizEngine.isCorrect(question, ['A', 'B', 'C']), false);
});

test('quiz score reports answered, correct and percentage values', () => {
    const quizEngine = engine();
    const questions = [
        { id: 'q1', correct: ['A'] },
        { id: 'q2', correct: ['B', 'D'] },
        { id: 'q3', correct: ['C'] }
    ];
    const result = quizEngine.score(questions, {
        q1: ['A'],
        q2: ['D', 'B'],
        q3: []
    });

    assert.equal(result.correct, 2);
    assert.equal(result.answered, 2);
    assert.equal(result.total, 3);
    assert.equal(result.percent, 67);
});
