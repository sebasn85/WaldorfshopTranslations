<?php
namespace WaldorfshopTranslations\Services;
use WaldorfshopTranslations\Core\TranslationEngine;
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
        $this->store->acquire();
        try {
            $settings = $this->settings();
            foreach ($this->gateway->candidates() as $candidate) {
                $job = $this->store->get('job:' . $candidate['item_id']);
                if ($job && in_array($job['status'], ['blocked', 'review_required'], true)) { continue; }
                $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $settings]);
                $result = $engine->run($candidate, true);
                $this->store->put('last-result', $result + ['at' => gmdate('c'), 'kind' => 'automatic']);
                return $result; // One article per cron: bounded runtime, at most 100/month.
            }
            return ['status' => 'idle'];
        } finally { $this->store->release(); }
    }
    public function configurationCheck(): array
    {
        $settings = $this->settings();
        return ['valid'=>true,'targets'=>$settings['targets'],'ready_tag_id'=>$settings['ready_tag_id'],'review_tag_id'=>$settings['review_tag_id'],'glossary_enabled'=>$settings['policy']['glossary_id'] !== '', 'monthly_items'=>$settings['monthly_items'],'monthly_characters'=>$settings['monthly_characters']];
    }
    public function status(): array
    {
        return ['automatic' => $this->http->option('automaticEnabled', '0') === '1', 'writes' => $this->http->option('writesAllowed', '0') === '1', 'pilot_item_id' => (int)$this->http->option('pilotItemId', '0'), 'last_result' => $this->store->get('last-result'), 'pilot_job' => $this->store->get('job:' . (int)$this->http->option('pilotItemId', '0')), 'lock' => $this->store->get('global-lock'), 'budget' => $this->store->get('budget:' . gmdate('Y-m'))];
    }
}
