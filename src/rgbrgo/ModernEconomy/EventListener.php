<?php
declare(strict_types=1);

namespace rgbrgo\ModernEconomy;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class EventListener implements Listener {
    public function __construct(private Main $plugin) {}

    public function onPacketReceive(DataPacketReceiveEvent $event): void {
        $pk = $event->getPacket();
        if ($pk instanceof ModalFormResponsePacket) {
            $player = $event->getOrigin()->getPlayer();
            if ($player === null || $pk->formData === null) return;
            $data = json_decode($pk->formData, true);

            if ($pk->formId === 200) {
                if ($data === 0) $this->plugin->menuTransferir($player);
                if ($data === 1) $this->plugin->menuRanking($player);
            }

            if ($pk->formId === 201) {
                $target = $data[1] ?? "";
                $amount = (float)($data[2] ?? 0);
                $prefix = $this->plugin->getMsg("prefixo");

                if ($target === "" || $amount <= 0) {
                    $player->sendMessage($prefix . $this->plugin->getMsg("erro_dados"));
                    return;
                }

                if ($this->plugin->reduceMoney($player->getName(), $amount)) {
                    $this->plugin->addMoney($target, $amount);
                    
                    $msgSub = str_replace(["{VALOR}", "{JOGADOR}"], [number_format($amount, 2), $target], $this->plugin->getMsg("sucesso_transferencia"));
                    $player->sendMessage($prefix . $msgSub);

                    $targetPlayer = $this->plugin->getServer()->getPlayerExact($target);
                    if ($targetPlayer) {
                        $msgRec = str_replace(["{VALOR}", "{JOGADOR}"], [number_format($amount, 2), $player->getName()], $this->plugin->getMsg("recebeu_transferencia"));
                        $targetPlayer->sendMessage($prefix . $msgRec);
                    }
                } else {
                    $player->sendMessage($prefix . $this->plugin->getMsg("saldo_insuficiente"));
                }
            }

            if ($pk->formId === 202) $this->plugin->menuEconomia($player);
        }
    }
}