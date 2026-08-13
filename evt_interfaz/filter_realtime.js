// FILTRADO EN TIEMPO REAL CON OCULTACIÓN DE SECCIONES VACÍAS
const inputFiltro = document.getElementById('filtro_titulo');
const eventCards = document.querySelectorAll('.evento-card');
const countEventos = document.getElementById('countEventos');

if (inputFiltro) {
    inputFiltro.addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleCount = 0;

        // Objeto para contar eventos visibles por sección
        const visibleBySection = {};

        eventCards.forEach(card => {
            const titulo = card.getAttribute('data-titulo');

            if (titulo.includes(searchTerm)) {
                card.style.display = '';
                visibleCount++;

                // Encontrar la sección padre y contar
                const parentGrid = card.closest('[id^="grid-"]');
                if (parentGrid) {
                    const sectionId = parentGrid.id;
                    visibleBySection[sectionId] = (visibleBySection[sectionId] || 0) + 1;
                }
            } else {
                card.style.display = 'none';
            }
        });

        // Mostrar/ocultar secciones de mes según eventos visibles
        const allSections = document.querySelectorAll('.users-section');
        allSections.forEach(section => {
            const grid = section.querySelector('[id^="grid-"]');
            if (grid) {
                const hasVisibleEvents = visibleBySection[grid.id] > 0;

                if (searchTerm === '') {
                    // Si no hay búsqueda, mostrar todas las secciones
                    section.style.display = '';
                } else {
                    // Si hay búsqueda, solo mostrar secciones con eventos visibles
                    section.style.display = hasVisibleEvents ? '' : 'none';
                }
            }
        });

        // Actualizar contador
        countEventos.textContent = visibleCount;

        // Mostrar mensaje si no hay resultados
        const contentWrapper = document.querySelector('.content-wrapper');
        let emptyMessage = document.getElementById('emptyMessage');

        if (visibleCount === 0 && searchTerm !== '') {
            if (!emptyMessage) {
                emptyMessage = document.createElement('div');
                emptyMessage.id = 'emptyMessage';
                emptyMessage.className = 'alert alert-light border text-center p-5';
                emptyMessage.style.marginTop = '20px';
                emptyMessage.innerHTML = `
                    <i class="bi bi-search fs-1 mb-3" style="color: white; display: block;"></i>
                    <p class="mb-0" style="color: white;">No se encontraron eventos con "${this.value}"</p>
                `;
                contentWrapper.appendChild(emptyMessage);
            }
        } else {
            if (emptyMessage) {
                emptyMessage.remove();
            }
        }
    });
}
