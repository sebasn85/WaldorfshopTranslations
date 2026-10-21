<?php
use WaldorfshopTranslations\Core\TranslationEngine as Engine;
use WaldorfshopTranslations\Services\Gateway;
function expandedFieldFixture(): array
{
    [$s,$g,$e,$c,$settings]=fixture();
    $g->rows['de'] += ['name2'=>'Seide 90 cm','name3'=>'Spieltuch 90 x 90 cm','title'=>'Spieltuch kaufen','technicalData'=>'<p class="spec">Seide: 90 x 90 cm</p>','metaKeywords'=>'Spieltuch, Seide'];
    $g->rows['de']['urlPath']='deutscher-pfad';
    $g->rows['en']['urlPath']='keep-me';
    return [$s,$g,$e,$c,$settings];
}
function fieldCoverageTests(): array
{
    return [
        'all nine populated fields translated for all five languages and URL excluded'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$source=$g->rows['de'];
            expect($e->run($c,true)['status']==='complete');expect($g->calls===45);expect($g->rows['de']===$source);
            foreach(Engine::TARGETS as $lang=>$code){foreach(Engine::FIELDS as $field){expect(!Engine::blank($g->rows[$lang][$field]));expect(isset($s->get('baseline:42')[$lang][$field]));}}
            foreach($g->writes as $write){expect(!array_key_exists('urlPath',$write[1]));expect(count($write[1])===9);}
            expect($g->rows['en']['urlPath']==='keep-me');expect(!in_array('urlPath',Engine::FIELDS,true));expect(!$g->ready && count($g->review)===2);
        },
        'new fields join existing four field baseline without retranslating old fields'=>function(){
            [$s,$g,$e,$c]=fixture();$e->run($c,true);$old=$g->rows['en'];$calls=$g->calls;$g->ready=[420,421];
            $g->rows['de'] += ['name2'=>'Seide 90 cm','name3'=>'Spieltuch 90 x 90 cm','title'=>'Spieltuch kaufen','technicalData'=>'<p>90 cm</p>','metaKeywords'=>'Spieltuch, Seide'];
            $g->writes=[];expect($e->run($c,true)['status']==='complete');expect($g->calls-$calls===25);
            foreach($g->writes as $write){expect(array_keys($write[1])===['name2','name3','title','technicalData','metaKeywords']);}
            foreach(['name','previewDescription','description','metaDescription'] as $field){expect($g->rows['en'][$field]===$old[$field]);}
            $g->ready=[420,421];$calls=$g->calls;$e->run($c,true);expect($g->calls===$calls);
        },
        'technical data uses HTML translation and preserves markup and dimensions'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$e->run($c,true);
            $requests=array_values(array_filter($g->translationRequests,function($r){return $r[0]==='<p class="spec">Seide: 90 x 90 cm</p>';}));
            expect(count($requests)===5);foreach($requests as $r){expect($r[1]['tag_handling']==='html' && $r[1]['tag_handling_version']==='v2');}
            expect($g->rows['en']['technicalData']==='<p class="spec">Silk: 90 x 90 cm</p>');
            raises(function(){Engine::validate('<p class="spec">90 cm</p>','<p>90 cm</p>','technicalData');});
            raises(function(){Engine::validate('<p>90 cm</p>','<p>91 cm</p>','technicalData');});
        },
        'all item names and HTML title enforce 240 Unicode characters and plain text'=>function(){
            foreach(['name','name2','name3','title'] as $field){Engine::validate('Titel',str_repeat('Ä',240),$field);raises(function()use($field){Engine::validate('Titel',str_repeat('Ä',241),$field);});raises(function()use($field){Engine::validate('Titel','<b>Title</b>',$field);});}
            raises(function(){Engine::validate('Seide','<b>Silk</b>','metaKeywords');});
        },
        'empty added source fields preserve existing translations and request review'=>function(){
            [$s,$g,$e,$c]=fixture();$g->rows['en']['name2']='Manual subtitle';$g->rows['en']['technicalData']='<p>Manual specifications</p>';
            expect($e->run($c,true)['status']==='review_required');expect(!$g->writes && $g->calls===0);expect($g->rows['en']['name2']==='Manual subtitle');expect($g->ready===[420,421]);
        },
        'manual added target field retained while German field unchanged'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$e->run($c,true);$calls=$g->calls;$g->ready=[420,421];$g->rows['en']['name2']='Manual subtitle';$e->run($c,true);expect($g->calls===$calls);expect($g->rows['en']['name2']==='Manual subtitle');
        },
        'single changed added source field only retranslates that field'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$e->run($c,true);$calls=$g->calls;$g->ready=[420,421];$g->writes=[];$g->rows['de']['name3']='Neues Spieltuch 90 x 90 cm';
            $e->run($c,true);expect($g->calls-$calls===5);foreach($g->writes as $w){expect(array_keys($w[1])===['name3']);}
        },
        'expanded readback failure leaves trigger and no baseline'=>function(){
            [$s,$g,$e,$c]=expandedFieldFixture();$g->dropWrite=true;raises(function()use($e,$c){$e->run($c,true);});expect($g->ready===[420,421]);expect($s->get('baseline:42')===null);
        },
        'gateway accepts added fields but rejects URL mutations before HTTP'=>function(){
            $h=new ConfigurationHttpFixture(configValues());$g=new Gateway($h);$c=['item_id'=>42,'variation_id'=>420];
            $g->writeTexts($c,'en',['name2'=>'Subtitle','name3'=>'Other title','title'=>'HTML title','technicalData'=>'<p>Specifications</p>','metaKeywords'=>'silk, play'],true);
            expect(count($h->requests)===1 && $h->requests[0][1]==='PUT');$before=count($h->requests);
            raises(function()use($g,$c){$g->writeTexts($c,'en',['urlPath'=>'changed'],true);});expect(count($h->requests)===$before);
        }
    ];
}
