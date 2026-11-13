const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const PassiveFaceCapture = require('../../public/assets/js/passive-face-capture.js');
const { BlinkLivenessChallenge } = require('../../public/assets/js/passive-face-capture.js');

const policy = { required_count: 2, min_open_ear: 0.12, closed_ratio: 0.6, reopen_ratio: 0.85, min_closed_ms: 60, max_closed_ms: 2000 };
function calibrate(challenge, start = 0) {
    for (const offset of [0, 320, 640]) { challenge.observe(0.3, 0.28, start + offset); }
}
function blink(challenge, start) {
    challenge.observe(0.12, 0.1, start);
    challenge.observe(0.29, 0.27, start + 320);
    return challenge.observe(0.3, 0.28, start + 640);
}

test('a static photo with small eye-landmark jitter never completes the blink challenge', () => {
    const challenge = new BlinkLivenessChallenge(policy);
    for (let index = 0; index < 60; index++) {
        const result = challenge.observe(0.3 + (index % 3) * 0.005, 0.28 - (index % 2) * 0.004, index * 320);
        assert.equal(result.passed, false);
    }
    assert.equal(challenge.summary().completed_count, 0);
});

test('the requested number of both-eye close and reopen cycles is required', () => {
    const challenge = new BlinkLivenessChallenge(policy);
    calibrate(challenge);
    assert.equal(blink(challenge, 1000).passed, false);
    assert.equal(blink(challenge, 2000).passed, true);
    const summary = challenge.summary();
    assert.equal(summary.completed_count, 2);
    assert.equal(summary.events.length, 2);
    assert.equal(summary.events[0].closed_at_ms, 1000);
    assert.equal(summary.events[0].opened_at_ms, 1320);
});

test('one-eye closure and a closed-eye photograph are insufficient', () => {
    for (const eyes of [[0.1, 0.28], [0.03, 0.03]]) {
        const challenge = new BlinkLivenessChallenge({ ...policy, required_count: 1 });
        calibrate(challenge);
        for (let index = 0; index < 20; index++) {
            assert.equal(challenge.observe(...eyes, 1000 + index * 320).passed, false);
        }
    }
});

test('too-short closure, an interrupted camera, and an explicit reset discard incomplete proof', () => {
    const challenge = new BlinkLivenessChallenge({ ...policy, required_count: 1 });
    calibrate(challenge);
    challenge.observe(0.1, 0.1, 1000);
    challenge.observe(0.3, 0.28, 1001);
    assert.equal(challenge.observe(0.3, 0.28, 1320).passed, false);
    challenge.observe(0.1, 0.1, 1640);
    assert.equal(challenge.observe(0.3, 0.28, 5000).passed, false);
    assert.equal(challenge.summary().completed_count, 0);
    calibrate(challenge, 6000);
    assert.equal(blink(challenge, 7000).passed, true);
    challenge.reset();
    assert.equal(challenge.summary().passed, false);
});

test('the actual camera workflow cannot capture a static photo and captures only after the blink and frontal evidence', async () => {
    const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
    const activePolicy = { ...policy, required_count: 1 };
    let now = 0;
    let ear = [0.3, 0.28];
    let captured = 0;
    const input = {};
    const face = { detection: { score: 0.99, box: { x: 100, y: 80, width: 120, height: 200 } }, descriptor: [],
        landmarks: { getLeftEye: () => ({ ear: ear[0] }), getRightEye: () => ({ ear: ear[1] }) } };
    const context = vm.createContext({
        cameraSessionId: 1, cameraVerificationLocked: false, faceApiReady: true, isPassiveLiveness: true,
        clientBlinkPolicy: activePolicy, blinkChallenge: new BlinkLivenessChallenge(activePolicy), passiveCapture: new PassiveFaceCapture(2000),
        faceDistanceThreshold: 0.5, TURN_CENTER_MAX_YAW: 0.035, passiveCaptureText: 'Hold still', passiveReadyText: 'Evidence ready',
        livenessRequiredFrames: ['passive_start', 'passive_middle', 'passive_end'],
        Date: { now: () => now }, attendanceChallengeElapsedMs: () => now + 1,
        document: { getElementById: id => id === 'selfieCamera' ? { readyState: 2 } : input },
        syncLiveCameraOverlaySize: () => ({ width: 320, height: 400 }),
        ensureReferenceFaceDescriptor: async () => [], detectFaceDescriptors: async () => [face],
        faceapi: { resizeResults: items => items, euclideanDistance: () => 0.3 },
        getLiveGuideRect: () => ({ x: 0, y: 0, width: 320, height: 400 }), getFaceRollAngle: () => 0,
        calculateHeadTurnMetric: () => ({ absYaw: 0 }), calculateEAR: eye => eye.ear,
        resetLivenessEvidence() {}, setBlinkResult() {}, updateLiveFrameFeedback() {}, fillFaceVerificationInputs() {},
        updateAttendanceButtonState() {}, drawLiveCameraOverlay() {}, captureLivenessEvidenceFrame() {},
        captureSelfieFromTurn: async () => { captured++; context.cameraVerificationLocked = true; },
    });
    for (const name of ['resetFaceMatchedReady', 'resetPassiveCapture', 'evaluateLiveCameraFrame']) {
        const body = source.match(new RegExp('    (?:async )?function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n    \\}'));
        vm.runInContext(body[0], context);
    }
    for (now = 0; now <= 6080; now += 320) { await context.evaluateLiveCameraFrame(); }
    assert.equal(captured, 0);
    assert.equal(context.faceMatchedReady, false);
    ear = [0.1, 0.1]; now = 6400; await context.evaluateLiveCameraFrame();
    ear = [0.3, 0.28];
    for (now of [6720, 7040, 8040]) { await context.evaluateLiveCameraFrame(); }
    assert.equal(captured, 0);
    now = 9040; await context.evaluateLiveCameraFrame();
    assert.equal(captured, 1);
    assert.equal(context.blinkChallenge.summary().passed, true);
});
