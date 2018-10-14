<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\tasks\RestoreItemTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;

abstract class Weapon implements Listener
{
    /** @var Int */
    public $maxCount = 1;
    public $weaponId = 0;
    public $itemId;
    public $delay = 0;

    /** @var TaskScheduler */
    protected $scheduler;

    /** @var Array */
    public $reloading;

    /** @var Config */
    public $kit;
    public $playerdata;

    /** @var string[] */
    public $levels = [];

    public function __construct(KitPlugin $plugin)
    {
        $this->scheduler = $plugin->getScheduler();
        $this->levels = $plugin->getConfig()->get('gameworlds', []);
        $this->kit = $plugin->kit;
        $this->playerdata = $plugin->playerdata;
        $this->itemId = $this->itemId ?? $this->weaponId;
    }

    /** Reload items */
    public function reload(Player $player, Item $item, $delay = null, $force = false)
    {
        $count = 0;
        foreach ($player->getInventory()->getContents() as $slot => $invitem) {
            if ($invitem->getId() === $item->getId() && $invitem->getDamage() === $item->getDamage()) {
                $count += $invitem->getCount();
                if($force) $player->getInventory()->setItem($slot, Item::get(0));
            }
        }
        if ($count < $this->maxCount || $force) {
            if (empty($this->reloading[$player->getName()]) || $this->reloading[$player->getName()] < Server::getInstance()->getTick()) {
                $this->scheduler->scheduleDelayedTask(new RestoreItemTask(
                    $item,
                    $player
                ), $delay ?? $this->delay);

                $player->sendMessage('Reloading...');
                $this->reloading[$player->getName()] = Server::getInstance()->getTick() + ($delay ?? $this->delay);
            }
        }
    }
}
