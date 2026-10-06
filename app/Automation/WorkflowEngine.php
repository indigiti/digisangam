<?php
declare(strict_types=1);

namespace DigiSangam\Automation;

use DigiSangam\Notifications\NotificationOutbox;

final class WorkflowEngine
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly NotificationOutbox $outbox,
    ) {}

    public function fire(string $eventName,array $context): array
    {
        $runs=[];
        foreach($this->workflows->all() as $workflow){
            if(empty($workflow['enabled']) || ($workflow['trigger']??'')!==$eventName) continue;
            if(!$this->conditionsPass((array)($workflow['conditions']??[]),$context)) continue;

            $delayMinutes=0;$queued=[];
            foreach((array)($workflow['actions']??[]) as $action){
                $type=(string)($action['type']??'');
                if($type==='wait'){
                    $delayMinutes+=max(0,(int)($action['minutes']??0));
                    continue;
                }
                if(!in_array($type,['email','whatsapp'],true)) continue;
                $recipient=$type==='email'
                    ? ['email'=>(string)($context['email']??'')]
                    : ['phone'=>(string)($context['phone']??'')];
                if(trim((string)array_values($recipient)[0])==='') continue;

                $notBefore=$delayMinutes>0 ? date(DATE_ATOM,time()+($delayMinutes*60)) : '';
                $queued[]=$this->outbox->queue(
                    $type,
                    (string)($action['template']??'registration_confirmation'),
                    $recipient,
                    $context+['workflow_id'=>$workflow['id']],
                    $notBefore
                );
            }

            $runs[]=['workflow_id'=>$workflow['id'],'name'=>$workflow['name'],'queued'=>count($queued)];
        }
        return ['trigger'=>$eventName,'matched'=>count($runs),'runs'=>$runs];
    }

    private function conditionsPass(array $conditions,array $context): bool
    {
        foreach($conditions as $condition){
            $field=(string)($condition['field']??'');
            $operator=(string)($condition['operator']??'equals');
            $expected=$condition['value']??null;
            $actual=$context[$field]??null;
            $ok=match($operator){
                'not_equals'=>$actual!==$expected,
                'contains'=>is_array($actual)?in_array($expected,$actual,true):str_contains((string)$actual,(string)$expected),
                'in'=>is_array($expected)&&in_array($actual,$expected,true),
                default=>$actual===$expected,
            };
            if(!$ok) return false;
        }
        return true;
    }
}
