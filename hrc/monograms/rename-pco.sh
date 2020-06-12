#!/bin/sh

for i in `ls /home/komet/ans_migration/fonts/svg/rename`
 do num=`echo $i | sed -E 's/monogram\.lorber\.([0-9]+)(_?[1-9]?\.svg)/\1/g'`
    suffix=`echo $i | sed -E 's/monogram\.lorber\.[0-9]+(_?[1-9]?\.svg)/\1/g'`
    int=`expr $num - 10`;
    mv /home/komet/ans_migration/fonts/svg/rename/$i /home/komet/ans_migration/fonts/svg/rename/monogram.lorber.$int$suffix
    done