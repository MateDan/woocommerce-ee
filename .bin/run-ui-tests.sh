#!/bin/bash
set -e # Exit with nonzero exit code if anything fails
#get version
export VERSION=`cat VERSION`

#start payment-sdk
php -S localhost:8080 > /dev/null &

# download and install ngrok
curl -s https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip > ngrok.zip
unzip ngrok.zip
chmod +x $PWD/ngrok
# Download json parser for determining ngrok tunnel
curl -sO http://stedolan.github.io/jq/download/linux64/jq
chmod +x $PWD/jq

# Open ngrok tunnel
$PWD/ngrok authtoken $NGROK_TOKEN
TIMESTAMP=$(date +%s)
$PWD/ngrok http 8080 -subdomain=${TIMESTAMP}${GATEWAY}> /dev/null &

# sleep to allow ngrok to initialize
sleep 150

# extract the ngrok url
export NGROK_URL=$(curl -s localhost:4040/api/tunnels/command_line | jq --raw-output .public_url)

#create the plugin package for installation
bash .bin/generate-release-package.sh

#start shopsystem and demoshop
bash .bin/start-shopsystem.sh

GROUP='default_gateway'

if [[ ${GATEWAY} = "TEST-SG" ]] || [[ ${GATEWAY} = "SECURE-TEST-SG" ]]; then
  GROUP='sg_gateway'
fi

#run tests
vendor/bin/codecept run acceptance -g ${GROUP} --html