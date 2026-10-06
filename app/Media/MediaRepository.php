<?php
declare(strict_types=1);

namespace DigiSangam\Media;

use DigiSangam\Core\Storage\JsonFileStore;

final class MediaRepository
{
    private const MIME_EXT=[
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg',
        'application/pdf'=>'pdf','text/plain'=>'txt'
    ];

    public function __construct(private readonly JsonFileStore $store) {}

    public function all(): array { return $this->store->read('media/index.json',[]); }
    public function find(string $id): ?array { foreach($this->all() as $row)if(($row['id']??'')===$id)return $row;return null; }

    public function saveUpload(array $file,string $eventId,string $kind='asset',bool $public=false): array
    {
        if($eventId==='') throw new \InvalidArgumentException('Event is required.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new \InvalidArgumentException('Upload failed.');
        $size=(int)($file['size']??0);
        if($size<=0||$size>10*1024*1024) throw new \InvalidArgumentException('File must be between 1 byte and 10 MB.');
        $tmp=(string)($file['tmp_name']??'');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'';
        if(!isset(self::MIME_EXT[$mime])) throw new \InvalidArgumentException('Unsupported file type.');
        $id='med_'.bin2hex(random_bytes(8));$ext=self::MIME_EXT[$mime];
        $dir=(defined('DIGISANGAM_STORAGE_ROOT')?DIGISANGAM_STORAGE_ROOT:dirname(__DIR__,2).'/storage').'/media';
        if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)) throw new \RuntimeException('Unable to create media storage.');
        $path=$dir.'/'.$id.'.'.$ext;
        if(!move_uploaded_file($tmp,$path) && !rename($tmp,$path)) throw new \RuntimeException('Unable to persist uploaded file.');
        $record=['id'=>$id,'event_id'=>$eventId,'kind'=>$kind,'name'=>(string)($file['name']??$id),'mime'=>$mime,'size'=>$size,'extension'=>$ext,'path'=>$path,'public'=>$public,'created_at'=>date(DATE_ATOM)];
        $rows=$this->all();array_unshift($rows,$record);$this->store->write('media/index.json',$rows);return $this->publicView($record);
    }

    public function publicView(array $row): array {
        $copy=$row;unset($copy['path']);$copy['url']='/api/v1/public/media/'.rawurlencode((string)$row['id']);return $copy;
    }

    public function stream(string $id): array {
        $row=$this->find($id);if(!$row) throw new \RuntimeException('Media not found.');
        return $row;
    }
}
