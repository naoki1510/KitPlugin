<?php

namespace naoki1510\kitplugin\subweapons;

use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use naoki1510\kitplugin\tasks\RestoreItemTask;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\item\Item;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;

class Shield implements Listener {

    /** @var Scheduler */
    private $scheduler;

    /** @var Config */
    private $overlapping;

    /** @var string[] */
    public $levels = [];

    public function __construct(TaskScheduler $scheduler, Config $overlapping, array $levels)
    {
        $this->scheduler = $scheduler;
        $this->overlapping = $overlapping;
        $this->levels = $levels;
    }

    public function onPlace(BlockPlaceEvent $e)
    {
        //Playerなどイベント関連情報を取得
        $player = $e->getPlayer();
        if (!in_array($player->getLevel()->getName(), $this->levels)) return;
        $block = $e->getBlock();
        $id = $block->getId();

        if ($id === Item::fromString('stained_glass_pane')->getId()){
            //$player->sendMessage(strval($player->yaw));
            $changedPos = [];
            for ($x = -2; $x < 3; $x++) {
                for ($y = 0; $y < 3; $y++) {
                    if ($player->yaw % 180 < 45 || $player->yaw % 180 > 135) {
                        $pos = $block->asPosition()->add($x, $y);
                    } else {
                        $pos = $block->asPosition()->add(0, $y, $x);
                    }
                    $rblock = $player->getLevel()->getBlock($pos);
                    if ($rblock->getId() === 0) {
                        $player->getLevel()->setBlock($pos, Item::fromString('stained_glass_pane')->getBlock());
                        array_push($changedPos, $pos);
                    } else if ($rblock->getId() === Item::fromString('stained_glass_pane')->getId()) {
                        array_push($changedPos, $pos);
                        $this->overlapping->set(implode(":", [$pos->x, $pos->y, $pos->z]), Server::getInstance()->getTick() + 10 * 20);
                    }
                }
            }

            $hand = $player->getInventory()->getItemInHand();
            $player->getInventory()->setItemInHand($hand->setCount($hand->getCount() - 1));
            $e->setCancelled();

            $this->scheduler->scheduleDelayedTask(
                new BlockRecoveryTask($changedPos, $player->getLevel(), $this->overlapping),
                10 * 20
            );

            $this->scheduler->scheduleDelayedTask(new RestoreItemTask(
                $block->getItem(),
                $player
            ), 15 * 20);
        }
    }
}
