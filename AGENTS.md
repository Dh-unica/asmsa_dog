# AGENTS.md

AI coding guide for ASMSA DOG Drupal project.

## AI Response Requirements

**Communication style:**
- Code over explanations - provide implementations, not descriptions
- Be direct, skip preambles
- Assume Drupal expertise - no over-explaining basics
- Suggest better approaches with code
- Show only changed code sections with minimal context
- Complete answers in one response when possible
- Use Drupal APIs, not generic PHP
- Ask if requirements are ambiguous

**Response format:**
- Production-ready code with imports/dependencies
- Inline comments explain WHY, not WHAT
- Include full file paths
- Proper markdown code blocks

## Project Overview

- **Platform**: Drupal 10.1+ single site
- **Context**: Sito patrimonio culturale digitale (ASMSA - Archivio Storico Minerario della Sardegna). Integra Omeka-S via API REST per visualizzare collezioni digitali su mappe e timeline.
- **Architecture**: Drupal monolitico con modulo custom `dog` (Drupal Omeka Geonode) per integrazione API Omeka-S. Tema Bootstrap Italia per conformità design system PA italiana.
- **Security**: Standard (public-facing cultural heritage site)
- **Languages**: Multilingual: italiano (it), inglese (en)
- **Custom Entities**: Nessuna entità custom
- **Role System**: Standard Drupal (anonymous, authenticated, admin)
- **Use Cases**: Visualizzazione collezioni digitali su mappe Leaflet e timeline, navigazione patrimonio culturale, integrazione dati GIS via GeoNode

## Date Verification Rule

**CRITICAL**: Before writing dates to `.md` files, run `date` command first.
Never use example dates (e.g., "2024-01-01") - always use actual system date.

## Git Workflow

**Branches**: `master` (prod), `feature/*`, `bugfix/*`, `hotfix/*`

**Flow**: `master` → `feature/name` → PR → merge to `master`

**Hotfix**: `master` → `hotfix/name` → merge to `master`, tag release

**Commit format**: `[type]: description` (max 50 chars)
Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `config`

**Before PR**: Run `phpcs`, `phpstan`, tests, `make drush "cex"`

**Don'ts**: No direct commits to master, no `--force` on shared branches, no credentials in code

**.gitignore essentials**:
```gitignore
web/core/
web/modules/contrib/
web/themes/contrib/
vendor/
web/sites/*/files/
web/sites/*/settings.local.php
node_modules/
.env
```

## Development Environment

**Web root**: `web/`

This project has **two environments**: Docker Compose (primary, via Makefile) and DDEV. Commands are shown for both.

### Setup (Docker Compose - Primary)
```bash
git clone https://github.com/Unica-dh/asmsa_dog && cd asmsa_dog
make up                              # Pull images + start containers
make composer "install"              # Install dependencies
make drush "cr"                      # Cache rebuild
```

### Setup (DDEV - Alternative)
```bash
ddev start
ddev composer install
ddev drush cr
```

### Drush Commands

```bash
# Docker Compose (primary)                    # DDEV (alternative)
make drush "status"                            # ddev drush status
make drush "cr"                                # ddev drush cr
make drush "cex"                               # ddev drush cex
make drush "cim"                               # ddev drush cim
make drush "updb"                              # ddev drush updb
make drush "watchdog:show"                     # ddev drush watchdog:show
make drush "upwd admin admin"                  # ddev drush upwd admin admin

# Database & PHP eval (Docker Compose)
make drush "sql:query \"SELECT * FROM node_field_data LIMIT 5;\""
make drush "php:eval \"echo 'Hello World';\""

# Test services and entities
make drush "php:eval \"var_dump(\Drupal::hasService('entity_type.manager'));\""
make drush "php:eval \"var_dump(\Drupal::config('system.site')->get('name'));\""
```

### Composer
```bash
# Docker Compose (primary)                    # DDEV (alternative)
make composer "outdated 'drupal/*'"            # ddev composer outdated 'drupal/*'
make composer "update drupal/mod --with-deps"  # ddev composer update drupal/mod --with-deps
make composer "require drupal/module"          # ddev composer require drupal/module
```

