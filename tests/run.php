<?php
require __DIR__ . '/../src/Core/TranslationEngine.php';
use WaldorfshopTranslations\Core\TranslationEngine as Engine;
class MemoryStore
{
    public $data = [];
    public function get($key) { return $this->data[$key] ?? null; }
    public function put($key, $value) { $this->data[$key] = $value; }
}
class FakeGateway
{
    public $rows;
    public $calls = 0;
    public $writes = [];
    public $ready = [420,421];
    public $review = [];
    public $failWrite = false;
    public $dropWrite = false;
    public $failReview = false;
    public $failTranslation = false;
    public $invalidNumbers = false;
    public function __construct()
    {
        $this->rows = ['de' => ['name' => 'Spieltuch', 'previewDescription' => '<p>Seide</p>', 'description' => '<p>90 x 90 cm</p>', 'metaDescription' => 'Seide für freies Spiel'], 'en' => ['name' => 'Old English title', 'urlPath' => 'keep-me']];
    }
    public function texts($c) { return $this->rows; }
    public function verifyGlossary() {}
    public function translate($text, $options)
    {
        $this->calls++;
        if ($this->failTranslation) { throw new RuntimeException('Provider failure'); }
        if ($this->invalidNumbers) { $text = str_replace('90', '91', $text); }
        return str_replace(['Spieltuch','Seide'], ['Playcloth','Silk'], $text);
    }
    public function writeTexts($c,$lang,$changes,$exists)
    {
        $this->writes[] = [$lang,$changes];
        if (!$this->dropWrite) { $this->rows[$lang] = array_merge($this->rows[$lang] ?? [],$changes); }
        if ($this->failWrite) { $this->failWrite=false;throw new RuntimeException('Ambiguous write'); }
    }
    public function hasAnyTrigger($c) { return count($this->ready)>0; }
    public function addReviewTag($id)
    {
        if ($this->failReview && $id===421) { throw new RuntimeException('Review marker failed'); }
        $this->review[$id]=true;
    }
    public function removeTrigger($id)
    {
        if (count($this->review)!==2) { throw new RuntimeException('Trigger removed before both review markers'); }
        $this->ready=array_values(array_diff($this->ready,[$id]));
    }
}
function expect($value,$message='Assertion failed') { if (!$value) { throw new RuntimeException($message); } }
function raises($fn) { try { $fn(); } catch (Throwable $e) { return; } throw new RuntimeException('Expected failure'); }
function fixture()
{
    $store=new MemoryStore();$gateway=new FakeGateway();
    $settings=['policy'=>['glossary_id'=>'test','version'=>1], 'context'=>'Waldorfshop', 'monthly_items'=>100,'monthly_characters'=>250000,'seed_month'=>'','seed_characters'=>0,'seed_items'=>[]];
    $candidate=['item_id'=>42,'variation_id'=>420,'tagged_variation_ids'=>[420,421],'ready_tag_id'=>141];
    return [$store,$gateway,new Engine($store,$gateway,$settings),$candidate,$settings];
}
$tests=[];
require __DIR__ . '/auth-fixture.php';
$tests += authBoundaryTests();
$tests['preview never writes or removes tags']=function(){
    [$s,$g,$e,$c]=fixture();$result=$e->run($c,false);
    expect($result['status']==='prepared');expect($g->calls===20);expect(!$g->writes);expect($g->ready===[420,421]);expect($s->get('baseline:42')===null);
};
$tests['unknown old texts refresh and verified baseline persists']=function(){
    [$s,$g,$e,$c]=fixture();expect($e->run($c,true)['status']==='complete');
    expect($g->rows['en']['name']==='Playcloth');expect($g->rows['en']['urlPath']==='keep-me');expect(!$g->ready);
    expect($s->get('baseline:42')['en']['name']['source']==='Spieltuch');
};
$tests['unchanged source causes zero new paid requests']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,true);$g->ready=[420,421];$before=$g->calls;$e->run($c,false);expect($g->calls===$before);
};
$tests['only changed German description is translated']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,true);$g->ready=[420,421];$before=$g->calls;$g->rows['de']['description'].='<p>Neu</p>';
    $e->run($c,true);expect($g->calls-$before===5);foreach($s->get('job:42')['targets'] as $t){expect(array_keys($t['changes'])===['description']);}
};
$tests['manual target is retained when German unchanged']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,true);$g->ready=[420,421];$g->rows['en']['name']='Manual name';$e->run($c,true);expect($g->rows['en']['name']==='Manual name');
};
$tests['simultaneous German and target edits require review']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,true);$g->ready=[420,421];$g->rows['en']['name']='Manual name';$g->rows['de']['name']='Neues Spieltuch';$before=count($g->writes);
    expect($e->run($c,true)['status']==='review_required');expect(count($g->writes)===$before);expect($g->ready===[420,421]);
};
$tests['target edit after preview creates fresh safe snapshot']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,false);$g->rows['en']['name']='Edited before next run';$e->run($c,false);expect($s->get('job:42')['targets']['en']['before']['name']==='Edited before next run');
};
$tests['ambiguous partial write resumes without duplicate language write']=function(){
    [$s,$g,$e,$c]=fixture();$g->failWrite=true;raises(function()use($e,$c){$e->run($c,true);});expect($s->get('baseline:42')===null);$paid=$g->calls;
    expect($e->run($c,true)['status']==='complete');expect($g->calls===$paid);expect(count(array_filter($g->writes,function($w){return $w[0]==='en';}))===1);
};
$tests['source change after partial write cannot replace pending job']=function(){
    [$s,$g,$e,$c]=fixture();$g->failWrite=true;raises(function()use($e,$c){$e->run($c,true);});$before=$g->calls;$g->rows['de']['name']='Changed';raises(function()use($e,$c){$e->run($c,true);});expect($g->calls===$before);
};
$tests['readback failure leaves trigger and no baseline']=function(){
    [$s,$g,$e,$c]=fixture();$g->dropWrite=true;raises(function()use($e,$c){$e->run($c,true);});expect($g->ready===[420,421]);expect($s->get('baseline:42')===null);
};
$tests['review tag failure removes no trigger']=function(){
    [$s,$g,$e,$c]=fixture();$g->failReview=true;raises(function()use($e,$c){$e->run($c,true);});expect($g->ready===[420,421]);$g->failReview=false;expect($e->run($c,true)['status']==='complete');
};
$tests['removed trigger prevents writes']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,false);$g->ready=[];raises(function()use($e,$c){$e->run($c,true);});expect(!$g->writes);
};
$tests['empty German field does not delete translated field']=function(){
    [$s,$g,$e,$c]=fixture();$e->run($c,true);$g->ready=[420,421];$g->rows['de']['description']='';$before=count($g->writes);expect($e->run($c,true)['status']==='review_required');expect(count($g->writes)===$before);
};
$tests['budget reserved before uncertain provider call']=function(){
    [$s,$g,$e,$c]=fixture();$g->failTranslation=true;raises(function()use($e,$c){$e->run($c,false);});expect($s->get('budget:'.gmdate('Y-m'))['characters']>0);
};
$tests['character limit blocks before provider request']=function(){
    [$s,$g,$e,$c,$settings]=fixture();$settings['monthly_characters']=1;$e=new Engine($s,$g,$settings);raises(function()use($e,$c){$e->run($c,false);});expect($g->calls===0);
};
$tests['monthly unique article limit enforced']=function(){
    [$s,$g,$e,$c,$settings]=fixture();$settings['monthly_items']=1;$e=new Engine($s,$g,$settings);$e->run($c,false);$c['item_id']=99;$g->rows['de']['name']='Other item';raises(function()use($e,$c){$e->run($c,false);});
};
$tests['validation protects HTML attributes numbers and title length']=function(){
    raises(function(){Engine::validate('<a href="/a">Hallo</a>','<a href="/b">Hello</a>','description');});
    raises(function(){Engine::validate('Ab 3 Jahren','From 2 years','name');});
    Engine::validate('2,5 cm','2.5 cm','name');
    raises(function(){Engine::validate('Name',str_repeat('X',241),'name');});
};
$tests['active HTML blocked and images are not blank']=function(){
    raises(function(){Engine::blank('<script>x()</script>');});expect(!Engine::blank('<img src="/a.png">'));expect(Engine::blank('<p>&nbsp;</p>'));
};
$tests['rejected paid output is retained but never applied or paid twice']=function(){
    [$s,$g,$e,$c]=fixture();$g->invalidNumbers=true;
    raises(function()use($e,$c){$e->run($c,true);});
    $calls=$g->calls;expect(!$g->writes);expect($g->ready===[420,421]);
    $job=$s->get('job:42');expect($job['status']==='blocked');expect($job['validation_error']['field']==='description');expect(strpos($job['validation_error']['target'],'91')!==false);
    raises(function()use($e,$c){$e->run($c,true);});expect($g->calls===$calls);expect(!$g->writes);
};
$tests['encoded HTML apostrophes never count as product numbers']=function(){
    Engine::validate('<p>Firma 1995, Flöte 440 Hz</p>', '<p>L&#x27;entreprise 1995, flûte 440 Hz</p>', 'description');
    Engine::validate('<p>Firma 1995</p>', '<p>L&#39;entreprise &#49;995</p>', 'description');
    raises(function(){Engine::validate('<p>Firma 1995, Flöte 440 Hz</p>', '<p>L&#x27;entreprise 1996, flûte 440 Hz</p>', 'description');});
};
require __DIR__ . '/configuration-fixture.php';
$tests += configurationTests();
$failed=0;
foreach($tests as $name=>$test){try{$test();echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: ".$e->getMessage()."\n";}}
echo count($tests)." tests; $failed failures\n";
exit($failed?1:0);
