<?php
namespace WaldorfshopTranslations\Migrations;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use WaldorfshopTranslations\Services\Gateway;
use WaldorfshopTranslations\Services\HttpClient;
class InspectRecoverySlice70 {
    public function run(DataBase $database, Gateway $gateway, HttpClient $http) {
        $review=pluginApp(RecoverySliceReview::class); $review->inspect($database,$gateway,$http,70);
    }
}
