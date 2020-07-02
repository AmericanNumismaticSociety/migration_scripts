#!/bin/bash
filename="rename-files.txt"
 
while read line
do
	readarray -d ';' -t arr <<<"$line"
	newvar=$(echo "${arr[1]}"|tr '\n' ' ')

	echo "Moving ${arr[0]} => ${newvar}"
	cp /data/images/archivesimages/thumbnail/${arr[0]} ~/${newvar}
	
done < $filename
