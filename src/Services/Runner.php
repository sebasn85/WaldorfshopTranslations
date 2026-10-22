<?php
namespace WaldorfshopTranslations\Services;
use WaldorfshopTranslations\Core\TranslationEngine;
use WaldorfshopTranslations\Core\NightlyBatch;
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
                    $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $settings, [$this->http, 'checkDeadline']]);
                    return $engine->run($candidate, true);
                },
                function ($deadline) { $this->http->setDeadline($deadline); },
                function () { return $this->now(); }
            ]);
            return $batch->run();
        } finally { $this->http->setDeadline(0); $this->store->release(); }
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
