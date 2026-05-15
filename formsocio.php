<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Becas - Club Rotario</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --azul-rotary: #005daa;
      --dorado-rotary: #f2a900;
      --azul-oscuro: #003b76;
      --gris-claro: #f8f9fa;
      --texto-oscuro: #333333;
      --verde-exito: #28a745;
      --naranja-pendiente: #fd7e14;
      --rojo-rechazado: #dc3545;
      --sidebar-width: 250px;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--gris-claro);
      color: var(--texto-oscuro);
      line-height: 1.6;
    }
    
    .dashboard-container {
      display: flex;
      min-height: 100vh;
    }
    
    /* SIDEBAR */
    .sidebar {
      width: var(--sidebar-width);
      background: linear-gradient(180deg, var(--azul-oscuro) 0%, var(--azul-rotary) 100%);
      color: white;
      position: fixed;
      height: 100vh;
      overflow-y: auto;
      z-index: 1000;
      transition: transform 0.3s ease;
    }
    
    .sidebar-header {
      padding: 20px;
      text-align: center;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-header .logo {
      font-size: 2.5rem;
      color: var(--dorado-rotary);
      margin-bottom: 10px;
    }
    
    .sidebar-header h2 {
      font-size: 1.1rem;
      font-weight: 500;
    }
    
    .nav-menu {
      padding: 20px 0;
    }
    
    .nav-item {
      margin: 5px 0;
    }
    
    .nav-link {
      display: flex;
      align-items: center;
      padding: 15px 20px;
      color: rgba(255,255,255,0.8);
      text-decoration: none;
      transition: all 0.3s;
      cursor: pointer;
    }
    
    .nav-link:hover, .nav-link.active {
      background: rgba(255,255,255,0.1);
      color: var(--dorado-rotary);
      transform: translateX(5px);
    }
    
    .nav-link i {
      margin-right: 12px;
      width: 20px;
      text-align: center;
    }
    
    /* MAIN CONTENT */
    .main-content {
      flex: 1;
      margin-left: var(--sidebar-width);
      background: var(--gris-claro);
    }
    
    .topbar {
      background: white;
      padding: 15px 30px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .topbar h1 {
      color: var(--azul-rotary);
      font-size: 1.8rem;
    }
    
    .user-info {
      display: flex;
      align-items: center;
      color: var(--texto-oscuro);
    }
    
    .user-info i {
      margin-right: 8px;
      color: var(--azul-rotary);
    }
    
    .content-area {
      padding: 30px;
    }
    
    /* CARDS */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    .stat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .stat-icon {
      font-size: 2.5rem;
      padding: 15px;
      border-radius: 50%;
      color: white;
    }
    
    .icon-total { background: var(--azul-rotary); }
    .icon-aprobado { background: var(--verde-exito); }
    .icon-pendiente { background: var(--naranja-pendiente); }
    .icon-rechazado { background: var(--rojo-rechazado); }
    
    .stat-number {
      font-size: 2.5rem;
      font-weight: bold;
      color: var(--azul-oscuro);
    }
    
    .stat-label {
      color: #666;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    /* CONTENT SECTIONS */
    .content-section {
      display: none;
    }
    
    .content-section.active {
      display: block;
      animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .section-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    
    .section-header {
      background: linear-gradient(135deg, var(--azul-rotary), var(--azul-oscuro));
      color: white;
      padding: 20px 30px;
    }
    
    .section-header h2 {
      font-size: 1.5rem;
      margin-bottom: 5px;
    }
    
    .section-header p {
      opacity: 0.9;
    }
    
    .section-content {
      padding: 30px;
    }
    
    /* TABLA DE APLICACIONES */
    .table-container {
      overflow-x: auto;
      margin: 20px 0;
    }
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
    
    .data-table th {
      background: var(--azul-rotary);
      color: white;
      padding: 15px;
      text-align: left;
      font-weight: 500;
    }
    
    .data-table td {
      padding: 15px;
      border-bottom: 1px solid #eee;
    }
    
    .data-table tr:hover {
      background: var(--gris-claro);
    }
    
    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
      text-transform: uppercase;
    }
    
    .status-pendiente {
      background: rgba(253, 126, 20, 0.1);
      color: var(--naranja-pendiente);
    }
    
    .status-aprobado {
      background: rgba(40, 167, 69, 0.1);
      color: var(--verde-exito);
    }
    
    .status-rechazado {
      background: rgba(220, 53, 69, 0.1);
      color: var(--rojo-rechazado);
    }
    
    /* BOTONES */
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      text-decoration: none;
      margin: 2px;
    }
    
    .btn-primary {
      background: var(--azul-rotary);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--azul-oscuro);
    }
    
    .btn-success {
      background: var(--verde-exito);
      color: white;
    }
    
    .btn-warning {
      background: var(--naranja-pendiente);
      color: white;
    }
    
    .btn-danger {
      background: var(--rojo-rechazado);
      color: white;
    }
    
    .btn i {
      margin-right: 5px;
    }
    
    /* FORMULARIO DE EVALUACIÓN */
    .evaluation-form {
      background: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    
    .form-header {
      background: linear-gradient(to right, var(--azul-oscuro), var(--azul-rotary));
      color: white;
      padding: 25px;
      text-align: center;
      position: relative;
    }
    
    .form-header h1 {
      font-size: 2.2rem;
      margin-bottom: 5px;
    }
    
    .form-header h2 {
      font-size: 1.4rem;
      font-weight: 400;
      margin-bottom: 15px;
      color: var(--dorado-rotary);
    }
    
    .form-logo {
      position: absolute;
      top: 20px;
      left: 30px;
      font-size: 2.5rem;
      color: var(--dorado-rotary);
    }
    
    .progress-container {
      background-color: #e6e6e6;
      height: 10px;
      border-radius: 5px;
      margin: 20px 25px 0;
      overflow: hidden;
    }
    
    .progress-bar {
      height: 100%;
      width: 25%;
      background-color: var(--dorado-rotary);
      border-radius: 5px;
      transition: width 0.5s ease;
    }
    
    .form-container {
      padding: 30px;
    }
    
    .form-section {
      display: none;
    }
    
    .form-section.active {
      display: block;
      animation: fadeIn 0.5s ease;
    }
    
    .form-section h3 {
      color: var(--azul-rotary);
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--dorado-rotary);
      font-size: 1.5rem;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--azul-oscuro);
    }
    
    .form-group input, .form-group textarea, .form-group select {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.3s, box-shadow 0.3s;
    }
    
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
      outline: none;
      border-color: var(--azul-rotary);
      box-shadow: 0 0 0 3px rgba(0, 93, 170, 0.2);
    }
    
    .radio-group, .checkbox-group {
      margin: 10px 0;
    }
    
    .radio-group label, .checkbox-group label {
      display: inline-flex;
      align-items: center;
      margin-right: 20px;
      font-weight: normal;
      cursor: pointer;
    }
    
    .radio-group input, .checkbox-group input {
      width: auto;
      margin-right: 8px;
    }
    
    .date-inputs {
      display: flex;
      gap: 15px;
    }
    
    .date-inputs > div {
      flex: 1;
    }
    
    .data-table-form {
      width: 100%;
      border-collapse: collapse;
      margin: 15px 0;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .data-table-form th, .data-table-form td {
      border: 1px solid #ddd;
      padding: 12px;
      text-align: left;
    }
    
    .data-table-form th {
      background-color: var(--azul-rotary);
      color: white;
      font-weight: 500;
    }
    
    .data-table-form tr:nth-child(even) {
      background-color: var(--gris-claro);
    }
    
    .table-input {
      width: 100%;
      border: none;
      background: transparent;
      padding: 5px;
    }
    
    .table-input:focus {
      outline: none;
      background-color: white;
      box-shadow: 0 0 0 2px rgba(0, 93, 170, 0.3);
    }
    
    .nav-buttons {
      display: flex;
      justify-content: space-between;
      margin-top: 30px;
    }
    
    .btn-prev {
      background-color: #e9ecef;
      color: var(--texto-oscuro);
    }
    
    .btn-prev:hover {
      background-color: #dee2e6;
    }
    
    .btn-next {
      background-color: var(--azul-rotary);
      color: white;
    }
    
    .btn-next:hover {
      background-color: var(--azul-oscuro);
    }
    
    .btn-submit {
      background-color: var(--dorado-rotary);
      color: white;
    }
    
    .btn-submit:hover {
      background-color: #e09a00;
    }
    
    .section-indicator {
      text-align: center;
      margin: 15px 0;
      color: var(--azul-rotary);
      font-weight: 500;
    }
    
    .required {
      color: #e22;
    }
    
    /* CHARTS */
    .chart-container {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      margin-bottom: 20px;
    }
    
    .chart-title {
      color: var(--azul-rotary);
      margin-bottom: 20px;
      font-size: 1.3rem;
    }
    
    /* SEARCH AND FILTERS */
    .search-filters {
      background: white;
      padding: 20px;
      border-radius: 15px;
      margin-bottom: 20px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    
    .search-row {
      display: flex;
      gap: 15px;
      align-items: end;
    }
    
    .search-group {
      flex: 1;
    }
    
    .mobile-menu-btn {
      display: none;
      background: var(--azul-rotary);
      color: white;
      border: none;
      padding: 10px;
      border-radius: 5px;
      cursor: pointer;
    }
    
    /* RESPONSIVE */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.open {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
      }
      
      .mobile-menu-btn {
        display: block;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .search-row {
        flex-direction: column;
      }
      
      .date-inputs {
        flex-direction: column;
      }
      
      .data-table-form {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <h2>Club Rotario<br>Coatepeque - Colomba</h2>
      </div>
      
      <nav class="nav-menu">
        <div class="nav-item">
          <a href="#" class="nav-link active" onclick="showContent('dashboard')">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
          </a>
        </div>
        <div class="nav-item">
          <a href="#" class="nav-link" onclick="showContent('aplicaciones')">
            <i class="fas fa-file-alt"></i>
            Aplicaciones
          </a>
        </div>
        <div class="nav-item">
          <a href="#" class="nav-link" onclick="showContent('evaluacion')">
            <i class="fas fa-clipboard-check"></i>
            Nueva Evaluación
          </a>
        </div>
        <div class="nav-item">
          <a href="#" class="nav-link" onclick="showContent('estudiantes')">
            <i class="fas fa-users"></i>
            Estudiantes
          </a>
        </div>
        <div class="nav-item">
          <a href="#" class="nav-link" onclick="showContent('reportes')">
            <i class="fas fa-chart-bar"></i>
            Reportes
          </a>
        </div>
        <div class="nav-item">
          <a href="#" class="nav-link" onclick="showContent('configuracion')">
            <i class="fas fa-cog"></i>
            Configuración
          </a>
        </div>
      </nav>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
      <div class="topbar">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
          <i class="fas fa-bars"></i>
        </button>
        <h1 id="page-title">Dashboard</h1>
        <div class="user-info">
          <i class="fas fa-user-circle"></i>
          Administrador Rotario
        </div>
      </div>
      
      <div class="content-area">
        <!-- DASHBOARD PRINCIPAL -->
     
        
        <!-- NUEVA EVALUACIÓN -->
        <div class="content-section" id="content-evaluacion">
          <div class="evaluation-form">
            <div class="form-header">
              <div class="form-logo">
                <i class="fas fa-graduation-cap"></i>
              </div>
              <h1>Club Rotario Coatepeque - Colomba</h1>
              <h2>Estudio Socioeconómico para Becas</h2>
              <div class="progress-container">
                <div class="progress-bar" id="progress-bar"></div>
              </div>
            </div>
            
            <form id="beca-form">
              <div class="form-container">
                <!-- SECCIÓN 1: DATOS DE IDENTIFICACIÓN -->
                <div class="form-section active" id="section-1">
                  <div class="section-indicator">Sección 1 de 4</div>
                  <h3>I. DATOS DE IDENTIFICACIÓN</h3>
                  
                  <div class="form-group">
                    <label for="fecha">FECHA: <span class="required">*</span></label>
                    <div class="date-inputs">
                      <div>
                        <label for="dia">Día</label>
                        <input type="number" id="dia" name="dia" min="1" max="31" required>
                      </div>
                      <div>
                        <label for="mes">Mes</label>
                        <input type="number" id="mes" name="mes" min="1" max="12" required>
                      </div>
                      <div>
                        <label for="anio">Año</label>
                        <input type="number" id="anio" name="anio" min="2000" max="2030" required>
                      </div>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label for="nombres">Nombres y Apellidos: <span class="required">*</span></label>
                    <input type="text" id="nombres" name="nombres" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="edad">Edad: <span class="required">*</span></label>
                    <input type="number" id="edad" name="edad" min="5" max="30" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="telefono">Número de teléfono: <span class="required">*</span></label>
                    <input type="tel" id="telefono" name="telefono" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="madre">Nombre de la madre: <span class="required">*</span></label>
                    <input type="text" id="madre" name="madre" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="padre">Nombre del padre: <span class="required">*</span></label>
                    <input type="text" id="padre" name="padre" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="direccion">Dirección Domiciliar: <span class="required">*</span></label>
                    <textarea id="direccion" name="direccion" rows="3" required></textarea>
                  </div>
                  
                  <div class="form-group">
                    <label for="grado">Grado obtenido el año anterior: <span class="required">*</span></label>
                    <input type="text" id="grado" name="grado" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="escuela">Escuela del año anterior: <span class="required">*</span></label>
                    <input type="text" id="escuela" name="escuela" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="encargado">Nombre del encargado(a): <span class="required">*</span></label>
                    <input type="text" id="encargado" name="encargado" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="tel_encargado">Teléfono del encargado(a): <span class="required">*</span></label>
                    <input type="tel" id="tel_encargado" name="tel_encargado" required>
                  </div>
                  
                  <div class="nav-buttons">
                    <div></div>
                    <button type="button" class="btn btn-next" onclick="showFormSection(2)">
                      Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                  </div>
                </div>
                
                <!-- SECCIÓN 2: INFORMACIÓN PERSONAL -->
                <div class="form-section" id="section-2">
                  <div class="section-indicator">Sección 2 de 4</div>
                  <h3>II. INFORMACIÓN PERSONAL</h3>
                  
                  <div class="form-group">
                    <label>¿Qué desea ser después de graduarse? <span class="required">*</span></label>
                    <div class="checkbox-group">
                      <label><input type="checkbox" name="meta[]" value="Electricista"> Electricista</label>
                      <label><input type="checkbox" name="meta[]" value="Maestro"> Maestro(a)</label>
                      <label><input type="checkbox" name="meta[]" value="Bachiller"> Bachiller</label>
                      <label><input type="checkbox" name="meta[]" value="Contador"> Contador(a)</label>
                      <label><input type="checkbox" name="meta[]" value="Administrador"> Administrador(a)</label>
                      <label><input type="checkbox" name="meta[]" value="Enfermero"> Enfermero(a)</label>
                      <label><input type="checkbox" name="meta[]" value="Secretaria"> Secretaria</label>
                    </div>
                    <label for="meta_otro">Otro:</label>
                    <input type="text" id="meta_otro" name="meta_otro">
                  </div>
                  
                  <div class="form-group">
                    <label>¿Está becado por otra institución? <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="otra_beca" value="SI" required> SI</label>
                      <label><input type="radio" name="otra_beca" value="NO"> NO</label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label for="institucion">Nombre de la Institución:</label>
                    <input type="text" id="institucion" name="institucion">
                  </div>
                  
                  <div class="form-group">
                    <label for="contacto">Contacto:</label>
                    <input type="text" id="contacto" name="contacto">
                  </div>
                  
                  <div class="nav-buttons">
                    <button type="button" class="btn btn-prev" onclick="showFormSection(1)">
                      <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-next" onclick="showFormSection(3)">
                      Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                  </div>
                </div>
                
                <!-- SECCIÓN 3: ASPECTO FAMILIAR -->
                <div class="form-section" id="section-3">
                  <div class="section-indicator">Sección 3 de 4</div>
                  <h3>III. ASPECTO FAMILIAR</h3>
                  
                  <div class="form-group">
                    <label>Estado civil de los padres: <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="estado_padres" value="Casados" required> Casados</label>
                      <label><input type="radio" name="estado_padres" value="Divorciados"> Divorciados</label>
                      <label><input type="radio" name="estado_padres" value="Viudo"> Viudo(a)</label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label>¿Su mamá sabe leer y escribir? <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="madre_leer" value="SI" required> Sí</label>
                      <label><input type="radio" name="madre_leer" value="NO"> No</label>
                    </div>
                    <label for="madre_grado">Hasta qué grado:</label>
                    <input type="text" id="madre_grado" name="madre_grado">
                  </div>
                  
                  <div class="form-group">
                    <label>¿Su papá sabe leer y escribir? <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="padre_leer" value="SI" required> Sí</label>
                      <label><input type="radio" name="padre_leer" value="NO"> No</label>
                    </div>
                    <label for="padre_grado">Hasta qué grado:</label>
                    <input type="text" id="padre_grado" name="padre_grado">
                  </div>
                  
                  <div class="form-group">
                    <label for="prof_madre">Profesión/Oficio de la madre:</label>
                    <input type="text" id="prof_madre" name="prof_madre">
                  </div>
                  
                  <div class="form-group">
                    <label for="prof_padre">Profesión/Oficio del padre:</label>
                    <input type="text" id="prof_padre" name="prof_padre">
                  </div>
                  
                  <div class="form-group">
                    <label for="trab_madre">Lugar de trabajo de la madre:</label>
                    <input type="text" id="trab_madre" name="trab_madre">
                  </div>
                  
                  <div class="form-group">
                    <label for="trab_padre">Lugar de trabajo del padre:</label>
                    <input type="text" id="trab_padre" name="trab_padre">
                  </div>
                  
                  <div class="form-group">
                    <label for="como_enterado">¿Cómo se enteró del programa de becas?</label>
                    <textarea id="como_enterado" name="como_enterado" rows="3"></textarea>
                  </div>
                  
                  <div class="form-group">
                    <label>Composición Familiar del hogar:</label>
                    <table class="data-table-form">
                      <thead>
                        <tr>
                          <th>Nombre y Apellidos</th>
                          <th>Edad</th>
                          <th>Parentesco</th>
                          <th>Nivel Educativo</th>
                          <th>Estado Civil</th>
                          <th>Ocupación</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                          <td><input type="number" name="fam_edad[]" class="table-input"></td>
                          <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                          <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                          <td><input type="text" name="fam_civil[]" class="table-input"></td>
                          <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                        </tr>
                        <tr>
                          <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                          <td><input type="number" name="fam_edad[]" class="table-input"></td>
                          <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                          <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                          <td><input type="text" name="fam_civil[]" class="table-input"></td>
                          <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                        </tr>
                        <tr>
                          <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                          <td><input type="number" name="fam_edad[]" class="table-input"></td>
                          <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                          <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                          <td><input type="text" name="fam_civil[]" class="table-input"></td>
                          <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                        </tr>
                        <tr>
                          <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                          <td><input type="number" name="fam_edad[]" class="table-input"></td>
                          <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                          <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                          <td><input type="text" name="fam_civil[]" class="table-input"></td>
                          <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                        </tr>
                        <tr>
                          <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                          <td><input type="number" name="fam_edad[]" class="table-input"></td>
                          <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                          <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                          <td><input type="text" name="fam_civil[]" class="table-input"></td>
                          <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                        </tr>
                        <tr>
                          <td><input type="text" name="fam_nombre[]" class="table-input"></td>
                          <td><input type="number" name="fam_edad[]" class="table-input"></td>
                          <td><input type="text" name="fam_parentesco[]" class="table-input"></td>
                          <td><input type="text" name="fam_educacion[]" class="table-input"></td>
                          <td><input type="text" name="fam_civil[]" class="table-input"></td>
                          <td><input type="text" name="fam_ocupacion[]" class="table-input"></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  
                  <div class="nav-buttons">
                    <button type="button" class="btn btn-prev" onclick="showFormSection(2)">
                      <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-next" onclick="showFormSection(4)">
                      Siguiente <i class="fas fa-arrow-right"></i>
                    </button>
                  </div>
                </div>
                
                <!-- SECCIÓN 4: ASPECTO SOCIOECONÓMICO Y ENSAYO -->
                <div class="form-section" id="section-4">
                  <div class="section-indicator">Sección 4 de 4</div>
                  <h3>IV. ASPECTO SOCIOECONÓMICO</h3>
                  
                  <div class="form-group">
                    <label>Tipo de vivienda: <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="vivienda" value="Casa" required> Casa</label>
                      <label><input type="radio" name="vivienda" value="Apartamento"> Apartamento</label>
                      <label><input type="radio" name="vivienda" value="Otro"> Otro</label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label>Condiciones de la vivienda: <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="condiciones" value="Excelente" required> Excelente</label>
                      <label><input type="radio" name="condiciones" value="Buena"> Buena</label>
                      <label><input type="radio" name="condiciones" value="Regular"> Regular</label>
                      <label><input type="radio" name="condiciones" value="Mala"> Mala</label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label>Material predominante de la vivienda: <span class="required">*</span></label>
                    <div class="radio-group">
                      <label><input type="radio" name="material" value="Ladrillo" required> Ladrillo</label>
                      <label><input type="radio" name="material" value="Block"> Block</label>
                      <label><input type="radio" name="material" value="Adobe"> Adobe</label>
                      <label><input type="radio" name="material" value="Madera"> Madera</label>
                      <label><input type="radio" name="material" value="Mixto"> Mixto</label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label>Servicios básicos con los que cuenta: <span class="required">*</span></label>
                    <div class="checkbox-group">
                      <label><input type="checkbox" name="servicios[]" value="Agua"> Agua potable</label>
                      <label><input type="checkbox" name="servicios[]" value="Luz"> Energía eléctrica</label>
                      <label><input type="checkbox" name="servicios[]" value="Drenaje"> Drenaje</label>
                      <label><input type="checkbox" name="servicios[]" value="Internet"> Internet</label>
                      <label><input type="checkbox" name="servicios[]" value="Telefono"> Teléfono fijo</label>
                    </div>
                  </div>
                  
                  <h3>Ensayo Personal</h3>
                  <div class="form-group">
                    <label for="ensayo">¿Por qué necesita la beca? (mínimo 100 palabras) <span class="required">*</span></label>
                    <textarea id="ensayo" name="ensayo" rows="8" required></textarea>
                  </div>
                  
                  <div class="form-group">
                    <label for="socio">Nombre del Socio del Club Rotario que realizó la Entrevista: <span class="required">*</span></label>
                    <input type="text" id="socio" name="socio" required>
                  </div>
                  
                  <div class="form-group">
                    <label for="firma">Firma del Socio: <span class="required">*</span></label>
                    <input type="text" id="firma" name="firma" required>
                  </div>
                  
                  <div class="nav-buttons">
                    <button type="button" class="btn btn-prev" onclick="showFormSection(3)">
                      <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <button type="submit" class="btn btn-submit">
                      <i class="fas fa-paper-plane"></i> Enviar Solicitud
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
        
        <!-- ESTUDIANTES -->
        <div class="content-section" id="content-estudiantes">
          <div class="section-card">
            <div class="section-header">
              <h2>Gestión de Estudiantes Becados</h2>
              <p>Seguimiento académico y administrativo</p>
            </div>
            <div class="section-content">
              <div class="search-filters">
                <div class="search-row">
                  <div class="search-group">
                    <label>Buscar</label>
                    <input type="text" placeholder="Nombre del estudiante...">
                  </div>
                  <div class="search-group">
                    <label>Grado</label>
                    <select>
                      <option value="">Todos</option>
                      <option value="1ro-basico">1ro Básico</option>
                      <option value="2do-basico">2do Básico</option>
                      <option value="3ro-basico">3ro Básico</option>
                      <option value="4to-bachillerato">4to Bachillerato</option>
                      <option value="5to-bachillerato">5to Bachillerato</option>
                    </select>
                  </div>
                  <div class="search-group">
                    <label>Estado</label>
                    <select>
                      <option value="activo">Activo</option>
                      <option value="suspendido">Suspendido</option>
                      <option value="graduado">Graduado</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <div class="table-container">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Estudiante</th>
                      <th>Edad</th>
                      <th>Grado Actual</th>
                      <th>Promedio</th>
                      <th>Año Inicio</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Carlos Pérez Morales</td>
                      <td>17</td>
                      <td>5to Bachillerato</td>
                      <td>85.5</td>
                      <td>2022</td>
                      <td><span class="status-badge status-aprobado">Activo</span></td>
                      <td>
                        <button class="btn btn-primary">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-warning">
                          <i class="fas fa-edit"></i>
                        </button>
                      </td>
                    </tr>
                    <tr>
                      <td>Lucía Martínez Flores</td>
                      <td>16</td>
                      <td>4to Bachillerato</td>
                      <td>92.3</td>
                      <td>2021</td>
                      <td><span class="status-badge status-aprobado">Activo</span></td>
                      <td>
                        <button class="btn btn-primary">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-warning">
                          <i class="fas fa-edit"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        
        <!-- REPORTES -->
        <div class="content-section" id="content-reportes">
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-header">
                <div>
                  <div class="stat-number">85%</div>
                  <div class="stat-label">Tasa de Graduación</div>
                </div>
                <div class="stat-icon icon-aprobado">
                  <i class="fas fa-graduation-cap"></i>
                </div>
              </div>
            </div>
            
            <div class="stat-card">
              <div class="stat-header">
                <div>
                  <div class="stat-number">Q287,500</div>
                  <div class="stat-label">Inversión Total 2025</div>
                </div>
                <div class="stat-icon icon-total">
                  <i class="fas fa-dollar-sign"></i>
                </div>
              </div>
            </div>
          </div>
          
          <div class="chart-container">
            <h3 class="chart-title">Distribución por Grado Académico</h3>
            <canvas id="gradosChart" width="400" height="200"></canvas>
          </div>
          
          <div class="section-card">
            <div class="section-header">
              <h2>Generar Reportes</h2>
              <p>Exportar datos e informes del programa de becas</p>
            </div>
            <div class="section-content">
              <div class="search-row">
                <div class="search-group">
                  <label>Tipo de Reporte</label>
                  <select>
                    <option>Reporte General de Becados</option>
                    <option>Evaluaciones Socioeconómicas</option>
                    <option>Rendimiento Académico</option>
                    <option>Seguimiento Familiar</option>
                  </select>
                </div>
                <div class="search-group">
                  <label>Período</label>
                  <select>
                    <option>2025</option>
                    <option>2024</option>
                    <option>Histórico</option>
                  </select>
                </div>
                <div class="search-group">
                  <label>&nbsp;</label>
                  <button class="btn btn-success">
                    <i class="fas fa-download"></i> Generar PDF
                  </button>
                </div>
                <div class="search-group">
                  <label>&nbsp;</label>
                  <button class="btn btn-primary">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- CONFIGURACIÓN -->
        <div class="content-section" id="content-configuracion">
          <div class="section-card">
            <div class="section-header">
              <h2>Configuración del Sistema</h2>
              <p>Ajustes generales y parámetros del programa</p>
            </div>
            <div class="section-content">
              <div class="form-group">
                <label for="monto_beca">Monto de Beca Mensual (Q)</label>
                <input type="number" id="monto_beca" value="500" min="0">
              </div>
              
              <div class="form-group">
                <label for="duracion_beca">Duración Máxima de Beca (años)</label>
                <input type="number" id="duracion_beca" value="5" min="1" max="10">
              </div>
              
              <div class="form-group">
                <label for="promedio_minimo">Promedio Mínimo Requerido</label>
                <input type="number" id="promedio_minimo" value="70" min="0" max="100">
              </div>
              
              <div class="form-group">
                <label>Criterios de Evaluación</label>
                <div class="checkbox-group">
                  <label><input type="checkbox" checked> Situación económica familiar</label>
                  <label><input type="checkbox" checked> Rendimiento académico previo</label>
                  <label><input type="checkbox" checked> Motivación personal</label>
                  <label><input type="checkbox" checked> Recomendación del centro educativo</label>
                </div>
              </div>
              
              <button class="btn btn-success">
                <i class="fas fa-save"></i> Guardar Configuración
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Variables globales
    let currentFormSection = 1;
    const totalFormSections = 4;
    
    // NAVEGACIÓN PRINCIPAL
    function showContent(section) {
      // Ocultar todas las secciones
      const sections = document.querySelectorAll('.content-section');
      sections.forEach(s => s.classList.remove('active'));
      
      // Mostrar sección seleccionada
      document.getElementById(`content-${section}`).classList.add('active');
      
      // Actualizar navegación activa
      const navLinks = document.querySelectorAll('.nav-link');
      navLinks.forEach(link => link.classList.remove('active'));
      event.target.classList.add('active');
      
      // Actualizar título
      const titles = {
        dashboard: 'Dashboard',
        aplicaciones: 'Aplicaciones',
        evaluacion: 'Nueva Evaluación',
        estudiantes: 'Estudiantes',
        reportes: 'Reportes',
        configuracion: 'Configuración'
      };
      document.getElementById('page-title').textContent = titles[section];
      
      // Cerrar sidebar en móvil
      if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
      }
    }
    
    // NAVEGACIÓN DEL FORMULARIO
    function showFormSection(sectionNumber) {
      // Ocultar sección actual
      document.getElementById(`section-${currentFormSection}`).classList.remove('active');
      
      // Mostrar nueva sección
      document.getElementById(`section-${sectionNumber}`).classList.add('active');
      
      // Actualizar barra de progreso
      const progressPercentage = (sectionNumber / totalFormSections) * 100;
      document.getElementById('progress-bar').style.width = `${progressPercentage}%`;
      
      currentFormSection = sectionNumber;
      
      // Desplazar hacia arriba
      document.querySelector('.content-area').scrollTo(0, 0);
    }
    
    // SIDEBAR MÓVIL
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
    }
    
    // VALIDACIÓN Y ENVÍO DEL FORMULARIO
    document.getElementById('beca-form').addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Aquí se enviarían los datos al servidor
      alert('¡Evaluación socioeconómica guardada exitosamente!\n\nLa solicitud ha sido registrada y está pendiente de revisión.');
      
      // Limpiar formulario y volver al dashboard
      this.reset();
      showFormSection(1);
      showContent('dashboard');
      
      // Simular actualización de estadísticas
      updateDashboardStats();
    });
    
    // ACTUALIZAR ESTADÍSTICAS
    function updateDashboardStats() {
      // Incrementar solicitudes pendientes
      const pendientesEl = document.querySelector('.icon-pendiente').closest('.stat-card').querySelector('.stat-number');
      let current = parseInt(pendientesEl.textContent);
      pendientesEl.textContent = current + 1;
      
      // Incrementar total
      const totalEl = document.querySelector('.icon-total').closest('.stat-card').querySelector('.stat-number');
      let currentTotal = parseInt(totalEl.textContent);
      totalEl.textContent = currentTotal + 1;
    }
    
    // INICIALIZACIÓN DE GRÁFICOS (simulado)
    function initCharts() {
      // Gráfico de solicitudes por mes (simulado)
      const solicitudesCtx = document.getElementById('solicitudesChart');
      if (solicitudesCtx) {
        const ctx = solicitudesCtx.getContext('2d');
        
        // Dibujar gráfico simple
        ctx.fillStyle = '#005daa';
        ctx.fillRect(50, 150, 40, 30);
        ctx.fillRect(100, 120, 40, 60);
        ctx.fillRect(150, 100, 40, 80);
        ctx.fillRect(200, 80, 40, 100);
        ctx.fillRect(250, 60, 40, 120);
        ctx.fillRect(300, 90, 40, 90);
        
        // Etiquetas
        ctx.fillStyle = '#333';
        ctx.font = '12px Arial';
        ctx.fillText('Ene', 60, 195);
        ctx.fillText('Feb', 110, 195);
        ctx.fillText('Mar', 160, 195);
        ctx.fillText('Abr', 210, 195);
        ctx.fillText('May', 260, 195);
        ctx.fillText('Jun', 310, 195);
      }
      
      // Gráfico de distribución por grados (simulado)
      const gradosCtx = document.getElementById('gradosChart');
      if (gradosCtx) {
        const ctx = gradosCtx.getContext('2d');
        
        // Gráfico de barras horizontales
        const colors = ['#005daa', '#f2a900', '#28a745', '#fd7e14', '#dc3545'];
        const data = [25, 35, 30, 20, 15];
        const labels = ['1ro Básico', '2do Básico', '3ro Básico', '4to Bach.', '5to Bach.'];
        
        for (let i = 0; i < data.length; i++) {
          ctx.fillStyle = colors[i];
          ctx.fillRect(100, i * 35 + 10, data[i] * 8, 25);
          
          ctx.fillStyle = '#333';
          ctx.font = '14px Arial';
          ctx.fillText(labels[i], 10, i * 35 + 27);
          ctx.fillText(data[i], data[i] * 8 + 110, i * 35 + 27);
        }
      }
    }
    
    // FUNCIONES SIMULADAS DE ACCIÓN
    function aprobarSolicitud(id) {
      if (confirm('¿Está seguro de aprobar esta solicitud?')) {
        alert('Solicitud aprobada exitosamente');
        // Aquí se actualizaría la base de datos
      }
    }
    
    function rechazarSolicitud(id) {
      const motivo = prompt('Ingrese el motivo del rechazo:');
      if (motivo) {
        alert('Solicitud rechazada. Se ha notificado al estudiante.');
        // Aquí se actualizaría la base de datos
      }
    }
    
    function verDetalleSolicitud(id) {
      alert('Abriendo detalles de la solicitud #' + id);
      // Aquí se abriría un modal o se navegaría a una página de detalles
    }
    
    // CONFIGURACIÓN INICIAL
    document.addEventListener('DOMContentLoaded', function() {
      // Inicializar gráficos
      setTimeout(initCharts, 100);
      
      // Configurar fecha actual
      const hoy = new Date();
      document.getElementById('dia').value = hoy.getDate();
      document.getElementById('mes').value = hoy.getMonth() + 1;
      document.getElementById('anio').value = hoy.getFullYear();
      
      // Cerrar sidebar al hacer clic fuera en móvil
      document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !menuBtn.contains(e.target)) {
          sidebar.classList.remove('open');
        }
      });
    });
    
    // ANIMACIONES Y EFECTOS
    function addHoverEffects() {
      const cards = document.querySelectorAll('.stat-card');
      cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0) scale(1)';
        });
      });
    }
    
    // NOTIFICACIONES SIMULADAS
    function showNotification(message, type = 'success') {
      const notification = document.createElement('div');
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : '#dc3545'};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: slideIn 0.3s ease;
      `;
      notification.textContent = message;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }
    
    // Agregar estilos para animaciones de notificaciones
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      
      @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
      
      .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
      }
      
      .highlight-row {
        background: rgba(0, 93, 170, 0.05) !important;
        border-left: 4px solid var(--azul-rotary);
      }
      
      .fade-in {
        animation: fadeIn 0.5s ease;
      }
      
      .loading {
        opacity: 0.6;
        pointer-events: none;
      }
      
      .success-animation {
        animation: pulse 0.6s ease;
      }
      
      @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
      }
    `;
    document.head.appendChild(style);
    
    // FUNCIONES ADICIONALES DE GESTIÓN
    function exportarDatos(formato) {
      showNotification(`Exportando datos en formato ${formato.toUpperCase()}...`);
      
      setTimeout(() => {
        showNotification(`Archivo ${formato.toUpperCase()} generado exitosamente`);
      }, 2000);
    }
    
    function buscarEstudiante(termino) {
      const filas = document.querySelectorAll('.data-table tbody tr');
      filas.forEach(fila => {
        const texto = fila.textContent.toLowerCase();
        if (texto.includes(termino.toLowerCase())) {
          fila.style.display = '';
          fila.classList.add('highlight-row');
        } else {
          fila.style.display = 'none';
          fila.classList.remove('highlight-row');
        }
      });
    }
    
    // VALIDACIÓN EN TIEMPO REAL
    function setupRealTimeValidation() {
      const requiredFields = document.querySelectorAll('input[required], textarea[required]');
      
      requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
          if (!this.value.trim()) {
            this.style.borderColor = '#dc3545';
            this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.2)';
          } else {
            this.style.borderColor = '#28a745';
            this.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.2)';
          }
        });
        
        field.addEventListener('input', function() {
          if (this.value.trim()) {
            this.style.borderColor = '#ddd';
            this.style.boxShadow = 'none';
          }
        });
      });
    }
    
    // INICIALIZAR EFECTOS
    document.addEventListener('DOMContentLoaded', function() {
      addHoverEffects();
      setupRealTimeValidation();
    });
  </script>
</body>
</html>