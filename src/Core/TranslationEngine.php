<?php
namespace WaldorfshopTranslations\Core;

/** Pure worker logic; platform, HTTP and persistence are injected adapters. */
class TranslationEngine
{
    const FIELDS = ['name', 'name2', 'name3', 'title', 'previewDescription', 'description', 'technicalData', 'metaDescription', 'metaKeywords'];
    const HTML_FIELDS = ['previewDescription', 'description', 'technicalData'];
    const TITLE_FIELDS = ['name', 'name2', 'name3', 'title'];
    const TARGETS = ['en' => 'EN-GB', 'fr' => 'FR', 'it' => 'IT', 'es' => 'ES', 'nl' => 'NL'];
    private $store;
    private $gateway;
    private $settings;
    private $targets;

    public function __construct($store, $gateway, array $settings)
    {
        $this->store = $store;
        $this->gateway = $gateway;
        $this->settings = $settings;
        $this->targets = $settings['targets'] ?? self::TARGETS;
        if (!$this->targets) { throw new \RuntimeException('Mindestens eine Zielsprache auswählen.'); }
    }

    public static function hash($value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function blank(string $text): bool
    {
        self::checkMarkup($text);
        return trim(str_replace("\xc2\xa0", ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) === '' && !preg_match('/<(img|svg|video|audio)\b/i', $text);
    }

    public static function checkMarkup(string $text): void
    {
        if (preg_match('/<\s*(script|iframe|object|embed|form|style)\b|\bon[a-z]+\s*=|(?:javascript|vbscript)\s*:/i', $text)) {
            throw new \RuntimeException('Aktives HTML: Artikel manuell prüfen.');
        }
    }

    public static function validate(string $source, string $target, string $field): void
    {
        if (self::blank($target)) {
            throw new \RuntimeException('Leere Übersetzung.');
        }
        self::checkMarkup($source);
        if (in_array($field, self::HTML_FIELDS, true)) {
            // Deliberately strict: every original tag/attribute must remain exact.
            preg_match_all('/<[^>]*>/u', $source, $a);
            preg_match_all('/<[^>]*>/u', $target, $b);
            if ($a[0] !== $b[0]) {
                throw new \RuntimeException('HTML-Struktur oder Attribute verändert.');
            }
        } elseif ($target !== strip_tags($target)) {
            throw new \RuntimeException('HTML in Klartextfeld.');
        }
        // HTML entities such as French apostrophe &#x27; are characters, not numbers.
        $sourceText = html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $targetText = html_entity_decode(strip_tags($target), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/\d+(?:[.,]\d+)*/u', $sourceText, $a);
        preg_match_all('/\d+(?:[.,]\d+)*/u', $targetText, $b);
        $x = array_map(function ($n) { return preg_replace('/\D/', '', $n); }, $a[0]);
        $y = array_map(function ($n) { return preg_replace('/\D/', '', $n); }, $b[0]);
        sort($x); sort($y);
        if ($x !== $y) {
            throw new \RuntimeException('Zahlen verändert.');
        }
        if (in_array($field, self::TITLE_FIELDS, true) && self::characters($target) > 240) {
            throw new \RuntimeException('Artikelname oder HTML-Titel über 240 Zeichen.');
        }
    }

    public static function characters(string $text): int
    {
        if (!preg_match_all('/./us', $text, $matches)) {
            return 0;
        }
        return count($matches[0]);
    }

    private function selected(array $row): array
    {
        $result = [];
        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? '';
            if (!is_string($value)) {
                throw new \RuntimeException('Ungültiges Textformat.');
            }
            $result[$field] = $value;
        }
        return $result;
    }

    public function plan(array $candidate): array
    {
        $rows = $this->gateway->texts($candidate);
        $source = $this->selected($rows['de'] ?? []);
        if (self::blank($source['name'])) {
            throw new \RuntimeException('Deutscher Artikelname fehlt.');
        }
        $baseline = $this->store->get('baseline:' . $candidate['item_id']) ?? [];
        $targets = []; $conflicts = [];
        foreach ($this->targets as $lang => $code) {
            $before = $this->selected($rows[$lang] ?? []);
            $needed = []; $reasons = [];
            foreach (self::FIELDS as $field) {
                $old = $baseline[$lang][$field] ?? null;
                if (self::blank($source[$field])) {
                    $reasons[$field] = 'source_empty';
                    if (!self::blank($before[$field])) {
                        $conflicts[] = $lang . ':' . $field . ':source_removed';
                    }
                } elseif (self::blank($before[$field])) {
                    $needed[$field] = $source[$field]; $reasons[$field] = 'missing';
                } elseif ($old === null) {
                    $needed[$field] = $source[$field]; $reasons[$field] = 'untracked';
                } elseif ($old['source'] !== $source[$field]) {
                    $needed[$field] = $source[$field]; $reasons[$field] = 'source_changed';
                    if ($old['target'] !== $before[$field]) {
                        $conflicts[] = $lang . ':' . $field . ':source_and_target_changed';
                    }
                } else {
                    $reasons[$field] = $old['target'] === $before[$field] ? 'unchanged' : 'target_edited';
                }
            }
            $targets[$lang] = ['before' => $before, 'needed' => $needed, 'reasons' => $reasons, 'changes' => [], 'exists' => isset($rows[$lang])];
        }
        return ['candidate' => $candidate, 'source' => $source, 'source_hash' => self::hash($source), 'targets' => $targets, 'conflicts' => $conflicts, 'policy' => $this->settings['policy'], 'status' => 'preparing', 'write_started' => false];
    }

    private function reserve(int $itemId, int $characters): void
    {
        $month = gmdate('Y-m');
        $budget = $this->store->get('budget:' . $month) ?? ['characters' => 0, 'items' => []];
        if ($month === $this->settings['seed_month'] && $budget['characters'] === 0 && !$budget['items']) {
            $budget = ['characters' => $this->settings['seed_characters'], 'items' => $this->settings['seed_items']];
        }
        $items = $budget['items'];
        if (!in_array($itemId, $items, true)) { $items[] = $itemId; }
        if (count($items) > $this->settings['monthly_items'] || $budget['characters'] + $characters > $this->settings['monthly_characters']) {
            throw new \RuntimeException('Monatslimit erreicht.');
        }
        $budget['items'] = $items;
        $budget['characters'] += $characters;
        $this->store->put('budget:' . $month, $budget);
    }

    /** Caller holds the persistent global lock throughout. */
    public function run(array $candidate, bool $apply): array
    {
        $key = 'job:' . $candidate['item_id'];
        $fresh = $this->plan($candidate);
        $job = $this->store->get($key);
        if ($job && $job['status'] !== 'complete') {
            if ($job['source_hash'] !== $fresh['source_hash'] || $job['policy'] !== $fresh['policy']) {
                if ($job['write_started']) {
                    throw new \RuntimeException('Quelle oder Glossar nach Teilübernahme geändert; manuell klären.');
                }
                $job = null;
            } elseif (!$job['write_started']) {
                foreach ($this->targets as $lang => $code) {
                    if ($job['targets'][$lang]['before'] !== $fresh['targets'][$lang]['before']) { $job = null; break; }
                }
            }
        } else { $job = null; }
        if ($job === null) { $job = $fresh; }
        $candidate = $job['candidate']; // Keep the original variant set during partial-write recovery.
        if ($job['conflicts']) {
            $job['status'] = 'review_required'; $this->store->put($key, $job);
            return ['item_id' => $candidate['item_id'], 'status' => 'review_required', 'conflicts' => $job['conflicts']];
        }
        try {
            $this->gateway->verifyGlossary();
            foreach ($this->targets as $lang => $code) {
                foreach ($job['targets'][$lang]['needed'] as $field => $source) {
                    if (isset($job['targets'][$lang]['changes'][$field])) { continue; }
                    $options = ['source_lang' => 'DE', 'target_lang' => $code, 'preserve_formatting' => true, 'context' => $this->settings['context'] . "\n" . $job['source']['name']];
                    if (!empty($job['policy']['glossary_id'])) { $options['glossary_id'] = $job['policy']['glossary_id']; }
                    if (!empty($this->settings['formality'][$lang])) { $options['formality'] = 'prefer_less'; }
                    if (in_array($field, self::HTML_FIELDS, true)) {
                        $options['tag_handling'] = 'html'; $options['tag_handling_version'] = 'v2';
                    }
                    $cacheKey = 'cache:' . self::hash([$source, $options, $field, $job['policy']]);
                    $cached = $this->store->get($cacheKey);
                    if ($cached === null) {
                        $this->reserve($candidate['item_id'], self::characters($source));
                        $translated = $this->gateway->translate($source, $options);
                        // Retain paid provider output even when validation rejects it.
                        // Every cached response is validated before it can be applied.
                        $cached = ['text' => $translated]; $this->store->put($cacheKey, $cached);
                    }
                    try {
                        self::validate($source, $cached['text'], $field);
                    } catch (\Throwable $validationError) {
                        $job['validation_error'] = ['language' => $lang, 'field' => $field, 'source' => $source, 'target' => $cached['text'], 'message' => $validationError->getMessage()];
                        $this->store->put($key, $job);
                        throw $validationError;
                    }
                    unset($job['validation_error']);
                    $job['targets'][$lang]['changes'][$field] = $cached['text'];
                    $this->store->put($key, $job);
                }
            }
            $job['status'] = 'prepared'; $this->store->put($key, $job);
            if (!$apply) { return ['item_id' => $candidate['item_id'], 'status' => 'prepared']; }
            $taggingStarted = !empty($job['tagging_started']);
            if (!$taggingStarted && !$this->gateway->hasAnyTrigger($candidate)) {
                throw new \RuntimeException('Auslöser-Tag entfernt.');
            }
            foreach ($this->targets as $lang => $code) {
                $rows = $this->gateway->texts($candidate);
                if (self::hash($this->selected($rows['de'] ?? [])) !== $job['source_hash']) {
                    throw new \RuntimeException('Deutscher Text während Verarbeitung geändert.');
                }
                $current = $this->selected($rows[$lang] ?? []); $changes = [];
                foreach ($job['targets'][$lang]['changes'] as $field => $value) {
                    if ($current[$field] === $value) { continue; }
                    if ($current[$field] !== $job['targets'][$lang]['before'][$field]) {
                        throw new \RuntimeException('Zieltext während Verarbeitung geändert.');
                    }
                    $changes[$field] = $value;
                }
                if ($changes) {
                    $job['write_started'] = true; $job['status'] = 'applying'; $this->store->put($key, $job);
                    if (!isset($rows[$lang]) && !isset($changes['name'])) { throw new \RuntimeException('Neue Sprache ohne Artikelname.'); }
                    $this->gateway->writeTexts($candidate, $lang, $changes, isset($rows[$lang]));
                }
            }
            $rows = $this->gateway->texts($candidate);
            if (self::hash($this->selected($rows['de'] ?? [])) !== $job['source_hash']) { throw new \RuntimeException('Quelle vor Rückleseprüfung geändert.'); }
            foreach ($this->targets as $lang => $code) {
                $current = $this->selected($rows[$lang] ?? []);
                foreach ($job['targets'][$lang]['changes'] as $field => $value) {
                    if ($current[$field] !== $value) { throw new \RuntimeException('Rückleseprüfung fehlgeschlagen.'); }
                }
                foreach ($job['source'] as $field => $source) {
                    if (!self::blank($source) && self::blank($current[$field])) { throw new \RuntimeException('Übersetzung unvollständig.'); }
                }
            }
            $baseline = $this->store->get('baseline:' . $candidate['item_id']) ?? [];
            foreach ($this->targets as $lang => $code) {
                foreach ($job['targets'][$lang]['changes'] as $field => $value) {
                    $baseline[$lang][$field] = ['source' => $job['source'][$field], 'target' => $value];
                }
            }
            $this->store->put('baseline:' . $candidate['item_id'], $baseline);
            $job['tagging_started'] = true; $job['status'] = 'tagging'; $this->store->put($key, $job);
            foreach ($candidate['tagged_variation_ids'] as $id) { $this->gateway->addReviewTag($id); }
            foreach ($candidate['tagged_variation_ids'] as $id) { $this->gateway->removeTrigger($id); }
            $job['status'] = 'complete'; $this->store->put($key, $job);
            return ['item_id' => $candidate['item_id'], 'status' => 'complete'];
        } catch (\Throwable $error) {
            $job['status'] = 'blocked'; $job['error'] = 'Verarbeitung fehlgeschlagen; Fehlercode ' . get_class($error);
            $this->store->put($key, $job);
            throw $error;
        }
    }
}
