<?php
use WaldorfshopTranslations\Core\NightlyBatch;
use WaldorfshopTranslations\Core\ProcessingPause;
use WaldorfshopTranslations\Core\TranslationEngine;

function nightFixture(int $count = 3): array
{
    $store = new MemoryStore();
    $env = (object)['now'=>strtotime('2026-10-22T00:00:00Z'), 'queue'=>[], 'calls'=>[], 'settings_calls'=>0, 'candidate_calls'=>0, 'deadlines'=>[], 'policy'=>['version'=>2], 'worker'=>null, 'setup_error'=>null];
    for ($id=1; $id <= $count; $id++) { $env->queue[] = ['item_id'=>$id, 'variation_id'=>$id*10, 'tagged_variation_ids'=>[$id*10], 'ready_tag_id'=>141]; }
    $env->success = function ($candidate) use ($env, $store) {
        $env->queue = array_values(array_filter($env->queue, function ($row) use ($candidate) { return $row['item_id'] !== $candidate['item_id']; }));
        $store->put('job:' . $candidate['item_id'], ['status'=>'complete']);
        return ['item_id'=>$candidate['item_id'], 'status'=>'complete'];
    };
    $batch = new NightlyBatch($store,
        function () use ($env) { $env->settings_calls++; if ($env->setup_error) { throw $env->setup_error; } return ['policy'=>$env->policy]; },
        function () use ($env) { $env->candidate_calls++; return $env->queue; },
        function ($candidate, $settings, $recovering) use ($env) {
            $env->calls[] = [$candidate['item_id'], $recovering];
            return $env->worker ? ($env->worker)($candidate, $settings, $recovering) : ($env->success)($candidate);
        },
        function ($deadline) use ($env) { $env->deadlines[] = $deadline; },
        function () use ($env) { return $env->now; }
    );
    return [$store, $env, $batch];
}
function nightlyTests(): array
{
    return [
        'build smoke check validates SDK date factories and explicit closure calls'=>function(){
            (new \WaldorfshopTranslations\Migrations\CheckNightlyRuntime())->run();
        },
        'nightly daytime performs no API work and creates no queue'=>function(){
            [$s,$v,$b]=nightFixture();$v->now=strtotime('2026-10-22T10:00:00Z');
            expect($b->run()['status']==='scheduled');expect($v->settings_calls===0 && $v->candidate_calls===0 && !$v->calls && !$v->deadlines);expect($s->get('nightly-run')===null);
        },
        'night window follows German summer and winter time'=>function(){
            foreach(['2026-07-01T00:00:00Z','2026-07-01T05:59:59Z','2026-12-01T01:00:00Z','2026-12-01T06:59:59Z','2026-03-29T01:15:00Z'] as $time){expect(NightlyBatch::inWindow(strtotime($time)),$time);}
            foreach(['2026-07-01T06:00:00Z','2026-07-01T23:59:59Z','2026-12-01T00:59:59Z','2026-12-01T07:00:00Z'] as $time){expect(!NightlyBatch::inWindow(strtotime($time)),$time);}
        },
        'nightly processes multiple articles and resumes at persisted cursor'=>function(){
            [$s,$v,$b]=nightFixture(60);$v->queue=array_reverse($v->queue);
            $r=$b->run();expect($r['cursor']===25 && $r['status']==='pending');$v->now+=900;
            $r=$b->run();expect($r['cursor']===50);$v->now+=900;$r=$b->run();
            expect($r['status']==='complete' && $r['complete']===60);expect($v->candidate_calls===1);expect(array_column($v->calls,0)===range(1,60));
        },
        'all 120 queued articles complete through bounded packages'=>function(){
            [$s,$v,$b]=nightFixture(120);
            for($tick=0;$tick<5;$tick++){$r=$b->run();$v->now+=900;}
            expect($r['status']==='complete' && $r['complete']===120 && count($v->calls)===120);expect($v->candidate_calls===1);
            expect(count($s->get('nightly-queue:0')['entries'])===100 && count($s->get('nightly-queue:1')['entries'])===20);
            expect(!isset($r['queue']));
        },
        'same night completion does not rescan or reprocess'=>function(){
            [$s,$v,$b]=nightFixture();$b->run();$calls=count($v->calls);$setup=$v->settings_calls;$r=$b->run();
            expect($r['status']==='complete' && count($v->calls)===$calls && $v->settings_calls===$setup && $v->candidate_calls===1);
        },
        'late tags wait for the next daily snapshot'=>function(){
            [$s,$v,$b]=nightFixture(1);$b->run();$v->queue[]=['item_id'=>99,'variation_id'=>990,'tagged_variation_ids'=>[990],'ready_tag_id'=>141];
            $v->now+=900;$b->run();expect(count($v->calls)===1);$v->now+=86400;$r=$b->run();
            expect($r['day']==='2026-10-23' && $r['total']===1 && count($v->calls)===2 && $v->calls[1][0]===99);
        },
        'empty night snapshot completes and waits until next night'=>function(){
            [$s,$v,$b]=nightFixture(0);$r=$b->run();expect($r['status']==='complete' && $r['total']===0);$b->run();expect($v->candidate_calls===1 && !$v->calls);
        },
        'slow workers yield between articles and clear HTTP deadline'=>function(){
            [$s,$v,$b]=nightFixture(10);$v->worker=function($c)use($v){$v->now+=120;return ($v->success)($c);};
            $r=$b->run();expect($r['status']==='pending' && $r['cursor']===3);expect($v->deadlines[count($v->deadlines)-1]===0);
        },
        'temporary time pause resumes the same in-flight article'=>function(){
            [$s,$v,$b]=nightFixture(2);$once=true;$v->worker=function($c,$settings,$recovering)use($v,&$once){if($once){$once=false;throw new ProcessingPause('time','Yield');}expect($recovering);$v->worker=null;return ($v->success)($c);};
            $r=$b->run();expect($r['status']==='pending' && $r['cursor']===0 && $r['in_flight']===1);$v->now+=900;$r=$b->run();
            expect($r['status']==='complete' && $r['complete']===2 && $v->calls[1]===[1,true]);
        },
        'night ending leaves unfinished articles for next daily snapshot'=>function(){
            [$s,$v,$b]=nightFixture(3);$v->now=strtotime('2026-10-22T05:59:30Z');$v->worker=function($c)use($v){$v->now+=40;return ($v->success)($c);};
            $r=$b->run();expect($r['status']==='pending' && $r['cursor']===1);expect($b->run()['status']==='scheduled');
            $v->now=strtotime('2026-10-23T00:00:00Z');$v->worker=null;$r=$b->run();expect($r['status']==='complete' && $r['total']===2 && count($v->calls)===3);
        },
        'repeated DST hour does not start a second daily queue'=>function(){
            [$s,$v,$b]=nightFixture(1);$v->now=strtotime('2026-10-25T00:15:00Z');$b->run();$v->now=strtotime('2026-10-25T01:15:00Z');$r=$b->run();
            expect($r['status']==='complete' && $v->candidate_calls===1 && count($v->calls)===1);
        },
        'blocked articles are retained and do not exhaust processing package'=>function(){
            [$s,$v,$b]=nightFixture(130);for($i=1;$i<=100;$i++){$s->put('job:'.$i,['status'=>$i%2?'blocked':'review_required']);}
            $r=$b->run();expect($r['cursor']===125 && $r['skipped']===100 && count($v->calls)===25);expect($s->get('job:1')['status']==='blocked');
            $v->now+=900;$r=$b->run();expect($r['status']==='complete' && $r['complete']===30);
        },
        'article validation failure does not stop subsequent articles'=>function(){
            [$s,$v,$b]=nightFixture();$v->worker=function($c)use($s,$v){if($c['item_id']===2){$s->put('job:2',['status'=>'blocked']);throw new RuntimeException('Zahlen verändert.');}return ($v->success)($c);};
            $r=$b->run();expect($r['status']==='complete' && $r['complete']===2 && $r['failed']===1);expect(array_column($v->calls,0)===[1,2,3]);expect($s->get('job:2')['status']==='blocked');
        },
        'review-required worker does not prevent remaining articles'=>function(){
            [$s,$v,$b]=nightFixture();$v->worker=function($c)use($v){return $c['item_id']===2?['item_id'=>2,'status'=>'review_required']:($v->success)($c);};
            $r=$b->run();expect($r['review_required']===1 && $r['complete']===2 && $r['status']==='complete');
        },
        'shared API outage pauses one article without blocking the queue'=>function(){
            [$s,$v,$b]=nightFixture();$v->worker=function(){throw new ProcessingPause('service','HTTP 429');};$r=$b->run();
            expect($r['status']==='service_paused' && $r['cursor']===0 && count($v->calls)===1);expect($s->get('job:2')===null);$v->worker=null;$v->now+=900;
            $r=$b->run();expect($r['complete']===3 && $v->calls[1][1]===true);
        },
        'budget pause avoids further requests until the next budget month'=>function(){
            [$s,$v,$b]=nightFixture();$v->worker=function(){throw new ProcessingPause('budget','Limit');};$r=$b->run();expect($r['status']==='budget_paused');$setup=$v->settings_calls;
            $v->now+=900;$b->run();expect(count($v->calls)===1 && $v->settings_calls===$setup);$v->now=strtotime('2026-11-01T01:00:00Z');$v->worker=null;$r=$b->run();expect($r['status']==='complete');
        },
        'preflight outage never begins article processing'=>function(){
            [$s,$v,$b]=nightFixture();$v->setup_error=new ProcessingPause('service','Unavailable');$r=$b->run();
            expect($r['status']==='service_paused' && !$v->calls && $v->candidate_calls===0);expect($s->get('nightly-last-issue')['day']==='2026-10-22');expect($v->deadlines[count($v->deadlines)-1]===0);
        },
        'policy changes pause an active snapshot instead of replacing it'=>function(){
            [$s,$v,$b]=nightFixture(30);$r=$b->run();$v->now+=900;$v->policy=['version'=>3];$r=$b->run();
            expect($r['status']==='policy_changed' && $r['cursor']===25 && count($v->calls)===25 && $v->candidate_calls===1);
            $v->policy=['version'=>2];expect($b->run()['status']==='complete');
        },
        'corrupt or stale queue page never reaches a worker'=>function(){
            [$s,$v,$b]=nightFixture(30);$b->run();$page=$s->get('nightly-queue:0');$page['day']='2026-10-21';$s->put('nightly-queue:0',$page);$v->now+=900;$r=$b->run();
            expect($r['status']==='queue_invalid' && count($v->calls)===25);
        },
        'removed trigger is reported as cancelled without stalling following items'=>function(){
            [$s,$v,$b]=nightFixture();$v->worker=function($c)use($v){return $c['item_id']===2?['item_id'=>2,'status'=>'cancelled']:($v->success)($c);};
            $r=$b->run();expect($r['cancelled']===1 && $r['complete']===2 && $r['cursor']===3);
        },
        'durable in-flight marker identifies recovery after a process interruption'=>function(){
            [$s,$v,$b]=nightFixture(30);$b->run();$run=$s->get('nightly-run');$run['in_flight']=26;$s->put('nightly-run',$run);$v->now+=900;
            $r=$b->run();expect($v->calls[25]===[26,true] && $r['status']==='complete');
        },
        'time checkpoint preserves already paid engine translations for resume'=>function(){
            [$s,$g,$e,$c,$settings]=fixture();$checks=0;$e=new TranslationEngine($s,$g,$settings,function()use(&$checks){if(++$checks===4){throw new ProcessingPause('time','Yield');}});
            raises(function()use($e,$c){$e->run($c,true);});expect($s->get('job:42')['status']==='paused' && $g->calls===3 && !$g->writes);
            $e=new TranslationEngine($s,$g,$settings);expect($e->run($c,true)['status']==='complete');expect($g->calls===20);
        },
        'monthly limit pauses an engine job without permanently blocking it'=>function(){
            [$s,$g,$e,$c,$settings]=fixture();$settings['monthly_characters']=1;$e=new TranslationEngine($s,$g,$settings);raises(function()use($e,$c){$e->run($c,true);});
            expect($s->get('job:42')['status']==='paused' && $g->calls===0);$settings['monthly_characters']=250000;$e=new TranslationEngine($s,$g,$settings);expect($e->run($c,true)['status']==='complete');
        },
        'HTTP deadline yields before credentials or library access'=>function(){
            $h=new ConfigurationHttpFixture(configValues());$h->setDeadline(time()-1);$caught=false;
            try{$h->checkDeadline();}catch(ProcessingPause $e){$caught=$e->reason()==='time';}expect($caught && !$h->requests);$h->setDeadline(0);$h->checkDeadline();
        },
        'unexpected worker status is isolated as an article failure'=>function(){
            [$s,$v,$b]=nightFixture();$v->worker=function($c)use($v){return $c['item_id']===2?['status'=>'prepared']:($v->success)($c);};$r=$b->run();expect($r['failed']===1 && $r['complete']===2);
        }
    ];
}
