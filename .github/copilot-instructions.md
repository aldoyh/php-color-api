# Color API - AI Agent Instructions

## Project Overview
This is a **GraphQL-based Color API** built with PHP 8.2+ that provides color information, palette generation, and color management. Originally started as a REST API but was redesigned to use GraphQL to align with Frontify's technology stack.

## Architecture & Key Patterns

### GraphQL Implementation
- **Entry Point**: `public/index.php` serves both GraphQL endpoint (`/graphql`) and HTML interface
- **Schema Definition**: `src/schema.php` orchestrates the complete GraphQL schema
- **Type System**: `src/color.php` defines the core `Color` GraphQL type
- **Operations**: Separated into `src/query.php` (read operations) and `src/mutation.php` (write operations)

### Database Strategy
- **PDO-based, pluggable drivers**: The project now uses a small PDO helper in `src/Database.php`. By default the app uses SQLite (`data/colors.db`) but can use MySQL/MariaDB by setting `DB_DRIVER=mysql` and the associated `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` environment variables.
- **Auto-migrations & seeding**: `Database::runMigrations()` creates the `colors` table and seeds it. Seeding prefers `colors.json` when present and falls back to a small default set.

### Color Processing
- **ColorUtils Class**: `src/ColorUtils.php` contains all color conversion logic (hex ↔ RGB ↔ HSL)
- **Palette Generation**: Supports multiple modes: analogous, complementary, triadic, tetradic, shades, tints
- **Validation**: Hex color validation and normalization (handles with/without # prefix)

## Development Workflows

### Local Development
```bash
# Install PHP dependencies
composer install

# Start development server (built-in PHP server)
./run.sh    # picks an available port (8081+) and runs the app

# Or run the server on default port 8080 and use a MySQL backend:
DB_DRIVER=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=colors DB_USERNAME=root DB_PASSWORD=frontify yarn start
```

### Docker Environment
```bash
# Database only
yarn docker:db  # Starts MariaDB on port 3306

# Full stack
yarn docker:run    # docker compose up -d
yarn docker:stop   # docker compose down
```

### Testing
- **PHPUnit**: Tests are configured and a `tests/ColorUtilsTest.php` unit test exists for utility functions.
- Run with: `yarn test` or `php ./vendor/bin/phpunit --colors=always tests/`

## GraphQL API Patterns

### Query Examples
```graphql
# Get color information
query {
  colorInfo(hex: "FF0000") { hex rgb hsl name }
  colorInfo(name: "red") { hex rgb hsl name }
}

# Generate color palette
query {
  palette(baseColor: "2196f3", mode: "analogous") {
    colors { hex rgb hsl name }
  }
}

# List saved colors
query {
  allColors { hex name rgb hsl }
}
```

### Mutation Examples
```graphql
mutation {
  saveColor(name: "Custom Blue", hex: "2196f3") {
    hex name rgb hsl
  }
}
```

## File Structure Conventions

### Source Organization
- `src/schema.php`: Central schema definition and database utilities
- `src/query.php`: All GraphQL queries (colorInfo, palette, allColors)
- `src/mutation.php`: All GraphQL mutations (saveColor)
- `src/color.php`: GraphQL type definitions
- `src/ColorUtils.php`: Pure color manipulation utilities

### Data Files
- `colors.json`: Static color collection for HTML interface
- `data/colors.db`: SQLite database (auto-created)
- `templates/home.html`: HTML template with `{{colors}}` placeholder

## Important Implementation Notes

### CORS Handling
Manual CORS headers in `public/index.php` - required for GraphQL client integration. The allowed headers include `Content-Type` and `Authorization`.
```php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
```

### Database Connection Pattern
- Uses a PDO singleton via `\Frontify\ColorApi\Database::getConnection()` (compatibility wrapper `getDatabase()` still returns a PDO instance).
- Migrations and seeding are performed in `Database::runMigrations()` during schema load.
- All query code should prefer prepared statements (the existing GraphQL mutations/queries were updated to use PDO prepared statements).

### Error Handling
- GraphQL errors thrown as PHP exceptions
- Basic validation in ColorUtils class
- Database errors logged but not always propagated to GraphQL layer

### Development Discrepancies / Notes
- Docker now supports building the PHP container with Composer installed (see `Dockerfile`) and PDO extensions enabled. The provided `docker-compose.yml` still references a MariaDB service; you can switch to MySQL by setting `DB_DRIVER=mysql` and providing DB env vars to the backend service.
- `package.json` contains helper scripts for starting and building containers but primary code is PHP.
- Tests were added for `ColorUtils`.

## Integration Points
- **Frontend Interface**: HTML served at root path with color grid using `colors.json`
- **GraphQL Endpoint**: `/graphql` accepts both GET and POST requests
 - **GraphQL Endpoint**: `/graphql` accepts both GET and POST requests
 - **Developer UI**: GraphiQL is available at `/graphiql` for local development (served by `templates/graphiql.html`).
- **Database**: SQLite file storage (not the MySQL from docker-compose.yml)

When making changes, prioritize GraphQL API consistency and ensure ColorUtils class remains the single source of truth for color calculations.