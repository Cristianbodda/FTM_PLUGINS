# FTM SCHEDULER - EXCEL IMPORT (03/02/2026)

## Panoramica
Sistema completo per importare attivita dal file Excel di planning mensile con rilevamento automatico di coach, gruppi, aule e progetti esterni.

## Struttura Colonne Excel
| Colonna | Contenuto | Esempio |
|---------|-----------|---------|
| A | Data | 03/02/2026 |
| C | Matt/Pom | Mattina / Pomeriggio |
| K | Aula 1 - Coach | DB |
| L | Aula 1 - Attivita/Colore | (nero = LADI) |
| M | Aula 2 - Coach | GM, FM, RB |
| N | Aula 2 - Attivita | GR. GRIGIO, At. Canali |
| O | Aula 3 - Coach | RB |
| P | Aula 3 - Colore | (nero = esterno) |

## Rilevamento Colori Celle
| Colore | Gruppo |
|--------|--------|
| Giallo | GIALLO |
| Grigio | GRIGIO |
| Rosso | ROSSO |
| Marrone | MARRONE |
| Viola | VIOLA |
| Nero | Esterno (LADI, BIT) |

## File Principali
```
local/ftm_scheduler/
├── import_calendar.php         # Pagina import con preview
├── classes/calendar_importer.php # Parser Excel completo
├── edit_activity.php           # Modifica attivita
├── edit_external.php           # Modifica progetti esterni
├── ajax_import_calendar.php    # Endpoint AJAX import
├── ajax_delete_activities.php  # Endpoint eliminazione
└── debug_rooms.php             # Tool debug 3 aule
```

## Classe calendar_importer
```php
$importer = new \local_ftm_scheduler\calendar_importer(2026);
$preview = $importer->preview_file($filepath, 'Febbraio');
$result = $importer->import_file($filepath, ['sheets' => ['Febbraio']]);
```

## Parsing Multi-Aula
Per ogni riga con Matt/Pom, vengono create fino a 3 attivita (una per aula):
```php
$aula1 = parse_room_data($sheet, $row, 'K', 'L', 1, ...);
$aula2 = parse_room_data($sheet, $row, 'M', 'N', 2, ...);
$aula3 = parse_room_data($sheet, $row, 'O', 'P', 3, ...);
```

## Rilevamento Colore Cella
```php
private function get_cell_background_color($sheet, $cellRef) {
    $style = $sheet->getStyle($cellRef);
    $fill = $style->getFill();
    if ($fill->getFillType() === Fill::FILL_SOLID) {
        return '#' . $fill->getStartColor()->getRGB();
    }
    return null;
}
```

## Coach-Group Inference
Il sistema costruisce una mappa coach->gruppo per settimana nella prima passata:
```php
$this->coach_group_map['2026-W07']['GM'] = 'grigio';
```
Poi usa questa mappa per assegnare gruppi ad attivita senza gruppo esplicito.
