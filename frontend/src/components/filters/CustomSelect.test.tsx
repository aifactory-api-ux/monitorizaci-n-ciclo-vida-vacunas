import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { BatchFilters } from '@/components/filters/BatchFilters';
import { CustomSelect, type CustomSelectOption } from '@/components/filters/CustomSelect';

const options: CustomSelectOption[] = [
    { value: '', label: 'Todas las fases' },
    { value: 'Fase de cultivo', label: 'Fase de cultivo' },
    { value: 'Formulación Sphereon (Campus 2)', label: 'Formulación Sphereon (Campus 2)' },
    { value: 'Fase logística (Campus 3)', label: 'Fase logística (Campus 3)', disabled: true },
];

function renderSelect(onChange: (value: string) => void, extra: Partial<{ searchable: boolean }> = {}) {
    return render(
        <CustomSelect
            label="Fase"
            value=""
            options={options}
            onChange={onChange}
            testId="fase"
            searchable={extra.searchable ?? false}
        />,
    );
}

describe('CustomSelect', () => {
    it('no renderiza ningún elemento select nativo', () => {
        const { container } = renderSelect(vi.fn());

        expect(container.querySelector('select')).toBeNull();
        expect(document.querySelector('select')).toBeNull();
    });

    it('propaga a la vista el valor literal de la opción elegida', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        renderSelect(onChange);

        await user.click(screen.getByTestId('fase-button'));
        await user.click(screen.getByRole('option', { name: 'Fase de cultivo' }));

        expect(onChange).toHaveBeenCalledWith('Fase de cultivo');
    });

    it('permite elegir una opción con el teclado', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        renderSelect(onChange);

        screen.getByTestId('fase-button').focus();

        await user.keyboard('{ArrowDown}');
        await user.keyboard('{ArrowDown}');
        await user.keyboard('{Enter}');

        expect(onChange).toHaveBeenCalledWith('Fase de cultivo');
    });

    it('no permite elegir una opción deshabilitada', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        renderSelect(onChange);

        await user.click(screen.getByTestId('fase-button'));

        const disabledOption = screen.getByRole('option', { name: 'Fase logística (Campus 3)' });

        expect(disabledOption).toHaveAttribute('aria-disabled', 'true');

        await user.click(disabledOption);

        expect(onChange).not.toHaveBeenCalled();
    });

    it('filtra las opciones con el campo de búsqueda', async () => {
        const user = userEvent.setup();

        renderSelect(vi.fn(), { searchable: true });

        await user.click(screen.getByTestId('fase-button'));
        await user.type(screen.getByTestId('fase-search'), 'Sphereon');

        expect(screen.getByRole('option', { name: 'Formulación Sphereon (Campus 2)' })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: 'Fase de cultivo' })).toBeNull();
    });
});

describe('BatchFilters', () => {
    it('renderiza los tres filtros como instancias de CustomSelect', () => {
        const { container } = render(
            <BatchFilters value={{ family: '', phase: '', alert_status: '' }} onChange={vi.fn()} />,
        );

        expect(container.querySelectorAll('[data-component="custom-select"]')).toHaveLength(3);
    });

    it('emite el criterio con el valor literal del filtro cambiado', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(<BatchFilters value={{ family: '', phase: '', alert_status: '' }} onChange={onChange} />);

        await user.click(screen.getByTestId('filter-phase-button'));
        await user.click(screen.getByRole('option', { name: 'Fase de cultivo' }));

        expect(onChange).toHaveBeenCalledWith({ family: '', phase: 'Fase de cultivo', alert_status: '' });
    });
});
