<?php
namespace WaldorfshopTranslations\Controllers;
use Plenty\Plugin\Controller;
use Plenty\Modules\Authorization\Contracts\AuthorizedUserRepositoryContract;
use WaldorfshopTranslations\Services\Runner;
use WaldorfshopTranslations\Services\HttpClient;
class TranslationController extends Controller
{
    private function identityId(array $data): int
    {
        $id = (int)($data['id'] ?? 0);
        $userId = (int)($data['userId'] ?? 0);
        if ($id > 0 && $userId > 0 && $id !== $userId) {
            throw new \RuntimeException('Widersprüchliche Plenty-Benutzerkennung.');
        }
        return $id > 0 ? $id : $userId;
    }
    private function assertOperator(AuthorizedUserRepositoryContract $users, HttpClient $http): void
    {
        $authorized = $users->getCurrentAuthorizedUser();
        $authorizedData = $authorized ? $authorized->toArray() : [];
        $user = $authorizedData['user'] ?? ($authorized ? $authorized->user : null);
        $userId = $this->identityId($authorizedData);
        $nestedId = is_array($user) ? $this->identityId($user) : (is_object($user) ? $this->identityId(['id' => $user->id, 'userId' => $user->userId]) : 0);
        if ($userId > 0 && $nestedId > 0 && $userId !== $nestedId) {
            throw new \RuntimeException('Widersprüchlicher Plenty-Anmeldekontext.');
        }
        if ($nestedId > 0) { $userId = $nestedId; }
        $expectedId = (int)$http->option('operatorUserId', '197');
        if ($userId <= 0 || $expectedId <= 0 || $userId !== $expectedId) {
            throw new \RuntimeException('Kein Zugriff auf die Übersetzungssteuerung (Benutzer-ID ' . $userId . ', erwartet ' . $expectedId . '; Felder ' . implode(',', is_array($user) ? array_keys($user) : []) . '; Kontextfelder ' . implode(',', array_keys($authorizedData)) . ').');
        }
    }
    public function status(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner)
    { $this->assertOperator($users, $http); return $runner->status(); }
    public function pilot(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner)
    { $this->assertOperator($users, $http); return $runner->pilot(); }
}
