import { describe, expect, it } from 'vitest';

import { buildThemeCss } from '@/styles/theme';
import { tokens } from '@/styles/tokens';

describe('tokens del contrato UI/UX aprobado', () => {
    it('expone la paleta aprobada', () => {
        expect(tokens.colors).toEqual({
            primary: '#007A73',
            primary_hover: '#00645E',
            primary_soft: '#E6F2F1',
            accent: '#00A6A6',
            navy: '#0E2A3A',
            text_high: '#102A43',
            text_medium: '#486581',
            text_low: '#829AB1',
            line: '#D9E2EC',
            surface: '#FFFFFF',
            canvas: '#F4F7FA',
            canvas_alt: '#EDF2F7',
            success: '#1B8A5A',
            success_soft: '#E3F5EC',
            warning: '#B7791F',
            warning_soft: '#FCF0D8',
            danger: '#C0392B',
            danger_soft: '#FBEAE8',
            info: '#1D6FA5',
            info_soft: '#E4F1F9',
            overlay: 'rgba(14,42,58,0.42)',
        });
    });

    it('expone las familias y la escala tipográfica aprobadas', () => {
        expect(tokens.typography.family_ui).toBe('Inter');
        expect(tokens.typography.family_display).toBe('Inter Tight');
        expect(tokens.typography.family_numeric).toBe("Inter, font-feature-settings: 'tnum'");

        expect(tokens.typography.scale).toEqual({
            display_28: { size: '28px', weight: '600', line_height: '32px' },
            title_20: { size: '20px', weight: '600', line_height: '26px' },
            heading_16: { size: '16px', weight: '600', line_height: '22px' },
            body_14: { size: '14px', weight: '400', line_height: '20px' },
            body_13: { size: '13px', weight: '400', line_height: '18px' },
            label_12: { size: '12px', weight: '500', line_height: '16px' },
            metric_24: { size: '24px', weight: '600', line_height: '28px' },
        });
    });

    it('expone la escala de espaciado aprobada', () => {
        expect(tokens.spacing).toEqual({
            '1': '4px',
            '2': '8px',
            '3': '12px',
            '4': '16px',
            '5': '20px',
            '6': '24px',
            '7': '32px',
            '8': '40px',
            '9': '48px',
        });
    });

    it('expone los radios y las sombras aprobados', () => {
        expect(tokens.radius).toEqual({
            sm: '6px',
            md: '10px',
            lg: '14px',
            xl: '20px',
            pill: '999px',
        });

        expect(tokens.shadows).toEqual({
            card: '0 1px 2px rgba(14,42,58,0.06), 0 8px 24px rgba(14,42,58,0.06)',
            overlay: '0 12px 32px rgba(14,42,58,0.18)',
            focus: '0 0 0 3px rgba(0,166,166,0.25)',
        });
    });

    it('expone los criterios de iconografía y de movimiento aprobados', () => {
        expect(tokens.iconography).toEqual({
            style: 'Iconos lineales de 20px, trazo de 1.5px, terminales redondeados',
            usage: 'Navegación, fases de manufactura, alertas, LIMS, auditoría y exportación',
        });

        expect(tokens.motion).toEqual({
            standard: '180ms cubic-bezier(0.2,0,0,1)',
            card_enter: 'Framer Motion fade + 8px rise, 250ms, stagger 40ms',
            live_update: 'Pulso de anillo de 1.6s en celdas que cambian por WebSocket',
            reduced: 'Respetar prefers-reduced-motion desactivando movimientos no esenciales',
        });
    });
});

describe('tema de Tailwind generado a partir de los tokens', () => {
    const css = buildThemeCss();

    it('publica la paleta como colores del tema', () => {
        expect(css).toContain('--color-primary: #007A73;');
        expect(css).toContain('--color-primary-hover: #00645E;');
        expect(css).toContain('--color-text-high: #102A43;');
        expect(css).toContain('--color-canvas: #F4F7FA;');
    });

    it('publica la escala de tipos con su interlineado y su grosor', () => {
        expect(css).toContain('--text-display-28: 28px;');
        expect(css).toContain('--text-display-28--line-height: 32px;');
        expect(css).toContain('--text-display-28--font-weight: 600;');
        expect(css).toContain('--text-body-14: 14px;');
        expect(css).toContain('--text-body-14--line-height: 20px;');
        expect(css).toContain('--text-label-12--font-weight: 500;');
    });

    it('publica el espaciado, los radios y las sombras', () => {
        expect(css).toContain('--spacing-7: 32px;');
        expect(css).toContain('--spacing-9: 48px;');
        expect(css).toContain('--radius-lg: 14px;');
        expect(css).toContain('--shadow-card: 0 1px 2px rgba(14,42,58,0.06), 0 8px 24px rgba(14,42,58,0.06);');
    });

    it('publica el movimiento estándar y la tipografía numérica', () => {
        expect(css).toContain('--default-transition-duration: 180ms;');
        expect(css).toContain('--default-transition-timing-function: cubic-bezier(0.2,0,0,1);');
        expect(css).toContain('@utility numeric {');
        expect(css).toContain("font-feature-settings: 'tnum';");
    });
});
