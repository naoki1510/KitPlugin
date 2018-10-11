<?php

namespace naoki1510\kitplugin\tasks;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;

class RestoreItemTask extends Task
{
    /** @var Item */
    public $item;

    /** @var Player */
    public $player;

    /** @var Int */
    public $max;

    public function __construct(Item $item, Player $player, Int $max = null)
    {
        $this->items = $item;
        $this->player = $player;
        $this->max = $max;
    }

    public function onRun(Int $currentTick)
    {
        $item = $this->item;
        $count = 0;
        foreach ($this->player->getInventory()->getContents() as $invitem) {
            if($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()){
                $count += $invitem->getCount();
            }
        }
        if($this->max > $count){
            if($this->player->getInventory()->canAddItem($item->setCount(min($this->max - $count, $item->getCount())))){
                $this->player->getInventory()->addItem($item->setCount(min($this->max - $count, $item->getCount())));
            }
        }
        
    }


}
