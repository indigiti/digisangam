<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$public=$root.'/public';
if(!is_dir($public) || !is_file($public.'/index.html')){
    fwrite(STDERR,"public build missing; run frontend build first\n");
    exit(1);
}

$out=$root.'/release';

$remove=function(string $path) use (&$remove): void {
    if(!file_exists($path) && !is_link($path)) return;
    if(is_file($path) || is_link($path)){ @unlink($path); return; }
    foreach(array_diff(scandir($path)?:[],['.','..']) as $name) $remove($path.'/'.$name);
    @rmdir($path);
};

$copy=function(string $src,string $dst) use (&$copy): void {
    if(is_dir($src)){
        if(!is_dir($dst) && !mkdir($dst,0755,true) && !is_dir($dst)) throw new RuntimeException('MKDIR_FAILED_'.$dst);
        foreach(array_diff(scandir($src)?:[],['.','..']) as $name){
            if(in_array($name,['.venv','__pycache__'],true)) continue;
            $copy($src.'/'.$name,$dst.'/'.$name);
        }
        return;
    }
    if(!is_dir(dirname($dst)) && !mkdir(dirname($dst),0755,true) && !is_dir(dirname($dst))) throw new RuntimeException('MKDIR_FAILED_'.dirname($dst));
    if(!copy($src,$dst)) throw new RuntimeException('COPY_FAILED_'.$src);
};

$remove($out);
mkdir($out,0755,true);

$copy($public,$out.'/public');
$copy($root.'/app',$out.'/private/app');
$copy($root.'/scripts',$out.'/private/scripts');
$copy($root.'/intelligence',$out.'/private/intelligence');

$sourceSha=(string)(getenv('GITHUB_SHA')?:'local');
$build=[
    'schema'=>'DIGIOPS-RELEASE/1',
    'name'=>'DigiSangam',
    'version'=>'3.0.0',
    'builtAt'=>date(DATE_ATOM),
    'sourceSha'=>$sourceSha,
    'branch'=>(string)(getenv('GITHUB_REF_NAME')?:'local'),
    'ciRunNumber'=>(string)(getenv('GITHUB_RUN_NUMBER')?:''),
    'ciRunId'=>(string)(getenv('GITHUB_RUN_ID')?:''),
    'ciRunAttempt'=>(string)(getenv('GITHUB_RUN_ATTEMPT')?:''),
    'public'=>'public',
    'private'=>'private',
    'publicPath'=>'public_html/digisangam/',
    'privatePath'=>'private_html/digisangam/',
    'persistentPaths'=>['storage/'],
];

file_put_contents($out.'/RELEASE.json',json_encode($build,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
if(!is_dir($out.'/private/build')) mkdir($out.'/private/build',0755,true);
file_put_contents($out.'/private/build/release.json',json_encode($build,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);

echo "DigiSangam DigiOps release built for {$sourceSha}\n";
