<?php
use WaldorfshopTranslations\Core\EmptySourceReview;

function retainedSourceTests(): array
{
    return [
        'retained facts require fresh review when translation policy changes'=>function(){
            [$s,$g,$e,$c,$settings]=fixture(); $g->rows['en']['technicalData']='<p>Retained facts</p>';
            $s->put('baseline:42',EmptySourceReview::baseline([],$e->plan($c),['en'=>['technicalData']],197));
            $settings['policy']['glossary_id']='changed'; $changed=new \WaldorfshopTranslations\Core\TranslationEngine($s,$g,$settings);
            expect(in_array('en:technicalData:source_removed',$changed->plan($c)['conflicts'],true));
        },
        'reviewed spelling repairs preserve all factual digits without relaxing validation'=>function(){
            $cases=json_decode(file_get_contents(__DIR__ . '/reviewed-number-cases.json'),true);
            foreach ($cases as $case) {
                raises(function()use($case){\WaldorfshopTranslations\Core\TranslationEngine::validate($case['source'],$case['rejected'],$case['field']);});
                \WaldorfshopTranslations\Core\TranslationEngine::validate($case['source_replacement'] ?? $case['source'],$case['replacement'],$case['field']);
            }
        },
        'reviewed source punctuation does not authorize changed supplier IDs or quantities'=>function(){
            $cases=json_decode(file_get_contents(__DIR__ . '/reviewed-number-cases.json'),true);
            foreach (array_slice($cases,3) as $case) {
                $wrong=str_replace($case['supplier_code'],'99.99.999',$case['replacement']);
                raises(function()use($case,$wrong){\WaldorfshopTranslations\Core\TranslationEngine::validate($case['source_replacement'],$wrong,$case['field']);});
            }
        },
        'empty-source approval preview is read-only and retains existing translated technical data'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0';
            $g->workers[42]->rows['en']['technicalData']='<p>Existing technical details 90 cm</p>';
            expect($r->pilot()['status']==='review_required'); $before=$s->data; $paid=$g->workers[42]->calls;
            $review=$r->recoveryPreview(42,[],['en'=>['technicalData']]);
            expect($review['can_resume'] && $s->data===$before && $g->workers[42]->calls===$paid && !$g->workers[42]->writes);
        },
        'explicitly approved empty German source never erases or translates existing target'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0';
            $text='<p>Existing technical details 90 cm</p>'; $g->workers[42]->rows['en']['technicalData']=$text; $r->pilot();
            expect(!$r->recoveryPreview(42)['can_resume']);
            $keep=['en'=>['technicalData']]; $v=$r->recoveryPreview(42,[],$keep); $h->values['writesAllowed']='1';
            expect($r->recoveryResume(42,$v['review_token'],[],$keep)['status']==='complete');
            expect($g->workers[42]->rows['en']['technicalData']===$text && $g->workers[42]->calls===4);
            expect(isset($s->get('baseline:42')['en']['technicalData']['empty_source_review']));
            expect($s->get('job:42')['conflicts']===[] && count($s->get('job:42')['resolved_conflicts'])===1);
        },
        'retained technical-data approval remains valid when the same article is tagged again'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0';
            $g->workers[42]->rows['en']['technicalData']='<p>Technical details</p>'; $r->pilot(); $keep=['en'=>['technicalData']];
            $v=$r->recoveryPreview(42,[],$keep); $h->values['writesAllowed']='1'; $r->recoveryResume(42,$v['review_token'],[],$keep);
            $paid=$g->workers[42]->calls; $g->workers[42]->ready=[420,421]; expect($r->pilot()['status']==='complete'); expect($g->workers[42]->calls===$paid);
        },
        'changed retained target invalidates the exact approval before any write'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0';
            $g->workers[42]->rows['en']['technicalData']='<p>Original</p>'; $r->pilot(); $keep=['en'=>['technicalData']]; $v=$r->recoveryPreview(42,[],$keep);
            $g->workers[42]->rows['en']['technicalData']='<p>Manually changed</p>'; $h->values['writesAllowed']='1'; $before=$s->data;
            raises(function()use($r,$v,$keep){$r->recoveryResume(42,$v['review_token'],[],$keep);});
            expect(!$g->workers[42]->writes && $g->workers[42]->calls===0 && $s->get('baseline:42')===null && $s->get('job:42')===$before['job:42']);
        },
        'retained-source approval is bound to the choices presented in the preview'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0';
            $g->workers[42]->rows['en']['technicalData']='<p>Keep me</p>'; $r->pilot(); $v=$r->recoveryPreview(42,[],['en'=>['technicalData']]); $h->values['writesAllowed']='1';
            raises(function()use($r,$v){$r->recoveryResume(42,$v['review_token']);}); expect($s->get('baseline:42')===null && !$g->workers[42]->writes);
        },
        'changed German product text requires fresh review of retained target facts'=>function(){
            [$s,$g,$e,$c,$settings]=fixture(); $g->rows['en']['technicalData']='<p>Previous product facts</p>';
            $plan=$e->plan($c); $s->put('baseline:42',EmptySourceReview::baseline([],$plan,['en'=>['technicalData']],197));
            expect(!$e->plan($c)['conflicts']); $g->rows['de']['description']='<p>New product facts 90 x 90 cm</p>';
            expect(in_array('en:technicalData:source_removed',$e->plan($c)['conflicts'],true)); expect(!$g->writes && $g->calls===0);
        },
        'retained-source approval cannot select URLs nonempty source empty target or unselected languages'=>function(){
            [$s,$g,$e,$c]=fixture(); $g->rows['en']['technicalData']='<p>Keep</p>'; $plan=$e->plan($c);
            foreach ([['en'=>['urlPath']],['en'=>['description']],['en'=>['title']],['zz'=>['technicalData']]] as $keep) {
                raises(function()use($plan,$keep){EmptySourceReview::baseline([],$plan,$keep,197);});
            }
            raises(function()use($plan){EmptySourceReview::baseline([],$plan,['en'=>['technicalData']],0);}); expect(!$g->writes && $g->calls===0);
        },
        'new German technical data replaces approval through normal translation and readback'=>function(){
            [$s,$g,$e,$c,$settings]=fixture(); $g->rows['en']['technicalData']='<p>Old technical details</p>';
            $s->put('baseline:42',EmptySourceReview::baseline([],$e->plan($c),['en'=>['technicalData']],197));
            expect($e->run($c,true)['status']==='complete'); $paid=$g->calls; $g->ready=[420,421]; $g->rows['de']['technicalData']='<p>90 cm</p>';
            expect($e->run($c,true)['status']==='complete'); expect($g->calls===$paid+5);
            expect($g->rows['en']['technicalData']==='<p>90 cm</p>' && !isset($s->get('baseline:42')['en']['technicalData']['empty_source_review']));
        }
    ];
}
