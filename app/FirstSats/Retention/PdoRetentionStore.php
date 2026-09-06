<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Retention;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class PdoRetentionStore implements RetentionStore
{
    public function __construct(private PDO $db){}

    public function closeDueHandovers(DateTimeImmutable $now):array
    {
        $cutoff=$now->sub(new DateInterval('P14D'));
        $s=$this->db->prepare(<<<'SQL'
            SELECT i.id,i.stage_four_completed_at
            FROM first_sats_invitations i
            LEFT JOIN first_sats_handover_states h ON h.invitation_id=i.id
            WHERE i.status='accepted' AND i.sats_amount>0
              AND i.stage_four_completed_at IS NOT NULL AND i.stage_four_completed_at<=:cutoff
              AND h.received_at IS NULL AND h.closed_without_confirmation_at IS NULL
            SQL);
        $s->execute(['cutoff'=>$this->date($cutoff)]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $closed=0;$deleted=0;$this->db->beginTransaction();
        try{
            foreach($rows as $row){
                $id=(string)$row['id'];$at=(new DateTimeImmutable((string)$row['stage_four_completed_at']))->add(new DateInterval('P14D'));
                $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
                $sql=$driver==='sqlite'
                    ?'INSERT INTO first_sats_handover_states (invitation_id,closed_without_confirmation_at) VALUES (:id,:at) ON CONFLICT(invitation_id) DO UPDATE SET closed_without_confirmation_at=excluded.closed_without_confirmation_at WHERE received_at IS NULL AND closed_without_confirmation_at IS NULL'
                    :'INSERT INTO first_sats_handover_states (invitation_id,closed_without_confirmation_at) VALUES (:id,:at) ON DUPLICATE KEY UPDATE closed_without_confirmation_at=IF(received_at IS NULL AND closed_without_confirmation_at IS NULL,VALUES(closed_without_confirmation_at),closed_without_confirmation_at)';
                $up=$this->db->prepare($sql);$up->execute(['id'=>$id,'at'=>$this->date($at)]);$closed++;
                $del=$this->db->prepare('DELETE FROM first_sats_payment_requests WHERE invitation_id=:id');$del->execute(['id'=>$id]);$deleted+=$del->rowCount();
                $schedule=$this->db->prepare('UPDATE first_sats_invitations SET delete_at=:delete_at WHERE id=:id AND delete_at IS NULL');
                $schedule->execute(['delete_at'=>$this->date($at->add(new DateInterval('P30D'))),'id'=>$id]);
            }
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        return ['closed'=>$closed,'payment_requests_deleted'=>$deleted];
    }

    public function scheduleDueInvitations(DateTimeImmutable $now):int
    {
        $scheduled=0;
        $received=$this->db->query("SELECT i.id,h.received_at FROM first_sats_invitations i JOIN first_sats_handover_states h ON h.invitation_id=i.id WHERE h.received_at IS NOT NULL AND i.delete_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach($received as $row){$scheduled+=$this->schedule((string)$row['id'],(new DateTimeImmutable((string)$row['received_at']))->add(new DateInterval('P30D')));}
        $zero=$this->db->query("SELECT id,stage_four_completed_at FROM first_sats_invitations WHERE status='accepted' AND sats_amount=0 AND stage_four_completed_at IS NOT NULL AND delete_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach($zero as $row){$scheduled+=$this->schedule((string)$row['id'],(new DateTimeImmutable((string)$row['stage_four_completed_at']))->add(new DateInterval('P30D')));}
        $expired=$this->db->prepare("SELECT id,status,accept_until,complete_until FROM first_sats_invitations WHERE delete_at IS NULL AND ((status='open' AND accept_until<:open_now) OR (status='accepted' AND stage_four_completed_at IS NULL AND complete_until<:accepted_now))");
        $formattedNow=$this->date($now);
        $expired->execute(['open_now'=>$formattedNow,'accepted_now'=>$formattedNow]);
        foreach($expired->fetchAll(PDO::FETCH_ASSOC) as $row){$base=$row['status']==='open'?$row['accept_until']:$row['complete_until'];$scheduled+=$this->schedule((string)$row['id'],(new DateTimeImmutable((string)$base))->add(new DateInterval('P30D')));}
        return $scheduled;
    }

    public function deleteDueInvitations(DateTimeImmutable $now):int
    {
        $s=$this->db->prepare('DELETE FROM first_sats_invitations WHERE delete_at IS NOT NULL AND delete_at<=:now');$s->execute(['now'=>$this->date($now)]);return $s->rowCount();
    }

    private function schedule(string $id,DateTimeImmutable $deleteAt):int
    {
        $s=$this->db->prepare('UPDATE first_sats_invitations SET delete_at=:delete_at WHERE id=:id AND delete_at IS NULL');$s->execute(['delete_at'=>$this->date($deleteAt),'id'=>$id]);return $s->rowCount();
    }

    private function date(DateTimeImmutable $at):string{return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
}
