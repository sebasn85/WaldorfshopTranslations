<?php
namespace WaldorfshopTranslations\Core;

/** A temporary stop preserves the paid cache and permits a controlled resume. */
class ProcessingPause extends \RuntimeException
{
    private $reason;
    public function __construct(string $reason, string $message)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }
    public function reason(): string { return $this->reason; }
}
