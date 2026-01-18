<?php
// printers.php - Visualizzazione STAMPANTI

require_once __DIR__ . '/bootstrap.php';

use App\Repository\StampantiRepository;

$container = require __DIR__ . '/bootstrap.php';
$db = $container['db'];

$stampantiRepo = new StampantiRepository($db);
$stampanti = $stampantiRepo->getAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Stampanti - DB_INV</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: white; padding: 20px 0; margin-bottom: 30px; }
        header h1 { font-size: 28px; }
        header a { color: white; text-decoration: none; font-size: 14px; margin-left: 20px; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        button, .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary { background: #2c3e50; color: white; }
        .btn-primary:hover { background: #1a252f; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .btn-small { padding: 6px 12px; font-size: 12px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px; }
        table thead { background: #34495e; color: white; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table tbody tr:hover { background: #f9f9f9; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 4px; width: 90%; max-width: 500px; }
        .modal-close { float: right; background: none; border: none; font-size: 24px; cursor: pointer; }

        .empty { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>DB_INV - Gestione Stampanti</h1>
            <a href="index.php">← Torna ai Configuratori</a>
        </div>
    </header>

    <div class="container">
        <h2>Stampanti Configurate</h2>
        
        <?php if (!empty($stampanti)): ?>
            <table>
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Coda CUPS</th>
                        <th>In uso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stampanti as $p): ?>
                        <?php $usage = $stampantiRepo->countUsage($p['IP']); ?>
                        <tr>
                            <td><code><?= $p['IP'] ?></code></td>
                            <td><?= htmlspecialchars($p['CODA_CUPS']) ?></td>
                            <td><?= $usage > 0 ? "<strong>$usage</strong>" : "-" ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty">Nessuna stampante configurata</div>
        <?php endif; ?>
    </div>

</body>
</html>