### Docker Commands (Makefile)
```bash
make up                          # Avvia container (pull + up)
make stop                        # Ferma container
make prune                       # Rimuovi container e volumi
make shell                       # Shell nel container PHP
make logs                        # Log container (opzionale: make logs php)
make ps                          # Lista container attivi
```

### Environment Variables
Store in `.env` (gitignored). Restart containers after changes.

### Patches
Structure: `./patches/{core,contrib/[module],custom}/`

In `composer.json` → `extra.patches`:
```json
"drupal/module": {"#123 Fix": "patches/contrib/module/fix.patch"}
```
Sources: local files, Drupal.org issue queue, GitHub PRs
Always include issue numbers in descriptions. Monitor upstream for merged patches.

## Code Quality Tools

```bash
# PHPStan - static analysis (in container)
make shell
vendor/bin/phpstan analyze web/modules/custom --level=1

# PHPCS - coding standards check
vendor/bin/phpcs --standard=Drupal web/modules/custom/

# PHPCBF - auto-fix coding standards
vendor/bin/phpcbf --standard=Drupal web/modules/custom/
```

**Config files**: `phpstan.neon`, `phpcs.xml`, `rector.php`
**Run before**: commits, PRs, Drupal upgrades

## Testing

```bash
# PHPUnit (in container via make shell)
vendor/bin/phpunit web/modules/custom
vendor/bin/phpunit web/modules/custom/dog/tests/src/Unit/MyTest.php
vendor/bin/phpunit --coverage-html coverage web/modules/custom

# Debug failed tests
vendor/bin/phpunit --testdox --verbose [test-file]
```

**Drupal test types** (in `tests/src/`): `Unit/` (isolated), `Kernel/` (minimal bootstrap), `Functional/` (full Drupal), `FunctionalJavascript/`

## Debugging

```bash
# Container & DB access
make shell                                       # PHP container
make logs                                        # Container logs

# Xdebug (dev compose on port 7001)
# Enabled via docker-compose-dev.yml

# Logs
make drush "watchdog:show --count=50 --severity=Error"

# State
make drush "state:get [key]"
make drush "state:set [key] [value]"

# PhpMyAdmin (dev only)
# Available at localhost:8080
```

**IDE**: VS Code (PHP Debug extension, port 9003 via docker-compose-dev.yml)
**Tips**: Twig debug in `development.services.yml`

**Ports**: Apache `localhost:6000` (prod), `localhost:7001` (dev with Xdebug). PhpMyAdmin dev `:8080`.

## Performance

```bash
# Cache
make drush "cr"                                  # Rebuild all
make drush "cache:clear render"                  # Specific bin

# DB performance
make shell
mysql -e "SELECT table_name, round(((data_length+index_length)/1024/1024),2) 'MB' FROM information_schema.TABLES WHERE table_schema=DATABASE() ORDER BY (data_length+index_length) DESC;"
```

**Optimization**: Page cache + dynamic page cache, CSS/JS aggregation (AdvAgg installed), CDN module enabled, Warmer module for cache pre-warming, image styles with lazy loading

## Code Standards

### Core Principles

- **SOLID/DRY**: Follow SOLID principles, extract repeated logic
- **PHP 8.1+**: Use strict typing: `declare(strict_types=1);`
- **Drupal Standards**: PSR-12 based, English comments only

### Module Structure

Location: `/web/modules/custom/dog_[module_name]/`
Naming: `dog_[descriptive_name]` - project prefix prevents conflicts with contrib

```
dog_[module_name]/
├── dog_[module_name].{info.yml,module,install,routing.yml,permissions.yml,services.yml,libraries.yml}
├── src/                          # PSR-4: \Drupal\dog_[module_name]\[Subdir]\ClassName
│   ├── Entity/                   # Custom entities
│   ├── Form/                     # Forms (ConfigFormBase, FormBase)
│   ├── Controller/               # Route controllers
│   ├── Plugin/{Block,Field/FieldWidget,Field/FieldFormatter}/
│   ├── Service/                  # Custom services
│   └── EventSubscriber/          # Event subscribers
├── templates/                    # Twig templates
├── css/ & js/                    # Assets
```

