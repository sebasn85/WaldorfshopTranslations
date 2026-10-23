<?php
use WaldorfshopTranslations\Core\TranslationIssue;
use WaldorfshopTranslations\Core\TranslationEngine as Engine;
use WaldorfshopTranslations\Services\Gateway;

function recoveryTests(): array
{
    return [
        'real Grapat ordinal correction preserves numbers and rejects the original digit conversion'=>function(){
            $samples=json_decode(file_get_contents(__DIR__ . '/real-recovery-samples.json'),true); $sample=$samples[0];
            raises(function()use($sample){Engine::validate($sample['source'],$sample['target'],$sample['field']);});
            Engine::validate($sample['source'],$sample['corrected'],$sample['field']);
            expect(strpos($sample['corrected'],'cinquième édition')!==false);
        },
        'real Toverlux correction restores exactly two original links without weakening HTML checks'=>function(){
            $samples=json_decode(file_get_contents(__DIR__ . '/real-recovery-samples.json'),true); $sample=$samples[1];
            raises(function()use($sample){Engine::validate($sample['source'],$sample['target'],$sample['field']);});
            Engine::validate($sample['source'],$sample['corrected'],$sample['field']);
            expect(substr_count($sample['corrected'],'<a ')===2);
        },
        'recovery token expires after translation context changes'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot();
            $v=$r->recoveryPreview(42); $h->values['writesAllowed']='1'; $h->values['translationContext']='New context';
            raises(function()use($r,$v){$r->recoveryResume(42,$v['review_token']);}); expect(!$g->workers[42]->writes);
        },
        'platform replay can finish real-shaped cached job without changing backing state or texts'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot();
            $job=$s->get('job:42'); $job['status']='blocked'; $s->put('job:42',$job); $before=$s->data; $rows=$g->workers[42]->rows;
            $memory=new \WaldorfshopTranslations\Diagnostics\ReplayStore($s);
            $sim=new \WaldorfshopTranslations\Diagnostics\ReplayGateway($rows,[420=>true,421=>true]);
            $engine=new Engine($memory,$sim,$h->settings()); expect($engine->run($g->queue[0],true)['status']==='complete');
            expect($s->data===$before && $g->workers[42]->rows===$rows && $sim->paidRequests===0 && $sim->writes===1);
        },
        'HTML accepts only equivalent quotes attribute order and void-tag notation'=>function(){
            Engine::validate('<p class="a" title="Text">90 cm<br><img src="/a.png" alt="Bild"></p>', "<p title='Text' class='a'>90 cm<br/><img alt='Bild' src='/a.png'/></p>", 'description');
            Engine::validate('<a href="/a?x=1&amp;y=2">Text</a>', "<a href='/a?x=1&y=2'>Texte</a>", 'description');
        },
        'HTML still blocks changed links styles attributes tag order and inserted markup'=>function(){
            foreach (['<p class="b">90 cm<br></p>','<p class="a">91 cm<br></p>','<p class="a">90 cm<hr></p>','<p class="a" onclick="bad()">90 cm<br></p>','<p class="a">90 cm<br><img src="/new.png"></p>'] as $changed) {
                raises(function()use($changed){Engine::validate('<p class="a">90 cm<br></p>',$changed,'description');});
            }
            raises(function(){Engine::validate('<a href="/safe">Text</a>','<a href="/changed">Text</a>','description');});
            raises(function(){Engine::validate('<p style="color:red">Text</p>','<p style="color:blue">Text</p>','description');});
        },
        'reviewed correction repairs rejected cached output without buying that field again'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $g->workers[42]->invalidNumbers=true;
            raises(function()use($r){$r->pilot();}); $paid=$g->workers[42]->calls; $original=$s->get('job:42');
            $corrections=['en'=>['description'=>'<p>90 x 90 cm</p>']]; $g->workers[42]->invalidNumbers=false;
            $v=$r->recoveryPreview(42,$corrections); expect($v['can_resume'] && $s->get('job:42')===$original);
            $h->values['writesAllowed']='1'; $r->recoveryResume(42,$v['review_token'],$corrections);
            expect($g->workers[42]->calls===$paid+1 && $s->get('job:42')['status']==='complete');
            expect(isset($s->get('job:42')['reviewed_corrections']['en']['description']));
        },
        'recovery corrections cannot alter URLs or bypass number validation'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot(); $before=$s->data;
            foreach ([['en'=>['urlPath'=>'changed']],['en'=>['description'=>'<p>91 x 90 cm</p>']],['zz'=>['name'=>'No']]] as $corrections) {
                raises(function()use($r,$corrections){$r->recoveryPreview(42,$corrections);});
            }
            expect($s->data===$before && !$g->workers[42]->writes);
        },
        'recovery token binds exact reviewed replacement text'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot();
            $a=['en'=>['description'=>'<p>90 x 90 cm adjusted</p>']]; $v=$r->recoveryPreview(42,$a); $h->values['writesAllowed']='1';
            $before=$s->data; raises(function()use($r,$v){$r->recoveryResume(42,$v['review_token'],['en'=>['description'=>'<p>90 x 90 cm different</p>']]);});
            expect(!$g->workers[42]->writes && $s->get('job:42')===$before['job:42'] && !$s->locked);
        },
        'recovery preview reads existing paid answers without writing job or acquiring lock'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot();
            $job=$s->get('job:42'); $job['status']='blocked'; $s->put('job:42',$job); $before=$s->data; $locks=$s->acquisitions; $paid=$g->workers[42]->calls;
            $v=$r->recoveryPreview(42);
            expect($v['can_resume'] && $v['reusable_fields']===4 && $v['missing_fields']===0);
            expect($s->data===$before && $s->acquisitions===$locks && $g->workers[42]->calls===$paid && !$g->workers[42]->writes);
        },
        'reviewed blocked recovery completes from cache without new translation calls'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot();
            $job=$s->get('job:42'); $job['status']='blocked'; $s->put('job:42',$job); $h->values['writesAllowed']='1';
            $v=$r->recoveryPreview(42); $paid=$g->workers[42]->calls; $result=$r->recoveryResume(42,$v['review_token']);
            expect($result['status']==='complete' && $g->workers[42]->calls===$paid && !$g->workers[42]->ready);
            expect($s->get('last-result')['kind']==='reviewed_recovery' && !$s->locked);
        },
        'recovery rejects stale review after target change without new writes or paid requests'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot(); $h->values['writesAllowed']='1';
            $v=$r->recoveryPreview(42); $paid=$g->workers[42]->calls; $g->workers[42]->rows['en']['name']='Manual edit';
            raises(function()use($r,$v){$r->recoveryResume(42,$v['review_token']);});
            expect(!$g->workers[42]->writes && $g->workers[42]->calls===$paid && !$s->locked);
        },
        'recovery cannot bypass write switch or missing trigger'=>function(){
            [$s,$h,$g,$r]=nightRunnerFixture(1); $h->values['pilotItemId']='42'; $h->values['writesAllowed']='0'; $r->pilot(); $v=$r->recoveryPreview(42);
            raises(function()use($r,$v){$r->recoveryResume(42,$v['review_token']);}); expect(!$g->workers[42]->writes);
            $g->queue=[]; raises(function()use($r){$r->recoveryPreview(42);});
        },
        'new failure details identify field and phase while unknown errors hide raw SDK messages'=>function(){
            [$s,$g,$e,$c]=fixture(); $g->invalidNumbers=true; raises(function()use($e,$c){$e->run($c,true);});
            $f=$s->get('job:42')['failure']; expect($f['code']==='numbers_changed' && $f['phase']==='translate' && $f['field']==='description' && $f['language']==='en');
            $safe=TranslationIssue::details(new RuntimeException('fixture-secret-do-not-expose')); expect(strpos(json_encode($safe),'fixture-secret')===false);
        },
        'uncertain paid request is never blindly repeated even on manual engine invocation'=>function(){
            [$s,$g,$e,$c]=fixture(); $g->failTranslation=true; raises(function()use($e,$c){$e->run($c,false);}); $paid=$g->calls;
            $g->failTranslation=false; raises(function()use($e,$c){$e->run($c,true);}); expect($g->calls===$paid && !$g->writes);
        },
        'billed characters are retained separately from conservative reserved characters'=>function(){
            [$s,$g,$e,$c,$settings]=fixture();
            $g=new class extends FakeGateway {
                public function translateWithUsage($text,$options){return ['text'=>$this->translate($text,$options),'billed_characters'=>7];}
            };
            $e=new Engine($s,$g,$settings); $e->run($c,false); $job=$s->get('job:42');
            $request=$s->get($job['requests']['en']['description']); expect($request['state']==='responded' && $request['billed_characters']===7 && $request['source_characters']>7);
            $paid=$g->calls; $e->run($c,true); expect($g->calls===$paid);
        },
        'existing prepared translations are revalidated before applying'=>function(){
            [$s,$g,$e,$c]=fixture(); $e->run($c,false); $job=$s->get('job:42'); $job['targets']['en']['changes']['description']='<p>91 x 90 cm</p>'; $s->put('job:42',$job); $paid=$g->calls;
            raises(function()use($e,$c){$e->run($c,true);}); expect(!$g->writes && $g->calls===$paid);
        },
        'provider billing-period limit blocks new requests after local month reset'=>function(){
            $h=new class(configValues()) extends ConfigurationHttpFixture {
                public $used=249999;
                public function request(string $service,string $method,string $path,array $body=[],array $query=[]){
                    if($path==='/v2/usage'){return ['character_count'=>$this->used,'character_limit'=>1000000000000];}
                    return parent::request($service,$method,$path,$body,$query);
                }
            };
            $g=new Gateway($h); raises(function()use($g){$g->checkTranslationBudget('Text');}); $h->used=0; $g->checkTranslationBudget('Text');
        }
    ];
}
