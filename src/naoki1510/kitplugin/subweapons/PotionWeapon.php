<?php

namespace naoki1510\kitplugin\subweapons;

use naoki1510\kitplugin\Weapon;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;


class PotionWeapon extends Weapon
{
    /** @var Int */
    public $maxCount = 2;
    public $weaponId = 438;
    public $delay = 30 * 20;

    public function onPlayerItemUse(PlayerItemUseEvent $e)
    {
        $player = $e->getPlayer();
        if (!in_array($player->getLevel()->getName(), $this->levels)) return;
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::fromString('splash potion')->getId():

                $this->reload($player, $hand);

                break;
        }
    }
}
