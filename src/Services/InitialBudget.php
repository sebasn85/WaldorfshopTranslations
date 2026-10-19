<?php
namespace WaldorfshopTranslations\Services;
/** Conservative carry-over of paid pilot reservations; never resets the local quota. */
class InitialBudget
{
    const MONTH = '2026-10';
    const CHARACTERS = 8565;
    public static function items(): array { return [10009957]; }
}
