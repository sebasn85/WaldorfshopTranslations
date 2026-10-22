<?php
namespace WaldorfshopTranslations\Migrations;
use WaldorfshopTranslations\Core\NightlyBatch;

/** Pure SDK smoke check: no API calls, article writes or persisted state. */
class CheckNightlyRuntime
{
    public function run(): void
    {
        $probe = function () { return true; };
        if (!$probe->__invoke() || !NightlyBatch::inWindow(1782864000) || !NightlyBatch::inWindow(1796086800) || NightlyBatch::inWindow(1782885600)) {
            throw new \RuntimeException('Nachtlauf-Laufzeitprüfung fehlgeschlagen.');
        }
    }
}
