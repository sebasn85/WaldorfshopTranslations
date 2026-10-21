<?php
namespace WaldorfshopTranslations\Migrations;

use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Repositories\StateRepository;

/** Read-only probe on an isolated diagnostic branch; no translations or writes. */
class InspectVariant38811
{
    use Loggable;
    public function run(StateRepository $store)
    {
        $job = $store->get('job:42989736');
        $last = $store->get('last-result');
        $counts = [];
        foreach (($job['targets'] ?? []) as $language => $target) {
            $counts[$language] = ['needed_fields'=>array_keys($target['needed'] ?? []),'translated_fields'=>array_keys($target['changes'] ?? [])];
        }
        $result = [
            'item_id'=>42989736,
            'variation_id'=>38811,
            'job_found'=>is_array($job),
            'job_status'=>$job['status'] ?? 'missing',
            'write_started'=>$job['write_started'] ?? false,
            'validation_error'=>$job['validation_error'] ?? null,
            'field_progress'=>$counts,
            'last_result'=>$last
        ];
        $this->getLogger('Variant38811Audit')->error('Read-only translation state diagnosis', $result);
    }
}
