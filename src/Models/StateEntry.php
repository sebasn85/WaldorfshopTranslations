<?php
namespace WaldorfshopTranslations\Models;
use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * @property int $id
 * @property string $stateKey
 * @property array $payload
 * @Index(columns={"stateKey"}, name="TranslationStateKey", isUnique="true")
 */
class StateEntry extends Model
{
    public $id = 0;
    public $stateKey = '';
    public $payload = [];
    public function getTableName(): string { return 'WaldorfshopTranslations::StateEntry'; }
}
