<?php
namespace WaldorfshopTranslations\Services;
use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Plenty\Plugin\ConfigRepository;
use WaldorfshopTranslations\Core\ProcessingPause;

class HttpClient
{
    private $library;
    private $config;
    private $token = '';
    private $settings;
    private $deadline = 0;
    public function __construct(LibraryCallContract $library, ConfigRepository $config)
    { $this->library = $library; $this->config = $config; }
    public function option(string $key, $default = '')
    { $value = $this->config->get('WaldorfshopTranslations.' . $key); return $value === null || $value === '' ? $default : $value; }
    public function settings(): array
    {
        if ($this->settings === null) {
            $values = [];
            foreach (['targetLanguages','englishVariant','portugueseVariant','chineseVariant','readyTagId','reviewTagId','glossaryMode','glossaryId','deeplPlan','plentyUsername','plentyPassword','deeplKey','operatorUserId','plentyBaseUrl','translationContext','monthlyItems','monthlyCharacters'] as $key) {
                $value = $this->config->get('WaldorfshopTranslations.' . $key);
                if ($value !== null) { $values[$key] = $value; }
            }
            $this->settings = Configuration::parse($values);
        }
        return $this->settings;
    }
    public function request(string $service, string $method, string $path, array $body = [], array $query = [])
    {
        $this->checkDeadline();
        if ($service === 'plenty' && $path !== '/rest/login' && !$this->token) {
            $login = $this->request('plenty', 'POST', '/rest/login', ['username' => $this->option('plentyUsername'), 'password' => $this->option('plentyPassword')]);
            if (!isset($login['access_token']) || !is_string($login['access_token'])) { throw new ProcessingPause('service', 'Plenty-Anmeldung fehlgeschlagen.'); }
            $this->token = $login['access_token'];
        }
        $authorization = $service === 'deepl' ? 'DeepL-Auth-Key ' . $this->option('deeplKey') : ($this->token ? 'Bearer ' . $this->token : '');
        $this->checkDeadline();
        try {
            $result = $this->library->call('WaldorfshopTranslations::http', ['plentyOrigin' => $this->settings()['plenty_origin'], 'deeplOrigin' => $this->settings()['deepl_origin'], 'service' => $service, 'method' => $method, 'path' => $path, 'body' => $body, 'query' => $query, 'authorization' => $authorization]);
        } catch (\Throwable $error) {
            throw new ProcessingPause('service', $service . ': API-Verbindung fehlgeschlagen.');
        }
        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            $status = (int)($result['status'] ?? 0);
            if (($service === 'deepl' && $path === '/v2/translate' && in_array($status, [400, 413, 422], true)) ||
                ($service === 'plenty' && strpos($path, '/rest/items/') === 0 && in_array($status, [400, 404, 409, 410, 413, 422], true))) {
                throw new \RuntimeException($service . ': Artikelanfrage abgelehnt (HTTP ' . $status . ').');
            }
            throw new ProcessingPause('service', $service . ': API-Aufruf fehlgeschlagen (HTTP ' . (int)($result['status'] ?? 0) . ').');
        }
        return $result['data'];
    }
    public function setDeadline(int $deadline): void { $this->deadline = $deadline; }
    public function checkDeadline(): void
    {
        if ($this->deadline > 0 && time() >= $this->deadline) { throw new ProcessingPause('time', 'Zeitfenster des Arbeitspakets erreicht.'); }
    }
}
