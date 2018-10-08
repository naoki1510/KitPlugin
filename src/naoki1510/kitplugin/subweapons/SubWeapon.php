<?php

namespace naoki1510\kitplugin\subweapons;

use pocketmine\item\Item;

abstract class SubWeapon implements Listener
{
    /** @var string */
    public $name;

    /** @var Item */
    public $item;

    /** @var int */
    public $rank;

    public function __construct(Item $item, string $name, int $rank = 1)
    {
        $this->item = $item;
        $this->name = $name;
        $this->rank = $rank;
    }
}
