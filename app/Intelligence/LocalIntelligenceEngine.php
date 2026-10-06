<?php
declare(strict_types=1);

namespace DigiSangam\Intelligence;

final class LocalIntelligenceEngine
{
    public function analyze(array $graph): array
    {
        $metrics=(array)($graph['metrics']??[]);
        $leadScores=$this->leadScores((array)($graph['nodes']['leads']??[]),(array)($graph['edges']??[]));
        $crowd=$this->crowd((array)($graph['nodes']['zones']??[]),(array)($metrics['zone_occupancy']??[]));
        $forecast=$this->forecast($metrics);
        $anomalies=$this->anomalies($metrics);
        $insights=$this->insights($forecast,$anomalies,$crowd,$leadScores);

        return [
            'generated_at'=>date(DATE_ATOM),
            'engine'=>'php_fallback',
            'forecast'=>$forecast,
            'anomalies'=>$anomalies,
            'lead_scores'=>$leadScores,
            'recommendations'=>$this->recommendations($graph),
            'crowd'=>$crowd,
            'insights'=>$insights,
        ];
    }

    public function copilot(string $question,array $graph): array
    {
        $analysis=$this->analyze($graph);
        $q=strtolower(trim($question));
        $m=(array)($graph['metrics']??[]);
        if(str_contains($q,'registration')||str_contains($q,'booking')){
            $answer=sprintf('There are %d registrations, %d confirmed, and the 7-day projection is %d.',
                (int)($m['registrations']??0),(int)($m['confirmed']??0),(int)($analysis['forecast']['registrations_7d']??0));
        }elseif(str_contains($q,'revenue')||str_contains($q,'payment')){
            $answer=sprintf('Recorded paid revenue is ₹%s and payment failure rate is %.1f%%.',
                number_format((int)($m['paid_revenue']??0)),((float)($m['payment_failure_rate']??0))*100);
        }elseif(str_contains($q,'crowd')||str_contains($q,'zone')||str_contains($q,'gate')){
            $risky=array_values(array_filter($analysis['crowd'],static fn(array $x): bool => in_array($x['risk'],['high','critical'],true)));
            $answer=$risky===[]?'No zones are currently above 80% configured capacity.':
                implode('; ',array_map(static fn(array $x): string => $x['name'].' is at '.$x['occupancy_pct'].'% ('.$x['risk'].'). '.$x['action'],array_slice($risky,0,3)));
        }elseif(str_contains($q,'lead')||str_contains($q,'sponsor')||str_contains($q,'exhibitor')){
            $hot=array_values(array_filter($analysis['lead_scores'],static fn(array $x): bool => ($x['ai_band']??'')==='hot'));
            $answer=count($hot).' leads are ranked hot'.($hot!==[]?'; top score is '.($hot[0]['ai_score']??0).'.':'.');
        }else{
            $answer=sprintf('Event overview: %d registrations, %d checked in, %d anomalies and %d hot leads.',
                (int)($m['registrations']??0),(int)($m['checked_in']??0),count($analysis['anomalies']),
                count(array_filter($analysis['lead_scores'],static fn(array $x): bool => ($x['ai_band']??'')==='hot')));
        }
        return ['answer'=>$answer,'suggested_actions'=>$this->actions($analysis),'evidence'=>['metrics'=>$m,'generated_at'=>$analysis['generated_at']]];
    }

