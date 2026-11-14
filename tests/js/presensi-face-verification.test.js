const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto');

const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');

test('the bundled accurate detector and landmark models contain the complete pinned weights', () => {
    const root = 'public/vendor/face-api/weights/';
    const hashes = {
        'ssd_mobilenetv1_model-shard1': '6ff16f095b0371f24accb48aab82e595697baa821cf851167cbf9203d1d7ec9a',
        'ssd_mobilenetv1_model-shard2': '2539847e627c8c4a561e999170735e6c527deecc6fdba2959fc5312fb1ea1de3',
        'face_landmark_68_model-shard1': '4611ef65c87d836d03d684b30eec4d195d8b219fa1dd58fc58945831c6b9299b',
    };
    for (const model of ['ssd_mobilenetv1_model', 'face_landmark_68_model']) {
        const manifest = JSON.parse(fs.readFileSync(root + model + '-weights_manifest.json', 'utf8'));
        for (const group of manifest) {
            for (const file of group.paths) {
                assert.equal(crypto.createHash('sha256').update(fs.readFileSync(root + file)).digest('hex'), hashes[file], file);
            }
        }
    }
    assert.match(source, /loadFaceModel\(faceapi\.nets\.faceLandmark68Net,/);
    assert.match(fs.readFileSync('public/vendor/face-api/LICENSE', 'utf8'), /MIT License/);
});
function load(context, ...names) {
    for (const name of names) {
        const body = source.match(new RegExp('    (?:async )?function ' + name + '\\([^)]*\\) \\{[\\s\\S]*?\\n    \\}'));
        assert.ok(body, name);
        vm.runInContext(body[0], context);
    }
}

function detectorApi(detect, work = { landmarks: 0, recognition: 0 }) {
    return {
        TinyFaceDetectorOptions: function (options) { return { kind: 'tiny', ...options }; },
        SsdMobilenetv1Options: function (options) { return { kind: 'ssd', ...options }; },
        nets: { ssdMobilenetv1: {} },
        detectAllFaces: (image, options) => ({ runAndExtendWithFaceDetections: async () => detect(image, options) }),
        DetectAllFaceLandmarksTask: function (parent, image, useTiny) {
            assert.equal(useTiny, false);
            work.landmarks++;
            const task = Promise.resolve(parent);
            task.withFaceDescriptors = async () => { work.recognition++; return task; };
            return task;
        },
    };
}

function landmarks(yaw = 0, angle = 0, jawWidth = 120) {
    const rotate = ([x, y]) => ({ x: 100 + x * Math.cos(angle) - y * Math.sin(angle), y: 100 + x * Math.sin(angle) + y * Math.cos(angle) });
    return {
        getLeftEye: () => [rotate([-30, 0])],
        getRightEye: () => [rotate([30, 0])],
        getNose: () => Array(4).fill(rotate([yaw * 120, 45])),
        getJawOutline: () => Array.from({ length: 17 }, (_, index) => rotate([(index / 16 - 0.5) * jawWidth, 80])),
    };
}

test('head pose is unchanged by a slightly tilted camera or an unreliable jaw outline', () => {
    const context = vm.createContext({});
    load(context, 'distance', 'averagePoint', 'calculateHeadTurnMetric');
    for (const yaw of [0, -0.08, 0.08]) {
        for (const angle of [0, -0.2, 0.2]) {
            for (const jawWidth of [120, 300]) {
                const result = context.calculateHeadTurnMetric(landmarks(yaw, angle, jawWidth));
                assert.ok(Math.abs(result.yaw - yaw) < 0.0001);
                assert.equal(result.absYaw, Math.abs(yaw));
            }
        }
    }
});

test('invalid eye landmarks cannot be treated as a frontal face', () => {
    const context = vm.createContext({});
    load(context, 'distance', 'averagePoint', 'calculateHeadTurnMetric');
    const face = landmarks();
    face.getRightEye = face.getLeftEye;
    assert.equal(context.calculateHeadTurnMetric(face), null);
});

test('a weak face detection retries at higher resolution without lowering the score gate', async () => {
    const calls = [];
    const work = { landmarks: 0, recognition: 0 };
    const weak = [{ detection: { score: 0.65 } }];
    const clear = [{ detection: { score: 0.94 } }];
    const context = vm.createContext({ useAccurateFaceDetector: false, accurateFaceDetectorLoad: null,
        faceapi: detectorApi((_, options) => {
            calls.push(options);
            return options.inputSize === 320 ? weak : clear;
        }, work),
    });
    load(context, 'detectFaceDescriptors');
    assert.equal(await context.detectFaceDescriptors({}), clear);
    assert.deepEqual(calls.map(options => options.inputSize), [320, 512]);
    assert.ok(calls.every(options => options.scoreThreshold === 0.5));
    assert.deepEqual(work, { landmarks: 1, recognition: 1 });
});

test('a face missed by both tiny resolutions uses the accurate detector and full landmarks', async () => {
    const calls = [];
    let modelLoads = 0;
    const work = { landmarks: 0, recognition: 0 };
    const face = [{ detection: { score: 0.99 }, descriptor: [] }];
    const context = vm.createContext({
        accurateFaceDetectorLoad: null, useAccurateFaceDetector: false, faceModelPath: '/local-models',
        updateLiveFrameFeedback() {},
        loadFaceModel: async (net, label) => { assert.equal(label, 'deteksi tambahan'); modelLoads++; },
        faceapi: detectorApi((_, options) => {
            calls.push(options);
            return options.kind === 'ssd' ? face : [{ detection: { score: 0.6 } }];
        }, work),
    });
    load(context, 'detectFaceDescriptors');
    assert.equal(await context.detectFaceDescriptors({}), face);
    assert.deepEqual(calls.map(options => options.kind), ['tiny', 'tiny', 'ssd']);
    assert.equal(calls[2].minConfidence, 0.5);
    assert.equal(calls[2].maxResults, 2);
    assert.equal(modelLoads, 1);
    assert.deepEqual(work, { landmarks: 1, recognition: 1 });
    calls.length = 0;
    assert.equal(await context.detectFaceDescriptors({}), face);
    assert.deepEqual(calls.map(options => options.kind), ['ssd']);
    assert.equal(modelLoads, 1);
});

test('a missing accurate model gives an actionable error and cannot silently approve a weak face', async () => {
    let modelLoads = 0;
    const context = vm.createContext({
        accurateFaceDetectorLoad: null, useAccurateFaceDetector: false, faceModelPath: '/local-models',
        updateLiveFrameFeedback() {},
        loadFaceModel: async () => { modelLoads++; throw new Error('Model deteksi tambahan gagal dimuat. Aktifkan ulang kamera.'); },
        faceapi: detectorApi(() => [{ detection: { score: 0.6 } }]),
    });
    load(context, 'detectFaceDescriptors');
    for (let attempt = 0; attempt < 2; attempt++) {
        await assert.rejects(context.detectFaceDescriptors({}), /deteksi tambahan.*ulang/i);
    }
    assert.equal(modelLoads, 1);
    assert.equal(context.useAccurateFaceDetector, false);
});

test('good detections and multiple faces do not trigger a retry that could hide another person', async () => {
    for (const detections of [[{ detection: { score: 0.95 } }], [{}, {}]]) {
        let calls = 0;
        const work = { landmarks: 0, recognition: 0 };
        const context = vm.createContext({ useAccurateFaceDetector: false, accurateFaceDetectorLoad: null,
            faceapi: detectorApi(() => {
                calls++;
                return detections;
            }, work),
        });
        load(context, 'detectFaceDescriptors');
        assert.equal(await context.detectFaceDescriptors({}), detections);
        assert.equal(calls, 1);
        assert.equal(work.recognition, detections.length === 1 ? 1 : 0);
        assert.equal(work.landmarks, detections.length === 1 ? 1 : 0);
    }
});

test('blink sampling keeps detection and full landmarks while avoiding repeated identity inference', async () => {
    for (const accurate of [false, true]) {
        const work = { landmarks: 0, recognition: 0 };
        const detections = [{ detection: { score: 0.99 }, landmarks: {} }];
        const context = vm.createContext({ useAccurateFaceDetector: accurate, accurateFaceDetectorLoad: null,
            faceapi: detectorApi(() => detections, work),
        });
        load(context, 'detectFaceDescriptors');
        assert.equal(await context.detectFaceDescriptors({}, false), detections);
        assert.equal(work.recognition, 0);
        assert.equal(await context.detectFaceDescriptors({}), detections);
        assert.equal(work.recognition, 1);
    }
});

test('an unclear reference photo requires HR correction instead of matching against an unreliable descriptor', async () => {
    const context = vm.createContext({
        referenceFaceDescriptor: null,
        document: { getElementById: () => ({ complete: true }) },
        detectFaceDescriptors: async () => [{ detection: { score: 0.6 }, descriptor: ['unreliable'] }],
    });
    load(context, 'ensureReferenceFaceDescriptor');
    await assert.rejects(context.ensureReferenceFaceDescriptor(), /HR/);
    assert.equal(context.referenceFaceDescriptor, null);
});

test('losing face readiness invalidates stale identity metadata in active mode too', () => {
    const context = vm.createContext({
        isPassiveLiveness: false, faceMatchedReady: true, latestFaceMatchCapturePayload: { distance: 0.2 },
    });
    load(context, 'resetFaceMatchedReady', 'resetPassiveCapture');
    context.resetPassiveCapture();
    assert.equal(context.faceMatchedReady, false);
    assert.equal(context.latestFaceMatchCapturePayload, null);
});

test('the saved selfie is checked again and a swapped or additional face cannot use an earlier match', async () => {
    for (const detections of [[], [{}, {}], [{ detection: { score: 0.99 }, descriptor: [], landmarks: {} }]]) {
        let encoded = false;
        const video = { videoWidth: 720, videoHeight: 960 };
        const context = vm.createContext({
            cameraSessionId: 1, selfieMaxCaptureWidth: 720, faceDistanceThreshold: 0.5,
            clientBlinkPolicy: null, blinkChallenge: null,
            document: {
                getElementById: id => id === 'selfieCamera' ? video : {},
                createElement: () => ({ getContext: () => ({ drawImage() {} }), toBlob() { encoded = true; } }),
            },
            hasRequiredLivenessEvidence: () => true,
            detectFaceDescriptors: async () => detections, ensureReferenceFaceDescriptor: async () => [],
            faceapi: { euclideanDistance: () => 0.8 }, getFaceRollAngle: () => 0,
            analyzeScreenSpoofFromCanvas: () => ({ score: 0 }),
        });
        load(context, 'captureLiveSelfie');
        await assert.rejects(context.captureLiveSelfie({ distance: 0.2 }), /wajah|Wajah/);
        assert.equal(encoded, false);
    }
});

test('a matching frozen selfie saves its own metrics and reports that final verification is still on the server', async () => {
    let metadata;
    let message;
    let state;
    const input = {};
    const video = { videoWidth: 720, videoHeight: 960 };
    const context = vm.createContext({
        cameraSessionId: 1, selfieMaxCaptureWidth: 720, selfieJpegQuality: 0.82, faceDistanceThreshold: 0.5,
        blinkLivenessScore: null, blinkLivenessMessage: 'Ready for the server', isPassiveLiveness: true,
        clientBlinkPolicy: null, blinkChallenge: null,
        passiveReadyText: 'Ready for the server', window: {}, URL: { createObjectURL: () => 'blob:selfie' },
        document: {
            getElementById: id => id === 'selfieCamera' ? video : input,
            createElement: () => ({
                getContext: () => ({ drawImage() {} }), toBlob: callback => callback({}), toDataURL: () => 'data:image/jpeg;base64,test',
            }),
        },
        hasRequiredLivenessEvidence: () => true, ensureReferenceFaceDescriptor: async () => [],
        detectFaceDescriptors: async () => [{ detection: { score: 0.97 }, descriptor: [], landmarks: {} }],
        faceapi: { euclideanDistance: () => 0.32 }, getFaceRollAngle: () => 2,
        analyzeScreenSpoofFromCanvas: () => ({ score: 0 }),
        fillFaceVerificationInputs: value => { metadata = value; },
        updateLiveFrameFeedback: (value, text) => { state = value; message = text; },
        clearSelfiePreview() {}, setBlinkResult() {}, setSelfieSurfaceMode() {}, stopLiveCamera() {}, updateAttendanceButtonState() {},
    });
    load(context, 'captureLiveSelfie');
    await context.captureLiveSelfie();
    assert.equal(metadata.distance, 0.32);
    assert.equal(metadata.detectionScore, 0.97);
    assert.equal(metadata.rollAngle, 2);
    assert.equal(context.cameraVerificationLocked, true);
    assert.equal(state, 'pending');
    assert.match(message, /ditentukan server/);
});

test('a frontal face with a tilted camera and obscured jaw reaches passive capture without changing the identity threshold', async () => {
    const input = {};
    let state;
    let captures = 0;
    const face = { detection: { score: 0.96, box: { x: 100, y: 80, width: 120, height: 200 } }, descriptor: [], landmarks: landmarks(0, 0.18, 300) };
    const context = vm.createContext({
        cameraSessionId: 1, cameraVerificationLocked: false, faceApiReady: true, isPassiveLiveness: true,
        clientBlinkPolicy: null, blinkChallenge: null,
        faceDistanceThreshold: 0.5, TURN_CENTER_MAX_YAW: 0.035, passiveCaptureText: 'Hold still',
        document: { getElementById: id => id === 'selfieCamera' ? { readyState: 2 } : input },
        syncLiveCameraOverlaySize: () => ({ width: 320, height: 400 }),
        ensureReferenceFaceDescriptor: async () => [], detectFaceDescriptors: async () => [face],
        faceapi: { resizeResults: items => items, euclideanDistance: () => 0.3 },
        getLiveGuideRect: () => ({ x: 0, y: 0, width: 320, height: 400 }), getFaceRollAngle: () => 10.3,
        passiveCapture: { observe: () => { captures++; return { frameIndex: null, progress: 0.5 }; } },
        updateLiveFrameFeedback: value => { state = value; },
        resetPassiveCapture() {}, fillFaceVerificationInputs() {}, updateAttendanceButtonState() {}, drawLiveCameraOverlay() {},
    });
    load(context, 'distance', 'averagePoint', 'calculateHeadTurnMetric', 'evaluateLiveCameraFrame');
    await context.evaluateLiveCameraFrame();
    assert.equal(state, 'validating');
    assert.equal(captures, 1);
    context.faceapi.euclideanDistance = () => 0.8;
    await context.evaluateLiveCameraFrame();
    assert.equal(state, 'red');
    assert.equal(captures, 1);
});

test('active capture waits for the face to return to center after both head turns', async () => {
    let tick;
    let captured = 0;
    let pose = 0;
    const labels = [];
    const context = vm.createContext({
        cameraSessionId: 1, blinkInterval: null, blinkIsProcessing: false, cameraVerificationLocked: false, useAccurateFaceDetector: false,
        TURN_SAMPLE_INTERVAL_MS: 140, TURN_CENTER_FRAMES: 3, TURN_CENTER_MAX_YAW: 0.035, TURN_MIN_YAW: 0.07,
        faceMatchedReady: true, latestFaceMatchCapturePayload: { distance: 0.3 },
        setInterval: callback => { tick = callback; return 1; }, clearInterval() {},
        faceapi: {
            TinyFaceDetectorOptions: function (options) { return options; },
            detectAllFaces: () => ({ withFaceLandmarks: async () => [{ landmarks: {} }] }),
        },
        calculateHeadTurnMetric: () => ({ yaw: pose, absYaw: Math.abs(pose) }),
        resetLivenessEvidence() {}, resetFaceMatchedReady() {},
        captureLivenessEvidenceFrame: label => labels.push(label),
        setBlinkResult: (_, score) => { context.blinkLivenessScore = score; },
        captureSelfieFromTurn: async () => captured++,
    });
    load(context, 'startTurnLiveness');
    await context.startTurnLiveness({ readyState: 2 });
    for (pose of [0, 0, 0, -0.08, 0.08]) { await tick(); }
    assert.deepEqual(labels, ['center', 'turn_left', 'turn_right']);
    assert.equal(captured, 0);
    await tick();
    assert.equal(captured, 0);
    pose = 0;
    await tick();
    assert.equal(captured, 1);
});

test('a captured image is displayed as unverified evidence rather than a green liveness success', () => {
    assert.match(source, /<p id="cameraFrameMessage"[^>]*role="status"/);
    assert.equal((source.match(/id="cameraFrameMessage"/g) || []).length, 1);
    const nodes = Object.fromEntries(['selfieCameraStage', 'cameraFrameBadge', 'cameraGuideLabel', 'cameraFrameMessage',
        'cameraHoldBar', 'cameraProgressText', 'cameraAssistText'].map(id => [id, { dataset: {}, style: {} }]));
    let alert;
    const context = vm.createContext({ document: { getElementById: id => nodes[id] }, updateFaceStatus: (_, value) => { alert = value; } });
    load(context, 'updateLiveFrameFeedback');
    context.updateLiveFrameFeedback('pending', 'Bukti kamera siap dikirim.', 1);
    assert.equal(nodes.selfieCameraStage.dataset.frameState, 'pending');
    assert.equal(nodes.cameraFrameBadge.textContent, 'Belum diverifikasi');
    assert.equal(nodes.cameraProgressText.textContent, 'Belum diverifikasi');
    assert.equal(alert, 'info');
    assert.equal(source.includes('Liveness wajah sudah valid.'), false);
    context.updateLiveFrameFeedback('blink', 'Tutup kedua mata dan tahan sekitar 1 detik, lalu buka kembali. Kedip 0/2.');
    assert.equal(nodes.cameraGuideLabel.textContent, 'Tutup kedua mata dan tahan sekitar 1 detik, lalu buka kembali. Kedip 0/2.');
    assert.equal(nodes.cameraProgressText.textContent, 'Ikuti instruksi kedip');
});
