<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateTimeImmutable;

final readonly class Invitation
{
    public function __construct(
        public string $id,
        public string $publicId,
        public string $locale,
        public string $inviterLabel,
        public string $inviteeLabel,
        public ?string $personalMessage,
        public int $satsAmount,
        public string $invitationAccessDigest,
        public int $invitationAccessVersion,
        public string $managementAccessDigest,
        public int $managementAccessVersion,
        public string $recoveryDigest,
        public int $recoveryVersion,
        public string $status,
        public string $termsVersion,
        public DateTimeImmutable $termsAcceptedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $acceptUntil,
        public ?string $participantTermsVersion = null,
        public ?DateTimeImmutable $participantTermsAcceptedAt = null,
        public ?DateTimeImmutable $acceptedAt = null,
        public ?DateTimeImmutable $completeUntil = null,
        public string $learningContentVersion = '2026-08-23-v1',
        public ?DateTimeImmutable $stageOneCompletedAt = null,
        public string $fractionSurveyState = 'pending',
        public ?DateTimeImmutable $stageTwoCompletedAt = null,
        public ?DateTimeImmutable $stageThreeCompletedAt = null,
        public ?DateTimeImmutable $stageFourCompletedAt = null,
        public ?string $walletPath = null,
    ) {
    }

    public function accepted(
        string $accessDigest,
        int $accessVersion,
        string $participantTermsVersion,
        DateTimeImmutable $acceptedAt,
        DateTimeImmutable $completeUntil,
    ): self {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $accessDigest, $accessVersion,
            $this->managementAccessDigest, $this->managementAccessVersion, $this->recoveryDigest,
            $this->recoveryVersion, 'accepted', $this->termsVersion, $this->termsAcceptedAt,
            $this->createdAt, $this->acceptUntil, $participantTermsVersion, $acceptedAt, $acceptedAt,
            $completeUntil, $this->learningContentVersion, $this->stageOneCompletedAt,
            $this->fractionSurveyState, $this->stageTwoCompletedAt, $this->stageThreeCompletedAt,
            $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withInvitationAccess(string $accessDigest, int $accessVersion): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $accessDigest, $accessVersion,
            $this->managementAccessDigest, $this->managementAccessVersion, $this->recoveryDigest,
            $this->recoveryVersion, $this->status, $this->termsVersion, $this->termsAcceptedAt,
            $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $this->stageOneCompletedAt, $this->fractionSurveyState,
            $this->stageTwoCompletedAt, $this->stageThreeCompletedAt, $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withManagementRecovery(
        string $managementDigest,
        int $managementVersion,
        string $recoveryDigest,
        int $recoveryVersion,
    ): self {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $managementDigest, $managementVersion,
            $recoveryDigest, $recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil,
            $this->participantTermsVersion, $this->participantTermsAcceptedAt, $this->acceptedAt,
            $this->completeUntil, $this->learningContentVersion, $this->stageOneCompletedAt,
            $this->fractionSurveyState, $this->stageTwoCompletedAt, $this->stageThreeCompletedAt,
            $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withAcceptUntil(DateTimeImmutable $until):self
    {
        return $this->copy(status:'open',acceptUntil:$until);
    }

    public function withCompleteUntil(DateTimeImmutable $until):self
    {
        return $this->copy(completeUntil:$until);
    }

    public function revoked(string $invitationDigest):self
    {
        return $this->copy(status:'revoked',invitationDigest:$invitationDigest,invitationVersion:$this->invitationAccessVersion+1);
    }

    public function resetParticipation(string $invitationDigest,DateTimeImmutable $acceptUntil):self
    {
        return new self(
            $this->id,$this->publicId,$this->locale,$this->inviterLabel,$this->inviteeLabel,$this->personalMessage,$this->satsAmount,
            $invitationDigest,$this->invitationAccessVersion+1,$this->managementAccessDigest,$this->managementAccessVersion,$this->recoveryDigest,$this->recoveryVersion,
            'open',$this->termsVersion,$this->termsAcceptedAt,$this->createdAt,$acceptUntil,null,null,null,null,$this->learningContentVersion,null,'pending',null,null,null,null,
        );
    }

    public function participationEnded(string $invitationDigest):self
    {
        return new self(
            $this->id,$this->publicId,$this->locale,$this->inviterLabel,$this->inviteeLabel,$this->personalMessage,$this->satsAmount,
            $invitationDigest,$this->invitationAccessVersion+1,$this->managementAccessDigest,$this->managementAccessVersion,$this->recoveryDigest,$this->recoveryVersion,
            'participant_ended',$this->termsVersion,$this->termsAcceptedAt,$this->createdAt,$this->acceptUntil,null,null,null,null,$this->learningContentVersion,null,'pending',null,null,null,null,
        );
    }

    private function copy(?string $status=null,?DateTimeImmutable $acceptUntil=null,?DateTimeImmutable $completeUntil=null,?string $invitationDigest=null,?int $invitationVersion=null):self
    {
        return new self(
            $this->id,$this->publicId,$this->locale,$this->inviterLabel,$this->inviteeLabel,$this->personalMessage,$this->satsAmount,
            $invitationDigest??$this->invitationAccessDigest,$invitationVersion??$this->invitationAccessVersion,$this->managementAccessDigest,$this->managementAccessVersion,$this->recoveryDigest,$this->recoveryVersion,
            $status??$this->status,$this->termsVersion,$this->termsAcceptedAt,$this->createdAt,$acceptUntil??$this->acceptUntil,$this->participantTermsVersion,$this->participantTermsAcceptedAt,$this->acceptedAt,
            $completeUntil??$this->completeUntil,$this->learningContentVersion,$this->stageOneCompletedAt,$this->fractionSurveyState,$this->stageTwoCompletedAt,$this->stageThreeCompletedAt,$this->stageFourCompletedAt,$this->walletPath,
        );
    }

    public function withStageOneCompleted(DateTimeImmutable $completedAt): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $this->managementAccessDigest, $this->managementAccessVersion,
            $this->recoveryDigest, $this->recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $completedAt, $this->fractionSurveyState,
            $this->stageTwoCompletedAt, $this->stageThreeCompletedAt, $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withFractionSurveyState(string $state): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $this->managementAccessDigest, $this->managementAccessVersion,
            $this->recoveryDigest, $this->recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $this->stageOneCompletedAt, $state,
            $this->stageTwoCompletedAt, $this->stageThreeCompletedAt, $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withStageTwoCompleted(DateTimeImmutable $completedAt): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $this->managementAccessDigest, $this->managementAccessVersion,
            $this->recoveryDigest, $this->recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $this->stageOneCompletedAt, $this->fractionSurveyState,
            $completedAt, $this->stageThreeCompletedAt, $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withStageThreeCompleted(DateTimeImmutable $completedAt): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $this->managementAccessDigest, $this->managementAccessVersion,
            $this->recoveryDigest, $this->recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $this->stageOneCompletedAt, $this->fractionSurveyState,
            $this->stageTwoCompletedAt, $completedAt, $this->stageFourCompletedAt, $this->walletPath,
        );
    }

    public function withStageFourCompleted(DateTimeImmutable $completedAt): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $this->managementAccessDigest, $this->managementAccessVersion,
            $this->recoveryDigest, $this->recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $this->stageOneCompletedAt, $this->fractionSurveyState,
            $this->stageTwoCompletedAt, $this->stageThreeCompletedAt, $completedAt, $this->walletPath,
        );
    }

    public function withWalletPath(string $walletPath): self
    {
        return new self(
            $this->id, $this->publicId, $this->locale, $this->inviterLabel, $this->inviteeLabel,
            $this->personalMessage, $this->satsAmount, $this->invitationAccessDigest,
            $this->invitationAccessVersion, $this->managementAccessDigest, $this->managementAccessVersion,
            $this->recoveryDigest, $this->recoveryVersion, $this->status, $this->termsVersion,
            $this->termsAcceptedAt, $this->createdAt, $this->acceptUntil, $this->participantTermsVersion,
            $this->participantTermsAcceptedAt, $this->acceptedAt, $this->completeUntil,
            $this->learningContentVersion, $this->stageOneCompletedAt, $this->fractionSurveyState,
            $this->stageTwoCompletedAt, $this->stageThreeCompletedAt, $this->stageFourCompletedAt,
            $walletPath,
        );
    }
}