**PSR-4**: `src/Form/MyForm.php` → `\Drupal\dog_mymodule\Form\MyForm`

### Entity Development Patterns

```php
// 1. Enums instead of magic numbers
enum EntityStatus: string {
  case Draft = 0;
  case Published = 1;
}

// 2. Getter methods instead of direct field access
public function getStatus(): int {
  return (int) $this->get('status')->value;
}

// 3. Safe migrations with backward compatibility
function dog_update_XXXX() {
  $manager = \Drupal::entityDefinitionUpdateManager();
  $field = $manager->getFieldStorageDefinition('field_name', 'entity_type');
  if ($field) {
    $new_def = BaseFieldDefinition::create('field_type')->setSettings([...]);
    $manager->updateFieldStorageDefinition($new_def);
    drupal_flush_all_caches();
    \Drupal::logger('dog')->info('Migration completed.');
  }
}
```

**Migration safety**: Backup DB, test on staging, ensure backward compatibility, log changes, have rollback plan.

### Drupal Best Practices

```php
// Database API - always use placeholders, never raw SQL
$query = \Drupal::database()->select('node_field_data', 'n')
  ->fields('n', ['nid', 'title'])->condition('status', 1)->range(0, 10);
$results = $query->execute()->fetchAll();

// Dependency Injection - avoid \Drupal:: static calls in classes
class MyService {
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}
}

// Caching - use tags and contexts (project uses cache.omeka bin)
$build = [
  '#markup' => $content,
  '#cache' => ['tags' => ['node:' . $nid], 'contexts' => ['user'], 'max-age' => 3600],
];
\Drupal\Core\Cache\Cache::invalidateTags(['node:' . $nid]);
\Drupal::cache('omeka')->set($cid, $data, time() + 3600, ['dog']);
```

```php
// Queue API - for heavy operations
$queue = \Drupal::queue('dog_processor');
$queue->createItem(['data' => $data]);
// QueueWorker plugin: @QueueWorker(id="...", cron={"time"=60})

// Entity System - always use entity type manager
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($nid);
$query = $storage->getQuery()
  ->condition('type', 'article')->condition('status', 1)
  ->accessCheck(TRUE)->sort('created', 'DESC')->range(0, 10);
$nids = $query->execute();

// Form API - extend FormBase, implement getFormId(), buildForm(), validateForm(), submitForm()
$form['field'] = ['#type' => 'textfield', '#title' => $this->t('Name'), '#required' => TRUE];
$form_state->setErrorByName('field', $this->t('Error message.'));

// Translation - always use t() for user-facing strings
$this->t('Hello @name', ['@name' => $name]);

// Config API
$config = \Drupal::config('dog.settings')->get('key');
\Drupal::configFactory()->getEditable('dog.settings')->set('key', $value)->save();

// Permissions
user_role_grant_permissions($role_id, ['permission']);
user_role_revoke_permissions($role_id, ['permission']);
```

### Code Style

- Type declarations/hints required, PHPDoc for classes/methods
- Align `=>` in arrays, `=` in variable definitions
- Controllers: final classes, DI, keep thin
- Services: register in `services.yml`, single responsibility
- Logging: `\Drupal::logger('dog')->notice('message')`
- Entity updates: always use update hooks in `.install`, maintain backward compatibility

## Directory Structure

**Key paths**: `/web/`, `/web/modules/custom/`, `/web/sites/default/config/` (config directory), `/web/sites/default/settings.php`, `/patches/`, `/tests/`

**Note**: Config directory is `web/sites/default/config/` (NOT the default `config/sync/`)

