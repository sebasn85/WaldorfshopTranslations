<?php
namespace WaldorfshopTranslations\Core;

/** Explicitly retain an existing target when its German source is empty. */
class EmptySourceReview
{
    public static function accepted(array $old, string $source, string $target, array $allSource, array $policy): bool
    {
        $review = $old['empty_source_review'] ?? [];
        return is_array($review) && is_int($review['reviewer'] ?? null) && $review['reviewer'] > 0
            && ($old['source'] ?? null) === $source && ($old['target'] ?? null) === $target
            && ($review['source_hash'] ?? '') === TranslationEngine::hash($allSource)
            && ($review['policy_hash'] ?? '') === TranslationEngine::hash($policy);
    }

    public static function baseline(array $baseline, array $plan, array $selected, int $reviewer): array
    {
        if ($reviewer <= 0) { throw new TranslationIssue('invalid_review', 'Freigebender Benutzer fehlt.'); }
        foreach ($selected as $lang => $fields) {
            if (!isset($plan['targets'][$lang]) || !is_array($fields) || !$fields) {
                throw new TranslationIssue('invalid_review', 'Ungültige Sprache oder Feldauswahl.');
            }
            foreach ($fields as $field) {
                if (!is_string($field) || !in_array($field, TranslationEngine::FIELDS, true)) {
                    throw new TranslationIssue('invalid_review', 'Nicht freigegebenes Textfeld.');
                }
                $source = $plan['source'][$field]; $target = $plan['targets'][$lang]['before'][$field];
                if (!TranslationEngine::blank($source) || TranslationEngine::blank($target)) {
                    throw new TranslationIssue('invalid_review', 'Nur vorhandene Zieltexte bei leerer deutscher Quelle dürfen beibehalten werden.');
                }
                $baseline[$lang][$field] = ['source'=>$source, 'target'=>$target,
                    'empty_source_review'=>['reviewer'=>$reviewer, 'at'=>gmdate('c'),
                        'source_hash'=>TranslationEngine::hash($plan['source']), 'policy_hash'=>TranslationEngine::hash($plan['policy'])]];
            }
        }
        return $baseline;
    }
}
