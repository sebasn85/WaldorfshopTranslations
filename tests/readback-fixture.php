<?php
use WaldorfshopTranslations\Core\TranslationEngine as Engine;
function readbackTests(): array
{
    return [
        'Plenty trimming plain text completes and records the actual stored baseline'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();
            $g->rows['de']['name2']='  Seide 90 cm ';
            $g->writeTransform=function($changes){foreach($changes as $field=>$value){if(!in_array($field,Engine::HTML_FIELDS,true)){$changes[$field]=trim($value," \t\n\r\v");}}return $changes;};
            expect($e->run($c,true)['status']==='complete');
            foreach(Engine::TARGETS as $lang=>$code){expect($s->get('baseline:42')[$lang]['name2']['target']===$g->rows[$lang]['name2']);}
            expect($g->rows['de']['name2']==='  Seide 90 cm ');
            $g->ready=[420,421];$g->rows['de']['name2']='  Neue Seide 90 cm ';
            expect($e->run($c,true)['status']==='complete');
        },
        'existing partially saved job resumes after trimming without new requests or text writes'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$e->run($c,false);$job=$s->get('job:42');
            foreach($job['targets'] as $lang=>&$target){$target['changes']['name2'].=' ';$g->rows[$lang]=array_merge($g->rows[$lang]??[],$target['changes']);$g->rows[$lang]['name2']=trim($target['changes']['name2']);}unset($target);
            $job['write_started']=true;$job['status']='blocked';$s->put('job:42',$job);$calls=$g->calls;
            expect($e->run($c,true)['status']==='complete');expect($g->calls===$calls);expect(!$g->writes);expect(!$g->ready);
            foreach(Engine::TARGETS as $lang=>$code){expect($s->get('baseline:42')[$lang]['name2']['target']===$g->rows[$lang]['name2']);}
        },
        'internal whitespace changes still fail verification and retain trigger'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$g->rows['de']['name2']='Seide  90 cm';
            $g->writeTransform=function($changes){if(isset($changes['name2'])){$changes['name2']=str_replace('  ',' ',$changes['name2']);}return $changes;};
            raises(function()use($e,$c){$e->run($c,true);});expect($g->ready===[420,421]);expect($s->get('baseline:42')===null);
        },
        'HTML whitespace remains exact during readback'=>function(){
            [$s,$g,$e,$c]=fixture();$g->rows['de']['description']=' <p>90 x 90 cm</p> ';
            $g->writeTransform=function($changes){if(isset($changes['description'])){$changes['description']=trim($changes['description']);}return $changes;};
            raises(function()use($e,$c){$e->run($c,true);});expect($g->ready===[420,421]);expect($s->get('baseline:42')===null);
        },
        'manual content edit after a partially saved job remains protected'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$g->failWrite=true;raises(function()use($e,$c){$e->run($c,true);});$g->rows['en']['name2']='Manually changed 90 cm';$writes=count($g->writes);
            raises(function()use($e,$c){$e->run($c,true);});expect(count($g->writes)===$writes);expect($g->rows['en']['name2']==='Manually changed 90 cm');expect($g->ready===[420,421]);
        }
    ];
}
