<?php
namespace WaldorfshopTranslations\Repositories;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use WaldorfshopTranslations\Models\StateEntry;

class StateRepository
{
    private $db;
    private $lock;
    public function __construct(DataBase $db) { $this->db = $db; }
    private function entry(string $key)
    {
        $rows = $this->db->query(StateEntry::class)->where('stateKey', '=', $key)->get();
        if (count($rows) > 1) { throw new \RuntimeException('Doppelter Plugin-Zustand.'); }
        return count($rows) ? $rows[0] : null;
    }
    public function get(string $key)
    {
        $entry = $this->entry($key);
        if (!$entry) { return null; }
        return is_array($entry) ? $entry['payload'] : $entry->payload;
    }
    public function put(string $key, array $payload): void
    {
        $entry = $this->entry($key);
        if (is_array($entry)) { $entry = $this->db->find(StateEntry::class, $entry['id']); }
        if (!$entry) { $entry = pluginApp(StateEntry::class); $entry->stateKey = $key; }
        $entry->payload = $payload;
        $this->db->save($entry);
    }
    public function acquire(): void
    {
        // New model + unique index is an atomic claim, never an upsert of a lock.
        if ($this->entry('global-lock')) { throw new \RuntimeException('Übersetzungslauf gesperrt oder bereits aktiv.'); }
        $entry = pluginApp(StateEntry::class);
        $entry->stateKey = 'global-lock'; $entry->payload = ['started' => gmdate('c')];
        $this->lock = $this->db->save($entry);
    }
    public function release(): void
    {
        if ($this->lock) { $this->db->delete($this->lock); $this->lock = null; }
    }
}
