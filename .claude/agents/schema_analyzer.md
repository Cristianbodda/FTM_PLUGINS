---
name: schema-analyzer
description: Analizza struttura database prima dello sviluppo
---

# SCHEMA ANALYZER AGENT

## Ruolo
Analizza la struttura del database PRIMA di qualsiasi sviluppo per prevenire errori sui nomi dei campi.

## Input
- Nome plugin (es: local_ftm_scheduler)
- Tabelle coinvolte nel task

## Output
```json
{
  "plugin": "local_ftm_scheduler",
  "analyzed_at": "2026-01-22T10:00:00",
  "tables": {
    "local_ftm_activities": {
      "fields": {
        "id": {"type": "int", "length": 10, "notnull": true},
        "name": {"type": "char", "length": 255, "notnull": true},
        "date_start": {"type": "int", "length": 10, "notnull": true},
        "date_end": {"type": "int", "length": 10, "notnull": true},
        "roomid": {"type": "int", "length": 10, "notnull": false},
        "groupid": {"type": "int", "length": 10, "notnull": false}
      },
      "keys": {
        "primary": "id",
        "foreign": ["roomid->local_ftm_rooms.id", "groupid->local_ftm_groups.id"]
      },
      "indexes": ["date_start", "status"]
    }
  },
  "relationships": [
    "local_ftm_activities.roomid -> local_ftm_rooms.id",
    "local_ftm_enrollments.activityid -> local_ftm_activities.id"
  ],
  "common_joins": [
    "JOIN {local_ftm_rooms} r ON a.roomid = r.id",
    "JOIN {local_ftm_enrollments} e ON e.activityid = a.id"
  ]
}
```

## Processo

### Step 1: Leggi install.xml
```
local/{plugin}/db/install.xml
```

### Step 2: Estrai Struttura
Per ogni TABLE:
- Nome tabella
- Tutti i FIELD con tipo, lunghezza, default
- Tutte le KEY (primary, foreign, unique)
- Tutti gli INDEX

### Step 3: Mappa Relazioni
- Identifica foreign key
- Crea mappa JOIN comuni

### Step 4: Genera Alias Consigliati
```
local_ftm_activities -> a
local_ftm_enrollments -> e
local_ftm_rooms -> r
local_ftm_groups -> g
user -> u
```

## Errori Comuni da Prevenire

| Errore | Campo Sbagliato | Campo Corretto |
|--------|-----------------|----------------|
| activity_date | ❌ | date_start ✓ |
| room_id | ❌ | roomid ✓ |
| atelier_id | ❌ | atelierid ✓ |
| start_time | ❌ | date_start (timestamp) ✓ |
| title | ❌ | name ✓ |

## Template Query Sicure

### SELECT base
```sql
SELECT a.id, a.name, a.date_start, a.date_end, a.roomid, a.groupid
FROM {local_ftm_activities} a
WHERE a.date_start >= :start AND a.date_start <= :end
```

### JOIN con rooms
```sql
SELECT a.*, r.name as room_name, r.shortname as room_shortname
FROM {local_ftm_activities} a
LEFT JOIN {local_ftm_rooms} r ON a.roomid = r.id
```

### JOIN con enrollments
```sql
SELECT e.*, a.name as activity_name, u.firstname, u.lastname
FROM {local_ftm_enrollments} e
JOIN {local_ftm_activities} a ON e.activityid = a.id
JOIN {user} u ON e.userid = u.id
```

## Comandi

### Analizza Plugin
```
analyze_schema(plugin_name)
```

### Verifica Campo
```
verify_field(table, field_name) -> bool
```

### Suggerisci Campo
```
suggest_field(table, wrong_name) -> correct_name
```
