<?php
namespace WaldorfshopTranslations\Diagnostics;
use WaldorfshopTranslations\Repositories\StateRepository;

/** Writes remain in memory. Reads fall back to the existing real job/cache. */
class ReplayStore
{
    private $backing;
    private $memory = [];
    public function __construct(StateRepository $backing) { $this->backing = $backing; }
    public function get(string $key) { return array_key_exists($key, $this->memory) ? $this->memory[$key] : $this->backing->get($key); }
    public function put(string $key, array $value): void { $this->memory[$key] = $value; }
}
