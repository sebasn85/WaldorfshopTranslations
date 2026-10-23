<?php
namespace WaldorfshopTranslations\Migrations;

use Plenty\Plugin\Log\Loggable;
use Plenty\Modules\Item\VariationDescription\Contracts\VariationDescriptionRepositoryContract;
use WaldorfshopTranslations\Repositories\StateRepository;
use WaldorfshopTranslations\Services\HttpClient;
use WaldorfshopTranslations\Services\Gateway;
use WaldorfshopTranslations\Core\TranslationEngine;

/** Isolated test-build audit. Only reads state, descriptions and API usage. */
class InspectConsumption
{
    use Loggable;
    private function same(string $field, $a, $b): bool
    {
        if (!is_string($a) || !is_string($b)) { return false; }
        if (!in_array($field, TranslationEngine::HTML_FIELDS, true)) {
            $a = trim($a, " \t\n\r\v"); $b = trim($b, " \t\n\r\v");
        }
        return $a === $b;
    }
    public function run(StateRepository $store, VariationDescriptionRepositoryContract $texts, HttpClient $http, Gateway $gateway)
    {
        $budget = $store->get('budget:' . gmdate('Y-m')) ?? [];
        $run = $store->get('nightly-run') ?? [];
        $report = ['probe'=>'consumption-read-only-2026-10-23','at'=>gmdate('c'),
            'internal_reserved_characters'=>$budget['characters'] ?? 0,
            'budget_article_count'=>count($budget['items'] ?? []), 'articles'=>[], 'queue_states'=>[], 'queue_issues'=>[], 'languages'=>[]];
        try {
            $usage = $http->request('deepl', 'GET', '/v2/usage');
            $report['deepl_usage'] = ['character_count'=>$usage['character_count'] ?? null, 'character_limit'=>$usage['character_limit'] ?? null];
        } catch (\Throwable $e) { $report['deepl_usage_error'] = $e->getMessage(); }
        try {
            $candidates = $gateway->candidates(); $variants = 0; $candidateIds = [];
            foreach ($candidates as $c) { $variants += count($c['tagged_variation_ids']); $candidateIds[] = $c['item_id']; }
            $report['current_trigger_articles'] = count($candidates); $report['current_trigger_variants'] = $variants;
            $report['current_trigger_article_ids'] = $candidateIds;
        } catch (\Throwable $e) { $report['candidate_read_error'] = $e->getMessage(); }
        for ($page = 0; $page < 20; $page++) {
            $snapshot = $store->get('nightly-queue:' . $page);
            if (!is_array($snapshot) || ($snapshot['day'] ?? '') !== ($run['day'] ?? '')) { break; }
            foreach (($snapshot['entries'] ?? []) as $candidate) {
                $job = $store->get('job:' . $candidate['item_id']); $status = $job['status'] ?? 'no_job';
                $report['queue_states'][$status] = ($report['queue_states'][$status] ?? 0) + 1;
                if (!empty($job['validation_error']['message'])) { $issue = $job['validation_error']['message']; }
                elseif (!empty($job['conflicts'])) { $issue = 'conflicts:' . implode(',', $job['conflicts']); }
                else { $issue = $job['error'] ?? $status; }
                $report['queue_issues'][$issue] = ($report['queue_issues'][$issue] ?? 0) + 1;
            }
        }
        foreach (($budget['items'] ?? []) as $id) {
            $job = $store->get('job:' . $id);
            if (!is_array($job)) { $report['articles'][] = ['item_id'=>$id,'status'=>'missing_job']; continue; }
            $candidate = $job['candidate']; $rows = []; $readError = null;
            try {
                foreach ($texts->findByVariationId((int)$candidate['variation_id']) as $row) {
                    $row = is_array($row) ? $row : $row->toArray();
                    if ((int)($row['itemId'] ?? 0) !== (int)$id) { throw new \RuntimeException('Artikelzuordnung unklar.'); }
                    $rows[strtolower($row['lang'] ?? '')] = $row;
                }
            } catch (\Throwable $e) { $readError = get_class($e); }
            $entry = ['item_id'=>$id,'variation_id'=>$candidate['variation_id'],'name'=>$job['source']['name'] ?? '',
                'status'=>$job['status'],'write_started'=>!empty($job['write_started']), 'read_error'=>$readError,
                'validation'=>isset($job['validation_error']) ? ['language'=>$job['validation_error']['language'],'field'=>$job['validation_error']['field'],'message'=>$job['validation_error']['message']] : null,
                'conflicts'=>$job['conflicts'] ?? [], 'languages'=>[]];
            foreach (($job['targets'] ?? []) as $lang => $target) {
                $detail = ['prepared_fields'=>count($target['changes'] ?? []),'prepared_source_characters'=>0,'matching_current'=>[],'different_current'=>[],'changed_from_before'=>[]];
                foreach (($target['changes'] ?? []) as $field => $translated) {
                    $detail['prepared_source_characters'] += TranslationEngine::characters($target['needed'][$field] ?? ($job['source'][$field] ?? ''));
                    if ($readError === null) {
                        $current = $rows[$lang][$field] ?? '';
                        $key = $this->same($field, $current, $translated) ? 'matching_current' : 'different_current';
                        $detail[$key][] = $field;
                        if (!$this->same($field, $current, $target['before'][$field] ?? '')) { $detail['changed_from_before'][] = $field; }
                    }
                }
                $entry['languages'][$lang] = $detail;
                if (!isset($report['languages'][$lang])) { $report['languages'][$lang] = ['prepared_fields'=>0,'prepared_source_characters'=>0,'matching_current'=>0,'different_current'=>0,'changed_from_before'=>0]; }
                foreach (['prepared_fields','prepared_source_characters'] as $k) { $report['languages'][$lang][$k] += $detail[$k]; }
                foreach (['matching_current','different_current','changed_from_before'] as $k) { $report['languages'][$lang][$k] += count($detail[$k]); }
            }
            $report['articles'][] = $entry;
        }
        $this->getLogger('ConsumptionReadOnly20261023')->error('Read-only translation consumption audit', ['report_json'=>json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
