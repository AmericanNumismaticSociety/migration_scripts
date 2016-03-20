#!/bin/sh

for i in `ls ead`; do 
 mkdir -p processed/$i;
 java -jar /usr/local/projects/nomisma/script/saxon9.jar  -xsl:/home/komet/ans_migration/nyead/process.xsl -s:ead/$i -o:processed;
done