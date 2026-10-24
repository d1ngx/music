(function(){
	var waitKM=null;
	try{if(window.parent!==window&&window.parent.KodMusic)waitKM=window.parent.KodMusic}catch(e){}
	if(waitKM&&!waitKM.engine){
		var tries=0,timer=setInterval(function(){if(waitKM.engine||++tries>80){clearInterval(timer);boot()}},50);
	}else boot();
function boot(){
	var K=window.KodMusicEngine,host=null;
	try{if(window.parent!==window&&window.parent.KodMusic&&window.parent.KodMusic.engine)host=window.parent}catch(e){host=null}
	var E=host?host.KodMusic.engine:K.create(window);
	var S=E.state,icon=K.icon,esc=K.esc,fmt=K.fmt;
	var $=function(id){return document.getElementById(id)};
	var store=window.localStorage;
	var V={view:'home',stack:[],detail:null,albums:[],tracks:[],bySource:{},lists:[],q:'',pageQ:'',sort:{key:'',dir:1},selected:0,recentMode:'albums',drawer:'',side:readPref('side','lyrics'),nowOpen:false,fit:'contain',ctx:[],loaded:false};
	var library=$('library'),menu=$('menu');
	var NAMES={home:'主页',recent:'最近添加',artists:'艺人',albums:'专辑',tracks:'歌曲',favorites:'喜爱的歌曲',lists:'播放列表',search:'搜索结果'};
	var CHUNK=200;

	function readPref(k,d){try{var v=store.getItem('kod-music-'+k);return v==null?d:v}catch(e){return d}}
	function writePref(k,v){try{store.setItem('kod-music-'+k,v)}catch(e){}}
	function setIcon(id,name){var el=$(id);if(el)el.innerHTML=icon(name)}
	function toast(text,ms){
		var el=$('toast');el.textContent=text;el.classList.remove('hidden');
		clearTimeout(toast.t);toast.t=setTimeout(function(){el.classList.add('hidden')},ms||2600);
	}
	function api(m,p,post){return E.api(m,p,post)}
	function track(id){return V.bySource[id]||(E.track()&&E.track().sourceID===id?E.track():null)}
	function curId(){var t=E.track();return t?t.sourceID:0}
	function total(list){return list.reduce(function(s,t){return s+(t.duration||0)},0)}
	function minutes(sec){var m=Math.round(sec/60);return m>=60?Math.floor(m/60)+' 小时 '+(m%60)+' 分钟':m+' 分钟'}
	function match(t,q){return (t.title+' '+t.artist+' '+t.album).toLowerCase().indexOf(q)>=0}
	function pageFilter(list){var q=V.pageQ.toLowerCase();return q?list.filter(function(t){return match(t,q)}):list}

	/* ---------- modal & menu ---------- */
	function modal(build){
		return new Promise(function(resolve){
			var wrap=$('modal'),box=$('modal-box');
			box.innerHTML=build.html;
			wrap.classList.remove('hidden');
			var input=box.querySelector('input');
			setTimeout(function(){(input||box.querySelector('.ok')||box).focus();if(input)input.select()},20);
			function done(v){wrap.classList.add('hidden');box.onsubmit=null;wrap.onmousedown=null;document.removeEventListener('keydown',key,true);resolve(v)}
			function key(e){if(e.key==='Escape'){e.stopPropagation();done(null)}}
			document.addEventListener('keydown',key,true);
			box.onsubmit=function(e){e.preventDefault();done(build.value?build.value(box):true)};
			box.querySelectorAll('[data-cancel]').forEach(function(b){b.onclick=function(){done(null)}});
			wrap.onmousedown=function(e){if(e.target===wrap)done(null)};
		});
	}
	function ask(title,value,ok){
		return modal({html:'<h3>'+esc(title)+'</h3><input maxlength="40" value="'+esc(value||'')+'" required><div class="modal-btns"><button type="button" data-cancel>取消</button><button class="ok" type="submit">'+esc(ok||'好')+'</button></div>',value:function(b){return b.querySelector('input').value.trim()||null}});
	}
	function confirmBox(title,text,ok,danger){
		return modal({html:'<h3>'+esc(title)+'</h3>'+(text?'<p>'+esc(text)+'</p>':'')+'<div class="modal-btns"><button type="button" data-cancel>取消</button><button class="ok'+(danger?' danger':'')+'" type="submit">'+esc(ok||'好')+'</button></div>'});
	}
	function infoBox(t){
		var rows=[['标题',t.title],['艺人',t.artist],['专辑',t.album],['年份',t.year||'—'],['音轨',t.trackNo||'—'],['时长',fmt(t.duration||E.duration())],['格式',String(t.ext||'').toUpperCase()],['加入时间',t.createTime?new Date(t.createTime*1000).toLocaleString():'—']];
		return modal({html:'<div class="info-head"><img src="'+esc(t.cover||'')+'" alt=""><div><h3>'+esc(t.title)+'</h3><p>'+esc(t.artist)+'</p></div></div><dl class="info">'+rows.map(function(r){return '<dt>'+r[0]+'</dt><dd>'+esc(r[1])+'</dd>'}).join('')+'</dl><div class="modal-btns"><button class="ok" type="submit">好</button></div>'});
	}
	var menuStack=[];
	function openMenu(at,items){
		menuStack=[items];
		drawMenu();
		menu.classList.remove('hidden');
		var r=at.getBoundingClientRect?at.getBoundingClientRect():{left:at.clientX,right:at.clientX,top:at.clientY,bottom:at.clientY};
		var w=menu.offsetWidth,h=menu.offsetHeight;
		var x=Math.min(r.left,window.innerWidth-w-8),y=r.bottom+6;
		if(y+h>window.innerHeight-8)y=Math.max(8,r.top-h-6);
		menu.style.left=Math.max(8,x)+'px';menu.style.top=y+'px';
	}
	function drawMenu(){
		var items=menuStack[menuStack.length-1];
		var html=menuStack.length>1?'<button type="button" class="m-back" data-back>'+icon('left')+'<span>返回</span></button><hr>':'';
		html+=items.map(function(it,i){
			if(it.sep)return '<hr>';
			if(it.note)return '<p class="m-note">'+esc(it.note)+'</p>';
			return '<button type="button" data-i="'+i+'" class="'+(it.danger?'danger':'')+'"'+(it.disabled?' disabled title="'+esc(it.disabled)+'"':'')+'>'+icon(it.icon||'')+'<span>'+esc(it.label)+'</span>'+(it.sub?icon('right','m-sub'):'')+(it.hint?'<small>'+esc(it.hint)+'</small>':'')+'</button>';
		}).join('');
		menu.innerHTML=html;
		var h=menu.offsetHeight,top=parseFloat(menu.style.top)||0;
		if(top+h>window.innerHeight-8)menu.style.top=Math.max(8,window.innerHeight-h-8)+'px';
	}
	function closeMenu(){menu.classList.add('hidden');menuStack=[]}
	menu.addEventListener('click',function(e){
		e.stopPropagation();
		if(e.target.closest('[data-back]')){menuStack.pop();drawMenu();return}
		var b=e.target.closest('[data-i]');if(!b||b.disabled)return;
		var it=menuStack[menuStack.length-1][+b.dataset.i];
		if(it.sub){
			var sub=typeof it.sub==='function'?it.sub():it.sub;
			Promise.resolve(sub).then(function(list){menuStack.push(list);drawMenu()});
			return;
		}
		closeMenu();
		if(it.act)it.act();
	});

	/* ---------- data ---------- */
	function load(){
		return api('collection').then(function(d){
			V.albums=d.albums||[];
			V.tracks=[];V.bySource={};
			V.albums.forEach(function(a){(a.tracks||[]).forEach(function(t){t.albumKey=a.key;V.tracks.push(t);V.bySource[t.sourceID]=t})});
			if(V.detail&&V.detail.builtin)V.detail.tracks=allTracks();
			V.loaded=true;
			ensureDefaultQueue();
			render();
		}).catch(function(e){library.innerHTML=emptyHtml(e.message,'refresh','重试','reload')});
	}
	function loadLists(){
		return api('playlists').then(function(d){V.lists=d.lists||[];paintNavLists();return V.lists}).catch(function(){return V.lists});
	}
	function paintNavLists(){
		var all=document.querySelector('.nav-item[data-nav-list="all"]');
		if(all)all.classList.toggle('active',!!(V.detail&&V.detail.builtin));
		$('nav-lists').innerHTML=V.lists.map(function(l){return '<button class="nav-item'+(V.detail&&V.detail.type==='list'&&V.detail.id===l.id?' active':'')+'" type="button" data-nav-list="'+l.id+'">'+icon('list')+'<span>'+esc(l.name)+'</span></button>'}).join('');
	}
	function allTracks(){
		return V.tracks.slice().sort(function(a,b){
			return String(a.artist||'').localeCompare(String(b.artist||''),'zh')||String(a.album||'').localeCompare(String(b.album||''),'zh')||(a.trackNo||0)-(b.trackNo||0)||String(a.title||'').localeCompare(String(b.title||''),'zh');
		});
	}
	function ensureDefaultQueue(){
		if(S.queueKind==='custom'||S.entries.length||!V.tracks.length)return;
		E.stage(allTracks());
	}
	function playKind(){return (V.detail&&V.detail.builtin||!V.detail&&V.view==='tracks')&&!V.pageQ?'all':'custom'}

	/* ---------- navigation ---------- */
	function go(view){
		V.view=view;V.detail=null;V.stack=[];V.pageQ='';$('page-search').value='';
		if(view!=='search'){V.q='';$('global-search').value=''}
		V.sort={key:'',dir:1};
		render();library.scrollTop=0;
		$('app').classList.remove('nav-open');
	}
	function open(detail){
		V.stack.push({view:V.view,detail:V.detail,scroll:library.scrollTop});
		V.detail=detail;V.pageQ='';$('page-search').value='';
		render();library.scrollTop=0;
	}
	function back(){
		var s=V.stack.pop();if(!s)return;
		V.view=s.view;V.detail=s.detail;render();library.scrollTop=s.scroll||0;
	}
	function paintNav(){
		document.querySelectorAll('.nav-item[data-view]').forEach(function(n){n.classList.toggle('active',!V.detail&&n.dataset.view===V.view)});
		paintNavLists();
		$('back').classList.toggle('hidden',!V.stack.length);
	}

	/* ---------- render ---------- */
	function emptyHtml(text,ic,btn,cmd){
		return '<div class="empty"><span class="empty-ic">'+icon(ic||'note')+'</span><p>'+esc(text)+'</p>'+(btn?'<button type="button" class="btn" data-cmd="'+cmd+'">'+esc(btn)+'</button>':'')+'</div>';
	}
	function render(){
		V.ctx=[];
		paintNav();
		if(!V.loaded){library.innerHTML='<div class="empty"><p>正在加载…</p></div>';return}
		var d=V.detail,html;
		if(d&&d.type==='album')html=albumPage(d);
		else if(d&&d.type==='artist')html=artistPage(d);
		else if(d&&d.type==='list')html=listPage(d);
		else html=({home:homePage,recent:recentPage,artists:artistsPage,albums:albumsPage,tracks:tracksPage,favorites:favPage,lists:listsPage,search:searchPage}[V.view]||homePage)();
		library.innerHTML=html;
		if(!d)$('heading').textContent=NAMES[V.view]||'音乐';
		observeMore();
	}
	function head(opts){
		return '<header class="hero'+(opts.round?' round':'')+'">'+
			'<div class="hero-art">'+(opts.cover?'<img src="'+esc(opts.cover)+'" alt="">':'<span class="tile-ic">'+icon(opts.icon||'note')+'</span>')+'</div>'+
			'<div class="hero-text"><small>'+esc(opts.kind)+'</small><h2>'+esc(opts.title)+'</h2>'+(opts.sub?'<p class="hero-sub">'+opts.sub+'</p>':'')+'<p class="hero-meta">'+esc(opts.meta||'')+'</p>'+
			'<div class="hero-btns"><button type="button" class="btn primary" data-cmd="play-all"'+(opts.count?'':' disabled')+'>'+icon('play')+'播放</button><button type="button" class="btn" data-cmd="shuffle-all"'+(opts.count?'':' disabled')+'>'+icon('shuffle')+'随机播放</button>'+(opts.more?'<button type="button" class="btn round" data-cmd="'+opts.more+'" title="更多">'+icon('more')+'</button>':'')+'</div></div>'+
		'</header>';
	}
	function ctx(list){V.ctx.push(list);return V.ctx.length-1}
	function table(list,opts){
		opts=opts||{};
		if(!list.length)return emptyHtml(opts.empty||'没有歌曲');
		if(opts.sortable&&V.sort.key){
			var k=V.sort.key,dir=V.sort.dir;
			list=list.slice().sort(function(a,b){var x=a[k],y=b[k];if(typeof x==='string')return x.localeCompare(y,'zh')*dir;return ((x||0)-(y||0))*dir});
		}
		var id=ctx(list);
		var cls='tl'+(opts.album?' no-album':'')+(opts.trackNo?' numbered':'')+(opts.list?' reorder':'');
		var th=function(key,label,c){return '<span class="'+c+(opts.sortable?' sortable':'')+(V.sort.key===key?' sorted'+(V.sort.dir<0?' desc':''):'')+'"'+(opts.sortable?' data-sort="'+key+'"':'')+'>'+label+'</span>'};
		return '<div class="'+cls+'" data-ctx="'+id+'"'+(opts.list?' data-list-id="'+opts.list+'"':'')+'>'+
			'<div class="tl-head"><span class="c-n">#</span>'+th('title','标题','c-t')+th('artist','艺人','c-a')+(opts.album?'':th('album','专辑','c-b'))+th('duration',icon('time'),'c-d')+'<span class="c-x"></span></div>'+
			rows(list,0,CHUNK,opts)+
			(list.length>CHUNK?'<div class="tl-more" data-from="'+CHUNK+'"></div>':'')+
		'</div>';
	}
	function rows(list,from,count,opts){
		var cur=curId(),html='';
		for(var i=from;i<Math.min(list.length,from+count);i++){
			var t=list[i],on=t.sourceID===cur;
			html+='<div class="tr'+(on?' playing':'')+(V.selected===t.sourceID?' selected':'')+'" data-i="'+i+'" data-source="'+t.sourceID+'"'+(opts&&opts.list?' draggable="true"':'')+'>'+
				'<span class="c-n"><em>'+(opts&&opts.trackNo?(t.trackNo||'·'):(i+1))+'</em><button type="button" class="rp" data-act="play" title="播放">'+icon('play')+'</button><i class="eq"><b></b><b></b><b></b></i></span>'+
				'<span class="c-t">'+(opts&&opts.trackNo?'':'<img loading="lazy" alt="" src="'+esc(t.cover||'')+'">')+'<span class="tt"><strong>'+esc(t.title)+(t.isVideo?' <em class="tag">视频</em>':'')+'</strong><small>'+esc(t.artist)+'</small></span></span>'+
				'<span class="c-a"><button type="button" class="link" data-artist="'+esc(t.artist)+'">'+esc(t.artist)+'</button></span>'+
				(opts&&opts.album?'':'<span class="c-b"><button type="button" class="link" data-album="'+esc(t.albumKey||'')+'">'+esc(t.album)+'</button></span>')+
				'<span class="c-d">'+(t.duration?fmt(t.duration):'')+'</span>'+
				'<span class="c-x"><button type="button" class="hf'+(t.favorite?' on':'')+'" data-act="fav" title="'+(t.favorite?'取消喜爱':'喜爱')+'">'+icon(t.favorite?'heartFill':'heart')+'</button><button type="button" class="mo" data-act="more" title="更多">'+icon('more')+'</button></span>'+
			'</div>';
		}
		return html;
	}
	var moreObs=null;
	function observeMore(){
		if(moreObs)moreObs.disconnect();
		var nodes=library.querySelectorAll('.tl-more');if(!nodes.length||!window.IntersectionObserver)return;
		moreObs=new IntersectionObserver(function(list){
			list.forEach(function(en){
				if(!en.isIntersecting)return;
				var node=en.target,tl=node.closest('.tl'),items=V.ctx[+tl.dataset.ctx],from=+node.dataset.from;
				var opts={album:tl.classList.contains('no-album'),trackNo:tl.classList.contains('numbered'),list:tl.dataset.listId};
				node.insertAdjacentHTML('beforebegin',rows(items,from,CHUNK*2,opts));
				if(from+CHUNK*2>=items.length){moreObs.unobserve(node);node.remove()}else node.dataset.from=String(from+CHUNK*2);
			});
		},{root:library,rootMargin:'600px'});
		nodes.forEach(function(n){moreObs.observe(n)});
	}
	function card(a){
		return '<article class="card" data-album="'+esc(a.key)+'"><div class="art"><img loading="lazy" alt="" src="'+esc(a.cover)+'"><div class="art-ov"><button type="button" class="art-play" data-act="play-album" title="播放">'+icon('play')+'</button><button type="button" class="art-more" data-act="album-more" title="更多">'+icon('more')+'</button></div></div><strong title="'+esc(a.album)+'">'+esc(a.album)+'</strong><small>'+esc(a.artist)+'</small></article>';
	}
	function artistCard(a){
		return '<article class="card artist" data-artist="'+esc(a.name)+'"><div class="art"><img loading="lazy" alt="" src="'+esc(a.cover)+'"></div><strong>'+esc(a.name)+'</strong><small>'+a.count+' 首歌曲</small></article>';
	}
	function listCard(l){
		return '<article class="card" data-list="'+l.id+'"><div class="art tile"><span class="tile-ic">'+icon('list')+'</span></div><strong>'+esc(l.name)+'</strong><small>'+l.count+' 首歌曲</small></article>';
	}
	function section(title,body,cmd){return '<section class="sec"><div class="sec-head"><h2>'+esc(title)+'</h2>'+(cmd?'<button type="button" class="link more" data-cmd="'+cmd+'">查看全部'+icon('right')+'</button>':'')+'</div>'+body+'</section>'}
	function shelf(items){return '<div class="shelf">'+items.join('')+'</div>'}
	function grid(items){return '<div class="grid">'+items.join('')+'</div>'}
	function albumMatches(){var q=V.pageQ.toLowerCase();return V.albums.filter(function(a){return !q||(a.album+' '+a.artist).toLowerCase().indexOf(q)>=0})}
	function artists(){
		var map={},q=V.pageQ.toLowerCase();
		V.tracks.forEach(function(t){var a=map[t.artist]||(map[t.artist]={name:t.artist,cover:t.cover,count:0});a.count++});
		return Object.keys(map).sort(function(a,b){return a.localeCompare(b,'zh')}).map(function(k){return map[k]}).filter(function(a){return !q||a.name.toLowerCase().indexOf(q)>=0});
	}
	function homePage(){
		if(!V.tracks.length)return emptyHtml('资料库还是空的。扫描当前账号可读取的音乐，或在文件管理里用「音乐」打开。','note','扫描资料库','scan');
		var recent=E.recent().map(track).filter(Boolean),seen={},played=[];
		recent.forEach(function(t){var a=V.albums.filter(function(x){return x.key===t.albumKey})[0];if(a&&!seen[a.key]){seen[a.key]=1;played.push(a)}});
		var added=V.albums.slice().sort(function(a,b){return (b.added||0)-(a.added||0)}).slice(0,12);
		var fav=V.tracks.filter(function(t){return t.favorite});
		var html='';
		if(played.length)html+=section('最近播放',shelf(played.slice(0,12).map(card)));
		html+=section('最近添加',shelf(added.map(card)),'recent');
		if(fav.length)html+=section('喜爱的歌曲',table(fav.slice(0,8)),'favorites');
		if(V.tracks.length||V.lists.length)html+=section('播放列表',shelf([listCard({id:'all',name:'全部歌曲',count:V.tracks.length})].concat(V.lists.slice(0,11).map(listCard))),'lists');
		return html;
	}
	function recentPage(){
		var seg='<div class="seg in-page"><button type="button" data-recent="albums" class="'+(V.recentMode==='albums'?'on':'')+'">专辑</button><button type="button" data-recent="songs" class="'+(V.recentMode==='songs'?'on':'')+'">歌曲</button></div>';
		if(V.recentMode==='albums'){
			var list=albumMatches().slice().sort(function(a,b){return (b.added||0)-(a.added||0)});
			return seg+(list.length?grid(list.map(card)):emptyHtml('没有最近添加的专辑'));
		}
		return seg+table(pageFilter(V.tracks).slice().sort(function(a,b){return (b.createTime||0)-(a.createTime||0)}));
	}
	function artistsPage(){var list=artists();return list.length?grid(list.map(artistCard)):emptyHtml('没有艺人','mic')}
	function albumsPage(){var list=albumMatches();return list.length?grid(list.map(card)):emptyHtml('没有专辑','album')}
	function tracksPage(){
		var list=pageFilter(V.tracks);
		return '<div class="bar-actions"><span class="note">'+list.length+' 首歌曲 · '+minutes(total(list))+'</span><span class="spacer"></span><button type="button" class="btn primary" data-cmd="play-all"'+(list.length?'':' disabled')+'>'+icon('play')+'播放</button><button type="button" class="btn" data-cmd="shuffle-all"'+(list.length?'':' disabled')+'>'+icon('shuffle')+'随机播放</button></div>'+table(list,{sortable:true});
	}
	function favPage(){
		var list=pageFilter(V.tracks.filter(function(t){return t.favorite}));
		return head({kind:'资料库',title:'喜爱的歌曲',icon:'heartFill',meta:list.length+' 首歌曲 · '+minutes(total(list)),count:list.length})+table(list,{empty:'在歌曲上点按爱心，就会出现在这里',sortable:true});
	}
	function listsPage(){
		return '<div class="grid">'+listCard({id:'all',name:'全部歌曲',count:V.tracks.length})+'<article class="card" data-cmd="list-new"><div class="art tile add"><span class="tile-ic">'+icon('plus')+'</span></div><strong>新建播放列表</strong><small>&nbsp;</small></article>'+V.lists.map(listCard).join('')+'</div>';
	}
	function searchPage(){
		var q=V.q.toLowerCase(),songs=V.tracks.filter(function(t){return match(t,q)});
		var al=V.albums.filter(function(a){return (a.album+' '+a.artist).toLowerCase().indexOf(q)>=0});
		var ar=artists().filter(function(a){return a.name.toLowerCase().indexOf(q)>=0});
		$('heading').textContent='“'+V.q+'”';
		if(!songs.length&&!al.length&&!ar.length)return emptyHtml('没有找到「'+V.q+'」','search');
		var html='';
		if(ar.length)html+=section('艺人',shelf(ar.slice(0,12).map(artistCard)));
		if(al.length)html+=section('专辑',shelf(al.slice(0,12).map(card)));
		if(songs.length)html+=section('歌曲',table(songs.slice(0,300)));
		return html;
	}
	function albumTracks(key){var a=V.albums.filter(function(x){return x.key===key})[0];return a?a.tracks:[]}
	function albumPage(d){
		var a=V.albums.filter(function(x){return x.key===d.key})[0];
		if(!a)return emptyHtml('专辑已不在资料库中','album');
		$('heading').textContent=a.album;
		var list=pageFilter(a.tracks);
		return head({kind:'专辑',title:a.album,cover:a.cover,sub:'<button type="button" class="link" data-artist="'+esc(a.artist)+'">'+esc(a.artist)+'</button>',meta:[a.year,a.tracks.length+' 首歌曲',minutes(total(a.tracks))].filter(Boolean).join(' · '),count:list.length,more:'album-more'})+table(list,{album:true,trackNo:true});
	}
	function artistPage(d){
		$('heading').textContent=d.name;
		var list=pageFilter(V.tracks.filter(function(t){return t.artist===d.name}));
		var al=V.albums.filter(function(a){return a.artist===d.name});
		return head({kind:'艺人',title:d.name,cover:al[0]&&al[0].cover,round:true,meta:al.length+' 张专辑 · '+list.length+' 首歌曲',count:list.length})+
			(al.length?section('专辑',shelf(al.map(card))):'')+section('歌曲',table(list));
	}
	function listPage(d){
		$('heading').textContent=d.name;
		if(!d.tracks)return '<div class="empty"><p>正在读取…</p></div>';
		var list=pageFilter(d.tracks);
		if(d.builtin)return head({kind:'播放列表',title:d.name,icon:'note',meta:d.tracks.length+' 首歌曲 · '+minutes(total(d.tracks)),count:list.length})+table(list,{sortable:true,empty:'资料库里还没有歌曲。'});
		return head({kind:'播放列表',title:d.name,icon:'list',meta:d.tracks.length+' 首歌曲 · '+minutes(total(d.tracks)),count:list.length,more:'list-more'})+table(list,{list:V.pageQ?'':d.id,empty:'这个播放列表还是空的。在歌曲的「更多」菜单里选择「添加到播放列表」。'});
	}
	function detailTracks(){return V.ctx[0]||[]}
	function openList(id){
		if(String(id)==='all'){open({type:'list',id:'all',name:'全部歌曲',builtin:1,tracks:allTracks()});return}
		id=+id;
		var meta=V.lists.filter(function(l){return l.id===id})[0];
		var d={type:'list',id:id,name:meta?meta.name:'播放列表',tracks:null};
		open(d);
		api('playlist',{id:id}).then(function(data){
			d.name=data.name;
			d.tracks=(data.tracks||[]).map(function(t){var x=V.bySource[t.sourceID];return x||t});
			if(V.detail===d)render();
		}).catch(function(e){toast(e.message);if(V.detail===d)back()});
	}

	/* ---------- actions ---------- */
	function playList(list,start,shuffle,kind){
		if(!list||!list.length)return;
		var opts={kind:kind||'custom'};if(typeof shuffle==='boolean')opts.shuffle=shuffle;
		var run=function(){E.setQueue(list,start,opts)};
		if(E.isDirty())confirmBox('替换待播列表？','你手动调整过待播列表，开始播放新的歌曲会替换它。','替换').then(function(ok){if(ok)run()});
		else run();
	}
	function toggleFav(t){
		if(!t)return;
		var next=!t.favorite;
		E.setFavorite(t,next).then(function(){
			[V.bySource[t.sourceID],t].forEach(function(x){if(x)x.favorite=next?1:0});
			paintPlayer();
			library.querySelectorAll('.tr[data-source="'+t.sourceID+'"] .hf').forEach(function(b){b.classList.toggle('on',next);b.innerHTML=icon(next?'heartFill':'heart')});
			if(V.view==='favorites'&&!V.detail)render();
		}).catch(function(e){toast(e.message)});
	}
	function addToList(t,listId){
		return api('playlistAdd',{id:listId,sourceid:t.sourceID},true).then(function(){
			var l=V.lists.filter(function(x){return x.id===listId})[0];
			toast('已添加到「'+(l?l.name:'播放列表')+'」');loadLists();
		}).catch(function(e){toast(e.message)});
	}
	function newList(t){
		return ask('新建播放列表','','创建').then(function(name){
			if(!name)return;
			return api('playlistSave',t?{name:name,sourceid:t.sourceID}:{name:name},true).then(function(d){
				toast(t?'已添加到「'+name+'」':'已创建「'+name+'」');
				return loadLists().then(function(){if(!t)openList(d.id)});
			}).catch(function(e){toast(e.message)});
		});
	}
	function listSub(t){
		return loadLists().then(function(lists){
			return [{label:'新建播放列表…',icon:'plus',act:function(){newList(t)}}].concat(lists.length?[{sep:1}]:[]).concat(lists.map(function(l){return {label:l.name,icon:'list',hint:String(l.count),act:function(){addToList(t,l.id)}}}));
		});
	}
	function locate(t){
		if(!t||!t.sourceID){toast('没有可定位的文件');return}
		try{
			var w=host||window.top;
			w.location.hash='explorer&sidf='+t.sourceID;
		}catch(e){toast('无法切换到文件管理')}
	}
	function trackMenu(at,t,list,extra){
		if(!t)return;
		var isCur=t.sourceID===curId();
		var items=[];
		if(!isCur)items.push({label:'播放',icon:'play',act:function(){var i=list?list.indexOf(t):-1;if(list&&i>=0)playList(list,i);else E.playNow(t)}});
		items.push({label:'下一首播放',icon:'queue',act:function(){E.insert([t],false);toast('将在下一首播放')}});
		items.push({label:'稍后播放',icon:'list',act:function(){E.insert([t],true);toast('已添加到待播列表末尾')}});
		items.push({sep:1});
		items.push({label:'添加到播放列表',icon:'plus',sub:function(){return listSub(t)},disabled:t.transient?'先扫描进资料库':''});
		if(extra&&extra.listId&&extra.listId!=='all')items.push({label:'从播放列表中移除',icon:'trash',act:function(){removeFromList(extra.listId,t)}});
		items.push({label:t.favorite?'取消喜爱':'喜爱',icon:t.favorite?'heartFill':'heart',act:function(){toggleFav(t)},disabled:t.transient?'先扫描进资料库':''});
		items.push({sep:1});
		if(t.albumKey)items.push({label:'前往专辑',icon:'album',act:function(){closeNow();open({type:'album',key:t.albumKey})}});
		items.push({label:'前往艺人',icon:'mic',act:function(){closeNow();open({type:'artist',name:t.artist})}});
		items.push({label:'在文件管理中定位',icon:'folder',act:function(){locate(t)}});
		items.push({label:'歌曲信息',icon:'info',act:function(){infoBox(t)}});
		items.push({label:'复制歌曲信息',icon:'copy',act:function(){copy(t.title+' - '+t.artist+(t.album?' - '+t.album:''))}});
		if(isCur){
			items.push({sep:1});
			items.push({label:'歌词',icon:'lyrics',sub:lyricSub});
			items.push({label:fsOn()?'退出全屏':'全屏播放',icon:fsOn()?'shrink':'expand',act:toggleFs});
		}
		openMenu(at,items);
	}
	function lyricSub(){
		var o=S.offset;
		return [
			{note:'当前偏移 '+(o>0?'+':'')+o.toFixed(1)+' 秒'},
			{label:'歌词提前 0.5 秒',icon:'left',act:function(){showOffset(E.nudgeOffset(500))}},
			{label:'歌词延后 0.5 秒',icon:'right',act:function(){showOffset(E.nudgeOffset(-500))}},
			{label:'重置偏移',icon:'refresh',act:function(){showOffset(E.nudgeOffset(0,true))},disabled:o?'':'当前没有偏移'},
			{sep:1},
			{label:'导入歌词文件…',icon:'upload',act:function(){$('lyric-file').click()}}
		];
	}
	function showOffset(o){if(typeof o==='number')toast('歌词偏移 '+(o>0?'+':'')+o.toFixed(1)+' 秒')}
	function copy(text){
		if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(text).then(function(){toast('已复制')},function(){toast(text)});
		else toast(text,5000);
	}
	function removeFromList(listId,t){
		api('playlistRemove',{id:listId,sourceid:t.sourceID},true).then(function(){
			var d=V.detail;if(d&&d.type==='list'&&d.id===listId&&d.tracks){d.tracks=d.tracks.filter(function(x){return x.sourceID!==t.sourceID});render()}
			loadLists();toast('已从播放列表移除');
		}).catch(function(e){toast(e.message)});
	}
	function listMenu(at,d){
		openMenu(at,[
			{label:'重命名…',icon:'list',act:function(){ask('重命名播放列表',d.name,'保存').then(function(name){if(!name||name===d.name)return;api('playlistRename',{id:d.id,name:name},true).then(function(){d.name=name;render();loadLists()}).catch(function(e){toast(e.message)})})}},
			{label:'稍后播放全部',icon:'queue',act:function(){if(d.tracks&&d.tracks.length){E.insert(d.tracks,true);toast('已添加到待播列表')}},disabled:d.tracks&&d.tracks.length?'':'列表为空'},
			{sep:1},
			{label:'删除播放列表',icon:'trash',danger:1,act:function(){confirmBox('删除「'+d.name+'」？','只删除播放列表，原来的文件不会被删除。','删除',true).then(function(ok){if(!ok)return;api('playlistRemove',{id:d.id},true).then(function(){toast('已删除');loadLists();V.stack=[];go('lists')}).catch(function(e){toast(e.message)})})}}
		]);
	}
	function albumMenu(at,key){
		var list=albumTracks(key);if(!list.length)return;
		openMenu(at,[
			{label:'播放',icon:'play',act:function(){playList(list,0,false)}},
			{label:'随机播放',icon:'shuffle',act:function(){playList(list,-1,true)}},
			{label:'下一首播放',icon:'queue',act:function(){E.insert(list,false);toast('将在下一首播放')}},
			{label:'稍后播放',icon:'list',act:function(){E.insert(list,true);toast('已添加到待播列表末尾')}},
			{sep:1},
			{label:'前往艺人',icon:'mic',act:function(){open({type:'artist',name:list[0].artist})}}
		]);
	}

	/* ---------- library events ---------- */
	function rowInfo(el){
		var r=el.closest('.tr');if(!r)return null;
		var tl=r.closest('.tl'),list=V.ctx[+tl.dataset.ctx]||[];
		return {row:r,list:list,i:+r.dataset.i,track:list[+r.dataset.i],listId:tl.dataset.listId||''};
	}
	library.addEventListener('click',function(e){
		var b=e.target.closest('[data-cmd],[data-act],[data-sort],[data-recent],[data-artist],[data-album],[data-list]');
		if(!b)return;
		var cmd=b.dataset.cmd,act=b.dataset.act,info=rowInfo(b);
		if(cmd){
			e.stopPropagation();
			if(cmd==='scan')return scan();
			if(cmd==='reload')return load();
			if(cmd==='play-all')return playList(detailTracks(),0,false,playKind());
			if(cmd==='shuffle-all')return playList(detailTracks(),-1,true,playKind());
			if(cmd==='list-more')return listMenu(b,V.detail);
			if(cmd==='album-more')return albumMenu(b,V.detail.key);
			if(cmd==='list-new')return newList();
			return go(cmd);
		}
		if(act==='play'&&info)return playList(info.list,info.i,undefined,playKind());
		if(act==='fav'&&info)return toggleFav(info.track);
		if(act==='more'&&info)return trackMenu(b,info.track,info.list,{listId:info.listId});
		if(act==='play-album'){e.stopPropagation();return playList(albumTracks(b.closest('[data-album]').dataset.album),0,false)}
		if(act==='album-more'){e.stopPropagation();return albumMenu(b,b.closest('[data-album]').dataset.album)}
		if(b.dataset.sort){var k=b.dataset.sort;V.sort=V.sort.key===k?(V.sort.dir>0?{key:k,dir:-1}:{key:'',dir:1}):{key:k,dir:1};var st=library.scrollTop;render();library.scrollTop=st;return}
		if(b.dataset.recent){V.recentMode=b.dataset.recent;render();return}
		if(b.dataset.artist!=null&&b.matches('.link,.card')){e.stopPropagation();if(!(V.detail&&V.detail.type==='artist'&&V.detail.name===b.dataset.artist))open({type:'artist',name:b.dataset.artist});return}
		if(b.dataset.album!=null&&b.matches('.link,.card')){e.stopPropagation();if(b.dataset.album)open({type:'album',key:b.dataset.album});return}
		if(b.dataset.list!=null&&b.matches('.card'))return openList(b.dataset.list);
	});
	library.addEventListener('click',function(e){
		var info=rowInfo(e.target);if(!info||e.target.closest('button'))return;
		V.selected=info.track.sourceID;
		library.querySelectorAll('.tr.selected').forEach(function(r){r.classList.remove('selected')});
		info.row.classList.add('selected');
	});
	library.addEventListener('dblclick',function(e){var info=rowInfo(e.target);if(info&&!e.target.closest('button'))playList(info.list,info.i,undefined,playKind())});
	library.addEventListener('contextmenu',function(e){var info=rowInfo(e.target);if(!info)return;e.preventDefault();trackMenu({clientX:e.clientX,clientY:e.clientY},info.track,info.list,{listId:info.listId})});
	var dragRow=null;
	library.addEventListener('dragstart',function(e){var r=e.target.closest('.reorder .tr');if(!r)return;dragRow=r;r.classList.add('dragging');try{e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',r.dataset.source)}catch(err){}});
	library.addEventListener('dragover',function(e){if(!dragRow)return;var r=e.target.closest('.reorder .tr');if(!r||r===dragRow)return;e.preventDefault();var box=r.getBoundingClientRect();r.parentNode.insertBefore(dragRow,e.clientY<box.top+box.height/2?r:r.nextSibling)});
	library.addEventListener('dragend',function(){
		if(!dragRow)return;
		var tl=dragRow.closest('.tl'),d=V.detail;dragRow.classList.remove('dragging');dragRow=null;
		if(!d||d.type!=='list'||!d.tracks)return;
		var order=Array.prototype.map.call(tl.querySelectorAll('.tr'),function(r){return +r.dataset.source});
		var map={};d.tracks.forEach(function(t){map[t.sourceID]=t});
		d.tracks=order.map(function(id){return map[id]}).filter(Boolean);
		api('playlistSort',{id:d.id,order:order.join(',')},true).then(function(){render()}).catch(function(e){toast(e.message);openList(d.id)});
	});

	/* ---------- player chrome ---------- */
	function bar(el,opts){
		var drag=false;
		function ratio(e){var r=el.getBoundingClientRect();return Math.max(0,Math.min(1,(e.clientX-r.left)/r.width))}
		el.addEventListener('pointerdown',function(e){if(e.button)return;drag=true;el.classList.add('drag');el.setPointerCapture(e.pointerId);var v=ratio(e);setBar(el,v);opts.move&&opts.move(v)});
		el.addEventListener('pointermove',function(e){if(!drag)return;var v=ratio(e);setBar(el,v);opts.move&&opts.move(v)});
		function end(e){if(!drag)return;drag=false;el.classList.remove('drag');opts.set(ratio(e))}
		el.addEventListener('pointerup',end);
		el.addEventListener('pointercancel',function(){drag=false;el.classList.remove('drag')});
		el.addEventListener('keydown',function(e){
			if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();e.stopPropagation();opts.step(e.key==='ArrowRight'?1:-1)}
		});
		el.isDragging=function(){return drag};
	}
	function setBar(el,v){el.style.setProperty('--v',(Math.max(0,Math.min(1,v||0))*100).toFixed(2)+'%')}
	function seekBars(){return [$('seek'),$('now-seek')]}
	function volBars(){return Array.prototype.slice.call(document.querySelectorAll('.vbar'))}
	seekBars().forEach(function(el){
		bar(el,{
			move:function(v){var d=E.duration();if(isFinite(d))paintTimes(v*d,d)},
			set:function(v){var d=E.duration();if(isFinite(d))E.seek(v*d)},
			step:function(s){E.seek(E.time()+s*5)}
		});
	});
	volBars().forEach(function(el){bar(el,{move:function(v){E.setVolume(v)},set:function(v){E.setVolume(v)},step:function(s){E.setVolume(S.volume+s*.05)}})});
	function paintTimes(t,d){
		var cur=fmt(t),left=isFinite(d)?'-'+fmt(Math.max(0,d-t)):'--:--';
		$('cur').textContent=cur;$('left').textContent=left;$('now-cur').textContent=cur;$('now-left').textContent=left;
	}
	function paintTime(){
		var t=E.time(),d=E.duration();
		seekBars().forEach(function(el){if(!el.isDragging())setBar(el,isFinite(d)&&d>0?t/d:0)});
		if(!seekBars().some(function(el){return el.isDragging()}))paintTimes(t,d);
	}
	function paintVolume(){
		var v=S.muted?0:S.volume,name=v<=0.001?'mute':v<.5?'volumeLow':'volume';
		volBars().forEach(function(el){setBar(el,v)});
		setIcon('mute',name);setIcon('now-mute',name);setIcon('now-volbtn',name);
		$('mute').title=$('now-mute').title=S.muted?'取消静音':'静音';
	}
	function paintModes(){
		var playing=E.playing()||S.loading;
		['toggle','now-toggle'].forEach(function(id){setIcon(id,playing?'pause':'play');$(id).title=playing?'暂停':'播放'});
		['shuffle','now-shuffle'].forEach(function(id){$(id).classList.toggle('on',S.shuffle);$(id).title=S.shuffle?'随机播放：开':'随机播放：关'});
		['repeat','now-repeat'].forEach(function(id){setIcon(id,S.repeat==='one'?'repeat1':'repeat');$(id).classList.toggle('on',S.repeat!=='off');$(id).title=S.repeat==='one'?'循环：单曲':S.repeat==='all'?'循环：全部':'循环：关闭'});
		$('dock').classList.toggle('is-loading',!!S.loading);
		$('dock').classList.toggle('is-playing',E.playing());
		$('now').classList.toggle('is-playing',E.playing());
		document.body.classList.toggle('is-playing-page',E.playing());
	}
	var lastCover='';
	function paintPlayer(){
		var t=E.track();
		$('dock').classList.toggle('empty',!t);
		$('title').textContent=t?t.title:'未在播放';
		$('meta').textContent=t?t.artist+(t.album?' — '+t.album:''):'选择一首歌开始播放';
		$('now-title').textContent=t?t.title:'未在播放';
		$('now-meta').textContent=t?t.artist+(t.album?' — '+t.album:''):'';
		var cover=t&&t.cover?t.cover:'';
		if(cover!==lastCover){
			lastCover=cover;
			['cover','now-cover'].forEach(function(id){var img=$(id);if(cover)img.src=cover;else img.removeAttribute('src')});
		}
		var fav=!!(t&&t.favorite);
		['fav','now-fav'].forEach(function(id){setIcon(id,fav?'heartFill':'heart');$(id).classList.toggle('on',fav);$(id).title=fav?'取消喜爱':'喜爱';$(id).disabled=!t||!!t.transient});
		['prev','next','toggle','now-prev','now-next','now-toggle','more','now-more','dock-lyrics','dock-queue'].forEach(function(id){$(id).disabled=!t});
		var cur=curId();
		library.querySelectorAll('.tr.playing').forEach(function(r){if(+r.dataset.source!==cur)r.classList.remove('playing')});
		if(cur)library.querySelectorAll('.tr[data-source="'+cur+'"]').forEach(function(r){r.classList.add('playing')});
		var isVideo=!!(t&&t.isVideo);
		$('now').classList.toggle('video',isVideo);
		$('now-video').classList.toggle('hidden',!isVideo);
		$('now-fit').classList.toggle('hidden',!isVideo);
		if(!host&&isVideo&&E.video.parentNode!==$('now-video'))$('now-video').appendChild(E.video);
		paintModes();paintTime();
		if(!t&&V.nowOpen)closeNow();
	}
	function applyPalette(){
		K.palette($('now-cover'),function(list){
			var cols=(list&&list.length?list:[[70,70,90],[40,40,60]]);
			while(cols.length<4)cols.push(cols[cols.length-1]);
			var dark=cols.map(function(c){var k=lum(c)>150?.45:lum(c)>90?.62:.82;return [Math.round(c[0]*k),Math.round(c[1]*k),Math.round(c[2]*k)]});
			dark.forEach(function(c,i){$('drawer-bg').style.setProperty('--c'+(i+1),'rgb('+c.join(',')+')')});
			bg.colors=dark;bg.dirty=true;
		});
	}
	var bg={colors:[[70,70,90],[40,40,60],[60,50,70],[30,30,40]],dirty:true,last:0,t:Math.random()*100};
	function drawBg(now){
		requestAnimationFrame(drawBg);
		if(!V.nowOpen||document.visibilityState==='hidden')return;
		var playing=E.playing();
		if(!bg.dirty&&(!playing||now-bg.last<50))return;
		if(playing)bg.t+=Math.min(0.1,(now-(bg.last||now))/1000);
		bg.last=now;bg.dirty=false;
		var cv=$('now-canvas'),x=cv.getContext('2d'),w=cv.width,h=cv.height,c=bg.colors,t=bg.t;
		x.fillStyle='rgb('+c[3].join(',')+')';x.fillRect(0,0,w,h);
		var blobs=[[.2,.25,.75,c[0],.11],[.85,.3,.7,c[1],.08],[.35,.95,.8,c[2],.09],[.9,.9,.6,c[0],.07]];
		blobs.forEach(function(b,i){
			var cx=(b[0]+Math.sin(t*b[4]*6+i*1.7)*.18)*w,cy=(b[1]+Math.cos(t*b[4]*5+i*2.3)*.18)*h,r=b[2]*Math.max(w,h);
			var g=x.createRadialGradient(cx,cy,0,cx,cy,r),col=b[3].join(',');
			g.addColorStop(0,'rgba('+col+',.95)');g.addColorStop(.55,'rgba('+col+',.45)');g.addColorStop(1,'rgba('+col+',0)');
			x.fillStyle=g;x.fillRect(0,0,w,h);
		});
	}
	requestAnimationFrame(drawBg);
	function lum(c){return .299*c[0]+.587*c[1]+.114*c[2]}
	$('now-cover').addEventListener('load',applyPalette);
	$('now-cover').addEventListener('error',function(){this.removeAttribute('src')});
	$('cover').addEventListener('error',function(){this.removeAttribute('src')});

	/* ---------- drawer & immersive ---------- */
	var lxDrawer=K.mountLyrics(E,$('d-lx'),{anchor:.32,onFree:function(f){$('d-follow').classList.toggle('hidden',!f)}});
	var lxNow=K.mountLyrics(E,$('now-lx'),{anchor:.36,onFree:function(f){$('now-follow').classList.toggle('hidden',!f)}});
	K.mountQueue(E,$('d-q'));
	K.mountQueue(E,$('now-q'));
	$('d-follow').onclick=function(){lxDrawer.follow()};
	$('now-follow').onclick=function(){lxNow.follow()};
	function setDrawer(mode){
		V.drawer=V.drawer===mode?'':mode;
		$('drawer').classList.toggle('hidden',!V.drawer);
		$('app').classList.toggle('with-drawer',!!V.drawer);
		$('d-lyrics').classList.toggle('on',V.drawer==='lyrics');
		$('d-queue').classList.toggle('on',V.drawer==='queue');
		$('d-lx').classList.toggle('hidden',V.drawer!=='lyrics');
		$('d-q').classList.toggle('hidden',V.drawer!=='queue');
		$('dock-lyrics').classList.toggle('on',V.drawer==='lyrics');
		$('dock-queue').classList.toggle('on',V.drawer==='queue');
		if(V.drawer==='queue')delete $('d-q').dataset.scrolled;
		if(!V.drawer)$('d-follow').classList.add('hidden');
		if(V.drawer)applyPalette();
	}
	function setSide(mode){
		V.side=mode;writePref('side',mode);
		var now=$('now');
		now.classList.toggle('side-none',mode==='none');
		$('now-lx').classList.toggle('hidden',mode!=='lyrics');
		$('now-q').classList.toggle('hidden',mode!=='queue');
		$('tab-lyrics').classList.toggle('on',mode==='lyrics');
		$('tab-queue').classList.toggle('on',mode==='queue');
		if(mode!=='lyrics')$('now-follow').classList.add('hidden');
		if(mode==='queue')delete $('now-q').dataset.scrolled;
	}
	function openNow(){
		if(!E.track())return;
		V.nowOpen=true;closeMenu();
		$('now').classList.remove('hidden');
		requestAnimationFrame(function(){$('now').classList.add('in')});
		setSide(V.side);applyPalette();paintPlayer();bg.dirty=true;
	}
	function handleNav(){
		if(!V.loaded||!E.nav)return;
		var n=E.takeNav(),t=E.track();
		if(V.nowOpen)closeNow();
		if(n.type==='current'){
			if(!t)return;
			var lib=V.bySource[t.sourceID];
			if(lib&&lib.albumKey)n={type:'album',key:lib.albumKey,select:t.sourceID};
			else{go('tracks');n={select:t.sourceID}}
		}
		if(n.type==='album'){V.stack=[];V.view='albums';V.detail=null;open({type:'album',key:n.key})}
		else if(n.type==='artist'){V.stack=[];V.view='artists';V.detail=null;open({type:'artist',name:n.name})}
		if(n.select){
			V.selected=n.select;
			var row=library.querySelector('.tr[data-source="'+n.select+'"]');
			if(row){row.classList.add('selected');row.scrollIntoView({block:'center'})}
		}
	}
	function closeNow(){
		if(!V.nowOpen)return;
		V.nowOpen=false;
		$('now').classList.remove('in');
		setTimeout(function(){if(!V.nowOpen)$('now').classList.add('hidden')},260);
		if(fsOn())toggleFs();
	}
	function fsOn(){
		try{if(host&&host.KodMusic.isFullscreen)return host.KodMusic.isFullscreen()}catch(e){}
		return !!document.fullscreenElement;
	}
	function toggleFs(){
		if(host&&host.KodMusic.fullscreen){
			host.KodMusic.fullscreen(window.frameElement).then(function(on){if(on&&!V.nowOpen)openNow();paintFs()}).catch(function(){toast('浏览器拒绝了全屏请求')});
			return;
		}
		var p=document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();
		if(p&&p.then)p.then(function(){if(document.fullscreenElement&&!V.nowOpen)openNow();paintFs()}).catch(function(){toast('浏览器拒绝了全屏请求')});
	}
	function paintFs(){var on=fsOn();setIcon('now-fs',on?'shrink':'expand');$('now-fs').title=on?'退出全屏':'全屏'}
	document.addEventListener('fullscreenchange',paintFs);
	if(host)try{host.document.addEventListener('fullscreenchange',paintFs)}catch(e){}

	function videoLoop(){
		requestAnimationFrame(videoLoop);
		var t=E.track();
		if(!host)return;
		var kod=host.KodMusic;if(!kod||!kod.placeVideo)return;
		if(!t||!t.isVideo||document.visibilityState==='hidden'){kod.placeVideo(window,null);return}
		var slot=V.nowOpen?$('now-video'):$('cover').parentNode;
		var fe=window.frameElement;if(!fe||!slot.offsetParent){kod.placeVideo(window,null);return}
		var fr=fe.getBoundingClientRect(),r=slot.getBoundingClientRect();
		kod.placeVideo(window,{x:fr.left+r.left,y:fr.top+r.top,w:r.width,h:r.height,fit:V.nowOpen?V.fit:'cover',radius:V.nowOpen?14:6});
	}
	function timeLoop(){requestAnimationFrame(timeLoop);if(E.playing()&&document.visibilityState!=='hidden')paintTime()}

	/* ---------- wiring ---------- */
	var ICON_IDS={prev:'prev',next:'next','now-prev':'prev','now-next':'next',shuffle:'shuffle','now-shuffle':'shuffle',more:'more','now-more':'more','dock-lyrics':'lyrics','dock-queue':'queue','tab-lyrics':'lyrics','tab-queue':'queue','now-close':'down','d-close':'close','nav-toggle':'sidebar',back:'left','list-add':'plus','now-fit':'image'};
	Object.keys(ICON_IDS).forEach(function(id){setIcon(id,ICON_IDS[id])});
	document.querySelectorAll('.nav-item[data-icon]').forEach(function(n){n.innerHTML=icon(n.dataset.icon)+'<span>'+n.textContent+'</span>'});
	$('scan').innerHTML=icon('refresh')+'<span>扫描资料库</span>';
	paintFs();

	document.querySelector('.nav').addEventListener('click',function(e){
		var v=e.target.closest('.nav-item[data-view]');if(v){go(v.dataset.view);return}
		var l=e.target.closest('[data-nav-list]');if(l){V.stack=[];V.view='lists';openList(l.dataset.navList);V.stack=[];paintNav();$('app').classList.remove('nav-open')}
	});
	$('list-add').onclick=function(e){e.stopPropagation();newList()};
	$('back').onclick=back;
	$('nav-toggle').onclick=function(){var app=$('app');if(window.innerWidth<820)app.classList.toggle('nav-open');else{app.classList.toggle('nav-hide');writePref('nav-hide',app.classList.contains('nav-hide')?'1':'')}};
	$('nav-mask').onclick=function(){$('app').classList.remove('nav-open')};
	if(readPref('nav-hide','')==='1')$('app').classList.add('nav-hide');
	var searchTimer=0;
	$('global-search').addEventListener('input',function(){
		var v=this.value.trim();clearTimeout(searchTimer);
		searchTimer=setTimeout(function(){
			if(v){if(V.view!=='search')V.prevView=V.view;V.q=v;V.view='search';V.detail=null;V.stack=[];render();library.scrollTop=0}
			else if(V.view==='search')go(V.prevView||'home');
		},180);
	});
	$('page-search').addEventListener('input',function(){var v=this.value.trim();clearTimeout(searchTimer);searchTimer=setTimeout(function(){V.pageQ=v;render()},150)});
	function scan(){
		var btn=$('scan');if(btn.disabled)return;
		btn.disabled=true;btn.classList.add('busy');btn.querySelector('span').textContent='正在扫描…';
		api('scan',{},true).then(function(d){
			$('scan-note').textContent='新增或更新 '+(d.processed||0)+' 首'+(d.skipped?'，跳过 '+d.skipped+' 个':'')+(d.more?'，还有更多，可再次扫描':'');
			return load();
		}).catch(function(e){toast(e.message)}).then(function(){btn.disabled=false;btn.classList.remove('busy');btn.querySelector('span').textContent='扫描资料库'});
	}
	$('scan').onclick=scan;

	$('toggle').onclick=$('now-toggle').onclick=function(){E.toggle()};
	$('prev').onclick=$('now-prev').onclick=function(){E.prev()};
	$('next').onclick=$('now-next').onclick=function(){E.next()};
	$('shuffle').onclick=$('now-shuffle').onclick=function(){E.setShuffle(!S.shuffle)};
	$('repeat').onclick=$('now-repeat').onclick=function(){E.cycleRepeat()};
	$('mute').onclick=$('now-mute').onclick=function(){E.toggleMute()};
	$('fav').onclick=$('now-fav').onclick=function(){toggleFav(E.track())};
	$('more').onclick=function(){trackMenu(this,E.track(),null)};
	$('now-more').onclick=function(){trackMenu(this,E.track(),null)};
	$('dock-open').onclick=openNow;
	$('dock-lyrics').onclick=function(){setDrawer('lyrics')};
	$('dock-queue').onclick=function(){setDrawer('queue')};
	$('d-lyrics').onclick=function(){if(V.drawer!=='lyrics')setDrawer('lyrics')};
	$('d-queue').onclick=function(){if(V.drawer!=='queue')setDrawer('queue')};
	$('d-close').onclick=function(){setDrawer(V.drawer)};
	$('now-close').onclick=closeNow;
	$('now-volbtn').onclick=function(e){e.stopPropagation();$('now-volpop').classList.toggle('hidden')};
	document.addEventListener('pointerdown',function(e){if(!e.target.closest('.now-volwrap'))$('now-volpop').classList.add('hidden')});
	$('now-volpop').addEventListener('wheel',function(e){e.preventDefault();E.setVolume(S.volume+(e.deltaY<0?.05:-.05))},{passive:false});
	$('now-fs').onclick=toggleFs;
	$('now-fit').onclick=function(){V.fit=V.fit==='cover'?'contain':'cover';$('now').classList.toggle('fit-cover',V.fit==='cover');this.title=V.fit==='cover'?'完整显示':'画面填充'};
	$('tab-lyrics').onclick=function(){setSide(V.side==='lyrics'?'none':'lyrics')};
	$('tab-queue').onclick=function(){setSide(V.side==='queue'?'none':'queue')};
	$('art-box').onclick=function(){if(!E.track()||E.track().isVideo)return;setSide(V.side==='none'?'lyrics':'none')};
	$('lyric-file').onchange=function(){
		var file=this.files&&this.files[0];this.value='';
		if(!file)return;
		if(file.size>500*1024){toast('歌词文件太大（上限 500 KB）');return}
		var reader=new FileReader();
		reader.onload=function(){E.importLyrics(String(reader.result||'')).then(function(){toast('已使用「'+file.name+'」')}).catch(function(e){toast(e.message)})};
		reader.onerror=function(){toast('无法读取这个文件')};
		reader.readAsText(file);
	};

	document.addEventListener('mousedown',function(e){if(!menu.contains(e.target))closeMenu()});
	library.addEventListener('scroll',closeMenu,{passive:true});
	window.addEventListener('resize',closeMenu);
	document.addEventListener('keydown',function(e){
		var tag=(e.target.tagName||'').toLowerCase(),typing=tag==='input'||tag==='textarea'||tag==='select'||e.target.isContentEditable;
		if(!$('modal').classList.contains('hidden'))return;
		if(e.key==='Escape'){if(!menu.classList.contains('hidden'))closeMenu();else if(V.nowOpen)closeNow();else if(V.drawer)setDrawer(V.drawer);else if(V.stack.length)back();return}
		if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='f'){e.preventDefault();$('global-search').focus();return}
		if(typing)return;
		if(e.key===' '&&tag!=='button'){e.preventDefault();E.toggle();return}
		if(e.target.getAttribute&&e.target.getAttribute('role')==='slider')return;
		if(e.key==='ArrowRight'&&!e.altKey){if(e.ctrlKey||e.metaKey)E.next();else E.seek(E.time()+5);e.preventDefault()}
		else if(e.key==='ArrowLeft'&&!e.altKey){if(e.ctrlKey||e.metaKey)E.prev();else E.seek(Math.max(0,E.time()-5));e.preventDefault()}
		else if(e.key==='ArrowUp'&&(e.ctrlKey||e.metaKey)){E.setVolume(S.volume+.1);e.preventDefault()}
		else if(e.key==='ArrowDown'&&(e.ctrlKey||e.metaKey)){E.setVolume(S.volume-.1);e.preventDefault()}
	});

	var off=E.on(function(type){
		if(type==='track'){paintPlayer();if(V.nowOpen||V.drawer)applyPalette()}
		else if(type==='state'){paintModes();paintTime()}
		else if(type==='time'){if(!E.playing())paintTime()}
		else if(type==='volume')paintVolume();
		else if(type==='favorite')paintPlayer();
		else if(type==='error'&&S.error)toast(S.error);
		else if(type==='nav')handleNav();
	},window);
	E.setUi(window);
	window.addEventListener('pagehide',function(){off();if(host)try{host.KodMusic.placeVideo(window,null)}catch(e){}if(E.ui===window)E.setUi(null)});

	setSide(V.side);
	paintPlayer();paintVolume();
	requestAnimationFrame(videoLoop);
	requestAnimationFrame(timeLoop);
	if(S.blocked)toast('浏览器阻止了自动播放，点按播放键继续');
	Promise.all([loadLists(),load()]).then(function(){if(V.view==='home'&&!V.detail)render();handleNav()});
}
})();