    public function concierge(string $question,string $attendeeId,array $graph): array
    {
        $analysis=$this->analyze($graph);
        $attendee=null;
        foreach((array)($graph['nodes']['attendees']??[]) as $row) if(($row['id']??'')===$attendeeId){$attendee=$row;break;}
        if(!$attendee) return ['answer'=>'I could not find this attendee profile.','recommendations'=>[]];
        $recommendations=(array)($analysis['recommendations']['attendees'][$attendeeId]??[]);
        $q=strtolower($question);
        if(str_contains($q,'session')||str_contains($q,'agenda')){
            $titles=array_map(static fn(array $x): string => (string)($x['title']??''),(array)($recommendations['sessions']??[]));
            $answer=$titles?'Recommended sessions: '.implode(', ',array_slice($titles,0,3)): 'No session recommendations are available yet.';
        }elseif(str_contains($q,'exhibitor')||str_contains($q,'booth')){
            $names=array_map(static fn(array $x): string => trim(($x['name']??'').' '.(!empty($x['booth'])?'('.$x['booth'].')':'')),(array)($recommendations['exhibitors']??[]));
            $answer=$names?'Suggested exhibitors: '.implode(', ',array_slice($names,0,3)):'No exhibitor recommendations are available yet.';
        }else{
            $answer='Welcome '.($attendee['name']??'').'. Your registration status is '.($attendee['status']??'Unknown').' and category is '.($attendee['category']??'General').'.';
        }
        return ['answer'=>$answer,'recommendations'=>$recommendations];
    }

    private function forecast(array $m): array
    {
        $trend=array_map('floatval',(array)($m['registration_trend']??[]));
        $current=(int)($m['registrations']??0);
        $velocity=0.0;
        if(count($trend)>=2) $velocity=($trend[count($trend)-1]-$trend[0])/max(1,count($trend)-1);
        elseif(count($trend)===1) $velocity=$trend[0];
        $projected=max($current,(int)round($current+max(0,$velocity)*7));
        $capacity=(int)($m['event_capacity']??0);
        if($capacity>0) $projected=min($projected,$capacity);
        return [
            'registrations_7d'=>$projected,
            'daily_velocity'=>round($velocity,2),
            'expected_footfall'=>(int)round($projected*(float)($m['checkin_rate']??0)),
            'confidence'=>count($trend)>=10?'high':(count($trend)>=5?'medium':'low'),
        ];
    }

    private function anomalies(array $m): array
    {
        $out=[];
        $failure=(float)($m['payment_failure_rate']??0);
        if($failure>=0.08) $out[]=['type'=>'payment_failures','severity'=>$failure>=0.15?'high':'medium','message'=>'Payment failure rate is '.round($failure*100,1).'%.','score'=>$failure];
        $registrations=max(1,(int)($m['registrations']??0));
        $pending=(int)($m['pending_approvals']??0);
        if($pending/$registrations>=0.12) $out[]=['type'=>'approval_backlog','severity'=>'medium','message'=>$pending.' registrations are waiting for approval.','score'=>$pending/$registrations];
        return $out;
    }

    private function leadScores(array $leads,array $edges): array
    {
        $meetings=[];
        foreach($edges as $edge){
            if(($edge['type']??'')!=='meeting') continue;
            $key=($edge['from']??'').'|'.($edge['to']??'');
            $meetings[$key]=($meetings[$key]??0)+1;
        }
        $rows=[];
        foreach($leads as $lead){
            $score=(int)($lead['score']??50);
            $intent=strtolower((string)($lead['intent']??''));
            $score+=match($intent){'hot'=>22,'warm'=>10,'low'=>-8,default=>0};
            $score+=min(15,(int)($meetings[($lead['exhibitor_id']??'').'|'.($lead['attendee_id']??'')]??0)*8);
            if(trim((string)($lead['notes']??''))!=='') $score+=4;
            $score=max(0,min(100,$score));
            $rows[]=$lead+['ai_score'=>$score,'ai_band'=>$score>=80?'hot':($score>=55?'warm':'low')];
        }
        usort($rows,static fn(array $a,array $b): int => ($b['ai_score']??0)<=>($a['ai_score']??0));
        return $rows;
    }

