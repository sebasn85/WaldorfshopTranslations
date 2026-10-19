<?php
namespace WaldorfshopTranslations\Providers;
use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Cron\Services\CronContainer;
use WaldorfshopTranslations\Cron\TranslationCron;
class TranslationServiceProvider extends ServiceProvider
{
    public function register() { $this->getApplication()->register(TranslationRouteProvider::class); }
    public function boot(CronContainer $cron) { $cron->add(CronContainer::EVERY_FIFTEEN_MINUTES, TranslationCron::class, 0); }
}
