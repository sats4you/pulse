<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Operator;

use PDO;

final readonly class PdoDashboardStore implements DashboardStore
{
    public function __construct(private PDO $db){}
    public function data():DashboardData
    {
        $flow=['created'=>0,'opened'=>0,'started'=>0,'completed'=>0,'participant_ended'=>0,'payment_requested'=>0,'sent_confirmed'=>0,'received_confirmed'=>0];
        $wallets=['wos'=>0,'phoenix'=>0,'other'=>0];$survey=['yes'=>0,'no'=>0,'unsure'=>0,'skipped'=>0];$surveyVersion='2026-08-23-v1';
        $surveyByVersion=[];$surveyUpdated=[];
        $rows=$this->db->query('SELECT metric_key,content_version,answer_key,SUM(count_value) AS total,MAX(updated_at) AS last_updated FROM first_sats_anonymous_counters GROUP BY metric_key,content_version,answer_key')->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){$key=(string)$row['metric_key'];$answer=(string)$row['answer_key'];$count=(int)$row['total'];
            if(array_key_exists($key,$flow)){
                if($key==='payment_requested'&&array_key_exists($answer,$wallets)){$flow[$key]+=$count;$wallets[$answer]+=$count;}
                elseif($key!=='payment_requested'&&$answer==='')$flow[$key]+=$count;
            }
            if($key==='fraction_known_before'&&array_key_exists($answer,$survey)){
                $version=(string)$row['content_version'];
                $surveyByVersion[$version]??=['yes'=>0,'no'=>0,'unsure'=>0,'skipped'=>0];
                $surveyByVersion[$version][$answer]+=$count;
                $updated=(string)$row['last_updated'];
                if(!isset($surveyUpdated[$version])||$updated>$surveyUpdated[$version])$surveyUpdated[$version]=$updated;
            }
        }
        foreach($surveyUpdated as $version=>$updated){
            if(!isset($surveyUpdated[$surveyVersion])||$updated>$surveyUpdated[$surveyVersion]||($updated===$surveyUpdated[$surveyVersion]&&$version>$surveyVersion))$surveyVersion=$version;
        }
        if(isset($surveyByVersion[$surveyVersion]))$survey=$surveyByVersion[$surveyVersion];
        return new DashboardData($flow,$wallets,$survey,$surveyVersion);
    }
}
