#!/bin/sh
#First create backup
PASSWORD=`cat  /usr/local/projects/migration_scripts/backup-scripts/password.txt`
COLLECTION=$1
echo "Generating backup."
cd /opt/eXist-db/bin
sh backup.sh -u admin -p $PASSWORD -b /db/$COLLECTION -d /data/backups/eXist-db/$COLLECTION -ouri=xmldb:exist://localhost:8888/exist/xmlrpc

#create zip
echo "Creating zip file."
cd /data/backups/eXist-db/$COLLECTION
NOW=`date +"%Y%m%d%H%M%S"`
zip $NOW.zip db

#delete directory

echo "Cleaning up."
rm -rf db

