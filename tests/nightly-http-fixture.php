<?php
namespace Plenty\Modules\Plugin\Libs\Contracts {
    abstract class LibraryCallContract { abstract public function call($name,$arguments); }
}
namespace Plenty\Plugin {
    class ConfigRepository {
        private $values; public $reads=0;
        public function __construct(array $values){$this->values=$values;}
        public function get($key){$this->reads++;return $this->values[$key]??null;}
    }
}
namespace {
    class NightLibraryFixture extends \Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract {
        public $calls=[];public $status=200;public $throw=false;public $badLogin=false;
        public function call($name,$arguments){
            $this->calls[]=[$name,$arguments];
            if($this->throw){throw new \RuntimeException('fixture-secret-must-not-escape');}
            if($arguments['path']==='/rest/login'){return ['ok'=>true,'data'=>$this->badLogin?[]:['access_token'=>'fixture-token']];}
            return ['ok'=>$this->status===200,'status'=>$this->status,'data'=>['fixture'=>true],'body'=>'fixture-secret-must-not-escape'];
        }
    }
    function nightHttpFixture():array{
        $values=[];foreach(configValues() as $key=>$value){$values['WaldorfshopTranslations.'.$key]=$value;}
        $library=new NightLibraryFixture();$config=new \Plenty\Plugin\ConfigRepository($values);
        return [new \WaldorfshopTranslations\Services\HttpClient($library,$config),$library,$config];
    }
    function nightlyHttpTests():array{
        $tests=[];
        foreach([429,500,503,456,401] as $status){
            $tests['remote service HTTP '.$status.' pauses instead of blocking articles']=function()use($status){
                [$h,$library]=nightHttpFixture();$library->status=$status;$caught=false;
                try{$h->request('deepl','POST','/v2/translate',['text'=>['Fixture']]);}catch(\WaldorfshopTranslations\Core\ProcessingPause $e){$caught=$e->reason()==='service' && strpos($e->getMessage(),'fixture-secret')===false;}
                expect($caught && count($library->calls)===1);
            };
        }
        foreach([400,413,422] as $status){
            $tests['rejected translation HTTP '.$status.' remains an isolated article failure']=function()use($status){
                [$h,$library]=nightHttpFixture();$library->status=$status;$caught=false;
                try{$h->request('deepl','POST','/v2/translate',['text'=>['Fixture']]);}catch(\RuntimeException $e){$caught=!($e instanceof \WaldorfshopTranslations\Core\ProcessingPause) && strpos($e->getMessage(),'fixture-secret')===false;}
                expect($caught);
            };
        }
        $tests['expired HTTP deadline does not read credentials or call library']=function(){
            [$h,$library,$config]=nightHttpFixture();$h->setDeadline(time()-1);raises(function()use($h){$h->request('plenty','GET','/rest/items/42');});expect(!$library->calls && $config->reads===0);
        };
        $tests['library exception becomes safe resumable service pause']=function(){
            [$h,$library]=nightHttpFixture();$library->throw=true;$caught=false;try{$h->request('deepl','GET','/v3/languages');}catch(\WaldorfshopTranslations\Core\ProcessingPause $e){$caught=$e->reason()==='service' && strpos($e->getMessage(),'fixture-secret')===false;}expect($caught);
        };
        $tests['invalid login is a shared pause before any article request']=function(){
            [$h,$library]=nightHttpFixture();$library->badLogin=true;$caught=false;try{$h->request('plenty','GET','/rest/items/42');}catch(\WaldorfshopTranslations\Core\ProcessingPause $e){$caught=$e->reason()==='service';}expect($caught && count($library->calls)===1);
        };
        $tests['night-ending deadline is capped at 08 local time']=function(){
            [$s,$v,$b]=nightFixture(1);$v->now=strtotime('2026-10-22T05:59:30Z');$b->run();expect($v->deadlines[0]===strtotime('2026-10-22T06:00:00Z'));
        };
        return $tests;
    }
}
