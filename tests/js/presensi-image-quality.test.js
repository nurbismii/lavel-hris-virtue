const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const PassiveFaceCapture = require('../../public/assets/js/passive-face-capture.js');
const { analyzeFaceImageQuality } = PassiveFaceCapture;
const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
const limits = { min_blur_score: 35, min_brightness: 35, max_brightness: 225 };
const messages = { dark: 'Too dark', bright: 'Too bright', blur: 'Hold still; try cleaning the lens', reference: 'Ask HR to update the reference', unavailable: 'Quality unavailable' };

function load(context, ...names) {
    for (const name of names) {
        const body = source.match(new RegExp('    (?:async )?function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n    \\}'));
        assert.ok(body, name);
        vm.runInContext(body[0], context);
    }
}

function pixels(value, width = 8, height = 6) {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const gray = typeof value === 'function' ? value(x, y) : value;
            data.set([gray, gray, gray, 255], (y * width + x) * 4);
        }
    }
    return { width, height, data };
}

test('browser quality measurements match OpenCV on RGB and decoded JPEG samples', () => {
    const fixture = JSON.parse(fs.readFileSync('tests/Fixtures/presensi-image-quality-opencv.json', 'utf8'));
    for (const sample of fixture.samples) {
        const result = analyzeFaceImageQuality({ ...sample, data: Buffer.from(sample.rgba_base64, 'base64') }, limits);
        assert.ok(Math.abs(result.brightness - sample.brightness) < 1e-8, sample.name + ' brightness');
        assert.ok(Math.abs(result.sharpness - sample.sharpness) < 1e-6, sample.name + ' sharpness');
    }
});

test('blur, darkness and overexposure are distinct and do not claim a dirty lens was detected', () => {
    assert.deepEqual(analyzeFaceImageQuality(pixels(128), limits).issues, ['blur']);
    assert.deepEqual(analyzeFaceImageQuality(pixels((x, y) => (x + y) % 2 ? 18 : 10), limits).issues, ['dark']);
    assert.deepEqual(analyzeFaceImageQuality(pixels((x, y) => (x + y) % 2 ? 248 : 240), limits).issues, ['bright']);
    assert.deepEqual(analyzeFaceImageQuality(pixels(0), limits).issues, ['dark', 'blur']);
    assert.equal(analyzeFaceImageQuality(pixels((x, y) => (x + y) % 2 ? 136 : 120), limits).passed, true);
});

test('quality thresholds retain inclusive boundaries and malformed inputs cannot pass', () => {
    for (const mean of [35, 225]) {
        assert.equal(analyzeFaceImageQuality(pixels((x, y) => mean + ((x + y) % 2 ? 1 : -1)), limits).passed, true);
    }
    const input = pixels((x, y) => (x + y) % 2 ? 136 : 120);
    const exact = analyzeFaceImageQuality(input, limits).sharpness;
    assert.equal(analyzeFaceImageQuality(input, { ...limits, min_blur_score: exact }).passed, true);
    assert.equal(analyzeFaceImageQuality(input, { ...limits, min_blur_score: exact + 0.001 }).passed, false);
    assert.throws(() => analyzeFaceImageQuality({ width: 0, height: 0, data: [] }, limits));
    assert.throws(() => analyzeFaceImageQuality(input, { ...limits, min_blur_score: NaN }));
    assert.throws(() => analyzeFaceImageQuality(input, { ...limits, min_brightness: 225 }));
});

test('quality feedback identifies lighting and reference problems and sanitizes read failures', () => {
    const context = vm.createContext({ analyzeFaceImageQuality, faceImageQualityLimits: limits, faceQualityMessages: messages });
    load(context, 'validateFaceImageQuality');
    const canvas = value => ({ width: 8, height: 6, getContext: () => ({ getImageData: () => pixels(value) }) });
    assert.throws(() => context.validateFaceImageQuality(canvas(0)), error => error.captureQuality && error.message === messages.dark);
    assert.throws(() => context.validateFaceImageQuality(canvas(128)), error => error.captureQuality && error.message === messages.blur);
    assert.throws(() => context.validateFaceImageQuality(canvas(0), true), error => error.referenceQuality && error.message === messages.reference);
    assert.throws(() => context.validateFaceImageQuality({ getContext() { throw new Error('private-image-data'); } }), error => error.captureQuality && error.message === messages.unavailable);
});

