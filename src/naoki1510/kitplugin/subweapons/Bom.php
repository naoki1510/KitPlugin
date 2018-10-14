<?php

namespace naoki1510\kitplugin\subweapons;

use naoki1510\kitplugin\tasks\RestoreItemTask;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\Listener;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\scheduler\TaskScheduler;
use naoki1510\kitplugin\Weapon;

class Bom extends Weapon
{
    /** @var Int */
    public $maxCount = 1;
    public $weaponId = 46;
    public $delay = 30 * 20;

    public function onPlayerItemUse(PlayerItemUseEvent $e)
    {
        $player = $e->getPlayer();
        if(!in_array($player->getLevel()->getName(), $this->levels)) return;
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::fromString('TNT')->getId():

                $aimPos = $player->getDirectionVector();
                $nbt = new CompoundTag("", [
                    "Pos" => new ListTag("Pos", [
                        new DoubleTag("", $player->x),
                        new DoubleTag("", $player->y + $player->getEyeHeight()),
                        new DoubleTag("", $player->z)
                    ]),
                    "Motion" => new ListTag("Motion", [
                        new DoubleTag("", $aimPos->x),
                        new DoubleTag("", $aimPos->y),
                        new DoubleTag("", $aimPos->z)
                    ]),
                    "Rotation" => new ListTag("Rotation", [
                        new FloatTag("", $player->yaw),
                        new FloatTag("", $player->pitch)
                    ]),
                    "Fire" => new ShortTag("", 20)
                ]);
                $f = 1.2;
                $entities = new PrimedTNT($player->getLevel(), $nbt, $player);
                $entities->setMotion($entities->getMotion()->multiply($f));
                $player->getInventory()->setItemInHand($hand->setCount($hand->getCount() - 1));

                $this->reload($player, Item::get(46));

                $e->setCancelled();
                break;
        }
    }

    public function onExplode(ExplosionPrimeEvent $e)
    {
        $e->setBlockBreaking(false);
        $e->setForce(2.5);
    }
}
