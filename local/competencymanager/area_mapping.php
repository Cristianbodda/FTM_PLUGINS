<?php
/**
 * Mapping completo aree di competenza per tutti i settori
 * Estratto dal framework FTM ufficiale
 * 
 * AGGIORNATO: 07/01/2026 
 * - Aggiunto CHIMFARM con tutte le 11 aree
 * - Fix encoding UTF-8 (rimossi caratteri corrotti)
 * - Aggiunta funzione extract_sector_from_idnumber()
 */

// ========================================
// MAPPING NOMI AREE PER SETTORE
// ========================================
$AREA_NAMES = [
    // ========================================
    // AUTOMOBILE (pattern: AUTOMOBILE_XX_A1 -> A)
    // ========================================
    'AUTOMOBILE_A' => 'A. Accoglienza, diagnosi preliminare e documentazione',
    'AUTOMOBILE_B' => 'B. Motore e alimentazione',
    'AUTOMOBILE_C' => 'C. Lubrificazione e raffreddamento',
    'AUTOMOBILE_D' => 'D. Scarico e controllo emissioni',
    'AUTOMOBILE_E' => 'E. Trasmissione e trazione',
    'AUTOMOBILE_F' => 'F. Sospensioni, sterzo e freni',
    'AUTOMOBILE_G' => 'G. Elettronica di bordo e reti CAN/LIN',
    'AUTOMOBILE_H' => 'H. Sistemi ADAS e sensori',
    'AUTOMOBILE_I' => 'I. Climatizzazione e termica abitacolo (HVAC)',
    'AUTOMOBILE_J' => 'J. Veicoli ibridi ed elettrici (alta tensione)',
    'AUTOMOBILE_K' => 'K. Carrozzeria e allestimenti (interventi base)',
    'AUTOMOBILE_L' => 'L. Qualita, sicurezza e ambiente',
    'AUTOMOBILE_M' => 'M. Relazione cliente e amministrazione',
    'AUTOMOBILE_N' => 'N. Manutenzione programmata e collaudi',
    
    // ========================================
    // LOGISTICA (pattern: LOGISTICA_LO_A1 -> A)
    // ========================================
    'LOGISTICA_A' => 'A. Organizzazione dei mandati logistici',
    'LOGISTICA_B' => 'B. Qualita ed efficienza dei processi',
    'LOGISTICA_C' => 'C. Ricezione, controllo e stoccaggio merce',
    'LOGISTICA_D' => 'D. Commissionamento, preparazione e spedizione',
    'LOGISTICA_E' => 'E. Accettazione invii e consulenza',
    'LOGISTICA_F' => 'F. Recapito e servizi logistici',
    'LOGISTICA_G' => 'G. Operazioni di magazzino',
    'LOGISTICA_H' => 'H. Commissionamento e carico',
    
    // ========================================
    // ELETTRICITA (pattern: ELETTRICITA_XX_A1 -> A)
    // Nota: supporta anche varianti encoding (ELETTRICITÀ)
    // ========================================
    'ELETTRICITA_A' => 'A. Pianificazione e Progettazione',
    'ELETTRICITA_B' => 'B. Installazione impianti BT (edifici)',
    'ELETTRICITA_C' => 'C. Montaggio e Cablaggio quadri',
    'ELETTRICITA_D' => 'D. Reti di distribuzione (media/bassa tensione)',
    'ELETTRICITA_E' => 'E. Misure, collaudi e verifiche',
    'ELETTRICITA_F' => 'F. Sicurezza, norme e conformita',
    'ELETTRICITA_G' => 'G. Documentazione, qualita e CAD/BIM',
    'ELETTRICITA_H' => 'H. Manutenzione e Service',
    
    // ========================================
    // AUTOMAZIONE (pattern: AUTOMAZIONE_XX_A1 -> A)
    // ========================================
    'AUTOMAZIONE_A' => 'A. Pianificazione e Documentazione',
    'AUTOMAZIONE_B' => 'B. Montaggio meccanico ed Elettromeccanico',
    'AUTOMAZIONE_C' => 'C. Cablaggio elettrico e Quadri',
    'AUTOMAZIONE_D' => 'D. Automazione e PLC',
    'AUTOMAZIONE_E' => 'E. Strumentazione e Misure',
    'AUTOMAZIONE_F' => 'F. Reti e Comunicazione industriale',
    'AUTOMAZIONE_G' => 'G. Qualita, Sicurezza e Normative',
    'AUTOMAZIONE_H' => 'H. Manutenzione e Service',
    
    // ========================================
    // METALCOSTRUZIONE (pattern: METALCOSTRUZIONE_XX_A1 -> A)
    // ========================================
    'METALCOSTRUZIONE_A' => 'A. Pianificazione, Disegno e CAD',
    'METALCOSTRUZIONE_B' => 'B. Preparazione e Taglio',
    'METALCOSTRUZIONE_C' => 'C. Lavorazioni e Assemblaggio',
    'METALCOSTRUZIONE_D' => 'D. Saldatura',
    'METALCOSTRUZIONE_E' => 'E. Trattamenti superficiali e Protezione',
    'METALCOSTRUZIONE_F' => 'F. Montaggio e Posa in opera',
    'METALCOSTRUZIONE_G' => 'G. Misure, Qualita e Conformita',
    'METALCOSTRUZIONE_H' => 'H. Sicurezza, Ambiente e Organizzazione',
    'METALCOSTRUZIONE_I' => 'I. CAD/CAM e BIM',
    'METALCOSTRUZIONE_J' => 'J. Manutenzione e Ripristino',
    
    // ========================================
    // MECCANICA (pattern codice: MECCANICA_CNC_01 -> CNC)
    // ========================================
    'MECCANICA_LMB' => 'LMB. Lavorazioni meccaniche di base',
    'MECCANICA_LMC' => 'LMC. Lavorazioni su macchine convenzionali',
    'MECCANICA_CNC' => 'CNC. Lavorazioni CNC e tecnologie digitali',
    'MECCANICA_ASS' => 'ASS. Assemblaggio e montaggio meccanico',
    'MECCANICA_MIS' => 'MIS. Misurazione e controllo qualita',
    'MECCANICA_GEN' => 'GEN. Trattamenti e processi speciali',
    'MECCANICA_MAN' => 'MAN. Manutenzione e revisione impianti',
    'MECCANICA_DT' => 'DT. Disegno tecnico e progettazione',
    'MECCANICA_AUT' => 'AUT. Automazione e meccatronica',
    'MECCANICA_PIAN' => 'PIAN. Pianificazione e documentazione tecnica',
    'MECCANICA_SAQ' => 'SAQ. Sicurezza, ambiente e qualita',
    'MECCANICA_CSP' => 'CSP. Collaborazione e sviluppo personale',
    'MECCANICA_PRG' => 'PRG. Progettazione avanzata',
    
    // ========================================
    // CHIMFARM (pattern codice: CHIMFARM_1C_01 -> 1C)
    // ========================================
    'CHIMFARM_1C' => '1C. Conformita e GMP',
    'CHIMFARM_1G' => '1G. Gestione Materiali',
    'CHIMFARM_1O' => '1O. Operazioni Base',
    'CHIMFARM_2M' => '2M. Misurazione',
    'CHIMFARM_3C' => '3C. Controllo Qualita',
    'CHIMFARM_4S' => '4S. Sicurezza',
    'CHIMFARM_5S' => '5S. Sterilita',
    'CHIMFARM_6P' => '6P. Produzione',
    'CHIMFARM_7S' => '7S. Strumentazione',
    'CHIMFARM_8T' => '8T. Tecnologie',
    'CHIMFARM_9A' => '9A. Analisi',

    // ========================================
    // GENERICO (pattern: GEN_A_01 -> A)
    // Test trasversali per orientamento settoriale
    // ========================================
    'GEN_A' => 'A. Meccanica',
    'GEN_B' => 'B. Metalcostruzione',
    'GEN_C' => 'C. Elettricità',
    'GEN_D' => 'D. Elettronica & Automazione',
    'GEN_E' => 'E. Logistica',
    'GEN_F' => 'F. Chimico-farmaceutico',
    'GEN_G' => 'G. Automobile / Manutenzione',
];

