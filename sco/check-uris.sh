#!/bin/sh

for i in `cat mint-URIs.tsv` 
	do
		HTTP_STATUS="$(curl -IL --silent $i | grep HTTP )";
		echo "$i\t${HTTP_STATUS}" >> out.txt;

done
