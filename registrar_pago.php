<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

if (!isset($_GET['id_beca']) || empty($_GET['id_beca'])) {
    header('Location: ver_becados.php');
    exit;
}

$id_beca = $_GET['id_beca'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';


$sql = "SELECT 
            b.*,
            e.Id_Estudiante,
            e.Numero_Expediente,
            e.Nombres_Apellidos,
            e.Email
        FROM Becas_Otorgadas b
        INNER JOIN Estudiantes e ON b.Id_Estudiante = e.Id_Estudiante
        WHERE b.Id_Beca = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_beca]);
$beca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$beca) {
    header('Location: ver_becados.php');
    exit;
}

$sql_pagos = "SELECT * FROM Pagos_Becas 
              WHERE Id_Beca = ? 
              ORDER BY Fecha_Pago DESC";
$stmt_pagos = $pdo->prepare($sql_pagos);
$stmt_pagos->execute([$id_beca]);
$pagos_anteriores = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fecha_pago = $_POST['fecha_pago'];
        $monto = $_POST['monto'];
        $periodo = $_POST['periodo'];
        $metodo_pago = $_POST['metodo_pago'];
        $referencia = $_POST['referencia'] ?? null;
        $notas = $_POST['notas'] ?? null;

        if (empty($fecha_pago) || empty($monto) || empty($periodo)) {
            throw new Exception('Por favor completa todos los campos obligatorios');
        }

        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a cero');
        }

        $sql_check = "SELECT COUNT(*) as total FROM Pagos_Becas 
                      WHERE Id_Beca = ? AND Periodo = ?";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([$id_beca, $periodo]);
        $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($existe['total'] > 0) {
            throw new Exception('Ya existe un pago registrado para el período: ' . $periodo);
        }

        $pdo->beginTransaction();

        $sql_insert = "INSERT INTO Pagos_Becas 
                       (Id_Beca, Fecha_Pago, Monto, Periodo, Metodo_Pago, Referencia, Notas, Id_Usuario_Registro)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $id_beca,
            $fecha_pago,
            $monto,
            $periodo,
            $metodo_pago,
            $referencia,
            $notas,
            $user_id
        ]);

        $sql_bitacora = "INSERT INTO Bitacora (Id_Usuario, Actividades, Fecha)
                         VALUES (?, ?, CURDATE())";
        $actividad = "Registró pago de Q{$monto} para {$beca['Nombres_Apellidos']} - Período: {$periodo}";
        $stmt_bitacora = $pdo->prepare($sql_bitacora);
        $stmt_bitacora->execute([$user_id, $actividad]);

        $pdo->commit();

        $mensaje = '¡Pago registrado exitosamente!';
        $tipo_mensaje = 'success';

        $stmt_pagos->execute([$id_beca]);
        $pagos_anteriores = $stmt_pagos->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = 'Error al registrar el pago: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

