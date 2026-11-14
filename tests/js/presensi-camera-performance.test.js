const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
function load(context, ...names) {
    for (const name of names) {
        const body = source.match(new RegExp('    (?:async )?function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n    \\}'));
        assert.ok(body, name);
        vm.runInContext(body[0], context);
    }
}

test('the installed face-api reuses the selected detector result and releases temporary tensors', async (t) => {
    const faceapi = require('../../public/vendor/face-api/face-api.min.js');
    const sizes = [];
    let landmarks = 0;
    let recognition = 0;
    t.mock.method(faceapi.nets.tinyFaceDetector, 'locateFaces', async (_, options) => {
        sizes.push(options.inputSize);
        return [new faceapi.FaceDetection(options.inputSize === 320 ? 0.6 : 0.99,
            new faceapi.Rect(0.1, 0.1, 0.8, 0.8), new faceapi.Dimensions(96, 96))];
    });
    t.mock.method(faceapi.nets.faceLandmark68Net, 'detectLandmarks', async (image) => {
        landmarks++;
        const points = Array.from({ length: 68 }, (_, index) => new faceapi.Point(
            (index % 8 + 1) / 10, (Math.floor(index / 8) + 1) / 11));
        return new faceapi.FaceLandmarks68(points, new faceapi.Dimensions(image.shape[1], image.shape[0]));
    });
    t.mock.method(faceapi.nets.faceRecognitionNet, 'computeFaceDescriptor', async () => {
        recognition++;
        return new Float32Array(128);
    });
    const context = vm.createContext({ faceapi, useAccurateFaceDetector: false, accurateFaceDetectorLoad: null });
    load(context, 'detectFaceDescriptors');
    const baseline = faceapi.tf.memory().numTensors;
    const image = faceapi.tf.zeros([96, 96, 3]);
    try {
        const result = await context.detectFaceDescriptors(image);
        assert.deepEqual(sizes, [320, 512]);
        assert.equal(landmarks, 1);
        assert.equal(recognition, 1);
        assert.equal(result[0].detection.score, 0.99);
        assert.equal(result[0].descriptor.length, 128);
    } finally {
        image.dispose();
    }
    assert.equal(faceapi.tf.memory().numTensors, baseline);
});

test('the actual wizard starts without a map dependency and supports capture and retake', () => {
    for (const hasFaceStep of [true, false]) {
        const node = { classList: { toggle() {}, remove() {}, add() {} } };
        const context = vm.createContext({
            wizardStepFace: hasFaceStep ? node : null, wizardStepAttendance: node,
            wizardIndicatorFace: null, wizardIndicatorAttendance: null, attendanceStepHint: null,
            wizardAttendanceContent: node, attendanceActionPanel: node,
            document: { getElementById: () => ({}) }, window: {},
            updateFaceLiveLocation() {}, updateAttendanceButtonState() {}, setSelfieSurfaceMode() {},
        });
        const start = source.indexOf('        const setWizardStep = function(');
        const end = source.indexOf('        if (isLikelyAndroidWebView())', start);
        assert.ok(start >= 0 && end > start);
        vm.runInContext(source.slice(start, end), context);
        for (const step of [2, 1, 2]) { context.window.setAttendanceWizardStep(step); }
        assert.equal(context.window.L, undefined);
    }
});

test('unchanged camera feedback updates progress without rebuilding its labels', () => {
    let labelWrites = 0;
    const nodes = Object.fromEntries(['cameraFrameBadge', 'cameraGuideLabel', 'cameraFrameMessage',
        'cameraProgressText', 'cameraAssistText'].map(id => [id, {
        set textContent(value) { labelWrites++; this.text = value; },
    }]));
    nodes.selfieCameraStage = { dataset: {} };
    nodes.cameraHoldBar = { style: {} };
    const context = vm.createContext({ document: { getElementById: id => nodes[id] }, updateFaceStatus() {} });
    load(context, 'updateLiveFrameFeedback');
    context.updateLiveFrameFeedback('validating', 'Hold still', 0.1);
    const firstWrites = labelWrites;
    context.updateLiveFrameFeedback('validating', 'Hold still', 0.5);
    assert.equal(labelWrites, firstWrites);
    assert.equal(nodes.cameraHoldBar.style.width, '50%');
    context.updateLiveFrameFeedback('red', 'Face lost', 0);
    assert.ok(labelWrites > firstWrites);
    assert.equal(nodes.cameraFrameMessage.text, 'Face lost');
    assert.equal(nodes.cameraHoldBar.style.width, '0%');
});
