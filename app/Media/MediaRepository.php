<?php
declare(strict_types=1);

namespace DigiSangam\Media;

use DigiSangam\Core\Storage\JsonFileStore;

final class MediaRepository
{
    private const INDEX='media/index.json';
    private const MIME_EXT=[
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',
        'application/pdf'=>'pdf','text/plain'=>'txt',
    ];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array { return $this->store->read(self::INDEX,[]); }
    public function find(string $id): ?array { foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;return null; }

    public function saveUpload(array $file,string $eventId,string $kind='asset',bool $public=false): array
    {
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new \InvalidArgumentException('Upload failed.');
        $size=(int)($file['size']??0);
        if($size<=0||$size>10*1024*1024) throw new \InvalidArgumentException('File must be between 1 byte and 10 MB.');
        $tmp=(string)($file['tmp_name']??'');
        if(!is_file($tmp)) throw new \InvalidArgumentException('Uploaded file is missing.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'';
        if(!isset(self::MIME_EXT[$mime])) throw new \InvalidArgumentException('Unsupported file type.');

        $id='med_'.bin2hex(random_bytes(8));$ext=self::MIME_EXT[$mime];
        $dir=$this->mediaDirectory();
        if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)) throw new \RuntimeException('Unable to create media storage.');
        $path=$dir.'/'.$id.'.'.$ext;
        if(!move_uploaded_file($tmp,$path)&&!rename($tmp,$path)) throw new \RuntimeException('Unable to persist uploaded file.');

        $record=[
            'id'=>$id,'event_id'=>$eventId,'kind'=>$kind,'name'=>(string)($file['name']??$id),
            'mime'=>$mime,'size'=>$size,'extension'=>$ext,'path'=>$path,'public'=>$public,'created_at'=>date(DATE_ATOM),
        ];
        try{
            $this->store->transaction(self::INDEX,static function(array $rows) use ($record): array {
                array_unshift($rows,$record);return ['data'=>$rows,'result'=>null];
            },[]);
        }catch(\Throwable $e){
            @unlink($path);throw $e;
        }
        return $this->publicView($record);
    }

    public function delete(string $id): bool
    {
        $removed=$this->store->transaction(self::INDEX,static function(array $rows) use ($id): array {
            $record=null;$next=[];
            foreach($rows as $row){
                if(($row['id']??'')===$id){$record=$row;continue;}
                $next[]=$row;
            }
            return ['data'=>$next,'result'=>$record];
        },[]);
        if(!is_array($removed)) return false;
        $path=(string)($removed['path']??'');
        if($path!==''&&is_file($path)) @unlink($path);
        return true;
    }

    public function publicView(array $row): array
    {
        $copy=$row;unset($copy['path']);
        $base=$this->publicBaseUrl();
        $relative='api/v1/public/media/'.rawurlencode((string)$row['id']);
        $copy['url']=rtrim($base,'/').'/'.$relative;
        return $copy;
    }

    private function publicBaseUrl(): string
    {
        $configured=rtrim(trim((string)getenv('APP_URL')),'/');
        if($configured!=='') return $configured;

        $script=str_replace('\\\\','/',(string)($_SERVER['SCRIPT_NAME']??''));
        if($script!=='' && preg_match('#^(.*?)/api(?:/index\\.php)?$#',$script,$match)){
            $base=rtrim((string)($match[1]??''),'/');
            return $base!==''?$base:'/';
        }

        // DigiOps production contract deploys the public app under /digisangam/.
        return '/digisangam';
    }

    public function stream(string $id): array
    {
        $row=$this->find($id);
        if(!$row) throw new \RuntimeException('Media not found.');
        if(!is_file((string)($row['path']??''))) throw new \RuntimeException('Media file is missing.');
        return $row;
    }

    private function mediaDirectory(): string
    {
        return (defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__,2).'/storage').'/media';
    }
}
