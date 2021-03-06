#!/bin/sh
#First create backup
PASSWORD=`cat  /usr/local/projects/migration_scripts/backup-scripts/password.txt`
COLLECTION=$1
echo "Generating backup."
cd /usr/local/projects/eXist-db
java -jar start.jar backup -u admin -p $PASSWORD -b /db/$COLLECTION -d /data/backups/eXist-db/$COLLECTION

#create zip
echo "Creating .gz file."
cd /data/backups/eXist-db/$COLLECTION
NOW=`date +"%Y%m%d%H%M%S"`
tar czvf $NOW.tar.gz db

#SCP backups to admin
echo "Uploading to admin server."
scp -P 4858 $NOW.tar.gz admin.numismatics.org:/usr/local/projects/backups/data/$COLLECTION/$NOW.tar.gz

#delete directory

echo "Cleaning up."
rm -rf db

