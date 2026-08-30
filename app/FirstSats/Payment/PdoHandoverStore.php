<?php
declare(strict_types=1);
namespace Sats4you\Pulse\FirstSats\Payment;
use DateTimeImmutable;use PDO;
final readonly class PdoHandoverStore implements HandoverStore
{
 public function __construct(private PDO $db){}
 public function find(string $id):?HandoverState{$s=$this->db->prepare('SELECT * FROM first_sats_handover_states WHERE invitation_id=:id');$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?new HandoverState((string)$r['invitation_id'],$this->date($r['sent_at']),$this->date($r['received_at']),$this->date($r['not_received_at']),$this->date($r['closed_without_confirmation_at']??null)):null;}
 public function markSent(string $id,DateTimeImmutable $at):HandoverState{$this->upsert($id,'sent_at',$at);return $this->find($id)??throw new \RuntimeException();}
 public function markNotReceived(string $id,DateTimeImmutable $at):HandoverState{$this->upsert($id,'not_received_at',$at);return $this->find($id)??throw new \RuntimeException();}
 public function confirmReceived(string $id,DateTimeImmutable $at):HandoverState{$this->db->beginTransaction();try{$this->upsert($id,'received_at',$at);$s=$this->db->prepare('DELETE FROM first_sats_payment_requests WHERE invitation_id=:id');$s->execute(['id'=>$id]);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}return $this->find($id)??throw new \RuntimeException();}
 private function upsert(string $id,string $column,DateTimeImmutable $at):void{$value=$at->format('Y-m-d H:i:s.u');$driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);$sql=$driver==='sqlite'?"INSERT INTO first_sats_handover_states (invitation_id,$column) VALUES (:id,:value) ON CONFLICT(invitation_id) DO UPDATE SET $column=excluded.$column":"INSERT INTO first_sats_handover_states (invitation_id,$column) VALUES (:id,:value) ON DUPLICATE KEY UPDATE $column=VALUES($column)";$s=$this->db->prepare($sql);$s->execute(['id'=>$id,'value'=>$value]);}
 private function date(mixed $v):?DateTimeImmutable{return $v===null?null:new DateTimeImmutable((string)$v);}
}
