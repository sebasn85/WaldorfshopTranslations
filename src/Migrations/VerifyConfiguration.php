<?php
namespace WaldorfshopTranslations\Migrations;

use WaldorfshopTranslations\Services\HttpClient;
use WaldorfshopTranslations\Services\Runner;

/** Read-only deployment probe for the isolated configuration test branch. */
class VerifyConfiguration
{
    public function run(Runner $runner, HttpClient $http)
    {
        if ($http->option('automaticEnabled', '0') !== '0' || $http->option('writesAllowed', '0') !== '0') {
            throw new \RuntimeException('Read-only configuration probe requires automation off and preview mode.');
        }
        $result = $runner->configurationCheck();
        if (($result['valid'] ?? false) !== true) {
            throw new \RuntimeException('Translation configuration check did not succeed.');
        }
    }
}
