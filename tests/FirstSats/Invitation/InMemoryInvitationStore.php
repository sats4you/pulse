<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Invitation;

use DateTimeImmutable;
use Sats4you\Pulse\FirstSats\Invitation\Invitation;
use Sats4you\Pulse\FirstSats\Invitation\InvitationStore;

final class InMemoryInvitationStore implements InvitationStore
{
    /** @var array<string, Invitation> */
    public array $invitations = [];

    public function create(Invitation $invitation): void
    {
        $this->invitations[$invitation->publicId] = $invitation;
    }

    public function findByPublicId(string $publicId): ?Invitation
    {
        return $this->invitations[$publicId] ?? null;
    }

    public function accept(string $publicId, int $expectedAccessVersion, string $newAccessDigest, string $participantTermsVersion, DateTimeImmutable $acceptedAt, DateTimeImmutable $completeUntil): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'open' || $current->invitationAccessVersion !== $expectedAccessVersion || $current->acceptUntil < $acceptedAt) {
            return null;
        }
        return $this->invitations[$publicId] = $current->accepted(
            $newAccessDigest, $expectedAccessVersion + 1, $participantTermsVersion, $acceptedAt, $completeUntil,
        );
    }

    public function rotateInvitationAccess(string $publicId, int $expectedAccessVersion, string $newAccessDigest, DateTimeImmutable $updatedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'accepted' || $current->invitationAccessVersion !== $expectedAccessVersion) {
            return null;
        }
        return $this->invitations[$publicId] = $current->withInvitationAccess($newAccessDigest, $expectedAccessVersion + 1);
    }

    public function rotateManagementRecovery(string $publicId, int $expectedManagementVersion, int $expectedRecoveryVersion, string $newManagementDigest, string $newRecoveryDigest, DateTimeImmutable $updatedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->managementAccessVersion !== $expectedManagementVersion || $current->recoveryVersion !== $expectedRecoveryVersion) return null;
        return $this->invitations[$publicId] = $current->withManagementRecovery(
            $newManagementDigest, $expectedManagementVersion + 1, $newRecoveryDigest, $expectedRecoveryVersion + 1,
        );
    }

    public function extendOpen(string $publicId,int $expectedManagementVersion,DateTimeImmutable $acceptUntil,DateTimeImmutable $updatedAt):?Invitation
    {
        $c=$this->findByPublicId($publicId);if($c===null||$c->status!=='open'||$c->managementAccessVersion!==$expectedManagementVersion)return null;
        return $this->invitations[$publicId]=$c->withAcceptUntil($acceptUntil);
    }

    public function extendAccepted(string $publicId,int $expectedManagementVersion,DateTimeImmutable $completeUntil,DateTimeImmutable $updatedAt):?Invitation
    {
        $c=$this->findByPublicId($publicId);if($c===null||$c->status!=='accepted'||$c->managementAccessVersion!==$expectedManagementVersion)return null;
        return $this->invitations[$publicId]=$c->withCompleteUntil($completeUntil);
    }

    public function revokeOpen(string $publicId,int $expectedManagementVersion,string $discardedInvitationDigest,DateTimeImmutable $updatedAt,DateTimeImmutable $deleteAt):?Invitation
    {
        $c=$this->findByPublicId($publicId);if($c===null||$c->status!=='open'||$c->managementAccessVersion!==$expectedManagementVersion)return null;
        return $this->invitations[$publicId]=$c->revoked($discardedInvitationDigest);
    }

    public function resetParticipation(string $publicId,int $expectedManagementVersion,string $newInvitationDigest,DateTimeImmutable $acceptUntil,DateTimeImmutable $updatedAt):?Invitation
    {
        $c=$this->findByPublicId($publicId);if($c===null||$c->status!=='accepted'||$c->managementAccessVersion!==$expectedManagementVersion)return null;
        return $this->invitations[$publicId]=$c->resetParticipation($newInvitationDigest,$acceptUntil);
    }

    public function endParticipation(string $publicId,int $expectedAccessVersion,string $discardedInvitationDigest,DateTimeImmutable $endedAt,DateTimeImmutable $deleteAt):?Invitation
    {
        $c=$this->findByPublicId($publicId);
        if($c===null||$c->status!=='accepted'||$c->stageFourCompletedAt!==null||$c->invitationAccessVersion!==$expectedAccessVersion)return null;
        return $this->invitations[$publicId]=$c->participationEnded($discardedInvitationDigest);
    }

    public function completeStageOne(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'accepted' || $current->invitationAccessVersion !== $expectedAccessVersion) return null;
        if ($current->stageOneCompletedAt !== null) return $current;
        return $this->invitations[$publicId] = $current->withStageOneCompleted($completedAt);
    }

    public function completeStageTwo(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'accepted' || $current->stageOneCompletedAt === null || $current->fractionSurveyState === 'pending' || $current->invitationAccessVersion !== $expectedAccessVersion) return null;
        if ($current->stageTwoCompletedAt !== null) return $current;
        return $this->invitations[$publicId] = $current->withStageTwoCompleted($completedAt);
    }

    public function completeStageThree(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'accepted' || $current->stageTwoCompletedAt === null || $current->invitationAccessVersion !== $expectedAccessVersion) return null;
        if ($current->stageThreeCompletedAt !== null) return $current;
        return $this->invitations[$publicId] = $current->withStageThreeCompleted($completedAt);
    }

    public function completeStageFour(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'accepted' || $current->stageThreeCompletedAt === null || $current->invitationAccessVersion !== $expectedAccessVersion) return null;
        if ($current->stageFourCompletedAt !== null) return $current;
        return $this->invitations[$publicId] = $current->withStageFourCompleted($completedAt);
    }

    public function selectWalletPath(string $publicId, int $expectedAccessVersion, string $walletPath, DateTimeImmutable $selectedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->status !== 'accepted' || $current->satsAmount === 0
            || $current->stageFourCompletedAt === null || $current->invitationAccessVersion !== $expectedAccessVersion
            || !in_array($walletPath, ['wos', 'phoenix', 'other'], true)) return null;
        return $this->invitations[$publicId] = $current->withWalletPath($walletPath);
    }

    public function recordFractionSurvey(string $publicId, int $expectedAccessVersion, string $answer, DateTimeImmutable $recordedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->stageOneCompletedAt === null || $current->fractionSurveyState !== 'pending' || $current->invitationAccessVersion !== $expectedAccessVersion) return null;
        return $this->invitations[$publicId] = $current->withFractionSurveyState('answered');
    }

    public function skipFractionSurvey(string $publicId, int $expectedAccessVersion, DateTimeImmutable $recordedAt): ?Invitation
    {
        $current = $this->findByPublicId($publicId);
        if ($current === null || $current->stageOneCompletedAt === null || $current->fractionSurveyState !== 'pending' || $current->invitationAccessVersion !== $expectedAccessVersion) return null;
        return $this->invitations[$publicId] = $current->withFractionSurveyState('skipped');
    }
}
