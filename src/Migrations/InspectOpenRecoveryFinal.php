<?php
namespace WaldorfshopTranslations\Migrations;
use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Core\TranslationEngine;
use WaldorfshopTranslations\Core\TranslationIssue;
use WaldorfshopTranslations\Core\RecoveryReview;
use WaldorfshopTranslations\Core\EmptySourceReview;
use WaldorfshopTranslations\Core\ReviewStore;
use WaldorfshopTranslations\Diagnostics\SnapshotReviewStore;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use WaldorfshopTranslations\Diagnostics\ReplayGateway;
use WaldorfshopTranslations\Repositories\StateRepository;
use WaldorfshopTranslations\Services\Gateway;
use WaldorfshopTranslations\Services\HttpClient;

/** Test-only read-only review. All proposed state changes stay in memory. */
class InspectOpenRecoveryFinal
{
    use Loggable;
    public function run(DataBase $database, Gateway $gateway, HttpClient $http)
    {
        $this->getLogger('RecoveryFinalStart')->error('READ ONLY: open-job recovery review started');
        $report=['read_only'=>true,'article_writes'=>0,'state_writes'=>0,'paid_requests'=>0,'items'=>[]];
        try {
            $http->setDeadline(time()+240);
            $settings=$gateway->preflight($http->settings());
            $this->getLogger('RecoveryFinalPreflight')->error('READ ONLY: settings verified');
            $candidates=$gateway->candidates();
            $keys=['budget:'.gmdate('Y-m')];
            foreach ($candidates as $candidate) { $keys[]='job:'.$candidate['item_id']; $keys[]='baseline:'.$candidate['item_id']; }
            $store=pluginApp(SnapshotReviewStore::class,[$database]); $store->load($keys); $keys=[];
            foreach ($candidates as $candidate) {
                $job=$store->get('job:'.$candidate['item_id']);
                foreach (($job['targets'] ?? []) as $lang=>$target) {
                    foreach ($target['needed'] as $field=>$source) {
                        $options=['source_lang'=>'DE','target_lang'=>$settings['targets'][$lang],'preserve_formatting'=>true,'context'=>$settings['context']."\n".$job['source']['name']];
                        if (!empty($job['policy']['glossary_id'])) { $options['glossary_id']=$job['policy']['glossary_id']; }
                        if (!empty($settings['formality'][$lang])) { $options['formality']='prefer_less'; }
                        if (in_array($field,TranslationEngine::HTML_FIELDS,true)) { $options['tag_handling']='html'; $options['tag_handling_version']='v2'; }
                        $keys[]='cache:'.TranslationEngine::hash([$source,$options,$field,$job['policy']]);
                        if (is_string($job['requests'][$lang][$field] ?? null)) { $keys[]=$job['requests'][$lang][$field]; }
                    }
                }
            }
            $store->load($keys);
            $this->getLogger('RecoveryFinalSnapshot')->error('READ ONLY: cache snapshot ready',['candidates'=>count($candidates),'queries'=>$store->queries]);
            $report['candidate_count']=count($candidates);
            $report['budget']=$store->get('budget:'.gmdate('Y-m'));
            $report['usage']=$http->request('deepl','GET','/v2/usage');
            foreach ($candidates as $candidate) {
                $http->checkDeadline();
                $job=$store->get('job:'.$candidate['item_id']);
                $entry=['item_id'=>$candidate['item_id'],'variation_id'=>$candidate['variation_id'],'status'=>$job['status'] ?? 'no_job'];
                try {
                    $rows=$gateway->texts($candidate); $tags=[];
                    foreach ($candidate['tagged_variation_ids'] as $id) { $tags[$id]=true; }
                    $simulation=pluginApp(ReplayGateway::class,[$rows,$tags]);
                    $memory=pluginApp(ReviewStore::class,[$store]);
                    $engine=pluginApp(TranslationEngine::class,[$memory,$simulation,$settings]);
                    foreach ($rows as $lang=>$row) {
                        foreach (TranslationEngine::FIELDS as $field) {
                            try { if (isset($row[$field]) && is_string($row[$field])) { TranslationEngine::checkMarkup($row[$field]); } }
                            catch (\Throwable $error) { $entry['markup_issues'][]=['language'=>$lang,'field'=>$field,'issue'=>TranslationIssue::details($error)]; }
                        }
                    }
                    $plan=$engine->plan($candidate);
                    $entry['source_hash']=$plan['source_hash'];
                    $entry['fresh_conflicts']=$plan['conflicts'];
                    if (!$job) { $entry['plan_status']=$plan['status']; $report['items'][]=$entry; $this->getLogger('RecoveryFinalItem')->error('READ ONLY: reviewed item',['item_json'=>json_encode($entry,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]); continue; }
                    $retain=[];
                    foreach ($plan['conflicts'] as $conflict) {
                        $parts=explode(':',$conflict);
                        if (count($parts)===3 && $parts[1]==='technicalData' && $parts[2]==='source_removed') { $retain[$parts[0]]=['technicalData']; }
                    }
                    if ($retain) {
                        $baseline=EmptySourceReview::baseline($store->get('baseline:'.$candidate['item_id']) ?? [],$plan,$retain,$settings['operator_user_id']);
                        $memory->put('baseline:'.$candidate['item_id'],$baseline);
                        $entry['retain_proposal']=$retain;
                    }
                    $review=RecoveryReview::inspect($memory,$simulation,$settings,$candidate);
                    unset($review['review_token']); $entry['review']=$review;
                    if ($review['can_resume'] && $review['missing_fields']===0) {
                        $job['resolved_conflicts']=$job['conflicts']; $job['conflicts']=[];
                        $memory->put('job:'.$candidate['item_id'],$job);
                        $entry['in_memory_result']=$engine->run($candidate,true);
                    }
                } catch (\Throwable $error) { $entry['issue']=TranslationIssue::details($error); }
                $report['items'][]=$entry;
                $this->getLogger('RecoveryFinalItem')->error('READ ONLY: reviewed item',['item_json'=>json_encode($entry,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        } catch (\Throwable $error) { $report['issue']=TranslationIssue::details($error); }
        $report['inspected_count']=count($report['items']);
        $http->setDeadline(0);
        $this->getLogger('RecoveryFinalFinished')->error('READ ONLY DIAGNOSTIC: open-job recovery review',['report_json'=>json_encode($report,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
