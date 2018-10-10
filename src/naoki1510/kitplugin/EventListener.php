<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerEvent;
use pocketmine\event\entity\EntityEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

class EventListener implements Listener
{
    /** @var Config */
    public $config;
    private $protectedBlock;

    /** @var TaskScheduler */
    public $scheduler;

    public function __construct(KitPlugin $kitPlugin)
    {
        $this->config = $kitPlugin->getConfig();
        $this->protectedBlock = new Config($kitPlugin->getDataFolder() . 'pb.yml', Config::YAML);
        $this->scheduler = $kitPlugin->getScheduler();
    }

    public function getConfig() : Config{
        return $this->config;
    }

    public function getScheduler() : TaskScheduler
    {
        return $this->scheduler;
    }

    public function getServer() : Server{
        return Server::getInstance();
    }


    public function onTouch(PlayerInteractEvent $e)
    {
        //$this->getServer()->getLogger()->info($e->getEventName() . " was Called.");
		//Playerなどイベント関連情報を取得
        $player = $e->getPlayer();
        $block = $e->getBlock();
        $id = $block->getId();

        switch ($id) {
            case Item::fromString($this->getConfig()->getNested('block.main', 57))->getId():
                $item = $this->gacha('main');
                break;

            default:
                # code...
                break;
        }

        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::fromString('Stone Sword')->getId():
                //$explosion = new Explosion($block->asPosition(), 3, $player);
                //$explosion->explodeB();
                /*foreach ($hand->getEnchantments() as $ench) {
                    if ($ench->getId() === Enchantment::FIRE_ASPECT) {
                        $explosion = new Explosion($block->asPosition(), 3, $player);
                        $explosion->explodeB();
                    }
                }
                break;*/

            default:
                # code...
                break;
        }

    }
    
    public function onPlayerItemUse(PlayerItemUseEvent $e){
        //$this->getServer()->getLogger()->info($e->getEventName() . " was Called.");
        $player = $e->getPlayer();
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
                $entities = new PrimedTNT($player->getLevel(), $nbt);
                $entities->setMotion($entities->getMotion()->multiply($f));
                $player->getInventory()->setItemInHand($hand->setCount($hand->getCount() - 1));

                break;

            default:
                # code...
                break;
        }
    }

    public function onPlayerAnimation(PlayerAnimationEvent $e)
    {
        $player = $e->getPlayer();
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
                $entities = new PrimedTNT($player->getLevel(), $nbt);
                $entities->setMotion($entities->getMotion()->multiply($f));
                $player->getInventory()->setItemInHand($hand->setCount($hand->getCount() - 1));
                
                break;

            default:
                # code...
                break;
        }
    }

    public function onDamage(EntityDamageByEntityEvent $e)
    {
        //$this->getServer()->getLogger()->info($e->getEventName() . " was Called.");
        $entity = $e->getEntity();
        if ($e->getCause() == EntityDamageEvent::CAUSE_ENTITY_EXPLOSION) {
            if ($e->getDamager() === $entity) {
                $e->setCancelled();
            } else if (($player = $this->getServer()->getPlayer(($e->getDamager()->getDataPropertyManager()->getString(100)) ?? '')) instanceof Player) {
                if ($entity === $player) {
                    $e->setCancelled();
                } else {
                    $this->getLogger()->info($e->getDamager()->getDataPropertyManager()->getString(100));
                    var_dump($player);
                }
            }
            //$this->getLogger()->info($e->getDamager()->getDataPropertyManager()->getString(100));
        }
    }

    public function onPacketReceive(DataPacketReceiveEvent $e){
        //$this->getServer()->getLogger()->info($e->getPacket()->getName() . " received.");
    }

    public function onExplode(ExplosionPrimeEvent $e)
    {
        $e->setBlockBreaking(false);
        $e->setForce(1.5);
        //$this->getLogger()->info($e->getEntity()->getDataPropertyManager()->getString(100));
    }

    public function onHitProjectile(ProjectileHitEvent $event)
    {
    }

    public function onBreak(BlockBreakEvent $e)
    {
        $player = $e->getPlayer();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($hand->getId()) {
            case Item::fromString('TNT')->getId():

                $e->setCancelled();
                break;

            default:
                # code...
                break;
        }
    }

    public function onPlace(BlockPlaceEvent $e)
    {
        //Playerなどイベント関連情報を取得
        $player = $e->getPlayer();
        $block = $e->getBlock();
        $id = $block->getId();

        switch ($id) {
            case Item::fromString('stained_glass_pane')->getId():
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
                        }else if($rblock->getId() ===  Item::fromString('stained_glass_pane')->getId()){
                            array_push($changedPos, $pos);
                            $this->protectedBlock->set(implode(":", [$pos->x, $pos->y, $pos->z]), $this->getServer()->getTick() + 10 * 20);
                        }
                    }
                }

                $this->getScheduler()->scheduleDelayedTask(
                    new BlockRecoveryTask($changedPos, $player->getLevel(), $this->protectedBlock),
                    10 * 20
                );



            default:
                # code...
                break;
        }
    }
}
