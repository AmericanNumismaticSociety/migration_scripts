#!/bin/sh
#First create backup
PASSWORD=`cat password.txt`
COLLECTION=$1
echo "Generating backup."
cd /usr/local/projects/eXist-db
java -jar start.jar backup -u admin -p $PASSWORD -b /db/$COLLECTION -d /data/backups/eXist-db/$COLLECTION

#create zip
echo "Creating .gz file."
cd /data/backups/eXist-db/$COLLECTION
NOW=`date +"%Y%m%d%H%M%S"`
tar czvf $NOW.gz db

#SCP backups to admin
echo "Uploading to admin server."
scp -P 4858 $NOW.gz admin.numismatics.org:/usr/local/projects/backups/data/$COLLECTION/$NOW.gz

#delete directory

echo "Cleaning up."
rm -rf db

