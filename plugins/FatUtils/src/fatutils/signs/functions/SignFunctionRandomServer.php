<?php

namespace fatutils\signs\functions;

use pocketmine\Player;
use fatcraft\loadbalancer\LoadBalancer;

class SignFunctionRandomServer extends SignFunction
{

    private $type = null;
    private $canJoin = false;

    public function __construct(&$sign)
    {
        parent::__construct("SignFunctionServer", $sign);
        if (isset($this->sign->data["type"]))
        {
            $this->type = $this->sign->data["type"];
        }
        else
        {
            throw Exception("SignFunctionServer has no server!");
        }
        $sign->text[0] = "Game " . $this->type;
    }

    public function onTick(int $currentTick)
    {
        if ($currentTick % 20 == 0)// update every seconds
        {
            $this->sign->text[1] = "";
            $this->sign->text[2] = "";
            $this->sign->text[3] = "";
            $online = 0;
            $max = 0;
            $servers = LoadBalancer::getInstance()->getServersByType($this->type);
            if ($servers !== null and count($servers) > 0)
            {
                foreach($servers as $server)
                {
                    $online += $server["online"];
                    $max += $server["max"];
                }
            }
            if ($max == 0)
            {
                $this->canJoin = false;
                $this->sign->text[2] = "§4CNo Server";
            }
            else
            {
                $this->sign->text[1] = $online . "/" . $max;
                if ($online < $max)
                {
                    $this->canJoin = true;
                    $this->sign->text[3] = "Tap to join";
                }
                else
                {
                    $this->canJoin = false;
                    $this->sign->text[2] = "§4CServers Full";
                }
            }
            $this->sign->updateTexte();
        }
    }

    public function onInterract(Player $player, int $p_Index = -1)
    {
        if ($this->canJoin and $player->hasPermission("sign.network.serverjoin"))
        {
            LoadBalancer::getInstance()->balancePlayer($player, $this->type);
        }
        else
        {
            $player->sendMessage("You can't dirrectly join this server now.");
        }
    }
}

