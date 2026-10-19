<?php
namespace WaldorfshopTranslations\Cron;
use Plenty\Modules\Cron\Contracts\CronHandler;
use WaldorfshopTranslations\Services\Runner;

class TranslationCron extends CronHandler
{
    public function handle()
    {
        // Persisted failures are visible in job state; never leak SDK exceptions.
        pluginApp(Runner::class)->automatic();
    }
}
