#!/bin/sh

# Script can be used to install Composer

COMPOSER_PATH="/usr/bin/composer"

# check if composer exists already
if [[ -x ${COMPOSER_PATH} ]]; then
  ${COMPOSER_PATH} "$@"
  exit 0
fi

echo "Installing composer"
# install composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" > /dev/null
if [ ! -z "${COMPOSER_VERSION}" ]; then
  php composer-setup.php --version=${COMPOSER_VERSION} > /dev/null
else
  # install latest composer version
  php composer-setup.php > /dev/null
fi
rm ./composer-setup.php
mv ./composer.phar ${COMPOSER_PATH}
${COMPOSER_PATH} --version

${COMPOSER_PATH} "$@"
