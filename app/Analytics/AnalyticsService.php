<?php
declare(strict_types=1);

namespace DigiSangam\Analytics;

final class AnalyticsService
{
    public function dashboard(): array
    {
        return [
            'event'=>['id'=>'evt_001','name'=>'Tech & Innovation Summit 2026','date'=>'12–14 Oct 2026','location'=>'Mumbai, India','status'=>'Live'],
            'stats'=>[
                ['label'=>'Registrations','value'=>'18,442','delta'=>'+18%','tone'=>'blue'],
                ['label'=>'Confirmed','value'=>'16,923','delta'=>'+12%','tone'=>'mint'],
                ['label'=>'Checked-in','value'=>'9,382','delta'=>'+25%','tone'=>'cyan'],
                ['label'=>'Revenue','value'=>'₹1.82 Cr','delta'=>'+20%','tone'=>'rose'],
            ],
            'trend'=>[22,34,29,47,42,55,49,68,61,79,74,92],
            'categories'=>[
                ['label'=>'General','value'=>42,'color'=>'#4f46e5'],['label'=>'VIP','value'=>18,'color'=>'#10b981'],
                ['label'=>'Speaker','value'=>12,'color'=>'#f59e0b'],['label'=>'Sponsor','value'=>16,'color'=>'#8b5cf6'],
                ['label'=>'Media','value'=>6,'color'=>'#f43f5e'],['label'=>'Other','value'=>6,'color'=>'#94a3b8'],
            ],
        ];
    }
}
