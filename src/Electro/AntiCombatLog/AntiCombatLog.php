<?php

namespace Electro\AntiCombatLog;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\scheduler\ClosureTask;
use pocketmine\event\server\CommandEvent;

class AntiCombatLog extends PluginBase implements Listener{

    public array $playersInCombat = [];
    public array $bannedCommands = [];
    public string $bannedCommandMsg;
    public string $enteredCombatMsg;
    public string $exitCombatMsg;
    public float $combatTime;
    public bool $quitKill;
    public bool $banAllCommands;

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        foreach ($this->getConfig()->get("BannedCommands") as $cmd)
        {
            $this->bannedCommands[] = $cmd;
        }
        $this->bannedCommandMsg = $this->getConfig()->get("BannedCommandMsg");
        $this->enteredCombatMsg = $this->getConfig()->get("EnterCombatMsg");
        $this->exitCombatMsg = $this->getConfig()->get("ExitCombatMsg");
        $this->combatTime = $this->getConfig()->get("CombatTime");
        $this->quitKill = $this->getConfig()->get("KillOnLogout");
        $this->banAllCommands = $this->getConfig()->get("BanAllCommands");
        $this->combatTask();
    }

    public function onDamage(EntityDamageByEntityEvent $event)
    {
        $player = $event->getEntity();
        $damager = $event->getDamager();

        if ($event->isCancelled()) return;
        if (!$player instanceof Player || !$damager instanceof Player) return;
        if ($player->isCreative() || $damager->isCreative()) return;

        foreach ([$player, $damager] as $player)
        {
            if (!isset($this->playersInCombat[$player->getName()]))
            {
                $player->sendMessage($this->enteredCombatMsg);
            }
            $this->playersInCombat[$player->getName()] = $this->combatTime;
        }
    }

    public function onCommandPreprocess(CommandEvent $event)
    {
        $player = $event->getSender();
        $msg = $event->getCommand();
        if (!isset($this->playersInCombat[$player->getName()])) return;
        if ($this->banAllCommands)
        {
            $player->sendMessage($this->bannedCommandMsg);
            $event->cancel();
            return;
        }
        $msg = explode(" ", $msg);
        if(!in_array($msg[0], $this->bannedCommands)) return;
		$player->sendMessage($this->bannedCommandMsg);
        $event->cancel();
    }

    public function onQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        if (!isset($this->playersInCombat[$player->getName()])) return;
        if (!$this->quitKill) return;
        $player->kill();
    }

    public function combatTask()
    {
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() {
                foreach ($this->playersInCombat as $playerName => $time)
                {
                    $time--;
                    $this->playersInCombat[$playerName]--;
                    if ($time <= 0)
                    {
                        unset($this->playersInCombat[$playerName]);
                        $player = $this->getServer()->getPlayerExact($playerName);
                        if ($player instanceof Player)
                        {
                            $player->sendMessage($this->exitCombatMsg);
                        }
                    }
                }
            }
        ), 20);
    }
}
