'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '..', '..', 'resources', 'scorm', 'assets', 'js', 'course-policy.js'),
    'utf8'
);

function policy() {
    const window = {};
    vm.runInNewContext(source, { window, Number });
    return window.CoursePolicy;
}

test('learner can seek backward but cannot skip beyond the watched frontier', () => {
    const coursePolicy = policy();

    assert.equal(coursePolicy.allowedSeekTime(35, 60, 1.5), 35);
    assert.equal(coursePolicy.allowedSeekTime(61, 60, 1.5), 61);
    assert.equal(coursePolicy.allowedSeekTime(90, 60, 1.5), 60);
});

test('learner can revisit unlocked steps but cannot jump to a locked step', () => {
    const coursePolicy = policy();

    assert.equal(coursePolicy.canNavigate(0, 1), true);
    assert.equal(coursePolicy.canNavigate(1, 1), true);
    assert.equal(coursePolicy.canNavigate(2, 1), false);
});
