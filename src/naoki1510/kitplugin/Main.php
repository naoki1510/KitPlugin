<?php

namespace naoki1510\kitplugin;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Cancellable;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Elytra;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

	/** @var Config */
	private $kit;
	private $playerdata;

	public function onEnable(){
		// 起動時のメッセージ
		$this->getLogger()->info("§eKitPlugin was loaded.");

		$this->saveDefaultConfig();
		// kit.yml作成
		$this->saveResource('kit.yml');
		$this->kit = new Config($this->getDataFolder() . 'kit.yml', Config::YAML);

		//PlayerData.yml作成
		$this->playerdata = new Config($this->getDataFolder() . 'PlayerData.yml', Config::YAML);

		// イベントリスナー登録
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

	}

	public function onTouch(PlayerInteractEvent $e){
		//Playerなどイベント関連情報を取得
		$player = $e->getPlayer();
		$block = $e->getBlock();
		$id = $block->getId();

		//タッチされたブロックが看板(Sign)以外の時 [63=>POST,68=>WALL]
		if ($id !== 63 && $id !== 68){
			return false;
		}
		if($block->getId() == 0){
			//ブロックが壊されたとき
			//ブロックの座標を取得できないためBlockBreakEventの代わりには使えなさそう
			return false;
		}

		$sign = $block->getLevel()->getTile($block);

		// 一行目がkit
		if($sign instanceof Sign && $sign->getLine(0) === '[Kit]'){
			$kit = trim($sign->getLine(1));
			

			// Kit名が存在するか
			if($this->kit->exists($kit)){
		
				$this->setItems($player, $kit);

				if($this->playerdata->get($player->getName()) == $kit) return ;

				$player->sendMessage('You are now ' . $kit);
				$this->playerdata->set($player->getName(), $kit);
				$this->playerdata->save();
			}else{
				$player->sendMessage('That kit was not found.');
			}

			if($e instanceof Cancellable){
				//ブロック配置の防止
				$e->setCancelled(true);
			}
		}
	
	}

	public function onMove(PlayerMoveEvent $e){
		$player = $e->getPlayer();
		$level = $player->getLevel();
		$blockUnderPlayer = ($level->getBlock($player->subtract(0, 0.5))->getId() == 0) ? $level->getBlock($player->subtract(0, 1.5)) : $level->getBlock($player->subtract(0, 0.5));
		if($blockUnderPlayer->getId() == Block::STAINED_GLASS && in_array($level->getName(), $this->getConfig()->get('worlds', []))){
			$kit = $this->playerdata->get($player->getName());

			$this->setItems($player, $kit);
			//$player->setHealth($player->getMaxHealth());
			//$player->setFood($player->getMaxFood());
		}
		//var_dump($this->getConfig()->get('worlds', ['pvp']));
	}

	public function setItems(Player $player, string $kit){
		if ($this->kit->exists($kit)) {

			try{
				$items = [];
				// kit.ymlからアイテムを取得
				foreach ($this->kit->get($kit)['Items'] as $itemId => $amount) {
					$item = Item::fromString($itemId);
					if ($item instanceof Item) {
						array_push($items, $item->setCount($amount));
					}
				}
				$player->getInventory()->setContents($items);

			}catch(\InvalidArgumentException $e){
				$this->getLogger()->warning('Item name is invalid');
				$this->getLogger()->warning($e->getMessage());
			}

			$armor = $player->getArmorInventory();

			try{
				$armor->setHelmet(Item::fromString($this->kit->getNested($kit . '.Armor.Helmet', 'Leather Helmet')));
				$armor->setChestplate(Item::fromString($this->kit->getNested($kit . '.Armor.ChestPlate', 'Leather Chestplate')));
				$armor->setLeggings(Item::fromString($this->kit->getNested($kit . '.Armor.Leggins', 'Leather Leggings')));
				$armor->setBoots(Item::fromString($this->kit->getNested($kit . '.Armor.Boots', 'Leather Boots')));
			}catch(\InvalidArgumentCountException $e){
				$this->getLogger()->warning('Armor name is invalid');
				$this->getLogger()->warning($e->getMessage());
			}

			$this->playerdata->set($player->getName(), $kit);
			$this->playerdata->save();
		}
	}
}