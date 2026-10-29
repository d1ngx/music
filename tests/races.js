// Isolated regression tests: no network, real audio or persistent storage.
const assert=require('assert'),fs=require('fs'),vm=require('vm');const root=require('path').join(__dirname,'../../../');const sandbox={setTimeout:()=>1,clearTimeout(){},setInterval(){}};sandbox.window=sandbox;vm.runInNewContext(fs.readFileSync(root+'plugins/music/static/engine.js','utf8'),sandbox);
function make(snapshot,userID='1'){const saved={};const events={};let lyricResolve;function media(){return {style:{},listeners:{},currentTime:0,paused:true,setAttribute(){},getAttribute(){return this.src||null},removeAttribute(){this.src=''},addEventListener(k,f){(this.listeners[k]??=[]).push(f)},removeEventListener(k,f){this.listeners[k]=this.listeners[k].filter(x=>x!==f)},emit(k){for(const f of [...(this.listeners[k]||[])])f()},pause(){this.paused=true},load(){},play(){this.paused=false;return Promise.resolve()}}}
const win={KodMusicUserID:userID,document:{createElement:media,body:{appendChild(){}},cookie:''},location:{pathname:'/'},navigator:{},URLSearchParams,localStorage:{getItem(k){return k.startsWith('kod-music-snap-')&&snapshot?JSON.stringify(snapshot):null},setItem(k,v){saved[k]=JSON.parse(v)}},addEventListener(k,f){events[k]=f},fetch(url){if(url.includes('/lyrics&'))return new Promise(r=>lyricResolve=()=>r({text:()=>Promise.resolve(JSON.stringify({code:true,data:{text:'old lyrics',synced:[]}}))}));return Promise.resolve({text:()=>Promise.resolve(JSON.stringify({code:true,data:{stream:'test'}}))})}};const e=sandbox.KodMusicEngine.create(win,'/?plugin/music/');return {e,saved,events,finishLyrics:()=>lyricResolve()};}
const flush=async()=>{for(let i=0;i<12;i++)await Promise.resolve()};
(async()=>{
const a={sourceID:1,title:'A'},b={sourceID:2,title:'B'};
let x=make({tracks:[a],order:[0],index:0,time:10});x.e.toggle();x.finishLyrics();await flush();
assert.equal(x.e.state.lyricState,'ok');assert.equal(x.e.state.lyrics.text,'old lyrics');
console.log('ok restore and immediate playback preserve pending lyrics');
x=make();x.e.stage([a,b]);x.e.remove(x.e.state.entries[1].entryId);x.events.pagehide();
let snap=x.saved['kod-music-snap-1'];assert.equal(snap.queueKind,'custom');assert.equal(snap.tracks.length,1);
let restored=make(snap);assert.equal(restored.e.state.entries.length,1);assert.equal(restored.e.stage([a,b]),false);
console.log('ok unplayed queue edits survive restore');
x.e.clearUpNext();x.events.pagehide();restored=make(x.saved['kod-music-snap-1']);assert.equal(restored.e.stage([a,b]),false);
restored.e.insert(b,true);assert.equal(restored.e.state.entries.length,1);assert.equal(restored.e.state.queueKind,'custom');
console.log('ok cleared queue stays empty and accepts later additions');
x=make();x.e.stage([a,b]);x.e.insert(b,false);x.events.pagehide();snap=x.saved['kod-music-snap-1'];restored=make(snap);
assert.equal(restored.e.upNext()[0].track.sourceID,2);assert.equal(snap.queueKind,'custom');
console.log('ok unplayed queue insertion preserves order');
for(const switchTrack of [false,true]){
 x=make();x.e.state.entries=[{entryId:1,track:a},{entryId:2,track:b}];x.e.state.index=0;x.e.state.order=[1,2];
 x.e.api=m=>Promise.resolve(m==='source'?{stream:'test'}:{text:'',synced:[]});
 x.e.audio.src='A';x.e.audio.currentTime=42;x.e.audio.error={code:2};x.e.audio.emit('error');await flush();
 if(switchTrack){x.e.play(2);await flush();}
 x.e.audio.currentTime=0;x.e.audio.emit('loadedmetadata');
 assert.equal(x.e.audio.currentTime,switchTrack?0:42);
}
console.log('ok retry seek applies only to the original playback');
for(const method of ['importLyrics','searchLyrics','refreshLyrics']){
 x=make();let finishOld;
 x.e.api=m=>m==='lyrics'?new Promise(r=>finishOld=r):Promise.resolve(m==='source'?{stream:'test'}:{text:'new lyrics',synced:[]});
 x.e.setQueue([a],0);await x.e[method]('new');finishOld({text:'old initial lyrics',synced:[]});await flush();
 assert.equal(x.e.state.lyrics.text,'new lyrics');assert.equal(x.e.state.cache[1].text,'new lyrics');
 console.log('ok '+method+' supersedes the initial lyric request');
}
x=make();x.e.state.entries=[{entryId:1,track:a}];x.e.state.index=0;
let pending=[];x.e.api=()=>new Promise(r=>pending.push(r));
const old=x.e.refreshLyrics(),latest=x.e.importLyrics('new');pending[1]({text:'latest',synced:[]});await latest;
pending[0]({text:'old',synced:[]});await old;assert.equal(x.e.state.lyrics.text,'latest');assert.equal(x.e.state.cache[1].text,'latest');
console.log('ok latest explicit lyric request wins out of order');
x=make();let finishOld;x.e.api=m=>m==='lyrics'?new Promise(r=>finishOld=r):m==='source'?Promise.resolve({stream:'test'}):Promise.reject(new Error('failed'));
x.e.setQueue([a],0);await x.e.importLyrics('bad').catch(()=>{});finishOld({text:'old',synced:[]});await flush();assert.equal(x.e.state.lyricState,'error');
console.log('ok failed latest request does not leave loading stuck');
x=make();let reads=0;x.e.api=m=>{if(m==='lyrics')reads++;return Promise.resolve(m==='source'?{stream:'test'}:{text:'',synced:[]})};
x.e.setQueue([a],0);await flush();assert.equal(x.e.state.lyricState,'none');assert.equal(x.e.state.cache[1],undefined);
x.e.play(x.e.current().entryId);await flush();assert.equal(reads,2);
console.log('ok empty lyrics can be retried on a later play');
x=make();let resolveOffset;x.e.api=m=>m==='lyrics'?new Promise(r=>resolveOffset=r):Promise.resolve(m==='source'?{stream:'test'}:{});
x.e.setQueue([a],0);x.e.nudgeOffset(500,false);resolveOffset({text:'late lyrics',synced:[],lyricOffset:0});await flush();
assert.equal(x.e.state.offset,0.5);assert.equal(x.e.track().lyricOffset,500);assert.equal(x.e.state.lyrics.text,'late lyrics');
console.log('ok pending lyrics do not overwrite manual offset');
for(const user of ['1','2','']){
 const q=make(null,user);q.e.stage([a]);q.events.pagehide();
 if(user)assert.deepEqual(Object.keys(q.saved),['kod-music-snap-'+user]);
 else assert.equal(Object.keys(q.saved).length,0);
}
const unknown=make({tracks:[a],index:0,order:[0]},'');assert.equal(unknown.e.state.entries.length,0);
console.log('ok storage is account scoped and disabled without identity');
})().catch(e=>{console.error(e);process.exitCode=1});
