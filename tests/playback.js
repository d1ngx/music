// Decision table for playback faults. Loads the engine in a sandbox and does not touch the page.
var fs = require('fs');
var vm = require('vm');
var sandbox = {};
sandbox.window = sandbox;
vm.runInNewContext(fs.readFileSync(require('path').join(__dirname, '../static/engine.js'), 'utf8'), sandbox);
var fault = sandbox.KodMusicEngine.playbackFault;
var message = sandbox.KodMusicEngine.playbackMessage;
var failed = 0;
function check(name, ok) {
	console.log((ok ? 'ok  ' : 'FAIL') + '  ' + name);
	if (!ok) failed++;
}

check('an aborted load is ignored', fault(1, 0) === 'ignore');
check('a decode error stays on the track', fault(3, 0) === 'stay');
check('the first network error retries', fault(2, 0) === 'retry');
check('the second network error retries', fault(2, 1) === 'retry');
check('the third network error stays', fault(2, 2) === 'stay');
check('a stall uses the same budget', fault(0, 0) === 'retry' && fault(0, 2) === 'stay');
check('an unsupported source retries, then stays', fault(4, 0) === 'retry' && fault(4, 2) === 'stay');
check('stall wait is 12 seconds and the budget is 2', sandbox.KodMusicEngine.playbackStall === 12000 && sandbox.KodMusicEngine.playbackTries === 2);
check('a network stay says the track was kept', message(2) === '网络中断，已停在当前歌曲' && message(0) === message(2));
check('a decode stay names the file', message(3) === '解码失败，文件可能已损坏');
check('an unsupported source names the format', message(4) === '浏览器不支持这个格式');

console.log(failed ? '\n' + failed + ' failed' : '\nall passed');
process.exit(failed ? 1 : 0);
