#!/bin/sh

. /usr/local/etc/firesense.conf

HOSTNAME=$(hostname)

TENTATIVA=1

while [ $TENTATIVA -le 3 ]
do

    RESPOSTA=$(curl -s --connect-timeout 10 --max-time 30 -F "client_id=${CLIENT_ID}" -F "client_secret=${CLIENT_SECRET}" -F "hostname=${HOSTNAME}" -F "config=@/cf/conf/config.xml" "${SERVER}")

    if echo "$RESPOSTA" | grep -q '"status":"ok"'; then
        break
    fi

    sleep 5

    TENTATIVA=$((TENTATIVA+1))

done

echo "$RESPOSTA"

if echo "$RESPOSTA" | grep -q '"status":"ok"'; then

    ARQUIVO=$(echo "$RESPOSTA" | sed -n 's/.*"arquivo":"\([^"]*\)".*/\1/p')

    echo "$(date '+%Y-%m-%d %H:%M:%S') | OK | $ARQUIVO" >> /var/log/firesense.log

else

    echo "$(date '+%Y-%m-%d %H:%M:%S') | ERRO | $RESPOSTA" >> /var/log/firesense.log

fi
