# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [0.1] - 2026-04-07

### Added
- Driver-based OG image rendering with Cloudflare Browser Rendering and Browsershot support
- Built-in Blade templates (`basic`, `dark`, `minimal`) with automatic text fitting
- Queue-first image generation via Laravel's queue system
- Static file serving on any Laravel filesystem disk
- Automatic lazy generation via `url()` with `generate_on_access`
- Stale image refresh after configurable threshold (`refresh_after_days`)
- Named variants for different image dimensions (e.g. Twitter, square)
- Signed route protection for template rendering
- Eloquent model support with automatic deterministic key derivation
- Synchronous rendering via `render()` for streaming or custom storage
