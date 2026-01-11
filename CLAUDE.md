# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Thelia 3** e-commerce project using the **dev-twig** branch (Twig templating replaces Smarty). The project uses DDEV for containerization and supports multiple frontOffice templates.

- **CMS**: Thelia 3 (dev-twig branch)
- **Framework**: Symfony 6.4
- **PHP**: 8.3
- **Database**: MySQL 8.0
- **Containerization**: DDEV (NOT Docker Compose or Make)
- **Active Template**: `nova` (configurable via `.env.local`)
- **Project URL**: https://thelia3-moderna.ddev.site

## Critical: DDEV Commands Only

**⚠️ NEVER use standard PHP/Composer/npm commands directly. ALL commands MUST go through DDEV.**

### Core Commands

```bash
# Start/Stop
ddev start                              # Start the project
ddev stop                               # Stop containers
ddev restart                            # Restart all services

# Thelia Console (NOT bin/console!)
ddev exec php Thelia <command>          # Run Thelia commands
ddev exec php Thelia cache:clear        # Clear cache

# Composer
ddev composer install                   # Install dependencies
ddev composer require <package>         # Add package
ddev composer update                    # Update dependencies

# Database
ddev mysql                              # MySQL CLI access
ddev import-db --file=dump.sql          # Import database
ddev export-db --file=backup.sql        # Export database

# Logs
ddev logs                               # All container logs
ddev logs -f                            # Follow logs
```

### Working with npm in Templates

**CRITICAL**: The `cd` command on the host does NOT affect the directory inside the container.

```bash
# ❌ WRONG - This does NOT work
cd templates/frontOffice/moderna && ddev exec npm install

# ✅ CORRECT - Navigate INSIDE the container
ddev exec bash -c "cd templates/frontOffice/moderna && npm install"
ddev exec bash -c "cd templates/frontOffice/moderna && npm run build"
ddev exec bash -c "cd templates/frontOffice/nova && npm run dev"
```

### Cache Management

```bash
# Method 1: Thelia command (may fail)
ddev exec php Thelia cache:clear

# Method 2: Direct removal (reliable)
ddev exec rm -rf var/cache/*
```

## Project Structure

```
.
├── .ddev/                      # DDEV configuration
│   └── config.yaml            # Main DDEV config
├── .env                       # Default environment (committed)
├── .env.local                 # Local overrides (gitignored) - MODIFY THIS
├── composer.json              # Thelia + modules dependencies
├── config/                    # Symfony configuration
├── local/                     # Local modules and setup
│   └── modules/              # Custom Thelia modules
├── public/                    # Web root (docroot)
├── src/                       # Custom application code
│   ├── ApiResource/          # API Platform resources
│   ├── Controller/           # Custom controllers
│   └── Kernel.php           # Symfony kernel
├── templates/                 # Thelia templates
│   ├── frontOffice/
│   │   ├── flexy/           # Default template (in vendor)
│   │   ├── moderna/         # Custom template
│   │   └── nova/            # Custom template (active)
│   ├── backOffice/          # Admin templates
│   ├── email/               # Email templates
│   └── pdf/                 # PDF templates
├── var/                       # Cache, logs, sessions
│   └── cache/propel/        # Propel ORM models
└── vendor/                    # Dependencies
    └── thelia/               # Thelia core + modules
```

## Configuration Files

### Environment Variables

| File | Purpose | Version Control |
|------|---------|----------------|
| `.env` | Default values for all environments | Committed |
| `.env.local` | **Local overrides** - MODIFY THIS ONE | Gitignored |
| `.env.dev` | Development-specific | Optional |

**To change the active template:**

```bash
# Edit .env.local (NOT .env)
echo "ACTIVE_FRONT_TEMPLATE=moderna" >> .env.local
ddev exec rm -rf var/cache/*
ddev restart
```

### Key Environment Variables

```bash
APP_ENV=dev                        # Environment (dev/prod)
ACTIVE_FRONT_TEMPLATE=nova         # Active frontOffice template
DATABASE_HOST=db                   # DDEV database host
DATABASE_NAME=db                   # Database name
DATABASE_USER=db                   # Database user
DATABASE_PASSWORD=db               # Database password
```

## Template Development

### Installing a New Template

1. Place template in `templates/frontOffice/<template-name>/`
2. Run setup SQL if provided:
   ```bash
   ddev mysql < templates/frontOffice/<template-name>/setup/install.sql
   ```
3. Install npm dependencies:
   ```bash
   ddev exec bash -c "cd templates/frontOffice/<template-name> && npm install && npm run build"
   ```
4. Activate in `.env.local`:
   ```bash
   ACTIVE_FRONT_TEMPLATE=<template-name>
   ```
5. Clear cache:
   ```bash
   ddev exec rm -rf var/cache/*
   ```

### Template Structure

