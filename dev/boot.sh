#!/bin/sh

wget https://raw.github.com/splitbrain/dokuwiki-travis/master/travis.sh
chmod 755 travis.sh
. ./travis.sh


git clone https://"${TOKEN}"@github.com/ComboStrap/combo_dev.git combo_dev
ln -s combo_dev/combo_test lib/plugins/combo/_test