// ========================================
// CONFIGURAZIONE PATTERN SETTORI
// ========================================

// Settori che usano il pattern lettera (terza parte dell'idnumber)
// Es. LOGISTICA_LO_A1 -> estrae "A" dalla terza parte
// Nota: GEN usa pattern speciale GEN_A_01 (seconda parte = lettera)
$LETTER_BASED_SECTORS = ['AUTOMOBILE', 'LOGISTICA', 'ELETTRICITA', 'AUTOMAZIONE', 'METALCOSTRUZIONE'];

// Settori che usano il pattern codice (seconda parte dell'idnumber)
// Es. MECCANICA_CNC_01 -> estrae "CNC" dalla seconda parte
// Es. CHIMFARM_1C_01 -> estrae "1C" dalla seconda parte
$CODE_BASED_SECTORS = ['MECCANICA', 'CHIMFARM'];

// ========================================
// NOMI SETTORI PER DISPLAY
// ========================================
$SECTOR_DISPLAY_NAMES = [
    'AUTOMOBILE' => 'Automobile',
    'LOGISTICA' => 'Logistica',
    'ELETTRICITA' => 'Elettricita',
    'AUTOMAZIONE' => 'Automazione',
    'METALCOSTRUZIONE' => 'Metalcostruzione',
    'MECCANICA' => 'Meccanica',
    'CHIMFARM' => 'Chimico-Farmaceutico',
    'GEN' => 'Generico',
    'GENERICO' => 'Generico',
];

