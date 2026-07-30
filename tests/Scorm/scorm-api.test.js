'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const wrapperSource = fs.readFileSync(
    path.join(__dirname, '..', '..', 'resources', 'scorm', 'assets', 'js', 'scorm-api.js'),
    'utf8'
);

test('SCORM wrapper forwards both LMSSetValue arguments', () => {
    const values = {};
    const window = {};
    window.parent = window;
    window.API = {
        LMSInitialize: () => 'true',
        LMSGetValue: (key) => values[key] || '',
        LMSSetValue: (key, value) => {
            values[key] = value;
            return 'true';
        },
        LMSCommit: () => 'true',
        LMSFinish: () => 'true',
        LMSGetLastError: () => '0'
    };

    vm.runInNewContext(wrapperSource, { window });

    assert.equal(window.Scorm.initialize(), true);
    assert.equal(window.Scorm.setValue('cmi.core.lesson_status', 'incomplete'), true);
    assert.equal(values['cmi.core.lesson_status'], 'incomplete');
    assert.equal(window.Scorm.getValue('cmi.core.lesson_status'), 'incomplete');
    assert.equal(window.Scorm.commit(), true);
    assert.equal(window.Scorm.finish(), true);
});

test('SCORM wrapper remains usable when the LMS API is absent', () => {
    const window = {};
    window.parent = window;

    vm.runInNewContext(wrapperSource, { window });

    assert.equal(window.Scorm.initialize(), false);
    assert.equal(window.Scorm.getValue('cmi.core.lesson_status'), '');
    assert.equal(window.Scorm.setValue('cmi.core.lesson_status', 'incomplete'), false);
    assert.equal(window.Scorm.getLastError(), '0');
});
