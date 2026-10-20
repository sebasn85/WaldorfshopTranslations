<?php
namespace WaldorfshopTranslations\Services;

/** Parse the same public configuration for runner, gateway and HTTP boundary. */
class Configuration
{
    const TARGETS = ['en'=>'EN-GB','fr'=>'FR','it'=>'IT','es'=>'ES','nl'=>'NL','bg'=>'BG','cn'=>'ZH-HANS','cz'=>'CS','da'=>'DA','nn'=>'NB','pl'=>'PL','pt'=>'PT-PT','ro'=>'RO','ru'=>'RU','se'=>'SV','sk'=>'SK','tr'=>'TR','vn'=>'VI'];
    public static function origin(string $value): string
    {
        $value = rtrim(trim($value), '/');
        if (!preg_match('~^https://(?:p[0-9]+\.my\.plentysystems\.com|[a-z0-9][a-z0-9-]*\.plentymarkets\.com)$~D', $value)) {
            throw new \RuntimeException('Gültige HTTPS-Plenty-Backend-Adresse erforderlich.');
        }
        return $value;
    }
    private static function positive($value, string $label): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string)$value)) { throw new \RuntimeException($label . ': positive ganze Zahl erforderlich.'); }
        return (int)$value;
    }
    public static function parse(array $values): array
    {
        $raw = $values['targetLanguages'] ?? '';
        if (is_string($raw)) { $raw = trim($raw) === '' ? [] : explode(',', $raw); }
        if (!is_array($raw) || !$raw) { throw new \RuntimeException('Mindestens eine Zielsprache auswählen.'); }
        $selected = [];
        foreach ($raw as $lang) {
            if (!is_string($lang) || !isset(self::TARGETS[trim($lang)])) { throw new \RuntimeException('Nicht unterstützte Plenty-Zielsprache.'); }
            $selected[trim($lang)] = true;
        }
        $targets = array_intersect_key(self::TARGETS, $selected);
        foreach (['en'=>['englishVariant',['EN-GB','EN-US']], 'pt'=>['portugueseVariant',['PT-PT','PT-BR']], 'cn'=>['chineseVariant',['ZH-HANS','ZH-HANT']]] as $lang => $variant) {
            if (isset($targets[$lang])) {
                $value = $values[$variant[0]] ?? $targets[$lang];
                if (!in_array($value, $variant[1], true)) { throw new \RuntimeException('Ungültige Sprachvariante.'); }
                $targets[$lang] = $value;
            }
        }
        $ready = self::positive($values['readyTagId'] ?? '', 'Auslöser-Tag-ID');
        $review = self::positive($values['reviewTagId'] ?? '', 'Prüftag-ID');
        if ($ready === $review) { throw new \RuntimeException('Auslöser- und Prüftag müssen verschieden sein.'); }
        $mode = $values['glossaryMode'] ?? 'off'; $glossary = trim((string)($values['glossaryId'] ?? ''));
        if (!in_array($mode, ['off','required'], true) || ($mode === 'required' && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', $glossary))) { throw new \RuntimeException('Gültigen Glossarmodus und Glossar-ID einstellen.'); }
        $plan = $values['deeplPlan'] ?? 'pro';
        if (!in_array($plan, ['pro','free'], true)) { throw new \RuntimeException('DeepL API-Tarif ungültig.'); }
        foreach (['plentyUsername','plentyPassword','deeplKey'] as $key) {
            if (!is_string($values[$key] ?? null) || trim($values[$key]) === '') { throw new \RuntimeException('API-Zugänge unvollständig.'); }
        }
        return ['targets'=>$targets,'ready_tag_id'=>$ready,'review_tag_id'=>$review,'operator_user_id'=>self::positive($values['operatorUserId'] ?? '', 'API-Benutzer-ID'),'plenty_origin'=>self::origin((string)($values['plentyBaseUrl'] ?? '')),'deepl_origin'=>$plan === 'free' ? 'https://api-free.deepl.com' : 'https://api.deepl.com','policy'=>['version'=>2,'unknown'=>'refresh','glossary_id'=>$mode === 'required' ? strtolower($glossary) : '', 'targets'=>$targets,'ready_tag_id'=>$ready,'review_tag_id'=>$review], 'context'=>(string)($values['translationContext'] ?? ''),'monthly_items'=>self::positive($values['monthlyItems'] ?? '100','Monatliche Artikel'),'monthly_characters'=>self::positive($values['monthlyCharacters'] ?? '250000','Monatliche Zeichen'),'seed_month'=>'','seed_characters'=>0,'seed_items'=>[]];
    }
}
