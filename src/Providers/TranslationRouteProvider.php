<?php
namespace WaldorfshopTranslations\Providers;
use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
class TranslationRouteProvider extends RouteServiceProvider
{
    public function map(ApiRouter $api)
    {
        $api->version(['v1'], ['middleware' => ['oauth']], function ($router) {
            $router->get('waldorfshop-translations/configuration-check', ['uses' => 'WaldorfshopTranslations\\Controllers\\TranslationController@configurationCheck']);
            $router->get('waldorfshop-translations/status', ['uses' => 'WaldorfshopTranslations\Controllers\TranslationController@status']);
            $router->post('waldorfshop-translations/pilot', ['uses' => 'WaldorfshopTranslations\Controllers\TranslationController@pilot']);
        });
    }
}
