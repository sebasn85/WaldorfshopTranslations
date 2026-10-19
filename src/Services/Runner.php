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
        return ['policy' => ['version' => 1, 'glossary_id' => Glossary::ID, 'glossary_hash' => Glossary::HASH, 'unknown' => 'refresh'], 'context' => 'Waldorfshop: an online shop for Waldorf education, craft materials, books and wooden toys. The text is a factual product description. Brand names, product identifiers, dimensions, quantities and material specifications refer to the product being sold.', 'monthly_items' => 100, 'monthly_characters' => 250000, 'seed_month' => InitialBudget::MONTH, 'seed_characters' => InitialBudget::CHARACTERS, 'seed_items' => InitialBudget::items()];
    }
    public function pilot(): array
    {
        $this->store->acquire();
        try {
            $id = (int)$this->http->option('pilotItemId', '10009957');
            if ($id <= 0) { throw new \RuntimeException('Pilotartikel fehlt.'); }
            foreach ($this->gateway->candidates() as $candidate) {
                if ($candidate['item_id'] === $id) {
                    $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $this->settings()]);
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
            foreach ($this->gateway->candidates() as $candidate) {
                $job = $this->store->get('job:' . $candidate['item_id']);
                if ($job && in_array($job['status'], ['blocked', 'review_required'], true)) { continue; }
                $engine = pluginApp(TranslationEngine::class, [$this->store, $this->gateway, $this->settings()]);
                $result = $engine->run($candidate, true);
                $this->store->put('last-result', $result + ['at' => gmdate('c'), 'kind' => 'automatic']);
                return $result; // One article per cron: bounded runtime, at most 100/month.
            }
            return ['status' => 'idle'];
        } finally { $this->store->release(); }
    }
    public function status(): array
    {
        return ['automatic' => $this->http->option('automaticEnabled', '0') === '1', 'writes' => $this->http->option('writesAllowed', '0') === '1', 'pilot_item_id' => (int)$this->http->option('pilotItemId', '10009957'), 'last_result' => $this->store->get('last-result'), 'lock' => $this->store->get('global-lock'), 'budget' => $this->store->get('budget:' . gmdate('Y-m'))];
    }
}
