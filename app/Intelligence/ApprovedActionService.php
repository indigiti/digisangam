<?php
declare(strict_types=1);

namespace DigiSangam\Intelligence;

use DigiSangam\Automation\WorkflowRepository;
use DigiSangam\Communications\CampaignRepository;
use DigiSangam\Core\Storage\JsonFileStore;

final class ApprovedActionService
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function execute(array $proposal): array
    {
        if(($proposal['status']??'')!=='approved') throw new \RuntimeException('Only approved action proposals can execute.');
        $payload=(array)($proposal['payload']??[]);
        $eventId=trim((string)($proposal['event_id']??''));
        if($eventId==='') throw new \InvalidArgumentException('Approved action is missing event scope.');

        return match((string)($proposal['type']??'')){
            'campaign_draft'=>[
                'type'=>'campaign_draft',
                'record'=>(new CampaignRepository($this->store))->create($payload+[
                    'event_id'=>$eventId,
                    'name'=>$proposal['title']??'AI campaign draft',
                    'status'=>'draft',
                ]),
            ],
            'workflow_draft'=>[
                'type'=>'workflow_draft',
                'record'=>(new WorkflowRepository($this->store))->create($payload+[
                    'event_id'=>$eventId,
                    'name'=>$proposal['title']??'AI workflow draft',
                    'enabled'=>false,
                ]),
            ],
            default=>['type'=>'operator_note','record'=>null],
        };
    }
}
