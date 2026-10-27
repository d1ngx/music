<?php
// Isolated checks for lyric selection and library removal planning.
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

$line = function ($t, $text) {
	return array('t' => $t, 'line' => $text, 'words' => array());
};
$full = array('text' => 'full', 'synced' => array($line(10, 'a'), $line(200, 'b')), 'source' => 'offline', 'fileTime' => 100);
$clip = array('text' => 'clip', 'synced' => array($line(19, 'a'), $line(27, 'b')), 'source' => 'kugou');
$fresh = array('text' => 'fresh', 'synced' => array($line(12, 'a'), $line(190, 'b')), 'source' => 'kugou', 'fetchedAt' => 500);
$older = array('text' => 'old cache', 'synced' => array($line(12, 'a'), $line(190, 'b')), 'source' => 'kugou', 'fetchedAt' => 50);

$chosen = $call('chooseLyrics', $full, $fresh, 217);
check('refreshed cache wins over an older lyric file', $chosen && $chosen['text'] === 'fresh');

$chosen = $call('chooseLyrics', array('fileTime' => 800) + $full, $fresh, 217);
check('a newer lyric file wins over the cache', $chosen && $chosen['text'] === 'full');

$chosen = $call('chooseLyrics', $full, $older, 217);
check('an old cache does not replace the lyric file', $chosen && $chosen['text'] === 'full');

$chosen = $call('chooseLyrics', $clip, null, 217);
check('a clip is not reused', $chosen === null);

$chosen = $call('chooseLyrics', null, $full, 217);
check('a covering cache is used when no file exists', $chosen && $chosen['text'] === 'full');

$rows = array(
	array('sourceID' => 1, 'ext' => 'mp3'),
	array('sourceID' => 2, 'ext' => 'mp4'),
	array('sourceID' => 3, 'ext' => 'mkv'),
	array('sourceID' => 4, 'ext' => 'flac'),
);
$ids = $call('removalSet', $rows, array(1, 4, 99), false);
check('removal keeps only selected library rows', $ids === array(1, 4));

$videos = $call('removalSet', $rows, array(1), true);
sort($videos);
check('video removal ignores the selection and keeps audio', $videos === array(2, 3));

$none = $call('removalSet', $rows, array(), false);
check('an empty selection removes nothing', $none === array());

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