$periodos = [];
for ($i = 0; $i < 12; $i++) {
    $fecha = new DateTime();
    $fecha->modify("-{$i} month");
    $periodos[] = $fecha->format('Y-m');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Pago - Sistema de Becas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/reglamento.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            width: 100%;
        }
        
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 30px 40px;
            width: calc(100% - 280px);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #004b87, #ffa500);
        }

        .header h1 {
            color: #004b87;
            font-size: 28px;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease;
        }

        .user-info:hover {
            transform: translateY(-2px);
        }

        .user-avatar-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-role-icon {
            font-size: 1.2em;
            color: #004b87;
        }

        .user-avatar-main {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #004b87, #0066b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 4px 8px rgba(0, 75, 135, 0.3);
        }

        .user-details-main {
            display: flex;
            flex-direction: column;
        }

        .user-name-main {
            color: #004b87;
            font-weight: 600;
            font-size: 15px;
        }

        .user-role-main {
            color: #666;
            font-size: 13px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745; 
        }
        
        .alert-error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545; 
        }
        
        .alert-info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-left: 4px solid #17a2b8; 
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .form-section, .historial-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i { 
            font-size: 22px; 
            color: #ffc107; 
        }
        
        .monto-display {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            color: white;
        }
        
        .monto-display .label { 
            font-size: 0.9em; 
            opacity: 0.9; 
            margin-bottom: 5px; 
        }
        
        .monto-display .valor { 
            font-size: 2.5em; 
            font-weight: 700; 
        }
        
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group label .required { 
            color: #dc3545; 
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }
        
        .form-group textarea { 
            resize: vertical; 
            min-height: 80px; 
        }
        
        .form-group .hint { 
            font-size: 0.85em; 
            color: #7f8c8d; 
            margin-top: 5px; 
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
            width: 100%;
            justify-content: center;
        }
        
        .btn-success:hover { 
            background: #218838; 
            transform: translateY(-2px); 
        }
        
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
        }
        
        .btn-secondary:hover { 
            background: #5a6268; 
            transform: translateY(-2px); 
        }
        
        .pagos-list { 
            max-height: 400px; 
            overflow-y: auto; 
        }
        
        .pago-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pago-info { 
            flex: 1; 
        }
        
        .pago-fecha { 
            font-size: 0.85em; 
            color: #7f8c8d; 
            margin-bottom: 5px; 
        }
        
        .pago-periodo { 
            font-weight: 600; 
            color: #2c3e50; 
            margin-bottom: 5px; 
        }
        
        .pago-metodo { 
            font-size: 0.85em; 
            color: #7f8c8d; 
        }
        
        .pago-monto { 
            font-size: 1.5em; 
            font-weight: 700; 
            color: #28a745; 
        }
        
        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #bdc3c7; 
        }
        
        .empty-state i { 
            font-size: 4em; 
            margin-bottom: 15px; 
            opacity: 0.3; 
        }
        
        .empty-state h3 { 
            font-size: 1.2em; 
            margin-bottom: 10px; 
            color: #7f8c8d; 
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        
        .stat-item { 
            text-align: center; 
        }
        
        .stat-label { 
            font-size: 0.85em; 
            color: #7f8c8d; 
            margin-bottom: 5px; 
        }
        
        .stat-value { 
            font-size: 1.5em; 
            font-weight: 700; 
            color: #2c3e50; 
        }
        
        .student-info {
            background: linear-gradient(135deg, #004b87 0%, #0066b3 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .student-info h3 {
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        
        .student-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9em;
        }
        
        @media (max-width: 968px) {
            .main-content { 
                margin-left: 0; 
                padding: 20px; 
            }
            
            .content-grid { 
                grid-template-columns: 1fr; 
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .student-details {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="container">
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Registrar Pago de Beca</h1>
                <div class="user-info">
                    <div class="user-avatar-wrapper">
                        <?php
                            $iconClass = '';
                            switch ($role) {
                                case 'Administrador':
                                    $iconClass = 'fa-solid fa-crown';
                                    break;
                                case 'Coordinador':
                                    $iconClass = 'fa-solid fa-user-tie';
                                    break;
                                default:
                                    $iconClass = 'fa-solid fa-user';
                                    break;
                            }
                        ?>
                        <i class="<?= $iconClass ?> user-role-icon"></i>
                        <div class="user-avatar-main"><?= getInitials($username) ?></div>
                    </div>
                    <div class="user-details-main">
                        <div class="user-name-main"><?= htmlspecialchars($username) ?></div>
                        <div class="user-role-main"><?= htmlspecialchars($role) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?>">
                <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <div><?= $mensaje ?></div>
            </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Información del Estudiante:</strong> 
                    <?= htmlspecialchars($beca['Nombres_Apellidos']) ?> - 
                    Expediente: <?= htmlspecialchars($beca['Numero_Expediente']) ?>
                </div>
            </div>

            <div class="content-grid">
                <div class="form-section">
                    <h3 class="section-title">
                        <i class="fas fa-plus-circle"></i> Nuevo Pago
                    </h3>

                    <div class="monto-display">
                        <div class="label">Monto Mensual de Beca</div>
                        <div class="valor">Q<?= number_format($beca['Monto_Mensual'], 2) ?></div>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Fecha del Pago <span class="required">*</span></label>
                            <input type="date" 
                                   name="fecha_pago" 
                                   value="<?= date('Y-m-d') ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   required>
                            <div class="hint">Fecha en que se realiza el pago</div>
                        </div>

                        <div class="form-group">
                            <label>Monto <span class="required">*</span></label>
                            <input type="number" 
                                   name="monto" 
                                   value="<?= $beca['Monto_Mensual'] ?>"
                                   step="0.01"
                                   min="0.01"
                                   required>
                            <div class="hint">Monto en quetzales (Q)</div>
                        </div>

                        <div class="form-group">
                            <label>Período <span class="required">*</span></label>
                            <select name="periodo" required>
                                <option value="">Selecciona el mes...</option>
                                <?php foreach ($periodos as $periodo): 
                                    $fecha_periodo = new DateTime($periodo . '-01');
                                    $mes_nombre = $fecha_periodo->format('F Y');
                                    $meses_es = [
                                        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                                        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                                        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                                        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
                                    ];
                                    foreach ($meses_es as $en => $es) {
                                        $mes_nombre = str_replace($en, $es, $mes_nombre);
                                    }
                                ?>
                                    <option value="<?= $periodo ?>"><?= $mes_nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="hint">Mes al que corresponde este pago</div>
                        </div>

                        <div class="form-group">
                            <label>Método de Pago <span class="required">*</span></label>
                            <select name="metodo_pago" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Depósito">Depósito</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Referencia / No. de Transacción</label>
                            <input type="text" 
                                   name="referencia" 
                                   placeholder="Ej: No. de cheque, código de transferencia...">
                            <div class="hint">Opcional - Para pagos electrónicos o cheques</div>
                        </div>

                        <div class="form-group">
                            <label>Notas Adicionales</label>
                            <textarea name="notas" 
                                      placeholder="Cualquier información adicional sobre este pago..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Registrar Pago
                        </button>
                    </form>

                    <div style="margin-top: 20px;">
                        <a href="estudiantes_becados.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Becados
                        </a>
                    </div>
                </div>

                <div class="historial-section">
                    <h3 class="section-title">
                        <i class="fas fa-history"></i> Historial de Pagos
                    </h3>

                    <?php if (count($pagos_anteriores) > 0): ?>
                    <div class="pagos-list">
                        <?php foreach ($pagos_anteriores as $pago): 
                            $fecha_periodo = new DateTime($pago['Periodo'] . '-01');
                            $mes_nombre = $fecha_periodo->format('F Y');
                            $meses_es = [
                                'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
                                'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
                                'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
                                'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
                            ];
                            foreach ($meses_es as $en => $es) {
                                $mes_nombre = str_replace($en, $es, $mes_nombre);
                            }
                        ?>
                        <div class="pago-item">
                            <div class="pago-info">
                                <div class="pago-fecha">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($pago['Fecha_Pago'])) ?>
                                </div>
                                <div class="pago-periodo"><?= $mes_nombre ?></div>
                                <div class="pago-metodo">
                                    <i class="fas fa-credit-card"></i>
                                    <?= htmlspecialchars($pago['Metodo_Pago']) ?>
                                    <?php if ($pago['Referencia']): ?>
                                        - Ref: <?= htmlspecialchars($pago['Referencia']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="pago-monto">
                                Q<?= number_format($pago['Monto'], 2) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-label">Total Pagos</div>
                            <div class="stat-value"><?= count($pagos_anteriores) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Total Pagado</div>
                            <div class="stat-value">Q<?= number_format(array_sum(array_column($pagos_anteriores, 'Monto')), 2) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Último Pago</div>
                            <div class="stat-value" style="font-size: 1em;">
                                <?= date('d/m/Y', strtotime($pagos_anteriores[0]['Fecha_Pago'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>Sin pagos registrados</h3>
                        <p>Este será el primer pago para este estudiante</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mostrar SweetAlert si hay mensajes de sesión
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($_SESSION['error']) ?>',
                confirmButtonColor: '#dc3545'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '<?= addslashes($_SESSION['success']) ?>',
                confirmButtonColor: '#28a745',
                timer: 3000
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        // Confirmación antes de enviar el formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const monto = document.querySelector('input[name="monto"]').value;
            const periodo = document.querySelector('select[name="periodo"]').value;
            
            if (!periodo) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Período requerido',
                    text: 'Por favor selecciona el período del pago',
                    confirmButtonColor: '#ffc107'
                });
                return false;
            }

            e.preventDefault();
            Swal.fire({
                title: '¿Registrar pago?',
                html: `¿Estás seguro de registrar un pago de <strong>Q${monto}</strong> para el período seleccionado?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, registrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.submit();
                }
            });
        });
    </script>
</body>
</html>