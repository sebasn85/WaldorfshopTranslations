<?php
namespace WaldorfshopTranslations\Core;

/** A temporary stop preserves the paid cache and permits a controlled resume. */
class ProcessingPause extends \RuntimeException
{
    private $reason;
    private $confirmedNoCharge;
    public function __construct(string $reason, string $message, bool $confirmedNoCharge = false)
    {
        parent::__construct($message);
        $this->reason = $reason;
        $this->confirmedNoCharge = $confirmedNoCharge;
    }
    public function confirmedNoCharge(): bool { return $this->confirmedNoCharge; }
    public function reason(): string { return $this->reason; }
}
