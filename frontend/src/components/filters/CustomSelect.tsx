import { Label, Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/react';
import { useMemo, useState, type KeyboardEvent } from 'react';

/**
 * Opción ofrecida por el desplegable.
 */
export interface CustomSelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

export interface CustomSelectProps {
    /** Texto visible que identifica el control. */
    label: string;
    /** Valor seleccionado. La cadena vacía representa la ausencia de selección. */
    value: string;
    /** Opciones que el usuario puede elegir. */
    options: CustomSelectOption[];
    /** Callback que recibe el valor literal de la opción elegida. */
    onChange: (value: string) => void;
    /** Muestra un campo de búsqueda que filtra las opciones por su etiqueta. */
    searchable?: boolean;
    /** Texto mostrado cuando no hay ninguna opción seleccionada. */
    placeholder?: string;
    /** Impide la interacción con el control. */
    disabled?: boolean;
    /** Identificador del control en las pruebas. */
    testId?: string;
}

function normalize(text: string): string {
    return text.trim().toLocaleLowerCase('es');
}

/**
 * Desplegable de selección construido sobre el `Listbox` de Headless UI.
 *
 * El control sustituye al selector nativo del navegador, que queda prohibido
 * por la especificación del panel, y aporta navegación por teclado, búsqueda
 * opcional y opciones deshabilitadas (véase D13).
 */
export function CustomSelect({
    label,
    value,
    options,
    onChange,
    searchable = false,
    placeholder = 'Sin selección',
    disabled = false,
    testId,
}: CustomSelectProps) {
    const [query, setQuery] = useState('');

    const visibleOptions = useMemo(() => {
        if (!searchable || query.trim() === '') {
            return options;
        }

        const needle = normalize(query);

        return options.filter((option) => normalize(option.label).includes(needle));
    }, [options, query, searchable]);

    const selected = options.find((option) => option.value === value);

    /**
     * Evita que el espacio y el escape llegues al gestor de teclado del
     * `Listbox`, que los usa para la búsqueda incremental de opciones.
     */
    const handleSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === ' ' || event.key === 'Escape') {
            event.stopPropagation();
        }

        if (event.key === 'Escape') {
            setQuery('');
        }
    };

    return (
        <div className="relative flex flex-col gap-1" data-component="custom-select" data-testid={testId}>
            <Listbox value={value} onChange={onChange} disabled={disabled}>
                <Label className="text-label-12 uppercase tracking-wide text-text-low">{label}</Label>

                <ListboxButton
                    className="flex w-full items-center justify-between gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-left text-body-14 text-text-high focus:border-primary focus:shadow-focus focus:outline-none data-open:border-primary"
                    data-testid={testId ? `${testId}-button` : undefined}
                >
                    <span className={selected ? 'truncate' : 'truncate text-text-low'}>
                        {selected?.label ?? placeholder}
                    </span>
                    <span aria-hidden="true" className="text-label-12 text-text-low">
                        ▾
                    </span>
                </ListboxButton>

                <ListboxOptions className="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-line bg-surface py-1 shadow-overlay focus:outline-none">
                    {searchable && (
                        <div className="px-2 py-1">
                            <input
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={handleSearchKeyDown}
                                placeholder={`Buscar ${label.toLocaleLowerCase('es')}`}
                                aria-label={`Buscar ${label.toLocaleLowerCase('es')}`}
                                className="w-full rounded-md border border-line bg-surface px-2 py-1 text-body-14 text-text-high focus:border-primary focus:shadow-focus focus:outline-none"
                                data-testid={testId ? `${testId}-search` : undefined}
                            />
                        </div>
                    )}

                    {visibleOptions.length === 0 && (
                        <p role="presentation" className="px-3 py-2 text-body-13 text-text-medium">
                            Sin resultados
                        </p>
                    )}

                    {visibleOptions.map((option) => (
                        <ListboxOption
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                            className="cursor-pointer px-3 py-2 text-body-14 text-text-medium data-focus:bg-primary-soft data-selected:font-semibold data-disabled:cursor-not-allowed data-disabled:text-text-low"
                        >
                            {option.label}
                        </ListboxOption>
                    ))}
                </ListboxOptions>
            </Listbox>
        </div>
    );
}
