<?php
namespace WaldorfshopTranslations\Diagnostics;
use WaldorfshopTranslations\Models\StateEntry;
use WaldorfshopTranslations\Core\TranslationIssue;

/** Batched read-only database snapshot; missing keys never cause hidden queries. */
class SnapshotReviewStore
{
    private $database;
    private $values=[];
    public $queries=0;
    public function __construct($database) { $this->database=$database; }
    public function load(array $keys): void
    {
        $needed=[];
        foreach ($keys as $key) {
            if (!is_string($key) || $key==='') { throw new TranslationIssue('snapshot_key','Ungültiger Leseschlüssel.'); }
            if (!array_key_exists($key,$this->values)) { $needed[$key]=true; }
        }
        foreach (array_chunk(array_keys($needed),100) as $chunk) {
            foreach ($chunk as $key) { $this->values[$key]=null; }
            $rows=$this->database->query(StateEntry::class)->whereIn('stateKey',$chunk)->get(); $this->queries++;
            $seen=[];
            foreach ($rows as $row) {
                $key=is_array($row) ? $row['stateKey'] : $row->stateKey;
                $value=is_array($row) ? $row['payload'] : $row->payload;
                if (!in_array($key,$chunk,true) || isset($seen[$key]) || !is_array($value)) { throw new TranslationIssue('snapshot_row','Widersprüchlicher Lesestand.'); }
                $seen[$key]=true; $this->values[$key]=$value;
            }
        }
    }
    public function get(string $key) { return $this->values[$key] ?? null; }
}
