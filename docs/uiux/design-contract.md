# UI/UX Design Contract

> **READ THIS FILE BEFORE IMPLEMENTING ANY FRONTEND COMPONENT.**
> This is the single source of truth for all visual and functional requirements.

## Figma Source

File URL: https://www.figma.com/design/bQUd0Puq7ZEbbd4GjXcLCB

## Visual Direction

Interfaz clínica, moderna y limpia para una torre de control de trazabilidad farmacéutica. Predominan superficies claras, acentos teal corporativos y una jerarquía densa pero escaneable, orientada a operadores de planta. El diseño usa tipografía Inter con números tabulares para datos de producción, badges semánticos por estado, paneles de alta densidad y una paleta de alerta sobria que prioriza desviaciones térmicas y eventos LIMS sin generar ruido visual.

## Pages / Figma Frames

### 1. Dashboard Principal
- **purpose**: Reacomodar el Dashboard Principal para eliminar la percepción de elementos colapsados, con mejor densidad, espaciado y jerarquía visual manteniendo KPIs, filtros, alertas y tabla de lotes activos.
- **sections**: ['Barra de navegación lateral colapsable y topbar global', 'Panel de KPIs de producción', 'Barra de filtros por especie, fase y estado', 'Banner de alertas prioritarias', 'Tabla de lotes activos con badges de estado', 'Indicador de sincronización en tiempo real']
- **node_id**: 9:2
- **screenshot_object_name**: 13fffe27-278e-4b63-bbb4-d3bd1b3448db/UIUX/1793348401720-Dashboard_Principal-default.png

### 2. Detalle del Lote
- **purpose**: Frame de trazabilidad individual del lote con fases, LIMS y auditoría.
- **sections**: ['Cabecera de lote con producto y tecnología', 'Stepper horizontal de fases entre los tres campus', 'Indicadores de fechas y dosis objetivo', 'Panel LIMS de solo lectura', 'Línea de tiempo de auditoría', 'Panel de alertas y desviaciones del lote']
- **node_id**: 6:2
- **screenshot_object_name**: 13fffe27-278e-4b63-bbb4-d3bd1b3448db/UIUX/1793348402153-Detalle_del_Lote-default.png

## Design Tokens

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

## Base Components

### `SidebarNav`

Props:
```json
[
  "items: NavItem[]",
  "collapsed?: boolean",
  "activePath: string",
  "onToggle?: () => void"
]
```

### `Topbar`

Props:
```json
[
  "user: UserProfile",
  "notifications: AlertNotification[]",
  "onToggleSidebar?: () => void"
]
```

### `Button`

Props:
```json
[
  "label: string",
  "variant?: 'primary' | 'secondary' | 'ghost' | 'danger'",
  "icon?: ReactNode",
  "loading?: boolean",
  "disabled?: boolean"
]
```

### `KpiCard`

Props:
```json
[
  "label: string",
  "value: string | number",
  "delta?: string",
  "tone?: 'positive' | 'warning' | 'critical' | 'neutral'",
  "loading?: boolean"
]
```

### `CustomSelect`

Props:
```json
[
  "value: Option | null",
  "options: Option[]",
  "onChange: (option: Option | null) => void",
  "placeholder?: string",
  "disabled?: boolean"
]
```

### `StatusBadge`

Props:
```json
[
  "status: 'ON_TRACK' | 'DELAYED' | 'ON_HOLD' | 'REJECTED'"
]
```

### `DataGrid`

Props:
```json
[
  "columns: Column[]",
  "rows: VaccineBatchRow[]",
  "selectedId?: string",
  "onRowSelect: (id: string) => void",
  "pagination?: PaginationState",
  "loading?: boolean"
]
```

### `PhaseStepper`

Props:
```json
[
  "phases: PhaseStep[]",
  "currentPhase: VaccinePhase",
  "orientation?: 'horizontal' | 'vertical'"
]
```

### `LimsResultCard`

Props:
```json
[
  "results: QualityControlResult[]",
  "isLoading?: boolean",
  "batchId: string"
]
```

### `AlertBanner`

Props:
```json
[
  "alert: DomainAlert",
  "severity: 'critical' | 'warning' | 'info'",
  "onDismiss?: () => void"
]
```

### `AuditLogItem`