**Development paths**: routes → `routing.yml`, forms → `src/Form/`, entities → `src/Entity/`, permissions → `permissions.yml`, updates → `.install`

## Multilingual Configuration

```bash
# Languages already configured: Italian (it) and English (en)
make drush "locale:check"
make drush "locale:update"
```

**Detection**: Configure at `/admin/config/regional/language/detection` - use URL prefix (`/en/`, `/it/`) for SEO

**Custom entities**: Add `translatable = TRUE` to `@ContentEntityType`, use `->setTranslatable(TRUE)` on fields

**In code**: `$this->t('Hello @name', ['@name' => $name])` | **In Twig**: `{{ 'Hello'|trans }}`

**Common issues**: Missing translations → `locale:update`, content not translatable → check Language settings tab

## Configuration Management

```bash
make drush "cex"                  # Export config
make drush "cim"                  # Import config
make drush "config:status"        # Show differences
```

**Config directory**: `web/sites/default/config/`

## Security

**Principles**: HTTPS required, sanitize input, use DB abstraction (no raw SQL), env vars for secrets, proper access checks

```bash
# Security updates
make drush "pm:security"
make composer "update drupal/core-recommended --with-dependencies"

# Audit
make drush "role:perm:list"
make drush "watchdog:show --severity=Error --count=100"
```

**Hardening**: `chmod 444 settings.php`, `chmod 755 sites/default/files`, disable PHP in files dir

**Code**: Use placeholders in queries, `Html::escape()` for output, `$account->hasPermission()` for access, Form API for validation

## SEO & Structured Data

### Installed Modules

Modules already installed: `metatag`, `pathauto`, `simple_sitemap`, `easy_breadcrumb`

```bash
make drush "simple-sitemap:generate"    # Generate sitemap at /sitemap.xml
make drush "pathauto:generate"          # Generate URL aliases
```

### Schema.org & Open Graph

Configure at `/admin/config/search/metatag/global`:
- **Organization**: `@type: Organization`, name, url, logo, sameAs
- **Article**: `@type: Article`, headline `[node:title]`, datePublished, author, image
- **Open Graph**: og:title, og:description, og:image, og:url

### SEO Checklist

On-page: title tags (50-60 chars), meta descriptions (150-160), H1 unique, clean URLs, alt attributes
Technical: sitemap submitted, robots.txt, canonical URLs, Schema.org, HTTPS, Core Web Vitals
Multilingual: hreflang tags, language-specific sitemaps, canonical per language

## Frontend Development

**JS Aggregation Issues**: Missing `.libraries.yml` deps, wrong load order, `drupalSettings` unavailable → Add deps (`core/jquery`, `core/drupal`, `core/drupalSettings`, `core/once`), use `once()` not `.once()`, test with aggregation enabled

**CSS**: BEM naming, organize by component

<!--
THEME DISCOVERY FOR AI/LLM:
1. Active theme: italiagov (child of bootstrap_italia 2.6.0)
2. Config: web/themes/custom/italiagov/italiagov.info.yml
3. Hooks: web/themes/custom/italiagov/italiagov.theme
4. Components namespace: italiagov_components → components/
5. Build: Webpack (npm run build:prod)

Theme files: italiagov.info.yml (definition), italiagov.libraries.yml (assets), italiagov.theme (hooks), templates/ (Twig), components/ (SDC namespace)
-->

**Theme location**: `/web/themes/custom/italiagov/`
**Base theme**: Bootstrap Italia 2.6.0
**Build system**: Webpack

### Build Commands

```bash
cd web/themes/custom/italiagov
npm install                       # Setup
npm run build:prod                # Build produzione
npm run build:dev                 # Build sviluppo
npm run watch:dev                 # Watch mode
```

### Libraries (`italiagov.libraries.yml`)

```yaml
global:
  css: { theme: { css/style.css: {} } }
  js: { js/global.js: {} }
  dependencies: [core/drupal, core/jquery, core/drupalSettings, core/once]
```

### Twig Templates

**Enable debugging** (`development.services.yml`): `twig.config: { debug: true, auto_reload: true, cache: false }`

