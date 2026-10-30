import '@testing-library/jest-dom/vitest';

/**
 * Observador de tamaño que jsdom no implementa.
 *
 * Headless UI lo usa para repositionar el panel de opciones al cambiar el
 * tamaño del viewport, de modo que la prueba dispose de una implementación
 * mínima que no altera el comportamiento del componente.
 */
class ResizeObserverStub {
    public observe(): void {
        // La posición del panel no es relevante para las pruebas.
    }

    public unobserve(): void {
        // La posición del panel no es relevante para las pruebas.
    }

    public disconnect(): void {
        // La posición del panel no es relevante para las pruebas.
    }
}

if (typeof globalThis.ResizeObserver === 'undefined') {
    globalThis.ResizeObserver = ResizeObserverStub as unknown as typeof ResizeObserver;
}

if (typeof Element.prototype.scrollIntoView === 'undefined') {
    Element.prototype.scrollIntoView = (): void => {
        // El desplazamiento no es relevante para las pruebas.
    };
}
