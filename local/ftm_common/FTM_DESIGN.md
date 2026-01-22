# FTM Design System v1.0

**Creato**: 21/01/2026
**Riferimento**: `student_report.php`
**CSS**: `local/ftm_common/styles/ftm_design_system.css`

---

## Quick Start

### Attivare il Design (Test)
```
?design=new    → Nuovo design
?design=old    → Design originale
(default)      → Design originale
```

### Includere il CSS
```php
// In PHP
require_once($CFG->dirroot . '/local/ftm_common/classes/design_helper.php');
\local_ftm_common\design_helper::load_design($PAGE);

// Oppure manualmente
$PAGE->requires->css('/local/ftm_common/styles/ftm_design_system.css');
```

---

## Palette Colori

### Colori Principali

| Nome | Variabile | Hex | Uso |
|------|-----------|-----|-----|
| Arancione | `--ftm-orange` | `#F5A623` | Header, CTA primari |
| Teal | `--ftm-teal` | `#1A5A5A` | Card utente, accenti |

### Colori Accent

| Nome | Variabile | Hex | Uso |
|------|-----------|-----|-----|
| Giallo | `--ftm-yellow` | `#FFC107` | Warning, highlight |
| Verde | `--ftm-green` | `#28A745` | Success, conferme |
| Blu | `--ftm-blue` | `#17A2B8` | Info, link |
| Rosso | `--ftm-red` | `#DC3545` | Errori, danger |

### Grigi

| Nome | Variabile | Hex | Uso |
|------|-----------|-----|-----|
| Gray 100 | `--ftm-gray-100` | `#F8F9FA` | Background |
| Gray 200 | `--ftm-gray-200` | `#E9ECEF` | Bordi leggeri |
| Gray 600 | `--ftm-gray-600` | `#6C757D` | Testo secondario |
| Gray 900 | `--ftm-gray-900` | `#212529` | Testo principale |

---

## Componenti

### Header
```html
<div class="ftm-header">
    <h1 class="ftm-header-title">Titolo Pagina</h1>
    <p class="ftm-header-subtitle">Sottotitolo opzionale</p>
</div>
```

### User Card (Card Principale)
```html
<div class="ftm-user-card">
    <div class="ftm-user-card-left">
        <div class="ftm-user-avatar">CB</div>
        <div class="ftm-user-info">
            <h2>Nome Utente</h2>
            <p>email@esempio.it</p>
            <span class="ftm-badge ftm-badge-success">Attivo</span>
        </div>
    </div>
    <div class="ftm-user-card-right">
        <div class="ftm-user-stat-big">84%</div>
        <div class="ftm-user-stat-label">Media Generale</div>
    </div>
</div>
```

### Stats Cards
```html
<div class="ftm-stats-grid">
    <div class="ftm-stat-card yellow">
        <div class="ftm-stat-icon">📊</div>
        <div class="ftm-stat-content">
            <div class="ftm-stat-number">100</div>
            <div class="ftm-stat-label">Competenze Valutate</div>
        </div>
    </div>
    <div class="ftm-stat-card green">
        <div class="ftm-stat-icon">📈</div>
        <div class="ftm-stat-content">
            <div class="ftm-stat-number">5.1/6</div>
            <div class="ftm-stat-label">Livello Medio</div>
        </div>
    </div>
    <div class="ftm-stat-card blue">
        <div class="ftm-stat-icon">📋</div>
        <div class="ftm-stat-content">
            <div class="ftm-stat-number">128</div>
            <div class="ftm-stat-label">Competenze Assegnate</div>
        </div>
    </div>
</div>
```

### Card Generica
```html
<div class="ftm-card">
    <div class="ftm-card-header">
        <h3>Titolo Card</h3>
        <span class="ftm-badge ftm-badge-info">Info</span>
    </div>
    <div class="ftm-card-body">
        Contenuto...
    </div>
    <div class="ftm-card-footer">
        Footer opzionale
    </div>
</div>
```

### Badges
```html
<span class="ftm-badge ftm-badge-success">Passato</span>
<span class="ftm-badge ftm-badge-warning">Warning</span>
<span class="ftm-badge ftm-badge-danger">Fallito</span>
<span class="ftm-badge ftm-badge-info">Info</span>
<span class="ftm-badge ftm-badge-primary">Primario</span>
<span class="ftm-badge ftm-badge-teal">Teal</span>
```

### Buttons
```html
<button class="ftm-btn ftm-btn-primary">Primario</button>
<button class="ftm-btn ftm-btn-secondary">Secondario</button>
<button class="ftm-btn ftm-btn-success">Successo</button>
<button class="ftm-btn ftm-btn-danger">Pericolo</button>
<button class="ftm-btn ftm-btn-teal">Teal</button>
<button class="ftm-btn ftm-btn-outline">Outline</button>

<!-- Dimensioni -->
<button class="ftm-btn ftm-btn-primary ftm-btn-lg">Grande</button>
<button class="ftm-btn ftm-btn-primary ftm-btn-sm">Piccolo</button>
```