Templates follow the Flexy architecture:
- `assets/` - Source files (JS, SCSS)
- `dist/` - Built files (generated, don't commit)
- `src/` - PHP components (PSR-4: `FlexyBundle\`)
- `templates/` - Twig templates
- `package.json` - npm configuration
- `webpack.config.js` - Build configuration

## Thelia-Specific Commands

```bash
# Module management
ddev exec php Thelia module:refresh                    # Refresh module list
ddev exec php Thelia module:activate <ModuleName>      # Activate module
ddev exec php Thelia module:deactivate <ModuleName>    # Deactivate module

# Admin user
ddev exec php Thelia admin:create \
  --login_name admin \
  --password admin \
  --last_name Admin \
  --first_name System \
  --email admin@example.com

# Database
ddev exec php Thelia thelia:dev:reloadDB -f           # Reload database (dev)
ddev exec php Thelia thelia:generate:model            # Generate Propel models

# Cache
ddev exec php Thelia cache:clear                      # Clear all caches
ddev exec php Thelia thelia:assets:install            # Install web assets
```

## Development Workflow

### Starting Development

```bash
ddev start                                            # Start containers
ddev composer install                                 # Install PHP deps
ddev exec bash -c "cd templates/frontOffice/nova && npm install"  # Install npm deps
ddev exec rm -rf var/cache/*                         # Clear cache
```

### Making Changes

1. **Backend (PHP)**:
   - Edit files in `src/`, `local/modules/`, or vendor modules
   - Clear cache: `ddev exec rm -rf var/cache/*`
   - Regenerate models if needed: `ddev exec php Thelia thelia:generate:model`

2. **Frontend (Templates)**:
   - Edit files in `templates/frontOffice/<template>/`
   - Rebuild assets:
     ```bash
     ddev exec bash -c "cd templates/frontOffice/<template> && npm run build"
     ```
   - Or use watch mode:
     ```bash
     ddev exec bash -c "cd templates/frontOffice/<template> && npm run dev"
     ```

### Common Issues

**Issue**: Template not updating
```bash
# Solution: Clear cache and rebuild
ddev exec rm -rf var/cache/*
ddev exec bash -c "cd templates/frontOffice/nova && npm run build"
```

**Issue**: Database connection errors
```bash
# Solution: Check database is running
ddev describe
ddev restart
```

**Issue**: npm install fails
```bash
# Solution: Navigate inside container
ddev exec bash -c "cd templates/frontOffice/nova && npm install"
```

## Architecture Notes

### Thelia Core Architecture

- **Propel ORM**: Used for database (not Doctrine)
- **Event Dispatcher**: Extensive event system for hooks
- **Module System**: Extensible via modules in `local/modules/` or `vendor/thelia/modules/`
- **Multi-template**: Support for multiple themes per project

### Custom Code Locations

- `src/` - Custom Symfony components (controllers, API resources)
- `local/modules/` - Custom Thelia modules
- `templates/frontOffice/<name>/src/` - Template-specific PHP components
- `config/` - Symfony configuration overrides

### Autoloading

From `composer.json`:
```json
"autoload": {
    "psr-4": {
        "": ["local/modules/", "vendor/thelia/modules"],
        "App\\": "src/",
        "FlexyBundle\\": "templates/frontOffice/moderna/src/",
        "TheliaMain\\": "var/cache/propel/database/TheliaMain"
    }
}
```

## Testing

```bash
# PHPUnit tests (if configured)
ddev exec vendor/bin/phpunit

# Functional tests
ddev exec php Thelia thelia:dev:test
```

## Debugging

```bash
# Enable Xdebug (if needed)
ddev xdebug on
ddev xdebug off

# View logs
ddev logs                           # All logs
ddev logs -f web                    # Follow web container logs
ddev exec tail -f var/log/*.log     # Application logs

# Database queries
ddev mysql -e "SHOW TABLES"
ddev mysql < query.sql
```

## Production Deployment

**Note**: DDEV is for development only. For production:

1. Use standard PHP/Composer without DDEV
2. Set `APP_ENV=prod` in `.env.local`
3. Build template assets: `cd templates/frontOffice/<template> && npm run build`
4. Clear cache: `php Thelia cache:clear --env=prod`
5. Optimize Composer: `composer install --no-dev --optimize-autoloader`

## Additional Resources

- Thelia Documentation: http://doc.thelia.net
- DDEV Documentation: https://docs.ddev.com/
- Installation Guide: `.claude/INSTRUCTIONS-INSTALLATION.md`
- Symfony Version: 6.4 (check `composer.json`)

## Code Style & Conventions

This project follows Symfony coding standards. See `.claude/rules/` for detailed conventions on:
- Architecture patterns (Clean Architecture, DDD)
- Code standards (PSR-12, PHPStan, etc.)
- Testing practices (TDD/BDD)
- Security and performance guidelines

**Key conventions**:
- Code in English (classes, methods, variables)
- Documentation in French
- UI messages in French
- Follow SOLID principles
- TDD methodology required for new features
