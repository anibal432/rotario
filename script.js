// script.js - JavaScript para manejar el formulario de registro
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registrationForm');
    const fileInput = document.getElementById('boleta_pago');
    const preview = document.getElementById('preview');
    const loading = document.getElementById('loading');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const runnerNumberDisplay = document.getElementById('runnerNumberDisplay');
    const submitBtn = document.getElementById('submitBtn');

    // Preview de imagen
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validar tipo de archivo
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                showError('Formato de archivo no válido. Solo se permiten JPG, PNG y PDF');
                this.value = '';
                return;
            }

            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showError('El archivo es demasiado grande. Máximo 5MB');
                this.value = '';
                return;
            }

            // Mostrar preview solo para imágenes
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }

            // Actualizar label del archivo
            const label = document.querySelector('.file-upload-label span');
            label.textContent = `Archivo seleccionado: ${file.name}`;
        }
    });

    // Validar edad en tiempo real para mostrar categoría
    document.getElementById('edad').addEventListener('input', function() {
        const edad = parseInt(this.value);
        const genero = document.getElementById('genero').value;
        
        if (edad && genero) {
            const categoria = determinarCategoria(edad, genero);
            mostrarCategoria(categoria);
        }
    });

    document.getElementById('genero').addEventListener('change', function() {
        const edad = parseInt(document.getElementById('edad').value);
        const genero = this.value;
        
        if (edad && genero) {
            const categoria = determinarCategoria(edad, genero);
            mostrarCategoria(categoria);
        }
    });

    // Función para determinar categoría
    function determinarCategoria(edad, genero) {
        if (edad >= 18 && edad <= 29) {
            return `Libre ${genero}`;
        } else if (edad >= 30 && edad <= 39) {
            return `Master A ${genero}`;
        } else if (edad >= 40 && edad <= 49) {
            return `Master B ${genero}`;
        } else if (edad >= 50) {
            return `Master C ${genero}`;
        }
        return '';
    }

    // Mostrar categoría calculada
    function mostrarCategoria(categoria) {
        let categoriaDisplay = document.getElementById('categoria-display');
        if (!categoriaDisplay) {
            categoriaDisplay = document.createElement('div');
            categoriaDisplay.id = 'categoria-display';
            categoriaDisplay.style.cssText = `
                background: #dbeafe;
                padding: 10px;
                border-radius: 5px;
                margin-top: 10px;
                font-weight: bold;
                color: #1e40af;
                text-align: center;
            `;
            document.getElementById('genero').parentNode.appendChild(categoriaDisplay);
        }
        categoriaDisplay.textContent = `Tu categoría será: ${categoria}`;
    }

    // Manejar envío del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validar términos y condiciones
        if (!document.getElementById('terminos').checked) {
            showError('Debes aceptar los términos y condiciones');
            return;
        }

        // Mostrar loading
        loading.style.display = 'block';
        submitBtn.disabled = true;
        hideMessages();

        try {
            const formData = new FormData(form);
            
            const response = await fetch('registro.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Mostrar éxito
                showSuccess(result.data.numero_corredor);
                form.reset();
                preview.style.display = 'none';
                const categoriaDisplay = document.getElementById('categoria-display');
                if (categoriaDisplay) {
                    categoriaDisplay.remove();
                }
                
                // Reset file upload label
                const label = document.querySelector('.file-upload-label span');
                label.textContent = 'Haz clic para subir tu boleta de pago';
                
            } else {
                showError(result.message);
            }

        } catch (error) {
            console.error('Error:', error);
            showError('Error de conexión. Por favor, intenta nuevamente.');
        } finally {
            loading.style.display = 'none';
            submitBtn.disabled = false;
        }
    });

    // Funciones para mostrar mensajes
    function showSuccess(numeroCarrera) {
        hideMessages();
        document.getElementById('runnerNumber').textContent = numeroCarrera;
        successMessage.style.display = 'block';
        runnerNumberDisplay.style.display = 'block';
        
        // Scroll al mensaje de éxito
        successMessage.scrollIntoView({ behavior: 'smooth' });
    }

    function showError(message) {
        hideMessages();
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
        
        // Scroll al error
        errorMessage.scrollIntoView({ behavior: 'smooth' });
    }

    function hideMessages() {
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';
        runnerNumberDisplay.style.display = 'none';
    }

    // Validación en tiempo real de email
    document.getElementById('email').addEventListener('blur', function() {
        const email = this.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.setCustomValidity('Por favor ingresa un email válido');
            this.reportValidity();
        } else {
            this.setCustomValidity('');
        }
    });

    // Validación de DPI (formato guatemalteco)
    document.getElementById('dpi').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, ''); // Solo números
        
        // Formatear DPI: #### ##### ####
        if (value.length > 4 && value.length <= 9) {
            value = value.substring(0, 4) + ' ' + value.substring(4);
        } else if (value.length > 9) {
            value = value.substring(0, 4) + ' ' + value.substring(4, 9) + ' ' + value.substring(9, 13);
        }
        
        this.value = value;
        
        // Validar longitud
        const cleanValue = value.replace(/\s/g, '');
        if (cleanValue.length > 0 && cleanValue.length !== 13) {
            this.setCustomValidity('El DPI debe tener 13 dígitos');
        } else {
            this.setCustomValidity('');
        }
    });

    // Validación de teléfono guatemalteco
    document.getElementById('telefono').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, ''); // Solo números
        
        // Formatear teléfono: ####-####
        if (value.length > 4) {
            value = value.substring(0, 4) + '-' + value.substring(4, 8);
        }
        
        this.value = value;
        
        // Validar longitud (8 dígitos para Guatemala)
        const cleanValue = value.replace(/-/g, '');
        if (cleanValue.length > 0 && cleanValue.length !== 8) {
            this.setCustomValidity('El teléfono debe tener 8 dígitos');
        } else {
            this.setCustomValidity('');
        }
    });

    // Validar edad mínima
    document.getElementById('edad').addEventListener('input', function() {
        const edad = parseInt(this.value);
        if (edad < 18) {
            this.setCustomValidity('Debes ser mayor de 18 años para participar');
        } else if (edad > 99) {
            this.setCustomValidity('Por favor verifica tu edad');
        } else {
            this.setCustomValidity('');
        }
    });
});