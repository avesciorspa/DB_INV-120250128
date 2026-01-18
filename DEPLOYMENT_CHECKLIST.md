# Deployment Checklist - DB_INV System

## Pre-Deployment (Development Phase)

### Code Quality
- [x] All PHP files pass syntax validation
- [x] No undefined variables or undefined classes
- [x] Type hints on all public methods
- [x] PSR-12 naming conventions followed
- [x] No hardcoded credentials in code
- [x] All sensitive data in .env

### Database
- [x] Schema created and tested
- [x] Master data loaded (REPARTI, AREE)
- [x] Test data available
- [x] Foreign key constraints configured
- [x] Collation set to utf8mb4_0900_ai_ci
- [x] InnoDB engine configured

### Web UI
- [x] index.php loads without errors
- [x] printers.php loads without errors
- [x] CRUD forms render correctly
- [x] AJAX API endpoints respond
- [x] Validation works (client + server)
- [x] Bulk operations safe
- [x] No console errors in browser

### CLI
- [x] import_invc.php syntax valid
- [x] LockManager class complete
- [x] MarkerProcessor class complete
- [x] Error handling in place
- [x] Logging configured

### Documentation
- [x] README.md complete
- [x] ARCHITECTURE.md complete
- [x] TEST_REPORT.md complete
- [x] COMPLETION_SUMMARY.md complete
- [x] Code comments clear and helpful

---

## Pre-Deployment (Production Setup)

### Infrastructure
- [ ] DB2 system accessible (10.151.30.1:50000)
- [ ] MySQL 8.0+ installed and running
- [ ] CUPS server configured and accessible
- [ ] ODBC driver for DB2 installed
- [ ] PHP 8.1+ installed with PDO + ODBC modules
- [ ] Linux cron daemon running

### Configuration
- [ ] .env file created with REAL credentials
  - [ ] DB2_HOST, DB2_PORT, DB2_USER, DB2_PASS tested
  - [ ] MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASS tested
  - [ ] LOG_PATH directory exists and writable
  - [ ] CUPS_TEMP_DIR directory created
  - [ ] LOCK_FILE path writable

### MySQL Setup
- [ ] Database `DB_INV` created
- [ ] User `DB_INV` created with proper permissions
- [ ] Schema loaded from db/init.sql
- [ ] Master data loaded from db/extend-schema.sql
- [ ] Backup strategy configured

### Web Server
- [ ] Apache/Nginx configured with /var/www/DB_INV/web as document root
- [ ] PHP-FPM or CGI handler configured
- [ ] Directory permissions: 755 for directories, 644 for files
- [ ] index.php loads at http://yourdomain/
- [ ] API endpoints accessible: /api/get-conf.php, /api/get-printer.php

### Cron Setup
- [ ] Cron job added: `* * * * * /usr/bin/php /var/www/DB_INV/bin/import_invc.php`
- [ ] Log file location configured
- [ ] Cron output redirected to logs/cron.log
- [ ] Cron user permissions sufficient
- [ ] Test run successful

### CUPS Printers
- [ ] At least one printer configured in STAMPANTI table
- [ ] Printer IP accessible
- [ ] Printer CODA_CUPS matches actual CUPS queue name
- [ ] lpr command working: `lpr -P queue_name file.txt`

### Logging & Monitoring
- [ ] /var/www/DB_INV/logs directory exists (755)
- [ ] Log rotation configured (logrotate)
- [ ] Log file size limit set
- [ ] Log retention policy defined
- [ ] Error alert email configured (optional)

### Backups
- [ ] Database backup script created
- [ ] Backup schedule configured (daily)
- [ ] Backup storage location defined
- [ ] Disaster recovery plan documented

---

## Testing Checklist

### Unit Tests
- [ ] DB2Connection instantiates
- [ ] MySQLConnection instantiates and queries work
- [ ] All 6 Repository classes instantiate
- [ ] LockManager acquire/release works
- [ ] Logger writes to file
- [ ] Config loads .env correctly
- [ ] MarkerProcessor instantiates
- [ ] PrinterManager instantiates

