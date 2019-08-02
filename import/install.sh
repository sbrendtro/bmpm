#!/bin/bash
VERSION=$1
FILE="bmver$VERSION.zip"

if [ -f  "$FILE" ]; then
    mkdir -p $VERSION \
      && cd $VERSION \
      && unzip -q -o ../$FILE \
      && cd .. \
      && sed -i 's/\?>//g' $VERSION/ash/*.php $VERSION/gen/*.php $VERSION/sep/*.php $VERSION/*.php \
      && mkdir -p ../language/$VERSION/sep \
      && mkdir -p ../language/$VERSION/gen \
      && mkdir -p ../language/$VERSION/ash \
      && php build.php gen $VERSION \
      && php build.php sep $VERSION \
      && php build.php ash $VERSION \
      && rm -rf $VERSION
else
    echo "Unable to find the file $FILE"
fi
