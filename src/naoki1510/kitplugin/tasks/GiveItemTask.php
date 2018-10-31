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
    /** @var KitPlugin */
    public $plugin;

    /** @var Player */
    public $player;

    /** @var string */
    public $kit;
    public $message;

    public function __construct(KitPlugin $plugin, Player $player, string $kit, string $message = null)
    {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->kit = $kit;
        $this->message = $message ?? 'Reloaded.';
    }

    public function onRun(Int $currentTick)
    {
        if ($this->plugin->getKit($player)) {
            
        }
        
    }


}
