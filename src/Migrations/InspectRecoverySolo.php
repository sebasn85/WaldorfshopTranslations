<?php
namespace WaldorfshopTranslations\Migrations;
use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Repositories\StateRepository;
use WaldorfshopTranslations\Services\HttpClient;
use WaldorfshopTranslations\Services\Gateway;
use WaldorfshopTranslations\Core\TranslationIssue;

/** Test-set only: read stored jobs, current trigger IDs and provider usage. */
class InspectRecoverySolo
{
    use Loggable;
    public function run(StateRepository $store, HttpClient $http, Gateway $gateway)
    {
        $report=['at'=>gmdate('c'),'read_only'=>true,'article_writes'=>0,'translation_requests'=>0,'states'=>[],'items'=>[]];
        try {
            $settings=$gateway->preflight($http->settings());
            $report['nightly']=$store->get('nightly-run');
            $report['nightly_issue']=$store->get('nightly-last-issue');
            $report['last_result']=$store->get('last-result');
            $budget=$store->get('budget:' . gmdate('Y-m')) ?? [];
            $report['budget']=['reserved_characters'=>$budget['characters'] ?? 0,'articles'=>count($budget['items'] ?? []),'limit'=>$settings['monthly_characters']];
            $usage=$http->request('deepl','GET','/v2/usage');
            $report['provider_usage']=['character_count'=>$usage['character_count'] ?? null,'character_limit'=>$usage['character_limit'] ?? null];
            foreach ($gateway->candidates() as $candidate) {
                $job=$store->get('job:' . $candidate['item_id']);
                $status=$job['status'] ?? 'no_job';
                $report['states'][$status]=($report['states'][$status] ?? 0)+1;
                $entry=['item_id'=>$candidate['item_id'],'variation_id'=>$candidate['variation_id'],'name'=>$job['source']['name'] ?? '', 'status'=>$status,'write_started'=>$job['write_started'] ?? false,'error'=>$job['error'] ?? null,'failure'=>$job['failure'] ?? null,'conflicts'=>$job['conflicts'] ?? [],'prepared'=>[],'policy_changes'=>[]];
                foreach (($job['targets'] ?? []) as $lang=>$target) {
                    $entry['prepared'][$lang]=['saved_answers'=>count($target['changes'] ?? []),'needed_fields'=>count($target['needed'] ?? [])];
                }
                foreach (array_keys(($job['policy'] ?? []) + $settings['policy']) as $key) {
                    $before=$job['policy'][$key] ?? null; $now=$settings['policy'][$key] ?? null;
                    if ($before !== $now) { $entry['policy_changes'][$key]=['before'=>$before,'now'=>$now]; }
                }
                if (is_array($job['validation_error'] ?? null)) {
                    $validation=$job['validation_error'];
                    $entry['validation']=['language'=>$validation['language'] ?? null,'field'=>$validation['field'] ?? null,'message'=>$validation['message'] ?? null];
                    $this->getLogger('OpenIssueValidationReadOnly20261024')->error('READ ONLY: retained rejected answer', ['item_id'=>$candidate['item_id'],'validation_json'=>json_encode($validation,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
                $report['items'][]=$entry;
            }
        } catch (\Throwable $error) { $report['issue']=TranslationIssue::details($error); }
        $this->getLogger('OpenIssuesReadOnly20261024')->error('READ ONLY: current translation jobs and nightly outcome', ['report_json'=>json_encode($report,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
