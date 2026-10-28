const { test } = require('node:test');
const assert = require('node:assert/strict');
const PassiveFaceCapture = require('../../public/assets/js/passive-face-capture.js');
const fs = require('node:fs');
const vm = require('node:vm');

test('frontal capture collects three spaced frames without a head-turn challenge', () => {
    const capture = new PassiveFaceCapture(2000);
    assert.equal(capture.observe(true, 0).frameIndex, 0);
    assert.equal(capture.observe(true, 500).frameIndex, null);
    assert.equal(capture.observe(true, 1000).frameIndex, 1);
    assert.equal(capture.observe(true, 1500).complete, false);
    assert.deepEqual(capture.observe(true, 2000), { frameIndex: 2, progress: 1, complete: true });
    assert.equal(capture.observe(true, 2100).frameIndex, null);
});

test('face loss and a paused camera discard stale capture progress', () => {
    for (const reason of ['face-loss', 'camera-pause']) {
        const capture = new PassiveFaceCapture(2000);
        capture.observe(true, 0);
        capture.observe(true, 1000);
        if (reason === 'face-loss') { capture.observe(false, 1200); }
        const restartAt = reason === 'face-loss' ? 1400 : 4000;
        assert.equal(capture.observe(true, restartAt).frameIndex, 0);
        assert.equal(capture.observe(true, restartAt + 1000).complete, false);
        assert.equal(capture.observe(true, restartAt + 2000).complete, true);
    }
});

test('late sampling cannot capture two evidence frames at the same instant', () => {
    const capture = new PassiveFaceCapture(2000);
    capture.observe(true, 0);
    assert.equal(capture.observe(true, 1800).frameIndex, 1);
    assert.equal(capture.observe(true, 2000).complete, false);
    assert.equal(capture.observe(true, 2300).complete, true);
});

test('the camera workflow captures frontal evidence once and resets when the face disappears', async () => {
    const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
    const evaluate = source.match(/    async function evaluateLiveCameraFrame\(\) \{[\s\S]*?\n    \}/)[0];
    const frames = [];
    let now = 0;
    let visible = true;
    let selfies = 0;
    const detection = { detection: { score: 0.99, box: { x: 100, y: 80, width: 120, height: 200 } }, descriptor: [], landmarks: {} };
    const context = vm.createContext({
        document: { getElementById: (id) => id === 'selfieCamera' ? { readyState: 2 } : {} },
        cameraVerificationLocked: false, cameraValidationStartedAt: null, faceVerificationPassed: false,
        faceApiReady: true, faceMatchedReady: false, latestFaceMatchCapturePayload: null,
        isPassiveLiveness: true, passiveCapture: new PassiveFaceCapture(2000),
        livenessRequiredFrames: ['passive_start', 'passive_middle', 'passive_end'],
        passiveCaptureText: 'Capture evidence', passiveReadyText: 'Ready for server verification',
        TURN_CENTER_MAX_YAW: 0.05, faceDistanceThreshold: 0.5,
        Date: { now: () => now },
        syncLiveCameraOverlaySize: () => ({ width: 320, height: 400 }),
        ensureReferenceFaceDescriptor: async () => [],
        detectFaceDescriptors: async () => visible ? [detection] : [],
        faceapi: { resizeResults: (items) => items, euclideanDistance: () => 0.3 },
        getLiveGuideRect: () => ({ x: 0, y: 0, width: 320, height: 400 }),
        getFaceRollAngle: () => 0, calculateHeadTurnMetric: () => ({ absYaw: 0 }),
        drawLiveCameraOverlay() {}, updateLiveFrameFeedback() {}, updateAttendanceButtonState() {}, fillFaceVerificationInputs() {},
        resetLivenessEvidence: () => { frames.length = 0; },
        captureLivenessEvidenceFrame: (label) => { frames.push(label); },
        resetPassiveCapture: () => { context.passiveCapture.reset(); frames.length = 0; context.faceMatchedReady = false; },
        captureSelfieFromTurn: async (score) => { assert.equal(score, null); selfies++; context.cameraVerificationLocked = true; },
    });
    vm.runInContext(evaluate, context);
    await context.evaluateLiveCameraFrame();
    now = 1000;
    await context.evaluateLiveCameraFrame();
    visible = false;
    now = 1200;
    await context.evaluateLiveCameraFrame();
    assert.equal(frames.length, 0);
    assert.equal(context.faceMatchedReady, false);
    visible = true;
    for (now of [1400, 2400, 3400, 3500]) { await context.evaluateLiveCameraFrame(); }
    assert.deepEqual(frames, ['passive_start', 'passive_middle', 'passive_end']);
    assert.equal(selfies, 1);
});
