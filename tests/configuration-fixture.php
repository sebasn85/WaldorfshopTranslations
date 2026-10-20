<?php
require __DIR__ . '/../src/Services/Configuration.php';
require __DIR__ . '/../src/Services/Gateway.php';
use WaldorfshopTranslations\Services\Configuration as Config;
use WaldorfshopTranslations\Services\Gateway;
class ConfigurationHttpFixture extends \WaldorfshopTranslations\Services\HttpClient
{
    public $values; public $requests=[]; public $missingGlossary=false; public $missingHtml=false; public $invalidTag=false; public $glossaryText="Spieltuch\tplay cloth";
    public function __construct($values) { $this->values=$values; }
    public function settings(): array { return Config::parse($this->values); }
    public function request(string $service,string $method,string $path,array $body=[],array $query=[])
    {
        $this->requests[]=[$service,$method,$path,$body,$query];
        if (preg_match('~^/rest/tags/(\d+)$~',$path,$m)) { return ['id'=>(int)$m[1],'availabilities'=>$this->invalidTag?[]:[['tagId'=>(int)$m[1],'tagType'=>'variation']]]; }
        if ($path==='/v3/languages') {
            $rows=[['lang'=>'de','usable_as_source'=>true,'features'=>['tag_handling'=>[],'glossary'=>[]]]];
            foreach ($this->settings()['targets'] as $code) { $rows[]=['lang'=>strtolower($code),'usable_as_target'=>true,'features'=>$this->missingHtml?[]:['tag_handling'=>[],'glossary'=>[]]]; }
            return $rows;
        }
        if (strpos($path,'/v3/glossaries/')===0) { return ['dictionaries'=>$this->missingGlossary?[]:[['source_lang'=>'de','target_lang'=>$query['target_lang'],'entries'=>$this->glossaryText]]]; }
        if ($path==='/rest/tags/relationships' && $method==='GET') { return [['tagId'=>$query['tagId'],'tagType'=>'variation','relationshipValue'=>420]]; }
        if ($path==='/rest/items/variations') { return [['id'=>420,'itemId'=>42],['id'=>999,'itemId'=>99]]; }
        return null;
    }
}
function configValues(): array
{
    return ['targetLanguages'=>'en,fr,it,es,nl','readyTagId'=>'901','reviewTagId'=>'902','operatorUserId'=>'12','plentyBaseUrl'=>'https://p12345.my.plentysystems.com','plentyUsername'=>'fixture','plentyPassword'=>'fixture-only','deeplKey'=>'fixture-only','glossaryMode'=>'off'];
}
function configurationTests(): array
{
    return [
        'selected languages use Plenty-specific mappings and variants'=>function(){
            $v=configValues();$v['targetLanguages']=['cz','se','vn','nn','pt','cn','en'];$v['englishVariant']='EN-US';$v['portugueseVariant']='PT-BR';$v['chineseVariant']='ZH-HANT';$s=Config::parse($v);
            expect($s['targets']===['en'=>'EN-US','cn'=>'ZH-HANT','cz'=>'CS','nn'=>'NB','pt'=>'PT-BR','se'=>'SV','vn'=>'VI']);
        },
        'deselecting every language blocks before processing'=>function(){foreach (['',[], '   '] as $empty){$v=configValues();$v['targetLanguages']=$empty;raises(function()use($v){Config::parse($v);});}},
        'unknown language and German target blocked'=>function(){foreach (['de','zz','en,/etc/passwd'] as $lang){$v=configValues();$v['targetLanguages']=$lang;raises(function()use($v){Config::parse($v);});}},
        'equal malformed or missing tags blocked'=>function(){foreach (['901','0','12abc','-3','1.5',''] as $id){$v=configValues();$v['reviewTagId']=$id;raises(function()use($v){Config::parse($v);});}},
        'only validated Plenty HTTPS origins may receive credentials'=>function(){foreach (['http://p123.my.plentysystems.com','https://example.com','https://p123.my.plentysystems.com.evil.org','https://p123.my.plentysystems.com@evil.org','https://p123.my.plentysystems.com/path','https://127.0.0.1','https://p123.my.plentysystems.com:444'] as $origin){raises(function()use($origin){Config::origin($origin);});}expect(Config::origin('https://p123.my.plentysystems.com/')==='https://p123.my.plentysystems.com');},
        'DeepL Free and Pro have fixed separate origins'=>function(){ $v=configValues();expect(Config::parse($v)['deepl_origin']==='https://api.deepl.com');$v['deeplPlan']='free';expect(Config::parse($v)['deepl_origin']==='https://api-free.deepl.com');$v['deeplPlan']='other';raises(function()use($v){Config::parse($v);});},
        'new installation requires own credentials and operator'=>function(){foreach(['plentyUsername','plentyPassword','deeplKey','operatorUserId'] as $key){$v=configValues();$v[$key]='';raises(function()use($v){Config::parse($v);});}},
        'configured limits have no Waldorf pilot seed'=>function(){ $v=configValues();$v['monthlyItems']='7';$v['monthlyCharacters']='4000';$s=Config::parse($v);expect($s['monthly_items']===7 && $s['monthly_characters']===4000 && $s['seed_items']===[] && $s['seed_month']==='');},
        'configured trigger applies to candidate scan and direct intersection'=>function(){ $h=new ConfigurationHttpFixture(configValues());$g=new Gateway($h);$c=$g->candidates();expect(count($c)===1 && $c[0]['ready_tag_id']===901 && $c[0]['item_id']===42);expect($h->requests[0][4]['tagId']===901 && $h->requests[1][4]['variationTagId']===901);},
        'configured trigger deletion never uses legacy tag ID'=>function(){ $h=new ConfigurationHttpFixture(configValues());$g=new Gateway($h);raises(function()use($g){$g->removeTrigger(420);});expect($h->requests[1][2]==='/rest/tags/relationships/420/variation/901');},
        'configured review tag never uses legacy tag ID'=>function(){ $h=new ConfigurationHttpFixture(configValues());$g=new Gateway($h);raises(function()use($g){$g->addReviewTag(421);});$post=array_values(array_filter($h->requests,function($r){return $r[1]==='POST';}));expect($post[0][3]['tagId']===902);},
        'non-selected language write blocked at gateway'=>function(){ $v=configValues();$v['targetLanguages']='fr';$h=new ConfigurationHttpFixture($v);$g=new Gateway($h);raises(function()use($g){$g->writeTexts(['item_id'=>42,'variation_id'=>420],'en',['name'=>'No'],true);});expect(!$h->requests);},
        'preflight performs no paid or mutation request'=>function(){ $h=new ConfigurationHttpFixture(configValues());$g=new Gateway($h);$s=$g->preflight($h->settings());expect(count($s['targets'])===5);foreach($h->requests as $r){expect($r[1]==='GET');}},
        'invalid variation tag blocks preflight'=>function(){ $h=new ConfigurationHttpFixture(configValues());$h->invalidTag=true;$g=new Gateway($h);raises(function()use($g,$h){$g->preflight($h->settings());});expect(count($h->requests)===1);},
        'unsupported HTML capability blocks preflight'=>function(){ $h=new ConfigurationHttpFixture(configValues());$h->missingHtml=true;$g=new Gateway($h);raises(function()use($g,$h){$g->preflight($h->settings());});},
        'required glossary coverage cannot silently fall back'=>function(){ $v=configValues();$v['glossaryMode']='required';$v['glossaryId']='11111111-2222-3333-4444-555555555555';$h=new ConfigurationHttpFixture($v);$h->missingGlossary=true;$g=new Gateway($h);raises(function()use($g,$h){$g->preflight($h->settings());});},
        'glossary entry changes invalidate prepared policy'=>function(){ $v=configValues();$v['glossaryMode']='required';$v['glossaryId']='11111111-2222-3333-4444-555555555555';$h=new ConfigurationHttpFixture($v);$g=new Gateway($h);$a=$g->preflight($h->settings());$h->glossaryText="Spieltuch\tplay silk";$b=$g->preflight($h->settings());expect($a['policy']!==$b['policy']);},
        'only selected language processed and other baseline retained'=>function(){ [$s,$g,$e,$c,$settings]=fixture();$settings['targets']=['fr'=>'FR'];$settings['policy']['targets']=$settings['targets'];$settings['policy']['glossary_id']='';$e=new \WaldorfshopTranslations\Core\TranslationEngine($s,$g,$settings);$s->put('baseline:42',['en'=>['name'=>['source'=>'old','target'=>'Keep']]]);$en=$g->rows['en'];$e->run($c,true);expect(count($g->writes)===1 && $g->writes[0][0]==='fr' && $g->calls===4 && $g->rows['en']===$en);expect($s->get('baseline:42')['en']['name']['target']==='Keep');},
        'changing selection after partial write blocks recovery'=>function(){ [$s,$g,$e,$c,$settings]=fixture();$g->failWrite=true;raises(function()use($e,$c){$e->run($c,true);});$before=count($g->writes);$settings['targets']=['fr'=>'FR'];$settings['policy']['targets']=$settings['targets'];$e=new \WaldorfshopTranslations\Core\TranslationEngine($s,$g,$settings);raises(function()use($e,$c){$e->run($c,true);});expect(count($g->writes)===$before && $g->ready===[420,421]);}
    ];
}
