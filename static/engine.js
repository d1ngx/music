(function(global){
	if(global.KodMusicEngine) return;

	var STROKE='fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
	var ICONS={
		play:'<path fill="currentColor" stroke="none" d="M6 5.14v13.72a1 1 0 0 0 1.52.85l10.6-6.86a1 1 0 0 0 0-1.7L7.52 4.29A1 1 0 0 0 6 5.14z"/>',
		pause:'<rect fill="currentColor" stroke="none" x="6" y="4.5" width="4" height="15" rx="1.2"/><rect fill="currentColor" stroke="none" x="14" y="4.5" width="4" height="15" rx="1.2"/>',
		next:'<path fill="currentColor" stroke="none" d="M2.5 6.6v10.8a.9.9 0 0 0 1.4.75L11.5 13v4.4a.9.9 0 0 0 1.4.75l8.3-5.4a.9.9 0 0 0 0-1.5l-8.3-5.4a.9.9 0 0 0-1.4.75V11L3.9 5.85a.9.9 0 0 0-1.4.75z"/>',
		prev:'<path fill="currentColor" stroke="none" d="M21.5 6.6v10.8a.9.9 0 0 1-1.4.75L12.5 13v4.4a.9.9 0 0 1-1.4.75l-8.3-5.4a.9.9 0 0 1 0-1.5l8.3-5.4a.9.9 0 0 1 1.4.75V11l7.6-5.15a.9.9 0 0 1 1.4.75z"/>',
		shuffle:'<polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/>',
		repeat:'<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
		repeat1:'<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/><path d="M11 10.5l1.5-1v5.5" stroke-width="1.8"/>',
		home:'<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
		clock:'<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/>',
		mic:'<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/>',
		album:'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r=".6" fill="currentColor"/>',
		note:'<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
		list:'<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r=".8" fill="currentColor"/><circle cx="3.5" cy="12" r=".8" fill="currentColor"/><circle cx="3.5" cy="18" r=".8" fill="currentColor"/>',
		grid:'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		search:'<circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.2" y2="16.2"/>',
		heart:'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
		heartFill:'<path fill="currentColor" d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
		more:'<circle cx="5" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.7" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.7" fill="currentColor" stroke="none"/>',
		lyrics:'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8.5 8.5h2v2.2c0 1.1-.6 1.8-1.6 2.1M13.5 8.5h2v2.2c0 1.1-.6 1.8-1.6 2.1"/>',
		queue:'<line x1="9" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="9" y1="18" x2="21" y2="18"/><path fill="currentColor" stroke="none" d="M3 4.5v4l3.2-2z"/><circle cx="4.5" cy="12" r=".8" fill="currentColor"/><circle cx="4.5" cy="18" r=".8" fill="currentColor"/>',
		volume:'<path fill="currentColor" d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>',
		volumeLow:'<path fill="currentColor" d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/>',
		mute:'<path fill="currentColor" d="M11 5 6 9H3v6h3l5 4z"/><line x1="22" y1="9" x2="16" y2="15"/><line x1="16" y1="9" x2="22" y2="15"/>',
		down:'<polyline points="6 9 12 15 18 9"/>',
		left:'<polyline points="15 18 9 12 15 6"/>',
		right:'<polyline points="9 18 15 12 9 6"/>',
		expand:'<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M16 21h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>',
		shrink:'<path d="M8 3v3a2 2 0 0 1-2 2H3M21 8h-3a2 2 0 0 1-2-2V3M16 21v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/>',
		image:'<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
		close:'<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
		sidebar:'<rect x="3" y="4" width="18" height="16" rx="2.5"/><line x1="9" y1="4" x2="9" y2="20"/>',
		refresh:'<polyline points="21 4 21 10 15 10"/><path d="M20 15a8.5 8.5 0 1 1-2-8.8L21 10"/>',
		plus:'<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
		open:'<path d="M14 3h7v7"/><path d="M10 14 21 3"/><path d="M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
		info:'<circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><circle cx="12" cy="7.8" r=".6" fill="currentColor"/>',
		folder:'<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		copy:'<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>',
		trash:'<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
		upload:'<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
		time:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		speakerOn:'<path fill="currentColor" stroke="none" d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/>'
	};
	function icon(name,cls){return '<svg class="i'+(cls?' '+cls:'')+'" viewBox="0 0 24 24" '+STROKE+' aria-hidden="true">'+(ICONS[name]||'')+'</svg>'}
	function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
	function fmt(sec){if(!isFinite(sec)||sec<0)return '--:--';sec=Math.floor(sec);var h=Math.floor(sec/3600),m=Math.floor(sec%3600/60),s=sec%60;return (h?h+':'+(m<10?'0':''):'')+m+':'+(s<10?'0':'')+s}
	// ignore: our own src change aborted the previous load.
	// stay: decode errors, or the retry budget is used up. The track does not change.
	// retry: network, unsupported-src, and stalls, up to two times.
	var PLAYBACK_TRIES = 2;
	var PLAYBACK_STALL = 12000;
	function playbackFault(code, tries) {
		code = code || 0;
		tries = tries || 0;
		if (code === 1) return 'ignore';
		if (code === 3) return 'stay';
		return tries < PLAYBACK_TRIES ? 'retry' : 'stay';
	}
	function playbackMessage(code) {
		if (code === 3) return '解码失败，文件可能已损坏';
		if (code === 4) return '浏览器不支持这个格式';
		if (code === 2 || code === 0) return '网络中断，已停在当前歌曲';
		return '无法播放，已停在当前歌曲';
	}

	function plain(track){
		var out={};
		['id','sourceID','title','artist','album','trackNo','duration','year','ext','favorite','createTime','lyricOffset','isVideo','path','cover','transient','albumKey'].forEach(function(k){if(track&&track[k]!==undefined)out[k]=track[k]});
		return out;
	}

	function create(win,base){
		var doc=win.document;
		base=base||(win.location.pathname+'?plugin/music/');
		if(!/[\/?]$/.test(base))base+='/';
		function csrf(){
			try{if(win.Cookie&&win.Cookie.get){var v=win.Cookie.get('CSRF_TOKEN');if(v)return v}}catch(e){}
			var m=doc.cookie.match(/(?:^|;\s*)CSRF_TOKEN=([^;]*)/);
			return m?decodeURIComponent(m[1]):'';
		}
		var audio=doc.createElement('audio');
		audio.preload='metadata';
		audio.style.display='none';
		doc.body.appendChild(audio);
		var video=doc.createElement('video');
		video.preload='metadata';
		video.playsInline=true;
		video.setAttribute('playsinline','');
		var userKey='';
		try{userKey=String((win.G&&win.G.user&&win.G.user.userID)||'')}catch(e){}
		var SNAP='kod-music-snap-'+userKey;
		var S={entries:[],index:-1,past:[],order:[],seq:1,repeat:'off',shuffle:false,volume:.8,muted:false,offset:0,lyrics:{synced:[],text:''},lyricState:'idle',cache:{},token:0,tries:0,dirty:false,loading:false,blocked:false,error:'',resumeAt:0,queueKind:''};
		var stallTimer=0;
		var subs=[];
		var E={state:S,audio:audio,video:video,ui:null,win:win};

		E.api=function(method,params,post){
			params=params||{};
			var body=new win.URLSearchParams(params),url=base+method;
			if(post)body.set('CSRF_TOKEN',csrf());
			return win.fetch(post?url:url+'&'+body.toString(),{method:post?'POST':'GET',headers:post?{'Content-Type':'application/x-www-form-urlencoded'}:{},body:post?body:null,credentials:'same-origin'}).then(function(r){
				if(r.status===401||r.status===403)throw new Error('登录已失效或没有权限');
				return r.text();
			},function(){throw new Error('网络连接失败')}).then(function(text){
				var res;try{res=JSON.parse(text)}catch(e){throw new Error(/login|登录/i.test(text)?'登录已失效，请刷新页面':'服务返回了无法识别的内容')}
				if(!res.code)throw new Error((res.data&&res.data.message)||(typeof res.data==='string'?res.data:'')||'请求失败');
				return res.data;
			});
		};
		function emit(type){
			subs=subs.filter(function(s){try{if(s.win&&s.win.closed)return false}catch(e){return false}return true});
			subs.slice().forEach(function(s){try{s.fn(type)}catch(e){}});
		}
		E.on=function(fn,w){var s={fn:fn,win:w||null};subs.push(s);return function(){subs=subs.filter(function(x){return x!==s})}};
		E.current=function(){return S.index>=0?S.entries[S.index]||null:null};
		E.track=function(){var e=E.current();return e?e.track:null};
		E.media=function(){var t=E.track();return t&&t.isVideo?video:audio};
		E.time=function(){var m=E.media();return m.getAttribute('src')?m.currentTime||0:S.resumeAt||0};
		E.duration=function(){var m=E.media(),d=m.duration;if(isFinite(d)&&d>0)return d;var t=E.track();return t&&t.duration?t.duration:NaN};
		E.playing=function(){var m=E.media();return !!m.getAttribute('src')&&!m.paused&&!m.ended};
		E.lyricTime=function(){return E.time()+S.offset};
		function entryBy(id){for(var i=0;i<S.entries.length;i++)if(S.entries[i].entryId===id)return S.entries[i];return null}
		function indexOf(id){for(var i=0;i<S.entries.length;i++)if(S.entries[i].entryId===id)return i;return -1}
		function halt(el){try{el.pause()}catch(e){}if(el.getAttribute('src')){el.removeAttribute('src');try{el.load()}catch(e){}}}
		function applyVolume(){var v=S.muted?0:S.volume;audio.volume=v;video.volume=v}
		function buildOrder(){
			var ids=S.entries.map(function(e){return e.entryId}),cur=E.current();
			if(!S.shuffle){S.order=ids;return}
			var rest=ids.filter(function(id){return !cur||id!==cur.entryId});
			for(var i=rest.length-1;i>0;i--){var j=Math.floor(Math.random()*(i+1)),t=rest[i];rest[i]=rest[j];rest[j]=t}
			S.order=cur?[cur.entryId].concat(rest):rest;
		}
		E.upNext=function(){
			var cur=E.current(),ids=S.order.length?S.order:S.entries.map(function(e){return e.entryId});
			var at=cur?ids.indexOf(cur.entryId):-1;
			return ids.slice(at+1).map(entryBy).filter(Boolean);
		};
		var saveTimer=0;
		function save(now){
			if(!now){clearTimeout(saveTimer);saveTimer=setTimeout(function(){save(true)},600);return}
			try{
				if(S.queueKind==='all'&&S.index<0){
					win.localStorage.setItem(SNAP,JSON.stringify({queueKind:'all',tracks:[],order:[],index:-1,time:0,repeat:S.repeat,shuffle:S.shuffle,volume:S.volume,muted:S.muted}));
					return;
				}
				var list=S.entries.slice(0,500);
				win.localStorage.setItem(SNAP,JSON.stringify({
					queueKind:S.queueKind||'custom',
					tracks:list.map(function(e){return plain(e.track)}),
					order:S.order.map(function(id){return indexOf(id)}).filter(function(i){return i>=0&&i<list.length}),
					index:S.index<list.length?S.index:-1,
					time:E.time(),repeat:S.repeat,shuffle:S.shuffle,volume:S.volume,muted:S.muted
				}));
			}catch(e){}
		}
		function restore(){
			var raw;try{raw=JSON.parse(win.localStorage.getItem(SNAP)||'null')}catch(e){raw=null}
			if(!raw)return;
			S.repeat=raw.repeat||'off';S.shuffle=!!raw.shuffle;
			if(typeof raw.volume==='number')S.volume=Math.max(0,Math.min(1,raw.volume));
			S.muted=!!raw.muted;applyVolume();
			S.queueKind=raw.queueKind||(raw.tracks&&raw.tracks.length?'custom':'');
			if(!raw.tracks||!raw.tracks.length)return;
			S.entries=raw.tracks.map(function(t){return {entryId:S.seq++,track:t}});
			S.order=(raw.order&&raw.order.length===S.entries.length)?raw.order.map(function(i){return S.entries[i].entryId}):S.entries.map(function(e){return e.entryId});
			S.index=Math.max(-1,Math.min(raw.index,S.entries.length-1));
			S.resumeAt=raw.time||0;
			var t=E.track();
			if(t){S.offset=(t.lyricOffset||0)/1000;loadLyrics(S.token,t);session(t)}
		}

		var RECENT='kod-music-recent-'+userKey;
		function remember(id){
			var list=E.recent().filter(function(x){return x!==id});list.unshift(id);
			try{win.localStorage.setItem(RECENT,JSON.stringify(list.slice(0,40)))}catch(e){}
		}
		E.recent=function(){try{var v=JSON.parse(win.localStorage.getItem(RECENT)||'[]');return Array.isArray(v)?v:[]}catch(e){return []}};
		function lyricData(d){return {synced:d.synced||[],text:d.text||'',source:d.source||'',wordSources:!!d.wordSources}}
		function loadLyrics(token,t){
			S.lyrics={synced:[],text:''};
			var hit=S.cache[t.sourceID];
			if(hit){S.lyrics=hit;S.lyricState='ok';emit('lyrics');return}
			S.lyricState='loading';emit('lyrics');
			E.api('lyrics',{sourceid:t.sourceID}).then(function(d){
				if(S.token!==token)return;
				S.lyrics=lyricData(d);
				S.lyricState=(S.lyrics.synced.length||S.lyrics.text)?'ok':'none';
				if(S.lyricState==='ok')S.cache[t.sourceID]=S.lyrics;
				if(typeof d.lyricOffset==='number'){t.lyricOffset=d.lyricOffset;S.offset=d.lyricOffset/1000}
				emit('lyrics');
			}).catch(function(){if(S.token===token){S.lyricState='error';emit('lyrics')}});
		}
		function session(t){
			var ms=win.navigator.mediaSession;if(!ms||!win.MediaMetadata)return;
			try{
				ms.metadata=new win.MediaMetadata({title:t.title||'',artist:t.artist||'',album:t.album||'',artwork:t.cover?[{src:t.cover,sizes:'512x512'}]:[]});
			}catch(e){}
		}
		function load(entry,opts){
			var t=entry.track,token=S.token;
			var el=t.isVideo?video:audio;
			S.loading=true;emit('state');
			return E.api('source',{sourceid:t.sourceID}).then(function(d){
				if(S.token!==token)return;
				el.src=d.stream;
				applyVolume();
				if(opts.seek>0){
					var at=opts.seek;
					var once=function(){el.removeEventListener('loadedmetadata',once);if(S.token===token){try{el.currentTime=at}catch(e){}}};
					el.addEventListener('loadedmetadata',once);
				}
				S.loading=false;
				if(opts.autoplay!==false){
					var p=el.play();
					if(p&&p.catch)p.catch(function(err){
						if(S.token!==token)return;
						if(err&&err.name==='NotAllowedError'){S.blocked=true;emit('state')}
					});
				}
				emit('state');
			}).catch(function(err){
				if(S.token!==token)return;
				S.loading=false;
				stay(err.message||'无法获取播放地址');
			});
		}
		E.play=function(entryId,opts){
			opts=opts||{};
			var i=indexOf(entryId);if(i<0)return;
			var prev=E.current();
			if(prev&&prev.entryId!==entryId&&!opts.fromPast){S.past.push(prev);if(S.past.length>200)S.past.shift()}
			S.index=i;S.token++;S.error='';S.blocked=false;S.tries=0;S.resumeAt=0;clearStall();
			var t=S.entries[i].track;
			S.offset=(t.lyricOffset||0)/1000;
			halt(audio);halt(video);
			remember(t.sourceID);
			emit('track');
			loadLyrics(S.token,t);
			session(t);
			load(S.entries[i],opts);
			save();
		};
		E.setQueue=function(tracks,start,opts){
			opts=opts||{};
			if(!tracks||!tracks.length)return false;
			if(typeof opts.shuffle==='boolean')S.shuffle=opts.shuffle;
			S.entries=tracks.map(function(t){return {entryId:S.seq++,track:plain(t)}});
			S.past=[];S.dirty=false;S.queueKind=opts.kind==='all'?'all':'custom';
			if(start==null||start<0)start=S.shuffle?Math.floor(Math.random()*S.entries.length):0;
			start=Math.max(0,Math.min(start,S.entries.length-1));
			S.index=start;buildOrder();S.index=-1;
			E.play(S.entries[start].entryId,{autoplay:opts.autoplay!==false});
			emit('queue');
			return true;
		};
		// Queue the whole library without starting playback. Used when nothing else is queued.
		E.stage=function(tracks){
			if(!tracks||!tracks.length||S.entries.length||S.queueKind==='custom')return false;
			S.entries=tracks.map(function(t){return {entryId:S.seq++,track:plain(t)}});
			S.past=[];S.index=-1;S.resumeAt=0;S.dirty=false;S.queueKind='all';
			buildOrder();
			emit('queue');
			save();
			return true;
		};
		E.isDirty=function(){return S.dirty&&E.upNext().length>0};
		function clearStall(){if(stallTimer){clearTimeout(stallTimer);stallTimer=0}}
		function stay(msg){clearStall();S.loading=false;S.error=msg;emit('error');emit('state')}
		function reload(el){
			var cur=E.current();
			if(!cur||el!==E.media())return;
			if(playbackFault(0,S.tries)==='stay'){stay(playbackMessage(0));return}
			S.tries++;clearStall();
			var at=el.currentTime||0,token=++S.token;
			E.api('source',{sourceid:cur.track.sourceID}).then(function(d){
				if(S.token!==token)return;
				el.src=d.stream;
				el.addEventListener('loadedmetadata',function once(){el.removeEventListener('loadedmetadata',once);try{if(at>0)el.currentTime=at}catch(e){}});
				var p=el.play();if(p&&p.catch)p.catch(function(){});
			}).catch(function(err){if(S.token===token)stay(err.message||playbackMessage(2))});
		}
		function armStall(el){
			if(stallTimer||el!==E.media()||el.paused||el.ended||S.blocked)return;
			var token=S.token,at=el.currentTime||0;
			stallTimer=setTimeout(function(){
				stallTimer=0;
				if(S.token!==token||el!==E.media()||el.paused||el.ended)return;
				if((el.currentTime||0)>at+0.3)return;
				reload(el);
			},PLAYBACK_STALL);
		}
		E.next=function(opts){
			opts=opts||{};
			var cur=E.current();if(!cur)return;
			if(opts.natural&&!opts.skip&&S.repeat==='one'){var m=E.media();m.currentTime=0;m.play().catch(function(){});return}
			var ids=S.order.length?S.order:S.entries.map(function(e){return e.entryId});
			var at=ids.indexOf(cur.entryId),nxt=ids[at+1];
			if(!nxt&&S.repeat==='all'&&ids.length){
				if(S.shuffle){buildOrder();ids=S.order}
				nxt=ids[0]===cur.entryId&&ids.length>1?ids[1]:ids[0];
			}
			if(!nxt){if(opts.natural){emit('state')}return}
			E.play(nxt,{autoplay:true});
		};
		E.prev=function(){
			if(!E.current())return;
			if(E.time()>3){E.seek(0);return}
			var item=S.past.pop();
			if(!item){E.seek(0);return}
			if(indexOf(item.entryId)<0){
				S.entries.splice(Math.max(0,S.index),0,item);
				var pos=S.order.indexOf(E.current().entryId);
				S.order.splice(Math.max(0,pos),0,item.entryId);
			}else{
				var o=S.order.indexOf(item.entryId),c=S.order.indexOf(E.current().entryId);
				if(o>c){S.order.splice(o,1);S.order.splice(c,0,item.entryId)}
			}
			E.play(item.entryId,{autoplay:true,fromPast:true});
		};
		E.toggle=function(){
			if(!E.current()){
				if(S.loading)return;
				var id=(S.order.length?S.order:S.entries.map(function(e){return e.entryId}))[0];
				if(id)E.play(id,{autoplay:true});
				return;
			}
			if(S.loading)return;
			var cur=E.current(),m=E.media();
			if(!m.getAttribute('src')){
				var at=S.resumeAt;S.token++;S.resumeAt=0;
				load(cur,{autoplay:true,seek:at});
				return;
			}
			if(m.error){S.tries=0;S.error='';S.blocked=false;reload(m);return}
			if(m.paused||m.ended){S.blocked=false;var p=m.play();if(p&&p.catch)p.catch(function(err){if(err&&err.name==='NotAllowedError'){S.blocked=true;emit('state')}})}
			else m.pause();
		};
		E.seek=function(sec){
			var m=E.media();
			if(!m.getAttribute('src')){S.resumeAt=Math.max(0,sec);emit('time');return}
			var d=E.duration();
			if(isFinite(d))sec=Math.min(sec,Math.max(0,d-0.25));
			try{m.currentTime=Math.max(0,sec)}catch(e){}
			emit('time');
		};
		E.setVolume=function(v){S.volume=Math.max(0,Math.min(1,v));S.muted=S.volume<=0.001;applyVolume();emit('volume');save()};
		E.toggleMute=function(){S.muted=!S.muted;if(!S.muted&&S.volume<=0.001)S.volume=.6;applyVolume();emit('volume');save()};
		E.setShuffle=function(on){S.shuffle=!!on;buildOrder();emit('state');emit('queue');save()};
		E.cycleRepeat=function(){S.repeat=S.repeat==='off'?'all':S.repeat==='all'?'one':'off';emit('state');save()};
		// Move a song already waiting in the queue. Adding another copy makes it play twice.
		E.insert=function(tracks,atEnd){
			tracks=[].concat(tracks).filter(Boolean);
			if(!tracks.length)return 'same';
			if(!S.entries.length){
				if(atEnd){E.stage(tracks);return 'end'}
				E.setQueue(tracks,0);return 'play';
			}
			var cur=E.current(),curId=cur?cur.entryId:null;
			var order=S.order.length?S.order.slice():S.entries.map(function(e){return e.entryId});
			var taken={},place=[];
			tracks.forEach(function(t){
				var found=null;
				for(var i=0;i<order.length;i++){
					var id=order[i];
					if(id===curId||taken[id])continue;
					var e=entryBy(id);
					if(e&&e.track.sourceID===t.sourceID){found=e;break}
				}
				if(found){
					taken[found.entryId]=1;
					order=order.filter(function(id){return id!==found.entryId});
					found.queued=!atEnd;
					place.push(found.entryId);
					return;
				}
				if(cur&&cur.track.sourceID===t.sourceID&&!atEnd)return;
				var item={entryId:S.seq++,track:plain(t),queued:!atEnd};
				S.entries.push(item);
				place.push(item.entryId);
			});
			if(!place.length)return 'same';
			if(atEnd)order=order.concat(place);
			else{
				var pos=curId?Math.max(0,order.indexOf(curId)+1):0;
				while(pos<order.length){
					var ex=entryBy(order[pos]);
					if(!ex||!ex.queued)break;
					pos++;
				}
				place.forEach(function(id){var e=entryBy(id);if(e)e.queued=true});
				Array.prototype.splice.apply(order,[pos,0].concat(place));
			}
			S.order=order;
			S.dirty=true;emit('queue');save();
			return atEnd?'end':'next';
		};
		E.remove=function(entryId){
			var cur=E.current();if(cur&&cur.entryId===entryId)return;
			var i=indexOf(entryId);if(i<0)return;
			S.entries.splice(i,1);
			if(i<S.index)S.index--;
			S.order=S.order.filter(function(id){return id!==entryId});
			S.dirty=true;emit('queue');save();
		};
		E.move=function(fromId,beforeId){
			var cur=E.current();if(!cur||fromId===beforeId)return;
			var c=S.order.indexOf(cur.entryId),f=S.order.indexOf(fromId);
			if(f<=c)return;
			S.order.splice(f,1);
			var b=beforeId?S.order.indexOf(beforeId):S.order.length;
			if(b<0)b=S.order.length;
			if(b<=c)b=c+1;
			S.order.splice(b,0,fromId);
			S.dirty=true;emit('queue');save();
		};
		E.jump=function(entryId){E.play(entryId,{autoplay:true})};
		E.playNow=function(track){
			if(!E.current()){E.setQueue([track],0);return}
			var item={entryId:S.seq++,track:plain(track)};
			S.entries.push(item);
			S.order.splice(S.order.indexOf(E.current().entryId)+1,0,item.entryId);
			emit('queue');
			E.play(item.entryId,{autoplay:true});
		};
		E.clearUpNext=function(){
			var cur=E.current();
			if(!cur){S.entries=[];S.order=[];S.index=-1}
			else{S.entries=[cur];S.order=[cur.entryId];S.index=0}
			S.dirty=false;emit('queue');save();
		};
		E.clearPast=function(){S.past=[];emit('queue')};
		E.nudgeOffset=function(ms,reset){
			var t=E.track();if(!t)return;
			S.offset=reset?0:Math.max(-15,Math.min(15,Math.round((S.offset+ms/1000)*10)/10));
			t.lyricOffset=Math.round(S.offset*1000);
			emit('lyrics');
			if(!t.transient)E.api('lyricOffset',{sourceid:t.sourceID,offset:t.lyricOffset},true).catch(function(){});
			return S.offset;
		};
		E.importLyrics=function(text){
			var t=E.track();if(!t)return Promise.reject(new Error('先播放一首歌'));
			return E.api('lyricImport',{sourceid:t.sourceID,text:text},true).then(function(d){return useLyrics(t,d)});
		};
		E.searchLyrics=function(){
			var t=E.track();if(!t)return Promise.reject(new Error('先播放一首歌'));
			return E.api('lyricSearch',{sourceid:t.sourceID},true).then(function(d){return useLyrics(t,d)});
		};
		E.refreshLyrics=function(){
			var t=E.track();if(!t)return Promise.reject(new Error('先播放一首歌'));
			return E.api('lyricRefresh',{sourceid:t.sourceID},true).then(function(d){return useLyrics(t,d)});
		};
		function useLyrics(t,d){
			S.lyrics=lyricData(d);
			S.lyricState=(S.lyrics.synced.length||S.lyrics.text)?'ok':'none';
			S.cache[t.sourceID]=S.lyrics;
			if(typeof d.lyricOffset==='number'){t.lyricOffset=d.lyricOffset;S.offset=d.lyricOffset/1000}
			emit('lyrics');
			return d;
		}
		E.setFavorite=function(track,on){
			if(!track||track.transient)return Promise.reject(new Error('先把这首扫描进曲库，才能标记喜爱'));
			return E.api('favorite',{sourceid:track.sourceID,favorite:on?1:0},true).then(function(){
				S.entries.concat(S.past).forEach(function(e){if(e.track.sourceID===track.sourceID)e.track.favorite=on?1:0});
				emit('favorite');save();
				return on?1:0;
			});
		};
		function collectTracks(data){
			var all=[];
			(data.albums||[]).forEach(function(a){(a.tracks||[]).forEach(function(t){t.albumKey=a.key;all.push(t)})});
			all.sort(function(a,b){
				return String(a.artist||'').localeCompare(String(b.artist||''),'zh')||String(a.album||'').localeCompare(String(b.album||''),'zh')||(a.trackNo||0)-(b.trackNo||0)||String(a.title||'').localeCompare(String(b.title||''),'zh');
			});
			return all;
		}
		E.openPath=function(path){
			return E.api('resolve',{path:path}).then(function(track){
				return E.api('collection').then(function(d){
					var all=collectTracks(d),i=-1;
					for(var n=0;n<all.length;n++)if(all[n].sourceID===track.sourceID){i=n;break}
					if(i<0){all.unshift(track);i=0}
					E.setQueue(all.length?all:[track],i,{shuffle:false,kind:all.length?'all':'custom'});
					return track;
				},function(){E.setQueue([track],0,{shuffle:false});return track});
			});
		};
		E.setUi=function(w){E.ui=w;emit('ui')};
		E.nav=null;
		E.requestNav=function(target){E.nav=target;emit('nav')};
		E.takeNav=function(){var n=E.nav;E.nav=null;return n};
		E.uiVisible=function(){
			var w=E.ui;if(!w)return false;
			try{
				if(w.closed)return false;
				var f=w.frameElement;if(!f)return w===win;
				var r=f.getBoundingClientRect();
				return r.width>120&&r.height>120&&f.offsetParent!==null;
			}catch(e){return false}
		};

		function onError(el){
			return function(){
				if(el!==E.media()||!el.getAttribute('src')||!E.current())return;
				var code=el.error&&el.error.code||0;
				var step=playbackFault(code,S.tries);
				if(step==='ignore')return;
				if(step==='stay'){stay(playbackMessage(code));return}
				reload(el);
			};
		}
		[audio,video].forEach(function(el){
			el.addEventListener('ended',function(){if(el===E.media()){clearStall();E.next({natural:true})}});
			el.addEventListener('playing',function(){if(el===E.media()){S.tries=0;S.blocked=false;clearStall();emit('state')}});
			el.addEventListener('play',function(){emit('state')});
			el.addEventListener('pause',function(){if(el===E.media())clearStall();emit('state');save()});
			el.addEventListener('waiting',function(){if(el===E.media())armStall(el);emit('state')});
			el.addEventListener('stalled',function(){if(el===E.media())armStall(el)});
			el.addEventListener('timeupdate',function(){if(el===E.media()){clearStall();emit('time')}});
			el.addEventListener('loadedmetadata',function(){if(el===E.media())emit('time')});
			el.addEventListener('error',onError(el));
		});
		setInterval(function(){if(E.playing())save(true)},5000);
		win.addEventListener('pagehide',function(){save(true)});
		var ms=win.navigator.mediaSession;
		if(ms){
			try{
				ms.setActionHandler('play',function(){E.toggle()});
				ms.setActionHandler('pause',function(){E.media().pause()});
				ms.setActionHandler('previoustrack',function(){E.prev()});
				ms.setActionHandler('nexttrack',function(){E.next()});
				ms.setActionHandler('seekto',function(d){if(d&&typeof d.seekTime==='number')E.seek(d.seekTime)});
			}catch(e){}
		}
		restore();
		return E;
	}

	function lyricIndex(lines,t){var i=-1,lo=0,hi=lines.length-1;while(lo<=hi){var mid=(lo+hi)>>1;if(lines[mid].t<=t){i=mid;lo=mid+1}else hi=mid-1}return i}

	function mountLyrics(E,box,opts){
		opts=opts||{};
		var doc=box.ownerDocument,w=doc.defaultView,S=E.state;
		var inner=doc.createElement('div');inner.className='lx-inner';
		box.innerHTML='';box.appendChild(inner);
		box.classList.add('lx');
		var built=null,builtState='',builtToken=-1,cur=-1,follow=true,idle=0,scroll=0,raf=0,dead=false,spans=[];
		function userScroll(){follow=false;idle=Date.now();box.classList.add('lx-free');if(opts.onFree)opts.onFree(true)}
		box.addEventListener('wheel',userScroll,{passive:true});
		box.addEventListener('touchmove',userScroll,{passive:true});
		function stateText(){return S.lyricState==='loading'?'正在读取歌词…':S.lyricState==='error'?'歌词读取失败':E.track()?'暂无歌词':''}
		function build(){
			built=S.lyrics;builtState=S.lyricState;builtToken=S.token;cur=-1;spans=[];
			inner.innerHTML='';
			var lines=S.lyrics.synced||[];
			box.classList.toggle('lx-static',!lines.length);
			if(!lines.length){
				if(S.lyrics.text){
					S.lyrics.text.split(/\n{2,}/).forEach(function(par){var p=doc.createElement('p');p.className='lx-plain';p.textContent=par;inner.appendChild(p)});
				}else{
					var p=doc.createElement('p');p.className='lx-empty';p.textContent=stateText();inner.appendChild(p);
				}
				box.scrollTop=0;scroll=0;
				return;
			}
			lines.forEach(function(line,i){
				var p=doc.createElement('p');
				p.className='lx-line';
				p.textContent=line.line||'♪';
				p.onclick=function(){E.seek(Math.max(0,line.t-S.offset));follow=true;box.classList.remove('lx-free');if(E.media().paused)E.toggle()};
				inner.appendChild(p);
			});
		}
		// Only sweep words that carry their own timing; line-synced lyrics just highlight the whole line.
		function karaoke(node,line,end){
			spans=[];
			var words=line.words||[];
			if(!words.length)return;
			node.textContent='';
			var n=words.length,avg=n>1?(words[n-1].t-words[0].t)/(n-1):0;
			words.forEach(function(word,i){
				var s=doc.createElement('span');
				s.className='lx-w';
				s.textContent=word.w;
				var next=words[i+1]?words[i+1].t:end,t1;
				if(word.e>word.t)t1=Math.min(word.e,Math.max(next,word.t+0.05));
				else if(words[i+1])t1=next;
				else t1=Math.min(end,word.t+Math.max(0.6,Math.min(3,(avg||1.5)*2)));
				spans.push({el:s,t0:word.t,t1:t1>word.t?t1:word.t+0.3});
				node.appendChild(s);
			});
		}
		function tick(){
			if(dead)return;
			raf=w.requestAnimationFrame(tick);
			if(!box.isConnected){destroy();return}
			if(box.offsetParent===null)return;
			if(built!==S.lyrics||builtState!==S.lyricState||builtToken!==S.token)build();
			var lines=S.lyrics.synced||[];
			if(!lines.length)return;
			var now=E.lyricTime(),i=lyricIndex(lines,now),nodes=inner.children;
			if(i!==cur){
				if(nodes[cur]){nodes[cur].classList.remove('on');nodes[cur].textContent=lines[cur].line||'♪'}
				for(var n=0;n<nodes.length;n++)nodes[n].classList.toggle('past',i>=0&&n<i);
				if(nodes[i]){
					nodes[i].classList.add('on');
					var end=lines[i+1]&&lines[i+1].t>lines[i].t+0.2?lines[i+1].t:lines[i].t+6;
					karaoke(nodes[i],lines[i],end);
				}
				cur=i;
			}
			for(var k=0;k<spans.length;k++){
				var sp=spans[k],p=now<=sp.t0?0:now>=sp.t1?1:(now-sp.t0)/(sp.t1-sp.t0);
				var v=(Math.round(p*1000)/10)+'%';
				if(sp.v!==v){sp.v=v;sp.el.style.setProperty('--p',v)}
			}
			if(!follow&&Date.now()-idle>4000){follow=true;box.classList.remove('lx-free');if(opts.onFree)opts.onFree(false)}
			var target=nodes[cur>=0?cur:0];
			if(follow&&target){
				var goal=target.offsetTop-box.clientHeight*(opts.anchor||0.38)+target.offsetHeight/2;
				if(Math.abs(goal-scroll)>box.clientHeight*1.5)scroll=goal;
				scroll+=(goal-scroll)*0.12;
				box.scrollTop=scroll;
			}else scroll=box.scrollTop;
		}
		function destroy(){dead=true;if(raf)w.cancelAnimationFrame(raf);box.removeEventListener('wheel',userScroll);box.removeEventListener('touchmove',userScroll)}
		raf=w.requestAnimationFrame(tick);
		return {destroy:destroy,follow:function(){follow=true;box.classList.remove('lx-free');if(opts.onFree)opts.onFree(false)},rebuild:function(){built=null}};
	}

	function queueHtml(E){
		var S=E.state,cur=E.current(),up=E.upNext(),past=S.past.slice(-30);
		function row(e,kind){
			var t=e.track;
			return '<div class="q-row" data-entry="'+e.entryId+'" data-kind="'+kind+'"'+(kind==='up'?' draggable="true"':'')+'>'+
				'<img class="q-art" alt="" loading="lazy" src="'+esc(t.cover||'')+'">'+
				'<div class="q-text"><strong>'+esc(t.title)+'</strong><small>'+esc(t.artist||'')+(t.album?' — '+esc(t.album):'')+'</small></div>'+
				(kind==='up'?'<button type="button" class="q-x" data-remove="'+e.entryId+'" title="从队列移除">'+icon('close')+'</button>':'')+
			'</div>';
		}
		var html='';
		if(past.length)html+='<div class="q-head"><span>历史记录</span><button type="button" data-clear="past">清除</button></div>'+past.map(function(e){return row(e,'past')}).join('');
		if(cur)html+='<div class="q-head"><span>正在播放</span></div>'+row(cur,'cur');
		html+='<div class="q-head"><span>继续播放</span>'+(up.length?'<button type="button" data-clear="up">清除</button>':'')+'</div>';
		html+=up.length?up.map(function(e){return row(e,'up')}).join(''):'<p class="q-empty">后面没有歌了。可以在歌曲的「更多」里选择「稍后播放」。</p>';
		return html;
	}
	function mountQueue(E,box){
		var doc=box.ownerDocument,dragId=0;
		function paint(){
			var keep=box.scrollTop;
			box.innerHTML=queueHtml(E);
			var c=box.querySelector('.q-row[data-kind="cur"]');
			if(!box.clientHeight)return;
			if(c&&!box.dataset.scrolled){box.dataset.scrolled='1';box.scrollTop=Math.max(0,c.offsetTop-40)}
			else box.scrollTop=keep;
		}
		var w=doc.defaultView;
		if(w.ResizeObserver)new w.ResizeObserver(function(){if(box.clientHeight&&!box.dataset.scrolled)paint()}).observe(box);
		box.addEventListener('click',function(e){
			var x=e.target.closest('[data-remove]');if(x){e.stopPropagation();E.remove(parseInt(x.dataset.remove,10));return}
			var c=e.target.closest('[data-clear]');if(c){if(c.dataset.clear==='past')E.clearPast();else E.clearUpNext();return}
			var r=e.target.closest('.q-row');if(!r||r.dataset.kind==='cur')return;
			var id=parseInt(r.dataset.entry,10);
			if(r.dataset.kind==='past'){
				var item=null;E.state.past.forEach(function(p){if(p.entryId===id)item=p});
				if(item)E.playNow(item.track);
				return;
			}
			E.jump(id);
		});
		box.addEventListener('dragstart',function(e){var r=e.target.closest('.q-row[data-kind="up"]');if(!r)return;dragId=parseInt(r.dataset.entry,10);r.classList.add('dragging');try{e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',String(dragId))}catch(err){}});
		box.addEventListener('dragover',function(e){if(!dragId)return;e.preventDefault();var r=e.target.closest('.q-row[data-kind="up"]');box.querySelectorAll('.drop-before').forEach(function(n){n.classList.remove('drop-before')});if(r)r.classList.add('drop-before')});
		box.addEventListener('drop',function(e){if(!dragId)return;e.preventDefault();var r=e.target.closest('.q-row[data-kind="up"]');var before=r?parseInt(r.dataset.entry,10):0;var id=dragId;dragId=0;E.move(id,before)});
		box.addEventListener('dragend',function(){dragId=0;box.querySelectorAll('.dragging,.drop-before').forEach(function(n){n.classList.remove('dragging','drop-before')})});
		var off=E.on(function(type){if(type==='track')delete box.dataset.scrolled;if(type==='queue'||type==='track'||type==='favorite')paint()},doc.defaultView);
		paint();
		return {destroy:off,paint:paint};
	}

	function palette(img,cb){
		if(!img||!img.complete||!img.naturalWidth){cb(null);return}
		try{
			var c=img.ownerDocument.createElement('canvas');c.width=c.height=24;
			var x=c.getContext('2d');x.drawImage(img,0,0,24,24);
			var d=x.getImageData(0,0,24,24).data,buckets={};
			for(var i=0;i<d.length;i+=4){
				var r=d[i],g=d[i+1],b=d[i+2],key=(r>>5)+','+(g>>5)+','+(b>>5);
				var bk=buckets[key]||(buckets[key]={n:0,r:0,g:0,b:0});
				bk.n++;bk.r+=r;bk.g+=g;bk.b+=b;
			}
			var list=Object.keys(buckets).map(function(k){var v=buckets[k];return [Math.round(v.r/v.n),Math.round(v.g/v.n),Math.round(v.b/v.n),v.n]}).sort(function(a,b){return b[3]-a[3]});
			cb(list.slice(0,4).map(function(c){return c.slice(0,3)}));
		}catch(e){cb(null)}
	}

	global.KodMusicEngine={create:create,icon:icon,esc:esc,fmt:fmt,mountLyrics:mountLyrics,mountQueue:mountQueue,palette:palette,lyricIndex:lyricIndex,playbackFault:playbackFault,playbackMessage:playbackMessage,playbackTries:PLAYBACK_TRIES,playbackStall:PLAYBACK_STALL};
})(window);
