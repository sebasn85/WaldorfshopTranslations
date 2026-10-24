<?php
namespace WaldorfshopTranslations\Services;
use WaldorfshopTranslations\Core\TranslationEngine;
use WaldorfshopTranslations\Core\NightlyBatch;
use WaldorfshopTranslations\Core\RecoveryReview;
use WaldorfshopTranslations\Core\TranslationIssue;
use WaldorfshopTranslations\Core\EmptySourceReview;
use WaldorfshopTranslations\Core\ReviewStore;
use WaldorfshopTranslations\Repositories\StateRepository;

class Runner
{
    private $http;
    private $gateway;
    private $store;
    public function __construct(HttpClient $http, Gateway $gateway, StateRepository $store)
    { $this->http = $http; $this->gateway = $gateway; $this->store = $store; }
    public function settings(): array
    {
        $settings = $this->http->settings();
        return $this->gateway->preflight($settings);
    }
    protected function now(): int { return time(); }
    public function pilot(): array
    {
        $this->store->acquire();
        try {
            $settings = $this->settings();
            $id = (int)$this->http->option('pilotItemId', '0');
            if ($id <= 0) { throw new \RuntimeException('Pilotartikel fehlt.'); }
            foreach ($this->gateway->candidates() as $candidate) {
                if ($candidate['item_id'] === $id) {
                    $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $settings]);
                    $result = $engine->run($candidate, $this->http->option('writesAllowed', '0') === '1');
                    $this->store->put('last-result', $result + ['at' => gmdate('c'), 'kind' => 'pilot']);
                    return $result;
                }
            }
            throw new \RuntimeException('Pilotartikel trägt keinen lesbaren Auslöser-Tag.');
        } finally { $this->store->release(); }
    }
    public function automatic(): array
    {
        if ($this->http->option('automaticEnabled', '0') !== '1' || $this->http->option('writesAllowed', '0') !== '1') { return ['status' => 'disabled']; }
        if (!NightlyBatch::inWindow($this->now())) { return ['status'=>'scheduled', 'timezone'=>'Europe/Berlin', 'start_hour'=>NightlyBatch::START_HOUR]; }
        $this->store->acquire();
        try {
            $batch = pluginApp(NightlyBatch::class, [
                $this->store,
                function () { return $this->settings(); },
                function () { return $this->gateway->candidates(); },
                function ($candidate, $settings, $recovering) {
                    if (!$this->gateway->hasAnyTrigger($candidate)) {
                        $job = $this->store->get('job:' . $candidate['item_id']);
                        return ['item_id'=>$candidate['item_id'], 'status'=>$recovering && $job && $job['status'] === 'complete' ? 'complete' : 'cancelled'];
                    }
                    $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $settings, function () { $this->http->checkDeadline(); }]);
                    return $engine->run($candidate, true);
                },
                function ($deadline) { $this->http->setDeadline($deadline); },
                function () { return $this->now(); }
            ]);
            return $batch->run();
        } finally { $this->http->setDeadline(0); $this->store->release(); }
    }
    private function recoveryCandidate(int $itemId): array
    {
        if ($itemId <= 0) { throw new TranslationIssue('invalid_item', 'Gültige Artikel-ID erforderlich.'); }
        foreach ($this->gateway->candidates() as $candidate) {
            if ($candidate['item_id'] === $itemId) { return $candidate; }
        }
        throw new TranslationIssue('trigger_missing', 'Artikel trägt keinen aktuellen Auslöser-Tag.');
    }
    private function reviewedRecovery(array $settings, array $candidate, array $corrections, array $retainEmptySource): array
    {
        $key = 'baseline:' . $candidate['item_id'];
        $before = $this->store->get($key) ?? []; $after = $before; $readStore = $this->store;
        if ($retainEmptySource) {
            $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $settings]);
            $after = EmptySourceReview::baseline($before, $engine->plan($candidate), $retainEmptySource, $settings['operator_user_id']);
            $readStore = pluginApp(ReviewStore::class, [$this->store]);
            $readStore->put($key, $after);
        }
        $review = RecoveryReview::inspect($readStore, $this->gateway, $settings, $candidate, $corrections);
        $review['review_token'] = TranslationEngine::hash([$review['review_token'], $retainEmptySource, $before]);
        $review['retain_empty_source'] = $retainEmptySource;
        return ['review'=>$review, 'baseline'=>$after, 'baseline_changed'=>$after !== $before];
    }
    public function recoveryPreview(int $itemId, array $corrections = [], array $retainEmptySource = []): array
    {
        return $this->reviewedRecovery($this->settings(), $this->recoveryCandidate($itemId), $corrections, $retainEmptySource)['review'];
    }
    public function recoveryResume(int $itemId, string $token, array $corrections = [], array $retainEmptySource = []): array
    {
        if ($this->http->option('writesAllowed', '0') !== '1') { throw new TranslationIssue('writes_disabled', 'Speichern ist ausgeschaltet.'); }
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new TranslationIssue('review_required', 'Aktuelle Rücklese-Freigabe erforderlich.'); }
        $this->store->acquire();
        $this->http->setDeadline($this->now() + NightlyBatch::MAX_SECONDS);
        try {
            $settings = $this->settings(); $candidate = $this->recoveryCandidate($itemId);
            $prepared = $this->reviewedRecovery($settings, $candidate, $corrections, $retainEmptySource);
            $review = $prepared['review'];
            if (!$review['can_resume'] || $review['review_token'] !== $token) {
                throw new TranslationIssue('review_stale', 'Auftrag oder Texte geändert oder weiterhin prüfpflichtig. Erneut prüfen.');
            }
            if ($prepared['baseline_changed']) { $this->store->put('baseline:' . $itemId, $prepared['baseline']); }
            $pending = $this->store->get('job:' . $itemId);
            if ($corrections || $retainEmptySource || !empty($pending['conflicts'])) {
                $corrected = RecoveryReview::correctedJob($pending, $settings, $corrections);
                if (!empty($corrected['conflicts'])) { $corrected['resolved_conflicts'] = $corrected['conflicts']; $corrected['conflicts'] = []; }
                $corrected['reviewed_by'] = (int)$this->http->option('operatorUserId', '0');
                $this->store->put('job:' . $itemId, $corrected);
            }
            $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $settings, function () { $this->http->checkDeadline(); }]);
            $result = $engine->run($candidate, true);
            $this->store->put('last-result', $result + ['at'=>gmdate('c'), 'kind'=>'reviewed_recovery']);
            return $result;
        } finally { $this->http->setDeadline(0); $this->store->release(); }
    }
    public function providerUsage(): array
    {
        $usage = $this->http->request('deepl', 'GET', '/v2/usage');
        return ['character_count'=>$usage['character_count'] ?? null, 'character_limit'=>$usage['character_limit'] ?? null,
            'scope'=>'DeepL-Abrechnungszeitraum, gesamtes API-Konto', 'plugin_limit'=>(int)$this->http->option('monthlyCharacters', '250000'),
            'local_reserved'=>$this->store->get('budget:' . gmdate('Y-m')), 'local_scope'=>'Kalendermonat, konservative Reservierung inklusive HTML'];
    }
    public function configurationCheck(): array
    {
        $settings = $this->settings();
        return ['valid'=>true,'targets'=>$settings['targets'],'ready_tag_id'=>$settings['ready_tag_id'],'review_tag_id'=>$settings['review_tag_id'],'glossary_enabled'=>$settings['policy']['glossary_id'] !== '', 'monthly_items'=>$settings['monthly_items'],'monthly_characters'=>$settings['monthly_characters']];
    }
    public function status(): array
    {
        return ['automatic' => $this->http->option('automaticEnabled', '0') === '1', 'writes' => $this->http->option('writesAllowed', '0') === '1', 'pilot_item_id' => (int)$this->http->option('pilotItemId', '0'), 'last_result' => $this->store->get('last-result'), 'pilot_job' => $this->store->get('job:' . (int)$this->http->option('pilotItemId', '0')), 'lock' => $this->store->get('global-lock'), 'budget' => $this->store->get('budget:' . gmdate('Y-m')), 'nightly_run'=>$this->store->get('nightly-run'), 'nightly_issue'=>$this->store->get('nightly-last-issue')];
    }
}
