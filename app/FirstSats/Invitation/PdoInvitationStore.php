<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateTimeImmutable;
use PDO;

final readonly class PdoInvitationStore implements InvitationStore
{
    public function __construct(private PDO $connection)
    {
    }

    public function create(Invitation $i): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
            INSERT INTO first_sats_invitations (
                id, public_id, locale, inviter_label, invitee_label, personal_message, sats_amount,
                invitation_access_digest, invitation_access_version, management_access_digest,
                management_access_version, recovery_digest, recovery_version, status,
                inviter_adult_attested, inviter_terms_version, inviter_terms_accepted_at,
                created_at, accept_until, updated_at
            ) VALUES (
                :id, :public_id, :locale, :inviter_label, :invitee_label, :personal_message, :sats_amount,
                :invitation_digest, :invitation_version, :management_digest, :management_version,
                :recovery_digest, :recovery_version, :status, 1, :terms_version, :terms_accepted_at,
                :created_at, :accept_until, :updated_at
            )
            SQL);
        foreach ([
            'invitation_digest' => $i->invitationAccessDigest,
            'management_digest' => $i->managementAccessDigest,
            'recovery_digest' => $i->recoveryDigest,
        ] as $name => $digest) {
            $statement->bindValue(':' . $name, $digest, PDO::PARAM_LOB);
        }
        $values = [
            'id' => $i->id, 'public_id' => $i->publicId, 'locale' => $i->locale,
            'inviter_label' => $i->inviterLabel, 'invitee_label' => $i->inviteeLabel,
            'personal_message' => $i->personalMessage, 'sats_amount' => $i->satsAmount,
            'invitation_version' => $i->invitationAccessVersion,
            'management_version' => $i->managementAccessVersion, 'recovery_version' => $i->recoveryVersion,
            'status' => $i->status, 'terms_version' => $i->termsVersion,
            'terms_accepted_at' => $this->date($i->termsAcceptedAt), 'created_at' => $this->date($i->createdAt),
            'accept_until' => $this->date($i->acceptUntil), 'updated_at' => $this->date($i->createdAt),
        ];
        foreach ($values as $name => $value) {
            $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $statement->bindValue(':' . $name, $value, $type);
        }
        $statement->execute();
    }

    public function findByPublicId(string $publicId): ?Invitation
    {
        $statement = $this->connection->prepare('SELECT * FROM first_sats_invitations WHERE public_id = :public_id');
        $statement->execute(['public_id' => $publicId]);
        $r = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) {
            return null;
        }

        return new Invitation(
            (string) $r['id'], (string) $r['public_id'], (string) $r['locale'],
            (string) $r['inviter_label'], (string) $r['invitee_label'],
            $r['personal_message'] === null ? null : (string) $r['personal_message'], (int) $r['sats_amount'],
            (string) $r['invitation_access_digest'], (int) $r['invitation_access_version'],
            (string) $r['management_access_digest'], (int) $r['management_access_version'],
            (string) $r['recovery_digest'], (int) $r['recovery_version'], (string) $r['status'],
            (string) $r['inviter_terms_version'], new DateTimeImmutable((string) $r['inviter_terms_accepted_at']),
            new DateTimeImmutable((string) $r['created_at']), new DateTimeImmutable((string) $r['accept_until']),
            $r['participant_terms_version'] === null ? null : (string) $r['participant_terms_version'],
            $this->optionalDate($r['participant_terms_accepted_at']),
            $this->optionalDate($r['accepted_at']),
            $this->optionalDate($r['complete_until']),
            (string) $r['learning_content_version'],
            $this->optionalDate($r['stage_one_completed_at']),
            (string) $r['fraction_survey_state'],
            $this->optionalDate($r['stage_two_completed_at']),
            $this->optionalDate($r['stage_three_completed_at']),
            $this->optionalDate($r['stage_four_completed_at']),
            $r['wallet_path'] === null ? null : (string) $r['wallet_path'],
        );
    }

    public function accept(string $publicId, int $expectedAccessVersion, string $newAccessDigest, string $participantTermsVersion, DateTimeImmutable $acceptedAt, DateTimeImmutable $completeUntil): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET invitation_access_digest = :digest,
                invitation_access_version = invitation_access_version + 1,
                status = 'accepted',
                participant_adult_attested = 1,
                participant_terms_version = :terms_version,
                participant_terms_accepted_at = :terms_accepted_at,
                accepted_at = :accepted_at,
                complete_until = :complete_until,
                updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'open'
              AND accept_until >= :accept_checked_at
            SQL);
        $statement->bindValue(':digest', $newAccessDigest, PDO::PARAM_LOB);
        $statement->bindValue(':terms_version', $participantTermsVersion);
        $statement->bindValue(':terms_accepted_at', $this->date($acceptedAt));
        $statement->bindValue(':accepted_at', $this->date($acceptedAt));
        $statement->bindValue(':complete_until', $this->date($completeUntil));
        $statement->bindValue(':updated_at', $this->date($acceptedAt));
        $statement->bindValue(':public_id', $publicId);
        $statement->bindValue(':expected_version', $expectedAccessVersion, PDO::PARAM_INT);
        $statement->bindValue(':accept_checked_at', $this->date($acceptedAt));
        $statement->execute();

        return $statement->rowCount() === 1 ? $this->findByPublicId($publicId) : null;
    }

    public function rotateInvitationAccess(string $publicId, int $expectedAccessVersion, string $newAccessDigest, DateTimeImmutable $updatedAt): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET invitation_access_digest = :digest,
                invitation_access_version = invitation_access_version + 1,
                updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'accepted'
            SQL);
        $statement->bindValue(':digest', $newAccessDigest, PDO::PARAM_LOB);
        $statement->bindValue(':updated_at', $this->date($updatedAt));
        $statement->bindValue(':public_id', $publicId);
        $statement->bindValue(':expected_version', $expectedAccessVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1 ? $this->findByPublicId($publicId) : null;
    }

    public function rotateManagementRecovery(string $publicId, int $expectedManagementVersion, int $expectedRecoveryVersion, string $newManagementDigest, string $newRecoveryDigest, DateTimeImmutable $updatedAt): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET management_access_digest = :management_digest,
                management_access_version = management_access_version + 1,
                recovery_digest = :recovery_digest,
                recovery_version = recovery_version + 1,
                updated_at = :updated_at
            WHERE public_id = :public_id
              AND management_access_version = :management_version
              AND recovery_version = :recovery_version
            SQL);
        $statement->bindValue(':management_digest', $newManagementDigest, PDO::PARAM_LOB);
        $statement->bindValue(':recovery_digest', $newRecoveryDigest, PDO::PARAM_LOB);
        $statement->bindValue(':updated_at', $this->date($updatedAt));
        $statement->bindValue(':public_id', $publicId);
        $statement->bindValue(':management_version', $expectedManagementVersion, PDO::PARAM_INT);
        $statement->bindValue(':recovery_version', $expectedRecoveryVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1 ? $this->findByPublicId($publicId) : null;
    }

    public function extendOpen(string $publicId,int $expectedManagementVersion,DateTimeImmutable $acceptUntil,DateTimeImmutable $updatedAt):?Invitation
    {
        return $this->updateDeadline($publicId,$expectedManagementVersion,'open','accept_until',$acceptUntil,$updatedAt);
    }

    public function extendAccepted(string $publicId,int $expectedManagementVersion,DateTimeImmutable $completeUntil,DateTimeImmutable $updatedAt):?Invitation
    {
        return $this->updateDeadline($publicId,$expectedManagementVersion,'accepted','complete_until',$completeUntil,$updatedAt);
    }

    public function revokeOpen(string $publicId,int $expectedManagementVersion,string $discardedInvitationDigest,DateTimeImmutable $updatedAt,DateTimeImmutable $deleteAt):?Invitation
    {
        $s=$this->connection->prepare("UPDATE first_sats_invitations SET status='revoked', invitation_access_digest=:digest, invitation_access_version=invitation_access_version+1, updated_at=:updated_at, delete_at=:delete_at WHERE public_id=:public_id AND management_access_version=:management_version AND status='open'");
        $s->bindValue(':digest',$discardedInvitationDigest,PDO::PARAM_LOB);$s->bindValue(':updated_at',$this->date($updatedAt));$s->bindValue(':delete_at',$this->date($deleteAt));$s->bindValue(':public_id',$publicId);$s->bindValue(':management_version',$expectedManagementVersion,PDO::PARAM_INT);$s->execute();
        return $s->rowCount()===1?$this->findByPublicId($publicId):null;
    }

    public function resetParticipation(string $publicId,int $expectedManagementVersion,string $newInvitationDigest,DateTimeImmutable $acceptUntil,DateTimeImmutable $updatedAt):?Invitation
    {
        $s=$this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations SET
              status='open', invitation_access_digest=:digest, invitation_access_version=invitation_access_version+1,
              participant_adult_attested=NULL, participant_terms_version=NULL, participant_terms_accepted_at=NULL,
              accepted_at=NULL, complete_until=NULL, stage_one_completed_at=NULL, fraction_survey_state='pending',
              stage_two_completed_at=NULL, stage_three_completed_at=NULL, stage_four_completed_at=NULL, wallet_path=NULL,
              accept_until=:accept_until, updated_at=:updated_at, delete_at=NULL
            WHERE public_id=:public_id AND management_access_version=:management_version AND status='accepted'
            SQL);
        $s->bindValue(':digest',$newInvitationDigest,PDO::PARAM_LOB);$s->bindValue(':accept_until',$this->date($acceptUntil));$s->bindValue(':updated_at',$this->date($updatedAt));$s->bindValue(':public_id',$publicId);$s->bindValue(':management_version',$expectedManagementVersion,PDO::PARAM_INT);$s->execute();
        return $s->rowCount()===1?$this->findByPublicId($publicId):null;
    }

    public function endParticipation(string $publicId,int $expectedAccessVersion,string $discardedInvitationDigest,DateTimeImmutable $endedAt,DateTimeImmutable $deleteAt):?Invitation
    {
        $s=$this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations SET
              status='participant_ended', invitation_access_digest=:digest,
              invitation_access_version=invitation_access_version+1,
              participant_adult_attested=NULL, participant_terms_version=NULL, participant_terms_accepted_at=NULL,
              accepted_at=NULL, complete_until=NULL, stage_one_completed_at=NULL, fraction_survey_state='pending',
              stage_two_completed_at=NULL, stage_three_completed_at=NULL, stage_four_completed_at=NULL, wallet_path=NULL,
              updated_at=:updated_at, delete_at=:delete_at
            WHERE public_id=:public_id AND invitation_access_version=:access_version
              AND status='accepted' AND stage_four_completed_at IS NULL
            SQL);
        $s->bindValue(':digest',$discardedInvitationDigest,PDO::PARAM_LOB);
        $s->bindValue(':updated_at',$this->date($endedAt));$s->bindValue(':delete_at',$this->date($deleteAt));
        $s->bindValue(':public_id',$publicId);$s->bindValue(':access_version',$expectedAccessVersion,PDO::PARAM_INT);$s->execute();
        return $s->rowCount()===1?$this->findByPublicId($publicId):null;
    }

    private function updateDeadline(string $publicId,int $expectedManagementVersion,string $status,string $column,DateTimeImmutable $until,DateTimeImmutable $updatedAt):?Invitation
    {
        if(!in_array($column,['accept_until','complete_until'],true))throw new \InvalidArgumentException();
        $s=$this->connection->prepare("UPDATE first_sats_invitations SET $column=:until, updated_at=:updated_at WHERE public_id=:public_id AND management_access_version=:management_version AND status=:status");
        $s->execute(['until'=>$this->date($until),'updated_at'=>$this->date($updatedAt),'public_id'=>$publicId,'management_version'=>$expectedManagementVersion,'status'=>$status]);
        return $s->rowCount()===1?$this->findByPublicId($publicId):null;
    }

    public function completeStageOne(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET stage_one_completed_at = :completed_at, updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'accepted'
              AND stage_one_completed_at IS NULL
            SQL);
        $statement->execute([
            'completed_at' => $this->date($completedAt), 'updated_at' => $this->date($completedAt),
            'public_id' => $publicId, 'expected_version' => $expectedAccessVersion,
        ]);
        if ($statement->rowCount() === 1) return $this->findByPublicId($publicId);
        $current = $this->findByPublicId($publicId);
        return $current?->stageOneCompletedAt === null ? null : $current;
    }

    public function completeStageTwo(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET stage_two_completed_at = :completed_at, updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'accepted'
              AND stage_one_completed_at IS NOT NULL
              AND fraction_survey_state <> 'pending'
              AND stage_two_completed_at IS NULL
            SQL);
        $statement->execute([
            'completed_at' => $this->date($completedAt), 'updated_at' => $this->date($completedAt),
            'public_id' => $publicId, 'expected_version' => $expectedAccessVersion,
        ]);
        if ($statement->rowCount() === 1) return $this->findByPublicId($publicId);
        $current = $this->findByPublicId($publicId);
        return $current?->stageTwoCompletedAt === null ? null : $current;
    }

    public function completeStageThree(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET stage_three_completed_at = :completed_at, updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'accepted'
              AND stage_two_completed_at IS NOT NULL
              AND stage_three_completed_at IS NULL
            SQL);
        $statement->execute([
            'completed_at' => $this->date($completedAt), 'updated_at' => $this->date($completedAt),
            'public_id' => $publicId, 'expected_version' => $expectedAccessVersion,
        ]);
        if ($statement->rowCount() === 1) return $this->findByPublicId($publicId);
        $current = $this->findByPublicId($publicId);
        return $current?->stageThreeCompletedAt === null ? null : $current;
    }

    public function completeStageFour(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET stage_four_completed_at = :completed_at, updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'accepted'
              AND stage_three_completed_at IS NOT NULL
              AND stage_four_completed_at IS NULL
            SQL);
        $statement->execute([
            'completed_at' => $this->date($completedAt), 'updated_at' => $this->date($completedAt),
            'public_id' => $publicId, 'expected_version' => $expectedAccessVersion,
        ]);
        if ($statement->rowCount() === 1) return $this->findByPublicId($publicId);
        $current = $this->findByPublicId($publicId);
        return $current?->stageFourCompletedAt === null ? null : $current;
    }

    public function selectWalletPath(string $publicId, int $expectedAccessVersion, string $walletPath, DateTimeImmutable $selectedAt): ?Invitation
    {
        if (!in_array($walletPath, ['wos', 'phoenix', 'other'], true)) return null;
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET wallet_path = :wallet_path, updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND status = 'accepted'
              AND sats_amount > 0
              AND stage_four_completed_at IS NOT NULL
            SQL);
        $statement->execute([
            'wallet_path' => $walletPath, 'updated_at' => $this->date($selectedAt),
            'public_id' => $publicId, 'expected_version' => $expectedAccessVersion,
        ]);
        return $statement->rowCount() === 1 ? $this->findByPublicId($publicId) : null;
    }

    public function recordFractionSurvey(string $publicId, int $expectedAccessVersion, string $answer, DateTimeImmutable $recordedAt): ?Invitation
    {
        if (!in_array($answer, ['yes', 'no', 'unsure'], true)) return null;
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->stageOneCompletedAt === null || $current->fractionSurveyState !== 'pending') return null;
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(<<<'SQL'
                UPDATE first_sats_invitations
                SET fraction_survey_state = 'answered', updated_at = :updated_at
                WHERE public_id = :public_id
                  AND invitation_access_version = :expected_version
                  AND stage_one_completed_at IS NOT NULL
                  AND fraction_survey_state = 'pending'
                SQL);
            $statement->execute(['updated_at' => $this->date($recordedAt), 'public_id' => $publicId, 'expected_version' => $expectedAccessVersion]);
            if ($statement->rowCount() !== 1) {
                $this->connection->rollBack();
                return null;
            }
            $updated = $this->connection->prepare(<<<'SQL'
                UPDATE first_sats_anonymous_counters
                SET count_value = count_value + 1, updated_at = :updated_at
                WHERE metric_key = :metric_key AND content_version = :content_version
                  AND locale = :locale AND answer_key = :answer_key
                SQL);
            $counter = ['updated_at' => $this->date($recordedAt), 'metric_key' => 'fraction_known_before', 'content_version' => $current->learningContentVersion, 'locale' => $current->locale, 'answer_key' => $answer];
            $updated->execute($counter);
            if ($updated->rowCount() === 0) {
                $insert = $this->connection->prepare(<<<'SQL'
                    INSERT INTO first_sats_anonymous_counters
                        (metric_key, content_version, locale, answer_key, count_value, updated_at)
                    VALUES (:metric_key, :content_version, :locale, :answer_key, 1, :updated_at)
                    SQL);
                $insert->execute(['metric_key' => $counter['metric_key'], 'content_version' => $counter['content_version'], 'locale' => $counter['locale'], 'answer_key' => $counter['answer_key'], 'updated_at' => $counter['updated_at']]);
            }
            $this->connection->commit();
        } catch (\Throwable $error) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $error;
        }
        return $this->findByPublicId($publicId);
    }

    public function skipFractionSurvey(string $publicId, int $expectedAccessVersion, DateTimeImmutable $recordedAt): ?Invitation
    {
        $current=$this->findByPublicId($publicId);
        if($current===null||$current->stageOneCompletedAt===null||$current->fractionSurveyState!=='pending')return null;
        $this->connection->beginTransaction();
        try{
        $statement = $this->connection->prepare(<<<'SQL'
            UPDATE first_sats_invitations
            SET fraction_survey_state = 'skipped', updated_at = :updated_at
            WHERE public_id = :public_id
              AND invitation_access_version = :expected_version
              AND stage_one_completed_at IS NOT NULL
              AND fraction_survey_state = 'pending'
            SQL);
        $statement->execute(['updated_at' => $this->date($recordedAt), 'public_id' => $publicId, 'expected_version' => $expectedAccessVersion]);
        if($statement->rowCount()!==1){$this->connection->rollBack();return null;}
        $counter=['updated_at'=>$this->date($recordedAt),'metric_key'=>'fraction_known_before','content_version'=>$current->learningContentVersion,'locale'=>$current->locale,'answer_key'=>'skipped'];
        $updated=$this->connection->prepare('UPDATE first_sats_anonymous_counters SET count_value=count_value+1,updated_at=:updated_at WHERE metric_key=:metric_key AND content_version=:content_version AND locale=:locale AND answer_key=:answer_key');$updated->execute($counter);
        if($updated->rowCount()===0){$insert=$this->connection->prepare('INSERT INTO first_sats_anonymous_counters (metric_key,content_version,locale,answer_key,count_value,updated_at) VALUES (:metric_key,:content_version,:locale,:answer_key,1,:updated_at)');$insert->execute($counter);}
        $this->connection->commit();
        }catch(\Throwable $e){if($this->connection->inTransaction())$this->connection->rollBack();throw $e;}
        return $this->findByPublicId($publicId);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }

    private function optionalDate(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }
}
