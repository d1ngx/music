(function(){
	var KM=window.KodMusic,K=window.KodMusicEngine;
	if(!KM||!K||KM.engine)return;
	var E=K.create(window),S=E.state,icon=K.icon,esc=K.esc,fmt=K.fmt;
	KM.engine=E;
	var pref=read('kod-music-mini2',{x:null,y:null,panel:'',art:false,panelH:280});
	var closedFor=-1,pageVideo=null,lastCover='',shown=false;

	function read(k,d){try{var v=JSON.parse(localStorage.getItem(k)||'null');return v&&typeof v==='object'?Object.assign({},d,v):d}catch(e){return d}}
	function save(){try{localStorage.setItem('kod-music-mini2',JSON.stringify(pref))}catch(e){}}
	function tips(msg,type){if(window.Tips&&Tips.tips)Tips.tips(msg,type||'warning');else console.warn(msg)}

	var root=document.createElement('aside');
	root.id='kod-music-mini';
	root.className='kmini hidden';
	root.innerHTML=
		'<div class="km-tint"><img alt=""></div>'+
		'<div class="km-art"><img alt=""><div class="km-media"></div></div>'+
		'<div class="km-head">'+
			'<div class="km-now">'+
				'<button type="button" class="km-thumb" data-k="open" title="打开音乐"><img alt=""></button>'+
				'<button type="button" class="km-text" data-k="open" title="打开音乐"><strong></strong><small></small></button>'+
			'</div>'+
			'<div class="km-hover">'+
				'<button type="button" class="km-dot" data-k="close" title="关闭小窗">'+icon('close')+'</button>'+
				'<button type="button" class="km-dot" data-k="open" title="打开音乐">'+icon('open')+'</button>'+
				'<span class="km-sp"></span>'+
				'<button type="button" class="km-chip" data-k="more" title="更多" aria-label="更多">'+icon('more')+'</button>'+
				'<div class="km-chip km-pair"><button type="button" data-k="lyrics" title="歌词">'+icon('lyrics')+'</button><button type="button" data-k="queue" title="待播清单">'+icon('list')+'</button></div>'+
				'<button type="button" class="km-chip" data-k="vol" title="音量" aria-label="音量"></button>'+
			'</div>'+
		'</div>'+
		'<div class="km-seek"><div class="km-bar km-sbar" role="slider" aria-label="播放进度" tabindex="0"><i></i><b></b></div><div class="km-times"><span class="km-cur">0:00</span><span class="km-left">--:--</span></div></div>'+
		'<div class="km-ctrl">'+
			'<button type="button" class="km-b sm" data-k="shuffle" title="随机播放">'+icon('shuffle')+'</button>'+
			'<span class="km-sp"></span>'+
			'<button type="button" class="km-b" data-k="prev" title="上一首">'+icon('prev')+'</button>'+
			'<button type="button" class="km-b lg" data-k="toggle" title="播放"></button>'+
			'<button type="button" class="km-b" data-k="next" title="下一首">'+icon('next')+'</button>'+
			'<span class="km-sp"></span>'+
			'<button type="button" class="km-b sm" data-k="repeat" title="循环"></button>'+
		'</div>'+
		'<div class="km-panel">'+
			'<div class="km-lx"></div><div class="km-q queue"></div>'+
			'<button type="button" class="km-follow hidden" data-k="follow">回到当前歌词</button>'+
		'</div>'+
		'<div class="km-grip" title="拖动调整高度"><i></i></div>';
	document.body.appendChild(root);
	var pop=document.createElement('div');
	pop.className='kmini-pop hidden';
	document.body.appendChild(pop);
	var q=function(s){return root.querySelector(s)};
	var video=E.video;
	video.className='km-video';
	q('.km-media').appendChild(video);

	var lx=K.mountLyrics(E,q('.km-lx'),{anchor:.35,onFree:function(f){q('.km-follow').classList.toggle('hidden',!f)}});
	K.mountQueue(E,q('.km-q'));

	/* ---------- layout ---------- */
	function applyLayout(){
		var t=E.track(),isVideo=!!(t&&t.isVideo);
		root.classList.toggle('with-art',!!pref.art||isVideo);
		root.classList.toggle('art-video',isVideo);
		root.classList.toggle('with-panel',!!pref.panel);
		q('.km-panel').style.height=pref.panelH+'px';
		q('.km-lx').classList.toggle('hidden',pref.panel!=='lyrics');
		q('.km-q').classList.toggle('hidden',pref.panel!=='queue');
		if(pref.panel!=='lyrics')q('.km-follow').classList.add('hidden');
		q('[data-k=lyrics]').classList.toggle('on',pref.panel==='lyrics');
		q('[data-k=queue]').classList.toggle('on',pref.panel==='queue');
		clampPos();placeVideoNow();
	}
	function setPanel(p){
		pref.panel=pref.panel===p?'':p;save();
		if(pref.panel==='queue')delete q('.km-q').dataset.scrolled;
		applyLayout();
	}
	function clampPos(){
		var w=root.offsetWidth||340,h=root.offsetHeight||140;
		if(pref.x==null||pref.y==null){pref.x=window.innerWidth-w-16;pref.y=60}
		pref.x=Math.max(8,Math.min(pref.x,window.innerWidth-w-8));
		pref.y=Math.max(8,Math.min(pref.y,Math.max(8,window.innerHeight-h-8)));
		root.style.left=pref.x+'px';root.style.top=pref.y+'px';
	}
	window.addEventListener('resize',function(){clampPos();closePop()});

	var grip=q('.km-grip');
	grip.addEventListener('pointerdown',function(e){
		if(e.button)return;e.preventDefault();e.stopPropagation();
		var y0=e.clientY,h0=pref.panelH;grip.setPointerCapture(e.pointerId);root.classList.add('km-resizing');
		function mv(ev){
			var room=window.innerHeight-pref.y-(root.offsetHeight-pref.panelH)-8;
			pref.panelH=Math.max(120,Math.min(620,room,h0+ev.clientY-y0));
			q('.km-panel').style.height=pref.panelH+'px';
		}
		function up(){grip.removeEventListener('pointermove',mv);grip.removeEventListener('pointerup',up);grip.removeEventListener('pointercancel',up);root.classList.remove('km-resizing');save()}
		grip.addEventListener('pointermove',mv);grip.addEventListener('pointerup',up);grip.addEventListener('pointercancel',up);
	});

	root.addEventListener('pointerdown',function(e){
		if(e.button||e.target.closest('button,[role=slider],.km-panel,.km-grip'))return;
		var x0=e.clientX-pref.x,y0=e.clientY-pref.y,sx=e.clientX,sy=e.clientY,moved=false;
		root.setPointerCapture(e.pointerId);
		function mv(ev){
			if(!moved&&Math.abs(ev.clientX-sx)+Math.abs(ev.clientY-sy)<4)return;
			if(!moved){moved=true;root.classList.add('km-dragging');closePop()}
			pref.x=ev.clientX-x0;pref.y=ev.clientY-y0;clampPos();placeVideoNow();
		}
		function up(){root.removeEventListener('pointermove',mv);root.removeEventListener('pointerup',up);root.removeEventListener('pointercancel',up);root.classList.remove('km-dragging');if(moved)save()}
		root.addEventListener('pointermove',mv);root.addEventListener('pointerup',up);root.addEventListener('pointercancel',up);
	});

	/* ---------- bars ---------- */
	function bar(el,o){
		var drag=false;
		function r(e){var b=el.getBoundingClientRect();return Math.max(0,Math.min(1,(e.clientX-b.left)/b.width))}
		el.addEventListener('pointerdown',function(e){if(e.button)return;e.stopPropagation();drag=true;el.classList.add('drag');el.setPointerCapture(e.pointerId);set(el,r(e));o.move&&o.move(r(e))});
		el.addEventListener('pointermove',function(e){if(drag){set(el,r(e));o.move&&o.move(r(e))}});
		el.addEventListener('pointerup',function(e){if(!drag)return;drag=false;el.classList.remove('drag');o.set(r(e))});
		el.addEventListener('pointercancel',function(){drag=false;el.classList.remove('drag')});
		el.addEventListener('keydown',function(e){if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();e.stopPropagation();o.step(e.key==='ArrowRight'?1:-1)}});
		el.dragging=function(){return drag};
	}
	function set(el,v){el.style.setProperty('--v',(Math.max(0,Math.min(1,v||0))*100).toFixed(2)+'%')}
	var sbar=q('.km-sbar');
	bar(sbar,{move:function(v){var d=E.duration();if(isFinite(d))times(v*d,d)},set:function(v){var d=E.duration();if(isFinite(d))E.seek(v*d)},step:function(s){E.seek(E.time()+s*5)}});

	/* ---------- paint ---------- */
	function times(t,d){q('.km-cur').textContent=fmt(t);q('.km-left').textContent=isFinite(d)?'-'+fmt(Math.max(0,d-t)):'--:--'}
	function paintTime(){var t=E.time(),d=E.duration();if(!sbar.dragging()){set(sbar,isFinite(d)&&d>0?t/d:0);times(t,d)}}
	function paintModes(){
		var b=q('[data-k=toggle]'),on=E.playing()||S.loading;
		b.innerHTML=icon(on?'pause':'play');b.title=on?'暂停':'播放';
		var sh=q('[data-k=shuffle]');sh.classList.toggle('on',S.shuffle);sh.title=S.shuffle?'随机播放：开':'随机播放：关';
		var rp=q('[data-k=repeat]');rp.innerHTML=icon(S.repeat==='one'?'repeat1':'repeat');rp.classList.toggle('on',S.repeat!=='off');rp.title=S.repeat==='one'?'循环：单曲':S.repeat==='all'?'循环：全部':'循环：关闭';
		root.classList.toggle('km-playing',E.playing());
		root.classList.toggle('km-loading',!!S.loading);
	}
	function volIcon(){var v=S.muted?0:S.volume;return v<=0.001?'mute':v<.5?'volumeLow':'volume'}
	function paintVolume(){
		q('[data-k=vol]').innerHTML=icon(volIcon());
		var vb=pop.querySelector('.km-vbar');if(vb)set(vb,S.muted?0:S.volume);
		var mb=pop.querySelector('[data-p=mute]');if(mb)mb.innerHTML=icon(volIcon());
	}
	function paintTrack(){
		var t=E.track();
		q('.km-text strong').textContent=t?t.title:'';
		q('.km-text small').textContent=t?t.artist+(t.album?' — '+t.album:''):'';
		var cover=t&&t.cover?t.cover:'';
		if(cover!==lastCover){
			lastCover=cover;
			root.querySelectorAll('img').forEach(function(img){if(cover)img.src=cover;else img.removeAttribute('src')});
		}
		applyLayout();paintModes();paintTime();
	}
	function loggedIn(){
		try{return !!(window.G&&G.user&&G.user.userID)}catch(e){return false}
	}
	function visible(){
		if(!loggedIn())return false;
		if(!E.track())return false;
		if(document.documentElement.classList.contains('kmusic-fs'))return false;
		if(closedFor===S.token)return false;
		return !E.uiVisible();
	}
	function paintVisibility(){
		if(!loggedIn()){
			try{var m=E.media();if(m&&!m.paused)m.pause()}catch(e){}
		}
		var v=visible();
		if(v===shown)return;
		shown=v;
		root.classList.toggle('hidden',!v);
		if(v){paintTrack();paintVolume()}else closePop();
		placeVideoNow();
	}

	/* ---------- popovers (menu & volume) ---------- */
	var popFor='';
	function closePop(){pop.classList.add('hidden');pop.innerHTML='';popFor='';root.classList.remove('km-active')}
	function placePop(anchor){
		var r=anchor.getBoundingClientRect(),w=pop.offsetWidth,h=pop.offsetHeight;
		var x=Math.min(window.innerWidth-w-8,Math.max(8,r.right-w)),y=r.bottom+8;
		if(y+h>window.innerHeight-8)y=Math.max(8,r.top-h-8);
		pop.style.left=x+'px';pop.style.top=y+'px';
	}
	function openVolume(anchor){
		if(popFor==='vol'){closePop();return}
		popFor='vol';root.classList.add('km-active');
		pop.className='kmini-pop km-volpop';
		pop.innerHTML='<button type="button" class="km-b sm" data-p="mute" title="静音"></button><div class="km-bar km-vbar" role="slider" aria-label="音量" tabindex="0"><i></i><b></b></div>';
		bar(pop.querySelector('.km-vbar'),{move:function(v){E.setVolume(v)},set:function(v){E.setVolume(v)},step:function(s){E.setVolume(S.volume+s*.05)}});
		paintVolume();placePop(anchor);
	}
	var menuAnchor=null;
	function openMenu(anchor){
		if(popFor==='menu'){closePop();return}
		menuAnchor=anchor;popFor='menu';root.classList.add('km-active');
		pop.className='kmini-pop km-menu';
		drawMenu(mainItems());
	}
	function header(){
		var t=E.track();if(!t)return '';
		return '<div class="km-mh"><img alt="" src="'+esc(t.cover||'')+'"><div><strong>'+esc(t.title)+'</strong><small>'+esc(t.artist+(t.album?' — '+t.album:''))+'</small></div></div><hr>';
	}
	function drawMenu(items,sub){
		pop.innerHTML=(sub?'<button type="button" class="km-mi km-mback" data-back>'+icon('left')+'<span>'+esc(sub)+'</span></button><hr>':header())+items.map(function(it,i){
			if(it.sep)return '<hr>';
			if(it.html)return it.html;
			return '<button type="button" class="km-mi'+(it.on?' on':'')+'" data-i="'+i+'"'+(it.disabled?' disabled title="'+esc(it.disabled)+'"':'')+'>'+icon(it.icon||'')+'<span>'+esc(it.label)+'</span>'+(it.sub?icon('right','km-chev'):'')+(it.hint?'<small>'+esc(it.hint)+'</small>':'')+'</button>';
		}).join('');
		pop.items=items;pop.isSub=!!sub;
		placePop(menuAnchor);
	}
	function mainItems(){
		var t=E.track();if(!t)return [];
		var noLib=t.transient?'先扫描进资料库':'';
		return [
			{label:'添加到播放列表',icon:'plus',sub:1,disabled:noLib,act:listMenu},
			{sep:1},
			{label:'显示简介',icon:'info',act:infoMenu},
			{label:t.favorite?'取消喜爱':'喜爱',icon:t.favorite?'heartFill':'heart',disabled:noLib,act:function(){E.setFavorite(t,!t.favorite).then(function(){paintTrack()}).catch(function(e){tips(e.message)});closePop()}},
			{sep:1},
			{label:'前往当前歌曲',icon:'note',act:function(){go({type:'current'})}},
			{label:'在资料库中显示专辑',icon:'album',disabled:t.albumKey?'':'这首歌不在资料库中',act:function(){go({type:'album',key:t.albumKey})}},
			{label:'在资料库中显示艺人',icon:'mic',act:function(){go({type:'artist',name:t.artist})}},
			{sep:1},
			{label:'拷贝',icon:'copy',act:function(){copy(t.title+' - '+t.artist+(t.album?' - '+t.album:''))}},
			{label:'在文件管理中定位',icon:'folder',act:function(){closePop();locateFile(t)}},
			{sep:1},
			{label:pref.art?'隐藏大插图':'显示大插图',icon:'image',on:pref.art,disabled:t.isVideo?'视频始终显示画面':'',act:function(){pref.art=!pref.art;save();applyLayout();closePop()}},
			{label:pref.panel==='queue'?'隐藏待播清单':'显示待播清单',icon:'list',on:pref.panel==='queue',act:function(){setPanel('queue');closePop()}},
			{label:pref.panel==='lyrics'?'隐藏歌词':'显示歌词',icon:'lyrics',on:pref.panel==='lyrics',act:function(){setPanel('lyrics');closePop()}}
		];
	}
	function listMenu(){
		var t=E.track();
		drawMenu([{html:'<p class="km-mnote">正在读取…</p>'}],'添加到播放列表');
		E.api('playlists').then(function(d){
			if(popFor!=='menu')return;
			var lists=d.lists||[];
			drawMenu([{html:'<form class="km-mnew"><input maxlength="40" placeholder="新建播放列表"><button type="submit">创建</button></form>'}].concat(lists.length?[{sep:1}]:[]).concat(lists.map(function(l){return {label:l.name,icon:'list',hint:String(l.count),act:function(){
				E.api('playlistAdd',{id:l.id,sourceid:t.sourceID},true).then(function(){tips('已添加到「'+l.name+'」','success')}).catch(function(e){tips(e.message)});closePop();
			}}})),'添加到播放列表');
			var f=pop.querySelector('.km-mnew');
			f.onsubmit=function(e){e.preventDefault();var name=f.querySelector('input').value.trim();if(!name)return;
				E.api('playlistSave',{name:name,sourceid:t.sourceID},true).then(function(){tips('已添加到「'+name+'」','success')}).catch(function(err){tips(err.message)});closePop()};
		}).catch(function(e){tips(e.message);closePop()});
	}
	function infoMenu(){
		var t=E.track();
		var rows=[['标题',t.title],['艺人',t.artist],['专辑',t.album],['年份',t.year||'—'],['时长',fmt(t.duration||E.duration())],['格式',String(t.ext||'').toUpperCase()||'—']];
		drawMenu([{html:'<dl class="km-info">'+rows.map(function(r){return '<dt>'+r[0]+'</dt><dd>'+esc(r[1])+'</dd>'}).join('')+'</dl>'}],'简介');
	}
	function copy(text){
		closePop();
		if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(text).then(function(){tips('已拷贝','success')},function(){tips(text,'info')});
		else tips(text,'info');
	}
	function go(target){closePop();E.requestNav(target);openMusic()}
	pop.addEventListener('click',function(e){
		e.stopPropagation();
		if(e.target.closest('[data-back]')){drawMenu(mainItems());return}
		if(e.target.closest('[data-p=mute]')){E.toggleMute();return}
		var b=e.target.closest('[data-i]');if(!b||b.disabled)return;
		var it=pop.items[+b.dataset.i];if(it&&it.act)it.act();
	});
	document.addEventListener('pointerdown',function(e){
		if(!popFor)return;
		if(pop.contains(e.target))return;
		var k=e.target.closest&&e.target.closest('#kod-music-mini [data-k]');
		if(k&&(k.dataset.k==='more'||k.dataset.k==='vol'))return;
		closePop();
	},true);
	document.addEventListener('keydown',function(e){if(e.key==='Escape'&&popFor)closePop()});
	pop.addEventListener('wheel',function(e){if(popFor==='vol'){e.preventDefault();E.setVolume(S.volume+(e.deltaY<0?.05:-.05))}},{passive:false});

	/* ---------- video ---------- */
	function placeVideoNow(){
		var t=E.track();
		if(!t||!t.isVideo){video.style.display='none';return}
		if(pageVideo&&pageVideo.rect){
			if(video.parentNode!==document.body)document.body.appendChild(video);
			var r=pageVideo.rect;
			video.className='km-video km-video-page';
			video.style.cssText='display:block;left:'+r.x+'px;top:'+r.y+'px;width:'+r.w+'px;height:'+r.h+'px;object-fit:'+(r.fit||'contain')+';border-radius:'+(r.radius||0)+'px';
			return;
		}
		if(shown){
			if(video.parentNode!==q('.km-media'))q('.km-media').appendChild(video);
			video.className='km-video';
			video.style.cssText='display:block';
			return;
		}
		video.style.display='none';
	}
	KM.placeVideo=function(win,rect){
		if(!rect){if(pageVideo&&pageVideo.win===win){pageVideo=null;placeVideoNow()}return}
		pageVideo={win:win,rect:rect};
		placeVideoNow();
	};

	/* ---------- fullscreen ---------- */
	var fsFrame=null;
	function fsClear(){document.documentElement.classList.remove('kmusic-fs');if(fsFrame)fsFrame.classList.remove('kmusic-fs-frame');fsFrame=null}
	KM.isFullscreen=function(){return !!document.fullscreenElement&&document.documentElement.classList.contains('kmusic-fs')};
	KM.fullscreen=function(frame){
		if(document.fullscreenElement)return document.exitFullscreen().then(function(){fsClear();return false});
		var el=document.documentElement;
		if(!el.requestFullscreen)return Promise.reject(new Error('unsupported'));
		fsFrame=frame||null;
		el.classList.add('kmusic-fs');
		if(fsFrame)fsFrame.classList.add('kmusic-fs-frame');
		return el.requestFullscreen({navigationUI:'hide'}).then(function(){paintVisibility();return true},function(err){fsClear();throw err});
	};
	document.addEventListener('fullscreenchange',function(){if(!document.fullscreenElement){fsClear();paintVisibility()}});

	/* ---------- actions ---------- */
	function openMusic(){
		try{if(window.Router&&typeof Router.go==='function'){Router.go('music');return}}catch(e){}
		location.hash='music';
	}
	function explorerApp(){
		var $=window.jQuery||window.$;
		if($){
			var app=$('#app [app-explorer="1"]').data('appExplorer');
			if(app&&app.pathAction&&typeof app.pathAction.openFatherSelect==='function')return app;
		}
		return null;
	}
	function listingCount(app){
		var n=0;
		try{var fl=app.select&&app.select.fileLight;if(fl&&fl.listAllEach)fl.listAllEach(function(){n++})}catch(e){}
		return n;
	}
	function locateFile(t){
		var path=typeof t==='string'?t:(t&&t.path?String(t.path).replace(/\/+$/,''):(t&&t.sourceID?'{source:'+t.sourceID+'}':''));
		if(!path)return;
		try{
			if(window.Router&&typeof Router.go==='function')Router.go('explorer');
			else location.hash='explorer';
		}catch(e){}
		var n=0,done=false,timer=0;
		var tick=function(){
			if(done){if(timer)clearInterval(timer);return}
			n++;
			if((location.hash||'').indexOf('explorer')<0&&n<25)return;
			var app=explorerApp();
			if(!app){if(n>50&&timer)clearInterval(timer);return}
			if(!listingCount(app)&&n<15)return;
			done=true;
			if(timer)clearInterval(timer);
			var light=app.select&&app.select.fileLight;
			if(light&&typeof light.selectByPath==='function'&&light.selectByPath(path,true))return;
			app.pathAction.openFatherSelect(path,false);
		};
		tick();
		if(!done)timer=setInterval(tick,40);
	}
	KM.locateFile=locateFile;
	root.addEventListener('click',function(e){
		var b=e.target.closest('[data-k]');if(!b)return;
		var k=b.dataset.k;
		if(k==='toggle')E.toggle();
		else if(k==='prev')E.prev();
		else if(k==='next')E.next();
		else if(k==='shuffle')E.setShuffle(!S.shuffle);
		else if(k==='repeat')E.cycleRepeat();
		else if(k==='open')openMusic();
		else if(k==='follow')lx.follow();
		else if(k==='lyrics'||k==='queue')setPanel(k);
		else if(k==='more')openMenu(b);
		else if(k==='vol')openVolume(b);
		else if(k==='close'){
			if(E.playing())E.media().pause();
			closedFor=S.token;closePop();paintVisibility();
		}
	});

	E.on(function(type){
		if(type==='track'){if(closedFor!==S.token)closedFor=-1;paintTrack();paintVisibility()}
		else if(type==='state'){paintModes();if(!E.playing())paintTime()}
		else if(type==='time'){if(!E.playing())paintTime()}
		else if(type==='volume')paintVolume();
		else if(type==='favorite')paintTrack();
		else if(type==='ui')paintVisibility();
		else if(type==='error'&&S.error&&shown)tips(S.error);
	},window);
	(function loop(){requestAnimationFrame(loop);if(shown&&E.playing())paintTime()})();
	setInterval(function(){
		if(pageVideo){try{if(pageVideo.win.closed||!pageVideo.win.frameElement||!pageVideo.win.frameElement.offsetParent){pageVideo=null;placeVideoNow()}}catch(e){pageVideo=null;placeVideoNow()}}
		paintVisibility();
	},400);
	window.addEventListener('hashchange',paintVisibility);

	KM.open=function(path){
		E.openPath(path).catch(function(err){tips(err.message||'无法播放这个文件')});
	};
	(KM.pending||[]).forEach(function(p){KM.open(p)});
	KM.pending=[];

	paintTrack();paintVolume();paintVisibility();
})();
