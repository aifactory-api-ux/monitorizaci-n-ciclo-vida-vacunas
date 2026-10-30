import { tokens, type DesignTokens } from './tokens';

/**
 * Traducción de los tokens del contrato UI/UX al tema de Tailwind.
 *
 * El bloque `@theme` que se genera es el que da nombre a las utilidades del
 * panel (`bg-primary`, `text-text-high`, `rounded-lg`, `shadow-card`,
 * `text-body-14`, `gap-7`…), de modo que la interfaz se construye siempre con
 * los valores aprobados y no con una paleta suelta.
 */

/** Alternativas de sistema que acompañan a las familias tipográficas aprobadas. */
const SYSTEM_FONT_FALLBACK =
    "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

/**
 * Convierte el nombre de un token del contrato en kebab-case, que es la
 * convención de las variables CSS y de las utilidades de Tailwind.
 */
export function toKebabCase(name: string): string {
    return name.replace(/_/g, '-');
}

/**
 * Extrae la familia tipográfica de un token que puede declarar ajustes de
 * fuente adicionales.
 */
function fontFamilyOf(value: string): string {
    const [family] = value.split(', font-feature-settings:');

    return family.trim();
}

/**
 * Extrae los ajustes de característica de fuente declarados en un token, o
 * `null` cuando el token no los declara.
 */
function fontFeatureSettingsOf(value: string): string | null {
    const [, settings] = value.split(', font-feature-settings:');

    return settings === undefined ? null : settings.trim();
}

/**
 * Divide el token de movimiento estándar en duración y curva de aceleración.
 */
function splitMotionStandard(value: string): { duration: string; timingFunction: string } {
    const [duration, ...rest] = value.trim().split(/\s+/);

    return {
        duration,
        timingFunction: rest.join(' '),
    };
}

/**
 * Construye el CSS del tema de Tailwind a partir de los tokens aprobados.
 *
 * @param source Tokens de origen; por defecto, el contrato UI/UX aprobado.
 */
export function buildThemeCss(source: DesignTokens = tokens): string {
    const lines: string[] = ['@theme {'];

    lines.push('    /* Colores */');
    for (const [name, value] of Object.entries(source.colors)) {
        lines.push(`    --color-${toKebabCase(name)}: ${value};`);
    }

    lines.push('');
    lines.push('    /* Familias tipográficas */');
    lines.push(
        `    --font-sans: ${fontFamilyOf(source.typography.family_ui)}, ${SYSTEM_FONT_FALLBACK};`,
    );
    lines.push(
        `    --font-display: ${fontFamilyOf(source.typography.family_display)}, ${fontFamilyOf(source.typography.family_ui)}, ${SYSTEM_FONT_FALLBACK};`,
    );
    lines.push(
        `    --font-numeric: ${fontFamilyOf(source.typography.family_numeric)}, ${SYSTEM_FONT_FALLBACK};`,
    );

    lines.push('');
    lines.push('    /* Escala de tipos */');
    for (const [name, style] of Object.entries(source.typography.scale)) {
        const key = toKebabCase(name);

        lines.push(`    --text-${key}: ${style.size};`);
        lines.push(`    --text-${key}--line-height: ${style.line_height};`);
        lines.push(`    --text-${key}--font-weight: ${style.weight};`);
    }

    lines.push('');
    lines.push('    /* Escala de espaciado */');
    for (const [step, value] of Object.entries(source.spacing)) {
        lines.push(`    --spacing-${step}: ${value};`);
    }

    lines.push('');
    lines.push('    /* Radios */');
    for (const [name, value] of Object.entries(source.radius)) {
        lines.push(`    --radius-${name}: ${value};`);
    }

    lines.push('');
    lines.push('    /* Sombras */');
    for (const [name, value] of Object.entries(source.shadows)) {
        lines.push(`    --shadow-${name}: ${value};`);
    }

    const motion = splitMotionStandard(source.motion.standard);

    lines.push('');
    lines.push('    /* Movimiento */');
    lines.push(`    --default-transition-duration: ${motion.duration};`);
    lines.push(`    --default-transition-timing-function: ${motion.timingFunction};`);

    lines.push('}');

    const numericFeatures = fontFeatureSettingsOf(source.typography.family_numeric);

    lines.push('');
    lines.push('@utility numeric {');
    lines.push('    font-family: var(--font-numeric);');
    if (numericFeatures !== null) {
        lines.push(`    font-feature-settings: ${numericFeatures};`);
    }
    lines.push('}');

    return `${lines.join('\n')}\n`;
}
