<?php
namespace WaldorfshopTranslations\Services;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;

class HttpClient
{
    private $library;
    private $config;
    private $token = '';
    public function __construct(LibraryCallContract $library, ConfigRepository $config)
    { $this->library = $library; $this->config = $config; }
    public function option(string $key, $default = '')
    { $value = $this->config->get('WaldorfshopTranslations.' . $key); return $value === null || $value === '' ? $default : $value; }
    public function request(string $service, string $method, string $path, array $body = [], array $query = [])
    {
        if ($service === 'plenty' && $path !== '/rest/login' && !$this->token) {
            $login = $this->request('plenty', 'POST', '/rest/login', ['username' => $this->option('plentyUsername'), 'password' => $this->option('plentyPassword')]);
            if (!isset($login['access_token']) || !is_string($login['access_token'])) { throw new \RuntimeException('Plenty-Anmeldung fehlgeschlagen.'); }
            $this->token = $login['access_token'];
        }
        $authorization = $service === 'deepl' ? 'DeepL-Auth-Key ' . $this->option('deeplKey') : ($this->token ? 'Bearer ' . $this->token : '');
        $result = $this->library->call('WaldorfshopTranslations::http', ['service' => $service, 'method' => $method, 'path' => $path, 'body' => $body, 'query' => $query, 'authorization' => $authorization]);
        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            throw new \RuntimeException($service . ': API-Aufruf fehlgeschlagen (HTTP ' . (int)($result['status'] ?? 0) . ').');
        }
        return $result['data'];
    }
}
