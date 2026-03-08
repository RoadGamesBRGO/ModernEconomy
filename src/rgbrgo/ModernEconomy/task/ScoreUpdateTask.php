<?php
declare(strict_types=1);

namespace rgbrgo\ModernEconomy\task;

use pocketmine\scheduler\Task;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\player\Player;
use rgbrgo\ModernEconomy\Main;

class ScoreUpdateTask extends Task {
    public function __construct(private Main $plugin) {}

    public function onRun(): void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $this->updateScore($player);
        }
    }

    private function updateScore(Player $player): void {
    $name = $player->getName();
    $money = number_format($this->plugin->myMoney($name), 2, ',', '.');
    $objName = "modern_hud";

    $removePk = new \pocketmine\network\mcpe\protocol\RemoveObjectivePacket();
    $removePk->objectiveName = $objName;
    $player->getNetworkSession()->sendDataPacket($removePk);

    $pk = new \pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket();
    $pk->displaySlot = "sidebar";
    $pk->objectiveName = $objName;
    $pk->displayName = "  §l§bMODERN §fNET  "; // Título mais curto e centralizado
    $pk->criteriaName = "dummy";
    $pk->sortOrder = 0;
    $player->getNetworkSession()->sendDataPacket($pk);

    // Layout Arredondado (Usando símbolos como ╭ ─ ╮)
    $lines = [
        6 => "§b╭────────────────╮",
        5 => "  §7👤 §fUser: §b$name",
        4 => "  §7💰 §fSaldo: §a$ $money",
        3 => "  §7📍 §fSetor: §ePlots",
        2 => "§b╰────────────────╯",
        1 => "     §8modernplot.net"
    ];

    foreach ($lines as $score => $text) {
        $entry = new \pocketmine\network\mcpe\protocol\types\ScorePacketEntry();
        $entry->objectiveName = $objName;
        $entry->type = \pocketmine\network\mcpe\protocol\types\ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = str_pad($text, 25, " ", STR_PAD_RIGHT); // Alinhamento forçado
        $entry->score = $score;
        $entry->scoreboardId = $score;

        $pkScore = new \pocketmine\network\mcpe\protocol\SetScorePacket();
        $pkScore->type = \pocketmine\network\mcpe\protocol\SetScorePacket::TYPE_CHANGE;
        $pkScore->entries[] = $entry;
        $player->getNetworkSession()->sendDataPacket($pkScore);
    }
}

    private function setLine(Player $player, string $text, int $id, string $objName): void {
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $objName;
        $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = $text;
        $entry->score = $id;
        $entry->scoreboardId = $id;

        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_CHANGE;
        $pk->entries[] = $entry;
        $player->getNetworkSession()->sendDataPacket($pk);
    }
}