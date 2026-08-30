<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Learning;

use DateTimeImmutable;
use Sats4you\Pulse\FirstSats\Invitation\Invitation;
use Sats4you\Pulse\FirstSats\Invitation\InvitationStore;

final readonly class LearningJourney
{
    public function __construct(private InvitationStore $store)
    {
    }

    public function answerStageOne(Invitation $invitation, string $answer, DateTimeImmutable $now): string
    {
        if ($invitation->stageOneCompletedAt !== null) return 'completed';
        if ($answer !== 'part') return 'wrong';
        return $this->store->completeStageOne(
            $invitation->publicId, $invitation->invitationAccessVersion, $now,
        ) === null ? 'conflict' : 'correct';
    }

    public function answerStageTwo(Invitation $invitation, string $answer, DateTimeImmutable $now): string
    {
        if ($invitation->stageTwoCompletedAt !== null) return 'completed';
        if ($invitation->stageOneCompletedAt === null || $invitation->fractionSurveyState === 'pending') return 'locked';
        if ($answer !== 'private') return 'wrong';
        return $this->store->completeStageTwo(
            $invitation->publicId, $invitation->invitationAccessVersion, $now,
        ) === null ? 'conflict' : 'correct';
    }

    public function answerStageThree(Invitation $invitation, string $answer, DateTimeImmutable $now): string
    {
        if ($invitation->stageThreeCompletedAt !== null) return 'completed';
        if ($invitation->stageTwoCompletedAt === null) return 'locked';
        if ($answer !== 'request') return 'wrong';
        return $this->store->completeStageThree(
            $invitation->publicId, $invitation->invitationAccessVersion, $now,
        ) === null ? 'conflict' : 'correct';
    }

    public function answerStageFour(Invitation $invitation, string $answer, DateTimeImmutable $now): string
    {
        if ($invitation->stageFourCompletedAt !== null) return 'completed';
        if ($invitation->stageThreeCompletedAt === null) return 'locked';
        if ($answer !== 'balanced') return 'wrong';
        return $this->store->completeStageFour(
            $invitation->publicId, $invitation->invitationAccessVersion, $now,
        ) === null ? 'conflict' : 'correct';
    }

    public function answerFractionSurvey(Invitation $invitation, string $answer, DateTimeImmutable $now): bool
    {
        if (!in_array($answer, ['yes', 'no', 'unsure'], true)) return false;
        return $this->store->recordFractionSurvey(
            $invitation->publicId, $invitation->invitationAccessVersion, $answer, $now,
        ) !== null;
    }

    public function skipFractionSurvey(Invitation $invitation, DateTimeImmutable $now): bool
    {
        return $this->store->skipFractionSurvey(
            $invitation->publicId, $invitation->invitationAccessVersion, $now,
        ) !== null;
    }
}
