<?php

class musicPlugin extends PluginBase {
	private $tableName = 'plugin_music_track';
	private $audioExt = array('mp3','flac','m4a','aac','ogg','oga','opus','wav','wma','webma');
	private $videoExt = array('webm','mp4','m4v','mov','mkv','avi');

	public function regist() {
		$this->hookRegist(array('user.commonJs.insert' => 'musicPlugin.echoJs'));
	}

	public function echoJs() {
		$this->echoFile('static/main.js');
	}

	public function onChangeStatus($status) {
		if ($status) $this->initTable();
		$this->updateTask($status ? 1 : 0);
	}

	public function onSetConfig($config) {
		$this->initTable();
		$this->updateTask(1);
		$config['scanBatch'] = max(1, min(200, intval(_get($config, 'scanBatch', 80))));
		$config['scanVideo'] = _get($config, 'scanVideo', 0) == 1 ? 1 : 0;
		$config['onlineLyrics'] = _get($config, 'onlineLyrics', 0) == 1 ? 1 : 0;
		return $config;
	}

	public function onUninstall() {
		$task = Model('SystemTask')->findByKey('event', $this->pluginName.'Plugin.task');
		if ($task) Model('SystemTask')->remove($task['id'], true);
	}

	public function index() {
		$this->initTable();
		include $this->pluginPath.'static/page.html';
	}

	public function initTable() {
		$tables = Model()->db()->getTables();
		if (!in_array($this->tableName, $tables, true) || !in_array('plugin_music_list', $tables, true)) {
			$file = stristr($GLOBALS['config']['database']['DB_TYPE'], 'sqlite') ? 'sqlite.sql' : 'mysql.sql';
			foreach (sqlSplit(file_get_contents($this->pluginPath.'lib/data/'.$file)) as $sql) {
				if (trim($sql)) {
					try { Model()->db()->execute($sql); } catch (Throwable $e) {}
				}
			}
		}
		try {
			$sql = stristr($GLOBALS['config']['database']['DB_TYPE'], 'sqlite')
				? 'ALTER TABLE "plugin_music_track" ADD COLUMN "lyricOffset" integer NOT NULL DEFAULT 0'
				: 'ALTER TABLE `plugin_music_track` ADD COLUMN `lyricOffset` int NOT NULL DEFAULT 0';
			Model()->db()->execute($sql);
		} catch (Throwable $e) {}
		try {
			$sql = stristr($GLOBALS['config']['database']['DB_TYPE'], 'sqlite')
				? 'ALTER TABLE "plugin_music_track" ADD COLUMN "favorite" integer NOT NULL DEFAULT 0'
				: 'ALTER TABLE `plugin_music_track` ADD COLUMN `favorite` tinyint NOT NULL DEFAULT 0';
			Model()->db()->execute($sql);
		} catch (Throwable $e) {}
		return true;
	}

	private function postAllowed() {
		if (_get($_SERVER, 'REQUEST_METHOD', '') !== 'POST' || !KodUser::id()) return false;
		$token = (string)_get($this->in, 'CSRF_TOKEN', '');
		$cookie = (string)Cookie::get('CSRF_TOKEN');
		return $token !== '' && $cookie !== '' && hash_equals($cookie, $token);
	}

	public function task() {
		$config = $this->getConfig();
		return $this->scanSources(null, max(1, min(200, intval(_get($config, 'scanBatch', 80)))));
	}

