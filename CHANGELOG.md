# Changelog

All notable changes to `template-renderer` will be documented in this file.

## 2.1.1 - 2026-11-09

### Security

- The `Template` validation rule only reports an `InvalidTemplateException`: any other exception thrown by a driver is a failure
  and is no longer turned into a validation error, which exposed internal error messages to the author of the template.

### Changed

- `TwigDriver::validate()` throws `Comhon\TemplateRenderer\Exceptions\InvalidTemplateException`, with the Twig error as previous.
  Its message is meant for the author of the template, without its internal name (`Filter "sort" is not allowed`), and `getTemplateLine()` gives the line.

## 2.1.0 - 2026-11-07

### Added

- Twig sandbox: the `sandbox` block of a Twig renderer configuration renders templates written by untrusted authors in the Twig sandbox.
  Disabled by default, see the "Security" section of the README.
- `TwigDriver::isSandboxed()`.

### Changed

- `twig/twig` requirement raised to `^3.29` (the sandbox is built on `Twig\Sandbox\Sandbox`).
