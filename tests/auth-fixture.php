<?php
// Minimal SDK boundary: the real controller is exercised, not a copy of its logic.
namespace Plenty\Plugin\Http {
    class Request { private $data; public function __construct(array $data){$this->data=$data;} public function get($key,$default=null){return $this->data[$key]??$default;} }
}
namespace Plenty\Plugin {
    class Controller { public function authorize() {} }
}
namespace Plenty\Modules\Authorization\Contracts {
    abstract class AuthorizedUserRepositoryContract { abstract public function getCurrentAuthorizedUser(); }
}
namespace Plenty\Modules\User\Contracts {
    abstract class UserRepositoryContract { abstract public function getCurrentUser(); }
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
    class CurrentUserFixture extends \Plenty\Modules\User\Contracts\UserRepositoryContract
    {
        private $data;
        public function __construct($data = null) { $this->data = $data; }
        public function getCurrentUser() { return $this->data === null ? null : new AuthModelFixture($this->data); }
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
        public function recoveryPreview(int $id, array $corrections=[]): array { $this->runs++; return ['item_id'=>$id]; }
        public function recoveryResume(int $id,string $token,array $corrections=[]): array { $this->runs++; return ['item_id'=>$id]; }
        public function providerUsage(): array { $this->runs++; return ['character_count'=>0]; }
        public function pilot(): array { $this->runs++; return ['status' => 'allowed']; }
    }
    function authBoundaryTests(): array
    {
        $cases = [];
        foreach ([['user' => ['id' => 197]], ['user' => ['userId' => 197]], ['userId' => 197, 'user' => 'waldorfshop_translation'], ['user' => (object)['id' => 197, 'userId' => 197]]] as $i => $data) {
            $cases['authorized operator identity format ' . $i] = function() use ($data) {
                $controller = new \WaldorfshopTranslations\Controllers\TranslationController();
                $runner = new AuthRunnerFixture();
                expect($controller->pilot(new AuthRepositoryFixture($data), new AuthHttpFixture(), $runner, new CurrentUserFixture())['status'] === 'allowed');
                expect($runner->runs === 1);
            };
        }
        foreach ([null, [], ['user' => ['id' => 6]], ['user' => ['id' => 197, 'userId' => 6]], ['id' => 6, 'user' => ['id' => 197]]] as $i => $data) {
            $cases['untrusted operator identity blocked before pilot ' . $i] = function() use ($data) {
                $controller = new \WaldorfshopTranslations\Controllers\TranslationController();
                $runner = new AuthRunnerFixture();
                raises(function() use ($controller, $runner, $data) { $controller->pilot(new AuthRepositoryFixture($data), new AuthHttpFixture(), $runner, new CurrentUserFixture()); });
                expect($runner->runs === 0);
            };
        }
        $cases['API current user authorizes when backend context is empty'] = function() {
            $controller = new \WaldorfshopTranslations\Controllers\TranslationController(); $runner = new AuthRunnerFixture();
            expect($controller->pilot(new AuthRepositoryFixture(null), new AuthHttpFixture(), $runner, new CurrentUserFixture(['id' => 197]))['status'] === 'allowed');
            expect($runner->runs === 1);
        };
        $cases['conflicting API and backend identities reject pilot'] = function() {
            $controller = new \WaldorfshopTranslations\Controllers\TranslationController(); $runner = new AuthRunnerFixture();
            raises(function() use ($controller, $runner) { $controller->pilot(new AuthRepositoryFixture(['user' => ['id' => 197]]), new AuthHttpFixture(), $runner, new CurrentUserFixture(['id' => 6])); });
            expect($runner->runs === 0);
        };
        foreach (['recoveryPreview','recoveryResume','usage'] as $method) {
            $cases['recovery endpoint '.$method.' refuses unauthorized operator before any work'] = function() use ($method) {
                $controller = new \WaldorfshopTranslations\Controllers\TranslationController(); $runner = new AuthRunnerFixture();
                raises(function()use($controller,$runner,$method){$controller->$method(new AuthRepositoryFixture(['id'=>6]),new AuthHttpFixture(),$runner,new CurrentUserFixture(),new \Plenty\Plugin\Http\Request(['itemId'=>42,'reviewToken'=>str_repeat('a',64)]));});
                expect($runner->runs===0);
            };
        }
        $cases['recovery rejects ambiguous IDs before runner'] = function() {
            foreach (['42abc',[],0,-42,'1.5'] as $id) {
                $controller = new \WaldorfshopTranslations\Controllers\TranslationController(); $runner = new AuthRunnerFixture();
                raises(function()use($controller,$runner,$id){$controller->recoveryPreview(new AuthRepositoryFixture(['id'=>197]),new AuthHttpFixture(),$runner,new CurrentUserFixture(),new \Plenty\Plugin\Http\Request(['itemId'=>$id]));});
                expect($runner->runs===0);
            }
        };
        return $cases;
    }
}
