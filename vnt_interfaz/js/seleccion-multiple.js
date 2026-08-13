// Funcionalidades avanzadas de selección múltiple

// Mostrar tooltip de ayuda al inicio
function mostrarTooltipSeleccion() {
    const tooltip = document.createElement('div');
    tooltip.className = 'selection-tooltip';
    tooltip.innerHTML = `
        <i class="bi bi-lightbulb"></i>
        <strong>Tip:</strong> Usa Ctrl+Click para seleccionar rangos o doble click en las filas
    `;
    document.body.appendChild(tooltip);
    
    setTimeout(() => {
        tooltip.style.opacity = '0';
        setTimeout(() => tooltip.remove(), 300);
    }, 5000);
}

// Contador flotante removido por preferencia del usuario

// Resaltar asientos en rango (preview)
let rangePreviewTimeout;
function mostrarPreviewRango(asientoActual) {
    if (!ultimoAsientoSeleccionado) return;
    
    clearTimeout(rangePreviewTimeout);
    
    // Limpiar preview anterior
    document.querySelectorAll('.seat.in-range').forEach(s => s.classList.remove('in-range'));
    
    const todosAsientos = Array.from(document.querySelectorAll('.seat'));
    const indexUltimo = todosAsientos.findIndex(s => s.dataset.asientoId === ultimoAsientoSeleccionado);
    const indexActual = todosAsientos.findIndex(s => s.dataset.asientoId === asientoActual);
    
    if (indexUltimo === -1 || indexActual === -1) return;
    
    const inicio = Math.min(indexUltimo, indexActual);
    const fin = Math.max(indexUltimo, indexActual);
    
    for (let i = inicio; i <= fin; i++) {
        const seat = todosAsientos[i];
        if (!seat.classList.contains('vendido') && !seat.classList.contains('selected')) {
            seat.classList.add('in-range');
        }
    }
    
    // Limpiar después de 2 segundos
    rangePreviewTimeout = setTimeout(() => {
        document.querySelectorAll('.seat.in-range').forEach(s => s.classList.remove('in-range'));
    }, 2000);
}

// Funciones removidas por preferencia del usuario
// function seleccionarPorCategoria() { ... }
// function seleccionarCategoria() { ... }

// Función removida por preferencia del usuario
// function seleccionInteligente() { ... }

// Inicializar funcionalidades avanzadas
document.addEventListener('DOMContentLoaded', () => {
    // Mostrar tooltip de ayuda después de 2 segundos
    setTimeout(mostrarTooltipSeleccion, 2000);
    
    // Agregar preview de rango con Ctrl
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && ultimoAsientoSeleccionado) {
            document.querySelectorAll('.seat:not(.vendido):not(.selected)').forEach(seat => {
                seat.addEventListener('mouseenter', function handler() {
                    if (e.ctrlKey) {
                        mostrarPreviewRango(this.dataset.asientoId);
                    }
                    this.removeEventListener('mouseenter', handler);
                });
            });
        }
    });
    
    document.addEventListener('keyup', (e) => {
        if (e.key === 'Control') {
            document.querySelectorAll('.seat.in-range').forEach(s => s.classList.remove('in-range'));
        }
    });
});
