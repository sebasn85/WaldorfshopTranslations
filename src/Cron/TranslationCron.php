<?php
namespace WaldorfshopTranslations\Cron;
use Plenty\Modules\Cron\Contracts\CronHandler;
use WaldorfshopTranslations\Services\Runner;
use Plenty\Plugin\Log\Loggable;
use WaldorfshopTranslations\Core\TranslationIssue;

class TranslationCron extends CronHandler
{
    use Loggable;
    public function handle()
    {
        try {
            $run = pluginApp(Runner::class)->automatic();
            if (in_array($run['status'] ?? '', ['scheduled','disabled'], true)) { return; }
            $summary = [];
            foreach (['status','day','total','cursor','complete','failed','review_required','skipped','cancelled','in_flight','last_issue','code','message','phase'] as $field) {
                if (isset($run[$field])) { $summary[$field] = $run[$field]; }
            }
            $this->getLogger('TranslationNightlyTick')->info('Nächtlicher Übersetzungslauf', $summary);
        } catch (\Throwable $error) {
            $this->getLogger('TranslationNightlyFailure')->error('Übersetzungslauf konnte nicht ausgeführt werden', TranslationIssue::details($error));
        }
    }
}
