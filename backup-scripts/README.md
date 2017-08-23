BACKUP SCRIPTS
==============

This folder contains various shell scripts necessary for backing up files and data for ANS production servers.

## eXist-db Backups
The exist.sh script is for backing up eXist-db XML collections. The script will back the data up to /data/backups/eXist-db on the Rackspace dedicated production server and SCP to file to admin.numismatics.org:/usr/local/projects/backups/data. The password.txt should include the eXist-db admin password. The eXist-db collection name should be passed through as a command line argument to the shell script:

`sh exist.sh aod` will back up the Art of Devastation XML data.

## Cron jobs
The shell scripts should be added as occasional cron jobs. Typically, monthly backups are sufficient.
