<?php
/**
 * Mapping completo aree di competenza per tutti i settori
 * Estratto dal framework FTM ufficiale
 */

// Mapping per settori con pattern SETTORE_PROFILO_LETTERA (es. LOGISTICA_LO_A1 -> A)
$AREA_NAMES = [
    // AUTOMOBILE
    'AUTOMOBILE_A' => 'A. Accoglienza, diagnosi preliminare & documentazione',
    'AUTOMOBILE_B' => 'B. Motore & alimentazione',
    'AUTOMOBILE_C' => 'C. Lubrificazione & raffreddamento',
    'AUTOMOBILE_D' => 'D. Scarico & controllo emissioni',
    'AUTOMOBILE_E' => 'E. Trasmissione & trazione',
    'AUTOMOBILE_F' => 'F. Sospensioni, sterzo & freni',
    'AUTOMOBILE_G' => 'G. Elettronica di bordo & reti CAN/LIN',
    'AUTOMOBILE_H' => 'H. Sistemi ADAS & sensori',
    'AUTOMOBILE_I' => 'I. Climatizzazione & termica abitacolo (HVAC)',
    'AUTOMOBILE_J' => 'J. Veicoli ibridi & elettrici (alta tensione)',
    'AUTOMOBILE_K' => 'K. Carrozzeria & allestimenti (interventi base)',
    'AUTOMOBILE_L' => 'L. Qualità, sicurezza & ambiente',
    'AUTOMOBILE_M' => 'M. Relazione cliente & amministrazione',
    'AUTOMOBILE_N' => 'N. Manutenzione programmata & collaudi',
    
    // LOGISTICA
    'LOGISTICA_A' => 'A. Organizzazione dei mandati logistici',
    'LOGISTICA_B' => 'B. Qualità ed efficienza dei processi',
    'LOGISTICA_C' => 'C. Ricezione, controllo e stoccaggio merce',
    'LOGISTICA_D' => 'D. Commissionamento, preparazione e spedizione',
    'LOGISTICA_E' => 'E. Accettazione invii e consulenza',
    'LOGISTICA_F' => 'F. Recapito e servizi logistici',
    'LOGISTICA_G' => 'G. Operazioni di magazzino',
    'LOGISTICA_H' => 'H. Commissionamento e carico',
    
    // ELETTRICITÀ
    'ELETTRICITÀ_A' => 'A. Pianificazione & Progettazione',
    'ELETTRICITÀ_B' => 'B. Installazione impianti BT (edifici)',
    'ELETTRICITÀ_C' => 'C. Montaggio & Cablaggio quadri',
    'ELETTRICITÀ_D' => 'D. Reti di distribuzione (media/bassa tensione)',
    'ELETTRICITÀ_E' => 'E. Misure, collaudi & verifiche',
    'ELETTRICITÀ_F' => 'F. Sicurezza, norme & conformità',
    'ELETTRICITÀ_G' => 'G. Documentazione, qualità & CAD/BIM',
    'ELETTRICITÀ_H' => 'H. Manutenzione & Service',
    
    // AUTOMAZIONE
    'AUTOMAZIONE_A' => 'A. Pianificazione & Documentazione',
    'AUTOMAZIONE_B' => 'B. Montaggio meccanico & Elettromeccanico',
    'AUTOMAZIONE_C' => 'C. Cablaggio elettrico & Quadri',
    'AUTOMAZIONE_D' => 'D. Automazione & PLC',
    'AUTOMAZIONE_E' => 'E. Strumentazione & Misure',
    'AUTOMAZIONE_F' => 'F. Reti & Comunicazione industriale',
    'AUTOMAZIONE_G' => 'G. Qualità, Sicurezza & Normative',
    'AUTOMAZIONE_H' => 'H. Manutenzione & Service',
    
    // METALCOSTRUZIONE
    'METALCOSTRUZIONE_A' => 'A. Pianificazione, Disegno & CAD',
    'METALCOSTRUZIONE_B' => 'B. Preparazione & Taglio',
    'METALCOSTRUZIONE_C' => 'C. Lavorazioni & Assemblaggio',
    'METALCOSTRUZIONE_D' => 'D. Saldatura',
    'METALCOSTRUZIONE_E' => 'E. Trattamenti superficiali & Protezione',
    'METALCOSTRUZIONE_F' => 'F. Montaggio & Posa in opera',
    'METALCOSTRUZIONE_G' => 'G. Misure, Qualità & Conformità',
    'METALCOSTRUZIONE_H' => 'H. Sicurezza, Ambiente & Organizzazione',
    'METALCOSTRUZIONE_I' => 'I. CAD/CAM & BIM',
    'METALCOSTRUZIONE_J' => 'J. Manutenzione & Ripristino',
    
    // MECCANICA (usa codici invece di lettere)
    'MECCANICA_LMB' => 'Lavorazioni meccaniche di base',
    'MECCANICA_LMC' => 'Lavorazioni su macchine convenzionali',
    'MECCANICA_CNC' => 'Lavorazioni CNC e tecnologie digitali',
    'MECCANICA_ASS' => 'Assemblaggio e montaggio meccanico',
    'MECCANICA_MIS' => 'Misurazione e controllo qualità',
    'MECCANICA_GEN' => 'Trattamenti e processi speciali',
    'MECCANICA_MAN' => 'Manutenzione e revisione impianti',
    'MECCANICA_DT' => 'Disegno tecnico e progettazione',
    'MECCANICA_AUT' => 'Automazione e meccatronica',
    'MECCANICA_PIAN' => 'Pianificazione e documentazione tecnica',
    'MECCANICA_SAQ' => 'Sicurezza, ambiente e qualità',
    'MECCANICA_CSP' => 'Collaborazione e sviluppo personale',
    'MECCANICA_PRG' => 'Progettazione avanzata',
];

// Settori che usano il pattern lettera (terza parte dell'idnumber)
$LETTER_BASED_SECTORS = ['AUTOMOBILE', 'LOGISTICA', 'ELETTRICITÀ', 'AUTOMAZIONE', 'METALCOSTRUZIONE'];

// Settori che usano il pattern codice (seconda parte dell'idnumber)
$CODE_BASED_SECTORS = ['MECCANICA'];

/**
 * Estrae il codice area e il nome completo da un idnumber di competenza
 * @param string $idnumber Es. "LOGISTICA_LO_A1" o "MECCANICA_CNC_01"
 * @return array ['code' => 'A', 'name' => 'A. Organizzazione...', 'key' => 'LOGISTICA_A']
 */
function get_area_info($idnumber) {
    global $AREA_NAMES, $LETTER_BASED_SECTORS, $CODE_BASED_SECTORS;
    
    $parts = explode('_', $idnumber);
    if (count($parts) < 2) {
        return ['code' => 'OTHER', 'name' => 'Altro', 'key' => 'OTHER'];
    }
    
    $sector = $parts[0];
    
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
    
    // Settori con pattern codice (MECCANICA_CNC_01 -> CNC)
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
