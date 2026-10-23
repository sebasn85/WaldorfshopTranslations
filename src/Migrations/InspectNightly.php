<?php
namespace WaldorfshopTranslations\Migrations;

use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Repositories\StateRepository;

/** Test-branch-only probe. Reads existing state; never translates or writes state. */
class InspectNightly
{
    use Loggable;
    private function fields($value, array $keys): array
    {
        $result = [];
        if (!is_array($value)) { return ['found'=>false]; }
        foreach ($keys as $key) {
            if (array_key_exists($key, $value)) { $result[$key] = $value[$key]; }
        }
        return $result + ['found'=>true];
    }
    public function run(StateRepository $store)
    {
        $run = $store->get('nightly-run');
        $result = [
            'probe'=>'nightly-read-only-2026-10-23',
            'nightly_run'=>$this->fields($run, ['day','status','total','cursor','complete','failed','review_required','skipped','cancelled','in_flight','started_at','updated_at','finished_at','budget_month']),
            'nightly_issue'=>$this->fields($store->get('nightly-last-issue'), ['day','status','message']),
            'last_issue'=>$this->fields(is_array($run) ? ($run['last_issue'] ?? null) : null, ['item_id','reason','message']),
            'lock'=>$this->fields($store->get('global-lock'), ['started']),
            'budget'=>$this->fields($store->get('budget:' . gmdate('Y-m')), ['items','characters']),
            'last_result'=>$this->fields($store->get('last-result'), ['item_id','status','at','kind'])
        ];
        $this->getLogger('NightlyReadOnly20261023')->error('Read-only nightly translation state', $result);
    }
}
