<?php

namespace naoki1510\KitPlugin;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\block\Block;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\event\Cancellable;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\inventory\PlayerInventory;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener{

	/** @var Config */
	private $kit;

	public function onEnable(){
		// 起動時のメッセージ
		$this->getLogger()->info("§eKitPlugin was loaded.");

		// kit.yml作成
		// Configは$this->getConfig()
		if (!file_exists($this->getDataFolder())){
			@mkdir($this->getDataFolder());

			//ToDo: Resourseフォルダの活用
			//$resourse = $this->getResource('kit.yml');
			//file_put_contents($this->getDataFolder() . 'kit.yml', fread($resourse, );
		} 
		$this->kit = new Config($this->getDataFolder() . 'kit.yml', Config::YAML);
		$this->kit->save();

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
			$kit = $sign->getLine(1);

			// Kit名が存在するか
			if($this->kit->exists($kit) && isset($this->kit->get($kit)['Items'])){
				$items = [];

				// kit.ymlからアイテムを取得
				foreach ($this->kit->get($kit)['Items'] as $itemId => $amount) {
					$item = Item::fromString($itemId)->setCount($amount);
					if($item instanceof Item){
						array_push($items, $item);
					}
				}

				// Playerにアイテムをセット
				$player->getInventory()->setContents($items);
				$player->sendMessage('You are now ' . $kit);
			}else{
				$player->sendMessage('That kit was not found.');
			}
			if($e instanceof Cancellable){
				
				//ブロック配置の防止
				$e->setCancelled(true);
			}
		}
	
	}
}