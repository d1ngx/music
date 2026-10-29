<?php
// No application initialization or real database/files: metadata comes from a stub.
class PluginBase {}
function _get($a,$k,$d=null){return is_array($a)&&array_key_exists($k,$a)?$a[$k]:$d;}
class SourceStub {
    public static $rows=array();
    public function where($x){return $this;} public function field($x){return $this;}
    public function order($x){return $this;} public function select(){return self::$rows;}
}
function Model($name){return new SourceStub;}
require dirname(__DIR__).'/app.php';
$p=new musicPlugin;
function invoke($name,...$args){global $p;return (new ReflectionMethod($p,$name))->invokeArgs($p,$args);}
function check($name,$ok){if(!$ok)throw new RuntimeException($name);echo "ok $name\n";}
foreach(array(1500,-500,0) as $offset){
    $data=invoke('detectAndParse',"[offset:$offset]\n[00:01.00]first\n[00:20.00]last\n");
    check('offset parsed '.$offset, $data['offset']===$offset && strpos($data['text'],'offset:')===false);
    $round=invoke('detectAndParse',invoke('toLrc',$data));
    check('offset round trip '.$offset,$round['offset']===$offset);
}
$source=array('sourceID'=>1,'fileID'=>2,'parentID'=>3,'modifyTime'=>100,'name'=>'song.mp3','size'=>42);
$v=invoke('mediaVersion',$source,'lyrics');
check('key ordering does not change fingerprint',$v===invoke('mediaVersion',array_reverse($source,true),'lyrics'));
$source['fileID']=4;$v2=invoke('mediaVersion',$source,'lyrics');
check('source replacement changes fingerprint',$v!==$v2);
SourceStub::$rows=array(array('sourceID'=>8,'fileID'=>9,'name'=>'song.lrc','size'=>20,'modifyTime'=>101));
$v3=invoke('mediaVersion',$source,'lyrics');check('new sidecar changes fingerprint',$v2!==$v3);
SourceStub::$rows[0]['fileID']=10;check('sidecar edit changes fingerprint',$v3!==invoke('mediaVersion',$source,'lyrics'));
SourceStub::$rows=array(array('sourceID'=>11,'fileID'=>12,'name'=>'cover.jpg','size'=>100,'modifyTime'=>102));
$cover=invoke('mediaVersion',$source,'cover');SourceStub::$rows[0]['modifyTime']=103;
check('folder cover edit changes fingerprint',$cover!==invoke('mediaVersion',$source,'cover'));
$data=array('text'=>'manual','synced'=>array(),'offset'=>1500,'mediaVersion'=>$v,'origin'=>'import');
$parsed=invoke('detectAndParse',invoke('toLrc',$data));
check('manual archive provenance survives round trip',$parsed['origin']==='import' && $parsed['mediaVersion']===$v && $parsed['text']==='manual');
check('manual lyrics survive replacement',invoke('lyricsMatchVersion',$parsed,$v2,200));
$data['origin']='auto';check('automatic lyrics expire on replacement',!invoke('lyricsMatchVersion',$data,$v2,200));
check('current automatic lyrics retained',invoke('lyricsMatchVersion',$data,$v,100));
check('older legacy archive not reused after replacement',!invoke('lyricsMatchVersion',array('fileTime'=>100),$v,200));
