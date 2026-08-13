/**
 * Sistema de sincronización de eventos en tiempo real
 * Actualiza el selector de eventos cuando se crean o editan desde otras pestañas
 */

(function() {
    'use strict';
    
    const SYNC_INTERVAL = 30000; // 30 segundos
    let syncTimer = null;
    
    /**
     * Actualiza el selector de eventos consultando el servidor
     */
    async function actualizarSelectorEventos() {
        try {
            const response = await fetch('obtener_eventos.php');
            const data = await response.json();
            
            if (data.success) {
                const selectEvento = document.querySelector('select[name="id_evento"]');
                if (!selectEvento) return;
                
                const eventoActual = selectEvento.value;
                const eventosActuales = Array.from(selectEvento.options)
                    .filter(opt => opt.value)
                    .map(opt => ({
                        id: opt.value,
                        texto: opt.textContent
                    }));
                
                const eventosNuevos = data.eventos.map(e => ({
                    id: e.id_evento.toString(),
                    texto: `${e.titulo} • ${e.tipo == 1 ? 'Teatro 420' : 'Pasarela 540'}`
                }));
                
                // Verificar si hay cambios reales
                const hayNuevos = eventosNuevos.some(en => 
                    !eventosActuales.find(ea => ea.id === en.id)
                );
                
                const hayEliminados = eventosActuales.some(ea => 
                    !eventosNuevos.find(en => en.id === ea.id)
                );
                
                const hayModificados = eventosNuevos.some(en => {
                    const actual = eventosActuales.find(ea => ea.id === en.id);
                    return actual && actual.texto !== en.texto;
                });
                
                if (hayNuevos || hayEliminados || hayModificados) {
                    console.log('Cambios detectados en eventos:', {hayNuevos, hayEliminados, hayModificados});
                    
                    // Reconstruir el selector
                    selectEvento.innerHTML = '<option value="">Seleccionar evento...</option>';
                    
                    eventosNuevos.forEach(evento => {
                        const option = document.createElement('option');
                        option.value = evento.id;
                        option.textContent = evento.texto;
                        if (evento.id === eventoActual) {
                            option.selected = true;
                        }
                        selectEvento.appendChild(option);
                    });
                    
                    // Mostrar notificación
                    if (hayNuevos && typeof notify !== 'undefined') {
                        notify.success('Lista de eventos actualizada');
                    } else if (hayModificados && typeof notify !== 'undefined') {
                        notify.info('Información de eventos actualizada');
                    }
                }
            }
        } catch (error) {
            console.error('Error al actualizar eventos:', error);
        }
    }
    
    /**
     * Maneja la detección de eventos editados
     */
    function manejarEventoEditado(idEvento) {
        const eventoActual = document.querySelector('select[name="id_evento"]')?.value;
        
        console.log('Evento editado:', idEvento, 'Evento actual:', eventoActual);
        
        if (idEvento == eventoActual && eventoActual) {
            // Es el evento que estamos viendo, recargar la página
            if (typeof notify !== 'undefined') {
                notify.info('El evento ha sido actualizado, recargando...');
            }
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            // Es otro evento, solo actualizar el selector
            actualizarSelectorEventos();
        }
    }
    
    /**
     * Escucha cambios en localStorage
     */
    window.addEventListener('storage', (e) => {
        // Evento creado
        if (e.key === 'evt_upd') {
            console.log('Evento creado detectado');
            actualizarSelectorEventos();
        }
        
        // Evento editado
        if (e.key === 'evento_actualizado' && e.newValue) {
            try {
                const data = JSON.parse(e.newValue);
                manejarEventoEditado(data.id_evento);
            } catch (error) {
                console.error('Error al procesar evento_actualizado:', error);
            }
        }
        
        // Categorías actualizadas
        if (e.key === 'categorias_actualizadas' && e.newValue) {
            try {
                const data = JSON.parse(e.newValue);
                const eventoActual = document.querySelector('select[name="id_evento"]')?.value;
                
                console.log('Categorías actualizadas:', data.id_evento, 'Evento actual:', eventoActual);
                
                if (data.id_evento == eventoActual && eventoActual) {
                    if (typeof notify !== 'undefined') {
                        notify.info('Las categorías han sido actualizadas, recargando...');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } catch (error) {
                console.error('Error al procesar categorias_actualizadas:', error);
            }
        }
        
        // Descuentos actualizados
        if (e.key === 'descuentos_actualizados' && e.newValue) {
            try {
                const data = JSON.parse(e.newValue);
                const eventoActual = document.querySelector('select[name="id_evento"]')?.value;
                
                console.log('Descuentos actualizados:', data.id_evento, 'Evento actual:', eventoActual);
                
                if (data.id_evento == eventoActual && eventoActual) {
                    if (typeof notify !== 'undefined') {
                        notify.info('Los descuentos han sido actualizados, recargando...');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } catch (error) {
                console.error('Error al procesar descuentos_actualizados:', error);
            }
        }
        
        // Mapa actualizado
        if (e.key === 'mapa_actualizado' && e.newValue) {
            try {
                const data = JSON.parse(e.newValue);
                const eventoActual = document.querySelector('select[name="id_evento"]')?.value;
                
                console.log('Mapa actualizado:', data.id_evento, 'Evento actual:', eventoActual);
                
                if (data.id_evento == eventoActual && eventoActual) {
                    if (typeof notify !== 'undefined') {
                        notify.info('El mapa de asientos ha sido actualizado, recargando...');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            } catch (error) {
                console.error('Error al procesar mapa_actualizado:', error);
            }
        }
    });
    
    /**
     * Escucha mensajes de otras ventanas
     */
    window.addEventListener('message', (e) => {
        if (e.data && e.data.type === 'evento_actualizado') {
            console.log('Mensaje recibido: evento editado', e.data.id_evento);
            
            // Propagar a otras pestañas vía localStorage
            localStorage.setItem('evento_actualizado', JSON.stringify({
                id_evento: e.data.id_evento,
                timestamp: Date.now()
            }));
            
            manejarEventoEditado(e.data.id_evento);
        }
    });
    
    /**
     * Inicializar sincronización periódica
     */
    function iniciarSync() {
        // Limpiar timer anterior si existe
        if (syncTimer) {
            clearInterval(syncTimer);
        }
        
        // Sincronizar cada 30 segundos
        syncTimer = setInterval(actualizarSelectorEventos, SYNC_INTERVAL);
        
        console.log('Sistema de sincronización de eventos iniciado');
    }
    
    /**
     * Detener sincronización
     */
    function detenerSync() {
        if (syncTimer) {
            clearInterval(syncTimer);
            syncTimer = null;
            console.log('Sistema de sincronización de eventos detenido');
        }
    }
    
    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarSync);
    } else {
        iniciarSync();
    }
    
    // Detener cuando se cierra la página
    window.addEventListener('beforeunload', detenerSync);
    
    // Exponer funciones globalmente si es necesario
    window.eventoSync = {
        actualizar: actualizarSelectorEventos,
        iniciar: iniciarSync,
        detener: detenerSync
    };
})();
