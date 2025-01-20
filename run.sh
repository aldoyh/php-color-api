#!/bin/bash

# Run the application
# check if php is installed
if ! [ -x "$(command -v php)" ]; then
  echo 'Error: php is not installed.' >&2
  exit 1
fi

# check if composer is installed
if ! [ -x "$(command -v composer)" ]; then
  echo 'Error: composer is not installed.' >&2
  exit 1
fi

# run php server on port 8081 if taken then increment
port=8081
while true; do
  if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
    port=$((port+1))
  else
    break
  fi
done

php -S localhost:$port -t public public/index.php