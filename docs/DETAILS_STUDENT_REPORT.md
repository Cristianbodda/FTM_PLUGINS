# STUDENT REPORT PRINT - DETTAGLI TECNICI (22/01/2026)

## Sistema Stampa Professionale

Implementato in `local/competencymanager/` per generare report PDF/stampa di alta qualita.

## File Coinvolti
```
local/competencymanager/
├── student_report.php           # Pagina principale + generate_svg_radar()
├── student_report_print.php     # Template stampa completo
└── pix/ftm_logo.png             # Logo FTM (scaricato localmente)
```

## Funzione generate_svg_radar()

```php
function generate_svg_radar(
    $data,                    // Array di ['label' => ..., 'value' => ...]
    $title = '',              // Titolo opzionale
    $size = 300,              // Dimensione grafico (ora 490px)
    $fillColor = 'rgba(...)', // Colore riempimento
    $strokeColor = '#667eea', // Colore bordo
    $labelFontSize = 9,       // Font etichette (ora 9)
    $maxLabelLen = 250        // Max caratteri etichetta
)
```

## Parametri Radar Attuali
| Parametro | Valore | Note |
|-----------|--------|------|
| Size | 490px | +40% rispetto originale 340px |
| horizontalPadding | 180px | Spazio laterale per etichette lunghe |
| SVG Width | size + 360px | 490 + 360 = 850px totale |
| labelFontSize | 9 | Font etichette |
| maxLabelLen | 250 | Nessun troncamento pratico |

## Sezioni Configurabili (Ordine 1-9)
1. `valutazione` - Valutazione Globale
2. `progressi` - Progressi Recenti
3. `autovalutazione` - Radar Autovalutazione
4. `performance` - Radar Performance
5. `dettaglio_aree` - Analisi per Area
6. `raccomandazioni` - Raccomandazioni
7. `piano_azione` - Piano d'Azione
8. `note` - Note Aggiuntive
9. `confronto` - Confronto Auto/Reale

## Tabelle Legenda (Dimensioni +20%)
| Elemento | Valore |
|----------|--------|
| Font tabella | 8.5pt |
| Titolo h6 | 11pt bold |
| Padding celle | 5px 8px |
| Larghezza colonne | 70px/65px |

## CSS Print
```css
@page { size: A4; margin: 15mm; }
body { padding-top: 75px; } /* Spazio header */
.page-break-before { page-break-before: always; }
```

## Branding FTM
- **Logo:** `/local/competencymanager/pix/ftm_logo.png`
- **Font:** Didact Gothic (Google Fonts)
- **Colore accento:** #dd0000 (rosso FTM)
- **Header running:** Logo + nome organizzazione su ogni pagina
