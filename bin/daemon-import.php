#!/usr/bin/env php
<?php
try {
    spl_autoload_register(function ($c) {
        if (strpos($c, 'App\\') === 0) {
            $f = '/var/www/DB_INV/src/' . str_replace('\\', '/', substr($c, 4)) . '.php';
            if (is_file($f)) require_once $f;
        }
    });
    
    require_once '/var/www/DB_INV/src/Config.php';
    require_once '/var/www/DB_INV/src/Logger.php';
    require_once '/var/www/DB_INV/src/Database/DB2Connection.php';
    require_once '/var/www/DB_INV/src/Database/MySQLConnection.php';
    require_once '/var/www/DB_INV/src/Import/Importer.php';
    require_once '/var/www/DB_INV/src/Print/StampaManager.php';
    
    \App\Config::load('/var/www/DB_INV/.env');
    
    $logger = new \App\Logger('/var/www/DB_INV/logs/inv_import.log');
    $logger->info('=== NEW DAEMON OPERATOR-BASED ===');
    
    $db2 = new \App\Database\DB2Connection($logger);
    $mysql = new \App\Database\MySQLConnection($logger);
    
    $sql = 'SELECT CODICE, ID_REP, ID_INVENTARIO FROM CONF_OPERATORE';
    $stmt = $mysql->getPDO()->prepare($sql);
    $stmt->execute();
    $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $logger->info('Operators: ' . count($ops));
    
    $iter = 0;
    $lock = '/var/www/DB_INV/logs/daemon-import.lock';
    @chmod($lock, 0666);
    
    while (true) {
        $iter++;
        $h = @fopen($lock, 'a');
            if ($h) fclose($h);
            sleep(60);
            continue;
        }
        
        try {
            $where = [];
            foreach ($ops as $op) {
                $where[] = '(REPARTO=' . (int)$op['ID_REP'] . ' AND NUMERO=' . (int)$op['ID_INVENTARIO'] . ' AND OPER_CREAZ='' . $op['CODICE'] . '')';
            }
            
            $q = 'SELECT REPARTO, NUMERO, PRECODICE, CODICE_ART, POSIZIONE, NUMERO_CONTA, PROG, QTA_CONTEGGIATA, RIFERIMENTI, OPER_CREAZ, DATA_CREAZ, ORA_CREAZ FROM D01.INVC WHERE ' . implode(' OR ', $where) . ' ORDER BY REPARTO, NUMERO';
            
            $p = $db2->getPDO();
            $s = $p->prepare($q);
            $s->execute();
            
            $recs = [];
            while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
                $r['PRECODICE'] = trim($r['PRECODICE'] ?? '');
                $r['CODICE_ART'] = trim($r['CODICE_ART'] ?? '');
                $r['OPER_CREAZ'] = trim($r['OPER_CREAZ'] ?? '');
                $recs[] = $r;
            }
            
            $logger->info('Iter ' . $iter . ': fetched ' . count($recs) . ' recs');
            
            $imp = $mark = 0;
            foreach ($recs as $r) {
                if ($r['PRECODICE'] === 'ZZZ') {
                    $logger->info('✓✓✓ ZZZ=' . $r['CODICE_ART'] . ' REP=' . $r['REPARTO'] . ' NUM=' . $r['NUMERO']);
                    $mark++;
                    continue;
                }
                $imp++;
            }
            
            $logger->info('Iter ' . $iter . ': imported=' . $imp . ' markers=' . $mark);
            
        } finally {
            flock($h, LOCK_UN);
            fclose($h);
        }
        sleep(60);
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "
";
}
?>