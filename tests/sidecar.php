<?php
// Exercises real matching and read order with a fake file store, not the user library.
class PluginBase {}
function _get($a,$k,$d=null){return is_array($a)&&isset($a[$k])?$a[$k]:$d;}
class StubModel {
    public function where($x){return $this;}
    public function select(){return array(array('name'=>'Love.lrc','sourceID'=>1),array('name'=>'Love Story.zh.lrc','sourceID'=>2),array('name'=>'Love Story.lrc','sourceID'=>3));}
}
function Model($name){return new StubModel;}
class KodIO {public static function make($id){return $id;}}
class IO {
    public static $content = array(1=>'fuzzy',2=>'language variant',3=>'exact');
    public static function getContent($id){return self::$content[$id];}
}
require dirname(__DIR__).'/app.php';
$p=new musicPlugin;$r=new ReflectionMethod($p,'sidecarLyrics');
$source=array('parentID'=>10,'name'=>'Love Story.mp3','targetID'=>7);$track=array('title'=>'Love Story');
foreach(array(3=>'exact',2=>'language variant',1=>'fuzzy') as $id=>$expected){
    $actual=$r->invoke($p,$source,$track);
    if($actual!==$expected)throw new RuntimeException("Expected $expected, got $actual");
    echo "ok $expected match in priority order\n";
    IO::$content[$id]='';
}
$r=new ReflectionMethod($p,'sidecarRank');
if($r->invoke($p,'unrelated','love story','love story')!==-1)throw new RuntimeException('unrelated match');
echo "ok unrelated lyrics excluded\n";
