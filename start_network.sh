# /bin/bash

# function to quickly start a network container
# start_docker <name> <port> <image>
start_docker()
{
    docker run \
--rm --name "$1-$2" \
--hostname "$1-$2" \
--env SERVER_NAME="$1-$2" \
--env SERVER_PORT="$3" \
--env SERVER_TYPE="$1" \
--env SERVER_ID="$2" \
--publish $3:$3 \
--publish $3:$3/udp \
--link mysql:mysql \
-d $4
}

# start front load-balancer
start_docker lb 1 19132 fatcraft/pocketmine:lb

# start lobbies
start_docker lobby 1 19133 fatcraft/pocketmine:lobby
start_docker lobby 2 19134 fatcraft/pocketmine:lobby

# start games




## DEBUG
#docker run --rm --name lb-1 --hostname lb-1 --env SERVER_NAME=lb-1 --env SERVER_PORT=19132 --env SERVER_TYPE=lb --env SERVER_ID=1 --publish 19132:19132 --publish 19132:19132/udp --link mysql:mysql -ti fatcraft/pocketmine:lb

#docker run --rm --name lobby1 --hostname lobby1 --env SERVER_NAME=lobby1 --env SERVER_PORT=19133 --publish 19133:19133 --publish 19133:19133/udp -ti fatcraft/pocketmine:lobby
#docker run --rm --name lobby2 --hostname lobby2 --env SERVER_NAME=lobby2 --env SERVER_PORT=19134 --publish 19134:19134 --publish 19134:19134/udp -ti fatcraft/pocketmine:lobby