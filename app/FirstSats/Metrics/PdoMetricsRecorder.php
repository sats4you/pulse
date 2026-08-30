<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Metrics;

use DateTimeImmutable;
use PDO;

final readonly class PdoMetricsRecorder implements MetricsRecorder
{
    private const FLOW_VERSION='flow-2026-08-23-v1';
    private const KEYS=['created','opened','started','completed','participant_ended','payment_requested','sent_confirmed','received_confirmed'];

    public function __construct(private PDO $db){}

    public function record(string $publicId,string $metricKey,DateTimeImmutable $at,string $answerKey=''):bool
    {
        if(!in_array($metricKey,self::KEYS,true))throw new \InvalidArgumentException('Unsupported metric.');
        if($metricKey==='payment_requested'){
            if(!in_array($answerKey,['wos','phoenix','other'],true))throw new \InvalidArgumentException('Unsupported metric answer.');
        }elseif($answerKey!=='')throw new \InvalidArgumentException('Metric does not accept an answer.');
        $find=$this->db->prepare('SELECT id,locale FROM first_sats_invitations WHERE public_id=:public_id');$find->execute(['public_id'=>$publicId]);$invitation=$find->fetch(PDO::FETCH_ASSOC);
        if(!is_array($invitation))return false;
        $this->db->beginTransaction();
        try{
            $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $insertSql=$driver==='sqlite'
                ?'INSERT OR IGNORE INTO first_sats_metric_contributions (invitation_id,metric_key,content_version,contributed_at) VALUES (:id,:metric,:version,:at)'
                :'INSERT IGNORE INTO first_sats_metric_contributions (invitation_id,metric_key,content_version,contributed_at) VALUES (:id,:metric,:version,:at)';
            $marker=$this->db->prepare($insertSql);$marker->execute(['id'=>$invitation['id'],'metric'=>$metricKey,'version'=>self::FLOW_VERSION,'at'=>$this->date($at)]);
            if($marker->rowCount()!==1){$this->db->commit();return false;}
            $counter=['metric'=>$metricKey,'version'=>self::FLOW_VERSION,'locale'=>$invitation['locale'],'answer'=>$answerKey,'at'=>$this->date($at)];
            $update=$this->db->prepare('UPDATE first_sats_anonymous_counters SET count_value=count_value+1,updated_at=:at WHERE metric_key=:metric AND content_version=:version AND locale=:locale AND answer_key=:answer');$update->execute($counter);
            if($update->rowCount()===0){$insert=$this->db->prepare('INSERT INTO first_sats_anonymous_counters (metric_key,content_version,locale,answer_key,count_value,updated_at) VALUES (:metric,:version,:locale,:answer,1,:at)');$insert->execute($counter);}
            $this->db->commit();return true;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function date(DateTimeImmutable $at):string{return $at->format('Y-m-d H:i:s.u');}
}
