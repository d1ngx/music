<?php
// Isolated checks for the library scan. Builds SQL only.
// Does not connect to KodBox, the database, or the user's files.

class PluginBase {
	public $config = array(
		'fileExt' => 'mp3,flac,m4a,aac,ogg,oga,opus,wav,wma',
		'scanVideo' => 0,
	);
	public function getConfig() { return $this->config; }
}
function _get($arr, $key, $default = null) {
	if (!is_array($arr)) return $default;
	foreach (explode('.', $key) as $k) {
		if (!is_array($arr) || !array_key_exists($k, $arr)) return $default;
		$arr = $arr[$k];
	}
	return $arr;
}
require dirname(__DIR__).'/app.php';

$plugin = (new ReflectionClass('musicPlugin'))->newInstanceWithoutConstructor();
$call = function ($method) use ($plugin) {
	$args = array_slice(func_get_args(), 1);
	$ref = new ReflectionMethod($plugin, $method);
	$ref->setAccessible(true);
	return $ref->invokeArgs($plugin, $args);
};

$failed = 0;
function check($name, $ok) {
	global $failed;
	echo ($ok ? 'ok  ' : 'FAIL') . '  ' . $name . "\n";
	if (!$ok) $failed++;
}

$sql = $call('scanBatchSql', 7, array('mp3', 'FLAC', 'mp4'), 80);
check('a manual scan is limited to that user\'s own files', strpos($sql, 's.targetType=1') !== false && strpos($sql, 'AND s.targetID=7') !== false);
check('a batch only loads new or changed files', strpos($sql, 't.id IS NULL OR t.sourceModifyTime<>s.modifyTime') !== false);
check('a zero duration is tried again after a day, and new files go first', strpos($sql, 't.duration=0 AND t.modifyTime<') !== false && strpos($sql, 'ORDER BY (t.id IS NULL) DESC') !== false);
$fixed = $call('scanBatchSql', 7, array('mp3'), 80, 2000000);
check('the retry cutoff is one day before the given time', strpos($fixed, 't.modifyTime<'.(2000000 - 86400)) !== false);
check('the batch stops at the configured size', (bool)preg_match('/LIMIT 80$/', $sql));
check('extensions are quoted and normalized', strpos($sql, "'mp3','flac','mp4'") !== false);
check('shared files are not part of the query', stripos($sql, 'share') === false && strpos($sql, 'parentLevel') === false);

$cron = $call('scanBatchSql', null, array('mp3'), 80);
check('the scheduled scan still keeps each file with its owner', strpos($cron, 's.targetType=1') !== false && strpos($cron, 'AND s.targetID=') === false && strpos($cron, 't.userID=s.targetID') !== false);

$bad = $call('scanBatchSql', 7, array("mp3' OR 1=1 --"), 80);
check('an extension cannot change the query', $bad === '');

$capped = $call('scanBatchSql', 7, array('mp3'), 9999);
check('the batch size cannot exceed 200', (bool)preg_match('/LIMIT 200$/', $capped));

$missing = $call('missingTracksSql', 3);
check('cleanup drops a missing file or a file that is no longer audio or video', strpos($missing, 's.sourceID IS NULL') !== false && strpos($missing, "s.fileType NOT IN") !== false && strpos($missing, "'mp3'") !== false && strpos($missing, "'mp4'") !== false && strpos($missing, 'AND t.userID=3') !== false);
$missingAll = $call('missingTracksSql', null);
check('scheduled cleanup does not add a single-user filter', strpos($missingAll, 'AND t.userID=') === false);

$plugin->config = array('fileExt' => 'mp3,aiff', 'scanVideo' => 0);
$customExt = $call('scanExtensions');
$customScan = $call('scanBatchSql', 1, $customExt, 80, 2000000);
$customKeep = $call('missingTracksSql', 1);
check('a configured extension is both indexed and kept', strpos($customScan, "'aiff'") !== false && strpos($customKeep, "'aiff'") !== false);
check('videos stay listed when video scanning is off', strpos($customScan, "'mp4'") === false && strpos($customKeep, "'mp4'") !== false);

check('albums group by artist and title, not year', $call('albumKey', '王若琳', '迷宫') === $call('albumKey', '王若琳', '迷宫') && strpos($call('albumKey', 'A', 'B'), '1999') === false);
check('album keys ignore letter case', $call('albumKey', 'Artist', 'Album') === 'artist|album');

check('a failed read keeps the metadata already stored', $call('indexKeepsMetadata', false, array('id' => 1)) === true);
check('a successful read replaces metadata', $call('indexKeepsMetadata', true, array('id' => 1)) === false);
check('a new file is stored even when the read fails', $call('indexKeepsMetadata', false, null) === false);
check('a cover requested by someone is cached for that person', $call('coverUser', array('targetID' => 4), 9) === 9);
check('a scan with no viewer caches the cover for the file owner', $call('coverUser', array('targetID' => 4), 0) === 4);

$stale = $call('missingTrackColumns', array('id', 'title', 'artist', 'album'));
check('a field cache from before favorite still needs those columns', $stale === array('lyricOffset', 'excluded', 'favorite'));
check('a current schema does not ask to add favorite again', $call('missingTrackColumns', array('id', 'lyricOffset', 'excluded', 'favorite')) === array());
$kept = $call('keepTrackUserFlags', array('title' => 'A'), array('favorite' => 1, 'lyricOffset' => 200));
check('a later scan keeps favorite and lyric offset already stored', $kept['favorite'] === 1 && $kept['lyricOffset'] === 200);
$staleRow = $call('keepTrackUserFlags', array('title' => 'A'), array('id' => 1, 'title' => 'A'));
check('a stale row without those keys does not write zeros', !isset($staleRow['favorite']) && !isset($staleRow['lyricOffset']));
check('a new file does not inherit favorite flags', $call('keepTrackUserFlags', array('title' => 'A'), null) === array('title' => 'A'));

$row = $call('rowKeys', array('sourceid' => 9, 'targetid' => 7, 'userid' => 7, 'filetype' => 'mp3', 'modifytime' => 12));
check('driver column names map back to the indexer', $row['sourceID'] === 9 && $row['targetID'] === 7 && $row['userID'] === 7 && $row['fileType'] === 'mp3' && $row['modifyTime'] === 12);

$plugin->config = array('fileExt' => 'FLAC', 'scanVideo' => 0);
check('configured extensions replace defaults', $call('scanExtensions') === array('flac'));
$sql = $call('scanBatchSql', 7, $call('scanExtensions'), 80);
$cleanup = $call('missingTracksSql', 7);
check('removed audio formats are neither scanned nor retained', strpos($sql, "'mp3'") === false && strpos($cleanup, "'mp3'") === false);
$plugin->config = array('fileExt' => '', 'scanVideo' => 0);
check('explicit empty config scans no audio', $call('scanExtensions') === array());
$plugin->config = array('scanVideo' => 0);
check('absent config uses defaults', in_array('mp3', $call('scanExtensions'), true));
$plugin->config = array('fileExt' => 'flac', 'scanVideo' => 1);
check('video switch still adds video formats', in_array('mp4', $call('scanExtensions'), true));

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
