<?php
namespace WaldorfshopTranslations\Services;
use WaldorfshopTranslations\Core\TranslationEngine;

class Gateway
{
    private $http;
    public function __construct(HttpClient $http) { $this->http = $http; }
    private function rows($response): array
    {
        if (!is_array($response)) { throw new \RuntimeException('Unerwartete API-Liste.'); }
        $rows = isset($response['entries']) ? $response['entries'] : $response;
        if (!is_array($rows) || ($rows && array_keys($rows) !== range(0, count($rows) - 1))) { throw new \RuntimeException('Unerwartetes Listenformat.'); }
        foreach ($rows as $row) { if (!is_array($row)) { throw new \RuntimeException('Unerwarteter Datensatz.'); } }
        return $rows;
    }
    private function pages(string $path, array $query = []): array
    {
        $all = []; $seen = [];
        for ($page = 1; $page <= 100; $page++) {
            $r = $this->http->request('plenty', 'GET', $path, [], array_merge($query, ['page' => $page, 'itemsPerPage' => 100]));
            $rows = $this->rows($r); $hash = TranslationEngine::hash($rows);
            if ($rows && isset($seen[$hash])) { throw new \RuntimeException('Wiederholte API-Seite.'); }
            $seen[$hash] = true; $all = array_merge($all, $rows);
            if (!isset($r['entries']) || !$rows || ($r['isLastPage'] ?? false) === true || (isset($r['lastPageNumber']) && $page >= (int)$r['lastPageNumber'])) { return $all; }
            if (!isset($r['isLastPage']) && !isset($r['lastPageNumber'])) { throw new \RuntimeException('Pagination fehlt.'); }
        }
        throw new \RuntimeException('API-Seitenlimit erreicht.');
    }
    public function triggerIds(): array
    {
        $tag = $this->http->settings()['ready_tag_id'];
        $ids = [];
        foreach ($this->pages('/rest/tags/relationships', ['tagId' => $tag, 'tagType' => 'variation']) as $r) {
            $type = $r['tagType'] ?? ($r['type'] ?? ''); $value = $r['relationshipValue'] ?? ($r['value'] ?? 0);
            if (isset($r['type'], $r['tagType']) && $r['type'] !== $r['tagType']) { throw new \RuntimeException('Widersprüchliche Tag-Antwort.'); }
            if (isset($r['value'], $r['relationshipValue']) && (string)$r['value'] !== (string)$r['relationshipValue']) { throw new \RuntimeException('Widersprüchliche Tag-Antwort.'); }
            if ((int)($r['tagId'] ?? 0) !== $tag || $type !== 'variation' || (int)$value <= 0) { throw new \RuntimeException('Tag-Filter passt nicht.'); }
            $ids[(int)$value] = true;
        }
        return $ids;
    }
    public function candidates(): array
    {
        $tag = $this->http->settings()['ready_tag_id'];
        $tagged = $this->triggerIds(); $groups = [];
        if (!$tagged) { return []; }
        // The variation endpoint also returns inherited tags: intersect direct links.
        foreach ($this->pages('/rest/items/variations', ['variationTagId' => $tag]) as $r) {
            $id = (int)($r['id'] ?? 0); $item = (int)($r['itemId'] ?? 0);
            if (!isset($tagged[$id])) { continue; }
            if ($item <= 0) { throw new \RuntimeException('Variante ohne Artikel-ID.'); }
            if (!isset($groups[$item])) { $groups[$item] = ['item_id' => $item, 'variation_id' => $id, 'tagged_variation_ids' => [], 'ready_tag_id' => $tag]; }
            $groups[$item]['tagged_variation_ids'][] = $id;
        }
        ksort($groups); return array_values($groups);
    }
    public function texts(array $candidate): array
    {
        $path = '/rest/items/' . (int)$candidate['item_id'] . '/variations/' . (int)$candidate['variation_id'] . '/descriptions';
        $result = [];
        foreach ($this->pages($path) as $row) {
            $lang = strtolower((string)($row['lang'] ?? ''));
            if (!$lang || isset($result[$lang]) || (int)($row['itemId'] ?? 0) !== (int)$candidate['item_id']) { throw new \RuntimeException('Artikeltext-Zuordnung ungültig.'); }
            $result[$lang] = $row;
        }
        return $result;
    }
    public function writeTexts(array $candidate, string $lang, array $changes, bool $exists): void
    {
        if (!isset($this->http->settings()['targets'][$lang]) || array_diff(array_keys($changes), TranslationEngine::FIELDS)) { throw new \RuntimeException('Nicht freigegebene Textänderung.'); }
        $path = '/rest/items/' . (int)$candidate['item_id'] . '/variations/' . (int)$candidate['variation_id'] . '/descriptions' . ($exists ? '/' . $lang : '');
        $this->http->request('plenty', $exists ? 'PUT' : 'POST', $path, array_merge(['itemId' => $candidate['item_id'], 'lang' => $lang], $changes));
    }
    private function hasTag(int $variation, int $tag): bool
    {
        foreach ($this->pages('/rest/tags/relationships', ['tagId' => $tag, 'tagType' => 'variation']) as $r) {
            if ((int)($r['tagId'] ?? 0) !== $tag || ($r['tagType'] ?? ($r['type'] ?? '')) !== 'variation') { throw new \RuntimeException('Tag-Prüfung ungültig.'); }
            if ((int)($r['relationshipValue'] ?? ($r['value'] ?? 0)) === $variation) { return true; }
        }
        return false;
    }
    public function hasAnyTrigger(array $candidate): bool
    {
        foreach ($candidate['tagged_variation_ids'] as $id) { if ($this->hasTag($id, $this->http->settings()['ready_tag_id'])) { return true; } }
        return false;
    }
    public function addReviewTag(int $variation): void
    {
        $tag = $this->http->settings()['review_tag_id'];
        if (!$this->hasTag($variation, $tag)) { $this->http->request('plenty', 'POST', '/rest/tags/relationships', ['tagId' => $tag, 'tagType' => 'variation', 'relationshipValue' => $variation]); }
        if (!$this->hasTag($variation, $tag)) { throw new \RuntimeException('Prüftag nicht gespeichert.'); }
    }
    public function removeTrigger(int $variation): void
    {
        $tag = $this->http->settings()['ready_tag_id'];
        if ($this->hasTag($variation, $tag)) { $this->http->request('plenty', 'DELETE', '/rest/tags/relationships/' . $variation . '/variation/' . $tag); }
        if ($this->hasTag($variation, $tag)) { throw new \RuntimeException('Auslöser-Tag nicht entfernt.'); }
    }
    public function preflight(array $settings): array
    {
        // Read-only checks complete before any paid translation or article/tag mutation.
        foreach ([$settings['ready_tag_id'], $settings['review_tag_id']] as $id) {
            $tag = $this->http->request('plenty', 'GET', '/rest/tags/' . $id, [], ['with'=>'availabilities']);
            $variation = false;
            foreach (($tag['availabilities'] ?? []) as $availability) {
                if ((int)($availability['tagId'] ?? 0) === $id && ($availability['tagType'] ?? ($availability['type'] ?? '')) === 'variation') { $variation = true; }
            }
            if ((int)($tag['id'] ?? 0) !== $id || !$variation) { throw new \RuntimeException('Tag fehlt oder ist nicht für Varianten freigegeben: ' . $id); }
        }
        $languages = $this->http->request('deepl', 'GET', '/v3/languages', [], ['resource'=>'translate_text']);
        $byCode = [];
        foreach ($this->rows($languages) as $language) { $byCode[strtoupper($language['lang'] ?? '')] = $language; }
        if (empty($byCode['DE']['usable_as_source'])) { throw new \RuntimeException('DeepL unterstützt Deutsch nicht als Quelle.'); }
        $hashes = []; $features = [];
        foreach ($settings['targets'] as $lang => $code) {
            $target = $byCode[$code] ?? [];
            if (empty($target['usable_as_target']) || !isset($target['features']['tag_handling'], $byCode['DE']['features']['tag_handling'])) { throw new \RuntimeException('Zielsprache oder HTML-Übersetzung bei DeepL nicht verfügbar: ' . $lang); }
            $features[$lang] = isset($target['features']['formality']);
            if ($settings['policy']['glossary_id'] !== '') {
                if (!isset($target['features']['glossary'], $byCode['DE']['features']['glossary'])) { throw new \RuntimeException('Glossar für Zielsprache nicht verfügbar: ' . $lang); }
                $base = strtolower(explode('-', $code)[0]);
                $response = $this->http->request('deepl', 'GET', '/v3/glossaries/' . $settings['policy']['glossary_id'] . '/entries', [], ['source_lang'=>'de','target_lang'=>$base]);
                $matches = [];
                foreach (($response['dictionaries'] ?? []) as $d) {
                    if (strtolower($d['source_lang'] ?? '') === 'de' && strtolower($d['target_lang'] ?? '') === $base) { $matches[] = $d; }
                }
                if (count($matches) !== 1 || trim($matches[0]['entries'] ?? '') === '') { throw new \RuntimeException('Glossar enthält kein eindeutiges Wörterbuch DE nach ' . $base); }
                $hashes[$lang] = TranslationEngine::hash($this->pairs($matches[0]['entries']));
            }
        }
        $settings['policy']['glossary_hash'] = TranslationEngine::hash($hashes);
        $settings['policy']['context_hash'] = TranslationEngine::hash($settings['context']);
        $settings['formality'] = $features;
        return $settings;
    }
    // Compatibility hook: runtime preflight has already verified the selected dictionaries.
    public function verifyGlossary(): void {}
    private function pairs(string $tsv): array
    {
        $pairs = [];
        foreach (explode("\n", trim($tsv)) as $line) {
            $pair = explode("\t", rtrim($line, "\r"));
            if (count($pair) !== 2 || isset($pairs[$pair[0]])) { throw new \RuntimeException('Ungültiges Glossar.'); }
            $pairs[$pair[0]] = $pair[1];
        }
        ksort($pairs); return $pairs;
    }
    public function translate(string $source, array $options): string
    {
        $response = $this->http->request('deepl', 'POST', '/v2/translate', array_merge(['text' => [$source]], $options));
        if (count($response['translations'] ?? []) !== 1 || !is_string($response['translations'][0]['text'] ?? null)) { throw new \RuntimeException('Ungültige DeepL-Antwort.'); }
        return $response['translations'][0]['text'];
    }
}
