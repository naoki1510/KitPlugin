<?php

namespace naoki1510\kitplugin;

use naoki1510\kitplugin\tasks\BlockRecoveryTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;


class KitPlugin extends PluginBase implements Listener
{

    /** @var Config */
    private $gachalist;
    private $playerdata;

    public function onEnable()
    {
		// 起動時のメッセージ
        $this->getLogger()->info("§eKitPlugin was loaded.");

        $this->saveDefaultConfig();
		//PlayerData.yml作成
        $this->playerdata = new Config($this->getDataFolder() . 'PlayerData.yml', Config::YAML);
        $this->saveResource('gacha.yml');
        $this->gachalist = new Config($this->getDataFolder() . 'gacha.yml', Config::YAML);

		// イベントリスナー登録
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new \naoki1510\kitplugin\EventListener($this), $this);

    }

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

    public function gacha(string $type){
        if($this->gachalist->exists($type)){
            $rand = rand(0, 99);
            if(is_array($this->gachalist->get($type))){
                $list = $this->gachalist->get($type);
                \sort($list);
                foreach ($list as $key => $value) {
                
                }
            }
        }
    }
}