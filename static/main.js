kodReady.push(function(){
	LNG.set(jsonDecode(urlDecode("{{LNG}}")));
	Events.bind('main.menu.loadBefore', function(listData){
		listData['music'] = {
			name: LNG['music.meta.name'],
			url: '{{pluginApi}}',
			target: 'inline',
			subMenu: '{{config.menuSubMenu}}',
			menuAdd: '{{config.menuAdd}}',
			icon: '{{pluginHost}}static/icon.svg'
		};
	});
	Router.mapIframe({page:'music', title:LNG['music.meta.name'], url:'{{pluginApi}}', ignoreLogin:false});
	if(!window.KodMusic && window.fetch && window.Promise){
		window.KodMusic = {engine:null, pending:[]};
		var host = '{{pluginHost}}static/', ver = '?v=0.5.6.21';
		var css = document.createElement('link');
		css.rel = 'stylesheet';
		css.href = host + 'mini.css' + ver;
		document.head.appendChild(css);
		var load = function(name, done){
			var s = document.createElement('script');
			s.src = host + name + ver;
			s.onload = done;
			document.body.appendChild(s);
		};
		load('engine.js', function(){ load('mini.js'); });
	}
	Events.bind('explorer.kodApp.before', function(appList){
		appList.push({
			name:'music',
			title:LNG['music.meta.name'],
			ext:'{{config.fileExt}}',
			icon:'{{pluginHost}}static/icon.svg',
			callback:function(path){
				var kod = window.KodMusic;
				if(!kod){ Router.go('music'); return; }
				if(kod.open) kod.open(path);
				else kod.pending.push(path);
			}
		});
	});
});
