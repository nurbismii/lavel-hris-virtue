const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
function load(context, ...names) {
    for (const name of names) {
        const body = source.match(new RegExp('    (?:async )?function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n    \\}'));
        assert.ok(body, name + ' exists in the actual camera workflow');
        vm.runInContext(body[0], context);
    }
}

function cameraContext(overrides = {}) {
    let nextTimer = 0;
    const timers = new Map();
    const video = { readyState: 2, srcObject: null, pause() {}, async play() {} };
    const retry = { disabled: false };
    const context = vm.createContext({
        cameraSessionId: 1, cameraDetectionTimerId: null, cameraIsProcessing: false,
        cameraVerificationLocked: false, cameraValidationStartedAt: null,
        cameraIsStarting: false, cameraStream: null, liveDetectionIntervalMs: 320,
        faceReferencePath: '/private-reference', faceApiReady: true,
        cameraPausedText: 'Camera paused',
        document: { hidden: false, getElementById: id => id === 'selfieCamera' ? video : retry },
        window: {
            isSecureContext: true,
            setTimeout: (callback, delay) => { const id = ++nextTimer; timers.set(id, { callback, delay }); return id; },
            clearTimeout: id => timers.delete(id),
        },
        isLikelyAndroidWebView: () => true, isAttendanceChallengeReady: () => true,
        emphasizeManualCameraFallback() {}, updateLiveFrameFeedback() {},
        setSelfieSurfaceMode() {}, drawLiveCameraOverlay() {}, resetFaceVerificationState() {},
        resetPassiveCapture() {}, syncLiveCameraOverlaySize: () => ({ width: 320, height: 400 }),
        ...overrides,
    });
    return { context, timers, video, retry };
}

function nextTick(timers) {
    const [id, timer] = timers.entries().next().value;
    timers.delete(id);
    return timer.callback();
}

test('slow inference finishes before a full rendering pause and the next analysis', async () => {
    let finish;
    let calls = 0;
    const { context, timers } = cameraContext({ evaluateLiveCameraFrame: () => { calls++; return new Promise(resolve => { finish = resolve; }); } });
    load(context, 'beginLiveCameraVerification', 'stopLiveCamera');
    context.beginLiveCameraVerification();
    const running = nextTick(timers);
    assert.equal(calls, 1);
    assert.equal(timers.size, 0);
    finish();
    await running;
    assert.equal(calls, 1);
    assert.equal(timers.size, 1);
    assert.equal(timers.values().next().value.delay, 320);
    context.stopLiveCamera();
    assert.equal(timers.size, 0);
});

test('restart waits for old inference to settle and ignores its error feedback', async () => {
    let rejectOld;
    let calls = 0;
    let errors = 0;
    const { context, timers } = cameraContext({
        evaluateLiveCameraFrame: () => { calls++; return calls === 1 ? new Promise((_, reject) => { rejectOld = reject; }) : Promise.resolve(); },
        updateLiveFrameFeedback: () => errors++,
    });
    load(context, 'beginLiveCameraVerification', 'stopLiveCamera');
    context.beginLiveCameraVerification();
    const oldRun = nextTick(timers);
    context.stopLiveCamera();
    context.beginLiveCameraVerification();
    await nextTick(timers);
    assert.equal(calls, 1);
    rejectOld(new Error('stale session'));
    await oldRun;
    assert.equal(errors, 0);
    await nextTick(timers);
    assert.equal(calls, 2);
    assert.equal(timers.size, 1);
});

test('an old face detection cannot mutate or capture in a restarted camera session', async () => {
    let finishDetection;
    let rendered = 0;
    const { context } = cameraContext({
        ensureReferenceFaceDescriptor: async () => [],
        detectFaceDescriptors: () => new Promise(resolve => { finishDetection = resolve; }),
        faceapi: { resizeResults: () => { rendered++; throw new Error('Stale results must be discarded'); } },
    });
    load(context, 'evaluateLiveCameraFrame', 'stopLiveCamera');
    const running = context.evaluateLiveCameraFrame();
    await new Promise(setImmediate);
    context.stopLiveCamera();
    finishDetection([{}]);
    await running;
    assert.equal(rendered, 0);
});

test('camera startup is single-flight and a late stream is stopped after leaving', async () => {
    let finishAccess;
    let requests = 0;
    let stopped = 0;
    let constraints;
    const { context, video, retry } = cameraContext({ navigator: { mediaDevices: {
        getUserMedia: options => { requests++; constraints = options; return new Promise(resolve => { finishAccess = resolve; }); },
    } } });
    load(context, 'startLiveCamera', 'stopLiveCamera');
    const starting = context.startLiveCamera();
    assert.equal(retry.disabled, true);
    await context.startLiveCamera();
    assert.equal(requests, 1);
    assert.equal(constraints.video.frameRate.ideal, 15);
    assert.equal(constraints.video.frameRate.max, undefined);
    assert.equal(constraints.video.width.ideal, 720);
    context.stopLiveCamera();
    finishAccess({ getTracks: () => [{ stop: () => stopped++ }] });
    await starting;
    assert.equal(stopped, 1);
    assert.equal(video.srcObject, null);
    assert.equal(retry.disabled, false);
});

