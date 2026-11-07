# Changelog

All notable changes to `template-renderer` will be documented in this file.

## Unreleased

### Added

- Twig sandbox: the `sandbox` block of a Twig renderer configuration renders templates written by untrusted authors in the Twig sandbox.
  Disabled by default, see the "Security" section of the README.
- `TwigDriver::isSandboxed()`.

### Changed

- `twig/twig` requirement raised to `^3.29` (the sandbox is built on `Twig\Sandbox\Sandbox`).
