#!/bin/sh

BASE=$PWD;
for dir in */;
do
	cd $BASE/"$dir";
	#ls;
	# create folders
	mkdir -p archive
	mkdir -p reference
	mkdir -p thumbnail

	#convert into thumbnails and reference images
	for i in *.jpg;
	do
		convert $i -density 72x72 -resize 600x600\> -strip reference/$i; 
		convert $i -density 72x72 -resize 180x180\> -strip thumbnail/$i;
		mv $i archive;
	done;
	cd $BASE;	
done;