### Progress Bar
```html
<div class="ftm-progress ftm-progress-lg">
    <div class="ftm-progress-bar success" style="width: 92%;">
        92% Completato
    </div>
</div>
```

### Tabelle
```html
<table class="ftm-table">
    <thead>
        <tr>
            <th>Colonna 1</th>
            <th>Colonna 2</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Valore 1</td>
            <td>Valore 2</td>
        </tr>
    </tbody>
</table>
```

### Status Indicators
```html
<span class="ftm-status ftm-status-passed">✓</span>
<span class="ftm-status ftm-status-failed">✗</span>
<span class="ftm-status ftm-status-warning">!</span>
<span class="ftm-status ftm-status-skipped">–</span>
```

### Alerts
```html
<div class="ftm-alert ftm-alert-success">
    ✅ Operazione completata con successo!
</div>
<div class="ftm-alert ftm-alert-warning">
    ⚠️ Attenzione: verifica i dati.
</div>
<div class="ftm-alert ftm-alert-danger">
    ❌ Errore durante l'operazione.
</div>
<div class="ftm-alert ftm-alert-info">
    ℹ️ Informazione utile.
</div>
```

---

## Utilities

### Testo
```html
<p class="ftm-text-center">Centrato</p>
<p class="ftm-text-muted">Grigio</p>
<p class="ftm-text-success">Verde</p>
<p class="ftm-text-danger">Rosso</p>
```

### Spaziature
```html
<div class="ftm-mt-lg">Margin top large</div>
<div class="ftm-mb-md">Margin bottom medium</div>
<div class="ftm-p-lg">Padding large</div>
```

### Flex
```html
<div class="ftm-flex ftm-gap-md">Flex con gap</div>
<div class="ftm-flex-between">Space between</div>
<div class="ftm-flex-center">Centrato</div>
```

---

## Spacing Scale

| Nome | Variabile | Valore |
|------|-----------|--------|
| xs | `--ftm-space-xs` | 4px |
| sm | `--ftm-space-sm` | 8px |
| md | `--ftm-space-md` | 16px |
| lg | `--ftm-space-lg` | 24px |
| xl | `--ftm-space-xl` | 32px |
| 2xl | `--ftm-space-2xl` | 48px |

---

## Border Radius

| Nome | Variabile | Valore | Uso |
|------|-----------|--------|-----|
| sm | `--ftm-radius-sm` | 6px | Buttons |
| md | `--ftm-radius-md` | 8px | Inputs |
| lg | `--ftm-radius-lg` | 12px | Cards |
| xl | `--ftm-radius-xl` | 16px | User card |
| pill | `--ftm-radius-pill` | 50px | Badges |

---

## Shadows

| Nome | Uso |
|------|-----|
| `--ftm-shadow-sm` | Elementi leggeri |
| `--ftm-shadow-md` | Cards standard |
| `--ftm-shadow-lg` | Cards in hover |
| `--ftm-shadow-xl` | Modals |

---

## Esempio Pagina Completa

```html
<div class="ftm-page-bg">
    <div class="ftm-container">

        <div class="ftm-header">
            <h1 class="ftm-header-title">Dashboard FTM</h1>
        </div>

        <div class="ftm-user-card">
            <div class="ftm-user-card-left">
                <div class="ftm-user-avatar">CB</div>
                <div class="ftm-user-info">
                    <h2>Cristian Bodda</h2>
                    <p>cristian@ftm.ch</p>
                </div>
            </div>
            <div class="ftm-user-card-right">
                <div class="ftm-user-stat-big">92%</div>
                <div class="ftm-user-stat-label">Test Passati</div>
            </div>
        </div>

        <div class="ftm-stats-grid">
            <div class="ftm-stat-card green">
                <div class="ftm-stat-icon">✅</div>
                <div class="ftm-stat-content">
                    <div class="ftm-stat-number">38</div>
                    <div class="ftm-stat-label">Passati</div>
                </div>
            </div>
            <div class="ftm-stat-card yellow">
                <div class="ftm-stat-icon">⚠️</div>
                <div class="ftm-stat-content">
                    <div class="ftm-stat-number">3</div>
                    <div class="ftm-stat-label">Warning</div>
                </div>
            </div>
            <div class="ftm-stat-card red">
                <div class="ftm-stat-icon">❌</div>
                <div class="ftm-stat-content">
                    <div class="ftm-stat-number">0</div>
                    <div class="ftm-stat-label">Falliti</div>
                </div>
            </div>
        </div>

        <div class="ftm-card">
            <div class="ftm-card-header">
                <h3>Dettagli Test</h3>
            </div>
            <div class="ftm-card-body">
                <table class="ftm-table">
                    ...
                </table>
            </div>
        </div>

    </div>
</div>
```

---

## Plugin che Useranno il Design

- [ ] ftm_testsuite
- [ ] ftm_scheduler
- [ ] competencymanager
- [ ] selfassessment
- [ ] labeval
- [ ] competencyreport
- [ ] coachmanager
- [ ] ftm_hub

---

## Changelog

### v1.0 (21/01/2026)
- Design system iniziale
- Basato su student_report.php
- Componenti: header, cards, badges, buttons, tables, alerts
