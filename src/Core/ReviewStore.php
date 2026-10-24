<?php
namespace WaldorfshopTranslations\Core;

/** Read-through overlay. A preview never changes the backing repository. */
class ReviewStore
{
    private $backing;
    private $values = [];
    public function __construct($backing) { $this->backing = $backing; }
    public function get(string $key) { return array_key_exists($key, $this->values) ? $this->values[$key] : $this->backing->get($key); }
    public function put(string $key, array $value): void { $this->values[$key] = $value; }
}
