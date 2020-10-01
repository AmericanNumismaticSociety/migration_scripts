#!/bin/bash
filename="concordance.txt"
 
while read line
do
	
	old=$(echo $line | awk -F';' '{print $1}'|tr '\n' ' ')
	new=$(echo $line | awk -F';' '{print $2}'|tr '\n' ' ')
	echo "Moving ${old} => ${new}"
	cp /f/rrdp-images/archive/$old /f/rrdp-images/new/$new
	
done < $filename
