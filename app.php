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
		$sources = _get($config, 'lyricSources', 'amll,kugou,lrclib');
		$sources = array_map('trim', is_array($sources) ? $sources : explode(',', (string)$sources));
		$config['lyricSources'] = implode(',', array_values(array_intersect(array('amll', 'kugou', 'lrclib'), $sources)));
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
			$key = $this->albumKey($artist, $album);
			$cover = $this->signedUrl('cover', $row['sourceID']);
			if (!isset($albums[$key])) {
				$albums[$key] = array('key' => $key, 'artist' => $artist, 'album' => $album, 'year' => $row['year'], 'cover' => $cover, 'tracks' => array(), 'added' => intval($row['createTime']));
			} elseif ($albums[$key]['year'] === '' && $row['year'] !== '') $albums[$key]['year'] = $row['year'];
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
		if (!is_file($file) || filesize($file) < 32) $this->collectCover($source, KodUser::id());
		if (is_file($file) && filesize($file) >= 32) return IO::fileOut($file);
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
		$data = $this->chooseLyrics($saved, $cached, $this->trackDuration($track));
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
		$data['wordSources'] = $this->wordSourcesOn();
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
		$data['wordSources'] = $this->wordSourcesOn();
		return show_json($data);
	}

	// Re-reads local, embedded and online lyrics. The result stays in the plugin cache.
	// The copy under 音乐资料/歌词 is left untouched.
	public function lyricRefresh() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		$source = $this->assertTrack($sourceID);
		$track = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->find();
		$data = $this->findLyrics($source, $track);
		if ($data['text'] === '' && !$data['synced']) return show_json('没有找到新的歌词，已保留原来的歌词', false);
		$data['fetchedAt'] = time();
		$dir = $this->lyricsDir();
		if (!is_dir($dir)) @mkdir($dir, 0755, true);
		@file_put_contents($this->lyricsFile($sourceID), json_encode($data, JSON_UNESCAPED_UNICODE));
		$data['lyricOffset'] = intval($track ? _get($track, 'lyricOffset', 0) : 0);
		$data['wordSources'] = $this->wordSourcesOn();
		return show_json($data);
	}

	// Drops index rows and playlist membership only. Source files and 音乐资料 are kept.
	public function libraryRemove() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$this->initTable();
		$videos = _get($this->in, 'videos', 0) == 1;
		$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)_get($this->in, 'sourceids', ''))))));
		if (!$videos && !$ids) return show_json('没有选择歌曲', false);
		if (count($ids) > 500) return show_json('一次最多移除 500 首', false);
		$rows = Model($this->tableName)->where(array('userID' => KodUser::id()))->field('id,sourceID,ext')->select();
		$drop = $this->removalSet((array)$rows, $ids, $videos);
		if (!$drop) return show_json($videos ? '资料库里没有视频' : '这些歌曲不在资料库中', false);
		foreach ($drop as $sourceID) {
			Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->delete();
			Model('plugin_music_list_item')->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->delete();
			@unlink($this->coverFile($sourceID));
			@unlink($this->lyricsFile($sourceID));
		}
		return show_json(array('sourceIDs' => $drop, 'filesKept' => 1));
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
		$drop = $this->playlistRemovalIds(_get($this->in, 'sourceid', 0), _get($this->in, 'sourceids', ''));
		if ($drop === null) {
			Model('plugin_music_list_item')->where(array('listID' => $id, 'userID' => KodUser::id()))->delete();
			Model('plugin_music_list')->where(array('id' => $id, 'userID' => KodUser::id()))->delete();
			return show_json(array('id' => $id));
		}
		foreach ($drop as $sourceID) {
			Model('plugin_music_list_item')->where(array('listID' => $id, 'userID' => KodUser::id(), 'sourceID' => $sourceID))->delete();
		}
		return show_json(array('id' => $id, 'sourceIDs' => $drop));
	}

	// null deletes the whole list. An empty array was asked to remove items and removes nothing.
	private function playlistRemovalIds($sourceID, $raw) {
		$asked = intval($sourceID) > 0 || trim((string)$raw) !== '';
		if (!$asked) return null;
		$ids = array();
		foreach (array_slice(array_filter(array_map('intval', explode(',', (string)$raw))), 0, 500) as $id) {
			if ($id > 0) $ids[$id] = $id;
		}
		$sourceID = intval($sourceID);
		if ($sourceID > 0) $ids[$sourceID] = $sourceID;
		return array_values($ids);
	}

	// One batch of files this user owns that are new or have changed. Shared files are not part of the library.
	private function scanSources($userID, $limit) {
		$this->initTable();
		$limit = max(1, min(200, intval($limit)));
		$sql = $this->scanBatchSql($userID, $this->scanExtensions(), $limit);
		$rows = $sql === '' ? array() : Model()->db()->query($sql);
		$processed = 0;
		foreach ((array)$rows as $source) {
			$source = $this->rowKeys($source);
			$existing = Model($this->tableName)->where(array(
				'userID' => intval($source['targetID']),
				'sourceID' => intval($source['sourceID']),
			))->find();
			$this->indexSource($source, $existing);
			$processed++;
		}
		return array('processed' => $processed, 'removed' => $this->cleanupMissing($userID), 'more' => $processed >= $limit ? 1 : 0);
	}

	// Owned media only: targetType 1 and, for a manual scan, targetID is that user.
	private function scanBatchSql($userID, $extensions, $limit, $now = null) {
		$ext = $this->sqlExtList($extensions);
		if ($ext === '') return '';
		$owner = $userID !== null ? ' AND s.targetID='.intval($userID) : '';
		$limit = max(1, min(200, intval($limit)));
		$now = $now === null ? time() : intval($now);
		$retryBefore = $now - 86400;
		return 'SELECT s.sourceID AS sourceID, s.targetID AS targetID, s.fileID AS fileID, s.name AS name, s.fileType AS fileType, s.size AS size, s.modifyTime AS modifyTime'
			.' FROM io_source s'
			.' LEFT JOIN plugin_music_track t ON t.userID=s.targetID AND t.sourceID=s.sourceID'
			.' WHERE s.targetType=1 AND s.isFolder=0 AND s.isDelete=0'.$owner
			.' AND s.fileType IN ('.$ext.')'
			.' AND (t.id IS NULL OR t.sourceModifyTime<>s.modifyTime OR (t.duration=0 AND t.modifyTime<'.$retryBefore.'))'
			.' ORDER BY (t.id IS NULL) DESC, s.modifyTime DESC LIMIT '.$limit;
	}

	private function sqlExtList($extensions) {
		$list = array();
		foreach ((array)$extensions as $ext) {
			$ext = strtolower((string)$ext);
			if (preg_match('/^[a-z0-9]{1,16}$/', $ext)) $list[$ext] = "'".$ext."'";
		}
		return implode(',', $list);
	}

	private function albumKey($artist, $album) {
		return strtolower($artist.'|'.$album);
	}

	private function rowKeys($row) {
		if (!is_array($row)) return array();
		$out = array();
		foreach ($row as $key => $value) if (is_string($key)) $out[$key] = $value;
		foreach (array('sourceID' => 'sourceid', 'targetID' => 'targetid', 'userID' => 'userid', 'fileID' => 'fileid', 'fileType' => 'filetype', 'modifyTime' => 'modifytime') as $name => $lower) {
			if (!isset($out[$name]) && isset($out[$lower])) $out[$name] = $out[$lower];
		}
		return $out;
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

	// A failed read of an existing row must not wipe tags. Stamp the attempt and try a zero-duration row again after a day.
	private function indexKeepsMetadata($read, $existing) {
		return !$read && !empty($existing);
	}

	private function indexSource($source, $existing) {
		$meta = array('title' => '', 'artist' => '', 'album' => '', 'genre' => '', 'year' => '', 'trackNo' => 0, 'duration' => 0, 'hasCover' => 0);
		$video = $this->isVideo($source['fileType']);
		$fromName = $this->parseMediaName($source['name']);
		$read = false;
		try {
			$info = GetInfo::get(KodIO::make(intval($source['sourceID'])));
			$more = (array)_get($info, 'fileInfoMore', array());
			$meta['duration'] = intval(_get($more, 'playtime', 0));
			if (!$video) {
				$tags = (array)_get($more, 'tags', array());
				$meta['title'] = $this->clip(_get($tags, 'title', ''), 250);
				$meta['artist'] = $this->clip(_get($tags, 'artist', ''), 250);
				$meta['album'] = $this->clip(_get($tags, 'album', ''), 250);
				$meta['genre'] = $this->clip(_get($tags, 'genre', ''), 120);
				$meta['year'] = $this->clip(_get($tags, 'year', ''), 16);
				$meta['trackNo'] = intval(_get($tags, 'track', 0));
			}
			$read = true;
		} catch (Throwable $e) {}
		if ($this->indexKeepsMetadata($read, $existing)) {
			$model = Model($this->tableName); $model->setDataAuto(false);
			$model->where(array('id' => intval($existing['id']), 'userID' => intval($existing['userID'])))->save(array(
				'sourceModifyTime' => intval($source['modifyTime']),
				'modifyTime' => time(),
			));
			return;
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

	// The viewer gets their own cache. A scan with no viewer stores it for the file's owner.
	private function coverUser($source, $userID) {
		$uid = intval($userID);
		return $uid > 0 ? $uid : intval(_get($source, 'targetID', 0));
	}

	private function collectCover($source, $userID = null) {
		$dir = $this->coverDir();
		if (!is_dir($dir)) @mkdir($dir, 0755, true);
		$target = $this->coverFile(intval($source['sourceID']), $this->coverUser($source, $userID));
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
		$remote = $this->onlineLyrics($track, $source);
		return $remote ? $remote : $empty;
	}

	private function lyricSources() {
		$config = $this->getConfig();
		if (_get($config, 'onlineLyrics', 0) != 1) return array();
		$raw = _get($config, 'lyricSources', null);
		if ($raw === null) $raw = 'amll,kugou,lrclib';
		$list = array_map('trim', is_array($raw) ? $raw : explode(',', (string)$raw));
		return array_values(array_intersect(array('amll', 'kugou', 'lrclib'), $list));
	}

	// Word-timed results win, but only when they cover the song. A short clip must not
	// replace a full line-timed lyric.
	private function onlineLyrics($track, $source, $wordsOnly = false) {
		$duration = $this->trackDuration($track);
		$best = null;
		$clip = null;
		foreach ($this->lyricSources() as $name) {
			if ($wordsOnly && $name === 'lrclib') continue;
			if ($name === 'amll') $data = $this->amllLyrics($track, $source);
			elseif ($name === 'kugou') $data = $this->kugouLyrics($track, $source);
			else $data = $this->lrclibLyrics($track, $source);
			if (!$data) continue;
			if (!$this->lyricCovers($data, $duration)) {
				if (!$clip || $this->lyricSpan($data) > $this->lyricSpan($clip)) $clip = $data;
				continue;
			}
			if ($this->hasWordTimes($data)) return $data;
			if (!$best || (!$best['synced'] && $data['synced'])) $best = $data;
		}
		if ($best) return $best;
		return $wordsOnly ? ($clip && $this->hasWordTimes($clip) ? $clip : null) : $clip;
	}

	public function lyricSearch() {
		if (!$this->postAllowed()) return show_json(LNG('common.illegalRequest'), false);
		$sourceID = intval(_get($this->in, 'sourceid', 0));
		$source = $this->assertTrack($sourceID);
		if (!$this->wordSourcesOn()) return show_json('插件设置里没有开启逐字歌词来源', false);
		$track = Model($this->tableName)->where(array('userID' => KodUser::id(), 'sourceID' => $sourceID))->find();
		$data = $this->onlineLyrics($track, $source, true);
		if (!$data) return show_json('没有找到这首歌的逐字歌词', false);
		$dir = $this->lyricsDir();
		if (!is_dir($dir)) @mkdir($dir, 0755, true);
		@file_put_contents($this->lyricsFile($sourceID), json_encode($data, JSON_UNESCAPED_UNICODE));
		$data['lyricOffset'] = intval($track ? _get($track, 'lyricOffset', 0) : 0);
		$this->archiveLyrics($sourceID, $data);
		$data['wordSources'] = 1;
		return show_json($data);
	}

	private function wordSourcesOn() {
		return array_diff($this->lyricSources(), array('lrclib')) ? 1 : 0;
	}

	private function trackDuration($track) {
		return max(0, intval($track ? _get($track, 'duration', 0) : 0));
	}

	private function lyricSpan($data) {
		$last = 0;
		foreach ((array)_get($data, 'synced', array()) as $row) {
			$last = max($last, floatval(_get($row, 't', 0)));
			foreach ((array)_get($row, 'words', array()) as $word) $last = max($last, floatval(_get($word, 'e', _get($word, 't', 0))));
		}
		return $last;
	}

	// A refreshed cache (fetchedAt) wins over an older archived file. A newer file wins over the cache.
	// Clips that end in the first part of the song are ignored so lyrics() looks again.
	private function chooseLyrics($saved, $cached, $duration) {
		$usable = function($data) use ($duration) {
			return is_array($data) && ((_get($data, 'text', '') !== '') || !empty($data['synced'])) && $this->lyricCovers($data, $duration);
		};
		$savedOk = $usable($saved);
		$cachedOk = $usable($cached);
		$fileTime = $savedOk ? intval(_get($saved, 'fileTime', 0)) : 0;
		if ($cachedOk && intval(_get($cached, 'fetchedAt', 0)) > $fileTime) return $cached;
		if ($savedOk) return $saved;
		if ($cachedOk) return $cached;
		return null;
	}

	// Which library rows to drop. Never returns anything that would identify a source file to delete.
	private function removalSet($rows, $sourceIDs, $videosOnly) {
		$want = array();
		foreach ((array)$sourceIDs as $id) {
			$id = intval($id);
			if ($id) $want[$id] = 1;
		}
		$drop = array();
		foreach ((array)$rows as $row) {
			$sid = intval(_get($row, 'sourceID', 0));
			if (!$sid || isset($drop[$sid])) continue;
			if ($videosOnly) {
				if ($this->isVideo(_get($row, 'ext', ''))) $drop[$sid] = $sid;
			} elseif (isset($want[$sid])) $drop[$sid] = $sid;
		}
		return array_values($drop);
	}

	// Lyrics that finish in the first 45% of the song, and more than 20 seconds before it ends, are a clip.
	private function lyricCovers($data, $duration) {
		$duration = intval($duration);
		if ($duration < 30) return true;
		$last = $this->lyricSpan($data);
		if ($last <= 0) return true;
		return $last >= $duration * 0.45 || $duration - $last <= 20;
	}

	private function sameTitle($a, $b) {
		$clean = function($s) {
			$s = strtolower(trim((string)$s));
			$s = preg_replace('/\s*[\(（\[【].*$/u', '', $s);
			return preg_replace('/[\s\p{P}]+/u', '', $s);
		};
		$a = $clean($a); $b = $clean($b);
		return $a !== '' && $a === $b;
	}

	private function sameArtist($want, $names) {
		$want = strtolower(preg_replace('/[\s\p{P}]+/u', '', (string)$want));
		if ($want === '') return true;
		foreach ((array)$names as $name) {
			$name = strtolower(preg_replace('/[\s\p{P}]+/u', '', (string)$name));
			if ($name !== '' && (strpos($want, $name) !== false || strpos($name, $want) !== false)) return true;
		}
		return false;
	}

	// Title must match without being another version; artist must match when known; durations within 4 s when both known.
	private function candidateMatches($pair, $duration, $title, $artists, $seconds) {
		if (!$this->sameTitle($pair[1], $title) || $this->otherVersion($pair[1], $title)) return false;
		if ($duration && $seconds && abs($duration - $seconds) > 4) return false;
		if ($pair[0] !== '') return $this->sameArtist($pair[0], $artists);
		return $duration && $seconds;
	}

	private function otherVersion($want, $title) {
		$re = '/\b(dj|remix|live|instrumental|karaoke|cover|demo|acoustic|inst)\b|伴奏|翻唱|翻自|原唱|现场|纯音乐|钢琴版|吉他版|女声版|男声版|深情版|dj版/iu';
		return preg_match($re, (string)$title) && !preg_match($re, (string)$want);
	}

	private function amllLyrics($track, $source) {
		$index = $this->amllIndex();
		if (!$index) return null;
		$duration = $this->trackDuration($track);
		$tried = 0;
		foreach ($this->lyricQueries($track, $source) as $pair) {
			if ($tried >= 3) break;
			$tried++;
			$fetched = 0;
			// Later index rows are newer revisions of the same song.
			for ($i = count($index) - 1; $i >= 0 && $fetched < 2; $i--) {
				list($titles, $artists, $file) = $index[$i];
				$hit = false;
				foreach ($titles as $title) {
					if ($this->sameTitle($pair[1], $title) && !$this->otherVersion($pair[1], $title)) { $hit = true; break; }
				}
				if (!$hit || ($pair[0] !== '' ? !$this->sameArtist($pair[0], $artists) : !$duration)) continue;
				$fetched++;
				$ttml = $this->httpGet('https://raw.githubusercontent.com/amll-dev/amll-ttml-db/main/raw-lyrics/'.rawurlencode($file));
				if ($ttml === '') continue;
				if ($duration && preg_match('/<body[^>]*\sdur="([^"]+)"/', $ttml, $m)) {
					$length = $this->clockToSeconds($m[1]);
					if ($length && abs($length - $duration) > 6) continue;
				} elseif ($pair[0] === '') continue;
				$parsed = $this->parseTtml($ttml);
				if ($parsed['synced']) { $parsed['source'] = 'amll'; return $parsed; }
			}
		}
		return null;
	}

	// Compact copy of metadata/raw-lyrics-index.jsonl: [[titles], [artists], rawLyricFile], refreshed daily.
	private function amllIndex() {
		$file = $this->lyricsDir().'/amll-index.json';
		$fresh = is_file($file) && filemtime($file) > time() - 86400;
		if (!$fresh) {
			$raw = $this->httpGet('https://raw.githubusercontent.com/amll-dev/amll-ttml-db/main/metadata/raw-lyrics-index.jsonl', array(), 25);
			$list = array();
			foreach (explode("\n", $raw) as $line) {
				$row = json_decode($line, true);
				$name = (string)_get($row, 'rawLyricFile', '');
				if ($name === '' || !preg_match('/^[\w.-]+\.ttml$/', $name)) continue;
				$meta = array();
				foreach ((array)_get($row, 'metadata', array()) as $pair) {
					if (is_array($pair) && count($pair) === 2) $meta[$pair[0]] = (array)$pair[1];
				}
				if (empty($meta['musicName'])) continue;
				$list[] = array(array_values($meta['musicName']), array_values(_get($meta, 'artists', array())), $name);
			}
			if (!is_dir($this->lyricsDir())) @mkdir($this->lyricsDir(), 0755, true);
			if ($list) {
				@file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE));
				return $list;
			}
			// Download failed: keep any old copy and wait an hour before trying again.
			if (!is_file($file)) @file_put_contents($file, '[]');
			@touch($file, time() - 86400 + 3600);
		}
		$list = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
		return is_array($list) ? $list : array();
	}

	private function kugouLyrics($track, $source) {
		$duration = $this->trackDuration($track);
		$tried = 0;
		$best = null;
		foreach ($this->lyricQueries($track, $source) as $pair) {
			if ($tried >= 2) break;
			$tried++;
			$params = array('ver' => 1, 'man' => 'yes', 'client' => 'pc', 'keyword' => $pair[0] !== '' ? $pair[0].' - '.$pair[1] : $pair[1], 'duration' => $duration * 1000, 'hash' => '');
			$list = (array)_get(json_decode((string)$this->httpGet('https://lyrics.kugou.com/search?'.http_build_query($params)), true), 'candidates', array());
			// Without a duration, Kugou ranks short clips first. Prefer the longest matching candidate.
			if (!$duration) usort($list, function($a, $b) {return intval(_get($b, 'duration', 0)) - intval(_get($a, 'duration', 0));});
			$seen = 0;
			foreach ($list as $row) {
				if ($seen >= 6) break;
				$id = _get($row, 'id', ''); $key = _get($row, 'accesskey', '');
				if ($id === '' || $key === '') continue;
				$seconds = intval(_get($row, 'duration', 0)) / 1000;
				if (!$this->candidateMatches($pair, $duration, _get($row, 'song', ''), preg_split('/[、,&]/u', (string)_get($row, 'singer', '')), $seconds)) continue;
				$seen++;
				$res = json_decode((string)$this->httpGet('https://lyrics.kugou.com/download?'.http_build_query(array('ver' => 1, 'client' => 'pc', 'id' => $id, 'accesskey' => $key, 'fmt' => 'krc', 'charset' => 'utf8'))), true);
				$text = $this->decodeKrc((string)base64_decode((string)_get($res, 'content', '')));
				if ($text === '') continue;
				$parsed = $this->parseKrc($text);
				if (!$parsed['synced']) continue;
				$parsed['source'] = 'kugou';
				if ($this->lyricCovers($parsed, $duration)) return $parsed;
				if (!$best || $this->lyricSpan($parsed) > $this->lyricSpan($best)) $best = $parsed;
			}
		}
		return $best;
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
			$hit = $this->pickLyricsRecord($this->httpGet('https://lrclib.net/api/search?'.http_build_query($params), array('Lrclib-Client: music-player/0.5.0')));
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
		if (preg_match('/^\[\d+,\d+\]</m', $text)) {
			$parsed = $this->parseKrc($text);
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
					$words[] = $this->word(floatval(_get($word, 't', 0)), (string)$word['w'], isset($word['e']) ? floatval($word['e']) : 0);
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
				if (isset($word['endTimeMs'])) $we = floatval($word['endTimeMs']) / 1000;
				elseif (isset($word['durationMs'])) $we = $wt + floatval($word['durationMs']) / 1000;
				else $we = floatval(_get($word, 'e', 0));
				$words[] = $this->word($wt, $w, $we);
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
			$words = array();
			$line = '';
			$timed = false;
			foreach ($node->childNodes as $child) {
				if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
					// Spaces between spans separate words; whitespace with a newline is just XML indentation.
					$gap = $child->textContent;
					if ($gap === '' || (trim($gap) === '' && strpos($gap, "\n") !== false)) continue;
					$gap = preg_replace('/\s+/', ' ', $gap);
					if ($words) $words[count($words) - 1]['w'] .= $gap;
					$line .= $gap;
					continue;
				}
				if ($child->nodeType !== XML_ELEMENT_NODE) continue;
				// Translation, romanisation and background-vocal spans are not part of the sung line.
				$role = $child->getAttributeNS('http://www.w3.org/ns/ttml#metadata', 'role');
				if ($role === '') $role = $child->getAttribute('ttm:role');
				if ($role !== '') continue;
				$w = preg_replace('/\s+/', ' ', $child->textContent);
				if ($w === '') continue;
				$begin = $child->getAttribute('begin');
				if ($begin !== '') $timed = true;
				$wt = $begin !== '' ? $this->clockToSeconds($begin) : $t;
				$end = $child->getAttribute('end');
				$dur = $child->getAttribute('dur');
				$we = $end !== '' ? $this->clockToSeconds($end) : ($dur !== '' ? $wt + $this->clockToSeconds($dur) : 0);
				$words[] = $this->word($wt, $w, $we);
				$line .= $w;
			}
			if (!$timed) $words = array();
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
					$length = intval($bits[$i]) / 100;
					if ($chunk === '') { $cursor += $length; continue; }
					$words[] = $this->word($cursor, $chunk, $cursor + $length);
					$plain .= $chunk;
					$cursor += $length;
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
					$words[] = $this->word(intval($part[1]) / 1000, $part[3], (intval($part[1]) + intval($part[2])) / 1000);
					$plain .= $part[3];
				}
			} else $plain = preg_replace('/\(\d+,\d+,\d+\)/', '', $m[3]);
			$plain = trim($plain);
			if ($plain === '') continue;
			$synced[] = array('t' => $start, 'line' => $plain, 'words' => $words);
		}
		return $this->packLyrics($synced, 'yrc');
	}

	// KRC: [lineStart,lineDur]<offset,dur,0>word, word offsets relative to the line start.
	private function parseKrc($text) {
		$synced = array();
		foreach (preg_split("/\r\n|\n|\r/", $text) as $line) {
			$line = trim($line);
			if (!preg_match('/^\[(\d+),(\d+)\](.*)$/', $line, $m)) continue;
			$start = intval($m[1]) / 1000;
			$words = array();
			$plain = '';
			if (preg_match_all('/<(\d+),(\d+),\d+>([^<]*)/', $m[3], $parts, PREG_SET_ORDER)) {
				foreach ($parts as $part) {
					if ($part[3] === '') continue;
					$t = $start + intval($part[1]) / 1000;
					$words[] = $this->word($t, $part[3], $t + intval($part[2]) / 1000);
					$plain .= $part[3];
				}
			} else $plain = $m[3];
			$plain = trim($plain);
			if ($plain === '') continue;
			$synced[] = array('t' => $start, 'line' => $plain, 'words' => $words);
		}
		return $this->packLyrics($synced, 'krc');
	}

	private function decodeKrc($bin) {
		if (strncmp($bin, 'krc1', 4) !== 0 || !function_exists('gzuncompress')) return '';
		$key = array(64, 71, 97, 119, 94, 50, 116, 71, 81, 54, 49, 45, 206, 210, 110, 105);
		$data = substr($bin, 4);
		for ($i = 0, $n = strlen($data); $i < $n; $i++) $data[$i] = chr(ord($data[$i]) ^ $key[$i % 16]);
		$text = @gzuncompress($data);
		if (!is_string($text)) return '';
		return strncmp($text, "\xEF\xBB\xBF", 3) === 0 ? substr($text, 3) : $text;
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

	private function httpGet($url, $headers = array(), $timeout = 8) {
		if (!function_exists('curl_init')) return '';
		$curl = curl_init($url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $timeout,
			CURLOPT_USERAGENT => 'music-player/0.5.0',
			CURLOPT_HTTPHEADER => $headers,
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

	// Index rows whose owned file is gone. Does not delete the file or anything under 音乐资料.
	private function cleanupMissing($userID) {
		$rows = Model()->db()->query($this->missingTracksSql($userID));
		$removed = 0;
		foreach ((array)$rows as $item) {
			$item = $this->rowKeys($item);
			$id = intval(_get($item, 'id', 0));
			$uid = intval(_get($item, 'userID', 0));
			$sid = intval(_get($item, 'sourceID', 0));
			if (!$id || !$uid || !$sid) continue;
			Model($this->tableName)->where(array('id' => $id, 'userID' => $uid))->delete();
			Model('plugin_music_list_item')->where(array('userID' => $uid, 'sourceID' => $sid))->delete();
			@unlink($this->coverFile($sid, $uid));
			@unlink($this->lyricsFile($sid, $uid));
			$removed++;
		}
		return $removed;
	}

	private function missingTracksSql($userID) {
		$owner = $userID !== null ? ' AND t.userID='.intval($userID) : '';
		// Keep the extensions this scan would index, and every video type even when video scanning is off.
		$media = $this->sqlExtList(array_merge($this->scanExtensions(), $this->videoExt));
		$gone = $media === '' ? 's.sourceID IS NULL' : '(s.sourceID IS NULL OR s.fileType NOT IN ('.$media.'))';
		return 'SELECT t.id AS id, t.userID AS userID, t.sourceID AS sourceID'
			.' FROM plugin_music_track t'
			.' LEFT JOIN io_source s ON s.sourceID=t.sourceID AND s.targetID=t.userID AND s.targetType=1 AND s.isDelete=0 AND s.isFolder=0'
			.' WHERE '.$gone.$owner;
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

	// A timestamp with no text after it marks where the previous word stops sounding.
	private function wordTimes($body, $lineTime) {
		if (!preg_match('/<\d{1,2}:\d{2}/', $body)) return array();
		$words = array();
		if (!preg_match_all('/(?:<(\d{1,2}):(\d{2}(?:\.\d+)?)>)([^<]*)/', $body, $found, PREG_SET_ORDER)) return array();
		foreach ($found as $part) {
			$t = $part[1] === '' ? $lineTime : intval($part[1]) * 60 + floatval($part[2]);
			if ($part[3] === '') {
				$last = count($words) - 1;
				if ($last >= 0 && empty($words[$last]['e']) && $t > $words[$last]['t']) $words[$last]['e'] = $t;
				continue;
			}
			$words[] = array('t' => $t, 'w' => $part[3]);
		}
		return $words;
	}

	private function word($t, $w, $e = 0) {
		$word = array('t' => round($t, 3), 'w' => $w);
		if ($e > $t) $word['e'] = round($e, 3);
		return $word;
	}

	private function homePath() {
		if (defined('MY_HOME') && MY_HOME) return MY_HOME;
		$user = Session::get('kodUser');
		$sourceID = intval(_get($user, 'sourceInfo.sourceID', 0));
		return $sourceID ? KodIO::make($sourceID) : '';
	}

	// Returns "{source:N}" for 音乐资料/<kind> in the user's home, creating it when missing.
	private function offlineDir($kind) {
		if (!preg_match('/^\{source:(\d+)\}/', $this->homePath(), $m)) return '';
		$dir = KodIO::make(intval($m[1]));
		foreach (array('音乐资料', $kind) as $name) {
			$node = $this->kodNode($dir, $name, true);
			if (!$node) {
				try { IO::mkdir(rtrim($dir, '/').'/'.$name); } catch (Throwable $e) { return ''; }
				$node = $this->kodNode($dir, $name, true);
			}
			if (!$node) return '';
			$dir = KodIO::make(intval($node['sourceID']));
		}
		return rtrim($dir, '/');
	}

	// Looked up by parent ID and name: IO::info on "{source:N}/name" falls back to the parent for some names.
	private function kodNode($dir, $name, $folder) {
		if (!preg_match('/^\{source:(\d+)\}/', (string)$dir, $m)) return null;
		$row = Model('Source')->where(array(
			'parentID' => intval($m[1]), 'name' => $name,
			'isFolder' => $folder ? 1 : 0, 'isDelete' => 0,
		))->find();
		return $row ? $row : null;
	}

	private function offlineLyrics($sourceID) {
		$dir = $this->offlineDir('歌词');
		if ($dir === '') return null;
		$info = $this->kodNode($dir, intval($sourceID).'.lrc', false);
		if (!$info) return null;
		try { $text = IO::getContent(KodIO::make($info['sourceID'])); } catch (Throwable $e) { return null; }
		if (!is_string($text) || trim($text) === '') return null;
		$data = $this->parseLyrics($text, 'offline');
		if (preg_match('/\[offset:\s*([+-]?\d+)\s*\]/i', $text, $m)) $data['offset'] = intval($m[1]);
		$data['fileTime'] = intval(_get($info, 'modifyTime', 0));
		return ($data['text'] !== '' || $data['synced']) ? $data : null;
	}

	private function offlineCover($sourceID) {
		$dir = $this->offlineDir('封面');
		if ($dir === '') return '';
		foreach (array('jpg','png','webp','img') as $ext) {
			$info = $this->kodNode($dir, intval($sourceID).'.'.$ext, false);
			if ($info && intval(_get($info, 'size', 0)) > 32) return KodIO::make($info['sourceID']);
		}
		return '';
	}

	private function archiveLyrics($sourceID, $data) {
		$dir = $this->offlineDir('歌词');
		if ($dir === '' || (($data['text'] === '' && !$data['synced']))) return;
		try { IO::mkfile($dir.'/'.intval($sourceID).'.lrc', $this->toLrc($data), REPEAT_REPLACE); } catch (Throwable $e) {}
	}

	private function writeOffset($sourceID, $offset) {
		$dir = $this->offlineDir('歌词');
		if ($dir === '') return;
		$path = $dir.'/'.intval($sourceID).'.lrc';
		$info = $this->kodNode($dir, intval($sourceID).'.lrc', false);
		if (!$info) return;
		try { $text = IO::getContent(KodIO::make($info['sourceID'])); } catch (Throwable $e) { return; }
		if (!is_string($text)) return;
		$tag = '[offset:'.($offset >= 0 ? '+' : '').intval($offset).']';
		$text = preg_match('/\[offset:[^\]]*\]/i', $text) ? preg_replace('/\[offset:[^\]]*\]/i', $tag, $text, 1) : $tag."\n".$text;
		try { IO::mkfile($path, $text, REPEAT_REPLACE); } catch (Throwable $e) {}
	}

	private function toLrc($data) {
		$offset = intval(_get($data, 'lyricOffset', 0));
		$lines = array('[offset:'.($offset >= 0 ? '+' : '').$offset.']');
		foreach ((array)$data['synced'] as $row) {
			$sec = floatval($row['t']);
			$m = floor($sec / 60); $s = $sec - $m * 60;
			$body = $row['line'];
			if (!empty($row['words'])) {
				$body = '';
				$words = array_values($row['words']);
				foreach ($words as $i => $word) {
					$body .= $this->lrcStamp($word['t']).$word['w'];
					$end = floatval(_get($word, 'e', 0));
					$next = isset($words[$i + 1]) ? floatval($words[$i + 1]['t']) : 0;
					if ($end > floatval($word['t']) && (!$next || $next - $end > 0.03)) $body .= $this->lrcStamp($end);
				}
			}
			$lines[] = sprintf('[%02d:%05.2f]%s', $m, $s, $body);
		}
		if (count($lines) === 1 && $data['text'] !== '') $lines[] = $data['text'];
		return implode("\n", $lines)."\n";
	}

	private function lrcStamp($sec) {
		$sec = floatval($sec);
		$m = floor($sec / 60);
		return sprintf('<%02d:%05.2f>', $m, $sec - $m * 60);
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
