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

## Documentation Guidelines

### README and Documentation Style

Documentation should focus on what the package does, not on the development process or meta-commentary about how it was built.

**Avoid:**
- Development process details (testing methodology, verification processes, implementation details)
- Self-referential language about the work ("we verified", "our testing", "this implementation")
- Meta-commentary about development history or testing approach
- References to comparing against other implementations
- Details about how features were validated or tested

**Focus on:**
- Package features and capabilities
- What the package does, not how it was built
- User-facing functionality and usage
- Clear, direct descriptions of behavior

**Examples:**

Good (feature-focused):
```markdown
## Features

- Reduces token consumption by 30-60% compared to JSON
- Supports nested objects and arrays
- Configurable delimiters and formatting options
```

Bad (meta/process-focused):
```markdown
## Implementation Verification

This implementation has been verified against the original TypeScript version
with 30+ test cases. All outputs match exactly for primitives, objects, arrays,
and edge cases.
```

Good (capability-focused):
```markdown
TOON handles all standard data types including primitives, nested structures,
and special values.
```

Bad (self-referential):
```markdown
We've thoroughly tested this implementation to ensure it handles all data types
correctly.
```
