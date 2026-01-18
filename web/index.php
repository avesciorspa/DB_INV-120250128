<?php
// Index.php - Home page gestione CONF_OPERATORE

require_once __DIR__ . '/bootstrap.php';

use App\Repository\ConfigOperatoreRepository;
use App\Repository\StampantiRepository;
use App\Repository\RepartiRepository;
use App\Repository\AreeRepository;

$container = require __DIR__ . '/bootstrap.php';
$logger = $container['logger'];
$db = $container['db'];

$confRepo = new ConfigOperatoreRepository($db);
$stampantiRepo = new StampantiRepository($db);
$repartiRepo = new RepartiRepository($db);
$areeRepo = new AreeRepository($db);

// Mappe per select dropdown
$repartiMap = $repartiRepo->getForSelect();
$areeMap = $areeRepo->getForSelect();

$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

// Gestione POST per CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? 'list';

        switch ($action) {
            case 'create':
                $codice = trim($_POST['codice'] ?? '');
                $nome = trim($_POST['nome'] ?? '') ?: null;
                $idRep = (int)($_POST['id_rep'] ?? 0);
                $idInventario = (int)($_POST['id_inventario'] ?? 0);
                $idArea = (int)($_POST['id_area'] ?? -1);
                $ipStampante = trim($_POST['ip_stampante'] ?? '');

                if (!$codice || !$idRep || !$idInventario || !$ipStampante) {
                    throw new \Exception('Campi obbligatori mancanti');
                }

                // Verifica stampante esiste
                if (!$stampantiRepo->findByIp($ipStampante)) {
                    throw new \Exception("Stampante IP $ipStampante non configurata");
                }

                // Verifica se esiste già
                if ($confRepo->findByKey($codice, $idRep, $idInventario)) {
                    throw new \Exception('Configurazione già esistente');
                }

                $confRepo->create($codice, $nome, $idRep, $idInventario, $idArea, $ipStampante);
                $message = 'Configurazione creata con successo';
                $messageType = 'success';
                $logger->info('ConfigOperatore creata', ['codice' => $codice, 'rep' => $idRep, 'inv' => $idInventario]);
                break;

            case 'update':
                $idConf = (int)($_POST['id_conf'] ?? 0);
                $nome = trim($_POST['nome'] ?? '') ?: null;
                $idArea = (int)($_POST['id_area'] ?? -1);
                $ipStampante = trim($_POST['ip_stampante'] ?? '');

                if (!$idConf || !$ipStampante) {
                    throw new \Exception('Dati incompleti');
                }

                if (!$stampantiRepo->findByIp($ipStampante)) {
                    throw new \Exception("Stampante IP $ipStampante non configurata");
                }

                $confRepo->update($idConf, $nome, $idArea, $ipStampante);
                $message = 'Configurazione aggiornata con successo';
                $messageType = 'success';
                $logger->info('ConfigOperatore aggiornata', ['id' => $idConf]);
                break;

            case 'delete':
                $idConf = (int)($_POST['id_conf'] ?? 0);
                if (!$idConf) {
                    throw new \Exception('ID mancante');
                }

                $confRepo->delete($idConf);
                $message = 'Configurazione eliminata';
                $messageType = 'success';
                $logger->info('ConfigOperatore eliminata', ['id' => $idConf]);
                break;

            case 'bulk_delete':
                $ids = array_map('intval', $_POST['selected_ids'] ?? []);
                if (empty($ids)) {
                    throw new \Exception('Nessuna riga selezionata');
                }

                $deleted = $confRepo->bulkDelete($ids);
                $message = "Eliminate $deleted configurazioni";
                $messageType = 'success';
                $logger->info('ConfigOperatore bulk delete', ['count' => $deleted]);
                break;

            case 'bulk_update_area':
                $ids = array_map('intval', $_POST['selected_ids'] ?? []);
                $newArea = (int)($_POST['bulk_value'] ?? -1);

                if (empty($ids)) {
                    throw new \Exception('Nessuna riga selezionata');
                }

                $updated = $confRepo->bulkUpdateArea($ids, $newArea);
                $message = "Aggiornate $updated aree";
                $messageType = 'success';
                $logger->info('ConfigOperatore bulk update area', ['count' => $updated, 'area' => $newArea]);
                break;

            case 'bulk_update_printer':
                $ids = array_map('intval', $_POST['selected_ids'] ?? []);
                $ipStampante = trim($_POST['bulk_value'] ?? '');

                if (empty($ids)) {
                    throw new \Exception('Nessuna riga selezionata');
                }

                if (!$stampantiRepo->findByIp($ipStampante)) {
                    throw new \Exception("Stampante IP $ipStampante non configurata");
                }

                $updated = $confRepo->bulkUpdatePrinter($ids, $ipStampante);
                $message = "Aggiornate $updated stampanti";
                $messageType = 'success';
                $logger->info('ConfigOperatore bulk update printer', ['count' => $updated, 'ip' => $ipStampante]);
                break;
        }
    } catch (\Exception $e) {
        $message = 'Errore: ' . $e->getMessage();
        $messageType = 'error';
        $logger->error($action . ' failed', ['error' => $e->getMessage()]);
    }

    $action = 'list';
}

