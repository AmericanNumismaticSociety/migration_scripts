#!/bin/sh
cd /home/komet/ans_migration/fonts/svg/rename
for i in `ls *.svg`
 do num=`echo $i | sed -E 's/monogram\.lorber\.([0-9]+)(_[1-9])?\.svg/\1/g'`
    suffix=`echo $i | sed -E 's/monogram\.lorber\.[0-9]+((_[1-9])?\.svg)/\1/g'`
    int=`expr $num - 10`;
    #echo $int " " $suffix;
    newFile=monogram.lorber.$int$suffix
    echo "$i -> $newFile"; 
    cp $i /home/komet/ans_migration/fonts/svg/out/$newFile
    done