test('bad preview quality clears capture progress before face inference and allows another attempt', async () => {
    let next;
    let detections = 0;
    let state;
    const frames = ['old-frame'];
    const qualityError = Object.assign(new Error(messages.dark), { captureQuality: true });
    const context = vm.createContext({
        cameraSessionId: 1, cameraDetectionTimerId: null, cameraVerificationLocked: false, cameraIsProcessing: false,
        faceApiReady: true, liveDetectionIntervalMs: 320, isPassiveLiveness: true, blinkChallenge: null,
        passiveCapture: new PassiveFaceCapture(2000), passiveCaptureText: 'Hold still',
        faceMatchedReady: true, latestFaceMatchCapturePayload: {},
        document: { getElementById: () => ({ readyState: 2 }) },
        window: { clearTimeout() {}, setTimeout: callback => { next = callback; return 1; } },
        syncLiveCameraOverlaySize: () => ({ width: 320, height: 400 }), ensureReferenceFaceDescriptor: async () => [],
        validateFaceImageQuality: () => { throw qualityError; },
        detectFaceDescriptors: async () => { detections++; return []; }, faceapi: { resizeResults: items => items },
        resetLivenessEvidence: () => { frames.length = 0; }, setBlinkResult() {}, updateAttendanceButtonState() {},
        drawLiveCameraOverlay() {}, updateLiveFrameFeedback: value => { state = value; },
    });
    load(context, 'resetFaceMatchedReady', 'resetPassiveCapture', 'evaluateLiveCameraFrame', 'beginLiveCameraVerification');
    context.beginLiveCameraVerification();
    await next();
    assert.equal(detections, 0);
    assert.equal(state, 'yellow');
    assert.equal(context.faceMatchedReady, false);
    assert.equal(frames.length, 0);
    context.validateFaceImageQuality = () => {};
    await next();
    assert.equal(detections, 1);
});

test('a bad reference stops the camera instead of repeatedly analysing a photo that needs HR correction', async () => {
    let next;
    let stops = 0;
    let state;
    const context = vm.createContext({
        cameraSessionId: 1, cameraDetectionTimerId: null, cameraIsProcessing: false, cameraVerificationLocked: false,
        liveDetectionIntervalMs: 320, isPassiveLiveness: true, blinkChallenge: null,
        window: { clearTimeout() {}, setTimeout: callback => { next = callback; return 1; } },
        evaluateLiveCameraFrame: async () => { throw Object.assign(new Error(messages.reference), { captureQuality: true, referenceQuality: true }); },
        resetPassiveCapture() {}, drawLiveCameraOverlay() {},
        stopLiveCamera: () => { stops++; context.cameraSessionId++; }, updateLiveFrameFeedback: value => { state = value; },
    });
    load(context, 'beginLiveCameraVerification');
    context.beginLiveCameraVerification();
    const first = next;
    next = null;
    await first();
    assert.equal(stops, 1);
    assert.equal(next, null);
    assert.equal(state, 'red');
});

