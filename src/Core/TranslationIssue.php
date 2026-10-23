<?php
namespace WaldorfshopTranslations\Core;

/** Messages originate in this plugin, never from an HTTP client or SDK. */
class TranslationIssue extends \RuntimeException
{
    private $issueCode;
    public function __construct(string $code, string $message)
    { parent::__construct($message); $this->issueCode = $code; }
    public function issueCode(): string { return $this->issueCode; }
    public static function details(\Throwable $error): array
    {
        if ($error instanceof self) { return ['code'=>$error->issueCode(), 'message'=>$error->getMessage()]; }
        if ($error instanceof ProcessingPause) { return ['code'=>'pause_' . $error->reason(), 'message'=>$error->getMessage()]; }
        return ['code'=>'unexpected', 'message'=>'Unerwarteter interner Fehler; technische Prüfung erforderlich.', 'type'=>get_class($error)];
    }
}
