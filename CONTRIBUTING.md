# Contributing to Kwik AI

Thanks for your interest in improving Kwik AI! This document covers how to get a
development environment running, the coding standards we follow, and how to submit
changes.

## Getting started

You'll need PHP 7.4+, Node.js, Composer, and a local WordPress install.

```bash
# Clone into your WordPress plugins directory
git clone https://github.com/Draggable/kwik-ai.git
cd kwik-ai

# Install dev dependencies (also installs the git hooks via lefthook)
composer install
npm install
```

## Building assets

Only the Gutenberg description block is bundled with webpack (`assets/src` →
`assets/js`). The other JavaScript files in `assets/js` are hand-written and shipped
as-is.

```bash
npm run build      # production build
npm run dev        # watch mode for development
```

## Tests and linting

```bash
composer test      # PHPUnit
composer lint      # PHP_CodeSniffer (WordPress standard)
composer lint:fix  # auto-fix what phpcbf can
```

Please make sure `composer test` and `composer lint` pass before opening a pull
request.

## Commit messages

This project uses [Conventional Commits](https://www.conventionalcommits.org/) and
releases are automated with semantic-release. Commit messages are validated by
commitlint via a git hook, so use prefixes like:

- `feat: ...` for new features (minor release)
- `fix: ...` for bug fixes (patch release)
- `docs: ...`, `chore: ...`, `refactor: ...`, `test: ...` for non-release changes

A `feat!:` or a `BREAKING CHANGE:` footer triggers a major release.

## Pull requests

1. Fork the repo and create a topic branch off `main`.
2. Make your change, with tests where it makes sense.
3. Ensure tests and linting pass.
4. Open a PR describing **what** changed and **why**.

## Reporting bugs and requesting features

Open an issue on GitHub. For **security** issues, do not open a public issue —
see [SECURITY.md](SECURITY.md) instead.

## License

By contributing, you agree that your contributions will be licensed under the
GPL-2.0-or-later license that covers this project.
