# Community Edition cutover inventory

Target: a self-contained MIT-licensed Community Edition, with enterprise modules removed rather than unlocked.

## Recovery point

The working tree was clean before this work. A complete tracked-source snapshot of HEAD is saved at `/tmp/paspapan-before-community.tar`. No existing database data or migration history will be deleted.

## Applied preparation

Attendance, payroll, reporting, and audit contracts now bind directly to the existing readable community implementations. Service resolution no longer attempts to decrypt enterprise implementations.

## Community Edition priority override

Updated 17 November 2026: features **4 (attendance analytics), 5 (appraisal/KPI), 7 (employee import/export), and 8 (attendance import/export and reports)** are the first Community Edition development priorities, in that order.

The secured artifacts listed below for these features are replacement targets, not instructions to remove the features. Preserve their shared models, data, routes, and UI contracts until readable replacements are implemented and verified. Analytics/appraisal/reporting license gates must be removed only alongside usable CE implementations; keep authorization and company isolation.

See [Community Edition priorities and acceptance criteria](./community-edition-priorities.md). Other enterprise modules remain outside this first implementation scope.

## Proposed removal scope

The following list inventories secured PHP artifacts. For the four CE priorities above, replace secured implementations while retaining functionality. For other enterprise modules, removal remains proposed and has not been executed. Preserve shared models and historical migrations to avoid deleting existing records.

- `app/Http/Controllers/Admin/OperationalHealthController.php`
- `app/Http/Controllers/Admin/Toko/DownloadTokoDeliveryLetterPdfController.php`
- `app/Http/Controllers/Admin/Toko/DownloadTokoInvoicePdfController.php`
- `app/Http/Controllers/Admin/Toko/DownloadTokoQuotationPdfController.php`
- `app/Http/Controllers/Admin/Toko/ExportTokoTransactionsCsvController.php`
- `app/Http/Controllers/Admin/Toko/PrintTokoProductBarcodesController.php`
- `app/Http/Controllers/Admin/Toko/PrintTokoStockAdjustmentReportController.php`
- `app/Jobs/ProcessActivityLogExportRun.php`
- `app/Jobs/ProcessAttendanceExportRun.php`
- `app/Jobs/ProcessAttendanceImportRun.php`
- `app/Jobs/ProcessEmployeeDocumentUpload.php`
- `app/Jobs/ProcessMonthlyAttendanceReportRun.php`
- `app/Jobs/ProcessUserExportRun.php`
- `app/Jobs/ProcessUserImportRun.php`
- `app/Jobs/RunSystemBackup.php`
- `app/Livewire/Admin/AnalyticsDashboard.php`
- `app/Livewire/Admin/ApiIntegrationManager.php`
- `app/Livewire/Admin/AppraisalManager.php`
- `app/Livewire/Admin/AssetManager.php`
- `app/Livewire/Admin/DocumentTemplateLibrary.php`
- `app/Livewire/Admin/DocumentTemplateManager.php`
- `app/Livewire/Admin/EmployeeDocumentRequestManager.php`
- `app/Livewire/Admin/HrChecklistManager.php`
- `app/Livewire/Admin/ImportExport/Attendance.php`
- `app/Livewire/Admin/ImportExport/User.php`
- `app/Livewire/Admin/PayrollManager.php`
- `app/Livewire/Admin/PayrollSettings.php`
- `app/Livewire/Admin/SystemMaintenance.php`
- `app/Livewire/Admin/TokoPosAddon.php`
- `app/Livewire/Admin/UserSessionManager.php`
- `app/Livewire/Finance/Concerns/ManagesCashAdvances.php`
- `app/Livewire/User/EmployeeDocumentRequestPage.php`
- `app/Livewire/User/Finance/MyCashAdvances.php`
- `app/Livewire/User/MyPayslips.php`
- `app/Notifications/EmployeeDocumentRequestStatusUpdated.php`
- `app/Services/AppraisalService.php`
- `app/Services/Attendance/EnterpriseService.php`
- `app/Services/Audit/EnterpriseAuditService.php`
- `app/Services/Payroll/EnterprisePayrollService.php`
- `app/Services/Reporting/EnterpriseReportingService.php`
- `app/Support/EmployeeDocumentPdfFactory.php`
- `app/Support/EmployeeDocumentRequestService.php`
- `app/Support/EnterpriseRuntime.php`
- `app/Support/ImportExportRunService.php`
- `app/Support/SpreadsheetInspectionService.php`
- `app/Support/SystemBackupService.php`
- `app/Support/SystemMaintenanceActionService.php`
- `app/Support/SystemMaintenanceViewService.php`
- `app/Support/TokoLegacyImportPreviewService.php`
- `app/Support/TokoPosCutoverArchiveService.php`
- `app/Support/TokoPosCutoverReadinessService.php`
- `app/Support/TokoPosDeliveryLetterService.php`
- `app/Support/TokoPosInventoryAdjustmentService.php`
- `app/Support/TokoPosPurchaseService.php`
- `app/Support/TokoPosQuotationService.php`
- `app/Support/TokoPosReportService.php`
- `app/Support/TokoPosSalesService.php`
- `app/Support/UserCashAdvanceService.php`
- `app/Support/UserPayslipService.php`

## Other integration changes required

- Remove LicenseGuard, EnterpriseRuntime, hardware-ID command, license middleware, runtime exception handlers, enterprise support endpoint, and demo edition toggle.
- Replace edition entitlement checks with explicit availability checks for modules actually shipped in Community Edition; keep RBAC and company scopes.
- Replace analytics, appraisal/KPI, employee import/export, and attendance reporting routes/navigation with CE paths after implementation. Removal of other enterprise routes/navigation remains a separate proposed change.
- Remove license configuration, license settings UI, upgrade dialogs, and encrypted artifact bootstrap from HTTP and CLI entry points.
- Replace enterprise packaging documentation, generated-code exemptions, and CI boundary validation with an open-source source-availability check.
- Keep MIT copyright notices and third-party dependency licenses.

## Required verification

- Composer autoload/package discovery without enterprise keys.
- Route registration and Blade compilation.
- Authenticated community dashboard, attendance, and settings tests.
- No secured runtime payloads or runtime license dependencies in the distributed application.
- Full test suite review: distinguish removed enterprise behavior from community regressions.

## Current status

Preparation only. Enterprise files have not been deleted. Full cutover remains pending approval after automatic review rejected the initial broad removal.

