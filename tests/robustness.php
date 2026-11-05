<?php
// Isolated in-memory SQLite checks; never loads the application or its database.
class PluginBase {
    public $pluginPath;
    public function getConfig() { return array('fileExt' => 'mp3', 'scanVideo' => 0); }
}
function _get($a, $k, $default = null) { return is_array($a) && array_key_exists($k, $a) ? $a[$k] : $default; }
function sqlSplit($s) { return explode(';', $s); }
class TestModel {
    public $pdo;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function db() { return $this; }
    public function getTables() { return $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN); }
    public function execute($sql) { return $this->pdo->exec($sql); }
    public function getDbFields() { return array_column($this->pdo->query('PRAGMA table_info(plugin_music_track)')->fetchAll(PDO::FETCH_ASSOC), 'name'); }
    public function flush() {}
    public function setDataAuto($value) {}
    private $conditions;
    public function where($conditions) { $this->conditions = $conditions; return $this; }
    public function save($data) {
        $fields = array_map(function($key){return '"'.$key.'"=?';}, array_keys($data));
        $where = array_map(function($key){return '"'.$key.'"=?';}, array_keys($this->conditions));
        $q = $this->pdo->prepare('UPDATE plugin_music_track SET '.implode(',', $fields).' WHERE '.implode(' AND ', $where));
        return $q->execute(array_merge(array_values($data), array_values($this->conditions)));
    }
}
function Model($name = null) { global $model; return $model; }
require dirname(__DIR__).'/app.php';
$model = new TestModel();
$GLOBALS['config']['database']['DB_TYPE'] = 'sqlite';
$p = new musicPlugin(); $p->pluginPath = dirname(__DIR__).'/';
function callPrivate($method, ...$args) { global $p; $r = new ReflectionMethod($p, $method); return $r->invokeArgs($p, $args); }
function check($name, $ok) { if (!$ok) throw new RuntimeException($name); echo "ok  $name\n"; }
$p->initTable();
$model->execute('DROP TABLE plugin_music_list_item');
$p->initTable();
check('repairs a missing playlist item table', in_array('plugin_music_list_item', $model->getTables()));
$model->execute('ALTER TABLE plugin_music_track DROP COLUMN excluded');
$p->initTable();
check('upgrades an existing track table', in_array('excluded', $model->getDbFields()));
$model->execute('CREATE TABLE io_source (sourceID integer, parentID integer, targetID integer, fileID integer, name text, fileType text, size integer, modifyTime integer, targetType integer, isFolder integer, isDelete integer)');
$model->execute("INSERT INTO io_source VALUES (1,10,7,1,'song.mp3','mp3',100,200,1,0,0)");
$model->execute("INSERT INTO plugin_music_track (userID,sourceID,fileID,name,ext,createTime,modifyTime,excluded) VALUES (7,1,1,'song.mp3','mp3',1,1,1)");
check('scan includes parent for folder cover lookup', strpos(callPrivate('scanBatchSql', 7, array('mp3'), 80), 's.parentID AS parentID') !== false);
foreach (array(7, null) as $user) {
    $rows = $model->pdo->query(callPrivate('scanBatchSql', $user, array('mp3'), 80, 2000000))->fetchAll();
    check('excluded track stays out of '.($user ? 'manual' : 'scheduled').' scan', !$rows);
}
$model->execute("UPDATE io_source SET fileType='txt'");
check('extension cleanup preserves exclusions', !$model->pdo->query(callPrivate('missingTracksSql', 7))->fetchAll());
$model->execute('UPDATE io_source SET isDelete=1');
check('deleted source can be cleaned up', count($model->pdo->query(callPrivate('missingTracksSql', 7))->fetchAll()) === 1);
foreach (array(null, array(), array('fileType'=>'audio')) as $more) check('empty metadata is not a successful read', !callPrivate('metadataReadable', $more));
check('duration is usable metadata', callPrivate('metadataReadable', array('playtime'=>123)));
check('tags are usable metadata', callPrivate('metadataReadable', array('tags'=>array('title'=>'song'))));
$miss = array('modifyTime'=>20, 'checkedAt'=>100);
check('recent cover miss is cached', callPrivate('coverMissValid', $miss, array('modifyTime'=>20), 200));
check('source change invalidates cover miss', !callPrivate('coverMissValid', $miss, array('modifyTime'=>21), 200));
check('cover miss expires', !callPrivate('coverMissValid', $miss, array('modifyTime'=>20), 3700));
// A replacement preserving mtime must still refresh metadata.
$model->execute("UPDATE io_source SET isDelete=0,fileType='mp3',fileID=1,size=100,modifyTime=200");
$model->execute("UPDATE plugin_music_track SET excluded=0,duration=200,sourceModifyTime=200,fileID=1,size=100");
foreach (array(7, null) as $owner) {
    $sql = callPrivate('scanBatchSql', $owner, array('mp3'), 80, 2000000);
    check('unchanged source is skipped', !$model->pdo->query($sql)->fetchAll());
    $model->execute('UPDATE io_source SET fileID=99');
    check('file replacement with same mtime is scanned', count($model->pdo->query($sql)->fetchAll()) === 1);
    $model->execute('UPDATE io_source SET fileID=1,size=999');
    check('size change with same mtime is scanned', count($model->pdo->query($sql)->fetchAll()) === 1);
    $model->execute('UPDATE io_source SET size=100');
}
$model->execute('ALTER TABLE plugin_music_track DROP COLUMN lyricOffsetSet');
$p->initTable();
check('existing schemas gain explicit-offset flag', in_array('lyricOffsetSet', $model->getDbFields()));
$track = $model->pdo->query('SELECT * FROM plugin_music_track LIMIT 1')->fetch(PDO::FETCH_ASSOC);
check('upgraded unset offset uses file tag', callPrivate('effectiveLyricOffset', $track, array('offset'=>1500)) === 1500);
$model->execute('UPDATE plugin_music_track SET lyricOffset=0,lyricOffsetSet=1');
$track = $model->pdo->query('SELECT * FROM plugin_music_track LIMIT 1')->fetch(PDO::FETCH_ASSOC);
check('stored explicit zero overrides refreshed file tag', callPrivate('effectiveLyricOffset', $track, array('offset'=>1500)) === 0);
$kept = callPrivate('keepTrackUserFlags', array('title'=>'rescanned'), $track);
check('rescan preserves explicit zero flag', $kept['lyricOffsetSet'] === 1 && $kept['lyricOffset'] === 0);
check('legacy nonzero offset stays effective', callPrivate('effectiveLyricOffset', array('lyricOffset'=>-500), array('offset'=>1500)) === -500);
check('explicit nonzero stays effective', callPrivate('effectiveLyricOffset', array('lyricOffset'=>500,'lyricOffsetSet'=>1), array('offset'=>1500)) === 500);
check('file offset is clamped', callPrivate('effectiveLyricOffset', array(), array('offset'=>90000)) === 15000);
// Failed metadata reads stamp the new identity without wiping the old metadata.
class GetInfo { public static function get($path) { throw new RuntimeException('unreadable tags'); } }
class KodIO { public static function make($id) { return '{source:'.$id.'}'; } }
$model->execute('UPDATE io_source SET fileID=999,size=1234');
$source = $model->pdo->query('SELECT * FROM io_source LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$existing = $model->pdo->query('SELECT * FROM plugin_music_track LIMIT 1')->fetch(PDO::FETCH_ASSOC);
callPrivate('indexSource', $source, $existing);
$updated = $model->pdo->query('SELECT * FROM plugin_music_track LIMIT 1')->fetch(PDO::FETCH_ASSOC);
check('failed read keeps stored duration and flags', $updated['duration'] === $existing['duration'] && $updated['lyricOffsetSet'] === 1);
check('failed read stamps replacement identity', intval($updated['fileID']) === 999 && intval($updated['size']) === 1234);
check('failed read is not retried on every scan', !$model->pdo->query(callPrivate('scanBatchSql', 7, array('mp3'), 80, 2000000))->fetchAll());
echo "all passed\n";
