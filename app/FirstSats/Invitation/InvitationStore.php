<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateTimeImmutable;

interface InvitationStore
{
    public function create(Invitation $invitation): void;

    public function findByPublicId(string $publicId): ?Invitation;

    public function accept(
        string $publicId,
        int $expectedAccessVersion,
        string $newAccessDigest,
        string $participantTermsVersion,
        DateTimeImmutable $acceptedAt,
        DateTimeImmutable $completeUntil,
    ): ?Invitation;

    public function rotateInvitationAccess(
        string $publicId,
        int $expectedAccessVersion,
        string $newAccessDigest,
        DateTimeImmutable $updatedAt,
    ): ?Invitation;

    public function rotateManagementRecovery(
        string $publicId,
        int $expectedManagementVersion,
        int $expectedRecoveryVersion,
        string $newManagementDigest,
        string $newRecoveryDigest,
        DateTimeImmutable $updatedAt,
    ): ?Invitation;

    public function extendOpen(string $publicId,int $expectedManagementVersion,DateTimeImmutable $acceptUntil,DateTimeImmutable $updatedAt):?Invitation;

    public function extendAccepted(string $publicId,int $expectedManagementVersion,DateTimeImmutable $completeUntil,DateTimeImmutable $updatedAt):?Invitation;

    public function revokeOpen(string $publicId,int $expectedManagementVersion,string $discardedInvitationDigest,DateTimeImmutable $updatedAt,DateTimeImmutable $deleteAt):?Invitation;

    public function resetParticipation(string $publicId,int $expectedManagementVersion,string $newInvitationDigest,DateTimeImmutable $acceptUntil,DateTimeImmutable $updatedAt):?Invitation;

    public function endParticipation(string $publicId,int $expectedAccessVersion,string $discardedInvitationDigest,DateTimeImmutable $endedAt,DateTimeImmutable $deleteAt):?Invitation;

    public function completeStageOne(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation;

    public function completeStageTwo(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation;

    public function completeStageThree(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation;

    public function completeStageFour(string $publicId, int $expectedAccessVersion, DateTimeImmutable $completedAt): ?Invitation;

    public function selectWalletPath(string $publicId, int $expectedAccessVersion, string $walletPath, DateTimeImmutable $selectedAt): ?Invitation;

    public function recordFractionSurvey(string $publicId, int $expectedAccessVersion, string $answer, DateTimeImmutable $recordedAt): ?Invitation;

    public function skipFractionSurvey(string $publicId, int $expectedAccessVersion, DateTimeImmutable $recordedAt): ?Invitation;
}
