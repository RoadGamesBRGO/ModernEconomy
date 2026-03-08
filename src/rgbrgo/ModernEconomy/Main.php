<?php
declare(strict_types=1);

namespace rgbrgo\ModernEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\{Command, CommandSender};
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use rgbrgo\ModernEconomy\task\ScoreUpdateTask;

class Main extends PluginBase {
    private static self $instance;
    private Config $database;

    protected function onEnable(): void {
        self::$instance = $this;
        $this->saveDefaultConfig(); // Cria a config.yml se não existir
        
        @mkdir($this->getDataFolder());
        $this->database = new Config($this->getDataFolder() . "balances.yml", Config::YAML);
        
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new ScoreUpdateTask($this), 20);
    }

    public static function getInstance(): self { return self::$instance; }

    // Pega mensagens da config
    public function getMsg(string $key): string {
        return (string) $this->getConfig()->getNested("mensagens.$key", "Erro de config");
    }

    // --- API DE ECONOMIA ---
    public function myMoney(string $playerName): float {
        $playerName = strtolower($playerName); // Força minúsculo
        $inicial = (float) $this->getConfig()->get("valor_inicial", 500.0);
        return (float) $this->database->get($playerName, $inicial);
    }

    public function addMoney(string $playerName, float $amount): void {
        $playerName = strtolower($playerName); // Força minúsculo
        $this->database->set($playerName, $this->myMoney($playerName) + $amount);
        $this->database->save();
    }

    public function reduceMoney(string $playerName, float $amount): bool {
        $playerName = strtolower($playerName); // Força minúsculo
        $current = $this->myMoney($playerName);
        if ($current >= $amount) {
            $this->database->set($playerName, $current - $amount);
            $this->database->save();
            return true;
        }
        return false;
    }

    public function getAllBalances(): array {
        return $this->database->getAll();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Use este comando dentro do jogo.");
            return true;
        }

        switch ($command->getName()) {
            case "money":
            case "pay":
                $this->menuEconomia($sender);
                return true;
            
            case "setmoney":
                if (!$sender->hasPermission("moderneconomy.admin")) {
                    $sender->sendMessage("§cVocê não tem permissão para isso.");
                    return true;
                }
                if (!isset($args[1])) {
                    $sender->sendMessage("§cUse: /setmoney <jogador> <valor>");
                    return true;
                }
                $this->database->set(strtolower($args[0]), (float)$args[1]);
                $this->database->save();
                $sender->sendMessage("§aSaldo de §f" . $args[0] . " §adefinido para §e$" . $args[1]);
                return true;
        }
        return false;
    }

    // --- INTERFACES ---
    public function menuEconomia(Player $player): void {
        $money = number_format($this->myMoney($player->getName()), 2, ',', '.');
        $data = [
            "type" => "form",
            "title" => "§l§9BANCO §0MODERNO",
            "content" => "§7Olá, §b{$player->getName()}§7!\n\n§fSeu saldo atual: §a$ $money\n§7Selecione uma operação:",
            "buttons" => [
                ["text" => "§l§0TRANSFERIR\n§8Enviar dinheiro", "image" => ["type" => "path", "data" => "textures/ui/pay_button"]],
                ["text" => "§l§0RANKING\n§8Mais ricos", "image" => ["type" => "path", "data" => "textures/ui/recap_glyph_color_2"]],
                ["text" => "§l§cSAIR\n§8Fechar menu"]
            ]
        ];
        $pk = ModalFormRequestPacket::create(200, json_encode($data));
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    public function menuTransferir(Player $player): void {
        $data = [
            "type" => "custom_form",
            "title" => "§l§9TRANSFERÊNCIA",
            "content" => [
                ["type" => "label", "text" => "§7Envie dinheiro para qualquer jogador."],
                ["type" => "input", "text" => "§fNome do Jogador:", "placeholder" => "Ex: rgbrgo"],
                ["type" => "input", "text" => "§fValor:", "placeholder" => "Ex: 100"]
            ]
        ];
        $pk = ModalFormRequestPacket::create(201, json_encode($data));
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    public function menuRanking(Player $player): void {
        $all = $this->getAllBalances();
        arsort($all);
        $all = array_slice($all, 0, 10);

        $text = "§6§lTOP 10 MAIS RICOS:\n\n";
        $pos = 1;
        foreach ($all as $name => $bal) {
            $text .= "§e{$pos}º §f" . ucfirst($name) . " §7- §a$ " . number_format($bal, 2, ',', '.') . "\n";
            $pos++;
        }

        $data = [
            "type" => "form",
            "title" => "§l§6RANKING FINANCEIRO",
            "content" => $text,
            "buttons" => [["text" => "§lVOLTAR"]]
        ];
        $pk = ModalFormRequestPacket::create(202, json_encode($data));
        $player->getNetworkSession()->sendDataPacket($pk);
    }
}