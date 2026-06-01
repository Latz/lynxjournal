# Plan: Remove Run Now and Preview buttons from scheduler page

## Context

The Run Now and Preview buttons were removed from the scheduler page to simplify the UI.

## Changes — `src/schedule/App.jsx`

- Removed `runBusy`, `runResult`, `previewBusy`, `previewResult`, `confirmRun` state declarations
- Removed `handleRunNow()` and `handlePreview()` functions
- Removed `renderRunResult()` and `renderPreviewResult()` functions
- Removed Run Now button group (confirm/cancel flow), Preview button, and their `{' '}` separators from the actions div
- Removed `{renderRunResult()}` and `{renderPreviewResult()}` JSX calls
- Removed unused `sprintf` from `@wordpress/i18n` import
- Removed stray `setConfirmRun(false)` call from `handleSave`
