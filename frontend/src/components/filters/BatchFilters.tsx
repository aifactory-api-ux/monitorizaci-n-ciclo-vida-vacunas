import { CustomSelect, type CustomSelectOption } from '@/components/filters/CustomSelect';
import { ALERT_STATUSES, BATCH_PHASES } from '@/types/batch';

/**
 * Criterio de filtrado del listado de lotes.
 *
 * Los tres campos usan los mismos nombres y literales que los parámetros del
 * endpoint de búsqueda avanzada, de modo que el frontend no renombra ni
 * reinterpreta el criterio.
 */
export interface BatchFilterValue {
    family: string;
    phase: string;
    alert_status: string;
}

export interface BatchFiltersProps {
    /** Criterio aplicado en este momento. */
    value: BatchFilterValue;
    /** Callback que recibe el criterio completo tras el cambio de un filtro. */
    onChange: (value: BatchFilterValue) => void;
    /** Familias de producto ofrecidas en el filtro de familia. */
    familyOptions?: string[];
}

/**
 * Familias de producto del catálogo de vacunas.
 */
export const PRODUCT_FAMILIES = ['Vacunas conjugadas', 'Vacunas combinadas', 'Vacunas orales'];

const ALL_OPTION_VALUE = '';

function toOptions(values: readonly string[], allLabel: string): CustomSelectOption[] {
    return [
        { value: ALL_OPTION_VALUE, label: allLabel },
        ...values.map((value) => ({ value, label: value })),
    ];
}

/**
 * Panel de filtros del listado de lotes.
 *
 * Los tres desplegables se construyen exclusivamente con `CustomSelect`, que
 * a su vez se apoya en el `Listbox` de Headless UI, de forma que el panel no
 * contiene ningún selector nativo del navegador (véase D13).
 */
export function BatchFilters({ value, onChange, familyOptions = PRODUCT_FAMILIES }: BatchFiltersProps) {
    const familySelectOptions: CustomSelectOption[] = [
        { value: ALL_OPTION_VALUE, label: 'Todas las familias' },
        ...familyOptions.map((family) => ({ value: family, label: family })),
    ];

    const phaseSelectOptions = toOptions(BATCH_PHASES, 'Todas las fases');
    const alertStatusSelectOptions = toOptions(ALERT_STATUSES, 'Todos los estados de alerta');

    return (
        <section aria-label="Filtros de lotes" className="grid gap-4 sm:grid-cols-3">
            <CustomSelect
                label="Familia de producto"
                value={value.family}
                options={familySelectOptions}
                searchable
                onChange={(family) => onChange({ ...value, family })}
                testId="filter-family"
            />

            <CustomSelect
                label="Fase"
                value={value.phase}
                options={phaseSelectOptions}
                onChange={(phase) => onChange({ ...value, phase })}
                testId="filter-phase"
            />

            <CustomSelect
                label="Estado de alerta"
                value={value.alert_status}
                options={alertStatusSelectOptions}
                onChange={(alert_status) => onChange({ ...value, alert_status })}
                testId="filter-alert-status"
            />
        </section>
    );
}
