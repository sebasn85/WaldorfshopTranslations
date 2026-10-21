<?php
namespace WaldorfshopTranslations\Migrations;

use Plenty\Modules\Item\VariationDescription\Contracts\VariationDescriptionRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Repositories\StateRepository;

/** Temporary test-branch probe: read existing job/texts; never write or translate. */
class InspectReadback
{
    use Loggable;
    public function run(StateRepository $store, VariationDescriptionRepositoryContract $texts)
    {
        $job = $store->get('job:42989716');
        $result = ['item_id'=>42989716,'variation_id'=>38790,'job_found'=>is_array($job),'job_status'=>$job['status'] ?? 'missing','mismatches'=>[]];
        if (is_array($job)) {
            $rows = [];
            foreach ($texts->findByVariationId(38790) as $row) {
                $row = is_array($row) ? $row : $row->toArray();
                if ((int)($row['itemId'] ?? 0) !== 42989716) { throw new \RuntimeException('Read-only diagnosis: unexpected article.'); }
                $rows[strtolower($row['lang'] ?? '')] = $row;
            }
            foreach (($job['targets'] ?? []) as $lang => $target) {
                foreach (($target['changes'] ?? []) as $field => $expected) {
                    $actual = $rows[$lang][$field] ?? '';
                    if ($actual !== $expected) {
                        $result['mismatches'][] = ['language'=>$lang,'field'=>$field,'expected'=>$expected,'stored'=>$actual,'equal_after_entity_decode'=>is_string($actual) && html_entity_decode($expected, ENT_QUOTES | ENT_HTML5, 'UTF-8') === html_entity_decode($actual, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
                    }
                }
            }
        }
        $this->getLogger('ReadbackAudit42989716')->error('Read-only translation readback diagnosis', $result);
    }
}
