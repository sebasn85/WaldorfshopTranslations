<?php
namespace WaldorfshopTranslations\Core;

/** Stateless review. The caller authorizes the operator and holds the global lock. */
class RecoveryReview
{
    public static function correctedJob(array $job, array $settings, array $corrections): array
    {
        foreach ($corrections as $lang => $fields) {
            if (!isset($settings['targets'][$lang]) || !is_array($fields)) { throw new TranslationIssue('invalid_correction', 'Nicht freigegebene Sprache oder Feldkorrektur.'); }
            foreach ($fields as $field => $text) {
                if (!in_array($field, TranslationEngine::FIELDS, true) || !isset($job['targets'][$lang]['needed'][$field]) || !is_string($text)) {
                    throw new TranslationIssue('invalid_correction', 'Nur vorhandene Übersetzungsfelder dieses Auftrags dürfen korrigiert werden.');
                }
                TranslationEngine::validate($job['targets'][$lang]['needed'][$field], $text, $field);
                $job['reviewed_corrections'][$lang][$field] = ['previous'=>$job['targets'][$lang]['changes'][$field] ?? null, 'replacement'=>$text, 'at'=>gmdate('c')];
                $job['targets'][$lang]['changes'][$field] = $text;
            }
        }
        return $job;
    }
    public static function inspect($store, $gateway, array $settings, array $candidate, array $corrections = []): array
    {
        $job = $store->get('job:' . $candidate['item_id']);
        if (!is_array($job) || !in_array($job['status'] ?? '', ['blocked','paused','prepared','applying','tagging','review_required'], true)) {
            throw new TranslationIssue('not_recoverable', 'Kein offener Übersetzungsauftrag für diesen Artikel.');
        }
        $originalJob = $job;
        $job = self::correctedJob($job, $settings, $corrections);
        $engine = pluginApp(TranslationEngine::class, [$store, $gateway, $settings]);
        $fresh = $engine->plan($candidate);
        $issues = []; $reusable = 0; $missing = 0; $estimated = 0; $billed = 0; $billedRequests = 0;
        if ($job['source_hash'] !== $fresh['source_hash']) { $issues[] = ['code'=>'source_changed', 'message'=>'Deutsche Quelle seit Auftrag geändert.']; }
        if ($job['policy'] !== $fresh['policy']) { $issues[] = ['code'=>'policy_changed', 'message'=>'Sprachen, Tags oder Glossar seit Auftrag geändert.']; }
        foreach ($fresh['conflicts'] as $conflict) { $issues[] = ['code'=>'manual_conflict', 'message'=>$conflict]; }
        foreach ($settings['targets'] as $lang => $code) {
            $target = $job['targets'][$lang] ?? null;
            if (!is_array($target)) { $issues[] = ['code'=>'missing_language', 'language'=>$lang]; continue; }
            foreach ($target['needed'] as $field => $source) {
                $requestKey = $job['requests'][$lang][$field] ?? null;
                $request = is_string($requestKey) ? $store->get($requestKey) : null;
                if (is_array($request) && is_int($request['billed_characters'] ?? null)) {
                    $billed += $request['billed_characters']; $billedRequests++;
                }
                $translated = $target['changes'][$field] ?? null;
                if ($translated === null) {
                    $options = ['source_lang'=>'DE','target_lang'=>$code,'preserve_formatting'=>true,'context'=>$settings['context'] . "\n" . $job['source']['name']];
                    if (!empty($job['policy']['glossary_id'])) { $options['glossary_id'] = $job['policy']['glossary_id']; }
                    if (!empty($settings['formality'][$lang])) { $options['formality'] = 'prefer_less'; }
                    if (in_array($field, TranslationEngine::HTML_FIELDS, true)) { $options['tag_handling']='html'; $options['tag_handling_version']='v2'; }
                    $cached = $store->get('cache:' . TranslationEngine::hash([$source,$options,$field,$job['policy']]));
                    $translated = $cached['text'] ?? null;
                }
                if ($translated === null) {
                    $missing++; $estimated += TranslationEngine::characters($source);
                    if (is_array($request) && in_array($request['state'] ?? '', ['reserved','uncertain','responded'], true)) {
                        $issues[] = ['code'=>'request_uncertain','language'=>$lang,'field'=>$field,'message'=>'Frühere Anfrage ohne sicher gespeicherte Antwort.'];
                    }
                }
                else {
                    try { TranslationEngine::validate($source, $translated, $field); $reusable++; }
                    catch (\Throwable $error) { $issues[] = TranslationIssue::details($error) + ['language'=>$lang,'field'=>$field]; }
                }
                $current = $fresh['targets'][$lang]['before'][$field];
                $before = $target['before'][$field];
                if ($current !== $before && !TranslationEngine::sameStoredText($field, $current, (string)$translated)) {
                    $issues[] = ['code'=>'target_changed','language'=>$lang,'field'=>$field,'message'=>'Zieltext außerhalb dieses Auftrags geändert.'];
                }
            }
        }
        return ['item_id'=>$candidate['item_id'], 'status'=>$job['status'], 'can_resume'=>!$issues,
            'reusable_fields'=>$reusable, 'missing_fields'=>$missing, 'maximum_new_source_characters'=>$estimated,
            'recorded_billed_characters'=>$billed, 'requests_with_billing_record'=>$billedRequests,
            'corrections'=>$corrections,
            'issues'=>$issues, 'review_token'=>TranslationEngine::hash([$originalJob,$fresh,$candidate,$settings,$corrections])];
    }
}
