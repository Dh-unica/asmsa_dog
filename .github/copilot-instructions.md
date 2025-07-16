# AI Agent Instructions for ASMSA Dog Project

## Project Overview
This is a Drupal 10 project using Docker for local development, with custom modules for Omeka integration and CKEditor 5 plugins. The project follows a modular architecture with specific emphasis on media handling and editor customization.

## Key Components

### 1. Development Environment
- Docker-based local setup (`docker-compose.yml`)
- PHP 8.x with Composer 2.5.8+
- MariaDB for database
- Node.js for theme compilation

### 2. Custom Modules
#### dog_ckeditor5
- Location: `web/modules/custom/dog/modules/dog_ckeditor5/`
- Custom CKEditor 5 plugins for enhanced editing capabilities
- Key file: `drupalomekaresourceui.js` - implements UI for Omeka resource integration
- Plugin build process uses Yarn/Webpack (see `webpack.config.js`)

#### omeka_utils
- Location: `web/modules/custom/omeka_utils/`
- Handles integration with Omeka services
- Focus on media resource management

### 3. Theme
- Custom theme based on Bootstrap Italia
- Located in `web/themes/custom/italiagov/`
- Requires npm build process for asset compilation

## Development Workflows

### Local Setup
```bash
composer install
docker-compose up -d
docker exec -it dog_php sh
cd web/themes/custom/italiagov
npm install && npm run build:prod
drush cr
drush cim
```

### Common Commands
- Cache rebuild: `drush cr`
- Import config: `drush cim`
- Reset admin password: `drush upwd admin admin`

## Coding Patterns

### CKEditor Plugin Development
1. Create new plugins in `js/ckeditor5_plugins/{pluginName}/src/`
2. Entry point must be `index.js`
3. Use Plugin class extension pattern:
```javascript
export default class MyPlugin extends Plugin {
  static get requires() {
    return [Dependencies];
  }
  
  init() {
    // Plugin initialization
  }
}
```

### Module Development Rules
1. Follow Drupal 10 coding standards
2. Use dependency injection
3. Implement proper configuration schemas
4. Provide migration paths for data changes
5. Document all hooks and services

## Integration Points
1. Omeka Resource Integration:
   - Uses custom UI components in CKEditor
   - Handles media resource embedding
2. Theme Integration:
   - Bootstrap Italia components
   - Custom SASS compilation
3. Database:
   - Uses Drupal's Entity API
   - Avoid direct SQL queries

## Security Considerations
- Always sanitize inputs
- Use Drupal's Form API for user input
- Follow CSRF protection patterns
- Implement proper access controls

## Performance Guidelines
- Implement caching where appropriate
- Use lazy loading for media resources
- Optimize database queries
- Bundle assets efficiently

---
Note: This is a living document. If you find patterns that aren't documented or need clarification, please suggest updates.
