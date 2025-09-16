#!/bin/bash
cd /home/steam/gmod
echo "Starting GMod server..."
./srcds_run -game garrysmod +maxplayers 12 +map gm_flatgrass +rcon_password "TonMotDePasse"
