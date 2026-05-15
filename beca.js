    function showSection(sectionId) {

      document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
      });

      document.getElementById(sectionId).classList.add('active');
      

      document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
      });
      
      document.querySelector(`.menu-item[onclick="showSection('${sectionId}')"]`).classList.add('active');
      
  
      const sectionTitles = {
        'dashboard': 'Dashboard',
        'estudiantes': 'Gestión de Estudiantes',
        'socioeconomica': 'Evaluación Socioeconómica',
        'academico': 'Control Académico',
        'pagos': 'Gestión de Pagos',
        'espera': 'Lista de Espera',
        'reportes': 'Reportes y Estadísticas',
        'actividades': 'Actividades de Recaudación',
        'configuracion': 'Configuración del Sistema'
      };
      
      const sectionIcons = {
        'dashboard': 'fa-home',
        'estudiantes': 'fa-user-graduate',
        'socioeconomica': 'fa-chart-line',
        'academico': 'fa-book-open',
        'pagos': 'fa-hand-holding-usd',
        'espera': 'fa-clock',
        'reportes': 'fa-chart-pie',
        'actividades': 'fa-running',
        'configuracion': 'fa-cog'
      };
      
      const pageTitle = document.querySelector('.page-title h1');
      pageTitle.innerHTML = `<i class="fas ${sectionIcons[sectionId]}"></i> ${sectionTitles[sectionId]}`;
      

      const sectionDescriptions = {
        'dashboard': 'Resumen general del sistema de becas',
        'estudiantes': 'Administra los estudiantes beneficiados con becas',
        'socioeconomica': 'Analiza las condiciones económicas de los estudiantes',
        'academico': 'Monitorea el rendimiento académico de los becados',
        'pagos': 'Registro y seguimiento de pagos de becas',
        'espera': 'Estudiantes en espera de una beca disponible',
        'reportes': 'Genera reportes detallados del programa de becas',
        'actividades': 'Gestiona las actividades para recaudar fondos',
        'configuracion': 'Personaliza la plataforma de gestión de becas'
      };
      
      document.querySelector('.page-title p').textContent = sectionDescriptions[sectionId];
    }
    
    function generateReport(type) {
      alert(`Generando reporte de ${type}...`);

    }
    
    function showHelp() {
      alert('Centro de ayuda: ¿En qué podemos ayudarte hoy?');
    }
    

    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', function() {
        this.parentElement.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
      });
    });