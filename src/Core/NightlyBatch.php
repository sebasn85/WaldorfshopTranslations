<?php
namespace WaldorfshopTranslations\Core;

/** The caller holds the existing global lock. Queue snapshots contain no secrets. */
class NightlyBatch
{
    const START_HOUR = 2;
    const END_HOUR = 8;
    const MAX_ITEMS = 25;
    const MAX_SECONDS = 300;
    const QUEUE_PAGE_SIZE = 100;
    private $store;
    private $settings;
    private $candidates;
    private $worker;
    private $deadline;
    private $clock;

    public function __construct($store, callable $settings, callable $candidates, callable $worker, callable $deadline, callable $clock = null)
    {
        $this->store = $store;
        $this->settings = $settings;
        $this->candidates = $candidates;
        $this->worker = $worker;
        $this->deadline = $deadline;
        $this->clock = $clock ?? function () { return time(); };
    }
    private static function localTime(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('Europe/Berlin'));
    }
    public static function inWindow(int $timestamp): bool
    {
        $hour = (int)self::localTime($timestamp)->format('G');
        return $hour >= self::START_HOUR && $hour < self::END_HOUR;
    }
    private function now(): int { return ($this->clock)(); }
    private function save(array $run): array
    {
        $run['updated_at'] = gmdate('c', $this->now());
        $this->store->put('nightly-run', $run);
        return $run;
    }
    private function pause(array $run, ProcessingPause $error): array
    {
        $run['status'] = $error->reason() === 'time' ? 'pending' : $error->reason() . '_paused';
        $run['last_issue'] = ['reason'=>$error->reason(), 'message'=>$error->getMessage()];
        if ($error->reason() === 'budget') { $run['budget_month'] = gmdate('Y-m', $this->now()); }
        return $this->save($run);
    }
    public function run(): array
    {
        $started = $this->now();
        if (!self::inWindow($started)) { return ['status'=>'scheduled', 'timezone'=>'Europe/Berlin', 'start_hour'=>self::START_HOUR]; }
        $day = self::localTime($started)->format('Y-m-d');
        $run = $this->store->get('nightly-run');
        if (is_array($run) && $run['day'] === $day && $run['status'] === 'complete') { return $run; }
        if (is_array($run) && $run['day'] === $day && $run['status'] === 'budget_paused' && ($run['budget_month'] ?? '') === gmdate('Y-m', $started)) { return $run; }
        $nightEnd = self::localTime($started)->setTime(self::END_HOUR, 0)->getTimestamp();
        ($this->deadline)(min($started + self::MAX_SECONDS, $nightEnd));
        try {
            try {
                $settings = ($this->settings)();
                $policy = TranslationEngine::hash($settings['policy']);
                if (is_array($run) && $run['day'] === $day && $run['policy_hash'] !== $policy) {
                    $run['status'] = 'policy_changed';
                    $run['last_issue'] = ['reason'=>'policy', 'message'=>'Einstellungen während Nachtlauf geändert; vor Fortsetzung prüfen.'];
                    return $this->save($run);
                }
                if (!is_array($run) || $run['day'] !== $day) {
                    $queue = ($this->candidates)();
                    usort($queue, function ($a, $b) { return $a['item_id'] <=> $b['item_id']; });
                    // Fixed page keys bound row size; day/policy guards detect incomplete snapshots.
                    foreach (array_chunk($queue, self::QUEUE_PAGE_SIZE) as $page => $entries) {
                        $this->store->put('nightly-queue:' . $page, ['day'=>$day, 'policy_hash'=>$policy, 'entries'=>$entries]);
                    }
                    $run = ['day'=>$day, 'policy_hash'=>$policy, 'status'=>'pending', 'total'=>count($queue), 'cursor'=>0, 'complete'=>0, 'failed'=>0, 'review_required'=>0, 'skipped'=>0, 'cancelled'=>0, 'in_flight'=>null, 'started_at'=>gmdate('c', $started), 'last_issue'=>null];
                    $run = $this->save($run);
                }
            } catch (ProcessingPause $error) {
                // No snapshot may exist yet (e.g. preflight/candidate scan unavailable).
                if (!is_array($run) || $run['day'] !== $day) {
                    $issue = ['status'=>$error->reason() . '_paused', 'day'=>$day, 'message'=>$error->getMessage()];
                    $this->store->put('nightly-last-issue', $issue);
                    return $issue;
                }
                return $this->pause($run, $error);
            }
            $attempts = 0;
            $pages = [];
            while ($run['cursor'] < $run['total']) {
                if ($attempts >= self::MAX_ITEMS || $this->now() >= $started + self::MAX_SECONDS || !self::inWindow($this->now())) {
                    $run['status'] = 'pending';
                    return $this->save($run);
                }
                $page = (int)floor($run['cursor'] / self::QUEUE_PAGE_SIZE);
                if (!isset($pages[$page])) { $pages[$page] = $this->store->get('nightly-queue:' . $page); }
                $snapshot = $pages[$page];
                $candidate = $snapshot['entries'][$run['cursor'] % self::QUEUE_PAGE_SIZE] ?? null;
                if (!is_array($snapshot) || $snapshot['day'] !== $day || $snapshot['policy_hash'] !== $run['policy_hash'] || !is_array($candidate)) {
                    $run['status'] = 'queue_invalid';
                    $run['last_issue'] = ['reason'=>'queue', 'message'=>'Gespeicherte Warteschlange unvollständig; administrativ prüfen.'];
                    return $this->save($run);
                }
                $job = $this->store->get('job:' . $candidate['item_id']);
                if ($job && in_array($job['status'], ['blocked', 'review_required'], true)) {
                    $run['skipped']++;
                    $run['cursor']++;
                    $run['in_flight'] = null;
                    $run = $this->save($run);
                    continue;
                }
                $recovering = $run['in_flight'] === $candidate['item_id'];
                $run['in_flight'] = $candidate['item_id'];
                $run['status'] = 'running';
                $run = $this->save($run);
                $attempts++;
                try {
                    $result = ($this->worker)($candidate, $settings, $recovering);
                    if (!in_array($result['status'] ?? '', ['complete', 'review_required', 'cancelled'], true)) { throw new \RuntimeException('Unerwartetes Verarbeitungsergebnis.'); }
                    $run[$result['status']]++;
                    $this->store->put('last-result', $result + ['at'=>gmdate('c', $this->now()), 'kind'=>'automatic']);
                } catch (ProcessingPause $error) {
                    // Keep cursor/in_flight: the next tick resumes this article from its paid cache.
                    return $this->pause($run, $error);
                } catch (\Throwable $error) {
                    $run['failed']++;
                    $run['last_issue'] = ['item_id'=>$candidate['item_id'], 'reason'=>'article', 'message'=>$error->getMessage()];
                }
                $run['cursor']++;
                $run['in_flight'] = null;
                $run = $this->save($run);
            }
            $run['status'] = 'complete';
            $run['finished_at'] = gmdate('c', $this->now());
            return $this->save($run);
        } finally {
            ($this->deadline)(0);
        }
    }
}
