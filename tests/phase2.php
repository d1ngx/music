<?php
// Isolated checks for the library scan. Builds SQL only.
// Does not connect to KodBox, the database, or the user's files.

class PluginBase {}
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
check('cleanup only drops index rows whose owned file is gone', strpos($missing, 's.sourceID IS NULL') !== false && strpos($missing, 's.targetType=1') !== false && strpos($missing, 'AND t.userID=3') !== false);
$missingAll = $call('missingTracksSql', null);
check('scheduled cleanup does not add a single-user filter', strpos($missingAll, 'AND t.userID=') === false);

check('albums group by artist and title, not year', $call('albumKey', '王若琳', '迷宫') === $call('albumKey', '王若琳', '迷宫') && strpos($call('albumKey', 'A', 'B'), '1999') === false);
check('album keys ignore letter case', $call('albumKey', 'Artist', 'Album') === 'artist|album');

$row = $call('rowKeys', array('sourceid' => 9, 'targetid' => 7, 'userid' => 7, 'filetype' => 'mp3', 'modifytime' => 12));
check('driver column names map back to the indexer', $row['sourceID'] === 9 && $row['targetID'] === 7 && $row['userID'] === 7 && $row['fileType'] === 'mp3' && $row['modifyTime'] === 12);

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
