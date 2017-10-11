<?php

namespace fatutils\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;

use fatutils\ui\impl\LanguageWindow;
use fatutils\players\PlayersManager;

class LanguageCommand extends PluginBase implements CommandExecutor
{

    public function __construct()
    {
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args): bool
    {
        if ($sender instanceof \pocketmine\Player)
        {
            if (PlayersManager::getInstance()->fatPlayerExist($sender))
            {
                if (count($args) == 0)
                {
                    (new LanguageWindow($sender));
                }
            }
            else
            {
                $sender->sendMessage("An error occured !");
            }
        }
        return true;
    }

}