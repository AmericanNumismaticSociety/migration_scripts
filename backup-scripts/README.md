BACKUP SCRIPTS
==============

This folder contains various shell scripts necessary for backing up files and data for ANS production servers.

## eXist-db Backups
The exist.sh script is for backing up eXist-db XML collections. The script will back the data up to /data/backups/eXist-db on the Rackspace dedicated production server and SCP to file to admin.numismatics.org:/usr/local/projects/backups/data. The password.txt should include the eXist-db admin password. The eXist-db collection name should be passed through as a command line argument to the shell script:

`sh exist.sh aod` will backup the Art of Devastation XML data.

## Cron jobs
The shell scripts should be added as occasional cron jobs. Typically, monthly or quarterly backups are sufficient, depending on how often the data are edited.

Below is the current crontab: 

    # m h  dom mon dow   command
    30 1 1 1,7 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh aod
    0 2 1 1,7 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh chrr
    30 2 1 1,7 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh crro
    0 3 1 1,3,6,9 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh xeac
    0 1 5 1,3,5,7,9,11 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh eaditor
    0 1 7 1,3,5,7,9,11 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh etdpub
    0 2 9 1,7 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh egypt
    0 1 11 1,3,6,9 * sh /usr/local/projects/migration_scripts/backup-scripts/exist.sh pella
    
Summary: Semiannual backups for Art of Devastation, CHRR, CRRO, and the Egyptian National Library. Quarterly backups for ANS Authorities (xEAC), PELLA. Bimonthly backups for Archer (EADitor) and the Digital Library (ETDPub). Monthly backup for OCRE, since corrections are common.