### Integration Tests
- [ ] CLI import runs without errors
- [ ] DB2 INVC records read successfully
- [ ] CONTEGGI table populated
- [ ] Marker detection works
- [ ] Area update marker works
- [ ] Print marker generates TXT file
- [ ] CUPS lpr command executes
- [ ] Lock prevents parallel runs

### Web UI Tests
- [ ] Operators CRUD working
- [ ] Printers CRUD working
- [ ] Bulk delete working
- [ ] Bulk update area working
- [ ] Bulk update printer working
- [ ] Form validation working
- [ ] API endpoints returning JSON
- [ ] Error handling graceful

### End-to-End Tests
- [ ] Import data from DB2 via CLI
- [ ] Verify CONTEGGI populated
- [ ] Detect and process marker AREA
- [ ] Verify CONF_OPERATORE.ID_AREA updated
- [ ] Detect and process marker STAMPA
- [ ] Verify print file generated
- [ ] Verify print sent to CUPS
- [ ] Verify CONTEGGI.STAMPATO=1 after print

### Performance Tests
- [ ] Import completes in <60 seconds (target)
- [ ] Lock timeout adequate (5 minutes default)
- [ ] Memory usage acceptable
- [ ] Database query performance acceptable
- [ ] CUPS queue response time acceptable

---

## Production Readiness

### Code
- [x] No syntax errors
- [x] No undefined variables
- [x] Comprehensive error handling
- [x] Security measures implemented
- [x] Documentation complete
- [ ] Code review completed by team
- [ ] Security audit completed

### Infrastructure
- [ ] Load testing completed
- [ ] Failover plan documented
- [ ] Monitoring alerts configured
- [ ] System capacity verified
- [ ] Backup/restore tested

### Operations
- [ ] Runbook created
- [ ] Escalation procedures defined
- [ ] Contact list updated
- [ ] Change management procedure followed
- [ ] Communication plan for downtime

---

## Post-Deployment

### Monitoring
- [ ] Import success rate monitored
- [ ] Log file size monitored
- [ ] Database size monitored
- [ ] CUPS print queue monitored
- [ ] Lock file behavior monitored
- [ ] Error rate tracked

### Maintenance
- [ ] Database maintenance scheduled
- [ ] Log rotation tested
- [ ] Backup verification scheduled
- [ ] Security updates monitored
- [ ] Performance baseline established

### Documentation
- [ ] Runbook updated with real URLs/contacts
- [ ] Architecture diagram shared with team
- [ ] Test procedures documented
- [ ] Troubleshooting guide created
- [ ] FAQ updated

### Optimization
- [ ] Query performance analyzed
- [ ] Slow queries logged
- [ ] Index usage reviewed
- [ ] Cache effectiveness measured
- [ ] Resource utilization tracked

---

## Rollback Plan

### If Import Fails
1. Stop cron job: `crontab -e` (comment out line)
2. Check logs: `tail -f /var/www/DB_INV/logs/inv_import.log`
3. Verify DB2 connectivity: Test ODBC connection
4. Check MySQL: Verify connection + table state
5. Roll back CONTEGGI: `TRUNCATE TABLE CONTEGGI;`
6. Fix issue in code
7. Re-run manual import: `php /var/www/DB_INV/bin/import_invc.php`

### If CUPS Fails
1. Check printer status: `lpstat -p -d`
2. Check queue: `lpq -P queue_name`
3. Update printer IP in STAMPANTI table
4. Manually test: `lpr -P queue_name /test/file.txt`
5. Clear stuck jobs: `cancel -a`
6. Restart CUPS: `sudo systemctl restart cups`

### If Web UI Fails
1. Check PHP: `php -v`
2. Check MySQL: `mysql -u user -p db -e "SELECT 1;"`
3. Check permissions: `ls -la /var/www/DB_INV/`
4. Check error log: `/var/log/apache2/error.log` (or PHP error log)
5. Test directly: `php -r "require 'web/bootstrap.php'; echo 'OK';"`

---

## Sign-Off

- [ ] Development Lead: _______________ Date: _______
- [ ] QA Lead: _______________ Date: _______
- [ ] Operations Lead: _______________ Date: _______
- [ ] Security Lead: _______________ Date: _______
- [ ] Project Manager: _______________ Date: _______

---

**Document Version**: 1.0  
**Last Updated**: 2025-01-18  
**Next Review**: 2025-02-18