test('encoded evidence is not published on quality failure or after a camera restart', async () => {
    for (const mode of ['bad-quality', 'restart', 'valid']) {
        let finish;
        let writes = 0;
        const context = vm.createContext({
            cameraSessionId: 1, livenessStartedAtMs: 1000, lastLivenessCapturedAtMs: 0,
            livenessEvidenceMaxWidth: 480, livenessEvidenceJpegQuality: 0.76, livenessEvidence: { frames: [] },
            window: { performance: {} }, performance: { now: () => 2000 },
            document: { createElement: () => ({ getContext: () => ({ drawImage() {} }), toDataURL: () => 'synthetic-jpeg' }) },
            validateEncodedFaceImageQuality: () => mode === 'bad-quality'
                ? Promise.reject(Object.assign(new Error(messages.blur), { captureQuality: true }))
                : new Promise(resolve => { finish = resolve; }),
            syncLivenessEvidenceInput: () => writes++,
        });
        load(context, 'captureLivenessEvidenceFrame');
        const capture = context.captureLivenessEvidenceFrame('passive_start', { videoWidth: 720, videoHeight: 960 });
        if (mode === 'bad-quality') {
            await assert.rejects(capture, /Hold still/);
        } else {
            if (mode === 'restart') { context.cameraSessionId++; }
            finish();
            await capture;
        }
        assert.equal(context.livenessEvidence.frames.length, mode === 'valid' ? 1 : 0);
        assert.equal(writes, mode === 'valid' ? 1 : 0);
    }
});

test('a failed JPEG check or a camera restart during decoding cannot publish a ready selfie', async () => {
    for (const mode of ['bad-quality', 'restart']) {
        const input = { value: '' };
        const decoded = {};
        let published = 0;
        const context = vm.createContext({
            cameraSessionId: 1, cameraVerificationLocked: false, clientBlinkPolicy: null, blinkChallenge: null,
            selfieMaxCaptureWidth: 720, selfieJpegQuality: 0.82, faceDistanceThreshold: 0.5,
            document: { getElementById: id => id === 'selfieCamera' ? { videoWidth: 720, videoHeight: 960 } : id === 'selfie_capture_data' ? input : { value: '' },
                createElement: () => ({ getContext: () => ({ drawImage() {} }), toBlob: callback => callback({}) }) },
            validateFaceImageQuality: image => { if (image === decoded) { throw Object.assign(new Error(messages.blur), { captureQuality: true }); } },
            createImageFromFile: async () => { if (mode === 'restart') { context.cameraSessionId++; } return decoded; }, hasRequiredLivenessEvidence: () => true,
            detectFaceDescriptors: async () => [{ descriptor: [], detection: { score: 0.99 }, landmarks: {} }],
            ensureReferenceFaceDescriptor: async () => [], faceapi: { euclideanDistance: () => 0.3 }, getFaceRollAngle: () => 0,
            analyzeScreenSpoofFromCanvas: () => ({ score: 0 }), fillFaceVerificationInputs: () => published++,
        });
        load(context, 'captureLiveSelfie');
        if (mode === 'bad-quality') { await assert.rejects(context.captureLiveSelfie(), error => error.captureQuality); }
        else { await context.captureLiveSelfie(); }
        assert.equal(published, 0);
        assert.equal(input.value, '');
        assert.equal(context.cameraVerificationLocked, false);
    }
});

test('a quality failure during final capture unlocks the camera and resets the capture workflow', async () => {
    for (const passive of [true, false]) {
        let resets = 0;
        let restarts = 0;
        let state;
        const context = vm.createContext({
            cameraSessionId: 1, cameraVerificationLocked: false, livenessCaptureInProgress: false,
            faceMatchedReady: true, latestFaceMatchCapturePayload: {}, blinkInterval: null,
            isPassiveLiveness: passive, passiveReadyText: 'Ready', document: { getElementById: () => ({}) },
            setBlinkResult() {}, updateLiveFrameFeedback: value => { state = value; },
            captureLiveSelfie: async () => { throw Object.assign(new Error(messages.blur), { captureQuality: true }); },
            resetPassiveCapture: () => { resets++; }, startTurnLiveness: () => { restarts++; },
        });
        load(context, 'captureSelfieFromTurn');
        await context.captureSelfieFromTurn(null, 'Ready');
        assert.equal(context.cameraVerificationLocked, false);
        assert.equal(context.livenessCaptureInProgress, false);
        assert.equal(resets, 1);
        assert.equal(restarts, passive ? 0 : 1);
        assert.equal(state, 'yellow');
    }
});