Props:
```json
[
  "entry: PhaseLogEntry",
  "showOperator?: boolean"
]
```


## Preliminary Spec

```json
{
  "site_goal": "Proporcionar trazabilidad de extremo a extremo y alertas tempranas en el ciclo de vida de lotes de vacunas para resolver la fragmentaci\u00f3n operativa entre tres campus y reducir el backlog t\u00e9cnico de IT.",
  "audience": "Operador de Producci\u00f3n, T\u00e9cnico de Control de Calidad (QA/QC), Coordinador de Log\u00edstica, Supervisor de Planta, Auditor/Administrador IT",
  "visual_references": [],
  "constraints": [],
  "sections_or_pages": [
    "Dashboard Principal",
    "Detalle del Lote"
  ],
  "confirmed_assumptions": [],
  "open_questions": [],
  "gaps_to_resolve": [],
  "item_attempts": {
    "audiencia": 1,
    "tono": 1,
    "pantallas": 1
  },
  "last_asked": null,
  "last_asked_options": null,
  "confirmed_items": [
    "audiencia",
    "pantallas",
    "tono"
  ],
  "assumed_items": [],
  "ready_for_phase_2": true,
  "status": "complete",
  "brand_tone": "Moderno y limpio"
}
```

## Last Refinement Notes

```json
{
  "figma_status": "applied",
  "user_request": "En el dashboard principal los elementos parece colapsados",
  "structural": {
    "action": "rebuild_page",
    "page_name": "Dashboard Principal",
    "new_page_name": "",
    "purpose": "Reacomodar el Dashboard Principal para eliminar la percepci\u00f3n de elementos colapsados, con mejor densidad, espaciado y jerarqu\u00eda visual manteniendo KPIs, filtros, alertas y tabla de lotes activos.",
    "sections": [
      "Barra de navegaci\u00f3n lateral colapsable y topbar global",
      "Panel de KPIs de producci\u00f3n",
      "Barra de filtros por especie, fase y estado",
      "Banner de alertas prioritarias",
      "Tabla de lotes activos con badges de estado",
      "Indicador de sincronizaci\u00f3n en tiempo real"
    ],
    "build_page_name": "Dashboard Principal"
  },
  "patch_operations": []
}
```

## Figma Design Context (layout, spacing, component tree)