// ========================================
// FUNZIONI
// ========================================

/**
 * Normalizza il nome del settore (gestisce varianti encoding)
 * @param string $sector Nome settore grezzo
 * @return string Nome settore normalizzato
 */
function normalize_sector_name($sector) {
    // Rimuovi caratteri problematici encoding
    $normalized = str_replace(
        ['À', 'Ã€', 'Ã ', 'à', 'Á', 'á', 'Â', 'â'],
        'A',
        $sector
    );
    // ELETTRICITÀ -> ELETTRICITA
    $normalized = str_replace(['Ì', 'Í', 'Î', 'Ï', 'ì', 'í', 'î', 'ï'], 'I', $normalized);
    return strtoupper($normalized);
}

/**
 * Estrae il settore da un idnumber di competenza
 * @param string $idnumber Es. "LOGISTICA_LO_A1" o "CHIMFARM_1C_01"
 * @return string Settore normalizzato (es. "LOGISTICA", "CHIMFARM")
 */
function extract_sector_from_idnumber($idnumber) {
    if (empty($idnumber)) {
        return 'UNKNOWN';
    }
    $parts = explode('_', $idnumber);
    if (count($parts) < 1) {
        return 'UNKNOWN';
    }
    return normalize_sector_name($parts[0]);
}

/**
 * Estrae il codice area e il nome completo da un idnumber di competenza
 * @param string $idnumber Es. "LOGISTICA_LO_A1" o "MECCANICA_CNC_01" o "CHIMFARM_1C_01"
 * @return array ['code' => 'A', 'name' => 'A. Organizzazione...', 'key' => 'LOGISTICA_A']
 */
function get_area_info($idnumber) {
    // Rimuovi prefisso OLD_ se presente
    if (strpos($idnumber, 'OLD_') === 0) {
        $idnumber = substr($idnumber, 4);
    }
    global $AREA_NAMES, $LETTER_BASED_SECTORS, $CODE_BASED_SECTORS;

    $parts = explode('_', $idnumber);
    if (count($parts) < 2) {
        return ['code' => 'OTHER', 'name' => 'Altro', 'key' => 'OTHER'];
    }

    $sector = normalize_sector_name($parts[0]);

    // Pattern speciale per GEN (GENERICO): GEN_A_01 -> seconda parte è la lettera
    if ($sector === 'GEN' && count($parts) >= 2) {
        $letter = strtoupper($parts[1]);
        if (preg_match('/^[A-Z]$/', $letter)) {
            $key = 'GEN_' . $letter;
            $name = $AREA_NAMES[$key] ?? $letter;
            return ['code' => $letter, 'name' => $name, 'key' => $key];
        }
    }

    // Settori con pattern lettera (LOGISTICA_LO_A1 -> A)
    if (in_array($sector, $LETTER_BASED_SECTORS) && count($parts) >= 3) {
        preg_match('/^([A-Z])/i', $parts[2], $matches);
        if (!empty($matches[1])) {
            $letter = strtoupper($matches[1]);
            $key = $sector . '_' . $letter;
            $name = $AREA_NAMES[$key] ?? $letter;
            return ['code' => $letter, 'name' => $name, 'key' => $key];
        }
    }

    // Settori con pattern codice (MECCANICA_CNC_01 -> CNC, CHIMFARM_1C_01 -> 1C)
    if (in_array($sector, $CODE_BASED_SECTORS) && count($parts) >= 2) {
        $code = $parts[1];
        $key = $sector . '_' . $code;
        $name = $AREA_NAMES[$key] ?? $code;
        return ['code' => $code, 'name' => $name, 'key' => $key];
    }

    // Fallback: usa la seconda parte
    $code = $parts[1] ?? 'OTHER';
    return ['code' => $code, 'name' => $code, 'key' => $sector . '_' . $code];
}

/**
 * Ottiene il nome display di un settore
 * @param string $sector Codice settore
 * @return string Nome per visualizzazione
 */
function get_sector_display_name($sector) {
    global $SECTOR_DISPLAY_NAMES;
    $normalized = normalize_sector_name($sector);
    return $SECTOR_DISPLAY_NAMES[$normalized] ?? $sector;
}
