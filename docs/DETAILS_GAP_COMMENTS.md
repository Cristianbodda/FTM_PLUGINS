# GAP COMMENTS SYSTEM - DETTAGLI TECNICI (28/01/2026)

## Panoramica
Sistema automatico per generare suggerimenti basati sul confronto tra autovalutazione e quiz performance.

## File Principale
```
local/competencymanager/
└── gap_comments_mapping.php    # 79 aree mappate con attivita lavorative
```

## Funzione Principale
```php
function generate_gap_comment($areaKey, $autovalutazione, $performance, $tone = 'formale') {
    // Calcola gap: autovalutazione - performance
    // gap > 0: Sovrastima (studente si valuta meglio di quanto sia)
    // gap < 0: Sottostima (studente si sottovaluta)
    // gap = 0: Allineamento

    return [
        'tipo' => 'sovrastima|sottostima|allineamento',
        'commento' => '...testo generato...',
        'attivita' => ['attivita1', 'attivita2', ...]
    ];
}
```

## Toni Disponibili
| Tono | Uso | Stile |
|------|-----|-------|
| `formale` | Suggerimenti Rapporto | Terza persona, professionale |
| `colloquiale` | Spunti Colloquio | Diretto al "tu", empatico |

## Integrazione nel Report
- **Sezione "Suggerimenti Rapporto":** Testo formale per documentazione
- **Sezione "Spunti Colloquio":** Punti di discussione per il coach

## Aree Coperte (79 totali)
Tutti i 7 settori FTM con aree A-G e sotto-aree (es. MECCANICA_A_01, AUTOMOBILE_B_03).