```json
{
  "success": true,
  "result": {
    "success": true,
    "action": "get_design_context",
    "auth_required": false,
    "message": "Design context retrieved successfully for nodes 9:2 and 6:2.",
    "figma": {
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB",
      "node_ids": [
        "9:2",
        "6:2"
      ]
    }
  },
  "auth_required": false,
  "provider": "codex",
  "return_code": 0,
  "model": "gpt-5.6-luna",
  "figma_tool_events": [
    {
      "tool": "create_session",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "create_session",
      "status": "completed",
      "error": null,
      "text": "{\"sessionId\":\"3e1f21aa-6ad9-4443-9263-3e04da752b79\"}\ne7381d31-2a40-4640-8fde-4d3e89c49bb4\n3e1f21aa-6ad9-4443-9263-3e04da752b79",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_design_context",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_design_context",
      "status": "failed",
      "error": null,
      "text": "Input validation error: Invalid arguments for tool get_design_context: nodeId: Node ID must be in the format \"123:456\", \"123-456\", \"I123:456;789:012\", or \"T123:456;789:012;345:678\"\n",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_design_context",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_design_context",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_design_context",
      "status": "completed",
      "error": null,
      "text": "export default function DetalleDelLote() {\n  return (\n    <div className=\"bg-[#f4f7fa] content-stretch flex flex-col gap-[24px] items-start pb-[40px] pt-[32px] px-[40px] relative size-full\" data-node-id=\"6:2\" data-name=\"Detalle del Lote\">\n      <div className=\"bg-white border border-[#d9e2ec] border-solid content-stretch flex gap-[24px] h-[56px] items-start overflow-clip px-[18px] py-[12px] relative rounded-[12px] shrink-0 w-full\" data-node-id=\"6:3\" data-name=\"Global Navigation\">\n        <div className=\"bg-white content-stretch flex gap-[10px] items-start overflow-clip relative shrink-0\" data-node-id=\"6:4\" data-name=\"Brand\">\n          <div className=\"bg-[#007a73] relative rounded-[8px] shrink-0 size-[32px]\" data-node-id=\"6:5\" data-name=\"MSD Mark\" />\n          <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[21px] not-italic relative shrink-0 text-[#0e2a3a] text-[15px] whitespace-nowrap\" data-node-id=\"6:6\">\n            Monitorizaci\u00f3n Ciclo Vida Vacunas\n          </p>\n        </div>\n        <div className=\"bg-white flex-[1_0_0] h-[100px] min-w-px relative\" data-node-id=\"6:7\" data-name=\"Nav Spacer\" />\n        <p className=\"[word-break:break-word] font-['Inter:Regular'] font-normal leading-[18px] not-italic relative shrink-0 text-[#486581] text-[13px] whitespace-nowrap\" data-node-id=\"6:8\">\n          Dashboard Principal\n        </p>\n        <div className=\"bg-[#d9e2ec] h-[24px] relative shrink-0 w-px\" data-node-id=\"6:9\" data-name=\"Divider\" />\n        <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[18px] not-italic relative shrink-0 text-[#007a73] text-[13px] whitespace-nowrap\" data-node-id=\"6:10\">\n          Detalle del Lote\n        </p>\n        <div className=\"bg-[#e6f2f1] content-stretch flex items-start overflow-clip relative rounded-[999px] shrink-0 size-[32px]\" data-node-id=\"6:11\" data-name=\"User Avatar\">\n          <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[17px] not-italic relative shrink-0 text-[#007a73] text-[12px] whitespace-nowrap\" data-node-id=\"6:12\">\n            MG\n          </p>\n        </div>\n      </div>\n      <div className=\"bg-white border border-[#d9e2ec] border-solid content-stretch flex gap-[24px] items-start overflow-clip px-[24px] py-[22px] relative rounded-[14px] shrink-0 w-full\" data-node-id=\"6:13\" data-name=\"Batch Header\">\n        <div className=\"bg-white content-stretch flex flex-[1_0_0] flex-col gap-[6px] items-start min-w-px overflow-clip relative\" data-node-id=\"6:14\" data-name=\"Batch Identity\">\n          <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[39px] not-italic relative shrink-0 text-[#0e2a3a] text-[28px] whitespace-nowrap\" data-node-id=\"6:15\">\n            Detalle del Lote\n          </p>\n          <p className=\"[word-break:break-word] font-['Inter:Regular'] font-normal leading-[20px] not-italic relative shrink-0 text-[#486581] text-[14px] whitespace-nowrap\" data-node-id=\"6:16\">\n            Trazabilidad end-to-end \u00b7 Campus 1 \u2192 Sphereon \u2192 Campus 3\n          </p>\n          <div className=\"bg-white content-stretch flex gap-[10px] items-start overflow-clip relative shrink-0\" data-node-id=\"6:17\" data-name=\"Batch Metadata\">\n            <div className=\"bg-[#e6f2f1] content-stretch flex items-start overflow-clip px-[9px] py-[5px] relative rounded-[999px] shrink-0\" data-node-id=\"6:18\" data-name=\"VAC-2026-0148\">\n              <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[17px] not-italic relative shrink-0 text-[#007a73] text-[12px] whitespace-nowrap\" data-node-id=\"6:19\">\n                VAC-2026-0148\n              </p>\n            </div>\n            <p className=\"[word-break:break-word] font-['Inter:Regular'] font-normal leading-[18px] not-italic relative shrink-0 text-[#486581] text-[13px] whitespace-nowrap\" data-node-id=\"6:20\">\n              Vacuna recombinante \u00b7 Tecnolog\u00eda Sphereon\n            </p>\n          </div>\n        </div>\n        <div className=\"bg-white content-stretch flex flex-col gap-[8px] items-start overflow-clip relative shrink-0\" data-node-id=\"6:21\" data-name=\"Batch Status\">\n          <div className=\"bg-[#e3f5ec] content-stretch flex items-start overflow-clip px-[9px] py-[5px] relative rounded-[999px] shrink-0\" data-node-id=\"6:22\" data-name=\"ON_TRACK\">\n            <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[17px] not-italic relative shrink-0 text-[#1b8a5a] text-[12px] whitespace-nowrap\" data-node-id=\"6:23\">\n              ON_TRACK\n            </p>\n          </div>\n          <p className=\"[word-break:break-word] font-['Inter:Regular'] font-normal leading-[17px] not-italic relative shrink-0 text-[#829ab1] text-[12px] whitespace-nowrap\" data-node-id=\"6:24\">\n            \u00daltima sincronizaci\u00f3n \u00b7 hace 18 s\n          </p>\n        </div>\n      </div>\n      <div className=\"bg-white border border-[#d9e2ec] border-solid content-stretch flex flex-col gap-[14px] items-start overflow-clip px-[22px] py-[18px] relative rounded-[14px] shrink-0 w-full\" data-node-id=\"6:25\" data-name=\"Phase Stepper\">\n        <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[22px] not-italic relative shrink-0 text-[#0e2a3a] text-[16px] whitespace-nowrap\" data-node-id=\"6:26\">\n          Fases del lote\n        </p>\n        <div className=\"bg-white content-stretch flex items-start overflow-clip relative shrink-0 w-full\" data-node-id=\"6:27\" data-name=\"Stepper Row\">\n          <div className=\"bg-white content-stretch flex flex-[1_0_0] gap-[12px] items-start min-w-px overflow-clip relative\" data-node-id=\"6:28\" data-name=\"Phase 01\">\n            <div className=\"bg-[#e3f5ec] content-stretch flex items-start overflow-clip relative rounded-[999px] shrink-0 size-[34px]\" data-node-id=\"6:29\" data-name=\"Step 01\">\n              <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[17px] not-italic relative shrink-0 text-[#1b8a5a] text-[12px] whitespace-nowrap\" data-node-id=\"6:30\">\n                01\n              </p>\n            </div>\n            <div className=\"[word-break:break-word] bg-white content-stretch flex flex-[1_0_0] flex-col gap-[2px] items-start min-w-px not-italic overflow-clip relative whitespace-nowrap\" data-node-id=\"6:31\" data-name=\"Phase Copy\">\n              <p className=\"font-['Inter:Semi_Bold'] font-semibold leading-[18px] relative shrink-0 text-[#102a43] text-[13px]\" data-node-id=\"6:32\">\n                Producci\u00f3n primaria\n              </p>\n              <p className=\"font-['Inter:Regular'] font-normal leading-[17px] relative shrink-0 text-[#1b8a5a] text-[12px]\" data-node-id=\"6:33\">\n                Campus 1 \u00b7 Completada\n              </p>\n            </div>\n          </div>\n          <div className=\"bg-[#1b8a5a] h-[2px] relative shrink-0 w-[34px]\" data-node-id=\"6:34\" data-name=\"Phase Connector\" />\n          <div className=\"bg-white content-stretch flex flex-[1_0_0] gap-[12px] items-start min-w-px overflow-clip relative\" data-node-id=\"6:35\" data-name=\"Phase 02\">\n            <div className=\"bg-[#e3f5ec] content-stretch flex items-start overflow-clip relative rounded-[999px] shrink-0 size-[34px]\" data-node-id=\"6:36\" data-name=\"Step 02\">\n              <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[17px] not-italic relative shrink-0 text-[#1b8a5a] text-[12px] whitespace-nowrap\" data-node-id=\"6:37\">\n                02\n              </p>\n            </div>\n            <div className=\"[word-break:break-word] bg-white content-stretch flex flex-[1_0_0] flex-col gap-[2px] items-start min-w-px not-italic overflow-clip relative whitespace-nowrap\" data-node-id=\"6:38\" data-name=\"Phase Copy\">\n              <p className=\"font-['Inter:Semi_Bold'] font-semibold leading-[18px] relative shrink-0 text-[#102a43] text-[13px]\" data-node-id=\"6:39\">\n                Control microbiol\u00f3gico\n              </p>\n              <p className=\"font-['Inter:Regular'] font-normal leading-[17px] relative shrink-0 text-[#1b8a5a] text-[12px]\" data-node-id=\"6:40\">\n                LIMS Labware \u00b7 Completada\n              </p>\n            </div>\n          </div>\n          <div className=\"bg-[#1b8a5a] h-[2px] relative shrink-0 w-[34px]\" data-node-id=\"6:41\" data-name=\"Phase Connector\" />\n          <div className=\"bg-white content-stretch flex flex-[1_0_0] gap-[12px] items-start min-w-px overflow-clip relative\" data-node-id=\"6:42\" data-name=\"Phase 03\">\n            <div className=\"bg-[#e6f2f1] content-stretch flex items-start overflow-clip relative rounded-[999px] shrink-0 size-[34px]\" data-node-id=\"6:43\" data-name=\"Step 03\">\n              <p className=\"[word-break:break-word] font-['Inter:Semi_Bold'] font-semibold leading-[17px] not-italic relative shrink-0 text-[#007a73] text-[12px] whitespace-nowrap\" data-node-id=\"6:44\">\n                03\n              </p>\n            </div>\n            <div className=\"[word-break:break-word] bg-white content-stretch flex flex-[1_0_0] flex-col gap-[2px] items-start min-w-px not-italic overflow-clip relative whitespace-nowrap\" data-node-id=\"6:45\" data-name=\"Phase Copy\">\n              <p className=\"font-['Inter:Semi_Bold'] font-semibold leading-[18px] relative shrink-0 text-[#102a43] text-[13px]\" data-node-id=\"6:46\">\n                Formulaci\u00f3n Sphereon\n              </p>\n              <p className=\"font-['Inter:Regular'] font-normal leading-[17px] relative shrink-0 text-[#007a73] text-[12px]\" data-node-id=\"6:47\">\n                Campus 2 \u00b7 En curso\n              </p>\n            </div>\n          </div>\n          <div className=\"bg-[#d9e2ec] h-[2px] relative shrink-0 w-[34px]\" data-node-id=\"6:48\" data-name=\"Phase Connector\" />\n
```

