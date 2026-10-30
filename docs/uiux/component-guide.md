# Component Guide

Quick reference for components and design tokens. Use exact names — do not rename or create synonyms.

## Token Quick Reference

```json
{
  "colors": {
    "primary": "#007A73",
    "primary_hover": "#00645E",
    "primary_soft": "#E6F2F1",
    "accent": "#00A6A6",
    "navy": "#0E2A3A",
    "text_high": "#102A43",
    "text_medium": "#486581",
    "text_low": "#829AB1",
    "line": "#D9E2EC",
    "surface": "#FFFFFF",
    "canvas": "#F4F7FA",
    "canvas_alt": "#EDF2F7",
    "success": "#1B8A5A",
    "success_soft": "#E3F5EC",
    "warning": "#B7791F",
    "warning_soft": "#FCF0D8",
    "danger": "#C0392B",
    "danger_soft": "#FBEAE8",
    "info": "#1D6FA5",
    "info_soft": "#E4F1F9",
    "overlay": "rgba(14,42,58,0.42)"
  },
  "typography": {
    "family_ui": "Inter",
    "family_display": "Inter Tight",
    "family_numeric": "Inter, font-feature-settings: 'tnum'",
    "scale": {
      "display_28": {
        "size": "28px",
        "weight": "600",
        "line_height": "32px"
      },
      "title_20": {
        "size": "20px",
        "weight": "600",
        "line_height": "26px"
      },
      "heading_16": {
        "size": "16px",
        "weight": "600",
        "line_height": "22px"
      },
      "body_14": {
        "size": "14px",
        "weight": "400",
        "line_height": "20px"
      },
      "body_13": {
        "size": "13px",
        "weight": "400",
        "line_height": "18px"
      },
      "label_12": {
        "size": "12px",
        "weight": "500",
        "line_height": "16px"
      },
      "metric_24": {
        "size": "24px",
        "weight": "600",
        "line_height": "28px"
      }
    }
  },
  "spacing": {
    "1": "4px",
    "2": "8px",
    "3": "12px",
    "4": "16px",
    "5": "20px",
    "6": "24px",
    "7": "32px",
    "8": "40px",
    "9": "48px"
  },
  "radius": {
    "sm": "6px",
    "md": "10px",
    "lg": "14px",
    "xl": "20px",
    "pill": "999px"
  },
  "shadows": {
    "card": "0 1px 2px rgba(14,42,58,0.06), 0 8px 24px rgba(14,42,58,0.06)",
    "overlay": "0 12px 32px rgba(14,42,58,0.18)",
    "focus": "0 0 0 3px rgba(0,166,166,0.25)"
  },
  "iconography": {
    "style": "Iconos lineales de 20px, trazo de 1.5px, terminales redondeados",
    "usage": "Navegaci\u00f3n, fases de manufactura, alertas, LIMS, auditor\u00eda y exportaci\u00f3n"
  },
  "motion": {
    "standard": "180ms cubic-bezier(0.2,0,0,1)",
    "card_enter": "Framer Motion fade + 8px rise, 250ms, stagger 40ms",
    "live_update": "Pulso de anillo de 1.6s en celdas que cambian por WebSocket",
    "reduced": "Respetar prefers-reduced-motion desactivando movimientos no esenciales"
  }
}
```

## Available Components

- **SidebarNav**: 
- **Topbar**: 
- **Button**: 
- **KpiCard**: 
- **CustomSelect**: 
- **StatusBadge**: 
- **DataGrid**: 
- **PhaseStepper**: 
- **LimsResultCard**: 
- **AlertBanner**: 
- **AuditLogItem**: 
