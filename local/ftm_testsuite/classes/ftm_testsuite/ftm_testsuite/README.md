# FIX CRITICO v5.5 - Domande senza risposte

## 🐛 BUG TROVATO

Il codice riutilizzava domande esistenti SENZA verificare se avevano risposte.
Le domande corrotte (senza risposte) venivano riusate invece di essere ricreate.

## ✅ FIX APPLICATO

Ora il codice:
1. Controlla se la domanda esistente ha risposte
2. Se NON ha risposte → la elimina e ricrea
3. Se HA risposte → la riusa (comportamento normale)

## 📦 Installazione

Sostituisci `setup_universale.php` e ricarica.

Le domande corrotte verranno eliminate e ricreate automaticamente.
