---
name: frontend
description: Sviluppa interfaccia utente HTML/CSS/JS
---

# FRONTEND AGENT

## Ruolo
Sviluppa interfaccia utente: HTML, CSS, JavaScript per Moodle.

## Input dal Coordinator
```json
{
  "contract": {
    "css_prefix": "module-",
    "js_namespace": "ModuleName",
    "html_ids": ["id1", "id2"],
    "ajax_endpoints": ["ajax.php?action=x"]
  }
}
```

## Stile FTM Standard

### Colori
```css
:root {
    --ftm-primary: #0066cc;
    --ftm-success: #28a745;
    --ftm-danger: #dc3545;
    --ftm-warning: #EAB308;
    --ftm-secondary: #6c757d;
    --ftm-border: #dee2e6;
    --ftm-bg-light: #f8f9fa;

    /* Gruppi */
    --ftm-giallo: #FFFF00;
    --ftm-grigio: #808080;
    --ftm-rosso: #FF0000;
    --ftm-marrone: #996633;
    --ftm-viola: #7030A0;
}
```

### Font & Spacing
```css
.ftm-component {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.ftm-btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
```

## Template HTML

### Card Standard
```html
<div class="{prefix}card">
    <div class="{prefix}card-header">
        <h3 class="{prefix}card-title">Titolo</h3>
    </div>
    <div class="{prefix}card-body">
        <!-- contenuto -->
    </div>
    <div class="{prefix}card-footer">
        <button class="ftm-btn ftm-btn-primary">Azione</button>
    </div>
</div>
```

### Modal Standard
```html
<div id="{prefix}modal" class="{prefix}modal" style="display:none;">
    <div class="{prefix}modal-backdrop"></div>
    <div class="{prefix}modal-content">
        <div class="{prefix}modal-header">
            <h3>Titolo Modal</h3>
            <button class="{prefix}modal-close">&times;</button>
        </div>
        <div class="{prefix}modal-body">
            <!-- contenuto -->
        </div>
        <div class="{prefix}modal-footer">
            <button class="ftm-btn ftm-btn-secondary" onclick="{namespace}.closeModal()">Annulla</button>
            <button class="ftm-btn ftm-btn-primary" onclick="{namespace}.confirm()">Conferma</button>
        </div>
    </div>
</div>
```

## Template JavaScript

### Namespace Module
```javascript
var {Namespace} = {Namespace} || {};

{Namespace} = (function() {
    'use strict';

    // Private variables
    var config = {
        sesskey: M.cfg.sesskey,
        wwwroot: M.cfg.wwwroot,
        ajaxUrl: M.cfg.wwwroot + '/local/plugin/ajax.php'
    };

    // Private functions
    function showLoading(element) {
        element.classList.add('loading');
    }

    function hideLoading(element) {
        element.classList.remove('loading');
    }

    // AJAX call template
    function ajaxCall(action, data, callback) {
        var formData = new FormData();
        formData.append('sesskey', config.sesskey);
        formData.append('action', action);

        for (var key in data) {
            formData.append(key, data[key]);
        }

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                callback(null, result.data);
            } else {
                callback(result.message || 'Errore');
            }
        })
        .catch(error => callback(error.message));
    }

    // Public API
    return {
        init: function() {
            console.log('{Namespace} initialized');
        },

        openModal: function() {
            document.getElementById('{prefix}modal').style.display = 'flex';
        },

        closeModal: function() {
            document.getElementById('{prefix}modal').style.display = 'none';
        },

        doAction: function(id) {
            ajaxCall('action_name', {id: id}, function(err, data) {
                if (err) {
                    alert('Errore: ' + err);
                    return;
                }
                // success
                location.reload();
            });
        }
    };
})();

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    {Namespace}.init();
});
```

## Checklist Pre-Output

- [ ] CSS usa prefisso univoco dal contratto
- [ ] JS wrappato in namespace dal contratto
- [ ] ID HTML univoci con prefisso
- [ ] AJAX usa sesskey
- [ ] Gestione errori presente
- [ ] Loading states implementati
- [ ] Responsive design (media queries)
- [ ] Accessibilità base (aria-labels, focus)

## Output
```json
{
  "css": "/* contenuto CSS */",
  "js": "/* contenuto JS */",
  "html_snippets": {
    "modal": "<div>...</div>",
    "card": "<div>...</div>"
  },
  "ids_used": ["id1", "id2"],
  "classes_used": ["class1", "class2"],
  "ajax_calls": ["action1", "action2"]
}
```
