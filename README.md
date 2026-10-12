# DataCheck for CF7

Controllo anti-spam per Contact Form 7: campo esca nascosto + tempo minimo di compilazione.
Aggiungi il tag `[datacheck]` al form per abilitarlo. Gli invii bloccati vengono marcati come spam da CF7.

Il tempo minimo (default 3 secondi) si può cambiare in `wp-config.php`:

```php
define( 'DATACHECK_MIN_SECONDS', 5 );
```

## Installazione

Scarica `datacheck.zip` dall'ultima [release](https://github.com/webottomedia/datacheck/releases/latest) e caricalo da Plugin → Aggiungi nuovo → Carica plugin.
Gli aggiornamenti successivi arrivano dalla bacheca di WordPress.

## Pubblicare una nuova versione

1. Aggiorna `Version:` in `datacheck/datacheck.php`
2. `git commit -am "..."`, poi `git tag vX.Y.Z` e `git push origin main vX.Y.Z`

La GitHub Action crea la release con lo zip allegato.
