BACKUP SCRIPTS
==============

This folder contains various shell scripts necessary for backing up files and data for ANS production servers.

## eXist-db Backups
The exist.sh script is for backing up eXist-db XML collections. The script will back the data up to /data/backups/eXist-db on the Rackspace dedicated production server and SCP to file to admin.numismatics.org to /usr/local/projects/backups/data.

## Cron jobs
