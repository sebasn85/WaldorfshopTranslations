<?php
namespace WaldorfshopTranslations\Migrations;
use WaldorfshopTranslations\Core\TranslationEngine;
use WaldorfshopTranslations\Core\TranslationIssue;

/** SDK smoke check: no database, API, credentials, tags or article changes. */
class CheckRecoveryRuntime
{
    public function run(): void
    {
        TranslationEngine::validate('<p>90 x 90 cm</p>', '<p>90 x 90 cm</p>', 'description');
        TranslationEngine::validate('<p class="a" title="Text">90 cm<br></p>', "<p title='Text' class='a'>90 cm<br/></p>", 'description');
        $blocked = false;
        try { TranslationEngine::validate('90 cm', '91 cm', 'name'); }
        catch (TranslationIssue $error) { $blocked = $error->issueCode() === 'numbers_changed'; }
        if (!$blocked) { throw new \RuntimeException('Zahlenprüfung im SDK fehlgeschlagen.'); }
        try { throw new TranslationIssue('probe', 'Prüfung'); }
        catch (TranslationIssue $error) { $issue = TranslationIssue::details($error); }
        if (($issue['code'] ?? '') !== 'probe') { throw new \RuntimeException('Fehlerdiagnose im SDK fehlgeschlagen.'); }
    }
}