**Naming**: `node--[type]--[view-mode].html.twig`, `paragraph--[type].html.twig`, `block--[type].html.twig`, `field--[name]--[entity].html.twig`

**Override**: Enable debug → view source for suggestions → copy from core/themes → place in templates/ → `make drush "cr"`

**Template directory structure**:
```
templates/
├── block/           # Block templates (incl. omeka-map, omeka-map-timeline)
├── content/         # Node templates
├── field/           # Field templates
├── form/            # Form element templates
├── layout/          # Layout templates
├── misc/            # Miscellaneous templates
├── navigation/      # Menu and navigation
├── paragraph/       # Paragraph templates
└── views/           # Views templates
```

### Preprocess Functions (`italiagov.theme`)

Current preprocess functions in the theme:
```php
function italiagov_preprocess_dog_omeka_resource(array &$variables) {
  // Preprocessing for Omeka resource rendering
}
function italiagov_preprocess_block(&$variables) {
  // Block preprocessing
}
```

### Troubleshooting

```bash
rm -rf node_modules package-lock.json && npm install   # Reset deps
rm -rf dist/                                            # Clear build cache
```

**Performance**: Minify for prod, critical CSS, font-display:swap, CSS/JS aggregation, AdvAgg module

## Documentation

**MANDATORY**: Document work in "Tasks and Problems" section. Use real date (`date` command). Document: modules, fixes, config changes, optimizations, problems/solutions.

## Common Tasks

### New Module
```bash
# Create /web/modules/custom/dog_[name]/ with:
# - dog_[name].info.yml (name, type:module, core_version_requirement:^10||^11, package:Custom)
# - dog_[name].module (hooks), .routing.yml, .permissions.yml, .services.yml as needed
make drush "pm:enable dog_[name]"
make drush "cr"
```

### Update Core
```bash
# Backup first
./scripts/backup-remote.sh
make composer "update drupal/core-recommended drupal/core-composer-scaffold --with-dependencies"
make drush "updb"
make drush "cr"
# Or use the update script:
./scripts/update-drupal.sh
```

### Database Migration
```php
// In dog.install
function dog_update_10001() {
  // Use EntityDefinitionUpdateManager for field changes
  // Check field exists, update displays, log completion
  drupal_flush_all_caches();
}
```

### Tests
```bash
# In container (make shell)
vendor/bin/phpunit web/modules/custom/dog/tests
# Dirs: tests/src/Unit/, Kernel/, Functional/
```

### Permissions
```bash
make drush "role:perm:list [role]"
# PHP: user_role_grant_permissions($role_id, ['perm1']); drupal_flush_all_caches();
```

### Deploy
```bash
./scripts/deploy.sh              # Deploy to production
./scripts/backup-remote.sh       # Backup remote DB
./scripts/update-drupal.sh       # Update Drupal
```

## Troubleshooting

### Quick Fixes
```bash
make drush "cr"                                      # Clear cache
make stop && make up                                 # Restart containers
make drush "watchdog:show --count=50"                # Check logs
```

### Cache Not Clearing
```bash
make drush "cr"                                                     # Standard
make shell -c "rm -rf web/sites/default/files/php/twig/*"          # Twig
make drush "sql:query \"TRUNCATE cache_render;\""                   # Nuclear
make drush "cr"
```

### Database Issues
```bash
make drush "sql:cli"                       # Check connection
make drush "updb"                          # Pending updates
make drush "entity:updates"                # Entity schema updates
```

### Container Issues
```bash
make stop && make up                       # Restart
make prune && make up                      # Recreate containers + volumes
make logs                                  # View logs
```

### Module Installation
```bash
make composer "why-not drupal/[module]"    # Check deps
make composer "require drupal/[module]"
make drush "pm:enable [module]"
make drush "updb"
make drush "cr"
```

### WSOD (White Screen)
```bash
make drush "config:set system.logging error_level verbose"
make logs
make drush "watchdog:show --count=50"
```

