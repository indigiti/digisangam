<?php
declare(strict_types=1);

namespace DigiSangam\Media;

use DigiSangam\Core\Storage\JsonFileStore;

final class MediaCleanupService
{
    public function __construct(private readonly JsonFileStore $store,private readonly MediaRepository $media) {}

    public function run(int $olderThanSeconds=86400,int $limit=200): array
    {
        $olderThanSeconds=max(3600,$olderThanSeconds);
        $cutoff=time()-$olderThanSeconds;
        $referenced=$this->referencedMediaIds();
        $checked=0;$deleted=0;$bytes=0;

        foreach($this->media->all() as $row){
            if($checked>=$limit)break;
            $created=strtotime((string)($row['created_at']??''));
            if($created===false||$created>$cutoff)continue;
            $kind=(string)($row['kind']??'');
            if(!in_array($kind,['registration_file','accreditation_document','branding_logo','branding_cover','branding_poster'],true))continue;
            $checked++;
            $id=(string)($row['id']??'');
            if($id===''||isset($referenced[$id]))continue;
            $size=(int)($row['size']??0);
            if($this->media->delete($id)){$deleted++;$bytes+=$size;}
        }

        return ['checked'=>$checked,'deleted'=>$deleted,'bytes_reclaimed'=>$bytes,'older_than_seconds'=>$olderThanSeconds];
    }

    private function referencedMediaIds(): array
    {
        $ids=[];
        $collect=static function(mixed $value) use (&$ids,&$collect): void {
            if(is_array($value)){foreach($value as $item)$collect($item);return;}
            if(!is_string($value))return;
            if(str_starts_with($value,'med_'))$ids[$value]=true;
            if(preg_match_all('/med_[a-f0-9]{8,}/i',$value,$matches)){
                foreach($matches[0] as $id)$ids[$id]=true;
            }
        };

        foreach($this->store->read('attendees/index.json',[]) as $row)$collect($row['answers']??[]);
        foreach($this->store->read('accreditation/records.json',[]) as $row)$collect($row['documents']??[]);
        foreach($this->store->read('events/index.json',[]) as $row)$collect($row['branding']??[]);

        return $ids;
    }
}