test('a successful camera startup begins one passive capture loop', async () => {
    let loops = 0;
    const stream = { getTracks: () => [] };
    const { context, video, retry } = cameraContext({
        navigator: { mediaDevices: { getUserMedia: async () => stream } },
        isPassiveLiveness: true, passiveCaptureText: 'Hold still',
        beginLiveCameraVerification: () => loops++,
    });
    load(context, 'startLiveCamera', 'stopLiveCamera');
    await context.startLiveCamera();
    assert.equal(loops, 1);
    assert.equal(video.srcObject, stream);
    assert.equal(retry.disabled, false);
    assert.equal(context.cameraIsStarting, false);
});

test('stopping during video playback startup cannot restart analysis afterward', async () => {
    let finishPlay;
    let stopped = 0;
    let loops = 0;
    const { context, video } = cameraContext({
        navigator: { mediaDevices: { getUserMedia: async () => ({ getTracks: () => [{ stop: () => stopped++ }] }) } },
        beginLiveCameraVerification: () => loops++,
    });
    video.play = () => new Promise(resolve => { finishPlay = resolve; });
    load(context, 'startLiveCamera', 'stopLiveCamera');
    const starting = context.startLiveCamera();
    await new Promise(setImmediate);
    context.stopLiveCamera();
    finishPlay();
    await starting;
    assert.equal(stopped, 1);
    assert.equal(loops, 0);
    assert.equal(video.srcObject, null);
});

test('a selfie encoded after the camera session ends cannot publish stale evidence', async () => {
    let finishBlob;
    let writes = 0;
    const input = { value: '' };
    const video = { videoWidth: 720, videoHeight: 960 };
    const canvas = { getContext: () => ({ drawImage() {} }), toBlob: callback => { finishBlob = callback; } };
    const { context } = cameraContext({
        document: { getElementById: id => id === 'selfieCamera' ? video : input, createElement: () => canvas },
        hasRequiredLivenessEvidence: () => true, selfieMaxCaptureWidth: 720, selfieJpegQuality: 0.82,
        analyzeScreenSpoofFromCanvas: () => ({ score: 0 }),
        fillFaceVerificationInputs: () => writes++,
    });
    load(context, 'captureLiveSelfie');
    const capturing = context.captureLiveSelfie({});
    context.cameraSessionId++;
    finishBlob({});
    await capturing;
    assert.equal(writes, 0);
});

test('missing map library produces readable fallback instead of aborting page initialization', () => {
    const mainMap = {};
    const faceMap = {};
    const { context } = cameraContext({
        document: { getElementById: id => id === 'map' ? mainMap : faceMap },
        mapUnavailableText: 'GPS still works',
    });
    load(context, 'initMap', 'initFaceLiveMap');
    context.initMap([{ lat: -4, long: 122 }]);
    context.initFaceLiveMap([{ lat: -4, long: 122 }]);
    assert.equal(mainMap.textContent, 'GPS still works');
    assert.equal(faceMap.textContent, 'GPS still works');
});

test('pausing a live camera stops its tracks and invalidates capture readiness', () => {
    let stopped = 0;
    let resets = 0;
    const { context } = cameraContext({
        cameraStream: { getTracks: () => [{ stop: () => stopped++ }] },
        resetFaceVerificationState: () => resets++,
    });
    load(context, 'pauseLiveCamera', 'stopLiveCamera');
    context.pauseLiveCamera();
    assert.equal(stopped, 1);
    assert.equal(resets, 1);
    context.pauseLiveCamera();
    assert.equal(resets, 1);
});

test('map startup uses complete local Leaflet assets with the existing version integrity', () => {
    const crypto = require('node:crypto');
    const path = require('node:path');
    assert.ok(source.includes("versioned_asset('vendor/leaflet/leaflet.js')"));
    assert.ok(source.includes("versioned_asset('vendor/leaflet/leaflet.css')"));
    assert.equal(source.includes('unpkg.com/leaflet'), false);
    for (const [name, hash] of [
        ['leaflet.js', '20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo='],
        ['leaflet.css', 'p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='],
    ]) {
        assert.equal(crypto.createHash('sha256').update(fs.readFileSync('public/vendor/leaflet/' + name)).digest('base64'), hash);
    }
    const css = fs.readFileSync('public/vendor/leaflet/leaflet.css', 'utf8');
    for (const match of css.matchAll(/url\(["']?(images\/[^)"']+)/g)) {
        assert.ok(fs.existsSync(path.join('public/vendor/leaflet', match[1])), match[1]);
    }
});

test('placeholder and live surfaces expose their mode without hiding the active camera', () => {
    const stage = { dataset: {} };
    const nodes = Object.fromEntries(['selfieCamera', 'selfieOverlay', 'selfiePreview', 'selfiePlaceholder'].map(id => {
        const node = { hidden: false };
        node.classList = { toggle: (_, value) => { node.hidden = value; } };
        return [id, node];
    }));
    const { context } = cameraContext({
        blinkInterval: null,
        document: { getElementById: id => id === 'selfieCameraStage' ? stage : nodes[id] },
    });
    load(context, 'setSelfieSurfaceMode');
    context.setSelfieSurfaceMode('placeholder');
    assert.equal(stage.dataset.cameraMode, 'placeholder');
    assert.equal(nodes.selfiePlaceholder.hidden, false);
    assert.equal(nodes.selfieCamera.hidden, true);
    context.setSelfieSurfaceMode('live');
    assert.equal(stage.dataset.cameraMode, 'live');
    assert.equal(nodes.selfiePlaceholder.hidden, true);
    assert.equal(nodes.selfieCamera.hidden, false);
});
