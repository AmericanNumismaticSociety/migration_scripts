#!/bin/sh

BASE=$PWD;
for dir in $BASE/ebooks/*;
do
	cd $dir;
	echo "$dir";
	#ls;
	# create folders
	mkdir -p archive
	mkdir -p reference
	mkdir -p thumbnail
	
	#move TEI file
	mv *.xml $BASE/tei;

	#convert into thumbnails and reference images
	for i in *.jpg;
	do
		convert $i -density 72x72 -resize 600x600\> -strip reference/$i; 
		convert $i -density 72x72 -resize 180x180\> -strip thumbnail/$i;
		mv $i archive;
	done;
	cd $BASE;	
done;
