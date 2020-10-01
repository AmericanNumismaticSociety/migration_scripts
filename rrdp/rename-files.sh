#!/bin/bash
filename="rename-files.txt"
 
while read line
do
	
	old=$(echo $line | awk -F';' '{print $1}'|tr '\n' ' ')
	new=$(echo $line | awk -F';' '{print $2}'|tr '\n' ' ')
	echo "Moving ${old} => ${new}"
	mv /f/rrdp-images/archive/$old /f/rrdp-images/archive/$new	
	#mv /data/images/archivesimages/thumbnail/$old /data/images/archivesimages/thumbnail/$new
	#mv /data/images/archivesimages/reference/$old /data/images/archivesimages/reference/$new	
	#mv /data/images/archivesimages/archive/$old /data/images/archivesimages/archive/$new		
	
	#the following files need to be simply deleted as they are duplicates and wrongly attributed
	rm /f/rrdp-images/archive/schaefer_364-1_b05_p127-0.jpg
	rm /f/rrdp-images/archive/schaefer_364-1_b05_p127-1.jpg
	rm /f/rrdp-images/archive/schaefer_285_b13_p014.jpg
	rm /f/rrdp-images/archive/schaefer_471_b11_p002_1.jpg
	rm /f/rrdp-images/archive/schaefer_471_b11_p003_1.jpg
	rm /f/rrdp-images/archive/schaefer_471_b11_p006_1.jpg
	rm /f/rrdp-images/archive/schaefer_471_b11_p007_1.jpg

done < $filename
