#!/bin/bash
filename="rename-files.txt"
 
while read line
do
	
	old=$(echo $line | awk -F';' '{print $1}'|tr '\n' ' ')
	new=$(echo $line | awk -F';' '{print $2}'|tr '\n' ' ')
	echo "Moving ${old} => ${new}"
	cp /data/images/archivesimages/thumbnail/$old ~/$new	
	
done < $filename
