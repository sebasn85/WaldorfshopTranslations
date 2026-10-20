<?php
// Minimal SDK boundary: the real controller is exercised, not a copy of its logic.
namespace Plenty\Plugin {
    class Controller { public function authorize() {} }
}
namespace Plenty\Modules\Authorization\Contracts {
    abstract class AuthorizedUserRepositoryContract { abstract public function getCurrentAuthorizedUser(); }
}
namespace {
    require __DIR__ . '/../src/Services/HttpClient.php';
    require __DIR__ . '/../src/Services/Runner.php';
    require __DIR__ . '/../src/Controllers/TranslationController.php';
    class AuthRepositoryFixture extends \Plenty\Modules\Authorization\Contracts\AuthorizedUserRepositoryContract
    {
        private $data;
        public function __construct($data) { $this->data = $data; }
        public function getCurrentAuthorizedUser() { return $this->data === null ? null : new AuthModelFixture($this->data); }
    }
    class AuthModelFixture
    {
        private $data;
        public $user;
        public function __construct(array $data) { $this->data = $data; $this->user = $data['user'] ?? null; }
        public function toArray(): array { return $this->data; }
    }
    class AuthHttpFixture extends \WaldorfshopTranslations\Services\HttpClient
    {
        public function __construct() {}
        public function option(string $key, $default = '') { return '197'; }
    }
    class AuthRunnerFixture extends \WaldorfshopTranslations\Services\Runner
    {
        public $runs = 0;
        public function __construct() {}
        public function pilot(): array { $this->runs++; return ['status' => 'allowed']; }
    }
    function authBoundaryTests(): array
    {
        $cases = [];
        foreach ([['user' => ['id' => 197]], ['user' => ['userId' => 197]], ['userId' => 197, 'user' => 'waldorfshop_translation'], ['user' => (object)['id' => 197, 'userId' => 197]]] as $i => $data) {
            $cases['authorized operator identity format ' . $i] = function() use ($data) {
                $controller = new \WaldorfshopTranslations\Controllers\TranslationController();
                $runner = new AuthRunnerFixture();
                expect($controller->pilot(new AuthRepositoryFixture($data), new AuthHttpFixture(), $runner)['status'] === 'allowed');
                expect($runner->runs === 1);
            };
        }
        foreach ([null, [], ['user' => ['id' => 6]], ['user' => ['id' => 197, 'userId' => 6]], ['id' => 6, 'user' => ['id' => 197]]] as $i => $data) {
            $cases['untrusted operator identity blocked before pilot ' . $i] = function() use ($data) {
                $controller = new \WaldorfshopTranslations\Controllers\TranslationController();
                $runner = new AuthRunnerFixture();
                raises(function() use ($controller, $runner, $data) { $controller->pilot(new AuthRepositoryFixture($data), new AuthHttpFixture(), $runner); });
                expect($runner->runs === 0);
            };
        }
        return $cases;
    }
}
