# DOG Library Module

## Panoramica
DOG Library è un sotto-modulo del sistema DOG (Drupal Omeka Geonode) che fornisce un'interfaccia utente in stile media library di Drupal per la navigazione e la selezione delle risorse Omeka. Questo modulo è fondamentale per fornire agli editor un'esperienza fluida nella selezione delle risorse Omeka da incorporare nei contenuti Drupal.

## Funzionalità principali

### Interfaccia di selezione risorse
- Fornisce una UI in stile "media library" per la selezione di risorse Omeka
- Supporta la visualizzazione a griglia e tabella per sfogliare le risorse
- Include filtri e paginazione per gestire grandi collezioni di risorse
- Permette la selezione multipla di risorse per l'inserimento nei contenuti

### Widget per campi entità
- Widget personalizzato per campi di tipo `dog_omeka_resource`
- Gestione della selezione e rimozione delle risorse tramite interfaccia drag-and-drop
- Anteprima delle risorse selezionate direttamente nel form di modifica dell'entità

### Integrazione con Views
- Vista personalizzata `resource_library` per visualizzare le risorse Omeka
- Plugin di campo personalizzato per la selezione delle risorse
- Supporto per l'AJAX nelle viste per una navigazione fluida

## Architettura tecnica

### Componenti chiave
- **ResourceLibraryState**: Gestisce lo stato della finestra di dialogo della library
- **ResourceLibraryUiBuilder**: Costruisce l'interfaccia utente per la selezione delle risorse
- **ResourceLibraryFieldWidgetOpener**: Gestisce l'apertura del selettore dal widget del campo
- **OpenerResolver**: Risolve i vari tipi di "openers" per la library

### Interazione con il modulo DOG principale
Il modulo `dog_library` si basa fortemente sui servizi forniti dal modulo DOG principale:

1. **Recupero delle risorse**:
   - Utilizza il servizio `dog.omeka_resource_fetcher` per ottenere i dati delle risorse Omeka
   - Ogni visualizzazione di risorsa nella libreria richiede una chiamata al fetcher

2. **Rendering delle risorse**:
   - Utilizza il servizio `dog.omeka_resource_view_builder` per il rendering delle risorse
   - Implementa un theme hook personalizzato `dog_omeka_resource__library` per la visualizzazione delle risorse nella library

3. **Gestione degli URL**:
   - Si basa sul servizio `dog.omeka_url` per la trasformazione degli URL tra API e URL pubblici

## Problematiche di performance

### Criticità attuali
Il modulo `dog_library` eredita le problematiche di performance del modulo DOG principale:

1. **Chiamate API in tempo reale**:
   - Ogni volta che viene caricata la vista della library, viene eseguita una chiamata API per ogni risorsa
   - Non esiste un meccanismo di caching implementato, causando lentezza quando si gestiscono più di 20 oggetti
   - Le chiamate sequenziali all'API esterna aumentano significativamente i tempi di caricamento

2. **Rendering multiplo**:
   - Ogni rendering di risorsa richiede una nuova chiamata all'API
   - Nessuna strategia di caching per il rendering implementata
   - Interazioni JavaScript che potrebbero richiedere più volte lo stesso dato

3. **Integrazione con Views**:
   - La vista `resource_library` esegue query remote inefficienti
   - Supporto limitato per la paginazione effettiva
   - Nessun caching dei risultati della vista

## Strategie di ottimizzazione

Per risolvere le problematiche di performance identificate, il modulo `dog_library` beneficerà delle seguenti ottimizzazioni:

1. **Implementazione del caching**:
   - Utilizzo del sistema di cache di Drupal per memorizzare le risorse
   - Prefetch di tutte le risorse in un momento separato dal rendering
   - Modificare i template per utilizzare sempre dati dalla cache, mai in tempo reale

2. **Modifiche al ResourceFetcher**:
   - Aggiornare il widget della library per leggere sempre dalla cache
   - Implementare cache tags appropriati per invalidare la cache quando necessario
   - Fornire feedback all'utente quando i dati non sono disponibili in cache

3. **Ottimizzazione JavaScript**:
   - Ridurre le chiamate AJAX duplicate
   - Implementare caching lato client dei dati già recuperati
   - Migliorare la gestione degli stati UI per evitare ricaricamenti non necessari

4. **Miglioramenti Views**:
   - Implementare caching dei risultati delle viste
   - Ottimizzare i display della vista per ridurre la quantità di dati richiesti
   - Supportare la paginazione efficiente

## Integrazione con il piano di ottimizzazione

Il modulo `dog_library` si integrerà con il piano di ottimizzazione generale tramite:

1. **Utilizzo del sistema di caching batch**:
   - Visualizzazione delle risorse dalla cache prefetch invece di chiamate in tempo reale
   - Feedback visivi quando la cache è vuota o non aggiornata

2. **Gestione degli errori**:
   - Gestione appropriata quando le risorse non sono disponibili in cache
   - Messaggi utente informativi sullo stato della cache

3. **UI Migliorata**:
   - Indicazione visiva dello stato della cache nella UI di selezione risorse
   - Opzioni per gli amministratori di forzare l'aggiornamento della cache se necessario

## Considerazioni per lo sviluppo

Quando si lavora con il modulo `dog_library`, tenere presente:

1. **Dipendenze**:
   - Richiede il modulo DOG principale con le sue ottimizzazioni di caching
   - Si basa sul supporto Views per Drupal

2. **JavaScript**:
   - Il file `resource_library.ui.js` gestisce l'interazione utente nella library
   - Il file `resource_library.widget.js` gestisce l'interazione con il widget del campo

3. **Templates**:
   - I template devono essere aggiornati per supportare la visualizzazione di dati dalla cache
   - Aggiungere gestione degli errori nei template per i casi in cui i dati non sono disponibili

## Conclusione

Il modulo `dog_library` è un componente cruciale per l'interazione degli editor con le risorse Omeka. Le ottimizzazioni proposte nel piano di lavoro risolveranno i problemi di performance attuali, garantendo un'esperienza utente fluida anche con un numero elevato di risorse.
