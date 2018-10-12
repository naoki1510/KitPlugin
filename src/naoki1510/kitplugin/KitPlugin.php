<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\KitPlugin;
use naoki1510\kitplugin\mainweapons\BowWeapon;
use naoki1510\kitplugin\mainweapons\SnowBallWeapon;
use naoki1510\kitplugin\subweapons\Bom;
use naoki1510\kitplugin\subweapons\Shield;
use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\level\Explosion;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerRespawnEvent;


class KitPlugin extends PluginBase implements Listener
{
    /** @var Config */
    private $gachalist;
    private $playerdata;
    private $inventories;

    public function onEnable()
    {
		// 起動時のメッセージ
        $this->getLogger()->info("§eKitPlugin was loaded.");

        $this->saveDefaultConfig();
		//PlayerData.yml作成
        $this->playerdata = new Config($this->getDataFolder() . 'PlayerData.yml', Config::YAML);
        $this->saveResource('gacha.yml');
        $this->gachalist = new Config($this->getDataFolder() . 'gacha.yml', Config::YAML);
        $this->inventories = new Config($this->getDataFolder() . 'inventories.yml', Config::YAML);

		// イベントリスナー登録
		/** @todo foreachを使って配列にする */
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new Bom($this->getScheduler(), $this->getConfig()->get('gameworlds', [])), $this);
        $this->getServer()->getPluginManager()->registerEvents(new Shield(
            $this->getScheduler(),
            new Config($this->getDataFolder() . 'pb.yml', Config::YAML),
            $this->getConfig()->get('gameworlds', [])
        ), $this);
        $this->getServer()->getPluginManager()->registerEvents(new SnowBallWeapon($this->getScheduler()), $this);
        $this->getServer()->getPluginManager()->registerEvents(new BowWeapon($this->getScheduler()), $this);

    }

    /** もういらない */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool
    {
        switch (strtolower($command->getName())) {

            case "exp": 
                if($sender instanceof Entity){
                    $sender->sendMessage("爆発！");
                    $explosion = new Explosion($sender->asPosition(), 5, $sender);
                    $explosion->explodeB();
                    return true; 
                }
        }
        return false;
    }

    public function gacha(Player $player, string $type){
        if (!$this->gachalist->exists($type)) {
            $player->sendMessage('that type was not found.');
            return;
        }

        $rand = rand(0, 99);
        if(is_array($this->gachalist->get($type, null))){
            $list = $this->gachalist->get($type);
            foreach ($list as $percent => $items) {
                if($rand >= $percent) continue;

                $itemName = array_rand($items);
                $item = Item::fromString($itemName);
                $item->setCount($items[$itemName]);

                if (!$item instanceof Item) return false;

                if ($player->getInventory()->canAddItem($item)) {
                    if (EconomyAPI::getInstance()->reduceMoney($player, $this->getConfig()->getNested('cost.'. $type, 3000)) === 1) {
                        $player->getInventory()->addItem($item);
                        return true;
                    } else {
                        $player->sendMessage('お金が足りません。');
                    }
                } else {
                    $player->sendMessage('インベントリに空きがありません。');
                }
            }
        }else{
            $player->sendMessage('データが壊れています。');
        }
        return false;
    }

    public function onDamage(EntityDamageEvent $e)
    {
        $this->getLogger()->info($e->getFinalDamage());
    }

    public function onPlayerTap(PlayerInteractEvent $e)
    {
        //$this->getServer()->getLogger()->info($e->getEventName() . " was Called.");
        $player = $e->getPlayer();
        $block = $e->getBlock();
        /** @var Item $hand */
        $hand = $player->getInventory()->getItemInHand();
        switch ($block->getId()) {
            case Item::fromString('Diamond Block')->getId():
                $this->gacha($player, 'main');
                $e->setCancelled();
                break;
            case Item::fromString('Gold Block')->getId():
                $this->gacha($player, 'sub');
                $e->setCancelled();
                break;
            case Item::fromString('Iron Block')->getId():
                $this->gacha($player, 'armor');
                $e->setCancelled();
                break;

            case Item::fromString('Diamond Ore')->getId():
                $this->setGameInventory($player, $hand);
                $e->setCancelled();
                break;
        }
    }

    public function onTeleportWorld(EntityLevelChangeEvent $e)
    {
        //Playerなどイベント関連情報を取得
        /** @var Player $player */
        $player = $e->getEntity();
        if (!$player instanceof Player) return;
        
        $target = $e->getTarget();
        $origin = $e->getOrigin();
        if (\in_array($origin->getName(), $this->getConfig()->get('shopworlds', []))) {
            // moving from Shopworld
            $this->inventories->setNested($player->getName() . '.shop', $player->getInventory()->getContents());
        }

        if (\in_array($target->getName(), $this->getConfig()->get('gameworlds', []))) {
            // moving into GameWorld
            $items = $this->getSavedInventory($player->getName() . '.game');
            $player->getInventory()->setContents($items);
        } elseif (\in_array($target->getName(), $this->getConfig()->get('shopworlds', []))) {
            // moving into ShopWorld
            $items = $this->getSavedInventory($player->getName() . '.shop');
            $player->getInventory()->setContents($items);
        } 
        $this->inventories->save();
    }

    /**
     * @param string $key
     * @param Item[] $items
     * 
     * @return Item[]
     */
    private function getSavedInventory(string $key){

        $items = [];
        foreach ($this->inventories->getNested($key, []) as $item) {
            // if this was saved before server restarting, $item has been serialized.
            if ($item instanceof Item) {
                array_push($items, $item);
            } elseif (is_string($item)) {
                if (($item = unserialize($item)) instanceof Item) {
                    array_push($items, $item);
                }
            }
        }
        return $items;
    }

    /**
     * @param Item $item
     */
    private function setGameInventory(Player $player, $item){
        switch ($item->getId()) {
            case Item::fromString('Bow')->getId():
            case Item::fromString('Stone Sword')->getId():
            case Item::fromString('Iron Sword')->getId():
            case Item::fromString('Diamond Sword')->getId():
            case Item::fromString('Snowball')->getId():
                $this->inventories->setNested($player->getName() . '.game.main', $item);
                $player->sendMessage($item->getName() . ' がメイン武器に設定されました。');
                break;

            case Item::fromString('TNT')->getId():
            case Item::fromString('Stained Glass Pane')->getId():
            case Item::fromString('Splash Potion')->getId():
                $this->inventories->setNested($player->getName() . '.game.sub', $item->setCount(1));
                $player->sendMessage($item->getName() . ' がサブ武器に設定されました。');
                break;
                
            case Item::fromString('leather helmet')->getId():
            case Item::fromString('chain helmet')->getId():
            case Item::fromString('iron helmet')->getId():
            case Item::fromString('Diamond helmet')->getId():
                $this->inventories->setNested($player->getName() . '.game.helmet', $item);
                $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            case Item::fromString('leather chestplate')->getId():
            case Item::fromString('chain chestplate')->getId():
            case Item::fromString('iron chestplate')->getId():
            case Item::fromString('Diamond chestplate')->getId():
                $this->inventories->setNested($player->getName() . '.game.chestplate', $item);
                $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            case Item::fromString('leather leggings')->getId():
            case Item::fromString('chain leggings')->getId():
            case Item::fromString('iron leggings')->getId():
            case Item::fromString('Diamond leggings')->getId():
                $this->inventories->setNested($player->getName() . '.game.leggings', $item);
                $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            case Item::fromString('leather boots')->getId():
            case Item::fromString('chain boots')->getId():
            case Item::fromString('iron boots')->getId():
            case Item::fromString('Diamond boots')->getId():
                $this->inventories->setNested($player->getName() . '.game.boots', $item);
                $player->sendMessage($item->getName() . ' が装備に設定されました。');
                break;
                
            default:
                # code...
                break;
        }
        $this->playerdata->save();
    }

    /**
     * @param Item[]|string[] $items
     * 
     * @return Item[]
     */
    private function unserializeItems(array $items){
        $pditems = [];
        foreach ($items as $item) {
            if ($item instanceof Item) {
                array_push($pditems, $item);
            } elseif (is_string($item)) {
                if (unserialize($item) instanceof Item) {
                    array_push($pditems, unserialize($item));
                }
            }
        }
        return $pditems;
    }

    public function onLaunchProjectile(ProjectileLaunchEvent $e){
        if(in_array($e->getEntity()->getLevel()->getName(), $this->getConfig()->get('shopworlds', []))) $e->setCancelled();
    }

    public function onRespawn(PlayerRespawnEvent $e){
        $player = $e->getPlayer();
        if (in_array($player->getLevel()->getName(), $this->getConfig()->get('gameworlds', []))){
            $items = $this->getSavedInventory($player->getName() . '.game');
            $player->getInventory()->setContents($items);
        }
    }
}