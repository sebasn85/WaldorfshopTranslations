<?php
namespace WaldorfshopTranslations\Controllers;
use Plenty\Plugin\Controller;
use Plenty\Modules\Authorization\Contracts\AuthorizedUserRepositoryContract;
use Plenty\Modules\User\Contracts\UserRepositoryContract;
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
    private function assertOperator(AuthorizedUserRepositoryContract $users, HttpClient $http, UserRepositoryContract $currentUsers): void
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
        $current = $currentUsers->getCurrentUser();
        $currentId = $current ? $this->identityId($current->toArray()) : 0;
        if ($userId > 0 && $currentId > 0 && $userId !== $currentId) {
            throw new \RuntimeException('Widersprüchlicher Plenty-API-Benutzer.');
        }
        if ($currentId > 0) { $userId = $currentId; }
        $expectedId = (int)$http->option('operatorUserId', '0');
        if ($userId <= 0 || $expectedId <= 0 || $userId !== $expectedId) {
            throw new \RuntimeException('Kein Zugriff auf die Übersetzungssteuerung. API-Benutzer-ID in der Plugin-Konfiguration prüfen.');
        }
    }
    public function configurationCheck(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner, UserRepositoryContract $currentUsers)
    { $this->assertOperator($users, $http, $currentUsers); return $runner->configurationCheck() + ['release' => '0.2.0']; }
    public function status(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner, UserRepositoryContract $currentUsers)
    { $this->assertOperator($users, $http, $currentUsers); return $runner->status() + ['release' => '0.2.0']; }
    public function pilot(AuthorizedUserRepositoryContract $users, HttpClient $http, Runner $runner, UserRepositoryContract $currentUsers)
    { $this->assertOperator($users, $http, $currentUsers); return $runner->pilot() + ['release' => '0.2.0']; }
}
