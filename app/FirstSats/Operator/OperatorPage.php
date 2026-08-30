<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Operator;

use Symfony\Contracts\Translation\TranslatorInterface;

final class OperatorPage
{
    private const MINIMUM=10;

    /** @param array<string,string> $languages */
    public function bootstrap(TranslatorInterface $t,string $locale,array $languages):string
    {
        $config=$this->e(json_encode([
            'exchange'=>'/erste-sats/api/access/operator',
            'target'=>'/erste-sats/operator/dashboard',
            'locale'=>$locale,
            'ready'=>$t->trans('access.ready'),
            'incomplete'=>$t->trans('access.incomplete'),
            'denied'=>$t->trans('access.denied'),
        ],JSON_THROW_ON_ERROR));
        return $this->layout($t,$locale,$languages,$t->trans('operator.access_title'),'<section class="card" id="first-sats-access" data-config="'.$config.'"><h1 id="state-title">'.$this->e($t->trans('operator.access_title')).'</h1><p id="state-copy">'.$this->e($t->trans('operator.access_checking')).'</p><a class="primary button" id="continue" href="#" hidden>'.$this->e($t->trans('access.continue')).'</a></section><script src="/assets/first-sats-access.js" defer></script>');
    }

    /** @param array<string,string> $languages */
    public function dashboard(TranslatorInterface $t,string $locale,array $languages,DashboardData $data,string $baseUrl):string
    {
        $funnel='';foreach(['created','opened','started','completed','payment_requested','sent_confirmed','received_confirmed','participant_ended'] as $key){$funnel.=$this->metric($t,$t->trans('operator.metric_'.$key),$data->funnel[$key]??0);}
        $wallets='';foreach(['wos','phoenix','other'] as $key){$wallets.=$this->metric($t,$t->trans('operator.wallet_'.$key),$data->wallets[$key]??0);}
        $answers=(int)($data->survey['yes']??0)+(int)($data->survey['no']??0)+(int)($data->survey['unsure']??0);
        $survey=$answers<self::MINIMUM?'<p class="suppressed">'.$this->e($t->trans('operator.suppressed')).'</p>':'';
        if($answers>=self::MINIMUM){foreach(['yes','no','unsure','skipped'] as $key){$survey.=$this->metric($t,$t->trans('operator.survey_'.$key),$data->survey[$key]??0);}}
        $promotion=$t->trans('operator.promotion_text',['%start%'=>$baseUrl.'/erste-sats','%faq%'=>$baseUrl.'/erste-sats/faq']);
        return $this->layout($t,$locale,$languages,$t->trans('operator.title'),'<section class="hero"><p class="eyebrow">'.$this->e($t->trans('operator.eyebrow')).'</p><h1>'.$this->e($t->trans('operator.title')).'</h1><p class="lede">'.$this->e($t->trans('operator.lead')).'</p></section><section class="card"><h2>'.$this->e($t->trans('operator.funnel_title')).'</h2><p>'.$this->e($t->trans('operator.funnel_copy')).'</p><div class="operator-grid">'.$funnel.'</div></section><section class="card"><h2>'.$this->e($t->trans('operator.wallet_title')).'</h2><p>'.$this->e($t->trans('operator.wallet_copy')).'</p><div class="operator-grid">'.$wallets.'</div></section><section class="card"><h2>'.$this->e($t->trans('operator.survey_title')).'</h2><p>'.$this->e($t->trans('operator.survey_question')).'</p><p class="help">'.$this->e($t->trans('operator.version',['%version%'=>$data->surveyVersion])).'</p><div class="operator-grid">'.$survey.'</div></section><section class="card"><h2>'.$this->e($t->trans('operator.method_title')).'</h2><ul class="operator-method"><li>'.$this->e($t->trans('operator.method_1')).'</li><li>'.$this->e($t->trans('operator.method_2')).'</li><li>'.$this->e($t->trans('operator.method_3')).'</li><li>'.$this->e($t->trans('operator.method_4')).'</li></ul></section><section class="card secrets"><h2>'.$this->e($t->trans('operator.promotion_title')).'</h2><p>'.$this->e($t->trans('operator.promotion_copy')).'</p><textarea id="share-text">'.$this->e($promotion).'</textarea><button class="primary" type="button" data-share data-title="'.$this->e($t->trans('operator.promotion_subject')).'">'.$this->e($t->trans('operator.promotion_button')).'</button></section><script src="/assets/first-sats-share.js" defer></script>');
    }

    private function metric(TranslatorInterface $t,string $label,int $count):string
    {
        $value=$count>=self::MINIMUM?number_format($count,0,'.',"'"):$t->trans('operator.hidden_value');
        return '<div class="operator-metric"><strong>'.$this->e($value).'</strong><span>'.$this->e($label).'</span></div>';
    }

    /** @param array<string,string> $languages */
    private function layout(TranslatorInterface $t,string $locale,array $languages,string $title,string $content):string
    {
        $options='';foreach($languages as $code=>$label){$options.='<option value="'.$this->e($code).'"'.($code===$locale?' selected':'').'>'.$this->e($label).'</option>';}
        return '<!doctype html><html lang="'.$this->e($locale).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$this->e($title).' · Erste Sats</title><link rel="icon" href="/assets/sats4you-favicon.svg"><link rel="stylesheet" href="/assets/first-sats.css"></head><body><main><header><span class="brand"><img src="/assets/sats4you-wordmark.svg" alt="sats4you.ch"></span><select data-language aria-label="'.$this->e($t->trans('nav.language')).'">'.$options.'</select></header>'.$content.'<footer>'.$this->e($t->trans('operator.private_footer')).'</footer></main><script src="/assets/first-sats-common.js" defer></script></body></html>';
    }

    private function e(string $value):string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
}