    private function recommendations(array $graph): array
    {
        $sessions=(array)($graph['nodes']['sessions']??[]);
        $exhibitors=(array)($graph['nodes']['exhibitors']??[]);
        $result=[];
        foreach((array)($graph['nodes']['attendees']??[]) as $attendee){
            $category=strtolower((string)($attendee['category']??'general'));
            $tracks=match($category){
                'vip'=>['Main','Innovation'],
                'speaker'=>['Main'],
                'sponsor'=>['Business','Innovation','Main'],
                default=>['Main','Innovation'],
            };
            $matches=array_values(array_filter($sessions,static fn(array $s): bool => in_array($s['track']??'',$tracks,true)));
            $result[$attendee['id']]=[
                'sessions'=>array_map(static fn(array $s): array => ['id'=>$s['id']??'','title'=>$s['title']??'','track'=>$s['track']??''],array_slice($matches,0,4)),
                'exhibitors'=>array_map(static fn(array $e): array => ['id'=>$e['id']??'','name'=>$e['name']??'','booth'=>$e['booth']??''],array_slice($exhibitors,0,3)),
            ];
        }
        return ['attendees'=>$result];
    }

    private function crowd(array $zones,array $occupancy): array
    {
        $rows=[];
        foreach($zones as $zone){
            $capacity=max(0,(int)($zone['capacity']??0));
            $current=max(0,(int)($occupancy[$zone['id']??'']??0));
            $ratio=$capacity>0?$current/$capacity:0;
            $risk=$ratio>=0.95?'critical':($ratio>=0.8?'high':($ratio>=0.6?'moderate':'normal'));
            $rows[]=['zone_id'=>$zone['id']??'','name'=>$zone['name']??'','capacity'=>$capacity,'occupancy'=>$current,'occupancy_pct'=>round($ratio*100,1),'risk'=>$risk,'action'=>$this->crowdAction($risk)];
        }
        return $rows;
    }

    private function insights(array $forecast,array $anomalies,array $crowd,array $leads): array
    {
        $out=[];
        if(($forecast['daily_velocity']??0)>0) $out[]=['priority'=>'info','title'=>'Registration momentum','message'=>'Projected registrations in 7 days: '.($forecast['registrations_7d']??0).'.'];
        $hot=count(array_filter($leads,static fn(array $x): bool => ($x['ai_band']??'')==='hot'));
        if($hot>0) $out[]=['priority'=>'high','title'=>'High-intent sponsor leads','message'=>$hot.' leads are currently ranked hot and should be followed up.'];
        foreach($crowd as $zone) if(in_array($zone['risk'],['high','critical'],true)) $out[]=['priority'=>$zone['risk']==='critical'?'critical':'high','title'=>$zone['name'].' congestion','message'=>$zone['occupancy_pct'].'% of configured capacity is occupied. '.$zone['action']];
        foreach(array_slice($anomalies,0,3) as $a) $out[]=['priority'=>$a['severity'],'title'=>ucwords(str_replace('_',' ',$a['type'])),'message'=>$a['message']];
        if($out===[]) $out[]=['priority'=>'info','title'=>'Operations stable','message'=>'No material operational anomalies are currently detected.'];
        return array_slice($out,0,8);
    }

    private function actions(array $analysis): array
    {
        $out=[];
        foreach($analysis['crowd'] as $zone) if(in_array($zone['risk'],['high','critical'],true)) $out[]=$zone['action'];
        if(array_filter($analysis['lead_scores'],static fn(array $x): bool => ($x['ai_band']??'')==='hot')) $out[]='Prioritize follow-up with hot exhibitor leads.';
        if($analysis['anomalies']!==[]) $out[]='Review detected operational anomalies.';
        return array_slice($out,0,4);
    }

    private function crowdAction(string $risk): string
    {
        return match($risk){
            'critical'=>'Stop additional entry and redirect attendees to another zone.',
            'high'=>'Open an alternate gate or redirect traffic.',
            'moderate'=>'Monitor entry rate and prepare an alternate route.',
            default=>'No intervention required.',
        };
    }
}
