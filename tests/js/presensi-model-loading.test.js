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

function modelContext(overrides = {}) {
    const messages = [];
    const context = vm.createContext({
        faceReferencePath: '/private-reference', faceModelPath: '/models', faceApiReady: false,
        clientBlinkPolicy: null, blinkChallenge: null,
        faceModelsLoadPromise: null, faceModelLoadError: '', faceModelLoads: new Map(),
        faceapi: { nets: { tinyFaceDetector: {}, faceLandmark68Net: {}, faceRecognitionNet: {} } },
        updateLiveFrameFeedback: (state, message) => messages.push({ state, message }),
        updateFaceStatus() {}, updateAttendanceButtonState() {}, resetFaceVerificationState() {},
        loadFaceModel: async () => {}, ...overrides,
    });
    return { context, messages };
}

test('a failed model load remains a visible camera error instead of being replaced by preparing status', async () => {
    const { context, messages } = modelContext({
        loadFaceModel: async () => { throw new Error('Model titik wajah gagal dimuat (HTTP 404).'); },
        cameraSessionId: 1, cameraVerificationLocked: false,
        document: { getElementById: () => ({ readyState: 2 }) },
        syncLiveCameraOverlaySize: () => ({ width: 320, height: 400 }), drawLiveCameraOverlay() {},
    });
    load(context, 'loadFaceModels', 'evaluateLiveCameraFrame');
    assert.equal(await context.loadFaceModels(), false);
    await context.evaluateLiveCameraFrame();
    assert.equal(messages.at(-1).state, 'red');
    assert.match(messages.at(-1).message, /HTTP 404/);
    assert.equal(context.faceApiReady, false);
});

test('model loading is single-flight and a failed attempt can be retried successfully', async () => {
    let fail = true;
    let calls = 0;
    let release;
    const waiting = new Promise(resolve => { release = resolve; });
    const { context } = modelContext({ loadFaceModel: async () => {
        calls++;
        await waiting;
        if (fail) { throw new Error('Network error'); }
    } });
    load(context, 'loadFaceModels');
    const first = context.loadFaceModels();
    const second = context.loadFaceModels();
    await new Promise(setImmediate);
    assert.equal(calls, 3);
    release();
    assert.deepEqual(await Promise.all([first, second]), [false, false]);
    assert.equal(context.faceModelsLoadPromise, null);
    fail = false;
    assert.equal(await context.loadFaceModels(), true);
    assert.equal(calls, 6);
    assert.equal(context.faceApiReady, true);
    assert.equal(context.faceModelLoadError, '');
    assert.equal(await context.loadFaceModels(), true);
    assert.equal(calls, 6);
});

test('a missing face-api script reports a library error and keeps capture blocked', async () => {
    const { context } = modelContext({ faceapi: undefined });
    load(context, 'loadFaceModels');
    assert.equal(await context.loadFaceModels(), false);
    assert.match(context.faceModelLoadError, /pustaka/i);
    assert.equal(context.faceApiReady, false);
});

test('a missing blink script keeps capture blocked and explains how to recover', async () => {
    const { context } = modelContext({ clientBlinkPolicy: { required_count: 1 }, blinkChallenge: null });
    load(context, 'loadFaceModels');
    assert.equal(await context.loadFaceModels(), false);
    assert.match(context.faceModelLoadError, /kedip.*Muat ulang/);
    assert.equal(context.faceApiReady, false);
});

function assetContext(fetch) {
    let nextTimer = 0;
    let writes = 0;
    const timers = new Map();
    const net = { isLoaded: false, getDefaultModelName: () => 'face_landmark_68_model', loadFromWeightMap() { writes++; this.isLoaded = true; } };
    const { context } = modelContext({
        AbortController,
        window: { fetch, setTimeout: callback => { timers.set(++nextTimer, callback); return nextTimer; }, clearTimeout: id => timers.delete(id) },
        faceapi: { tf: { io: { weightsLoaderFactory: download => async (manifest, root) => {
            await download(manifest[0].paths.map(path => root + '/' + path));
            return {};
        } } } },
    });
    load(context, 'loadFaceModel');
    return { context, timers, net, writes: () => writes };
}

test('a missing shard identifies its model, releases the pending attempt and allows a clean retry', async () => {
    let missing = true;
    const paths = [];
    const { context, timers, net, writes } = assetContext(async (path, options) => {
        paths.push(path);
        assert.ok(options.signal);
        return { ok: !missing || path.endsWith('.json'), status: missing ? 404 : 200,
            json: async () => [{ paths: ['face_landmark_68_model-shard1'] }], arrayBuffer: async () => new ArrayBuffer(8) };
    });
    await assert.rejects(context.loadFaceModel(net, 'titik wajah'), /titik wajah.*HTTP 404/);
    assert.equal(writes(), 0);
    assert.equal(timers.size, 0);
    assert.equal(context.faceModelLoads.size, 0);
    missing = false;
    await context.loadFaceModel(net, 'titik wajah');
    assert.equal(writes(), 1);
    assert.equal(paths.length, 4);
    await context.loadFaceModel(net, 'titik wajah');
    assert.equal(paths.length, 4);
});

test('a stalled model request is aborted at the deadline rather than leaving the camera preparing forever', async () => {
    let requestSignal;
    const { context, timers, net, writes } = assetContext((_, { signal }) => {
        requestSignal = signal;
        return new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new Error('Aborted')), { once: true }));
    });
    const loading = context.loadFaceModel(net, 'pengenalan wajah');
    const rejected = assert.rejects(loading, /60 detik/);
    await new Promise(setImmediate);
    timers.values().next().value();
    await rejected;
    assert.equal(requestSignal.aborted, true);
    assert.equal(writes(), 0);
    assert.equal(timers.size, 0);
    assert.equal(context.faceModelLoads.size, 0);
});
