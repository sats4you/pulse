<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Http;

use DateTimeImmutable;
use DateTimeZone;
use Sats4you\Pulse\FirstSats\Invitation\Invitation;
use Sats4you\Pulse\FirstSats\Payment\PaymentRequest;
use Sats4you\Pulse\FirstSats\Payment\HandoverState;
use Sats4you\Pulse\FirstSats\Pricing\ChfQuote;
use Sats4you\Pulse\FirstSats\Pricing\ChfQuoteProvider;
use Sats4you\Pulse\FirstSats\Pricing\UnavailableChfQuoteProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FirstSatsPage
{
    private const CONTENT_REVIEWED_AT = ['2026-08-23-v1' => '24.08.2026'];

    private ChfQuoteProvider $quotes;
    private ?ChfQuote $usedQuote = null;
    private bool $usedAmount = false;

    public function __construct(?ChfQuoteProvider $quotes = null)
    {
        $this->quotes = $quotes ?? new UnavailableChfQuoteProvider();
    }

    /** @param array<string, string> $languages @param array<string, string> $values */
    public function form(TranslatorInterface $t, string $locale, array $languages, string $csrf, array $values = [], ?string $error = null): string
    {
        $v = static fn (string $key): string => htmlspecialchars($values[$key] ?? '', ENT_QUOTES, 'UTF-8');
        $checked = static fn (string $key): string => ($values[$key] ?? '') === '1' ? ' checked' : '';
        $errorHtml = $error === null ? '' : '<div class="alert" role="alert">' . $this->e($t->trans('form.error.' . $error)) . '</div>';
        $quote = $this->quote();
        $quoteSum = $quote?->sumUnits() ?? '';

        return $this->layout($t, $locale, $languages, $t->trans('form.title'), <<<HTML
            <section class="hero">
              <div class="hero-brand"><span class="hero-mark"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><p class="tag"><span>{$this->e($t->trans('brand.tagline_line_1'))}</span><span>{$this->e($t->trans('brand.tagline_line_2'))}</span></p></div>
              <p class="eyebrow">{$this->e($t->trans('form.eyebrow'))}</p>
              <h1>{$this->e($t->trans('form.title'))}</h1>
              <p class="lede">{$this->e($t->trans('form.intro_simple'))}</p>
            </section>
            {$errorHtml}
            <form method="post" action="/erste-sats" class="form" novalidate>
              <input type="hidden" name="csrf" value="{$this->e($csrf)}">
              <input type="hidden" name="lang" value="{$this->e($locale)}">
              <div class="field"><span class="field-label">{$this->e($t->trans('form.sats'))}</span><div class="presets" role="group" aria-label="{$this->e($t->trans('form.sats'))}">{$this->amountPreset($t, 1000)}{$this->amountPreset($t, 5000)}{$this->amountPreset($t, 10000)}</div></div>
              <label class="field">{$this->e($t->trans('form.custom_sats'))}<input name="sats" inputmode="numeric" autocomplete="off" pattern="[0-9']*" value="{$v('sats')}" data-quote-sum="{$this->e((string) $quoteSum)}" data-chf-estimate="{$this->e($t->trans('amount.chf_estimate', ['%value%' => '__VALUE__']))}" data-chf-under-cent="{$this->e($t->trans('amount.chf_under_cent'))}" data-chf-zero="{$this->e($t->trans('amount.chf_zero'))}" data-chf-unavailable="{$this->e($t->trans('amount.chf_unavailable'))}" required aria-describedby="sats-help"></label>
              <p id="sats-help" class="help"><span data-summary aria-live="polite"></span><br>{$this->e($t->trans('form.maximum'))}: {$this->e($this->amount($t, 100_000))}<br>{$this->e($t->trans('form.zero_hint'))}</p>
              <div class="promise">{$this->e($t->trans('form.promise'))}</div>
              <label class="field">{$this->e($t->trans('form.inviter'))}<input name="inviter" maxlength="50" value="{$v('inviter')}" required></label>
              <label class="field">{$this->e($t->trans('form.invitee'))}<input name="invitee" maxlength="50" value="{$v('invitee')}" required></label>
              <label class="check"><input type="checkbox" name="adult" value="1"{$checked('adult')}> <span>{$this->e($t->trans('form.adult'))}</span></label>
              <div class="high-amount" data-high-amount hidden>
                <label class="check"><input type="checkbox" name="high_amount" value="1"{$checked('high_amount')}> <span>{$this->e($t->trans('form.high_amount'))}</span></label>
              </div>
              <section class="summary-card"><h2>{$this->e($t->trans('form.summary_title'))}</h2><ul><li>{$this->e($t->trans('form.summary_1'))}</li><li>{$this->e($t->trans('form.summary_2'))}</li><li>{$this->e($t->trans('form.summary_3'))}</li><li>{$this->e($t->trans('form.summary_4'))}</li></ul></section>
              <label class="check"><input type="checkbox" name="terms" value="1"{$checked('terms')}> <span>{$this->e($t->trans('form.terms'))}</span></label>
              <div class="legal-links"><a href="/erste-sats/terms?lang={$this->e($locale)}">{$this->e($t->trans('form.terms_link'))}</a><span>·</span><a href="/erste-sats/privacy?lang={$this->e($locale)}">{$this->e($t->trans('nav.privacy'))}</a><small>{$this->e($t->trans('info.legal_pending'))}</small></div>
              <button class="primary" type="submit">{$this->e($t->trans('form.submit'))}</button>
            </form>
            <p class="below"><a class="secondary button" href="/erste-sats/faq?lang={$this->e($locale)}">{$this->e($t->trans('nav.faq'))}</a></p><p class="below recovery-entry"><a class="text-link" href="/erste-sats/recover?lang={$this->e($locale)}">{$this->e($t->trans('recovery.entry'))}</a></p>
            <script src="/assets/first-sats-form.js" defer></script>
            HTML, false, true);
    }

    /** @param array<string, string> $languages */
    public function created(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $inviteLink, string $manageLink, string $recoveryCode): string
    {
        $amount = $this->amount($t, $i->satsAmount);
        $share = $i->satsAmount === 0
            ? $t->trans('created.share_body_zero', ['%invitee%' => $i->inviteeLabel, '%inviter%' => $i->inviterLabel, '%link%' => $inviteLink])
            : $t->trans('created.share_body_positive', ['%invitee%' => $i->inviteeLabel, '%inviter%' => $i->inviterLabel, '%amount%' => $amount, '%link%' => $inviteLink]);
        $share .= "\n\n" . $t->trans('created.share_faq', ['%link%' => 'https://sats4you.ch/erste-sats/faq'])
            . "\n\n" . $t->trans('created.share_safety')
            . "\n\n" . $t->trans('created.share_closing', ['%inviter%' => $i->inviterLabel]);

        return $this->layout($t, $locale, $languages, $t->trans('created.title'), <<<HTML
            <section class="hero"><p class="eyebrow">{$this->e($t->trans('created.eyebrow'))}</p><h1>{$this->e($t->trans('created.title'))}</h1><p>{$this->e($t->trans('created.intro'))}</p></section>
            <section class="card secrets">
              <h2>{$this->e($t->trans('created.keep_title'))}</h2>
              <p>{$this->e($t->trans('created.keep_copy'))}</p>
              {$this->copySecret($t->trans('created.manage_link'), $manageLink, 'manage-link', $t->trans('created.copy_manage_action'), $t->trans('created.copied_manage'))}
              {$this->copySecret($t->trans('created.recovery'), $recoveryCode, 'recovery-code', $t->trans('created.copy_recovery_action'), $t->trans('created.copied_recovery'))}
              <p class="copy-status" data-copy-status aria-live="polite"></p>
              <p class="warning">{$this->e($t->trans('created.recovery_warning'))}</p>
            </section>
            <section class="card">
              <h2>{$this->e($t->trans('created.share_send_title', ['%invitee%' => $i->inviteeLabel]))}</h2>
              <p>{$this->e($t->trans('created.share_intro', ['%invitee%' => $i->inviteeLabel]))}</p>
              <textarea id="share-text">{$this->e($share)}</textarea>
              <button class="primary" type="button" data-share data-title="{$this->e($t->trans('created.share_subject'))}">{$this->e($t->trans('created.share_button'))}</button>
              <p class="share-safety">{$this->e($t->trans('created.share_privacy'))}</p>
            </section>
            <script src="/assets/first-sats-share.js?v=message-autosize-2" defer></script>
            HTML);
    }

    /** @param array<string,string> $languages */
    public function recoveryForm(TranslatorInterface $t,string $locale,array $languages,string $csrf,bool $error=false):string
    {
        $errorHtml=$error?'<div class="alert" role="alert">'.$this->e($t->trans('recovery.error')).'</div>':'';
        return $this->layout($t,$locale,$languages,$t->trans('recovery.title'),<<<HTML
            <section class="hero"><p class="eyebrow">{$this->e($t->trans('recovery.eyebrow'))}</p><h1>{$this->e($t->trans('recovery.title'))}</h1><p class="lede">{$this->e($t->trans('recovery.intro'))}</p></section>
            {$errorHtml}
            <form class="card payment-form" method="post" action="/erste-sats/recover"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><label class="field"><span>{$this->e($t->trans('recovery.code'))}</span><textarea name="recovery_code" rows="3" required spellcheck="false" autocomplete="off"></textarea></label><p class="warning">{$this->e($t->trans('recovery.warning'))}</p><button class="primary" type="submit">{$this->e($t->trans('recovery.submit'))}</button></form>
            <section class="card"><h2>{$this->e($t->trans('recovery.no_code_title'))}</h2><p>{$this->e($t->trans('recovery.no_code_copy'))}</p></section>
            HTML);
    }

    /** @param array<string,string> $languages */
    public function recovered(TranslatorInterface $t,string $locale,array $languages,string $manageLink,string $recoveryCode):string
    {
        return $this->layout($t,$locale,$languages,$t->trans('recovery.done_title'),<<<HTML
            <section class="hero"><p class="eyebrow">{$this->e($t->trans('recovery.done_eyebrow'))}</p><h1>{$this->e($t->trans('recovery.done_title'))}</h1><p class="lede">{$this->e($t->trans('recovery.done_intro'))}</p></section>
            <section class="card secrets"><h2>{$this->e($t->trans('recovery.keep_title'))}</h2><p>{$this->e($t->trans('recovery.keep_copy'))}</p>{$this->copySecret($t->trans('created.manage_link'),$manageLink,'recovered-manage-link',$t->trans('created.copy_manage_action'),$t->trans('created.copied_manage'))}{$this->copySecret($t->trans('created.recovery'),$recoveryCode,'recovered-code',$t->trans('created.copy_recovery_action'),$t->trans('created.copied_recovery'))}<p class="copy-status" data-copy-status aria-live="polite"></p><p class="warning">{$this->e($t->trans('recovery.done_warning'))}</p></section><script src="/assets/first-sats-share.js" defer></script>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function bootstrap(TranslatorInterface $t, string $locale, array $languages, string $publicId, string $role): string
    {
        $target = $role === 'management' ? '/erste-sats/manage/' . rawurlencode($publicId) . '/view' : '/erste-sats/i/' . rawurlencode($publicId) . '/view';
        $exchange = '/erste-sats/api/access/' . rawurlencode($role) . '/' . rawurlencode($publicId);
        $config = htmlspecialchars(json_encode([
            'exchange' => $exchange,
            'target' => $target,
            'locale' => $locale,
            'ready' => $t->trans('access.ready'),
            'incomplete' => $t->trans('access.incomplete'),
            'denied' => $t->trans('access.denied'),
        ], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');

        return $this->layout($t, $locale, $languages, $t->trans('access.title'), <<<HTML
            <section class="card" id="first-sats-access" data-config="{$config}">
              <h1 id="state-title">{$this->e($t->trans('access.title'))}</h1>
              <p id="state-copy">{$this->e($t->trans('access.checking'))}</p>
              <a class="primary button" id="continue" href="#" hidden>{$this->e($t->trans('access.continue'))}</a>
            </section>
            <script src="/assets/first-sats-access.js" defer></script>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function invitation(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf, bool $error = false): string
    {
        $gift = $i->satsAmount === 0
            ? $t->trans('invitation.zero')
            : $t->trans('invitation.positive', [
                '%name%' => $i->inviterLabel,
                '%amount%' => $this->amount($t, $i->satsAmount),
            ]);
        $summary = $i->satsAmount === 0
            ? $t->trans('invitation.summary_zero')
            : $t->trans('invitation.summary_positive', ['%name%' => $i->inviterLabel]);
        $alert = $error ? '<p class="alert participant-alert">' . $this->e($t->trans('invitation.error.required')) . '</p>' : '';
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/accept';
        return $this->layout($t, $locale, $languages, $t->trans('invitation.title'), <<<HTML
            <section class="hero invitation-hero"><p class="eyebrow">{$this->e($t->trans('invitation.from_eyebrow', ['%name%' => $i->inviterLabel]))}</p><h1>{$this->e($t->trans('invitation.hello', ['%name%' => $i->inviteeLabel]))}</h1><p class="lede">{$this->e($gift)}</p></section>
            <section class="card invitation-overview"><h2>{$this->e($t->trans('invitation.what_title'))}</h2><p>{$this->e($t->trans('invitation.what_intro'))}</p><ol class="journey-list"><li>{$this->e($t->trans('invitation.step_1'))}</li><li>{$this->e($t->trans('invitation.step_2'))}</li><li>{$this->e($t->trans('invitation.step_3'))}</li></ol><div class="promise neutral">{$this->e($summary)}</div><p class="identity-warning">{$this->e($t->trans('invitation.identity'))}</p></section>
            <form class="card participant-card" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><h2>{$this->e($t->trans('invitation.before_start'))}</h2><p>{$this->e($t->trans('invitation.no_account', ['%date%' => $i->acceptUntil->format('d.m.Y')]))}</p>{$alert}<label class="check"><input type="checkbox" name="adult" value="1" data-participant-adult> <span>{$this->e($t->trans('invitation.adult'))}</span></label><label class="check"><input type="checkbox" name="terms" value="1" data-participant-terms> <span>{$this->e($t->trans('invitation.terms'))}</span></label><div class="legal-links"><a href="/erste-sats/terms?lang={$this->e($locale)}">{$this->e($t->trans('form.terms_link'))}</a><span>·</span><a href="/erste-sats/privacy?lang={$this->e($locale)}">{$this->e($t->trans('nav.privacy'))}</a><small>{$this->e($t->trans('info.legal_pending'))}</small></div><button class="primary" type="submit" data-participant-submit disabled>{$this->e($t->trans('invitation.start'))}</button><p class="stage-note">{$this->e($t->trans('invitation.stage_notice'))}</p></form>
            <script src="/assets/first-sats-invitation.js" defer></script>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function participantHome(
        TranslatorInterface $t,
        string $locale,
        array $languages,
        Invitation $i,
        string $csrf,
        ?PaymentRequest $payment = null,
        ?HandoverState $handover = null,
    ): string
    {
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/continuation?lang=' . rawurlencode($locale);
        $learningUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/1?lang=' . rawurlencode($locale);
        if ($i->stageOneCompletedAt === null) {
            $learningState = '<a class="primary button" href="' . $this->e($learningUrl) . '">' . $this->e($t->trans('participant.learning_start')) . '</a>';
        } elseif ($i->fractionSurveyState === 'pending') {
            $surveyUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/1/survey?lang=' . rawurlencode($locale);
            $learningState = '<p class="stage-note active">' . $this->e($t->trans('participant.stage_one_done')) . '</p><a class="primary button" href="' . $this->e($surveyUrl) . '">' . $this->e($t->trans('participant.survey_continue')) . '</a>';
        } elseif ($i->stageTwoCompletedAt === null) {
            $stageTwoUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/2?lang=' . rawurlencode($locale);
            $learningState = '<p class="stage-note active">' . $this->e($t->trans('participant.stage_one_done')) . '</p><a class="primary button" href="' . $this->e($stageTwoUrl) . '">' . $this->e($t->trans('participant.stage_two_start')) . '</a>';
        } elseif ($i->stageThreeCompletedAt === null) {
            $stageThreeUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/3?lang=' . rawurlencode($locale);
            $learningState = '<p class="stage-note active">' . $this->e($t->trans('participant.stage_one_done')) . '</p><p class="stage-note active">' . $this->e($t->trans('participant.stage_two_done')) . '</p><a class="primary button" href="' . $this->e($stageThreeUrl) . '">' . $this->e($t->trans('participant.stage_three_start')) . '</a>';
        } elseif ($i->stageFourCompletedAt === null) {
            $stageFourUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/4?lang=' . rawurlencode($locale);
            $learningState = '<p class="stage-note active">' . $this->e($t->trans('participant.stage_one_done')) . '</p><p class="stage-note active">' . $this->e($t->trans('participant.stage_two_done')) . '</p><p class="stage-note active">' . $this->e($t->trans('participant.stage_three_done')) . '</p><a class="primary button" href="' . $this->e($stageFourUrl) . '">' . $this->e($t->trans('participant.stage_four_start')) . '</a>';
        } else {
            $completion = $i->satsAmount === 0
                ? $t->trans('participant.complete_zero')
                : ($handover?->receivedAt !== null
                    ? $t->trans('participant.complete_received')
                    : $t->trans('participant.complete_positive'));
            $walletAction = '';
            if ($i->satsAmount > 0) {
                if ($handover?->receivedAt !== null) {
                    $walletUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/next?lang=' . rawurlencode($locale);
                    $walletLabel = 'next.open';
                } elseif ($payment !== null) {
                    $walletUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/payment-request?lang=' . rawurlencode($locale);
                    $walletLabel = 'participant.payment_continue';
                } else {
                    $walletUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . ($i->walletPath === null ? '/wallet' : '/wallet/setup') . '?lang=' . rawurlencode($locale);
                    $walletLabel = $i->walletPath === null ? 'participant.wallet_start' : 'participant.wallet_continue';
                }
                $walletAction = '<a class="primary button" href="' . $this->e($walletUrl) . '">' . $this->e($t->trans($walletLabel)) . '</a>';
            } else {
                $nextUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/next?lang=' . rawurlencode($locale);
                $walletAction = '<a class="primary button" href="' . $this->e($nextUrl) . '">' . $this->e($t->trans('next.open')) . '</a>';
            }
            $learningState = '<p class="stage-note active">' . $this->e($t->trans('participant.stage_one_done')) . '</p><p class="stage-note active">' . $this->e($t->trans('participant.stage_two_done')) . '</p><p class="stage-note active">' . $this->e($t->trans('participant.stage_three_done')) . '</p><p class="stage-note active">' . $this->e($t->trans('participant.stage_four_done')) . '</p><section class="journey-complete learning-complete"><span class="completion-mark" aria-hidden="true">&#10003;</span><div><p class="completion-praise">' . $this->e($t->trans('participant.complete_praise')) . '</p><h2>' . $this->e($t->trans('participant.journey_complete')) . '</h2><p class="completion-knowledge">' . $this->e($t->trans('participant.complete_knowledge')) . '</p><p class="completion-next">' . $this->e($completion) . '</p></div></section>' . $walletAction;
        }
        $abort='';
        if($i->stageFourCompletedAt===null){
            $abortAction='/erste-sats/i/'.rawurlencode($i->publicId).'/end';
            $abort='<details class="card participant-end"><summary>'.$this->e($t->trans('participant.end_title')).'</summary><p>'.$this->e($t->trans('participant.end_copy')).'</p><form method="post" action="'.$this->e($abortAction).'"><input type="hidden" name="csrf" value="'.$this->e($csrf).'"><input type="hidden" name="lang" value="'.$this->e($locale).'"><label class="check"><input type="checkbox" name="confirm_end" value="1" required><span>'.$this->e($t->trans('participant.end_check')).'</span></label><button class="secondary button danger" type="submit">'.$this->e($t->trans('participant.end_button')).'</button></form></details>';
        }
        return $this->layout($t, $locale, $languages, $t->trans('participant.title', ['%name%' => $i->inviteeLabel]), <<<HTML
            <section class="hero"><p class="eyebrow">{$this->e($t->trans('participant.eyebrow'))}</p><h1>{$this->e($t->trans('participant.title', ['%name%' => $i->inviteeLabel]))}</h1><p class="lede">{$this->e($t->trans('participant.accepted'))}</p></section>
            <section class="card participant-card"><h2>{$this->e($t->trans('participant.next_title'))}</h2><p>{$this->e($t->trans('participant.next_copy'))}</p>{$learningState}</section>
            <section class="card continuation-card"><h2>{$this->e($t->trans('participant.continuation_title'))}</h2><p>{$this->e($t->trans('participant.continuation_intro'))}</p><p class="warning">{$this->e($t->trans('participant.continuation_warning'))}</p><button class="secondary button" type="button" data-continuation data-url="{$this->e($action)}" data-csrf="{$this->e($csrf)}" data-error="{$this->e($t->trans('participant.continuation_error'))}">{$this->e($t->trans('participant.continuation_create'))}</button><div class="continuation-result" data-continuation-result hidden><p>{$this->e($t->trans('participant.continuation_ready'))}</p><div class="copy-box"><code id="continuation-link" data-continuation-link></code><button class="copy-icon" type="button" data-copy-continuation data-copied="{$this->e($t->trans('participant.continuation_copied'))}" aria-label="{$this->e($t->trans('participant.continuation_copy'))}"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></div><p class="copy-status" data-continuation-status aria-live="polite"></p></div></section>
            {$abort}
            <script src="/assets/first-sats-participant.js" defer></script>
            HTML);
    }

    /** @param array<string,string> $languages */
    public function participationEnded(TranslatorInterface $t,string $locale,array $languages):string
    {
        return $this->layout($t,$locale,$languages,$t->trans('participant.end_done_title'),'<section class="hero"><p class="eyebrow">'.$this->e($t->trans('participant.end_done_eyebrow')).'</p><h1>'.$this->e($t->trans('participant.end_done_title')).'</h1><p class="lede">'.$this->e($t->trans('participant.end_done_copy')).'</p></section><section class="card"><p>'.$this->e($t->trans('participant.end_done_privacy')).'</p><a class="primary button" href="/erste-sats?lang='.$this->e($locale).'">'.$this->e($t->trans('participant.end_done_home')).'</a></section>');
    }

    /** @param array<string, string> $languages */
    public function stageOne(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf, bool $wrong = false): string
    {
        $amount = number_format($i->satsAmount, 0, '.', "'") . ' Sats';
        $question = $i->satsAmount === 0
            ? $t->trans('learn.1.question_zero')
            : $t->trans('learn.1.question_amount', ['%amount%' => $amount]);
        $gift = $i->satsAmount === 0
            ? $t->trans('learn.1.gift_zero')
            : $t->trans('learn.1.gift_positive', ['%amount%' => $this->amount($t, $i->satsAmount)]);
        $feedback = $wrong ? '<div class="answer-feedback wrong" role="alert"><strong>' . $this->e($t->trans('learn.try_again')) . '</strong><p>' . $this->e($t->trans('learn.1.wrong')) . '</p></div>' : '';
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/1';
        $sources = $this->sources($t, $i, '1', [
            ['primary', 'https://bitcoin.org/bitcoin.pdf', 'Bitcoin-Whitepaper'],
            ['primary', 'https://github.com/bitcoin/bitcoin/blob/master/src/consensus/amount.h', 'Bitcoin Core · amount.h'],
        ]);
        return $this->layout($t, $locale, $languages, $t->trans('learn.1.title'), <<<HTML
            <div class="lesson-progress" aria-label="{$this->e($t->trans('learn.progress', ['%current%' => '1', '%total%' => '4']))}"><span class="active"></span><span></span><span></span><span></span></div>
            <section class="hero lesson-hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('learn.eyebrow', ['%current%' => '1', '%total%' => '4']))}</p><h1>{$this->e($t->trans('learn.1.title'))}</h1></section>
            <section class="card lesson-copy"><p>{$this->e($t->trans('learn.1.body_1'))}</p><p>{$this->e($t->trans('learn.1.body_2'))}</p><p class="promise">{$this->e($gift)}</p></section>
            <form class="card answer-card" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><p class="eyebrow">{$this->e($t->trans('learn.check'))}</p><h2>{$this->e($question)}</h2><fieldset><legend>{$this->e($t->trans('learn.choose'))}</legend><label class="answer-option"><input type="radio" name="answer" value="coin" required><span>{$this->e($t->trans('learn.1.answer_coin'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="part" required><span>{$this->e($t->trans('learn.1.answer_part'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="points" required><span>{$this->e($t->trans('learn.1.answer_points'))}</span></label></fieldset>{$feedback}<button class="primary" type="submit">{$this->e($t->trans('learn.check_answer'))}</button></form>
            {$sources}
            HTML);
    }

    /** @param array<string, string> $languages */
    public function fractionSurvey(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf): string
    {
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/1/survey';
        return $this->layout($t, $locale, $languages, $t->trans('survey.title'), <<<HTML
            <section class="hero"><p class="eyebrow">{$this->e($t->trans('survey.eyebrow'))}</p><h1>{$this->e($t->trans('survey.title'))}</h1><p class="lede">{$this->e($t->trans('survey.question'))}</p></section>
            <section class="card survey-info"><p><strong>{$this->e($t->trans('survey.optional'))}</strong></p><p>{$this->e($t->trans('survey.privacy'))}</p></section>
            <form class="card answer-card" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><fieldset><legend>{$this->e($t->trans('survey.choose'))}</legend><label class="answer-option"><input type="radio" name="answer" value="yes" required><span>{$this->e($t->trans('survey.yes'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="no"><span>{$this->e($t->trans('survey.no'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="unsure"><span>{$this->e($t->trans('survey.unsure'))}</span></label></fieldset><button class="primary" type="submit" name="action" value="answer">{$this->e($t->trans('survey.send'))}</button><button class="secondary button" type="submit" name="action" value="skip" formnovalidate>{$this->e($t->trans('survey.skip'))}</button></form>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function stageTwo(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf, bool $wrong = false): string
    {
        $feedback = $wrong ? '<div class="answer-feedback wrong" role="alert"><strong>' . $this->e($t->trans('learn.try_again')) . '</strong><p>' . $this->e($t->trans('learn.2.wrong')) . '</p></div>' : '';
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/2';
        $sources = $this->sources($t, $i, '2', [
            ['primary', 'https://developer.bitcoin.org/devguide/wallets.html', 'Bitcoin Developer Guide · Wallets'],
            ['primary', 'https://github.com/bitcoin/bips/blob/master/bip-0039.mediawiki', 'BIP 39 · Mnemonic code'],
            ['security', 'https://support.bitbox.swiss/security/can-a-hardware-wallet-be-hacked', 'BitBox Support · Wallet-Sicherheit'],
        ]);
        return $this->layout($t, $locale, $languages, $t->trans('learn.2.title'), <<<HTML
            <div class="lesson-progress" aria-label="{$this->e($t->trans('learn.progress', ['%current%' => '2', '%total%' => '4']))}"><span class="active"></span><span class="active"></span><span></span><span></span></div>
            <section class="hero lesson-hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('learn.eyebrow', ['%current%' => '2', '%total%' => '4']))}</p><h1>{$this->e($t->trans('learn.2.title'))}</h1></section>
            <section class="card lesson-copy"><p>{$this->e($t->trans('learn.2.body_1'))}</p><p>{$this->e($t->trans('learn.2.body_2'))}</p><p>{$this->e($t->trans('learn.2.body_3'))}</p><p class="promise">{$this->e($t->trans('learn.2.body_4'))}</p></section>
            <form class="card answer-card" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><p class="eyebrow">{$this->e($t->trans('learn.check'))}</p><h2>{$this->e($t->trans('learn.2.question'))}</h2><fieldset><legend>{$this->e($t->trans('learn.choose'))}</legend><label class="answer-option"><input type="radio" name="answer" value="photo" required><span>{$this->e($t->trans('learn.2.answer_photo'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="platform" required><span>{$this->e($t->trans('learn.2.answer_platform'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="private" required><span>{$this->e($t->trans('learn.2.answer_private'))}</span></label></fieldset>{$feedback}<button class="primary" type="submit">{$this->e($t->trans('learn.check_answer'))}</button></form>
            {$sources}
            HTML);
    }

    /** @param array<string, string> $languages */
    public function stageThree(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf, bool $wrong = false): string
    {
        $amount = $this->amount($t, $i->satsAmount);
        $body2 = $i->satsAmount === 0 ? $t->trans('learn.3.body_2_zero') : $t->trans('learn.3.body_2_positive');
        $body3 = $i->satsAmount === 0
            ? $t->trans('learn.3.body_3_zero')
            : $t->trans('learn.3.body_3_positive', ['%amount%' => $amount]);
        $feedback = $wrong ? '<div class="answer-feedback wrong" role="alert"><strong>' . $this->e($t->trans('learn.try_again')) . '</strong><p>' . $this->e($t->trans('learn.3.wrong')) . '</p></div>' : '';
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/3';
        $sources = $this->sources($t, $i, '3', [
            ['primary', 'https://github.com/lightning/bolts/blob/master/11-payment-encoding.md', 'BOLT 11 · Lightning Invoice Protocol'],
            ['primary', 'https://developer.bitcoin.org/devguide/wallets.html', 'Bitcoin Developer Guide · Wallets'],
        ]);
        return $this->layout($t, $locale, $languages, $t->trans('learn.3.title'), <<<HTML
            <div class="lesson-progress" aria-label="{$this->e($t->trans('learn.progress', ['%current%' => '3', '%total%' => '4']))}"><span class="active"></span><span class="active"></span><span class="active"></span><span></span></div>
            <section class="hero lesson-hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('learn.eyebrow', ['%current%' => '3', '%total%' => '4']))}</p><h1>{$this->e($t->trans('learn.3.title'))}</h1></section>
            <section class="card lesson-copy"><p>{$this->e($t->trans('learn.3.body_1'))}</p><p>{$this->e($body2)}</p><p class="promise">{$this->e($body3)}</p></section>
            <form class="card answer-card" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><p class="eyebrow">{$this->e($t->trans('learn.check'))}</p><h2>{$this->e($t->trans('learn.3.question'))}</h2><fieldset><legend>{$this->e($t->trans('learn.choose'))}</legend><label class="answer-option"><input type="radio" name="answer" value="request" required><span>{$this->e($t->trans('learn.3.answer_request'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="recovery" required><span>{$this->e($t->trans('learn.3.answer_recovery'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="password" required><span>{$this->e($t->trans('learn.3.answer_password'))}</span></label></fieldset>{$feedback}<button class="primary" type="submit">{$this->e($t->trans('learn.check_answer'))}</button></form>
            {$sources}
            HTML);
    }

    /** @param array<string, string> $languages */
    public function stageFour(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf, bool $wrong = false): string
    {
        $feedback = $wrong ? '<div class="answer-feedback wrong" role="alert"><strong>' . $this->e($t->trans('learn.try_again')) . '</strong><p>' . $this->e($t->trans('learn.4.wrong')) . '</p></div>' : '';
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/learn/4';
        $sources = $this->sources($t, $i, '4', [
            ['primary', 'https://bitcoin.org/bitcoin.pdf', 'Bitcoin-Whitepaper'],
            ['primary', 'https://github.com/bitcoin/bitcoin/blob/master/src/consensus/amount.h', 'Bitcoin Core · amount.h'],
            ['security', 'https://bitcoin.org/en/secure-your-wallet', 'Bitcoin.org · Securing your wallet'],
            ['counter', 'https://www.bis.org/publ/work1049.pdf', 'BIZ Working Paper 1049 · Adoption, Handel und Risiken'],
            ['regulator', 'https://www.finma.ch/~/media/finma/dokumente/dokumentencenter/myfinma/faktenblaetter/faktenblatt-kryptobasierte-vermoegenswerte.pdf?hash=1E5AECBC7B13A7C82FD484B51CCD0BF5&sc_lang=de', 'FINMA · Kryptobasierte Vermögenswerte'],
            ['regulator', 'https://www.finma.ch/de/news/2026/01/20260112-mm-am-01-26/', 'FINMA · Verwahrungsrisiken 2026'],
        ]);
        return $this->layout($t, $locale, $languages, $t->trans('learn.4.title'), <<<HTML
            <div class="lesson-progress" aria-label="{$this->e($t->trans('learn.progress', ['%current%' => '4', '%total%' => '4']))}"><span class="active"></span><span class="active"></span><span class="active"></span><span class="active"></span></div>
            <section class="hero lesson-hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('learn.eyebrow', ['%current%' => '4', '%total%' => '4']))}</p><h1>{$this->e($t->trans('learn.4.title'))}</h1></section>
            <section class="card lesson-copy"><p>{$this->e($t->trans('learn.4.body_1'))}</p><p>{$this->e($t->trans('learn.4.body_2'))}</p><p>{$this->e($t->trans('learn.4.body_3'))}</p><p class="promise">{$this->e($t->trans('learn.4.body_4'))}</p></section>
            <form class="card answer-card" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><p class="eyebrow">{$this->e($t->trans('learn.check'))}</p><h2>{$this->e($t->trans('learn.4.question'))}</h2><fieldset><legend>{$this->e($t->trans('learn.choose'))}</legend><label class="answer-option"><input type="radio" name="answer" value="guaranteed" required><span>{$this->e($t->trans('learn.4.answer_guaranteed'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="balanced" required><span>{$this->e($t->trans('learn.4.answer_balanced'))}</span></label><label class="answer-option"><input type="radio" name="answer" value="replacement" required><span>{$this->e($t->trans('learn.4.answer_replacement'))}</span></label></fieldset>{$feedback}<button class="primary" type="submit">{$this->e($t->trans('learn.check_answer'))}</button></form>
            {$sources}
            HTML);
    }

    /** @param array<string, string> $languages */
    public function walletChoice(TranslatorInterface $t, string $locale, array $languages, Invitation $i, string $csrf): string
    {
        $action = '/erste-sats/i/' . rawurlencode($i->publicId) . '/wallet';
        $option = function (string $value, string $tag, string $title, string $copy) use ($i): string {
            $checked = $i->walletPath === $value ? ' checked' : '';
            return '<label class="wallet-option"><input type="radio" name="wallet" value="' . $this->e($value) . '" required' . $checked . '><span><small>' . $this->e($tag) . '</small><strong>' . $this->e($title) . '</strong><span>' . $this->e($copy) . '</span></span></label>';
        };
        $options = $option('wos', $t->trans('wallet.wos.tag'), 'Wallet of Satoshi', $t->trans('wallet.wos.copy'))
            . $option('phoenix', $t->trans('wallet.phoenix.tag'), 'Phoenix', $t->trans('wallet.phoenix.copy'))
            . $option('other', $t->trans('wallet.other.tag'), $t->trans('wallet.other.title'), $t->trans('wallet.other.copy'));

        return $this->layout($t, $locale, $languages, $t->trans('wallet.title'), <<<HTML
            <section class="hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('wallet.eyebrow'))}</p><h1>{$this->e($t->trans('wallet.title'))}</h1><p class="lede">{$this->e($t->trans('wallet.intro'))}</p></section>
            <form class="card wallet-form" method="post" action="{$this->e($action)}"><input type="hidden" name="csrf" value="{$this->e($csrf)}"><input type="hidden" name="lang" value="{$this->e($locale)}"><fieldset><legend>{$this->e($t->trans('wallet.choose'))}</legend>{$options}</fieldset><button class="primary" type="submit">{$this->e($t->trans('wallet.continue'))}</button></form>
            <section class="card wallet-neutral"><h2>{$this->e($t->trans('wallet.neutral_title'))}</h2><p>{$this->e($t->trans('wallet.neutral_copy'))}</p></section>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function walletSetup(TranslatorInterface $t, string $locale, array $languages, Invitation $i): string
    {
        $amount = $this->amount($t, $i->satsAmount);
        $path = $i->walletPath ?? 'other';
        $provider = match ($path) { 'wos' => 'Wallet of Satoshi', 'phoenix' => 'Phoenix', default => $t->trans('wallet.other.title') };
        $providerUrl = match ($path) { 'wos' => 'https://www.walletofsatoshi.com/', 'phoenix' => 'https://phoenix.acinq.co/', default => null };
        $providerLink = $providerUrl === null ? '' : '<a class="secondary button provider-link" href="' . $this->e($providerUrl) . '" target="_blank" rel="noreferrer">' . $this->e($t->trans('wallet.setup.open_provider', ['%provider%' => $provider])) . '</a>';
        $specific = match ($path) {
            'wos' => [$t->trans('wallet.setup.wos.1'), $t->trans('wallet.setup.wos.2'), $t->trans('wallet.setup.wos.3', ['%amount%' => $amount]), $t->trans('wallet.setup.wos.4')],
            'phoenix' => [$t->trans('wallet.setup.phoenix.1'), $t->trans('wallet.setup.phoenix.2'), $t->trans('wallet.setup.phoenix.3'), $t->trans('wallet.setup.phoenix.4', ['%amount%' => $amount])],
            default => [$t->trans('wallet.setup.other.1'), $t->trans('wallet.setup.other.2'), $t->trans('wallet.setup.other.3', ['%amount%' => $amount]), $t->trans('wallet.setup.other.4')],
        };
        $steps = '';
        foreach ($specific as $step) $steps .= '<li>' . $this->e($step) . '</li>';
        $changeUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/wallet?lang=' . rawurlencode($locale);
        $homeUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/view?lang=' . rawurlencode($locale);
        $requestUrl = '/erste-sats/i/' . rawurlencode($i->publicId) . '/payment-request?lang=' . rawurlencode($locale);

        return $this->layout($t, $locale, $languages, $t->trans('wallet.setup.title'), <<<HTML
            <section class="hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('wallet.setup.eyebrow'))}</p><h1>{$this->e($t->trans('wallet.setup.title'))}</h1><p class="lede">{$this->e($t->trans('wallet.setup.intro', ['%provider%' => $provider]))}</p></section>
            <section class="card setup-card"><h2>{$this->e($provider)}</h2><ol>{$steps}</ol>{$providerLink}</section>
            <section class="card safety-card"><h2>{$this->e($t->trans('wallet.setup.safety_title'))}</h2><p>{$this->e($t->trans('wallet.setup.safety_copy'))}</p><strong>{$this->e($t->trans('wallet.setup.never'))}</strong></section>
            <section class="card setup-next"><h2>{$this->e($t->trans('wallet.setup.next_title'))}</h2><p>{$this->e($t->trans('wallet.setup.next_copy', ['%amount%' => $amount]))}</p><a class="primary button" href="{$this->e($requestUrl)}">{$this->e($t->trans('payment.start'))}</a><a class="secondary button" href="{$this->e($changeUrl)}">{$this->e($t->trans('wallet.setup.change'))}</a><a class="text-link" href="{$this->e($homeUrl)}">{$this->e($t->trans('wallet.setup.back'))}</a></section>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function paymentRequest(TranslatorInterface $t,string $locale,array $languages,Invitation $i,string $csrf,?PaymentRequest $saved,?HandoverState $handover=null,?string $error=null,string $value=''): string
    {
        $amount=$this->amount($t,$i->satsAmount);$action='/erste-sats/i/'.rawurlencode($i->publicId).'/payment-request';
        $errorHtml=$error===null?'':'<div class="answer-feedback wrong" role="alert"><strong>'.$this->e($t->trans('payment.error_title')).'</strong><p>'.$this->e($t->trans('payment.error.'.$error)).'</p></div>';
        $savedHtml='';
        $formHtml='<form class="card payment-form" method="post" action="'.$this->e($action).'"><input type="hidden" name="csrf" value="'.$this->e($csrf).'"><input type="hidden" name="lang" value="'.$this->e($locale).'"><label class="field"><span>'.$this->e($t->trans('payment.label')).'</span><textarea name="invoice" rows="7" required spellcheck="false" autocomplete="off" placeholder="lnbc…">'.$this->e($value).'</textarea></label><p class="help">'.$this->e($t->trans('payment.help',['%amount%'=>$amount])).'</p>'.$errorHtml.'<button class="primary" type="submit">'.$this->e($t->trans('payment.submit')).'</button></form>';
        if($handover?->closedWithoutConfirmationAt!==null){
            $savedHtml='<section class="card"><strong>'.$this->e($t->trans('handover.closed_title')).'</strong><p>'.$this->e($t->trans('handover.closed_copy')).'</p></section>';
            $formHtml='';
        } elseif($handover?->receivedAt!==null){
            $nextUrl='/erste-sats/i/'.rawurlencode($i->publicId).'/next?lang='.rawurlencode($locale);
            $savedHtml='<section class="card journey-complete"><strong>'.$this->e($t->trans('handover.received_title')).'</strong><p>'.$this->e($t->trans('handover.received_copy')).'</p><a class="primary button" href="'.$this->e($nextUrl).'">'.$this->e($t->trans('next.open')).'</a></section>';
            $formHtml='';
        } elseif($saved!==null){
            $sent=$handover?->sentAt===null?$t->trans('handover.waiting_sent'):$t->trans('handover.sent_notice');
            $notReceived=$handover?->notReceivedAt===null?'':'<p class="warning">'.$this->e($t->trans('handover.not_received_saved')).'</p>';
            $baseFields='<input type="hidden" name="csrf" value="'.$this->e($csrf).'"><input type="hidden" name="lang" value="'.$this->e($locale).'">';
            $savedHtml='<section class="card journey-complete"><strong>'.$this->e($t->trans('payment.saved_title')).'</strong><p>'.$this->e($t->trans('payment.saved_copy',['%date%'=>$saved->expiresAt->format('d.m.Y H:i')])).'</p><p>'.$this->e($sent).'</p>'.$notReceived.'</section><section class="card handover-actions"><form method="post" action="/erste-sats/i/'.$this->e(rawurlencode($i->publicId)).'/received">'.$baseFields.'<label class="check-row"><input type="checkbox" name="receipt_confirmed" value="1" required><span>'.$this->e($t->trans('handover.received_check')).'</span></label><button class="primary" type="submit">'.$this->e($t->trans('handover.received_button')).'</button></form><form method="post" action="/erste-sats/i/'.$this->e(rawurlencode($i->publicId)).'/not-received">'.$baseFields.'<button class="secondary button" type="submit">'.$this->e($t->trans('handover.not_received_button')).'</button></form></section>';
            if($handover?->sentAt!==null){
                $formHtml='';
            } else {
                $open=$error===null?'':' open';
                $replacementForm=str_replace('class="card payment-form"','class="payment-form"',$formHtml);
                $formHtml='<details class="card replace-invoice"'.$open.'><summary>'.$this->e($t->trans('payment.replace')).'</summary><p class="help">'.$this->e($t->trans('payment.replace_copy')).'</p>'.$replacementForm.'</details>';
            }
        }
        return $this->layout($t,$locale,$languages,$t->trans('payment.title'),<<<HTML
            <section class="hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('payment.eyebrow'))}</p><h1>{$this->e($t->trans('payment.title'))}</h1><p class="lede">{$this->e($t->trans('payment.intro',['%amount%'=>$amount]))}</p></section>{$savedHtml}
            {$formHtml}
            <section class="card wallet-neutral"><h2>{$this->e($t->trans('payment.check_title'))}</h2><p>{$this->e($t->trans('payment.check_copy'))}</p></section>
            HTML);
    }

    /** @param array<string, string> $languages */
    public function management(TranslatorInterface $t,string $locale,array $languages,Invitation $i,?PaymentRequest $payment=null,?HandoverState $handover=null,string $csrf=''): string
    {
        $paymentHtml='';
        if($handover?->receivedAt!==null){$paymentHtml='<section class="card journey-complete"><strong>'.$this->e($t->trans('management.received_title')).'</strong><p>'.$this->e($t->trans('management.received_copy')).'</p></section>';}
        elseif($payment!==null){$expired=$payment->expiresAt<=new DateTimeImmutable();$sent=$handover?->sentAt!==null?'<div class="journey-complete"><strong>'.$this->e($t->trans('management.sent_title')).'</strong><p>'.$this->e($t->trans('management.sent_copy')).'</p></div>':($expired?'<div class="answer-feedback wrong"><strong>'.$this->e($t->trans('management.expired_title')).'</strong><p>'.$this->e($t->trans('management.expired_copy')).'</p></div>':'<form method="post" action="/erste-sats/manage/'.$this->e(rawurlencode($i->publicId)).'/sent"><input type="hidden" name="csrf" value="'.$this->e($csrf).'"><input type="hidden" name="lang" value="'.$this->e($locale).'"><button class="primary" type="submit">'.$this->e($t->trans('management.sent_button')).'</button></form>');$notReceived=$handover?->notReceivedAt===null?'':'<p class="warning">'.$this->e($t->trans('management.not_received_notice')).'</p>';$paymentHtml='<section class="card secrets"><h2>'.$this->e($t->trans('management.payment_title')).'</h2><p>'.$this->e($t->trans('management.payment_copy',['%amount%'=>$this->amount($t,$payment->satsAmount),'%date%'=>$payment->expiresAt->format('d.m.Y H:i')])).'</p>'.$this->copySecret($t->trans('management.invoice'),$payment->bolt11,'payment-invoice',$t->trans('management.copy'),$t->trans('management.copied')).'<p class="warning">'.$this->e($t->trans('management.payment_warning')).'</p>'.$notReceived.$sent.'</section><script src="/assets/first-sats-share.js" defer></script>';}
        $now=new DateTimeImmutable();$openExpired=$i->status==='open'&&$i->acceptUntil<$now;$learningExpired=$i->status==='accepted'&&$i->stageFourCompletedAt===null&&$i->completeUntil!==null&&$i->completeUntil<$now;
        $statusKey=$i->status==='revoked'?'management.revoked':($i->status==='participant_ended'?'management.participant_ended':($openExpired?'management.open_expired':($i->status==='open'?'management.open':($handover?->closedWithoutConfirmationAt!==null?'management.closed_unconfirmed':($handover?->receivedAt!==null?'management.received_status':($handover?->sentAt!==null?'management.sent_status':($learningExpired?'management.learning_expired':($i->stageFourCompletedAt===null?'management.learning':($payment===null?'management.waiting':'management.request_ready')))))))));
        $deadlineLabel=$i->status==='accepted'&&$i->stageFourCompletedAt===null?$t->trans('management.complete_until'):$t->trans('management.until');
        $deadline=$i->status==='accepted'&&$i->stageFourCompletedAt===null?$i->completeUntil:$i->acceptUntil;
        $fields='<input type="hidden" name="csrf" value="'.$this->e($csrf).'"><input type="hidden" name="lang" value="'.$this->e($locale).'">';$actions='';
        if($i->status==='open'){$extend=$i->acceptUntil>=$now->sub(new \DateInterval('P30D'))?'<form method="post" action="/erste-sats/manage/'.$this->e(rawurlencode($i->publicId)).'/extend">'.$fields.'<p>'.$this->e($t->trans('management.extend_open_copy')).'</p><button class="secondary button" type="submit">'.$this->e($t->trans('management.extend')).'</button></form>':'';$actions='<section class="card management-actions"><h2>'.$this->e($t->trans('management.actions_title')).'</h2>'.$extend.'<form method="post" action="/erste-sats/manage/'.$this->e(rawurlencode($i->publicId)).'/revoke">'.$fields.'<label class="check"><input type="checkbox" name="confirm_revoke" value="1" required><span>'.$this->e($t->trans('management.revoke_check')).'</span></label><button class="secondary button danger" type="submit">'.$this->e($t->trans('management.revoke')).'</button></form></section>';}
        elseif($i->status==='accepted'&&$handover===null&&$payment===null){$extend=$i->stageFourCompletedAt===null&&$i->completeUntil!==null&&$i->completeUntil>=$now->sub(new \DateInterval('P30D'))?'<form method="post" action="/erste-sats/manage/'.$this->e(rawurlencode($i->publicId)).'/extend">'.$fields.'<p>'.$this->e($t->trans('management.extend_learning_copy')).'</p><button class="secondary button" type="submit">'.$this->e($t->trans('management.extend')).'</button></form>':'';$actions='<section class="card management-actions"><h2>'.$this->e($t->trans('management.actions_title')).'</h2>'.$extend.'<form method="post" action="/erste-sats/manage/'.$this->e(rawurlencode($i->publicId)).'/reset">'.$fields.'<label class="check"><input type="checkbox" name="confirm_reset" value="1" required><span>'.$this->e($t->trans('management.reset_check')).'</span></label><button class="secondary button danger" type="submit">'.$this->e($t->trans('management.reset')).'</button></form></section>';}
        return $this->layout($t, $locale, $languages, $t->trans('management.title'), <<<HTML
            <section class="hero"><p class="eyebrow">{$this->e($t->trans('management.eyebrow'))}</p><h1>{$this->e($t->trans('management.title'))}</h1></section>
            <section class="card"><dl><dt>{$this->e($t->trans('management.invitee'))}</dt><dd>{$this->e($i->inviteeLabel)}</dd><dt>{$this->e($t->trans('management.amount'))}</dt><dd>{$this->e($this->amount($t, $i->satsAmount))}</dd><dt>{$this->e($t->trans('management.status'))}</dt><dd>{$this->e($t->trans($statusKey))}</dd><dt>{$this->e($deadlineLabel)}</dt><dd>{$this->e($deadline?->format('d.m.Y')??'–')}</dd></dl><p class="help">{$this->e($t->trans('management.stage_notice'))}</p></section>{$paymentHtml}{$actions}
            HTML);
    }

    /** @param array<string,string> $languages */
    public function resetDone(TranslatorInterface $t,string $locale,array $languages,Invitation $i,string $inviteLink):string
    {
        return $this->layout($t,$locale,$languages,$t->trans('management.reset_done_title'),'<section class="hero"><p class="eyebrow">'.$this->e($t->trans('management.reset_done_eyebrow')).'</p><h1>'.$this->e($t->trans('management.reset_done_title')).'</h1><p class="lede">'.$this->e($t->trans('management.reset_done_copy',['%name%'=>$i->inviteeLabel])).'</p></section><section class="card secrets">'.$this->copySecret($t->trans('management.new_invite_link'),$inviteLink,'reset-invite-link',$t->trans('management.copy_invite'),$t->trans('management.copied_invite')).'<p class="warning">'.$this->e($t->trans('management.reset_done_warning')).'</p><p class="copy-status" data-copy-status aria-live="polite"></p></section><script src="/assets/first-sats-share.js" defer></script>');
    }

    /** @param array<string, string> $languages */
    public function nextSteps(TranslatorInterface $t,string $locale,array $languages,Invitation $i): string
    {
        $zeroSats=$i->satsAmount===0;
        $introKey=$zeroSats?'next.intro_zero':'next.intro_positive';
        $modeSuffix=$zeroSats?'_zero':'';
        $newInvitation='/erste-sats?lang='.rawurlencode($locale);
        $back='/erste-sats/i/'.rawurlencode($i->publicId).'/view?lang='.rawurlencode($locale);
        return $this->layout($t,$locale,$languages,$t->trans('next.title'),<<<HTML
            <section class="hero next-hero"><div class="lesson-brand"><span class="lesson-icon"><img src="/assets/sats4you-mark-mono-light.svg" alt=""></span><strong>Erste Sats</strong></div><p class="eyebrow">{$this->e($t->trans('next.eyebrow'))}</p><h1>{$this->e($t->trans('next.title'))}</h1><p class="lede">{$this->e($t->trans($introKey))}</p></section>
            <section class="card next-option"><h2>{$this->e($t->trans('next.keep_title'.$modeSuffix))}</h2><p>{$this->e($t->trans('next.keep_copy'.$modeSuffix))}</p></section>
            <section class="card next-option"><h2>{$this->e($t->trans('next.pay_title'.$modeSuffix))}</h2><p>{$this->e($t->trans('next.pay_copy'.$modeSuffix))}</p></section>
            <section class="card next-option"><h2><a class="next-option-link" href="{$this->e($newInvitation)}"><span>{$this->e($t->trans('next.give_title'.$modeSuffix))}</span><span class="next-link-symbol" aria-hidden="true">&rarr;</span></a></h2><p>{$this->e($t->trans('next.give_copy'.$modeSuffix))}</p></section>
            <section class="card next-option"><h2>{$this->e($t->trans('next.learn_title'))}</h2><p>{$this->e($t->trans('next.learn_copy'))}</p></section>
            <section class="card safety-card"><h2>{$this->e($t->trans('next.risk_title'))}</h2><p>{$this->e($t->trans('next.risk_copy'.$modeSuffix))}</p></section>
            <section class="card next-actions"><a class="primary button" href="{$this->e($newInvitation)}">{$this->e($t->trans('next.give_action'.$modeSuffix))}</a><a class="secondary button" href="{$this->e($back)}">{$this->e($t->trans('next.back'))}</a></section>
            HTML);
    }

    /** @param array<string, string> $languages @param list<string> $paragraphs */
    public function information(TranslatorInterface $t, string $locale, array $languages, string $title, array $paragraphs): string
    {
        $copy = '';
        foreach ($paragraphs as $paragraph) {
            $copy .= '<p>' . $this->e($paragraph) . '</p>';
        }

        return $this->layout($t, $locale, $languages, $title, '<section class="hero"><h1>' . $this->e($title) . '</h1></section><section class="card">' . $copy . '</section>', true, true);
    }

    /** @param array<string, string> $languages */
    public function legalInformation(
        TranslatorInterface $t,
        string $locale,
        array $languages,
        string $document,
        int $sectionCount,
        string $version,
        string $effectiveDate,
    ): string {
        $prefix = 'legal.' . $document;
        $overview = '';
        foreach (range(1, 4) as $number) {
            $overview .= '<li>' . $this->e($t->trans($prefix . '.overview_' . $number)) . '</li>';
        }

        $sections = '';
        foreach (range(1, $sectionCount) as $number) {
            $sections .= '<section class="card legal-section" id="section-' . $number . '"><h2>'
                . $this->e($t->trans($prefix . '.section_' . $number . '_title')) . '</h2><p>'
                . $this->e($t->trans($prefix . '.section_' . $number . '_body')) . '</p></section>';
        }

        $title = $t->trans($prefix . '.title');
        $meta = $t->trans('legal.version', ['%version%' => $version, '%date%' => $effectiveDate]);
        $contact = '<section class="card legal-contact"><h2>' . $this->e($t->trans('legal.contact_title')) . '</h2><p>'
            . $this->e($t->trans('legal.contact_body')) . '</p><p><a href="mailto:hallo@sats4you.ch">hallo@sats4you.ch</a><br>'
            . '<a href="mailto:security@sats4you.ch">security@sats4you.ch</a></p></section>';

        return $this->layout($t, $locale, $languages, $title,
            '<section class="hero legal-hero"><p class="eyebrow">' . $this->e($t->trans('legal.eyebrow')) . '</p><h1>'
            . $this->e($title) . '</h1><p class="lede">' . $this->e($t->trans($prefix . '.intro')) . '</p><p class="legal-meta">'
            . $this->e($meta) . '</p><p class="warning">' . $this->e($t->trans('info.legal_pending')) . '</p></section>'
            . '<section class="card legal-overview"><h2>' . $this->e($t->trans($prefix . '.overview_title')) . '</h2><ul>'
            . $overview . '</ul></section>' . $sections . $contact, true, true);
    }

    /** @param array<string,string> $languages */
    public function chfMethodology(TranslatorInterface $t, string $locale, array $languages): string
    {
        $items = '';
        foreach (range(1, 7) as $number) {
            $items .= '<li>' . $this->e($t->trans('chf.method.' . $number)) . '</li>';
        }
        $source = '<a href="https://docs.kraken.com/api-reference/market-data/get-ticker-information" target="_blank" rel="noreferrer">' . $this->e($t->trans('chf.method.source_link')) . '</a>';

        return $this->layout($t, $locale, $languages, $t->trans('chf.method.title'), '<section class="hero"><p class="eyebrow">' . $this->e($t->trans('chf.method.eyebrow')) . '</p><h1>' . $this->e($t->trans('chf.method.title')) . '</h1><p class="lede">' . $this->e($t->trans('chf.method.intro')) . '</p></section><section class="card"><ol class="operator-method">' . $items . '</ol><p>' . $source . '</p><p class="help">' . $this->e($t->trans('chf.method.reviewed')) . '</p></section>', true, true);
    }

    /** @param list<array{0:string,1:string,2:string}> $links */
    private function sources(TranslatorInterface $t, Invitation $i, string $stage, array $links): string
    {
        $items = '';
        foreach ($links as [$type, $url, $label]) {
            $items .= '<li><span class="source-type">' . $this->e($t->trans('learn.source_type.' . $type)) . '</span><a href="'
                . $this->e($url) . '" target="_blank" rel="noreferrer">' . $this->e($label) . '</a></li>';
        }
        $reviewedAt = self::CONTENT_REVIEWED_AT[$i->learningContentVersion] ?? $t->trans('learn.source_review_pending');
        $meta = $t->trans('learn.source_meta', ['%version%' => $i->learningContentVersion, '%date%' => $reviewedAt]);

        return '<details class="card sources"><summary>' . $this->e($t->trans('learn.sources')) . '</summary><p>'
            . $this->e($t->trans('learn.' . $stage . '.source_note')) . '</p><ul>' . $items . '</ul><small>'
            . $this->e($meta) . '</small></details>';
    }

    /** @param array<string, string> $languages */
    private function layout(TranslatorInterface $t, string $locale, array $languages, string $title, string $content, bool $showFaqInFooter = true, bool $indexable = false): string
    {
        $options = '';
        foreach ($languages as $code => $label) {
            $selected = $code === $locale ? ' selected' : '';
            $options .= '<option value="' . $this->e($code) . '"' . $selected . '>' . $this->e($label) . '</option>';
        }

        $faq = $showFaqInFooter ? '<a href="/erste-sats/faq?lang=' . $this->e($locale) . '">' . $this->e($t->trans('nav.faq')) . '</a> · ' : '';
        $legal = '<a href="/erste-sats/terms?lang=' . $this->e($locale) . '">' . $this->e($t->trans('form.terms_link')) . '</a> · '
            . '<a href="/erste-sats/privacy?lang=' . $this->e($locale) . '">' . $this->e($t->trans('nav.privacy')) . '</a> · ';
        $rateMeta = '';
        if ($this->usedAmount) {
            $rateMeta = $this->usedQuote === null
                ? '<aside class="rate-meta">' . $this->e($t->trans('amount.chf_unavailable')) . ' · <a href="/erste-sats/chf-methodology?lang=' . $this->e($locale) . '">' . $this->e($t->trans('nav.chf_method')) . '</a></aside>'
                : '<aside class="rate-meta">' . $this->e($t->trans('amount.rate_meta', ['%rate%' => $this->formatCents($this->usedQuote->midpointCents()), '%time%' => $this->usedQuote->fetchedAt->setTimezone(new DateTimeZone('Europe/Zurich'))->format('d.m.Y H:i')])) . ' · <a href="/erste-sats/chf-methodology?lang=' . $this->e($locale) . '">' . $this->e($t->trans('nav.chf_method')) . '</a></aside>';
        }
        $this->usedAmount = false;
        $this->usedQuote = null;
        $robots = $indexable ? 'index,follow' : 'noindex,nofollow,noarchive';
        return '<!doctype html><html lang="' . $this->e($locale) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="' . $robots . '"><title>' . $this->e($title) . ' · Erste Sats</title><link rel="icon" href="/assets/sats4you-favicon.svg"><link rel="stylesheet" href="/assets/first-sats.css"></head><body><main><header><a href="/erste-sats?lang=' . $this->e($locale) . '" class="brand"><img src="/assets/sats4you-wordmark.svg" alt="sats4you.ch"></a><select data-language aria-label="' . $this->e($t->trans('nav.language')) . '">' . $options . '</select></header>' . $content . $rateMeta . '<footer>' . $faq . $legal . '<a href="mailto:hallo@sats4you.ch">hallo@sats4you.ch</a><br>' . $this->e($t->trans('footer.prototype')) . '</footer></main><script src="/assets/first-sats-common.js" defer></script></body></html>';
    }

    private function amount(TranslatorInterface $t, int $sats): string
    {
        return number_format($sats, 0, '.', "'") . ' Sats · ' . $this->chf($t, $sats);
    }

    private function amountPreset(TranslatorInterface $t, int $sats): string
    {
        return '<button class="chip" type="button" data-sats="' . $sats . '"><strong>'
            . number_format($sats, 0, '.', "'") . ' Sats</strong><small>'
            . $this->e($this->chf($t, $sats)) . '</small></button>';
    }

    private function chf(TranslatorInterface $t, int $sats): string
    {
        $this->usedAmount = true;
        if ($sats === 0) {
            return $t->trans('amount.chf_zero');
        }
        $quote = $this->quote();
        if ($quote === null) {
            return $t->trans('amount.chf_unavailable');
        }
        $this->usedQuote = $quote;
        $cents = $quote->estimateCents($sats);

        return $cents === 0 ? $t->trans('amount.chf_under_cent') : $t->trans('amount.chf_estimate', ['%value%' => $this->formatCents($cents)]);
    }

    private function quote(): ?ChfQuote
    {
        return $this->quotes->current(new DateTimeImmutable());
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', "'");
    }

    private function copySecret(string $label, string $value, string $id, string $copyLabel, string $copiedLabel): string
    {
        return '<div class="secret-item"><span class="field-label">' . $this->e($label) . '</span><div class="copy-box"><code id="'
            . $this->e($id) . '">' . $this->e($value) . '</code><button type="button" class="copy-icon" data-copy-target="'
            . $this->e($id) . '" data-copied-label="' . $this->e($copiedLabel) . '" aria-label="' . $this->e($copyLabel) . '" title="' . $this->e($copyLabel)
            . '"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></div></div>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