### Config Import Fails
```bash
make drush "config:status"                             # Check status
make drush "config:set system.site uuid [correct-uuid]" # UUID mismatch
```

## Additional Resources

- **Project Documentation**: `CLAUDE.md`, `refactory_dog.md`
- **User Guide PDF**: `docs/DOG DH UNICA - GUIDA ALL'USO - DOCUMENTAZIONE V.2.0.pdf`
- **Drupal Documentation**: https://www.drupal.org/docs

---

<!--
===========================================
HOW TO DISCOVER FULL ENTITY STRUCTURE - GUIDE FOR AI/LLM
===========================================

Use this guide to discover the complete entity structure of a Drupal project.
Config directory for this project: `web/sites/default/config/`

**Entity Type Config File Patterns:**

| Entity Type | Config File Pattern | Example |
|-------------|---------------------|---------|
| Content Types | `node.type.*.yml` | `node.type.article.yml` |
| Field Storage | `field.storage.*.yml` | `field.storage.node.field_image.yml` |
| Field Instance | `field.field.*.yml` | `field.field.node.article.field_image.yml` |
| Paragraph Types | `paragraphs.paragraphs_type.*.yml` | `paragraphs.paragraphs_type.text.yml` |
| Media Types | `media.type.*.yml` | `media.type.image.yml` |
| Taxonomy Vocabularies | `taxonomy.vocabulary.*.yml` | `taxonomy.vocabulary.tags.yml` |

**Commands to list config files:**
```bash
ls web/sites/default/config/node.type.*.yml
ls web/sites/default/config/paragraphs.paragraphs_type.*.yml
ls web/sites/default/config/field.storage.*.yml
```

**Drush commands:**
```bash
make drush "entity:bundle-info node"
make drush "entity:bundle-info paragraph"
make drush "field:list node article"
```
-->

## Drupal Entities Structure

Complete reference of content types, media types, taxonomies, and paragraph types.

### Content Types (Node Bundles)

```
content_types[2]{machine_name, label, description}:
  article    - Article   - News and blog posts
  page       - Page      - Static pages (used for ASMSA content organized under "esplora l'atlante" hierarchy)
```

### Paragraph Types (24)

```
paragraph_types:
  layout[6]:       accordion, accordion_item, section, carousel, slide, hero
  content[6]:      content, citation, callout, attachments, node_reference, webform
  omeka_gis[6]:    omeka_gallery, omeka_map, omeka_map_timeline, map, geonode_wms, geonode_wms_timeline
  media[5]:        gallery, gallery_item, drupal_gallery, timeline, timeline_item
  config[1]:       settings
```

### Media Types (5)

```
media_types[5]{machine_name, label}:
  audio          - Audio
  document       - Document
  image          - Image
  remote_video   - Remote Video
  video          - Video
```

### Taxonomy Vocabularies (1)

```
taxonomies[1]{machine_name, label}:
  tags - Tags (content tagging)
```

### Entity Relationships

- **Page** → **Paragraphs** (one-to-many, used for rich page content with Omeka/GIS embeds)
- **Article** → **Tags** (many-to-many via `field_tags`)
- **Paragraph (omeka_map)** → **Omeka API** (external reference via collection/resource IDs)
- **Paragraph (geonode_wms)** → **GeoNode** (external WMS layer reference)

### Field Patterns

**Common field naming patterns in this project**:

- `field_[name]` - Standard field prefix
- Base fields: `title`, `body`, `created`, `changed`, `uid`, `status`

**Key Field Types**:
- Reference fields: `entity_reference`, `entity_reference_revisions` (paragraphs)
- Text fields: `string`, `text_long`, `text_with_summary`
- Geo fields: `geofield` (coordinates for map display)
- Structured: `link`, `address`, `color_field`

### View Modes

**Node View Modes**:
- `full` - Full content display
- `teaser` - Summary/card display

### Entity Access Patterns

- View: `access content` | Edit own: `edit own [type] content` | Delete own: `delete own [type] content` | Admin: `administer nodes`

