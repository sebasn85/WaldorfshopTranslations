<?php
namespace WaldorfshopTranslations\Repositories {
    // The SDK-backed database adapter is replaced only at the persistence boundary.
    class StateRepository extends \MemoryStore
    {
        public $acquisitions=0; public $releases=0; public $locked=false;
        public function acquire(){if($this->locked){throw new \RuntimeException('Locked');}$this->locked=true;$this->acquisitions++;}
        public function release(){$this->locked=false;$this->releases++;}
    }
}
namespace {
    function pluginApp($class, array $arguments=[]){return new $class(...$arguments);}
    class NightRunnerHttpFixture extends \WaldorfshopTranslations\Services\HttpClient
    {
        public $values; public $clock; public $deadline=0;
        public function __construct($clock){$this->clock=$clock;$this->values=configValues()+['automaticEnabled'=>'1','writesAllowed'=>'1'];$this->values['targetLanguages']='en';}
        public function option(string $key,$default=''){return $this->values[$key]??$default;}
        public function settings():array{return \WaldorfshopTranslations\Services\Configuration::parse($this->values);}
        public function setDeadline(int $deadline):void{$this->deadline=$deadline;}
        public function checkDeadline():void{if($this->deadline>0 && $this->clock->now >= $this->deadline){throw new \WaldorfshopTranslations\Core\ProcessingPause('time','Yield');}}
    }
    class NightRunnerGatewayFixture extends \WaldorfshopTranslations\Services\Gateway
    {
        public $workers=[]; public $queue=[]; public $active; public $scans=0; public $preflightError=null; public $clock; public $translationSeconds=0; public $uncertainWrite=false;
        public function __construct($clock,int $count){
            $this->clock=$clock;
            for($id=42;$id<42+$count;$id++){$g=new FakeGateway();$g->rows['de']['name'].=' Artikel '.$id;$g->ready=[$id*10,$id*10+1];$this->workers[$id]=$g;$this->queue[]=['item_id'=>$id,'variation_id'=>$id*10,'tagged_variation_ids'=>$g->ready,'ready_tag_id'=>901];}
        }
        public function preflight(array $settings):array{if($this->preflightError){throw $this->preflightError;}return $settings;}
        public function candidates():array{$this->scans++;return $this->queue;}
        public function hasAnyTrigger(array $candidate):bool{$this->active=$candidate['item_id'];return (bool)$this->workers[$this->active]->ready;}
        public function texts(array $candidate):array{$this->active=$candidate['item_id'];return $this->workers[$this->active]->rows;}
        public function verifyGlossary():void{}
        public function translate(string $source,array $options):string{$this->clock->now+=$this->translationSeconds;return $this->workers[$this->active]->translate($source,$options);}
        public function writeTexts(array $candidate,string $language,array $changes,bool $exists):void{
            $this->workers[$candidate['item_id']]->writeTexts($candidate,$language,$changes,$exists);
            if($this->uncertainWrite){$this->uncertainWrite=false;throw new \WaldorfshopTranslations\Core\ProcessingPause('service','Uncertain response');}
        }
        public function addReviewTag(int $variation):void{$this->workers[$this->active]->addReviewTag($variation);}
        public function removeTrigger(int $variation):void{$this->workers[$this->active]->removeTrigger($variation);}
    }
    class NightRunnerFixture extends \WaldorfshopTranslations\Services\Runner
    {
        public $clock;
        public function __construct($http,$gateway,$store,$clock){parent::__construct($http,$gateway,$store);$this->clock=$clock;}
        protected function now():int{return $this->clock->now;}
    }
    function nightRunnerFixture(int $count=3):array{
        $clock=(object)['now'=>strtotime('2026-10-22T00:00:00Z')];$store=new \WaldorfshopTranslations\Repositories\StateRepository();$http=new NightRunnerHttpFixture($clock);$gateway=new NightRunnerGatewayFixture($clock,$count);
        return [$store,$http,$gateway,new NightRunnerFixture($http,$gateway,$store,$clock),$clock];
    }
    function nightlyRunnerTests():array{
        return [
            'real runner processes several real engine jobs and releases lock'=>function(){
                [$s,$h,$g,$r,$clock]=nightRunnerFixture();$before=[];foreach($g->workers as $id=>$w){$before[$id]=$w->rows['de'];}
                $result=$r->automatic();expect($result['status']==='complete' && $result['complete']===3 && $g->scans===1);expect($s->acquisitions===1 && $s->releases===1 && !$s->locked && $h->deadline===0);
                foreach($g->workers as $id=>$w){expect(!$w->ready && count($w->review)===2 && $w->rows['de']===$before[$id]);expect($s->get('job:'.$id)['status']==='complete');expect($w->rows['en']['urlPath']==='keep-me');}
            },
            'disabled real runner never acquires lock or scans queue'=>function(){
                [$s,$h,$g,$r]=nightRunnerFixture();$h->values['automaticEnabled']='0';expect($r->automatic()['status']==='disabled');expect($s->acquisitions===0 && $g->scans===0);
            },
            'daytime real runner leaves source tags jobs and lock untouched'=>function(){
                [$s,$h,$g,$r,$clock]=nightRunnerFixture();$clock->now=strtotime('2026-10-22T10:00:00Z');expect($r->automatic()['status']==='scheduled');expect(!$s->data && $s->acquisitions===0 && $g->scans===0);
            },
            'preflight service outage releases real runner lock'=>function(){
                [$s,$h,$g,$r]=nightRunnerFixture();$g->preflightError=new \WaldorfshopTranslations\Core\ProcessingPause('service','Unavailable');expect($r->automatic()['status']==='service_paused');expect(!$s->locked && $s->releases===1 && $g->scans===0 && $h->deadline===0);
            },
            'blocked real job is skipped while remaining jobs finish'=>function(){
                [$s,$h,$g,$r]=nightRunnerFixture();$s->put('job:42',['status'=>'blocked']);$result=$r->automatic();expect($result['skipped']===1 && $result['complete']===2);expect($g->workers[42]->ready===[420,421] && !$g->workers[42]->writes && $s->get('job:42')['status']==='blocked');
            },
            'removed trigger avoids real engine translation and continues queue'=>function(){
                [$s,$h,$g,$r]=nightRunnerFixture();$g->workers[42]->ready=[];$result=$r->automatic();expect($result['cancelled']===1 && $result['complete']===2 && $g->workers[42]->calls===0);
            },
            'interruption after engine completion never writes texts twice'=>function(){
                [$s,$h,$g,$r,$clock]=nightRunnerFixture(1);$r->automatic();$run=$s->get('nightly-run');$run['status']='pending';$run['cursor']=0;$run['complete']=0;$run['in_flight']=42;$s->put('nightly-run',$run);$writes=count($g->workers[42]->writes);$paid=$g->workers[42]->calls;$clock->now+=900;
                $result=$r->automatic();expect($result['status']==='complete' && $result['complete']===1 && count($g->workers[42]->writes)===$writes && $g->workers[42]->calls===$paid);
            },
            'real runner cooperatively pauses and reuses cached translations'=>function(){
                [$s,$h,$g,$r,$clock]=nightRunnerFixture(1);$g->translationSeconds=150;$result=$r->automatic();expect($result['status']==='pending' && $result['cursor']===0 && $s->get('job:42')['status']==='paused' && $g->workers[42]->calls===2);
                $clock->now+=900;$g->translationSeconds=0;$result=$r->automatic();expect($result['status']==='complete' && $g->workers[42]->calls===4 && !$g->workers[42]->ready);
            },
            'uncertain saved response pauses and resumes without duplicate write'=>function(){
                [$s,$h,$g,$r,$clock]=nightRunnerFixture(1);$g->uncertainWrite=true;$result=$r->automatic();expect($result['status']==='service_paused' && $s->get('job:42')['status']==='paused');$paid=$g->workers[42]->calls;$clock->now+=900;
                $result=$r->automatic();expect($result['status']==='complete' && count($g->workers[42]->writes)===1 && $g->workers[42]->calls===$paid);
            }
        ];
    }
}
