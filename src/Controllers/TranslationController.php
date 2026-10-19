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
        $user = $authorized ? $authorized->user : null;
        $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user ? $user->id : 0));
        $expectedId = (int)$http->option('operatorUserId', '197');
        if ($userId <= 0 || $expectedId <= 0 || $userId !== $expectedId) {
            throw new \RuntimeException('Kein Zugriff auf die Übersetzungssteuerung (Benutzer-ID ' . $userId . ', erwartet ' . $expectedId . ').');
        }
    }
    public function status(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner)
    { $this->assertOperator($users, $http); return $runner->status(); }
    public function pilot(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner)
    { $this->assertOperator($users, $http); return $runner->pilot(); }
}
