---
description: Simplify blade template to low-fidelity prototype
---

Simplify as `$ARGUMENTS` to a low-fidelity prototype for wireframing.

## Transformation Rules

1. **Replace Flux Callouts**: Convert `flux:callout` components to plain `<div>` with `flux:heading` and `flux:text` inside
   - Remove all `icon` and `color` attributes
   - Add `class="mt-2"` to `flux:text` for spacing from heading

2. **Remove Button Variants**: Strip `variant="primary"` and `variant="ghost"` unless functionally required

3. **Remove Icons**: Delete all `icon` attributes from buttons and callouts

4. **Simplify Typography**: Replace custom heading classes (e.g., `text-xl font-semibold`) with `flux:heading`

5. **Remove Visual Styling**:
   - No backgrounds (`bg-*`)
   - No borders (`border`, `rounded-*`)
   - No padding on sections (`p-*`)
   - No colors (`text-zinc-600`, etc.)

6. **Standardize Spacing**:
   - Use `mt-8` for spacing between major sections/blocks
   - Use `mt-2` for spacing between heading and text
   - Use `mb-8` for page header bottom margin

7. **Simplify Code Blocks**: Keep only `text-sm` class, remove all other styling

8. **Container Width**: Add `max-w-lg` to root container if not present

9. **Remove Duplicate Sections**: Keep only essential content (e.g., one code block instead of two)

The goal is minimal, functional wireframe with only spacing-related Tailwind classes. Keep all Livewire logic, Alpine.js, and Flux button components intact.
