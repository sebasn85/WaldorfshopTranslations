<?php
namespace WaldorfshopTranslations\Controllers;
use Plenty\Plugin\Controller;
use Plenty\Modules\Authorization\Contracts\AuthorizedUserRepositoryContract;
use WaldorfshopTranslations\Services\Runner;
use WaldorfshopTranslations\Services\HttpClient;
class TranslationController extends Controller
{
    private function assertOperator(AuthorizedUserRepositoryContract $users, HttpClient $http): void
    {
        $authorized = $users->getCurrentAuthorizedUser();
        if (!$authorized || !$authorized->user || (int)$authorized->user->id !== (int)$http->option('operatorUserId', '197')) {
            throw new \RuntimeException('Kein Zugriff auf die Übersetzungssteuerung.');
        }
    }
    public function status(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner)
    { $this->assertOperator($users, $http); return $runner->status(); }
    public function pilot(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner)
    { $this->assertOperator($users, $http); return $runner->pilot(); }
}
