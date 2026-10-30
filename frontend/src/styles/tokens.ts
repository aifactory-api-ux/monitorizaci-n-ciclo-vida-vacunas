/**
 * Tokens de diseño del contrato UI/UX aprobado.
 *
 * Este módulo es la única fuente de verdad de la paleta, la tipografía, la
 * escala de espaciado, los radios, las sombras, la iconografía y el movimiento
 * del panel. Los valores reproducen literalmente el contrato aprobado; la
 * traducción al tema de Tailwind se hace en `frontend/src/styles/theme.ts`.
 */

/** Estilo tipográfico de un nivel de la escala de tipos. */
export interface TypeScaleStyle {
    /** Tamaño de la fuente. */
    size: string;
    /** Grosor de la fuente. */
    weight: string;
    /** Interlineado. */
    line_height: string;
}

/** Paleta aprobada para la interfaz clínica. */
export interface ColorTokens {
    primary: string;
    primary_hover: string;
    primary_soft: string;
    accent: string;
    navy: string;
    text_high: string;
    text_medium: string;
    text_low: string;
    line: string;
    surface: string;
    canvas: string;
    canvas_alt: string;
    success: string;
    success_soft: string;
    warning: string;
    warning_soft: string;
    danger: string;
    danger_soft: string;
    info: string;
    info_soft: string;
    overlay: string;
}

/** Familias tipográficas y escala de tipos aprobadas. */
export interface TypographyTokens {
    family_ui: string;
    family_display: string;
    family_numeric: string;
    scale: {
        display_28: TypeScaleStyle;
        title_20: TypeScaleStyle;
        heading_16: TypeScaleStyle;
        body_14: TypeScaleStyle;
        body_13: TypeScaleStyle;
        label_12: TypeScaleStyle;
        metric_24: TypeScaleStyle;
    };
}

/** Escala de espaciado aprobada, en pasos de 4 px. */
export interface SpacingTokens {
    '1': string;
    '2': string;
    '3': string;
    '4': string;
    '5': string;
    '6': string;
    '7': string;
    '8': string;
    '9': string;
}

/** Radios de esquina aprobados. */
export interface RadiusTokens {
    sm: string;
    md: string;
    lg: string;
    xl: string;
    pill: string;
}

/** Sombras aprobadas. */
export interface ShadowTokens {
    card: string;
    overlay: string;
    focus: string;
}

/** Criterio de iconografía aprobado. */
export interface IconographyTokens {
    style: string;
    usage: string;
}

/** Criterio de movimiento aprobado. */
export interface MotionTokens {
    standard: string;
    card_enter: string;
    live_update: string;
    reduced: string;
}

/** Conjunto completo de tokens del contrato UI/UX aprobado. */
export interface DesignTokens {
    colors: ColorTokens;
    typography: TypographyTokens;
    spacing: SpacingTokens;
    radius: RadiusTokens;
    shadows: ShadowTokens;
    iconography: IconographyTokens;
    motion: MotionTokens;
}

/**
 * Tokens del contrato UI/UX aprobado, con los valores exactos definidos en el
 * diseño de la interfaz de monitorización del ciclo de vida de vacunas.
 */
export const tokens: DesignTokens = {
    colors: {
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
    },
    typography: {
        family_ui: 'Inter',
        family_display: 'Inter Tight',
        family_numeric: "Inter, font-feature-settings: 'tnum'",
        scale: {
            display_28: { size: '28px', weight: '600', line_height: '32px' },
            title_20: { size: '20px', weight: '600', line_height: '26px' },
            heading_16: { size: '16px', weight: '600', line_height: '22px' },
            body_14: { size: '14px', weight: '400', line_height: '20px' },
            body_13: { size: '13px', weight: '400', line_height: '18px' },
            label_12: { size: '12px', weight: '500', line_height: '16px' },
            metric_24: { size: '24px', weight: '600', line_height: '28px' },
        },
    },
    spacing: {
        '1': '4px',
        '2': '8px',
        '3': '12px',
        '4': '16px',
        '5': '20px',
        '6': '24px',
        '7': '32px',
        '8': '40px',
        '9': '48px',
    },
    radius: {
        sm: '6px',
        md: '10px',
        lg: '14px',
        xl: '20px',
        pill: '999px',
    },
    shadows: {
        card: '0 1px 2px rgba(14,42,58,0.06), 0 8px 24px rgba(14,42,58,0.06)',
        overlay: '0 12px 32px rgba(14,42,58,0.18)',
        focus: '0 0 0 3px rgba(0,166,166,0.25)',
    },
    iconography: {
        style: 'Iconos lineales de 20px, trazo de 1.5px, terminales redondeados',
        usage: 'Navegación, fases de manufactura, alertas, LIMS, auditoría y exportación',
    },
    motion: {
        standard: '180ms cubic-bezier(0.2,0,0,1)',
        card_enter: 'Framer Motion fade + 8px rise, 250ms, stagger 40ms',
        live_update: 'Pulso de anillo de 1.6s en celdas que cambian por WebSocket',
        reduced: 'Respetar prefers-reduced-motion desactivando movimientos no esenciales',
    },
};
