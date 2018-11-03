<?php

namespace naoki1510\kitplugin\tasks;

use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use naoki1510\kitplugin\KitPlugin;

class RestoreItemTask extends Task
{
    /** @var Item */
    public $item;

    /** @var Player */
    public $player;

    /** @var Int */
    public $max;

    /** @var string */
    public $message;

    public function __construct(Item $item, Player $player, int $max = null, string $message = null)
    {
        $this->item = $item;
        $this->player = $player;
        $this->max = $max ?? $item->getCount();
        $this->message = $message ?? '{item} Reloaded';
    }

    public function onRun(Int $currentTick)
    {
        $count = 0;
        if (!$this->player->isOnline()) return;
        foreach ($this->player->getInventory()->getContents() as $invitem) {
            if($invitem->getId() === $this->item->getId() && $invitem->getDamage() === $this->item->getDamage()){
                $count += $invitem->getCount();
            }
        }
        if($this->max > $count){
            $items = $this->item->setCount(min($this->max - $count, $this->item->getCount()));
            if($this->player->getInventory()->canAddItem($items)){
                $this->player->getInventory()->addItem($items);
                $this->player->sendMessage(str_ireplace('{item}', $items->getName(), $this->message));
            }
            //$this->player->sendMessage('Reloaded2');
        }
        //$this->player->sendMessage('Reloaded3');
        
    }


}