## Figma Variable Definitions (token names + values)

```json
{
  "success": true,
  "result": {
    "success": true,
    "action": "get_variable_defs",
    "auth_required": false,
    "message": "Variable definitions retrieved for nodes 9:2 and 6:2.",
    "figma": {
      "9:2": {},
      "6:2": {}
    }
  },
  "auth_required": false,
  "provider": "codex",
  "return_code": 0,
  "model": "gpt-5.6-luna",
  "figma_tool_events": [
    {
      "tool": "create_session",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "create_session",
      "status": "completed",
      "error": null,
      "text": "{\"sessionId\":\"6890bbbe-b6f0-4583-90a7-fcb407ca9979\"}\naf84a062-a621-43ec-9573-2313f9928735\n6890bbbe-b6f0-4583-90a7-fcb407ca9979",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_variable_defs",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_variable_defs",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_variable_defs",
      "status": "completed",
      "error": null,
      "text": "{}\nda5e25f9-e56c-4d56-aac0-893ded44b3a3\n",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_variable_defs",
      "status": "completed",
      "error": null,
      "text": "{}\nce389ed2-a67b-4891-bb12-2a7a447eb325\n",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    }
  ]
}
```

## Code Connect Map (Figma component → code file)

```json
{
  "success": true,
  "result": {
    "success": true,
    "action": "get_code_connect_map",
    "auth_required": false,
    "message": "Code Connect lookup completed; no mappings were found for the specified nodes.",
    "figma": {
      "9:2": {},
      "6:2": {}
    }
  },
  "auth_required": false,
  "provider": "codex",
  "return_code": 0,
  "model": "gpt-5.6-luna",
  "figma_tool_events": [
    {
      "tool": "create_session",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "create_session",
      "status": "completed",
      "error": null,
      "text": "{\"sessionId\":\"618b14ca-ffc3-4478-b9db-c1663ca683c6\"}\na56ae04d-bf38-4dd0-8db3-58606372e757\n618b14ca-ffc3-4478-b9db-c1663ca683c6",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_code_connect_map",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_code_connect_map",
      "status": "in_progress",
      "error": null,
      "text": "",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_code_connect_map",
      "status": "completed",
      "error": null,
      "text": "{}\n514cbda6-9894-451c-bb0a-5245611f80a0\n",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    },
    {
      "tool": "get_code_connect_map",
      "status": "completed",
      "error": null,
      "text": "{}\n082e2a67-568d-4265-81ce-a8df7dc63659\n",
      "file_key": "bQUd0Puq7ZEbbd4GjXcLCB"
    }
  ]
}
```

## Screenshots (workspace-relative paths)

- **Dashboard Principal**: `design/screenshots/Dashboard_Principal.png`
- **Detalle del Lote**: `design/screenshots/Detalle_del_Lote.png`
