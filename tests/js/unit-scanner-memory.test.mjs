import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { test } from 'node:test';

const source = (await readFile(new URL('../../resources/js/unit-scanner.js', import.meta.url), 'utf8')).replace(/^import .*;$/gm, '');

function scanner(getUserMedia = async () => null) {
    let factory;
    const timers = new Map();
    let sequence = 0;
    const context = vm.createContext({
        window: { Alpine: { data(name, value) { factory = value; } } },
        document: { addEventListener() {} }, navigator: { mediaDevices: { getUserMedia } },
        DecodeHintType: {}, BarcodeFormat: {}, BrowserMultiFormatReader: class {},
        setTimeout(callback) { timers.set(++sequence, callback); return sequence; },
        clearTimeout(id) { timers.delete(id); },
    });
    vm.runInContext(source, context);
    const state = factory();
    state.$refs = { video: { srcObject: null } };
    state.$nextTick = callback => callback();
    return { state, timers };
}

function media() {
    let stops = 0;
    const track = { stop() { stops++; }, getSettings() { return {}; } };
    return { getTracks: () => [track], getVideoTracks: () => [track], get stops() { return stops; } };
}

test('destroy releases the camera, decoder, image buffers and pending timers', () => {
    const { state, timers } = scanner();
    const stream = media();
    state.stream = stream;
    state.$refs.video.srcObject = stream;
    const canvas = { width: 1024, height: 1024 };
    const bin = { width: 1024, height: 1024 };
    state._canvas = canvas;
    state._binCanvas = bin;
    state.reader = {};
    state._scanning = true;
    state.showDetected('Camera');
    assert.equal(timers.size, 2);
    state.destroy();
    assert.equal(stream.stops, 1);
    assert.equal(state.stream, null);
    assert.equal(state.$refs.video.srcObject, null);
    assert.equal(canvas.width + bin.width, 0);
    assert.equal(state._canvas, null);
    assert.equal(state.reader, null);
    assert.equal(state._scanning, false);
    assert.equal(timers.size, 0);
});

test('camera permission resolving after the scanner closes cannot retain a stream', async () => {
    let resolve;
    const { state } = scanner(() => new Promise(done => { resolve = done; }));
    const pending = state.requestCamera();
    state.destroy();
    const stream = media();
    resolve(stream);
    await pending;
    assert.equal(stream.stops, 1);
    assert.equal(state.stream, null);
});

test('closing while switching lenses stops the newly acquired camera too', async () => {
    let resolve;
    const { state } = scanner(() => new Promise(done => { resolve = done; }));
    const original = media();
    state.stream = original;
    state.pickMainBackCameraId = async () => 'main';
    const pending = state.preferMainBackCamera();
    await new Promise(resolve => setImmediate(resolve));
    state.stopAll();
    const switched = media();
    resolve(switched);
    await pending;
    assert.equal(switched.stops, 1);
    assert.equal(state.stream, null);
});

test('an old frame cannot dispatch a scan or start a second loop after reopening', async () => {
    let resolve;
    const { state, timers } = scanner();
    state._scanning = true;
    state.$refs.video = { readyState: 2, videoWidth: 1280 };
    state.grabAndDecode = () => new Promise(done => { resolve = done; });
    state.onDecode = () => assert.fail('closed scanner must ignore the old frame');
    const pending = state.runLoop();
    state.stopAll();
    state._scanning = true;
    resolve('FTV:camera');
    await pending;
    assert.equal(timers.size, 0);
});
