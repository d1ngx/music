<?php
// Playlist removal planning, and the cover path no longer archives into the user's folders.
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

check('omitting ids deletes the list itself', $call('playlistRemovalIds', 0, '') === null);
check('one source id removes that item', $call('playlistRemovalIds', 5, '') === array(5));
check('a list of ids is deduped and capped to real ids', $call('playlistRemovalIds', 0, '3,5,3,0,9') === array(3, 5, 9));
check('a single id and a list combine', $call('playlistRemovalIds', 8, '3,8') === array(3, 8));
check('an invalid item request removes nothing', $call('playlistRemovalIds', 0, 'abc') === array());
check('viewing a cover has no writer into the user folder', !(new ReflectionClass('musicPlugin'))->hasMethod('archiveCover'));

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