## Custom Modules Reference

### dog (`web/modules/custom/dog/`)
Main module "Drupal Omeka Geonode". Core services:
- `OmekaResourceFetcher` - Recupero risorse da API Omeka-S
- `OmekaUrlService` - Gestione URL base API Omeka
- `OmekaResourceViewBuilder` - Rendering risorse Omeka
- `SettingsForm` - Form configurazione (`/admin/config/dog/settings`)
- Cache bin dedicata: `cache.omeka`
- Views plugin: filtri per collection, argomenti per resource type
- Event subscriber per query risorse

### dog_library (`web/modules/custom/dog/modules/dog_library/`)
Integrazione Layout Builder, UI selezione risorse Omeka nel backend.

### dog_ckeditor5 (`web/modules/custom/dog/modules/dog_ckeditor5/`)
Plugin CKEditor 5 per embedding risorse Omeka inline nei contenuti.
Plugin path: `js/ckeditor5_plugins/{nome}/src/`, entry point `index.js`, estendere `Plugin`.

### dog_utils (`web/modules/custom/dog_utils/`)
Modulo utility di supporto.

### omeka_stats_block (`web/modules/custom/omeka_stats_block/`)
Blocco statistiche Omeka.

### omeka_utils (`web/modules/custom/omeka_utils/`)
Utility API legacy - **in fase di dismissione**. Il modulo `dog` lo bypassa progressivamente.

## Omeka-S Integration

**API Base URL**: `https://<base_url>/api/` (es. `http://storia.dh.unica.it/risorse/api/`)

**Data flow**: Omeka API → `OmekaResourceFetcher` → `cache.omeka` bin → Template rendering

**Key templates**:
- `block--omeka-map.html.twig` - Mappa con oggetti Omeka (coordinate, titolo, immagini)
- `block--omeka-map-timeline.html.twig` - Mappa + timeline (date inizio/fine eventi)
- `dog_library/templates/` - UI selezione risorse nel backend

**IMPORTANTE**: I dati Omeka devono sempre passare per la cache (`cache.omeka` bin), mai chiamate API live nei template.

**Performance issue**: Chiamate API sincrone rallentano con >20 oggetti. Soluzione in corso: servizio batch di pre-caricamento cache (cron + bottone manuale).

## Refactoring in Corso

Vedi `refactory_dog.md` per lo stato completo:
1. Bypass di `omeka_utils` per `omeka_map` (completato)
2. Bypass di `omeka_utils` per `omeka_map_timeline` (da fare)
3. Fix JS visualizzazione mappa
4. Verifica funzionamento Layout Builder + selezione oggetti Omeka
5. Crawler batch per pre-caricamento cache

## GIS & Mapping

**Moduli installati** (tutti in composer.json, ma attualmente **disabilitati** in core.extension):
- `drupal/leaflet` ^10.2 - Mappe Leaflet
- `drupal/geofield` ^1.62 - Field type per dati geografici
- `drupal/geofield_map` ^11.0 - Widget mappa per geofield
- `drupal/geocoder` ^4.25 - Geocoding (Google Maps, GeoIP2 providers)
- `drupal/geolocation` ^3.14 - Geolocalizzazione

**Nota**: Le mappe Omeka usano Leaflet direttamente via libreria JS del tema (`italiagov/leaflet`), non tramite i moduli contrib Drupal.

**Template mappe** (in `web/themes/custom/italiagov/tempates/`):
- `block--omeka-map.html.twig` - Librerie: `italiagov/leaflet`, `italiagov/timelinejs`, `italiagov/remotevideopopup`
- `block--omeka-map-timeline.html.twig` - Stesse librerie, aggiunge timeline
- `block--omeka-gallery.html.twig` - Galleria Omeka
- `block--drupal-gallery.html.twig` - Galleria Drupal nativa

## Layout Builder

