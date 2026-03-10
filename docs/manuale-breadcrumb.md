# Manuale redattore: Breadcrumb

Questo documento spiega come creare e gestire correttamente i contenuti affinché i breadcrumb (le "briciole di pane") vengano generati automaticamente nel sito.

## Come funziona

Il breadcrumb viene costruito automaticamente a partire dalla **struttura dell'URL** della pagina. L'URL viene generata dal sistema Pathauto secondo questo schema:

```
/asmsa/it/[voce-menu-genitore]/[titolo-pagina]
```

Ad esempio, la pagina "Bastimenti" sotto "Esplora l'Atlante" produce:
- URL: `/asmsa/it/esplora-latlante/bastimenti`
- Breadcrumb: **Home / Esplora l'Atlante / Bastimenti**

Perché funzioni correttamente, ogni pagina deve essere **collegata al menu principale** con la corretta gerarchia genitore-figlio.

---

## Creare una nuova pagina con breadcrumb corretto

### Passo 1: Creare il contenuto

1. Andare su **Contenuto > Aggiungi contenuto > Pagina base**
2. Compilare il campo **Titolo** (es. "Corsari e guerra di corsa")
3. Inserire il contenuto della pagina

### Passo 2: Collegare al menu principale

Questo passaggio determina il breadcrumb.

1. Nella barra laterale destra, espandere la sezione **Impostazioni del menu**
2. Spuntare **Fornisci un collegamento nel menu**
3. Compilare i campi:
   - **Titolo del collegamento**: il testo che apparirà nel menu (es. "Corsari e guerra di corsa")
   - **Elemento genitore**: selezionare la voce di menu sotto cui posizionare la pagina
     - Per le pagine tematiche: selezionare `-- Esplora l'Atlante`
     - Per le pagine di primo livello: selezionare `<Navigazione principale>`
   - **Peso**: determina l'ordine nel menu (numeri bassi = posizione alta)
4. Salvare la pagina

### Passo 3: Verificare il risultato

Dopo il salvataggio:
- L'URL viene generata automaticamente (es. `/asmsa/it/esplora-latlante/corsari-e-guerra-di-corsa`)
- Il breadcrumb mostrerà: **Home / Esplora l'Atlante / Corsari e Guerra di Corsa**

---

## Creare una sezione (voce di menu contenitore)

Per creare una nuova sezione di primo livello (come "Esplora l'Atlante") che raggruppa sotto-pagine:

### Opzione A: Sezione con pagina di atterraggio

1. Creare una **Pagina base** con il titolo della sezione
2. Nelle **Impostazioni del menu**, collegare al menu principale con genitore `<Navigazione principale>`
3. Spuntare **Mostra come espanso** se la voce deve mostrare i figli nel menu
4. Le pagine figlie useranno questa come genitore

### Opzione B: Sezione come dropdown senza pagina

1. Andare su **Struttura > Menu > Navigazione principale > Aggiungi collegamento**
2. Nel campo **Collegamento**, inserire `route:<nolink>` oppure `internal:#`
3. Compilare il **Titolo del collegamento** (es. "Nuova Sezione")
4. Spuntare **Mostra come espanso**
5. Salvare

> **Nota**: con l'Opzione B, nel breadcrumb il nome della sezione apparirà come testo non cliccabile. Se il testo contiene apostrofi o caratteri speciali che vengono persi nello slug URL, potrebbe essere necessario aggiungere una regola di sostituzione nella configurazione di Easy Breadcrumb (contattare l'amministratore del sito).

---

## Creare sotto-pagine (terzo livello)

Per pagine a tre livelli di profondità (es. Home > Sale, Saline, Salinieri > Le saline di Cagliari):

1. Creare la **Pagina base** "Le saline di Cagliari"
2. Nelle **Impostazioni del menu**, selezionare come **Elemento genitore**: `---- Sale, Saline, Salinieri`
3. Salvare

Il breadcrumb mostrerà: **Home / Esplora l'Atlante / Sale, Saline, Salinieri / Le Saline di Cagliari**

> **Nota**: la pagina genitore "Sale, Saline, Salinieri" deve avere **Mostra come espanso** attivo nelle impostazioni del menu, altrimenti i figli non appariranno nel menu a tendina.

---

## Regole importanti

### La pagina DEVE essere nel menu

Se una pagina non ha un collegamento nel menu principale, il breadcrumb mostrerà solo **Home / [Titolo pagina]** senza i livelli intermedi. Questo avviene perché l'URL viene generata dal pattern `[node:menu-link:parent]/[node:title]`: senza collegamento al menu, non c'è genitore e il percorso sarà piatto.

### Non modificare l'URL manualmente

L'URL viene generata automaticamente da Pathauto. Se si modifica manualmente l'alias URL, il breadcrumb potrebbe non corrispondere alla struttura del menu. In caso di necessità:

1. Andare su **Modifica** della pagina
2. Nella sezione **Alias URL**, spuntare **Genera alias URL automatico**
3. Salvare

### Spostare una pagina in un'altra sezione

Per cambiare la posizione di una pagina nel breadcrumb:

1. Modificare la pagina
2. Nelle **Impostazioni del menu**, cambiare l'**Elemento genitore**
3. Salvare
4. Rigenerare l'alias URL: nella sezione **Alias URL**, deselezionare e ri-selezionare **Genera alias URL automatico**, poi salvare

Dopo questa operazione, il vecchio URL continuerà a funzionare come redirect automatico.

### Titolo nel breadcrumb

Il titolo che appare nel breadcrumb corrisponde al **titolo della pagina** (campo Titolo del nodo). Se si desidera un testo diverso nel breadcrumb rispetto al titolo della pagina, è possibile compilare il campo **Breadcrumb title** (se disponibile nel tipo di contenuto).

---

## Riepilogo: checklist per il redattore

Per ogni nuova pagina, verificare:

- [ ] Il titolo della pagina è corretto
- [ ] La pagina ha un collegamento nel **menu principale**
- [ ] L'**Elemento genitore** nel menu corrisponde alla sezione desiderata
- [ ] L'**alias URL** è impostato su generazione automatica
- [ ] Dopo il salvataggio, il breadcrumb mostra il percorso corretto

---

## Struttura attuale del sito

```
Home
 └── Esplora l'Atlante (dropdown, non cliccabile)
      ├── Bastimenti
      ├── Colonie marittime
      ├── Corsari e guerra di corsa
      ├── Norme e istituzioni
      ├── Pesca e pescatori
      ├── Porti e scali
      ├── Rotte e portolani
      ├── Sale, Saline, Salinieri
      │    ├── Saline nel Medioevo sardo
      │    ├── Gestione e concessione delle saline
      │    ├── Mappare le saline sarde
      │    └── Le saline di Cagliari
      ├── Schiave e schiavi
      └── Torri costiere
```
