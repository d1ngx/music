// Engine state tests with fake media; no network or browser storage access.
const assert = require('assert');
const vm = require('vm');
const fs = require('fs');
const sandbox = {setTimeout: () => 1, clearTimeout() {}, setInterval() {}};
sandbox.window = sandbox;
vm.runInNewContext(fs.readFileSync(require('path').join(__dirname, '../static/engine.js'), 'utf8'), sandbox);
function media() {
    return {style: {}, listeners: {}, setAttribute() {}, getAttribute() {return this.src || null},
        removeAttribute() {this.src = ''}, addEventListener(k, f) {(this.listeners[k] ||= []).push(f)},
        removeEventListener() {}, pause() {}, load() {}, play() {return Promise.resolve()}};
}
function engine() {
    return sandbox.KodMusicEngine.create({KodMusicUserID:'1',document: {createElement: media, body: {appendChild() {}}, cookie: ''},
        location: {pathname: '/'}, navigator: {}, localStorage: {getItem() {return null}, setItem() {}}, addEventListener() {}}, '/?plugin/music/');
}
(async () => {
    for (const method of ['importLyrics', 'searchLyrics', 'refreshLyrics']) {
        const e = engine(), s = e.state;
        s.entries = [{entryId: 1, track: {sourceID: 1}}, {entryId: 2, track: {sourceID: 2}}]; s.index = 0;
        let resolve; e.api = () => new Promise(r => {resolve = r});
        const pending = e[method]('lyrics');
        s.index = 1; s.token++; s.lyrics = {text: 'B'}; s.offset = 2;
        resolve({text: 'A', lyricOffset: 1000}); await pending;
        assert.equal(s.lyrics.text, 'B'); assert.equal(s.offset, 2); assert.equal(s.cache[1].text, 'A');
        const current = e[method]('lyrics'); resolve({text: 'B updated', lyricOffset: 3000}); await current;
        assert.equal(s.lyrics.text, 'B updated'); assert.equal(s.offset, 3);
        console.log('ok  ' + method + ' ignores stale responses and applies current responses');
    }
    const e = engine(); e.state.entries = [{entryId: 1, track: {sourceID: 1}}]; e.state.index = 0;
    e.state.tries = 2; e.audio.listeners.playing.forEach(f => f());
    assert.equal(e.state.tries, 2);
    assert.equal(sandbox.KodMusicEngine.playbackFault(2, e.state.tries), 'stay');
    e.audio.src = 'test'; e.state.error = 'network'; e.api = () => new Promise(() => {});
    e.toggle(); assert.equal(e.state.error, ''); assert.equal(e.state.tries, 1);
    console.log('ok  recovery preserves budget; explicit retry starts a new budget');
})().catch(err => {console.error(err); process.exitCode = 1});
