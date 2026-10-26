<?php
class SnapshotDatabaseProbe {
    public $calls=0; public $rows=[]; public $keys=[];
    public function query($model) { return $this; }
    public function whereIn($field,$keys) { $this->keys=$keys; return $this; }
    public function get() { $this->calls++; return array_values(array_filter($this->rows,function($row){return in_array($row['stateKey'],$this->keys,true);})); }
}
function snapshotTests(): array {
    return [
        'bulk review reads are bounded and absent cached fields never issue extra database calls'=>function(){
            $db=new SnapshotDatabaseProbe(); $db->rows=[['stateKey'=>'cache:42','payload'=>['text'=>'Existing answer']]];
            $s=new \WaldorfshopTranslations\Diagnostics\SnapshotReviewStore($db); $keys=[]; for($i=0;$i<205;$i++){$keys[]='cache:'.$i;}
            $s->load($keys); expect($db->calls===3 && $s->get('cache:42')['text']==='Existing answer');
            expect($s->get('cache:missing')===null); $s->load($keys); expect($db->calls===3 && !method_exists($s,'put'));
        },
        'review overlay cannot alter a loaded database snapshot'=>function(){
            $db=new SnapshotDatabaseProbe(); $db->rows=[['stateKey'=>'job:42','payload'=>['status'=>'blocked']]];
            $s=new \WaldorfshopTranslations\Diagnostics\SnapshotReviewStore($db); $s->load(['job:42']);
            $overlay=new \WaldorfshopTranslations\Core\ReviewStore($s); $overlay->put('job:42',['status'=>'complete']);
            expect($s->get('job:42')['status']==='blocked' && $db->rows[0]['payload']['status']==='blocked' && $db->calls===1);
        },
        'ambiguous duplicate review records stop the read-only inspection'=>function(){
            $db=new SnapshotDatabaseProbe(); $row=['stateKey'=>'job:42','payload'=>['status'=>'blocked']]; $db->rows=[$row,$row];
            $s=new \WaldorfshopTranslations\Diagnostics\SnapshotReviewStore($db); raises(function()use($s){$s->load(['job:42']);});
        }
    ];
}