Il progetto usa Layout Builder con moduli aggiuntivi per l'editing:
- `layout_builder_restrictions` - Restrizioni su blocchi/layout disponibili
- `layout_builder_modal` / `layout_builder_iframe_modal` - UI modale per editing
- `layout_builder_at` - Asymmetric translations per Layout Builder

**dog_library** (`web/modules/custom/dog/modules/dog_library/`) fornisce:
- `ResourceLibraryUiBuilder` - Costruisce l'UI di selezione risorse Omeka
- `OpenerResolver` - Risolve opener per la library
- `ResourceLibraryFieldWidgetOpener` - Opener per field widget

**Cron**: Layout Builder ha un cron job dedicato (ogni 15 minuti via simple_cron).

## UI Patterns

Moduli installati: `drupal/ui_patterns` ^1.7, `drupal/ui_patterns_settings` ^2.0@RC

Configurazione attuale: `ui_patterns_settings.settings.yml` con `mapping: null` (nessun pattern custom configurato).

Il tema `italiagov` usa il namespace `italiagov_components` → `components/` per i componenti, ma si appoggia principalmente ai pattern del tema base Bootstrap Italia.

## Webform

Modulo `drupal/webform` ^6.2@beta installato. Paragraph type `webform` disponibile per embedding nei contenuti.

**Webform esistenti**:
- `contact` ("Contatto") - Form di contatto con campi: name, email, subject, message
  - Handler: email_confirmation + email_notification (entrambi attivi)
  - Accesso: anonymous e authenticated
  - Conferma: redirect a home con messaggio

**Opzioni preconfigurate**: 24 set standard (mesi, giorni, nazioni, province, istruzione, etc.)

## Queue & Cron

**Simple Cron** (`drupal/simple_cron` ^1.1@beta) gestisce 16 job schedulati:

| Job | Frequenza | Note |
|-----|-----------|------|
| warmer | `*/15 * * * *` | Pre-warming cache (ogni 15 min) |
| layout_builder | `*/15 * * * *` | Pulizia layout (ogni 15 min) |
| backup_migrate | cron default | Backup automatici |
| simple_sitemap | cron default | Rigenerazione sitemap |
| advagg | cron default | Ottimizzazione CSS/JS |
| webform | cron default | Pulizia submission |
| locale, search, system, etc. | cron default | Job standard Drupal |

**Config**: `max_execution_time: 600s`, `lock_timeout: 900s`

**Queue UI** (`drupal/queue_ui` ^3.1): Interfaccia admin per monitoraggio code a `/admin/config/system/queue-ui`.

**Nota**: Nessun QueueWorker custom è attualmente implementato nei moduli `dog`. Il crawler batch per pre-caricamento cache Omeka (vedi Refactoring) userà probabilmente il Queue API.

## Google Tag / Analytics

**Modulo**: `drupal/google_tag` ^2.0

**Container GA4**: `G-KF9DEFNFWK`

**Eventi configurati**: generate_lead, login, webform_purchase, search, custom, sign_up
- Login/sign_up: metodo "CMS"
- Lingua: Italian (it)

**Admin**: `/admin/config/services/google-tag`

## Development Workflow

- Document all significant changes in "Tasks and Problems" section below
- Follow the format and examples provided
- Review existing entries before making architectural changes
- Always run `date` command to get current date before adding entries

---

## Tasks and Problems Log

**Format**: `YYYY-MM-DD | [TYPE] Description` — Types: TASK, PROBLEM/SOLUTION, CONFIG, PERF, SECURITY, NOTE

Run `date` first. Add new entries at top. Include file paths, module names, config keys.

```
[Add entries here - newest first]

Examples:
2024-01-15 | TASK: Created custom module dog_feature for special workflow
2024-01-15 | PROBLEM: Config import failing with UUID mismatch
          | SOLUTION: make drush "config:set system.site uuid [correct-uuid]"
2024-01-14 | CONFIG: Enabled CSS/JS aggregation and AdvAgg module
2024-01-13 | NOTE: Custom entity queries must include ->accessCheck(TRUE/FALSE)
```
