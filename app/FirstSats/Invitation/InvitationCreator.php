<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Invitation;

use DateInterval;
use DateTimeImmutable;
use DomainException;
use Sats4you\Pulse\Platform\Security\SecretDigester;
use Sats4you\Pulse\Platform\Security\SecretGenerator;

final readonly class InvitationCreator
{
    public function __construct(
        private InvitationStore $store,
        private SecretGenerator $secrets,
        private SecretDigester $digester,
    ) {
    }

    public function create(InvitationInput $input, DateTimeImmutable $now): InvitationCredentials
    {
        $inviter = $this->label($input->inviterLabel);
        $invitee = $this->label($input->inviteeLabel);
        $message = trim((string) $input->personalMessage);
        if (mb_strlen($message) > 500) {
            throw new DomainException('invalid_message');
        }
        if (!in_array($input->locale, ['de', 'fr', 'it', 'rm'], true)) {
            throw new DomainException('invalid_locale');
        }
        if ($input->satsAmount < 0 || $input->satsAmount > 100_000) {
            throw new DomainException('invalid_sats');
        }
        if (!$input->adultAttested) {
            throw new DomainException('adult_attestation_required');
        }
        if (!$input->termsAccepted || $input->termsVersion === '') {
            throw new DomainException('terms_required');
        }
        if ($input->satsAmount >= 50_000 && !$input->highAmountAcknowledged) {
            throw new DomainException('high_amount_acknowledgement_required');
        }

        $invitationSecret = $this->secrets->generate();
        $managementSecret = $this->secrets->generate();
        $publicId = substr(bin2hex(random_bytes(16)), 0, 24);
        $recoveryCode = $publicId . '.' . $this->secrets->generate();
        $invitation = new Invitation(
            bin2hex(random_bytes(16)),
            $publicId,
            $input->locale,
            $inviter,
            $invitee,
            $message === '' ? null : $message,
            $input->satsAmount,
            $this->digester->digest($invitationSecret),
            1,
            $this->digester->digest($managementSecret),
            1,
            $this->digester->digest($recoveryCode),
            1,
            'open',
            $input->termsVersion,
            $now,
            $now,
            $now->add(new DateInterval('P30D')),
        );
        $this->store->create($invitation);

        return new InvitationCredentials($invitation, $invitationSecret, $managementSecret, $recoveryCode);
    }

    private function label(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 50 || str_contains($value, "\n") || str_contains($value, "\r")
            || preg_match('~(?:https?://|www\.)~iu', $value) === 1) {
            throw new DomainException('invalid_label');
        }

        return $value;
    }
}
