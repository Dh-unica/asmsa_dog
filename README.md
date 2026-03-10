<div align="center">

<img src="docs/images/cover.png" alt="Atlante digitale di Storia Marittima del Regno di Sardegna" width="100%" />

# Atlante digitale di Storia Marittima del Regno di Sardegna

### Patrimonio culturale digitale su mappe interattive e timeline

[![Sito Produzione](https://img.shields.io/badge/Sito-storia.dh.unica.it-0066cc?style=for-the-badge&logo=googlechrome&logoColor=white)](https://storia.dh.unica.it/asmsa/it)
[![Versione DOG](https://img.shields.io/badge/DOG-v2.0-green?style=for-the-badge)](docs/)
[![Licenza](https://img.shields.io/badge/Licenza-Open_Source-orange?style=for-the-badge&logo=opensourceinitiative&logoColor=white)](#licenza)

![Drupal](https://img.shields.io/badge/Drupal-10.1+-0678BE?style=flat-square&logo=drupal&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.11-003545?style=flat-square&logo=mariadb&logoColor=white)
![Bootstrap Italia](https://img.shields.io/badge/Bootstrap_Italia-2.6.0-06c?style=flat-square&logo=bootstrap&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker&logoColor=white)
![Node.js](https://img.shields.io/badge/Node.js-18+-339933?style=flat-square&logo=nodedotjs&logoColor=white)
![Leaflet](https://img.shields.io/badge/Leaflet-Maps-199900?style=flat-square&logo=leaflet&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-2.4-D22128?style=flat-square&logo=apache&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-Cache-DC382D?style=flat-square&logo=redis&logoColor=white)
![Webpack](https://img.shields.io/badge/Webpack-Build-8DD6F9?style=flat-square&logo=webpack&logoColor=black)
![CKEditor 5](https://img.shields.io/badge/CKEditor-5-0287D0?style=flat-square&logo=ckeditor4&logoColor=white)
![Drush](https://img.shields.io/badge/Drush-12.1+-success?style=flat-square)

---

*Un progetto del [DH UniCA](https://dh.unica.it) — Centro Interdipartimentale per l'Umanistica Digitale dell'Universita degli Studi di Cagliari*
*Direttore Prof. Giampaolo Salice*

</div>

---

## Il progetto

L'**Atlante digitale di Storia Marittima del Regno di Sardegna** (ASMSA) e un sito web per la valorizzazione del patrimonio culturale digitale legato alla storia marittima della Sardegna. Il progetto combina **mappe interattive**, **timeline**, documenti d'archivio, immagini, video e audio per consentire a ricercatori e cittadini di esplorare la storia marittima sarda attraverso la dimensione spaziale e temporale.

Le collezioni digitali — metadatate secondo standard internazionali (Dublin Core) — sono gestite sulla piattaforma **Omeka-S** e vengono integrate nel sito Drupal attraverso la suite di moduli **DOG**. I livelli cartografici storici provengono da server **GeoNode** e vengono sovrapposti alle mappe base, permettendo di visualizzare l'evoluzione del territorio nel tempo.

Il tema grafico e conforme al **Design System della Pubblica Amministrazione italiana** (linee guida AGID) grazie a Bootstrap Italia.

### Cosa offre il sito

- **Mappe interattive** con oggetti georiferiti provenienti dalle collezioni digitali Omeka-S
- **Timeline sincronizzate** che permettono di navigare gli eventi nel tempo e nello spazio contemporaneamente
- **Layer cartografici storici** (WMS) sovrapposti alle mappe, con visibilita legata all'arco temporale selezionato
- **Gallerie fotografiche** di documenti d'archivio, dipinti, fotografie storiche
- **Contenuti multimediali** (audio, video, documenti) collegati a punti sulla mappa
- **Pagine editoriali** composte liberamente dai redattori tramite il Layout Builder di Drupal

---

## DOG — La suite di moduli Drupal

**DOG** (**D**rupal + **O**meka-S + **G**eonode) e la suite di moduli custom che rende possibile l'integrazione tra le tre piattaforme. E il cuore tecnologico del progetto: un applicativo open source che permette di comporre pagine web in modo visuale, integrando livelli cartografici esposti con GeoNode e oggetti Omeka-S preventivamente metadatati, disponendoli anche su una linea temporale integrata con la mappa.

> *"Salire sulle spalle dei giganti"* — Invece di costruire da zero, DOG sfrutta le basi solide di tre piattaforme open source consolidate (Drupal, Omeka-S, GeoNode), ciascuna eccellenza nel proprio ambito, connettendole in un unico ecosistema.

DOG e riutilizzabile: qualsiasi istituzione culturale, universita o centro di ricerca puo adottarlo per costruire il proprio atlante digitale collegandolo alla propria istanza Omeka-S e ai propri geoserver.

### Le tre componenti integrate

**Drupal** — CMS open source (dal 2001). Gestisce il sito web, i contenuti e il layout delle pagine tramite il **Layout Builder**, un sistema drag-and-drop che consente ai redattori di comporre pagine personalizzate senza competenze tecniche.

**Omeka-S** — CMS per oggetti digitali (dal 2008). Piattaforma GLAM (Galleries, Libraries, Archives, Museums) per archiviare, gestire e presentare oggetti digitali con metadati strutturati secondo standard internazionali (Dublin Core). Le collezioni dell'Atlante sono ospitate su `storia.dh.unica.it/risorse`.

**GeoNode** — Server web di mappe geografiche (dal 2010). Piattaforma open source per dati geografici. DOG integra informazioni da GeoNode e da qualsiasi geoserver che esponga layer secondo il formato **WMS** (Web Map Service).

---

## Architettura e flusso dati

```mermaid
graph TB
    subgraph DRUPAL["🌐 Sito ASMSA (Drupal 10)"]
        direction TB
        subgraph EDITOR["📝 Composizione pagine"]
            LB["🧩 Layout Builder<br/><i>Drag & drop</i>"]
            HERO["🖼️ Hero / Galleria / Carousel"]
            MAP["🗺️ Blocco Omeka Map<br/><i>Leaflet + oggetti georiferiti</i>"]
            TIMELINE["📅 Blocco Mappa + Timeline<br/><i>Navigazione spazio-temporale</i>"]
        end
        subgraph DOG_MODULES["🐕 Suite DOG"]
            FETCHER["OmekaResourceFetcher<br/><i>Recupero risorse via REST</i>"]
            URLSVC["OmekaUrlService<br/><i>Gestione URL base API</i>"]
            VIEWBLD["OmekaResourceViewBuilder<br/><i>Rendering oggetti</i>"]
            LIBRARY["dog_library<br/><i>UI selezione risorse</i>"]
            CK5["dog_ckeditor5<br/><i>Embedding inline</i>"]
        end
        subgraph CACHE["⚡ DOG Cache"]
            REDIS[("🔴 Redis<br/><b>cache.omeka</b> bin")]
            ITEMS["omeka_resource:items:*"]
            FEATURES["omeka_geo_data:feature:*"]
        end
    end

    subgraph EXTERNAL["☁️ Servizi esterni"]
        OMEKA[("📦 Omeka-S<br/><i>REST API</i><br/>storia.dh.unica.it/risorse")]
        GEONODE["🌍 GeoNode<br/><i>WMS Server</i><br/>geonode.dh.unica.it"]
        WMS_EXT["🗺️ Altri server WMS<br/><i>Geoserver compatibili</i>"]
    end

    LB --> MAP
    LB --> TIMELINE
    LB --> HERO
    MAP --> DOG_MODULES
    TIMELINE --> DOG_MODULES
    HERO -.->|contenuti Drupal| LB
    FETCHER --> CACHE
    URLSVC --> FETCHER
    REDIS --- ITEMS
    REDIS --- FEATURES
    CACHE -->|cache miss / aggiornamento| OMEKA
    CACHE -->|"4 chiamate REST per item<br/>300-500ms ciascuna"| OMEKA
    MAP -->|Layer WMS| GEONODE
    MAP -->|Layer WMS| WMS_EXT
    TIMELINE -->|"Layer WMS con range temporale<br/>WMS layer start / end"| GEONODE

    style DRUPAL fill:#0678BE,color:#fff,stroke:#045a8d,stroke-width:2px
    style EDITOR fill:#e8f4f8,color:#333,stroke:#0678BE
    style DOG_MODULES fill:#fff3e0,color:#333,stroke:#e67e22
    style CACHE fill:#fce4ec,color:#333,stroke:#DC382D
    style EXTERNAL fill:#f1f8e9,color:#333,stroke:#4caf50
    style REDIS fill:#DC382D,color:#fff,stroke:#b71c1c
    style OMEKA fill:#6a1b9a,color:#fff,stroke:#4a148c
    style GEONODE fill:#2e7d32,color:#fff,stroke:#1b5e20
    style WMS_EXT fill:#558b2f,color:#fff,stroke:#33691e
    style LB fill:#0678BE,color:#fff
    style MAP fill:#199900,color:#fff
    style TIMELINE fill:#e67e22,color:#fff
    style HERO fill:#3498db,color:#fff
    style FETCHER fill:#f39c12,color:#fff
    style URLSVC fill:#f39c12,color:#fff
    style VIEWBLD fill:#f39c12,color:#fff
    style LIBRARY fill:#f39c12,color:#fff
    style CK5 fill:#f39c12,color:#fff
    style ITEMS fill:#ef9a9a,color:#333
    style FEATURES fill:#ef9a9a,color:#333
```

> **Come leggere il diagramma**: Il redattore compone le pagine tramite il Layout Builder, aggiungendo blocchi mappa e timeline. I moduli DOG recuperano gli oggetti dalla cache Redis; se la cache e vuota o in aggiornamento, le chiamate vengono inoltrate alle API REST di Omeka-S. I layer cartografici WMS vengono caricati direttamente da GeoNode.

### Il connettore Drupal — Omeka-S

Il cuore dell'integrazione e il modulo custom **`dog`** che funge da connettore tra Drupal e Omeka-S. La connessione viene configurata tramite l'interfaccia di amministrazione:

**`Amministrazione > Configurazione > Webservice > Drupal Omeka Geonode`**

dove si impostano:

- **Base URL** dell'istanza Omeka-S pubblicata su internet (es. `https://storia.dh.unica.it/risorse/`)
- **API Key Identity** (facoltativo, per servizi non pubblici)
- **API Key Credential** (facoltativo)

Il sistema verifica automaticamente la raggiungibilita della risorsa al salvataggio della configurazione. La funzione di browsing e ricerca nelle API avviene in tempo reale e riguarda solo gli item in stato pubblicato.

### Come funziona il flusso dati

Per ogni singolo elemento interrogato su Drupal sono necessarie **quattro chiamate REST separate** verso Omeka-S. Le API di Omeka richiedono dai 300 ai 500 ms per chiamata, a causa della complessa struttura relazionale del database.

#### Versione 1.0 — Chiamate in tempo reale

Nella prima versione, Drupal interrogava dinamicamente i database di Omeka e GeoNode ad ogni caricamento di pagina. Questo garantiva dati sempre aggiornati ma causava rallentamenti significativi oltre i 20 oggetti Omeka per pagina.

#### Versione 2.0 — Sistema di cache intelligente

La versione attuale introduce la **DOG Cache**, un layer di cache dedicato con le seguenti caratteristiche:

- **Interna a Drupal** e trasparente rispetto al livello superiore, garantendo piena compatibilita con le pagine create nella versione 1.0
- **Componente architetturale dedicato** basato su **Redis**, con alta scalabilita
- **Cache separata e autonoma** rispetto alle altre cache Drupal, con persistenza indipendente e invalidazione controllata
- **Aggiornamento manuale o schedulato**: i redattori decidono quando sincronizzare la cache con i dati presenti in Omeka

Due entita principali vengono messe in cache:

| Entita | Cache Key |
|--------|-----------|
| Item Omeka | `cache_omeka: omeka_resource:items:*` |
| Feature geografiche | `cache_omeka: omeka_geo_data:feature:*` |

L'aggiornamento avviene tramite il pannello di controllo DOG (`/admin/config/services/dog-settings`) con i pulsanti **"Update Items Cache Now"** e **"Update Features Cache Now"**, oppure tramite job schedulati (cron).

> Dai test effettuati, **150 oggetti vengono renderizzati in circa 900ms**. Il limite teorico e di **1500-2000 oggetti Omeka per pagina** con un caricamento asincrono sotto i 10 secondi.

---

## Blocchi contenuto disponibili

I redattori possono comporre le pagine dell'Atlante tramite il Layout Builder, aggiungendo diversi tipi di blocco:

| Blocco | Descrizione |
|--------|-------------|
| **Blocco base** | Editor WYSIWYG per testo formattato |
| **Carousel** | Carosello di immagini |
| **Galleria** | Griglia fotografica a 3 colonne con link interni/esterni |
| **Hero** | Banner visivo prominente con titolo, testo, CTA e immagine di sfondo |
| **Map** | Mappa Leaflet base |
| **Omeka Map** | Mappa con oggetti Omeka georiferiti, contenuti Drupal, media e layer WMS |
| **Omeka Map Timeline** | Mappa + timeline sincronizzata con oggetti posizionati per data (`dcterms:data`) |

### Blocco Omeka Map

Il blocco piu caratteristico di DOG. Consente di posizionare su una mappa Leaflet:

- **Oggetti Omeka-S** georiferiti, selezionati dal redattore tramite una finestra modale con ricerca e filtro per collection
- **Contenuti Drupal** interni al sito, referenziati tramite autocomplete
- **Media** (audio, documenti, immagini, video remoti) inseriti nella libreria del sito
- **Layer WMS** da server GeoNode o qualsiasi geoserver compatibile

### Blocco Omeka Map Timeline

Ha le stesse funzionalita del blocco mappa, con in aggiunta una **timeline** sotto la mappa. Gli oggetti vengono posizionati sulla timeline in base al valore `dcterms:data` indicato su Omeka. E possibile:

- Definire un **arco temporale** per ogni layer WMS (WMS layer start / WMS layer end)
- Navigare la timeline e vedere i **layer WMS cambiare automaticamente** in base al periodo selezionato
- Esplorare simultaneamente la dimensione **spaziale e temporale** dei dati

---

## Moduli custom DOG

Il codice della suite DOG risiede in `web/modules/custom/`:

| Modulo | Percorso | Funzione |
|--------|----------|----------|
| **dog** | `web/modules/custom/dog/` | Modulo principale. Cache dedicata, servizi (`OmekaResourceFetcher`, `OmekaUrlService`, `OmekaResourceViewBuilder`), field type/widget/formatter, Views plugin, form di configurazione |
| **dog_library** | `web/modules/custom/dog/modules/dog_library/` | Integrazione Layout Builder e UI di selezione risorse Omeka |
| **dog_ckeditor5** | `web/modules/custom/dog/modules/dog_ckeditor5/` | Plugin CKEditor 5 per embedding inline di risorse Omeka |
| **omeka_utils** | `web/modules/custom/omeka_utils/` | Utility API legacy (in dismissione progressiva) |

---

## Stack tecnologico

| Componente | Tecnologia | Versione |
|------------|------------|----------|
| CMS | Drupal | 10.1+ |
| Runtime | PHP | 8.2 |
| Database | MariaDB | 10.11 |
| CLI Drupal | Drush | 12.1+ |
| Build frontend | Node.js + Webpack | 18+ |
| Tema | Bootstrap Italia | 2.6.0 |
| Mappe | Leaflet | - |
| Cache | Redis | - |
| Container | Docker Compose | - |
| Web server | Apache | 2.4 |
| Editor | CKEditor | 5 |

---

## Conformita PA italiana

Il sito utilizza il tema **Bootstrap Italia**, sviluppato secondo le [linee guida AGID](https://docs.italia.it/italia/designers-italia/design-linee-guida-docs/it/stabile/index.html) per i siti della Pubblica Amministrazione italiana. Il tema e stato personalizzato per adattarsi alle esigenze dell'Atlante e ai componenti DOG (blocchi mappa, timeline, selettore risorse Omeka).

---

## Documentazione

| Documento | Descrizione |
|-----------|-------------|
| [Guida all'uso DOG v2.0](docs/DOG%20DH%20UNICA%20-%20GUIDA%20ALL'USO%20-%20DOCUMENTAZIONE%20V.2.0.pdf) | Documentazione completa: architettura, componenti, istruzioni per i redattori, FAQ |
| [Guida breadcrumb](docs/manuale-breadcrumb.md) | Configurazione navigazione breadcrumb |

---

## Licenza

Progetto **open source** sviluppato dal [DH UniCA](https://dh.unica.it) — Centro Interdipartimentale per l'Umanistica Digitale dell'Universita degli Studi di Cagliari.

---

<div align="center">

*Sviluppato con passione per le Digital Humanities*

**DH UniCA** | Via Is Mirrionis 1 - 09127 Cagliari (Italy)

</div>
