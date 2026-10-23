<?php
namespace WaldorfshopTranslations\Diagnostics;
use WaldorfshopTranslations\Core\TranslationIssue;

/** No transport or real repository dependency: article/tag writes are simulated. */
class ReplayGateway
{
    public $rows;
    public $writes = 0;
    public $paidRequests = 0;
    private $tags;
    public function __construct(array $rows, array $tags) { $this->rows=$rows; $this->tags=$tags; }
    public function texts(array $candidate): array { return $this->rows; }
    public function verifyGlossary(): void {}
    public function checkTranslationBudget(string $source): void { throw new TranslationIssue('replay_missing_cache', 'Für vollständige Simulation fehlen gespeicherte Antworten.'); }
    public function translateWithUsage(string $source, array $options): array { throw new TranslationIssue('replay_network_denied', 'Netzwerkzugriff in Simulation ausgeschlossen.'); }
    public function hasAnyTrigger(array $candidate): bool { return (bool)$this->tags; }
    public function writeTexts(array $candidate, string $lang, array $changes, bool $exists): void { $this->rows[$lang]=array_merge($this->rows[$lang] ?? [],$changes); $this->writes++; }
    public function addReviewTag(int $id): void {}
    public function removeTrigger(int $id): void { unset($this->tags[$id]); }
}
