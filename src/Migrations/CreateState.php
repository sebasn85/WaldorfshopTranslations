<?php
namespace WaldorfshopTranslations\Migrations;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use WaldorfshopTranslations\Models\StateEntry;
class CreateState
{
    public function run(Migrate $migrate) { $migrate->createTable(StateEntry::class); }
}
