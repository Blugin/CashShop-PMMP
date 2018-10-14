<?php
/**
 * @name CashShop
 * @author alvin0319
 * @main alvin0319\CashShop
 * @version 1.0.0
 * @api 4.0.0
 */
namespace alvin0319;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\item\Item;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat;

class CashShop extends PluginBase implements Listener{
    public $config;
    public $db;
    public $cash;
    public $p;
    public $list;
    public $l;
    public $prefix = '§d§l[ §f캐쉬 §d] §r';
    public static $instance = null;
    public function onLoad() : void{
        if (self::$instance === null) {
            self::$instance = $this;
        }
    }
    public function onEnable() : void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        @mkdir($this->getDataFolder());
        $this->config = new Config($this->getDataFolder() . "Cashes.yml", Config::YAML);
        $this->db = $this->config->getAll();
        $this->cash = new Config($this->getDataFolder() . "ShopDB.yml", Config::YAML);
        $this->p = $this->cash->getAll();
        $this->list = new Config($this->getDataFolder() . "BuyPrice.yml", Config::YAML);
        $this->l = $this->list->getAll();
        $admin = new PluginCommand('캐쉬관리', $this);
        $admin->setDescription('캐쉬 관리 명령어 입니다');
        $this->getServer()->getCommandMap()->register('캐쉬관리', $admin);
        $cash = new PluginCommand('캐쉬', $this);
        $cash->setDescription('캐쉬를 관리');
        $this->getServer()->getCommandMap()->register('캐쉬', $cash);
        $cashshop = new PluginCommand('캐쉬샵', $this);
        $cashshop->setDescription('캐쉬샵 오픈');
        $this->getServer()->getCommandMap()->register('캐쉬샵', $cashshop);
    }
    public static function getInstance() : CashShop{
        return self::$instance;
    }
    public function save() {
        $this->config->setAll($this->db);
        $this->config->save();
        $this->cash->setAll($this->p);
        $this->cash->save();
        $this->list->setAll($this->l);
        $this->list->save();
    }
    public function onDisable() : void{
        $this->save();
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if ($command->getName() === "캐쉬관리") {
            if (! $sender->isOp()) {
                $sender->sendMessage($this->prefix . '권한이 없습니다');
                return true;
            }
            if (! isset($args[0])) {
                $sender->sendMessage($this->prefix . '/캐쉬관리 캐쉬주기 <닉네임> <양>');
                $sender->sendMessage($this->prefix . '/캐쉬관리 캐쉬뺏기 <닉네임> <양>');
                $sender->sendMessage($this->prefix . '/캐쉬관리 캐쉬지급');
                $sender->sendMessage($this->prefix . '/캐쉬관리 상점추가 <상점이름> <제거할 캐쉬의 양>');
                $sender->sendMessage($this->prefix . '/캐쉬관리 상점제거');
                return true;
            }
            if ($args[0] === '캐쉬주기') {
                if (! isset($args[1])) {
                    $sender->sendMessage('닉네임을 입력해주세요');
                    return true;
                }
                if (! isset($args[2]) or ! is_numeric($args[2])) {
                    $sender->sendMessage($this->prefix . '양은 숫자로 입력해주세요');
                    return true;
                }
                $this->addCash($args[1], $args[2]);
                $this->save();
                $sender->sendMessage($this->prefix . '지급하였습니다');
            }
            if ($args[0] === '캐쉬뺏기') {
                if (! isset($args[1])) {
                    $sender->sendMessage($this->prefix . '닉네임을 입력해주세요');
                    return true;
                }
                if (! isset($args[2]) or ! is_numeric($args[2])) {
                    $sender->sendMessage($this->prefix . '양은 숫자로 입력해주세요');
                    return true;
                }
                $this->removeCash($args[1], $args[2]);
                $this->save();
                $sender->sendMessage($this->prefix . '뺏었습니다');
            }
            if ($args[0] === '캐쉬지급') {
                if (! isset($args[1]) or ! is_numeric($args[1])) {
                    $sender->sendMessage($this->prefix . '양은 숫자로 입력해주세요');
                    return true;
                }
                foreach($this->getServer()->getOnlinePlayers() as $players) {
                    $this->addCash($players->getName(), $args[1]);
                    $this->save();
                    $players->sendMessage($this->prefix . '관리자가 캐쉬를 ' . $args[1] . '만큼 지급하였습니다');
                    $this->getLogger()->info('관리자가 캐쉬를 ' . $args[1] . '만큼 지급하였습니다');
                }
            }
            if ($args[0] === '상점추가') {
                if (! isset($args[1])) {
                    $sender->sendMessage($this->prefix . '상점의 이름을 적어주세요');
                    return true;
                }
                if (isset($this->p[$args[1]])) {
                    $sender->sendMessage($this->prefix . '해당 상점이 이미 존재합니다');
                    return true;
                }
                if (! isset($args[2])) {
                    $sender->sendMessage($this->prefix . '캐쉬의 양을 적어주세요');
                    return true;
                }
                $item = $sender->getInventory()->getItemInHand();
                $this->p[$args[1]] = [ ];
                $this->p[$args[1]] ['id'] = $item->getId();
                $this->p[$args[1]] ['damage'] = $item->getDamage();
                $this->p[$args[1]] ['count'] = $item->getCount();
                $this->p[$args[1]] ['nbt'] = $this->getNBT($item);
                $this->l[$args[1]] ['cash'] = $args[2];
                $this->save();
                $sender->sendMessage($this->prefix . '등록되었습니다');
            }
            if ($args[0] === '상점제거') {
                $this->sendUI($sender, 313, $this->removeShop());
            }
        }
        if ($command->getName() === '캐쉬') {
            if (! isset($args[0])) {
                $sender->sendMessage($this->prefix . '/캐쉬 내정보');
                $sender->sendMessage($this->prefix . '/캐쉬 보기 <닉네임>');
                $sender->sendMessage($this->prefix . '/캐쉬 상점목록');
                return true;
            }
            if ($args[0] === '내정보') {
                $sender->sendMessage($this->prefix . '현재 내 캐쉬: ' . $this->getCash($sender->getName()));
            }
            if ($args[0] === '보기') {
                if (! isset($args[1])) {
                    $sender->sendMessage($this->prefix . '닉네임을 입력해주세요');
                    return true;
                }
                if (! isset($this->db[$args[1]] ['캐쉬'])) {
                    $sender->sendMessage($this->prefix . '그런 플레이어는 없습니다');
                    return true;
                }
                $sender->sendMessage($this->prefix . $args[1] . '님의 캐쉬: ' . $this->getCash($args[1]) . '원');
            }
            if ($args[0] === '상점목록') {
                foreach($this->p as $name => $cash) {
                    $sender->sendMessage($this->prefix . '상점이름: ' . $name . TextFormat::EOL . $this->prefix . '받을수 있는 아이템: ' . $cash['id'] . ':' . $cash['damage']);
                }
            }
        }
        if ($command->getName() === '캐쉬샵') {
            $this->sendUI($sender, 312, $this->CashData());
        }
        return true;
    }
    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        if (! isset($this->db[$name] ['캐쉬'])) {
            $this->db[strtolower($name)] ['캐쉬'] = 0;
            $this->save();
            $player->sendMessage($this->prefix . '현재 캐쉬는 0원입니다');
        }
    }
    public function onDataPacketReceive(DataPacketReceiveEvent $event) {
        $player = $event->getPlayer();
        $pk = $event->getPacket();
        if ($pk instanceof ModalFormResponsePacket) {
            $id = $pk->formId;
            $data = json_decode($pk->formData, true);
            if ($id === 312) {
                if (! isset($data[0])) {
                    $player->sendMessage($this->prefix . '칸을 정확히 입력해주세요');
                }
                if (! isset($this->p[$data[0]])) {
                    $player->sendMessage($this->prefix . '그런 상점은 없습니다');
                    return;
                }
                $this->buy($player, $data[0]);
            } else if ($id === 313) {
                if (! isset($data[0])) {
                    $player->sendMessage($this->prefix . '칸을 정확히 입력해주세요');
                    return;
                }
                if (! isset($this->p[$data[0]])) {
                    $player->sendMessage($this->prefix . '그런 상점은 없습니다');
                    return;
                }
                $this->remove($player, $data[0]);
            }
        }
    }
    public function sendUI(Player $player, $code, $data) {
        $pk = new ModalFormRequestPacket();
        $pk->formId = $code;
        $pk->formData = $data;
        $player->dataPacket($pk);
    }
    public function CashData() {
        $encode = [
            "type" => "custom_form",
            "title" => "UI",
            "content" => [
                [
                    "type" => "input",
                    "text" => "상점의 이름을 적어주세요",
                ]
            ]
        ];
        return json_encode($encode);
    }
    public function removeShop() {
        $encode = [
            "type" => "custom_form",
            "title" => "UI",
            "content" => [
                [
                    "type" => "input",
                    "text" => "상점의 이름을 적어주세요",
                ]
            ]
        ];
        return json_encode($encode);
    }
    public function buy(Player $player, $name) {
        $inv = $player->getInventory();
        if ($this->getCash($player->getName()) <= $this->l[$name] ['cash']) {
            $player->sendMessage($this->prefix . "캐쉬가 부족합니다.");
            return;
        }
        $this->db[strtolower($player->getName())] ['캐쉬'] -= $this->l[$name] ['cash'];
        $this->save();
        $item = $this->getItem($this->p[$name]);
        $player->getInventory()->addItem($item);
        $player->sendMessage($this->prefix . '구매하였습니다');
    }
    public function getCash($name) {
        return $this->db[strtolower($name)] ['캐쉬'];
    }
    public function getNBT($item) {
        return $item->getCompoundTag();
    }
    public function getItem($item) {
        return Item::jsonDeserialize($item);
    }
    public function remove($player, $name) {
        unset($this->p[$name]);
        unset($this->l[$name]);
        $this->save();
        $player->sendMessage($this->prefix . '제거되었습니다');
    }
    public function addCash($name, $amount) {
        $this->db[strtolower($name)] ['캐쉬'] += $amount;
        $this->save();
    }
    public function removeCash($name, $amount) {
        $this->db[strtolower($name)] ['캐쉬'] -= $amount;
        $this->save();
    }
}