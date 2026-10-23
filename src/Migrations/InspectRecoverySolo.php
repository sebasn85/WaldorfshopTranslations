<?php
namespace WaldorfshopTranslations\Migrations;

use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Core\RecoveryReview;
use WaldorfshopTranslations\Core\TranslationEngine;
use WaldorfshopTranslations\Core\TranslationIssue;
use WaldorfshopTranslations\Diagnostics\ReplayStore;
use WaldorfshopTranslations\Diagnostics\ReplayGateway;
use WaldorfshopTranslations\Repositories\StateRepository;
use WaldorfshopTranslations\Services\Gateway;
use WaldorfshopTranslations\Services\HttpClient;

/** Included in runOnBuild only on the isolated test branch. */
class InspectRecoverySolo
{
    use Loggable;
    public function run(StateRepository $store, Gateway $gateway, HttpClient $http)
    {
        $this->getLogger('RecoveryCheckpointStart')->error('READ ONLY TEST: recovery inspection started', ['real_article_writes'=>0,'paid_requests'=>0]);
        $report=['read_only'=>true,'real_article_writes'=>0,'paid_requests'=>0,'items'=>[]];
        try {
            pluginApp(CheckRecoveryRuntime::class)->run();
            $this->getLogger('RecoveryRuntimeCheckPassed')->error('READ ONLY TEST: runtime checks passed', ['real_article_writes'=>0]);
            $settings=$gateway->preflight($http->settings());
            foreach ($gateway->candidates() as $candidate) {
                if (!in_array($candidate['item_id'], [10020234,42989386,42989716,42989427], true)) { continue; }
                $entry=['item_id'=>$candidate['item_id']];
                $proposals=json_decode("{\"10020234\": {\"fr\": {\"name3\": \"Grapat | Lucky Lucky cinquième édition | Boîte surprise | Figurines à collectionner | à partir de 3 ans\"}}, \"42989386\": {\"fr\": {\"description\": \"<h3>Kit d&#x27;artiste Toverlux - Eentje van Margo </h3>\\n<p>Commence ton voyage dans un monde plein de lumière et d&#x27;imagination avec ce coffret spécial, conçu spécialement pour toi.</p>\\n<p>Ce kit d&#x27;artiste te permet de te lancer facilement dans l&#x27;univers des images lumineuses Toverlux. Il contient quatre silhouettes soigneusement sélectionnées par l&#x27;artiste Eentje van Margo, qui t&#x27;accompagneront tout au long des quatre saisons. Ces motifs riches en détails s’insèrent sans effort dans ta lampe Toverlux et transforment la lumière en une douce histoire. Chaque illustration capture l’ambiance particulière du printemps, de l’été, de l’automne et de l’hiver, pour que tu puisses toujours décorer ta table des saisons ou ta maison en fonction de la saison.</p>\\n<h4>Informations sur le produit</h4>\\n<ul>\\t<li>Contenu : 4 silhouettes Toverlux.</li>\\t<li>Artiste : Eentje van Margo.</li>\\t<li>Matériau : matériau translucide pour images lumineuses.</li>\\t<li>Utilisation : compatible avec <a href=\\\"https://www.waldorfshop.eu/p/toverlux-lamp_42985845\\\" target=\\\"_blank\\\">la lampe Toverlux</a> et <a href=\\\"https://www.waldorfshop.eu/p/toverlux-rahmen_42988737\\\" target=\\\"_blank\\\">le cadre</a>.</li></ul>\\n<h5> </h5>\"}}}",true);
                $corrections=$proposals[$candidate['item_id']] ?? [];
                try {
                    $review=RecoveryReview::inspect($store,$gateway,$settings,$candidate,$corrections);
                    unset($review['review_token']); $entry['review']=$review;
                    if ($review['can_resume'] && $review['missing_fields'] === 0) {
                        $memory=pluginApp(ReplayStore::class,[$store]);
                        $job=RecoveryReview::correctedJob($store->get('job:' . $candidate['item_id']),$settings,$corrections);
                        $memory->put('job:' . $candidate['item_id'],$job); $tags=[];
                        foreach ($job['candidate']['tagged_variation_ids'] as $id) { $tags[$id]=true; }
                        $simulation=pluginApp(ReplayGateway::class,[$gateway->texts($candidate),$tags]);
                        $engine=pluginApp(TranslationEngine::class,[$memory,$simulation,$settings]);
                        $entry['simulation']=$engine->run($candidate,true);
                        $entry['simulated_language_writes']=$simulation->writes;
                    }
                } catch (\Throwable $error) { $entry['issue']=TranslationIssue::details($error); }
                $report['items'][]=$entry;
                $this->getLogger('RecoveryCheckpointItem')->error('READ ONLY TEST: inspected item', ['report_json'=>json_encode($entry,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        } catch (\Throwable $error) { $report['issue']=TranslationIssue::details($error); }
        $this->getLogger('RecoveryCheckpointFinished')->error('Read-only recovery review and in-memory replay', ['report_json'=>json_encode($report,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}