// Carica dati
$configurazioni = $confRepo->getAll();
$stampanti = $stampantiRepo->getAll();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB_INV - Gestione Configurazioni</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: #2c3e50; color: white; padding: 20px 0; margin-bottom: 30px; }
        header h1 { font-size: 28px; }
        header p { font-size: 14px; opacity: 0.9; margin-top: 5px; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tabs button {
            background: none;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tabs button.active { color: #2c3e50; border-bottom-color: #2c3e50; }
        .tabs button:hover { color: #2c3e50; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 14px; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-row.full { grid-template-columns: 1fr; }

        .button-group { display: flex; gap: 10px; margin-top: 20px; }
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
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-small { padding: 6px 12px; font-size: 12px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table thead { background: #34495e; color: white; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table tbody tr:hover { background: #f9f9f9; }
        table input[type="checkbox"] { margin: 0; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 4px; width: 90%; max-width: 500px; }
        .modal-header { font-size: 20px; font-weight: 600; margin-bottom: 20px; }
        .modal-close { float: right; background: none; border: none; font-size: 24px; cursor: pointer; }

        .bulk-actions { background: #ecf0f1; padding: 15px; border-radius: 4px; margin-bottom: 20px; display: none; }
        .bulk-actions.active { display: block; }
        .bulk-actions p { font-size: 14px; margin-bottom: 10px; }

        .empty { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>DB_INV</h1>
            <p>Gestione Configurazioni Operatori e Stampanti</p>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab-btn active" data-tab="operatori">Operatori</button>
            <button class="tab-btn" data-tab="stampanti">Stampanti</button>
        </div>

        <!-- TAB: OPERATORI -->
        <div id="operatori" class="tab-content active">
            <h2 style="margin-bottom: 20px;">Configurazioni Operatori</h2>

            <div class="bulk-actions" id="bulkActions">
                <p>
                    <span id="selectedCount">0</span> righe selezionate
                    <button class="btn btn-secondary btn-small" onclick="clearSelection()">Deseleziona tutto</button>
                </p>
                <form method="POST" style="display: grid; grid-template-columns: 1fr auto; gap: 10px;">
                    <input type="hidden" name="action" value="">
                    <div id="hiddenIds"></div>

                    <select name="new_area" style="padding: 8px;">
                        <option value="">Scegli azione...</option>
                        <optgroup label="Aggiorna Area">
                            <option value="bulk_update_area">Assegna area a...</option>
                        </optgroup>
                        <optgroup label="Aggiorna Stampante">
                            <option value="bulk_update_printer">Assegna stampante a...</option>
                        </optgroup>
                        <optgroup label="Elimina">
                            <option value="bulk_delete" style="background: #e74c3c; color: white;">Elimina selezionati</option>
                        </optgroup>
                    </select>
                    <input type="text" id="bulkValue" name="bulk_value" placeholder="Valore..." style="display: none;">
                    <button type="submit" class="btn btn-primary">Applica</button>
                </form>
            </div>

            <button class="btn btn-primary" onclick="openCreateModal()">+ Nuova Configurazione</button>

            <?php if (!empty($configurazioni)): ?>
                <table style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                            <th>Operatore</th>
                            <th>Nome</th>
                            <th>Reparto</th>
                            <th>Inventario</th>
                            <th>Area</th>
                            <th>Stampante</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="confTable">
                        <?php foreach ($configurazioni as $conf): ?>
                            <tr data-id="<?= $conf['ID_CONF'] ?>">
                                <td><input type="checkbox" class="row-checkbox" onchange="updateBulkUI()"></td>
                                <td><?= htmlspecialchars($conf['CODICE']) ?></td>
                                <td><?= htmlspecialchars($conf['NOME'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($repartiMap[$conf['ID_REP']] ?? $conf['ID_REP']) ?></td>
                                <td><?= $conf['ID_INVENTARIO'] ?></td>
                                <td><?= htmlspecialchars($areeMap[$conf['ID_AREA']] ?? $conf['ID_AREA']) ?></td>
                                <td><?= $conf['IP_STAMPANTE'] ?></td>
                                <td>
                                    <button class="btn btn-primary btn-small" onclick="openEditModal(<?= $conf['ID_CONF'] ?>)">Modifica</button>
                                    <button class="btn btn-danger btn-small" onclick="deleteConf(<?= $conf['ID_CONF'] ?>)">Elimina</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">Nessuna configurazione. Crea la prima!</div>
            <?php endif; ?>
        </div>

        <!-- TAB: STAMPANTI -->
        <div id="stampanti" class="tab-content">
            <h2 style="margin-bottom: 20px;">Stampanti Configurate</h2>
            <button class="btn btn-primary" onclick="openPrinterModal()">+ Nuova Stampante</button>

            <?php if (!empty($stampanti)): ?>
                <table style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Coda CUPS</th>
                            <th>Note</th>
                            <th>In uso</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stampanti as $printer): ?>
                            <?php $usage = $stampantiRepo->countUsage($printer['IP']); ?>
                            <tr>
                                <td><code><?= $printer['IP'] ?></code></td>
                                <td><?= htmlspecialchars($printer['CODA_CUPS']) ?></td>
                                <td><?= htmlspecialchars($printer['NOTE'] ?? '-') ?></td>
                                <td><?= $usage > 0 ? "<strong>$usage</strong> operatori" : "Non usata" ?></td>
                                <td>
                                    <button class="btn btn-primary btn-small" onclick="editPrinter('<?= $printer['IP'] ?>')">Modifica</button>
                                    <?php if ($usage === 0): ?>
                                        <button class="btn btn-danger btn-small" onclick="deletePrinter('<?= $printer['IP'] ?>')">Elimina</button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-small" disabled>In uso</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">Nessuna stampante configurata. Aggiungine una!</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL: CREA/MODIFICA OPERATORE -->
    <div id="confModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('confModal')">&times;</button>
            <div class="modal-header" id="modalTitle">Nuova Configurazione</div>
            <form method="POST">
                <input type="hidden" name="action" id="confAction">
                <input type="hidden" name="id_conf" id="confId">

                <div class="form-row">
                    <div class="form-group">
                        <label>Operatore *</label>
                        <input type="text" name="codice" id="confCodice" maxlength="10" required>
                    </div>
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" id="confNome" maxlength="30">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Reparto *</label>
                        <select name="id_rep" id="confRep" required>
                            <option value="">Seleziona reparto...</option>
                            <?php foreach ($repartiMap as $id => $desc): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($desc) ?> (#<?= $id ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Inventario *</label>
                        <input type="number" name="id_inventario" id="confInv" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Area Corrente</label>
                    <select name="id_area" id="confArea">
                        <?php foreach ($areeMap as $id => $desc): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($desc) ?> (#<?= $id ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Stampante *</label>
                    <select name="ip_stampante" id="confPrinter" required>
                        <option value="">Seleziona stampante...</option>
                        <?php foreach ($stampanti as $p): ?>
                            <option value="<?= $p['IP'] ?>"><?= $p['IP'] ?> (<?= $p['CODA_CUPS'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Salva</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('confModal')">Annulla</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: CREA/MODIFICA STAMPANTE -->
    <div id="printerModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('printerModal')">&times;</button>
            <div class="modal-header">Configura Stampante</div>
            <form method="POST" action="printers.php">
                <input type="hidden" name="action" id="printerAction" value="create">
                <input type="hidden" name="old_ip" id="oldIp">

                <div class="form-group">
                    <label>IP Stampante *</label>
                    <input type="text" name="ip" id="printerIp" placeholder="192.168.1.100" required pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}">
                </div>

                <div class="form-group">
                    <label>Coda CUPS *</label>
                    <input type="text" name="coda_cups" id="printerQueue" placeholder="INV_172_16_8_248" required>
                </div>

                <div class="form-group">
                    <label>Note</label>
                    <input type="text" name="note" id="printerNote" placeholder="Es: Piano 2, reparto A">
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Salva</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('printerModal')">Annulla</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById(btn.dataset.tab).classList.add('active');
            });
        });

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Nuova Configurazione';
            document.getElementById('confAction').value = 'create';
            document.getElementById('confId').value = '';
            document.getElementById('confCodice').value = '';
            document.getElementById('confNome').value = '';
            document.getElementById('confRep').value = '';
            document.getElementById('confInv').value = '';
            document.getElementById('confArea').value = '-1';
            document.getElementById('confPrinter').value = '';
            document.getElementById('confCodice').removeAttribute('readonly');
            openModal('confModal');
        }

        function openEditModal(id) {
            fetch(`api/get-conf.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const conf = data.data;
                        document.getElementById('modalTitle').textContent = 'Modifica Configurazione';
                        document.getElementById('confAction').value = 'update';
                        document.getElementById('confId').value = conf.ID_CONF;
                        document.getElementById('confCodice').value = conf.CODICE;
                        document.getElementById('confCodice').setAttribute('readonly', 'readonly');
                        document.getElementById('confNome').value = conf.NOME || '';
                        document.getElementById('confRep').value = conf.ID_REP;
                        document.getElementById('confInv').value = conf.ID_INVENTARIO;
                        document.getElementById('confArea').value = conf.ID_AREA;
                        document.getElementById('confPrinter').value = conf.IP_STAMPANTE;
                        openModal('confModal');
                    }
                })
                .catch(e => alert('Errore caricamento dati'));
        }

        function deleteConf(id) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            const codice = row.querySelector('td:nth-child(2)').textContent;
            if (confirm(`Eliminare configurazione di ${codice}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_conf" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Form validation per CONF_OPERATORE
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const action = document.getElementById('confAction').value;
                    
                    if (action === 'create') {
                        const codice = document.getElementById('confCodice').value.trim();
                        if (!codice || codice.length === 0) {
                            e.preventDefault();
                            alert('Codice operatore obbligatorio');
                            return;
                        }
                        if (!/^[A-Z0-9]+$/.test(codice)) {
                            e.preventDefault();
                            alert('Codice deve contenere solo lettere maiuscole e numeri');
                            return;
                        }
                    }
                });
            }
        });

        // Bulk selection
        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkUI();
        }

        function updateBulkUI() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            selectedCount.textContent = checked.length;

            if (checked.length > 0) {
                bulkActions.classList.add('active');
                const ids = Array.from(checked).map(cb => cb.closest('tr').getAttribute('data-id'));
                updateHiddenIds(ids);
            } else {
                bulkActions.classList.remove('active');
                document.getElementById('selectAll').checked = false;
            }
        }

        function updateHiddenIds(ids) {
            const container = document.getElementById('hiddenIds');
            container.innerHTML = '';
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = id;
                container.appendChild(input);
            });
        }

        function clearSelection() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            updateBulkUI();
        }

        // Bulk actions form handling
        document.addEventListener('DOMContentLoaded', function() {
            const bulkForm = document.querySelector('#bulkActions form');
            if (bulkForm) {
                const selectEl = bulkForm.querySelector('select[name="new_area"]');
                const bulkValueInput = document.getElementById('bulkValue');

                selectEl.addEventListener('change', function() {
                    const value = this.value;
                    if (value === 'bulk_update_area') {
                        bulkValueInput.style.display = 'block';
                        bulkValueInput.placeholder = 'Numero area...';
                        document.querySelector('#bulkActions form [name="action"]').value = 'bulk_update_area';
                    } else if (value === 'bulk_update_printer') {
                        bulkValueInput.style.display = 'block';
                        bulkValueInput.placeholder = 'IP stampante...';
                        document.querySelector('#bulkActions form [name="action"]').value = 'bulk_update_printer';
                    } else if (value === 'bulk_delete') {
                        bulkValueInput.style.display = 'none';
                        document.querySelector('#bulkActions form [name="action"]').value = 'bulk_delete';
                    }
                });

                bulkForm.addEventListener('submit', function(e) {
                    const action = document.querySelector('#bulkActions form [name="action"]').value;
                    const selected = document.querySelectorAll('.row-checkbox:checked').length;

                    if (selected === 0) {
                        e.preventDefault();
                        alert('Seleziona almeno una riga');
                        return;
                    }

                    let msg = '';
                    if (action === 'bulk_delete') {
                        msg = `Eliminare ${selected} configurazioni? Questa azione non può essere annullata.`;
                    } else if (action === 'bulk_update_area') {
                        msg = `Assegnare area a ${selected} configurazioni?`;
                    } else if (action === 'bulk_update_printer') {
                        msg = `Assegnare stampante a ${selected} configurazioni?`;
                    }

                    if (msg && !confirm(msg)) {
                        e.preventDefault();
                    }
                });
            }

            // Validazione form CONF_OPERATORE
            const confForm = document.querySelector('#confModal form');
            if (confForm) {
                confForm.addEventListener('submit', function(e) {
                    const action = document.getElementById('confAction').value;
                    
                    if (action === 'create') {
                        const codice = document.getElementById('confCodice').value.trim();
                        if (!codice || codice.length === 0) {
                            e.preventDefault();
                            alert('Codice operatore obbligatorio');
                            return;
                        }
                        if (!/^[A-Z0-9]+$/.test(codice)) {
                            e.preventDefault();
                            alert('Codice deve contenere solo lettere maiuscole e numeri');
                            return;
                        }
                        if (codice.length > 10) {
                            e.preventDefault();
                            alert('Codice massimo 10 caratteri');
                            return;
                        }
                    }

                    const reparto = document.getElementById('confRep').value;
                    const inventario = document.getElementById('confInv').value;
                    
                    if (!reparto || !inventario) {
                        e.preventDefault();
                        alert('Reparto e Inventario sono obbligatori');
                        return;
                    }
                });
            }
        });
    </script>
</body>
</html>
