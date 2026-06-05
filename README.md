# PHP-MVP Healthcare Project

This repository contains the foundational API backend for the PHP-MVP Healthcare Project.

## Architecture

This project strictly follows the **Controller → Service → Repository → MySQL** pattern.

- **Controllers**: Handle HTTP requests, responses, request validation, and encryption/decryption. They do NOT contain business logic.
- **Services**: Contain the core business logic. They process data and orchestrate calls to multiple Repositories.
- **Repositories**: Handle direct interactions with the MySQL database via PDO.

## Team Workflow

- Developers work **only** in feature branches.
- **Never** push directly to `main`.
- Create Pull Requests for review.
- Team Lead reviews and merges changes into `main`.

### Git Workflow Example

```bash
# Ensure you are on main and up to date
git checkout main
git pull origin main

# Create a new feature branch
git checkout -b feature/auth

# ... make your code changes ...

# Commit and push
git add .
git commit -m "implemented login"
git push origin feature/auth
```

## Security Flow
- All requests and responses are AES-256-CBC encrypted.
- CSRF protection is implemented via `$_SESSION`.
- Short-lived JWT Access Tokens are stored in `$_SESSION`.
- Long-lived Refresh Tokens are stored in the database.