	public function scan() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		if (!KodUser::id()) return show_json(LNG('explorer.noPermissionAction'), false);
		$config = $this->getConfig();
		return show_json($this->scanSources(KodUser::id(), max(1, min(200, intval(_get($config, 'scanBatch', 80))))));
	}

	public function collection() {
		if (!KodUser::id()) return show_json(LNG('explorer.noPermissionAction'), false);
		$this->initTable();
		$q = trim((string)_get($this->in, 'q', ''));
		$rows = Model($this->tableName)->where(array('userID' => KodUser::id()))->order('artist asc,album asc,trackNo asc,title asc')->select();
		$albums = array();
		foreach ((array)$rows as $row) {
			$title = $row['title'] !== '' ? $row['title'] : $row['name'];
			$artist = $row['artist'] !== '' ? $row['artist'] : LNG('music.lib.unknownArtist');
			$album = $row['album'] !== '' ? $row['album'] : LNG('music.lib.unknownAlbum');
			if ($q !== '' && stripos($title.$artist.$album.$row['name'], $q) === false) continue;
			$key = strtolower($artist.'|'.$album.'|'.$row['year']);
			$cover = $this->signedUrl('cover', $row['sourceID']);
			if (!isset($albums[$key])) {
				$albums[$key] = array('key' => $key, 'artist' => $artist, 'album' => $album, 'year' => $row['year'], 'cover' => $cover, 'tracks' => array(), 'added' => intval($row['createTime']));
			}
			if (intval($row['createTime']) > intval($albums[$key]['added'])) $albums[$key]['added'] = intval($row['createTime']);
			$albums[$key]['tracks'][] = $this->trackOut($row, $title, $artist, $album, $cover);
		}
		$list = array_values($albums);
		return show_json(array(
			'albums' => $list,
			'total' => array_sum(array_map(function($a) {return count($a['tracks']);}, $list)),
		));
	}

	public function stream() {
		$sourceID = intval(Input::get('sourceid', 'require'));
		$this->assertTrack($sourceID);
		return IO::fileOut(KodIO::make($sourceID));
	}

	public function cover() {
		$sourceID = intval(Input::get('sourceid', 'require'));
		$source = $this->assertTrack($sourceID);
		$file = $this->coverFile($sourceID);
		$offline = $this->offlineCover($sourceID);
		if ($offline) return IO::fileOut($offline);
		if (!is_file($file) || filesize($file) < 32) $this->collectCover($source);
		if (is_file($file) && filesize($file) >= 32) {
			$this->archiveCover($sourceID, $file);
			return IO::fileOut($file);
		}
		$track = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->find();
		$artist = $track ? $track['artist'] : '';
		$album = $track ? $track['album'] : $source['name'];
		header('Content-Type: image/svg+xml; charset=utf-8');
		header('Cache-Control: private, max-age=86400');
		echo $this->patternCover($artist, $album !== '' ? $album : $source['name']);
	}

	public function lyrics() {
		$sourceID = intval(_get($this->in, 'sourceid', _get($this->in, 'sourceID', 0)));
		if (!$sourceID) $sourceID = intval(Input::get('sourceid', 'require'));
		$source = $this->assertTrack($sourceID);
		$track = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->find();
		$offset = intval($track ? _get($track, 'lyricOffset', 0) : 0);
		$saved = $this->offlineLyrics($sourceID);
		$cache = $this->lyricsFile($sourceID);
		$cached = null;
		if (is_file($cache)) {
			$cached = json_decode(@file_get_contents($cache), true);
			if (!is_array($cached) || ($cached['text'] === '' && empty($cached['synced']))) $cached = null;
		}
		$data = null;
		if ($saved && $this->hasWordTimes($saved)) $data = $saved;
		elseif ($cached && $this->hasWordTimes($cached)) $data = $cached;
		elseif ($saved) $data = $saved;
		elseif ($cached) $data = $cached;
		if (!$data) {
			$data = $this->findLyrics($source, $track);
			if (($data['text'] !== '' || $data['synced'])) {
				$dir = $this->lyricsDir();
				if (!is_dir($dir)) @mkdir($dir, 0755, true);
				@file_put_contents($cache, json_encode($data, JSON_UNESCAPED_UNICODE));
				$this->archiveLyrics($sourceID, $data);
			} elseif ($cached) $data = $cached;
			elseif ($saved) $data = $saved;
		}
		if (!$offset && !empty($data['offset'])) $offset = intval($data['offset']);
		$data['lyricOffset'] = $offset;
		return show_json($data);
	}

	public function lyricOffset() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		$this->assertTrack($sourceID);
		$offset = max(-15000, min(15000, intval(_get($this->in, 'offset', 0))));
		Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->save(array('lyricOffset' => $offset, 'modifyTime' => time()));
		$this->writeOffset($sourceID, $offset);
		return show_json(array('lyricOffset' => $offset));
	}

	public function lyricImport() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		$this->assertTrack($sourceID);
		$text = $this->lyricText(_get($this->in, 'text', ''));
		if ($text === '' || strlen($text) > 500000) return show_json('歌词文件无法读取', false);
		$data = $this->detectAndParse($text);
		if ($data['text'] === '' && !$data['synced']) return show_json('没有识别出歌词。支持 LRC、逐词 LRC、SRT、WebVTT、ASS/SSA、TTML、网易云 YRC、JSON', false);
		$dir = $this->lyricsDir();
		if (!is_dir($dir)) @mkdir($dir, 0755, true);
		@file_put_contents($this->lyricsFile($sourceID), json_encode($data, JSON_UNESCAPED_UNICODE));
		$this->archiveLyrics($sourceID, $data);
		$track = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->find();
		$data['lyricOffset'] = intval($track ? _get($track, 'lyricOffset', 0) : 0);
		return show_json($data);
	}

	public function playlists() {
		if (!KodUser::id()) return show_json(LNG('explorer.noPermissionAction'), false);
		$this->initTable();
		$rows = Model('plugin_music_list')->where(array('userID' => KodUser::id()))->order('id desc')->select();
		$list = array();
		foreach ((array)$rows as $row) {
			$count = Model('plugin_music_list_item')->where(array('listID' => $row['id'], 'userID' => KodUser::id()))->count();
			$list[] = array('id' => intval($row['id']), 'name' => $row['name'], 'count' => intval($count));
		}
		return show_json(array('lists' => $list));
	}

	public function playlist() {
		if (!KodUser::id()) return show_json(LNG('explorer.noPermissionAction'), false);
		$this->initTable();
		$id = intval(_get($this->in, 'id', 0));
		$list = Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->find();
		if (!$list) return show_json('歌单不存在', false);
		$items = Model('plugin_music_list_item')->where(array('listID' => $id, 'userID' => KodUser::id()))->order('sort asc,id asc')->select();
		$tracks = array();
		foreach ((array)$items as $item) {
			$row = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => intval($item['sourceID'])))->find();
			if (!$row) continue;
			$title = $row['title'] !== '' ? $row['title'] : $row['name'];
			$artist = $row['artist'] !== '' ? $row['artist'] : LNG('music.lib.unknownArtist');
			$album = $row['album'] !== '' ? $row['album'] : LNG('music.lib.unknownAlbum');
			$tracks[] = $this->trackOut($row, $title, $artist, $album, $this->signedUrl('cover', $row['sourceID']));
		}
		return show_json(array('id' => $id, 'name' => $list['name'], 'tracks' => $tracks));
	}

	public function playlistSave() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$this->initTable();
		$name = $this->clip(_get($this->in, 'name', ''), 40);
		if ($name === '') return show_json('请填写歌单名', false);
		try {
			$model = Model('plugin_music_list');
			$model->setDataAuto(false);
			$id = $model->add(array('userID' => KodUser::id(), 'name' => $name, 'createTime' => time()));
		} catch (Throwable $e) {
			return show_json('歌单没有保存成功', false);
		}
		if (!$id) return show_json('歌单没有保存成功', false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		if ($sourceID) $this->addListItem($id, $sourceID);
		return show_json(array('id' => intval($id), 'name' => $name));
	}

	public function playlistRename() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$id = intval(_get($this->in, 'id', 0));
		$name = $this->clip(_get($this->in, 'name', ''), 40);
		if ($name === '') return show_json('请填写歌单名', false);
		$list = Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->find();
		if (!$list) return show_json('歌单不存在', false);
		Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->save(array('name' => $name));
		return show_json(array('id' => $id, 'name' => $name));
	}

	public function playlistAdd() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$this->initTable();
		$id = intval(_get($this->in, 'id', 0));
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		$list = Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->find();
		if (!$list) return show_json('歌单不存在', false);
		$this->assertTrack($sourceID);
		$this->addListItem($id, $sourceID);
		return show_json(array('id' => $id));
	}

	public function playlistSort() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$this->initTable();
		$id = intval(_get($this->in, 'id', 0));
		$list = Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->find();
		if (!$list) return show_json('歌单不存在', false);
		$ids = array_slice(array_filter(array_map('intval', explode(',', (string)_get($this->in, 'order', '')))), 0, 5000);
		foreach ($ids as $sort => $sourceID) {
			Model('plugin_music_list_item')->where(array('listID' => $id, 'userID' => KodUser::id(), 'sourceID' => $sourceID))->save(array('sort' => $sort + 1));
		}
		return show_json(array('id' => $id));
	}

	public function playlistRemove() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$this->initTable();
		$id = intval(_get($this->in, 'id', 0));
		$list = Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->find();
		if (!$list) return show_json('歌单不存在', false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		if ($sourceID) {
			Model('plugin_music_list_item')->where(array('listID' => $id, 'userID' => KodUser::id(), 'sourceID' => $sourceID))->delete();
		} else {
			Model('plugin_music_list_item')->where(array('listID' => $id, 'userID' => KodUser::id()))->delete();
			Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->delete();
		}
		return show_json(array('id' => $id));
	}

	private function scanSources($userID, $limit) {
		$this->initTable();
		$extensions = $this->scanExtensions();
		$where = array('targetType' => 1, 'isFolder' => 0, 'isDelete' => 0, 'fileType' => array('in', $extensions));
		if ($userID !== null) $where['targetID'] = intval($userID);
		$sources = Model('Source')->where($where)->order('modifyTime desc')->select();
		$processed = 0; $skipped = 0;
		foreach ((array)$sources as $source) {
			$existing = Model($this->tableName)->where(array('userID' => $source['targetID'], 'sourceID' => $source['sourceID']))->find();
			if ($existing && intval($existing['sourceModifyTime']) === intval($source['modifyTime'])) {
				$skipped++;
				continue;
			}
			$this->indexSource($source, $existing);
			$processed++;
			if ($processed >= $limit) break;
		}
		return array('processed' => $processed, 'skipped' => $skipped, 'removed' => $this->cleanupMissing($userID), 'total' => count((array)$sources));
	}

	private function scanVideoOn($config = null) {
		if ($config === null) $config = $this->getConfig();
		return _get($config, 'scanVideo', 0) == 1;
	}

	private function scanExtensions($raw = null) {
		$config = $this->getConfig();
		if ($raw === null) $raw = _get($config, 'fileExt', '');
		if (is_array($raw)) $raw = implode(',', $raw);
		$fromConfig = array_filter(array_map('strtolower', preg_split('/[\s,;]+/', (string)$raw)));
		$list = array_merge($this->audioExt, $fromConfig);
		if ($this->scanVideoOn($config)) $list = array_merge($list, $this->videoExt);
		$out = array();
		foreach ($list as $ext) {
			$ext = strtolower(ltrim((string)$ext, '.'));
			if ($ext !== '') $out[$ext] = $ext;
		}
		if (!$this->scanVideoOn($config)) {
			foreach ($this->videoExt as $ext) unset($out[$ext]);
		}
		return array_values($out);
	}

	private function indexSource($source, $existing) {
		$meta = array('title' => '', 'artist' => '', 'album' => '', 'genre' => '', 'year' => '', 'trackNo' => 0, 'duration' => 0, 'hasCover' => 0);
		$video = $this->isVideo($source['fileType']);
		$fromName = $this->parseMediaName($source['name']);
		if (!$video) {
			try {
				$info = GetInfo::get(KodIO::make(intval($source['sourceID'])));
				$more = (array)_get($info, 'fileInfoMore', array());
				$tags = (array)_get($more, 'tags', array());
				$meta['title'] = $this->clip(_get($tags, 'title', ''), 250);
				$meta['artist'] = $this->clip(_get($tags, 'artist', ''), 250);
				$meta['album'] = $this->clip(_get($tags, 'album', ''), 250);
				$meta['genre'] = $this->clip(_get($tags, 'genre', ''), 120);
				$meta['year'] = $this->clip(_get($tags, 'year', ''), 16);
				$meta['trackNo'] = intval(_get($tags, 'track', 0));
				$meta['duration'] = intval(_get($more, 'playtime', 0));
			} catch (Throwable $e) {}
		}
		if ($meta['title'] === '' || $meta['title'] === $source['name']) $meta['title'] = $fromName['title'];
		if ($meta['artist'] === '') $meta['artist'] = $fromName['artist'];
		if ($this->collectCover($source)) $meta['hasCover'] = 1;
		if ($meta['title'] === '') $meta['title'] = $fromName['title'] !== '' ? $fromName['title'] : $source['name'];
		$data = array_merge($meta, array(
			'userID' => intval($source['targetID']),
			'sourceID' => intval($source['sourceID']),
			'fileID' => intval($source['fileID']),
			'name' => $source['name'],
			'ext' => strtolower($source['fileType']),
			'size' => intval($source['size']),
			'sourceModifyTime' => intval($source['modifyTime']),
			'modifyTime' => time(),
		));
		$model = Model($this->tableName); $model->setDataAuto(false);
		if ($existing) $model->where(array('id' => $existing['id']))->save($data);
		else {
			$data['createTime'] = time();
			$model->add($data);
		}
	}

	private function collectCover($source) {
		$dir = $this->coverDir();
		if (!is_dir($dir)) @mkdir($dir, 0755, true);
		$target = $this->coverFile(intval($source['sourceID']), intval(_get($source, 'targetID', KodUser::id())));
		$picture = $this->isVideo($source['fileType']) ? '' : $this->embeddedPicture($source);
		if ($picture) {
			@file_put_contents($target, $picture);
			return true;
		}
		$folder = $this->folderCover($source);
		if ($folder) {
			@file_put_contents($target, $folder);
			return true;
		}
		return false;
	}

	private function embeddedPicture($source) {
		try {
			$raw = $this->analyzeRaw($source);
			$picture = _get($raw, 'comments.picture.0.data', '');
			if (is_string($picture) && strlen($picture) > 64) return $picture;
		} catch (Throwable $e) {}
		return '';
	}

	private function folderCover($source) {
		$parentID = intval(_get($source, 'parentID', 0));
		if (!$parentID) return '';
		$names = array('cover','folder','album','front','artwork','albumart','folder-large');
		$rows = Model('Source')->where(array(
			'parentID' => $parentID, 'targetType' => 1, 'targetID' => intval($source['targetID']),
			'isFolder' => 0, 'isDelete' => 0, 'fileType' => array('in', array('jpg','jpeg','png','webp','bmp')),
		))->select();
		foreach ((array)$rows as $row) {
			$base = strtolower(preg_replace('/\.[^.]+$/', '', $row['name']));
			if (!in_array($base, $names, true)) continue;
			try {
				$data = IO::getContent(KodIO::make(intval($row['sourceID'])));
				if (is_string($data) && strlen($data) > 64) return $data;
			} catch (Throwable $e) {}
		}
		return '';
	}

	private function findLyrics($source, $track) {
		$empty = array('text' => '', 'synced' => array(), 'source' => '');
		$lrc = $this->sidecarLyrics($source, $track);
		if ($lrc) {
			$parsed = $this->detectAndParse($lrc);
			if ($parsed['text'] !== '' || $parsed['synced']) return $parsed;
		}
		if (!$this->isVideo(_get($source, 'fileType', ''))) {
			$embedded = $this->embeddedLyrics($source);
			if ($embedded) return $this->parseLyrics($embedded, 'tag');
		}
		if (_get($this->getConfig(), 'onlineLyrics', 0) == 1) {
			$remote = $this->lrclibLyrics($track, $source);
			if ($remote) return $remote;
		}
		return $empty;
	}

	private function sidecarLyrics($source, $track = null) {
		$parentID = intval(_get($source, 'parentID', 0));
		if (!$parentID) return '';
		$stem = strtolower(preg_replace('/\.[^.]+$/', '', $source['name']));
		$parsed = $this->parseMediaName($source['name']);
		$title = strtolower($parsed['title']);
		if ($track && _get($track, 'title', '') !== '') $title = strtolower($track['title']);
		$rows = Model('Source')->where(array(
			'parentID' => $parentID, 'targetType' => 1, 'targetID' => intval($source['targetID']),
			'isFolder' => 0, 'isDelete' => 0, 'fileType' => array('in', array('lrc','txt','srt','vtt','ass','ssa','ttml','xml','json','yrc','lrcx')),
		))->select();
		foreach ((array)$rows as $row) {
			$base = strtolower(preg_replace('/\.[^.]+$/', '', $row['name']));
			$hit = $base === $stem || $base === $stem.'.cn' || $base === $stem.'.zh' || ($title !== '' && ($base === $title || strpos($stem, $base) !== false || strpos($base, $title) !== false));
			if (!$hit) continue;
			try {
				$text = IO::getContent(KodIO::make(intval($row['sourceID'])));
				if (is_string($text) && trim($text) !== '') return $text;
			} catch (Throwable $e) {}
		}
		return '';
	}

	private function embeddedLyrics($source) {
		try {
			$raw = $this->analyzeRaw($source);
			foreach (array('unsyncedlyrics','lyrics','unsynchronised_lyric') as $key) {
				$value = _get($raw, 'comments.'.$key.'.0', '');
				if (is_string($value) && trim($value) !== '') return $value;
			}
		} catch (Throwable $e) {}
		return '';
	}

	private function lrclibLyrics($track, $source = null) {
		$tried = 0;
		foreach ($this->lyricQueries($track, $source) as $pair) {
			if ($tried >= 3) break;
			$tried++;
			$params = $pair[0] !== ''
				? array('track_name' => $pair[1], 'artist_name' => $pair[0])
				: array('q' => $pair[1]);
			$hit = $this->pickLyricsRecord($this->httpGet('https://lrclib.net/api/search?'.http_build_query($params)));
			if ($hit) return $hit;
		}
		return null;
	}

	private function lyricQueries($track, $source = null) {
		$name = $track ? _get($track, 'name', '') : '';
		if ($name === '' && $source) $name = _get($source, 'name', '');
		$parsed = $this->parseMediaName($name);
		$title = trim($track ? _get($track, 'title', '') : '');
		$artist = trim($track ? _get($track, 'artist', '') : '');
		if ($title === '' || $title === $name) $title = $parsed['title'];
		if ($artist === '') $artist = $parsed['artist'];
		$queries = array();
		$add = function($who, $what) use (&$queries) {
			$who = trim((string)$who); $what = trim((string)$what);
			if ($what === '') return;
			$key = strtolower($who.'|'.$what);
			if (!isset($queries[$key])) $queries[$key] = array($who, $what);
		};
		$add($artist, $title);
		$add($parsed['artist'], $parsed['title']);
		if ($parsed['artist'] !== '' && preg_match('/^([\p{Han}]+)/u', $parsed['artist'], $m)) $add($m[1], $parsed['title'] !== '' ? $parsed['title'] : $title);
		if ($title !== '') $add('', $title);
		return array_values($queries);
	}

	private function pickLyricsRecord($raw) {
		$list = json_decode((string)$raw, true);
		if (!is_array($list) || !$list) return null;
		if (isset($list['plainLyrics']) || isset($list['syncedLyrics'])) $list = array($list);
		$best = null;
		foreach ($list as $row) {
			if (!is_array($row)) continue;
			$synced = trim((string)_get($row, 'syncedLyrics', ''));
			$plain = trim((string)_get($row, 'plainLyrics', ''));
			if ($synced === '' && $plain === '') continue;
			$parsed = $this->parseLyrics($synced !== '' ? $synced : $plain, 'lrclib');
			$parsed = $this->mergeWordSync($parsed, (string)_get($row, 'lyricsfile', ''));
			if ($parsed['text'] === '' && $plain !== '') $parsed['text'] = $plain;
			if ($parsed['text'] === '') continue;
			if ($this->hasWordTimes($parsed)) return $parsed;
			if ($synced !== '' && !$best) $best = $parsed;
		}
		return $best;
	}

	private function hasWordTimes($data) {
		foreach ((array)_get($data, 'synced', array()) as $row) {
			if (!empty($row['words'])) return true;
		}
		return false;
	}

	private function mergeWordSync($parsed, $yaml) {
		$fileLines = $this->parseLyricsfile($yaml);
		if (!$fileLines || empty($parsed['synced'])) return $parsed;
		foreach ($parsed['synced'] as &$row) {
			$best = null;
			$bestDiff = 0.8;
			foreach ($fileLines as $line) {
				if (empty($line['words'])) continue;
				$diff = abs($line['t'] - $row['t']);
				if ($diff < $bestDiff) { $best = $line; $bestDiff = $diff; }
			}
			if ($best) $row['words'] = $best['words'];
		}
		unset($row);
		return $parsed;
	}

	private function parseLyricsfile($yaml) {
		$yaml = str_replace(array("\r\n", "\r"), "\n", (string)$yaml);
		if (strpos($yaml, 'time:') === false) return array();
		$out = array();
		$cur = null;
		$mode = '';
		foreach (explode("\n", $yaml) as $raw) {
			$indent = strlen($raw) - strlen(ltrim($raw));
			$trim = ltrim($raw);
			if ($indent <= 3 && preg_match('/^- time:\s*([0-9.]+)/', $trim, $m)) {
				if ($cur) $out[] = $cur;
				$cur = array('t' => $this->lyricSeconds($m[1]), 'words' => array());
				$mode = 'line';
				continue;
			}
			if (!$cur) continue;
			if ($indent <= 5 && $trim === 'words:') { $mode = 'words'; continue; }
			if ($mode === 'words' && $indent >= 4 && preg_match('/^- time:\s*([0-9.]+)/', $trim, $m)) {
				$cur['words'][] = array('t' => $this->lyricSeconds($m[1]), 'w' => '');
				continue;
			}
			if ($mode === 'words' && $cur['words'] && preg_match('/^text:\s*(.*)$/', $trim, $m)) {
				$cur['words'][count($cur['words']) - 1]['w'] = $this->yamlString($m[1]);
				continue;
			}
		}
		if ($cur && $cur['words']) $out[] = $cur;
		return $out;
	}

	private function lyricSeconds($value) {
		$value = floatval($value);
		return $value > 1000 ? $value / 1000 : $value;
	}

	private function yamlString($value) {
		$value = trim((string)$value);
		if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
			$quote = $value[0];
			$value = substr($value, 1, -1);
			if ($quote === '"') $value = stripcslashes($value);
		}
		return $value;
	}

	private function parseMediaName($name) {
		$stem = preg_replace('/\.[^.]+$/', '', (string)$name);
		$stem = preg_replace('/\[[^\]]*\]/', ' ', $stem);
		$stem = preg_replace('/【[^】]*】/', ' ', $stem);
		$stem = preg_replace('/\([^)]*(official|mv|audio|lyric|video|hq|hd|4k)[^)]*\)/i', ' ', $stem);
		$stem = preg_replace('/官方完整版MV|官方MV|完整版MV|高清MV|完整版/u', ' ', $stem);
		$stem = trim(preg_replace('/\s+/', ' ', $stem));
		if (preg_match('/^(.+?)\s+-\s+(.+)$/', $stem, $m)) {
			return array('artist' => trim($m[1]), 'title' => trim($m[2]));
		}
		return array('artist' => '', 'title' => $stem);
	}

	private function lyricText($text) {
		$text = (string)$text;
		if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) $text = substr($text, 3);
		if ($text === '' || strncmp($text, 'krc', 3) === 0) return '';
		if (preg_match('/[\x00-\x08]/', substr($text, 0, 240))) return '';
		if (function_exists('mb_detect_encoding')) {
			$enc = mb_detect_encoding($text, array('UTF-8', 'GB18030', 'BIG5'), true);
			if ($enc && $enc !== 'UTF-8') $text = mb_convert_encoding($text, 'UTF-8', $enc);
		}
		return $text;
	}

	private function emptyLyrics() {
		return array('text' => '', 'synced' => array(), 'source' => '');
	}

	private function packLyrics($synced, $source) {
		$synced = array_values(array_filter($synced, function($row) {return trim($row['line']) !== '';}));
		usort($synced, function($a, $b) {return $a['t'] == $b['t'] ? 0 : ($a['t'] < $b['t'] ? -1 : 1);});
		if (!$synced) return $this->emptyLyrics();
		return array(
			'text' => implode("\n", array_map(function($row) {return $row['line'];}, $synced)),
			'synced' => $synced,
			'source' => $source,
		);
	}

	private function clockToSeconds($value) {
		$value = trim(str_replace(',', '.', (string)$value));
		if ($value === '') return 0;
		if (substr($value, -2) === 'ms') return floatval($value) / 1000;
		if (substr($value, -1) === 's' && strpos($value, ':') === false) return floatval($value);
		if (preg_match('/^(?:(\d+):)?(\d+):(\d+(?:\.\d+)?)$/', $value, $m)) return intval($m[1]) * 3600 + intval($m[2]) * 60 + floatval($m[3]);
		if (preg_match('/^(\d+):(\d+(?:\.\d+)?)$/', $value, $m)) return intval($m[1]) * 60 + floatval($m[2]);
		return floatval($value);
	}

	private function detectAndParse($text) {
		$text = $this->lyricText($text);
		$trim = ltrim($text);
		if ($trim === '') return $this->emptyLyrics();
		if ($trim[0] === '{' || $trim[0] === '[') {
			$json = json_decode($text, true);
			if (is_array($json)) {
				$parsed = $this->parseJsonLyrics($json);
				if ($parsed['text'] !== '' || $parsed['synced']) return $parsed;
			}
		}
		if (stripos($trim, '<tt') !== false || (stripos($trim, '<p') !== false && stripos($text, 'begin=') !== false)) {
			$parsed = $this->parseTtml($text);
			if ($parsed['synced']) return $parsed;
		}
		if (preg_match('/^WEBVTT/i', $trim) || preg_match('/\d{2}:\d{2}[.,]\d{1,3}\s*-->/', $text)) {
			$parsed = $this->parseSrt($text);
			if ($parsed['synced']) return $parsed;
		}
		if (stripos($text, 'Dialogue:') !== false) {
			$parsed = $this->parseAss($text);
			if ($parsed['synced']) return $parsed;
		}
		if (preg_match('/\[\d+,\d+\]/', $text) && preg_match('/\(\d+,\d+,/', $text)) {
			$parsed = $this->parseYrc($text);
			if ($parsed['synced']) return $parsed;
		}
		if (strpos($text, 'words:') !== false && preg_match('/^- time:/m', $text)) {
			$lines = $this->parseLyricsfile($text);
			$synced = array();
			foreach ($lines as $line) {
				$plain = '';
				foreach ($line['words'] as $word) $plain .= $word['w'];
				if ($plain === '') continue;
				$synced[] = array('t' => $line['t'], 'line' => $plain, 'words' => $line['words']);
			}
			if ($synced) return $this->packLyrics($synced, 'lyricsfile');
		}
		return $this->parseLyrics($text, 'lrc');
	}

	private function parseJsonLyrics($json) {
		if (isset($json['lyrics']) && is_array($json['lyrics'])) $json = $json['lyrics'];
		$synced = array();
		if (isset($json['synced']) && is_array($json['synced'])) {
			foreach ($json['synced'] as $row) {
				if (!is_array($row)) continue;
				$words = array();
				foreach ((array)_get($row, 'words', array()) as $word) {
					if (!is_array($word) || _get($word, 'w', '') === '') continue;
					$words[] = array('t' => floatval(_get($word, 't', 0)), 'w' => (string)$word['w']);
				}
				$synced[] = array('t' => floatval(_get($row, 't', 0)), 'line' => (string)_get($row, 'line', ''), 'words' => $words);
			}
			return $this->packLyrics($synced, 'json');
		}
		$lines = isset($json['lines']) && is_array($json['lines']) ? $json['lines'] : (isset($json[0]['words']) ? $json : array());
		foreach ($lines as $line) {
			if (!is_array($line)) continue;
			if (isset($line['startTimeMs'])) $t = floatval($line['startTimeMs']) / 1000;
			elseif (isset($line['time']) && floatval($line['time']) > 1000) $t = floatval($line['time']) / 1000;
			else $t = floatval(_get($line, 't', _get($line, 'time', 0)));
			$words = array();
			$syllables = isset($line['syllables']) && is_array($line['syllables']) ? $line['syllables'] : (isset($line['words']) && is_array($line['words']) && isset($line['words'][0]) && is_array($line['words'][0]) ? $line['words'] : array());
			foreach ($syllables as $word) {
				if (!is_array($word)) continue;
				$w = (string)_get($word, 'text', _get($word, 'word', _get($word, 'w', _get($word, 'chars', ''))));
				if ($w === '') continue;
				$wt = isset($word['startTimeMs']) ? floatval($word['startTimeMs']) / 1000 : floatval(_get($word, 't', $t));
				$words[] = array('t' => $wt, 'w' => $w);
			}
			$plain = $words ? implode('', array_map(function($word) {return $word['w'];}, $words)) : (string)_get($line, 'words', _get($line, 'text', _get($line, 'line', '')));
			if (is_array($plain)) $plain = '';
			if (trim($plain) === '') continue;
			$synced[] = array('t' => $t, 'line' => trim($plain), 'words' => $words);
		}
		return $this->packLyrics($synced, 'json');
	}

	private function parseTtml($text) {
		$synced = array();
		if (!class_exists('DOMDocument')) return $this->emptyLyrics();
		$dom = new DOMDocument();
		$errors = libxml_use_internal_errors(true);
		$ok = @$dom->loadXML($text, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($errors);
		if (!$ok) return $this->emptyLyrics();
		$xp = new DOMXPath($dom);
		$nodes = $xp->query('//*[local-name()="p"]');
		if (!$nodes) return $this->emptyLyrics();
		foreach ($nodes as $node) {
			$t = $this->clockToSeconds($node->getAttribute('begin'));
			$spans = $xp->query('.//*[local-name()="span"]', $node);
			$words = array();
			$line = '';
			if ($spans && $spans->length) {
				foreach ($spans as $span) {
					$w = $span->textContent;
					if ($w === '') continue;
					$begin = $span->getAttribute('begin');
					$words[] = array('t' => $begin !== '' ? $this->clockToSeconds($begin) : $t, 'w' => $w);
					$line .= $w;
				}
			} else $line = $node->textContent;
			$line = trim(preg_replace('/\s+/', ' ', $line));
			if ($line === '') continue;
			$synced[] = array('t' => $t, 'line' => $line, 'words' => $words);
		}
		return $this->packLyrics($synced, 'ttml');
	}

	private function parseSrt($text) {
		$text = preg_replace('/^WEBVTT[^\n]*\n+/i', '', $text);
		$synced = array();
		if (!preg_match_all('/((?:\d{1,2}:)?\d{2}:\d{2}[.,]\d{1,3})\s*-->\s*[^\n]*\n([\s\S]*?)(?=\n\s*\n|\z)/', $text, $all, PREG_SET_ORDER)) return $this->emptyLyrics();
		foreach ($all as $cue) {
			$line = trim(preg_replace('/<[^>]+>/', '', str_replace("\n", ' ', $cue[2])));
			if ($line === '' || strtoupper($line) === 'WEBVTT') continue;
			$synced[] = array('t' => $this->clockToSeconds($cue[1]), 'line' => $line, 'words' => array());
		}
		return $this->packLyrics($synced, 'srt');
	}

	private function parseAss($text) {
		$synced = array();
		foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
			if (stripos($line, 'Dialogue:') !== 0) continue;
			$parts = explode(',', substr($line, 9), 10);
			if (count($parts) < 10) continue;
			$start = $this->clockToSeconds(trim($parts[1]));
			$body = $parts[9];
			$words = array();
			$plain = '';
			$cursor = $start;
			$bits = preg_split('/\{\\\\(?:kf|ko|k|K)(\d+)\}/', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
			if (count($bits) > 1) {
				$prefix = trim(preg_replace('/\{[^}]*\}/', '', $bits[0]));
				if ($prefix !== '') { $words[] = array('t' => $cursor, 'w' => $prefix); $plain .= $prefix; }
				for ($i = 1; $i + 1 < count($bits); $i += 2) {
					$chunk = preg_replace('/\{[^}]*\}/', '', $bits[$i + 1]);
					$chunk = str_replace(array('\N', '\n'), ' ', $chunk);
					if ($chunk === '') { $cursor += intval($bits[$i]) / 100; continue; }
					$words[] = array('t' => $cursor, 'w' => $chunk);
					$plain .= $chunk;
					$cursor += intval($bits[$i]) / 100;
				}
			} else $plain = preg_replace('/\{[^}]*\}/', '', $body);
			$plain = trim(str_replace(array('\N', '\n', '\h'), array(' ', ' ', ' '), $plain));
			if ($plain === '') continue;
			$synced[] = array('t' => $start, 'line' => $plain, 'words' => $words);
		}
		return $this->packLyrics($synced, 'ass');
	}

	private function parseYrc($text) {
		$synced = array();
		foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
			$line = trim($line);
			if (!preg_match('/^\[(\d+),(\d+)\](.*)$/', $line, $m)) continue;
			$start = intval($m[1]) / 1000;
			$words = array();
			$plain = '';
			if (preg_match_all('/\((\d+),(\d+),\d+\)([^(]*)/', $m[3], $parts, PREG_SET_ORDER)) {
				foreach ($parts as $part) {
					if ($part[3] === '') continue;
					$words[] = array('t' => intval($part[1]) / 1000, 'w' => $part[3]);
					$plain .= $part[3];
				}
			} else $plain = preg_replace('/\(\d+,\d+,\d+\)/', '', $m[3]);
			$plain = trim($plain);
			if ($plain === '') continue;
			$synced[] = array('t' => $start, 'line' => $plain, 'words' => $words);
		}
		return $this->packLyrics($synced, 'yrc');
	}

	private function parseLyrics($text, $source) {
		$lines = preg_split("/\r\n|\n|\r/", (string)$text);
		$synced = array();
		$plain = array();
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') continue;
			if (preg_match_all('/\[(\d{1,2}):(\d{2}(?:\.\d+)?)\]/', $line, $matches, PREG_SET_ORDER)) {
				$body = trim(preg_replace('/\[\d{1,2}:\d{2}(?:\.\d+)?\]/', '', $line));
				if ($body === '') continue;
				foreach ($matches as $match) {
					$t = intval($match[1]) * 60 + floatval($match[2]);
					$synced[] = array('t' => $t, 'line' => trim(preg_replace('/<\d{1,2}:\d{2}(?:\.\d+)?>/', '', $body)), 'words' => $this->wordTimes($body, $t));
				}
			} else $plain[] = $line;
		}
		usort($synced, function($a, $b) {return $a['t'] == $b['t'] ? 0 : ($a['t'] < $b['t'] ? -1 : 1);});
		return array(
			'text' => $plain ? implode("\n", $plain) : implode("\n", array_map(function($row) {return $row['line'];}, $synced)),
			'synced' => $synced,
			'source' => $source,
		);
	}

	private function analyzeRaw($source) {
		static $parser = null;
		if (!$parser) {
			require_once SDK_DIR.'/getID3/getid3/getid3.php';
			$parser = new getID3();
			$parser->option_save_attachments = true;
		}
		$path = KodIO::make(intval($source['sourceID']));
		return @$parser->analyze('kodio://'.$path, intval($source['size']), $source['name']);
	}

	private function httpGet($url) {
		if (!function_exists('curl_init')) return '';
		$curl = curl_init($url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
			CURLOPT_USERAGENT => 'music-player/0.4.2',
			CURLOPT_HTTPHEADER => array('Lrclib-Client: music-player/0.4.2'),
		));
		$body = curl_exec($curl);
		$code = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
		curl_close($curl);
		return $code === 200 && is_string($body) ? $body : '';
	}

	private function patternCover($artist, $album) {
		$seed = abs(crc32($artist.'|'.$album.'|music'));
		$c1 = sprintf('#%02x%02x%02x', 80 + ($seed % 120), 40 + (($seed >> 8) % 100), 140 + (($seed >> 16) % 80));
		$c2 = sprintf('#%02x%02x%02x', 180 + (($seed >> 4) % 60), 60 + (($seed >> 12) % 120), 40 + (($seed >> 20) % 80));
		$c3 = sprintf('#%02x%02x%02x', 30 + (($seed >> 6) % 50), 20 + (($seed >> 14) % 40), 40 + (($seed >> 22) % 50));
		$label = htmlspecialchars($this->clip($album !== '' ? $album : $artist, 18), ENT_QUOTES, 'UTF-8');
		$sub = htmlspecialchars($this->clip($artist, 22), ENT_QUOTES, 'UTF-8');
		return '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$c1.'"/><stop offset="1" stop-color="'.$c2.'"/></linearGradient></defs><rect width="400" height="400" fill="url(#g)"/><circle cx="250" cy="150" r="150" fill="'.$c3.'" opacity=".35"/><circle cx="120" cy="280" r="90" fill="#fff" opacity=".12"/><circle cx="210" cy="200" r="78" fill="#111827" opacity=".55"/><circle cx="210" cy="200" r="18" fill="'.$c2.'"/><text x="24" y="350" fill="#fff" font-size="22" font-family="sans-serif">'.$label.'</text><text x="24" y="376" fill="rgba(255,255,255,.8)" font-size="14" font-family="sans-serif">'.$sub.'</text></svg>';
	}

	private function cleanupMissing($userID) {
		$where = array();
		if ($userID !== null) $where['userID'] = intval($userID);
		$indexed = Model($this->tableName)->where($where)->field('id,userID,sourceID')->select();
		$removed = 0;
		foreach ((array)$indexed as $item) {
			$source = Model('Source')->where(array(
				'sourceID' => intval($item['sourceID']),
				'targetID' => intval($item['userID']),
				'targetType' => 1, 'isDelete' => 0, 'isFolder' => 0,
			))->find();
			if ($source) continue;
			Model($this->tableName)->where(array('id' => intval($item['id'])))->delete();
			@unlink($this->coverFile(intval($item['sourceID']), intval($item['userID'])));
			@unlink($this->lyricsFile(intval($item['sourceID']), intval($item['userID'])));
			$removed++;
		}
		return $removed;
	}

	private function assertTrack($sourceID) {
		$sourceID = intval($sourceID);
		if (!$sourceID || !KodUser::id()) show_json(LNG('explorer.noPermissionAction'), false);
		$path = KodIO::make($sourceID);
		if (!Action('explorer.auth')->fileCanRead($path)) show_json(LNG('explorer.noPermissionAction'), false);
		$item = Model('Source')->where(array('sourceID' => $sourceID, 'isDelete' => 0, 'isFolder' => 0))->find();
		if (!$item) show_json('文件不存在或已删除', false);
		return $item;
	}

	public function source() {
		$sourceID = intval(Input::get('sourceid', 'require'));
		$this->assertTrack($sourceID);
		return show_json(array('stream' => $this->signedUrl('stream', $sourceID)));
	}

	public function resolve() {
		if (!KodUser::id()) return show_json(LNG('explorer.noPermissionAction'), false);
		$path = trim((string)_get($this->in, 'path', ''));
		if ($path === '') return show_json('缺少文件路径', false);
		try { $info = IO::info($path); } catch (Throwable $e) { $info = null; }
		if (!is_array($info) || _get($info, 'isFolder', 0)) return show_json('无法读取该文件', false);
		$sourceID = intval(_get($info, 'sourceID', 0));
		if (!$sourceID) return show_json('当前存储不能直接播放', false);
		$this->assertTrack($sourceID);
		$row = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->find();
		if ($row) {
			$title = $row['title'] !== '' ? $row['title'] : $row['name'];
			$artist = $row['artist'] !== '' ? $row['artist'] : LNG('music.lib.unknownArtist');
			$album = $row['album'] !== '' ? $row['album'] : LNG('music.lib.unknownAlbum');
			return show_json($this->trackOut($row, $title, $artist, $album, $this->signedUrl('cover', $sourceID)));
		}
		$ext = strtolower(_get($info, 'fileType', _get($info, 'ext', '')));
		return show_json(array(
			'id' => 0, 'sourceID' => $sourceID, 'title' => _get($info, 'name', '未命名'),
			'artist' => LNG('music.lib.unknownArtist'), 'album' => LNG('music.lib.unknownAlbum'),
			'trackNo' => 0, 'duration' => 0, 'ext' => $ext, 'year' => '', 'favorite' => 0,
			'lyricOffset' => 0, 'isVideo' => $this->isVideo($ext) ? 1 : 0, 'createTime' => time(),
			'path' => KodIO::make($sourceID), 'cover' => $this->signedUrl('cover', $sourceID), 'transient' => 1,
		));
	}

	public function favorite() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		$this->assertTrack($sourceID);
		$favorite = _get($this->in, 'favorite', 0) == 1 ? 1 : 0;
		Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->save(array('favorite' => $favorite, 'modifyTime' => time()));
		return show_json(array('sourceID' => $sourceID, 'favorite' => $favorite));
	}

	private function trackOut($row, $title, $artist, $album, $cover) {
		return array(
			'id' => intval($row['id']),
			'sourceID' => intval($row['sourceID']),
			'title' => $title,
			'artist' => $artist,
			'album' => $album,
			'trackNo' => intval($row['trackNo']),
			'duration' => intval($row['duration']),
			'year' => $row['year'],
			'ext' => $row['ext'],
			'favorite' => intval(_get($row, 'favorite', 0)),
			'createTime' => intval($row['createTime']),
			'lyricOffset' => intval(_get($row, 'lyricOffset', 0)),
			'isVideo' => $this->isVideo($row['ext']) ? 1 : 0,
			'path' => KodIO::make(intval($row['sourceID'])),
			'cover' => $cover,
		);
	}

	private function addListItem($listID, $sourceID) {
		$exists = Model('plugin_music_list_item')->where(array('listID' => intval($listID), 'sourceID' => intval($sourceID)))->find();
		if ($exists) return;
		$last = Model('plugin_music_list_item')->where(array('listID' => intval($listID)))->order('sort desc')->find();
		$model = Model('plugin_music_list_item');
		$model->setDataAuto(false);
		$model->add(array(
			'listID' => intval($listID), 'userID' => KodUser::id(), 'sourceID' => intval($sourceID),
			'sort' => $last ? intval($last['sort']) + 1 : 1,
		));
	}

	private function wordTimes($body, $lineTime) {
		if (!preg_match('/<\d{1,2}:\d{2}/', $body)) return array();
		$words = array();
		if (!preg_match_all('/(?:<(\d{1,2}):(\d{2}(?:\.\d+)?)>)([^<]*)/', $body, $found, PREG_SET_ORDER)) return array();
		foreach ($found as $part) {
			if ($part[3] === '') continue;
			$words[] = array(
				't' => ($part[1] === '' ? $lineTime : intval($part[1]) * 60 + floatval($part[2])),
				'w' => $part[3],
			);
		}
		return $words;
	}

	private function homePath() {
		if (defined('MY_HOME') && MY_HOME) return MY_HOME;
		$user = Session::get('kodUser');
		$sourceID = intval(_get($user, 'sourceInfo.sourceID', 0));
		return $sourceID ? KodIO::make($sourceID) : '';
	}

	private function offlineDir($kind) {
		$home = $this->homePath();
		if ($home === '') return '';
		$dir = rtrim($home, '/').'/音乐资料/'.$kind;
		if ($this->kodNode($dir, true)) return $dir;
		try { IO::mkdir($dir); } catch (Throwable $e) { return ''; }
		return $this->kodNode($dir, true) ? $dir : '';
	}

	// IO::info resolves a missing child path to its nearest existing parent, so match the name and type too.
	private function kodNode($path, $folder) {
		try { $info = IO::info($path); } catch (Throwable $e) { return null; }
		if (!is_array($info) || !_get($info, 'sourceID')) return null;
		if (intval(_get($info, 'isFolder', 0)) !== ($folder ? 1 : 0)) return null;
		$path = rtrim($path, '/');
		$name = substr($path, strrpos($path, '/') + 1);
		return _get($info, 'name', '') === $name ? $info : null;
	}

	private function offlineLyrics($sourceID) {
		$dir = $this->offlineDir('歌词');
		if ($dir === '') return null;
		$info = $this->kodNode($dir.'/'.intval($sourceID).'.lrc', false);
		if (!$info) return null;
		try { $text = IO::getContent(KodIO::make($info['sourceID'])); } catch (Throwable $e) { return null; }
		if (!is_string($text) || trim($text) === '') return null;
		$data = $this->parseLyrics($text, 'offline');
		if (preg_match('/\[offset:\s*([+-]?\d+)\s*\]/i', $text, $m)) $data['offset'] = intval($m[1]);
		return ($data['text'] !== '' || $data['synced']) ? $data : null;
	}

	private function offlineCover($sourceID) {
		$dir = $this->offlineDir('封面');
		if ($dir === '') return '';
		foreach (array('jpg','png','webp','img') as $ext) {
			$info = $this->kodNode($dir.'/'.intval($sourceID).'.'.$ext, false);
			if ($info && intval(_get($info, 'size', 0)) > 32) return KodIO::make($info['sourceID']);
		}
		return '';
	}

	private function archiveLyrics($sourceID, $data) {
		$dir = $this->offlineDir('歌词');
		if ($dir === '' || (($data['text'] === '' && !$data['synced']))) return;
		try { IO::mkfile($dir.'/'.intval($sourceID).'.lrc', $this->toLrc($data), REPEAT_REPLACE); } catch (Throwable $e) {}
	}

	private function archiveCover($sourceID, $file) {
		if ($this->offlineCover($sourceID) || !is_file($file)) return;
		$dir = $this->offlineDir('封面');
		if ($dir === '') return;
		$bin = @file_get_contents($file);
		if (!is_string($bin) || strlen($bin) < 32 || strncmp($bin, '<svg', 4) === 0) return;
		$ext = 'img';
		if (strncmp($bin, "\xFF\xD8\xFF", 3) === 0) $ext = 'jpg';
		elseif (strncmp($bin, "\x89PNG", 4) === 0) $ext = 'png';
		elseif (strncmp($bin, 'RIFF', 4) === 0) $ext = 'webp';
		try { IO::mkfile($dir.'/'.intval($sourceID).'.'.$ext, $bin, REPEAT_REPLACE); } catch (Throwable $e) {}
	}

	private function writeOffset($sourceID, $offset) {
		$dir = $this->offlineDir('歌词');
		if ($dir === '') return;
		$path = $dir.'/'.intval($sourceID).'.lrc';
		$info = $this->kodNode($path, false);
		if (!$info) return;
		try { $text = IO::getContent(KodIO::make($info['sourceID'])); } catch (Throwable $e) { return; }
		if (!is_string($text)) return;
		$tag = '[offset:'.($offset >= 0 ? '+' : '').intval($offset).']';
		$text = preg_match('/\[offset:[^\]]*\]/i', $text) ? preg_replace('/\[offset:[^\]]*\]/i', $tag, $text, 1) : $tag."\n".$text;
		try { IO::mkfile($path, $text, REPEAT_REPLACE); } catch (Throwable $e) {}
	}

	private function toLrc($data) {
		$lines = array('[offset:+'.intval(_get($data, 'lyricOffset', 0)).']');
		foreach ((array)$data['synced'] as $row) {
			$sec = floatval($row['t']);
			$m = floor($sec / 60); $s = $sec - $m * 60;
			$body = $row['line'];
			if (!empty($row['words'])) {
				$body = '';
				foreach ($row['words'] as $word) {
					$ws = floatval($word['t']);
					$wm = floor($ws / 60); $wr = $ws - $wm * 60;
					$body .= sprintf('<%02d:%05.2f>', $wm, $wr).$word['w'];
				}
			}
			$lines[] = sprintf('[%02d:%05.2f]%s', $m, $s, $body);
		}
		if (count($lines) === 1 && $data['text'] !== '') $lines[] = $data['text'];
		return implode("\n", $lines)."\n";
	}

	private function signedUrl($method, $sourceID) {
		return Action('user.index')->apiSignMake('plugin/music/'.$method, array('sourceid' => intval($sourceID)), false, '', true);
	}

	private function isVideo($ext) {
		return in_array(strtolower($ext), $this->videoExt, true);
	}

	private function coverDir() {return rtrim(TEMP_PATH, '/\\').'/music-covers';}
	private function lyricsDir() {return rtrim(TEMP_PATH, '/\\').'/music-lyrics';}

	private function coverFile($sourceID, $userID = null) {
		$uid = $userID !== null ? intval($userID) : intval(KodUser::id());
		return $this->coverDir().'/'.$uid.'-'.intval($sourceID).'.bin';
	}

	private function lyricsFile($sourceID, $userID = null) {
		$uid = $userID !== null ? intval($userID) : intval(KodUser::id());
		return $this->lyricsDir().'/'.$uid.'-'.intval($sourceID).'.json';
	}

	private function clip($value, $max) {
		$value = trim((string)$value);
		return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
	}

	private function updateTask($enable) {
		$event = $this->pluginName.'Plugin.task';
		$task = Model('SystemTask')->findByKey('event', $event);
		$data = array(
			'name' => LNG('music.meta.name'), 'type' => 'method', 'event' => $event,
			'time' => '{"type":"minute","minute":1}',
			'desc' => LNG('music.meta.desc'), 'enable' => $enable, 'system' => 1,
		);
		if ($task) return Model('SystemTask')->update($task['id'], array('name' => $data['name'], 'enable' => $enable, 'time' => $data['time']));
		return Model('SystemTask')->add($data);
	}
}
