# Claude Code Guidelines

This file contains project-specific guidelines for Claude Code when working on this repository.

## Release Guidelines

### Release Titles

Release titles should be version numbers only, with no additional descriptive text.

**Correct:**
- `v1.1.0`
- `v1.0.1`
- `v2.0.0`

**Incorrect:**
- `v1.1.0 - Task Automation & Benchmarks`
- `Version 1.0.0: Initial Release`
- `v1.1.0 (Major Update)`

### Release Notes

Release notes should be factual and straightforward:

- **No emojis** - Use plain text only
- **No hype or marketing language** - Avoid words like "exciting", "amazing", "revolutionary"
- **Be direct and technical** - Focus on what changed, not how great it is
- **Use simple headings** - "New Features", "Bug Fixes", "Changes", not "What's New" or "Highlights"

**Good example:**
```
## Changes in v1.1.0

### Added
- Justfile for task automation
- Token efficiency benchmarks comparing TOON vs JSON vs XML
- PHPDoc documentation for main methods

### Changed
- Benchmark output formatting simplified
```

**Bad example:**
```
## What's New in v1.1.0 🎉

We're excited to announce some amazing new features! 🚀

### ✨ New Features
- Added an incredible justfile that makes development so much easier!
```
