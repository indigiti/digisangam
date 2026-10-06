<?php
declare(strict_types=1);

namespace DigiSangam\Reports;

use DigiSangam\Agenda\SessionRepository;
use DigiSangam\Agenda\SessionAttendanceRepository;
use DigiSangam\Accreditation\AccreditationRepository;
use DigiSangam\Attendees\AttendeeRepository;
use DigiSangam\Commerce\OrderRepository;
use DigiSangam\Core\Csv\CsvService;
use DigiSangam\Core\Storage\JsonFileStore;
use DigiSangam\Exhibitors\LeadRepository;
use DigiSangam\OnGround\CheckinRepository;

final class OperationalReportService
{
    public function __construct(private readonly JsonFileStore $store) {}

    public function summary(string $eventId): array
    {
        $att=$this->eventRows((new AttendeeRepository($this->store))->all(),$eventId);
        $orders=$this->eventRows((new OrderRepository($this->store))->all(),$eventId);
        $checkins=(new CheckinRepository($this->store))->all($eventId);
        $leads=$this->eventRows((new LeadRepository($this->store))->all(),$eventId);
        $accr=$this->eventRows((new AccreditationRepository($this->store))->all(),$eventId);
        $sessions=$this->eventRows((new SessionRepository($this->store))->all(),$eventId);
        $sessionAttendance=0;foreach($sessions as $s)$sessionAttendance+=count((new SessionAttendanceRepository($this->store))->all((string)$s['id']));
        $breakdown=static function(array $rows,string $field): array {
            $out=[];foreach($rows as $row){$key=(string)($row[$field]??'Unknown');$out[$key]=($out[$key]??0)+1;}arsort($out);return $out;
        };
        $sessionBreakdown=[];
        foreach($sessions as $session)$sessionBreakdown[(string)($session['title']??$session['id'])]=count((new SessionAttendanceRepository($this->store))->all((string)$session['id']));
        return [
            'registrations'=>count($att),
            'confirmed'=>count(array_filter($att,fn($x)=>($x['status']??'')==='Confirmed')),
            'checked_in'=>count($checkins),
            'paid_revenue'=>array_sum(array_map(fn($x)=>($x['status']??'')==='paid'?(int)($x['amount']??0):0,$orders)),
            'refunds'=>count(array_filter($orders,fn($x)=>($x['status']??'')==='refunded')),
            'leads'=>count($leads),'hot_leads'=>count(array_filter($leads,fn($x)=>($x['intent']??'')==='hot')),
            'accreditations'=>count($accr),'approved_accreditations'=>count(array_filter($accr,fn($x)=>in_array(($x['status']??''),['approved','activated'],true))),
            'session_entries'=>$sessionAttendance,
            'breakdowns'=>[
                'attendee_categories'=>$breakdown($att,'category'),
                'registration_sources'=>$breakdown($att,'source'),
                'payment_status'=>$breakdown($orders,'status'),
                'lead_intent'=>$breakdown($leads,'intent'),
                'accreditation_status'=>$breakdown($accr,'status'),
                'session_attendance'=>$sessionBreakdown,
            ],
        ];
    }

    public function export(string $eventId,string $type): string
    {
        $rows=match($type){
            'attendees'=>$this->eventRows((new AttendeeRepository($this->store))->all(),$eventId),
            'orders'=>$this->eventRows((new OrderRepository($this->store))->all(),$eventId),
            'checkins'=>(new CheckinRepository($this->store))->all($eventId),
            'leads'=>$this->eventRows((new LeadRepository($this->store))->all(),$eventId),
            'accreditation'=>$this->eventRows((new AccreditationRepository($this->store))->all(),$eventId),
            default=>throw new \InvalidArgumentException('Unsupported report type.'),
        };
        if($rows===[])return "";
        $flat=array_map(static function(array $row): array{
            $out=[];foreach($row as $k=>$v)$out[$k]=is_scalar($v)||$v===null?$v:json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);return $out;
        },$rows);
        return CsvService::encode($flat);
    }

    private function eventRows(array $rows,string $eventId): array { return array_values(array_filter($rows,fn($x)=>($x['event_id']??'')===$eventId)); }
}
