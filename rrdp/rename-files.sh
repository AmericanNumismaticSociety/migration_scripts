#!/bin/bash
filename="rename-files.txt"
 
while read line
do
	
	old=$(echo $line | awk -F';' '{print $1}'|tr '\n' ' ')
	new=$(echo $line | awk -F';' '{print $2}'|tr '\n' ' ')
	echo "Moving ${old} => ${new}"
	mv /data/images/archivesimages/thumbnail/$old /data/images/archivesimages/thumbnail/$new
	mv /data/images/archivesimages/reference/$old /data/images/archivesimages/reference/$new	
	mv /data/images/archivesimages/archive/$old /data/images/archivesimages/archive/$new		
	
done < $filename
